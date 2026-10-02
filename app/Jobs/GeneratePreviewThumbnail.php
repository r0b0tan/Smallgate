<?php

namespace App\Jobs;

use App\Enums\ThumbnailStatus;
use App\Models\Preview;
use App\Services\Previews\PreviewScreenshotter;
use App\Services\Previews\ThumbnailFailed;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Takes the screenshot for exactly one version of a preview.
 *
 * Queued on provisioning and on an administrator's explicit request -- never on
 * a page view. The version is part of the job: if the preview has moved on by
 * the time the job runs, the job does nothing, and a result is only ever
 * stored against the version it shows.
 *
 * A failure is not an error for the customer: the card falls back to a
 * placeholder and the preview itself stays reachable.
 */
class GeneratePreviewThumbnail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $previewId,
        public readonly int $version,
    ) {}

    public static function for(Preview $preview): void
    {
        if (config('previews.thumbnails.enabled')) {
            static::dispatch($preview->id, $preview->version);
        }
    }

    public function uniqueId(): string
    {
        return $this->previewId.':'.$this->version;
    }

    public function handle(PreviewScreenshotter $screenshotter): void
    {
        $preview = Preview::query()->find($this->previewId);

        if ($preview === null || $preview->version !== $this->version) {
            return;
        }

        $disk = Storage::disk(config('previews.thumbnails.disk'));
        $directory = config('previews.thumbnails.directory').'/'.$preview->id;
        // A fresh name per attempt: the file a customer may be loading right
        // now is never overwritten underneath them.
        $path = $directory.'/v'.$this->version.'-'.Str::lower((string) Str::ulid()).'.jpg';

        $disk->makeDirectory($directory);

        try {
            $screenshotter->capture($preview, $disk->path($path));
        } catch (ThumbnailFailed $failure) {
            $disk->delete($path);
            $this->markFailed($failure->getMessage());

            return;
        }

        // Only store the result while the preview is still at this version.
        $stored = $this->currentVersion()->update([
            'thumbnail_status' => ThumbnailStatus::Ready->value,
            'thumbnail_version' => $this->version,
            'thumbnail_path' => $path,
            'thumbnail_generated_at' => Carbon::now(),
        ]);

        if ($stored === 0) {
            $disk->delete($path);

            return;
        }

        // The new picture replaces every older one of this preview.
        foreach ($disk->files($directory) as $file) {
            if ($file !== $path) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Called by the worker when the job dies outside handle(), e.g. on timeout.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception instanceof ThumbnailFailed ? $exception->getMessage() : 'job_failed');
    }

    private function markFailed(string $reason): void
    {
        // Ids and a reason code only: no target, no URL, no customer data.
        Log::warning('Preview thumbnail could not be generated.', [
            'preview_id' => $this->previewId,
            'version' => $this->version,
            'reason' => $reason,
        ]);

        $this->currentVersion()->update(['thumbnail_status' => ThumbnailStatus::Failed->value]);
    }

    /**
     * The preview row, as long as it is still at this job's version. A plain
     * query builder on purpose: thumbnail state must not move updated_at,
     * which is what tells the administrator the configuration has drifted
     * from what was last provisioned.
     */
    private function currentVersion(): Builder
    {
        return Preview::query()
            ->whereKey($this->previewId)
            ->where('version', $this->version)
            ->toBase();
    }
}
