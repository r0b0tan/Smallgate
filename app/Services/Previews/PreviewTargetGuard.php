<?php

namespace App\Services\Previews;

use App\Enums\PreviewTargetType;

/**
 * Decides whether a preview target is acceptable.
 *
 * A preview target is the one field in the whole application that could point
 * the future gateway at an arbitrary file or an arbitrary host, so it gets the
 * strictest treatment: an administrator may only pick a value that the server
 * operator has already allow-listed in config/previews.php, and customers can
 * never influence it at all.
 *
 * Two attack classes are handled here:
 *
 *  - Path traversal. A "static_directory" target is normalised lexically (so
 *    "a/../../etc" collapses before anything touches the filesystem) and must
 *    then sit inside an allowed root. If the path exists it is additionally
 *    resolved with realpath(), which also defeats symlinks pointing out.
 *
 *  - SSRF. An "upstream_url" target must use an allowed scheme and an exactly
 *    allow-listed host. IP literals, credentials and non-default ports are
 *    rejected outright, so the target cannot be pointed at a metadata endpoint
 *    or an internal service.
 */
class PreviewTargetGuard
{
    /**
     * @return string|null a human readable reason, or null when the target is fine
     */
    public function rejectionReason(?PreviewTargetType $type, ?string $target): ?string
    {
        if ($type === null) {
            return 'Es wurde kein Zieltyp angegeben.';
        }

        if ($target === null || trim($target) === '') {
            return 'Es wurde kein Ziel angegeben.';
        }

        if (str_contains($target, "\0")) {
            return 'Das Ziel enthält ungültige Zeichen.';
        }

        return match ($type) {
            PreviewTargetType::StaticDirectory => $this->rejectDirectory($target),
            PreviewTargetType::UpstreamUrl => $this->rejectUpstreamUrl($target),
        };
    }

    public function isAllowed(?PreviewTargetType $type, ?string $target): bool
    {
        return $this->rejectionReason($type, $target) === null;
    }

    // ------------------------------------------------------------ directories

    private function rejectDirectory(string $target): ?string
    {
        $roots = (array) config('previews.allowed_roots', []);

        if ($roots === []) {
            return 'Es ist kein Wurzelverzeichnis für Vorschauen konfiguriert.';
        }

        if (! str_starts_with($target, '/')) {
            return 'Das Zielverzeichnis muss ein absoluter Pfad sein.';
        }

        $candidate = $this->normalisePath($target);

        foreach ($roots as $root) {
            $normalisedRoot = $this->normalisePath((string) $root);

            if (! $this->isInside($candidate, $normalisedRoot)) {
                continue;
            }

            // The lexical check already stops "..", but an existing path may
            // still be a symlink that escapes the root, so resolve it too.
            $real = realpath($candidate);

            if ($real !== false && ! $this->isInside($real, realpath($normalisedRoot) ?: $normalisedRoot)) {
                return 'Das Zielverzeichnis verlässt das freigegebene Wurzelverzeichnis.';
            }

            return null;
        }

        return 'Das Zielverzeichnis liegt außerhalb der freigegebenen Wurzelverzeichnisse.';
    }

    /**
     * Collapse ".", ".." and duplicate separators without touching the
     * filesystem, so a non-existent path can be checked too.
     */
    private function normalisePath(string $path): string
    {
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return '/'.implode('/', $segments);
    }

    private function isInside(string $candidate, string $root): bool
    {
        return $candidate === $root || str_starts_with($candidate, rtrim($root, '/').'/');
    }

    // ----------------------------------------------------------- upstream URLs

    private function rejectUpstreamUrl(string $target): ?string
    {
        $parts = parse_url($target);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return 'Die Upstream-URL ist ungültig.';
        }

        $schemes = (array) config('previews.allowed_upstream_schemes', ['https']);

        if (! in_array(strtolower($parts['scheme']), $schemes, true)) {
            return 'Die Upstream-URL muss HTTPS verwenden.';
        }

        // Credentials in the URL would end up in logs and proxy configuration.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'Die Upstream-URL darf keine Zugangsdaten enthalten.';
        }

        if (isset($parts['port']) && $parts['port'] !== 443) {
            return 'Die Upstream-URL darf keinen abweichenden Port verwenden.';
        }

        $host = strtolower($parts['host']);

        // An IP literal bypasses the whole point of a host allowlist and is the
        // classic way into link-local metadata services.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || str_starts_with($host, '[')) {
            return 'Die Upstream-URL darf keine IP-Adresse verwenden.';
        }

        $allowed = array_map('strtolower', (array) config('previews.allowed_upstream_hosts', []));

        if ($allowed === []) {
            return 'Es sind keine Upstream-Hosts freigegeben.';
        }

        if (! in_array($host, $allowed, true)) {
            return 'Der Upstream-Host ist nicht freigegeben.';
        }

        return null;
    }
}
