<?php

namespace App\Services\Previews;

/**
 * What PreviewArchiveExtractor wrote: counted while writing, not taken from
 * the archive's own headers.
 */
final readonly class ExtractedArchive
{
    public function __construct(
        public int $files,
        public int $bytes,
    ) {}
}
