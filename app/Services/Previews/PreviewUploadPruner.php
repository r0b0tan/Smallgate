<?php

namespace App\Services\Previews;

use App\Enums\PreviewUploadStatus;
use App\Models\Preview;
use Illuminate\Support\Facades\Log;

/**
 * Deletes a preview's old uploaded drafts so the disk does not fill up
 * (ADR 0004). The newest config('previews.uploads.keep') stay. Everything
 * else goes only if it is recorded in preview_uploads, is not what the preview
 * shows right now, and resolves to somewhere inside the preview's own folder.
 * Folders someone put there by hand are never in the table and never touched.
 */
class PreviewUploadPruner
{
    public function __construct(private readonly DirectoryRemover $remover) {}

    public function prune(Preview $preview): void
    {
        $parent = $preview->uploadsDirectory();

        if ($parent === null) {
            return;
        }

        $old = $preview->uploads()
            ->where('status', PreviewUploadStatus::Ready)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->slice(max(1, (int) config('previews.uploads.keep')));

        foreach ($old as $upload) {
            $upload->setRelation('preview', $preview);
            $directory = (string) $upload->directory();

            // After an upload the preview shows the newest one, but an
            // administrator may have pointed it back at an older one by hand.
            if ($directory === $preview->target) {
                continue;
            }

            if (file_exists($directory) && ! $this->remover->remove($directory, $parent)) {
                Log::warning('Old preview upload could not be removed.', ['upload_id' => $upload->id]);

                continue;
            }

            $upload->status = PreviewUploadStatus::Removed;
            $upload->save();
        }
    }
}
