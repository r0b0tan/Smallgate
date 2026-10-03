<?php

namespace App\Services\Previews;

/**
 * A directory asked for without its trailing slash. Sending the browser to
 * the slashed path keeps the draft's relative links pointing into it.
 */
final readonly class PreviewRedirect
{
    public function __construct(
        /** Absolute path on the preview host, already URL-encoded. */
        public string $location,
    ) {}
}
