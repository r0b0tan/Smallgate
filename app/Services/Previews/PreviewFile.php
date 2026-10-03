<?php

namespace App\Services\Previews;

/**
 * A file of a static preview that may be sent, as PreviewFileResolver found it.
 */
final readonly class PreviewFile
{
    public function __construct(
        /** Absolute, resolved path inside the preview's directory. */
        public string $path,
        public string $mimeType,
        /** Unknown types are offered as a download, never rendered. */
        public bool $attachment,
    ) {}
}
