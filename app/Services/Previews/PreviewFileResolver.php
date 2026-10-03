<?php

namespace App\Services\Previews;

/**
 * The one place that turns a request path on a preview host into a file on
 * disk (ADR 0003). Everything a draft contains is served through here, so this
 * is where path traversal has to stop.
 *
 * The path comes in already URL-decoded by the router and is never decoded
 * again: a second decode would turn "%252e%252e" into "..".
 *
 * Rejected before the filesystem is touched: null bytes, backslashes, empty,
 * "." and ".." segments, hidden files and directories (".env", ".git") and
 * the reserved "__smallgate" prefix. What is left is resolved with realpath()
 * and must still lie inside the resolved root -- that also defeats symlinks
 * pointing out -- and is checked for hidden segments a second time, because a
 * symlink inside the draft may point at one.
 *
 * The MIME type comes from a fixed list of extensions, never from the file's
 * content. Anything else is offered as a download.
 */
class PreviewFileResolver
{
    /**
     * The first segment the preview host keeps for itself, e.g. for exchanging
     * the handoff token. Never served from a draft.
     */
    public const RESERVED_PREFIX = '__smallgate';

    private const MIME_TYPES = [
        'html' => 'text/html; charset=UTF-8',
        'htm' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'mjs' => 'text/javascript; charset=UTF-8',
        'json' => 'application/json',
        'map' => 'application/json',
        'webmanifest' => 'application/manifest+json',
        'txt' => 'text/plain; charset=UTF-8',
        'xml' => 'application/xml',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mp3' => 'audio/mpeg',
        'pdf' => 'application/pdf',
    ];

    /**
     * @param  string  $root  the preview's target directory
     * @param  string  $path  the decoded request path, without leading slash
     */
    public function resolve(string $root, string $path): PreviewFile|PreviewRedirect|null
    {
        $realRoot = realpath($root);

        if ($realRoot === false || ! is_dir($realRoot)) {
            return null;
        }

        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }

        $wantsDirectory = $path === '' || str_ends_with($path, '/');
        $segments = $path === '' ? [] : explode('/', rtrim($path, '/'));

        if (! $this->segmentsAreSafe($segments)) {
            return null;
        }

        $real = realpath($segments === [] ? $realRoot : $realRoot.'/'.implode('/', $segments));

        if ($real === false || ! $this->isInside($real, $realRoot)) {
            return null;
        }

        if (is_dir($real)) {
            if (! $wantsDirectory) {
                return new PreviewRedirect('/'.implode('/', array_map('rawurlencode', $segments)).'/');
            }

            $real = realpath($real.'/index.html');

            if ($real === false || ! $this->isInside($real, $realRoot)) {
                return null;
            }
        } elseif ($wantsDirectory) {
            // "style.css/" is not a directory.
            return null;
        }

        if (! is_file($real) || ! is_readable($real)) {
            return null;
        }

        // A symlink inside the draft may lead to a hidden file or into the
        // reserved prefix, so the resolved path has to pass the same test.
        $resolved = substr($real, strlen(rtrim($realRoot, '/')) + 1);

        if (! $this->segmentsAreSafe(explode('/', $resolved))) {
            return null;
        }

        $mimeType = self::MIME_TYPES[strtolower(pathinfo($real, PATHINFO_EXTENSION))] ?? null;

        return new PreviewFile($real, $mimeType ?? 'application/octet-stream', $mimeType === null);
    }

    /**
     * @param  list<string>  $segments
     */
    private function segmentsAreSafe(array $segments): bool
    {
        if (($segments[0] ?? null) === self::RESERVED_PREFIX) {
            return false;
        }

        foreach ($segments as $segment) {
            // Covers "", "." and ".." as well as every hidden file.
            if ($segment === '' || str_starts_with($segment, '.')) {
                return false;
            }
        }

        return true;
    }

    private function isInside(string $candidate, string $root): bool
    {
        return $candidate === $root || str_starts_with($candidate, rtrim($root, '/').'/');
    }
}
