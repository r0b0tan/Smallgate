<?php

namespace App\Jobs;

use App\Models\Preview;
use App\Models\PreviewUpload;
use App\Models\Project;
use App\Services\Previews\DirectoryRemover;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removes the unpacked drafts of a deleted preview (ADR 0004).
 *
 * The preview and its upload rows are gone by the time this runs, so the
 * request hands over the upload ids it read from the table. Every path is
 * built from those ids and the project's folder -- never taken from a stored
 * string -- and only those folders are deleted. The preview's own folder goes
 * last, and only if nothing else is left in it: whatever someone put there by
 * hand stays.
 */
class RemovePreviewUploads implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 80;

    /**
     * @param  list<string>  $uploadIds
     */
    public function __construct(
        public readonly string $projectId,
        public readonly string $previewId,
        public readonly array $uploadIds,
    ) {}

    public function handle(DirectoryRemover $remover): void
    {
        $disk = Storage::disk(config('previews.uploads.disk'));
        $project = Project::query()->find($this->projectId);
        $parent = $project === null ? null : Preview::uploadsDirectoryFor($project, $this->previewId);

        foreach ($this->uploadIds as $uploadId) {
            // ULIDs only: the id becomes part of a path.
            if (preg_match('/^[0-9a-z]{26}$/', $uploadId) !== 1) {
                continue;
            }

            $disk->delete(PreviewUpload::archivePathFor($uploadId));

            if ($parent === null) {
                continue;
            }

            foreach ([$parent.'/'.$uploadId, PreviewUpload::stagingDirectoryIn($parent, $uploadId)] as $directory) {
                if (file_exists($directory) && ! $remover->remove($directory, $parent)) {
                    Log::warning('Upload of a deleted preview could not be removed.', ['upload_id' => $uploadId]);
                }
            }
        }

        if ($parent !== null && is_dir($parent) && ! is_link($parent)) {
            @rmdir($parent);
        }
    }
}
