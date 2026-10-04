<?php

namespace App\Jobs;

use App\Contracts\PreviewProvisioner;
use App\Enums\ActivityAction;
use App\Enums\PreviewTargetType;
use App\Enums\PreviewUploadStatus;
use App\Models\Activity;
use App\Models\Preview;
use App\Models\PreviewUpload;
use App\Models\User;
use App\Services\Previews\DirectoryRemover;
use App\Services\Previews\PreviewArchiveExtractor;
use App\Services\Previews\PreviewArchiveRejected;
use App\Services\Previews\PreviewTargetGuard;
use App\Services\Previews\PreviewUploadPruner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Unpacks one uploaded draft and points its preview at it (ADR 0004).
 *
 * Queued by the upload form, never run inside the request. The archive goes
 * into a hidden folder the preview host never serves, is renamed into place
 * only once complete, and only then does the preview change -- in a single
 * database update. A live preview gets a new version, exactly as if it had
 * been provisioned; a draft only gets its new target.
 *
 * Afterwards PreviewUploadPruner deletes uploads beyond
 * config('previews.uploads.keep').
 */
class ExtractPreviewUpload implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Well below the queue's retry_after, so no second worker takes the job
    // while this one is still unpacking.
    public int $timeout = 80;

    public bool $failOnTimeout = true;

    // A lost job must not block the preview's next upload forever.
    public int $uniqueFor = 600;

    public function __construct(
        public readonly string $uploadId,
        public readonly ?string $actorId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->uploadId;
    }

    public function handle(
        PreviewArchiveExtractor $extractor,
        PreviewProvisioner $provisioner,
        PreviewTargetGuard $guard,
        DirectoryRemover $remover,
        PreviewUploadPruner $pruner,
    ): void {
        // Claimed in one statement: of two workers, only one unpacks.
        $claimed = PreviewUpload::query()
            ->whereKey($this->uploadId)
            ->where('status', PreviewUploadStatus::Pending)
            ->toBase()
            ->update(['status' => PreviewUploadStatus::Extracting->value]);

        if ($claimed !== 1) {
            return;
        }

        $upload = PreviewUpload::query()->with('preview.project')->findOrFail($this->uploadId);
        $parent = $upload->preview->uploadsDirectory();
        $target = $upload->directory();
        $staging = $upload->stagingDirectory();

        if ($parent === null || $target === null || $staging === null) {
            $this->markFailed('no_directory');

            return;
        }

        if (! $guard->isAllowed(PreviewTargetType::StaticDirectory, $target)) {
            $this->markFailed('not_allowed');

            return;
        }

        if (! $this->makeParent($parent, (string) $upload->preview->project->directoryPath())) {
            $this->markFailed('write_failed');

            return;
        }

        try {
            $result = $extractor->extract($this->archive(), $staging);
        } catch (PreviewArchiveRejected $rejection) {
            $this->markFailed($rejection->reason);

            return;
        }

        // The draft becomes visible here, in one rename, or not at all.
        if (file_exists($target) || is_link($target) || ! @rename($staging, $target)) {
            $remover->remove($staging, $parent);
            $this->markFailed('write_failed');

            return;
        }

        $published = DB::transaction(function () use ($upload, $result, $target, $provisioner) {
            $preview = Preview::query()->with('project')->lockForUpdate()->findOrFail($upload->preview_id);
            $now = Carbon::now();

            $upload->status = PreviewUploadStatus::Ready;
            $upload->size_bytes = $result->bytes;
            $upload->file_count = $result->files;
            $upload->finished_at = $now;
            $upload->save();

            $preview->target = $target;

            // A live preview: the same as "Bereitstellen", so the customer is
            // asked again and the card gets a fresh picture.
            if ($preview->status->isVisitable() && $provisioner->provision($preview)->successful) {
                $preview->provisioned_at = $now;
                $preview->updated_at = $now;
                $preview->publishNewVersion();
                $preview->save();

                return $preview;
            }

            $preview->save();

            return null;
        });

        Activity::record(ActivityAction::PreviewUploaded, $upload->preview, $this->actor(), [
            'files' => $result->files,
            'bytes' => $result->bytes,
        ]);

        if ($published !== null) {
            Activity::record(ActivityAction::PreviewProvisioned, $published, $this->actor(), ['version' => $published->version]);
            GeneratePreviewThumbnail::for($published);
        }

        $this->deleteArchive();

        if (($preview = Preview::query()->with('project')->find($upload->preview_id)) !== null) {
            $pruner->prune($preview);
        }
    }

    /**
     * Called by the worker when the job dies outside handle(), e.g. on timeout.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed('job_failed');
    }

    /**
     * vorschauen/<preview-id> below the project folder, one level at a time,
     * with the same care as CreateProjectDirectory: no symlinks, and the
     * result has to stay inside the project folder.
     */
    private function makeParent(string $parent, string $projectDirectory): bool
    {
        $base = realpath($projectDirectory);

        if ($base === false || ! is_dir($base) || ! str_starts_with($parent, $projectDirectory.'/')) {
            return false;
        }

        $path = $projectDirectory;

        foreach (explode('/', substr($parent, strlen($projectDirectory) + 1)) as $segment) {
            $path .= '/'.$segment;

            if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
                return false;
            }

            if (! is_dir($path) && ! @mkdir($path, 0775) && ! is_dir($path)) {
                return false;
            }
        }

        $real = realpath($parent);

        return $real !== false && str_starts_with($real, $base.'/');
    }

    private function markFailed(string $reason): void
    {
        // Ids and a reason code only: paths carry customer slugs, and names
        // from the archive are the uploader's.
        Log::warning('Preview upload rejected.', [
            'upload_id' => $this->uploadId,
            'reason' => $reason,
        ]);

        $stored = PreviewUpload::query()
            ->whereKey($this->uploadId)
            ->whereIn('status', [PreviewUploadStatus::Pending, PreviewUploadStatus::Extracting])
            ->toBase()
            ->update([
                'status' => PreviewUploadStatus::Failed->value,
                'error' => (new PreviewArchiveRejected($reason))->germanMessage(),
                'finished_at' => Carbon::now(),
            ]);

        $upload = PreviewUpload::query()->with('preview.project')->find($this->uploadId);

        // A job that died halfway leaves its hidden folder; it goes too.
        if ($upload !== null && ($parent = $upload->preview->uploadsDirectory()) !== null) {
            $staging = PreviewUpload::stagingDirectoryIn($parent, $upload->id);

            if (file_exists($staging)) {
                app(DirectoryRemover::class)->remove($staging, $parent);
            }
        }

        $this->deleteArchive();

        if ($stored > 0 && $upload !== null) {
            Activity::record(ActivityAction::PreviewUploadFailed, $upload->preview, $this->actor());
        }
    }

    private function archive(): string
    {
        return Storage::disk(config('previews.uploads.disk'))->path(PreviewUpload::archivePathFor($this->uploadId));
    }

    private function deleteArchive(): void
    {
        Storage::disk(config('previews.uploads.disk'))->delete(PreviewUpload::archivePathFor($this->uploadId));
    }

    private function actor(): ?User
    {
        return $this->actorId === null ? null : User::query()->find($this->actorId);
    }
}
