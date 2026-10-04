<?php

namespace App\Services\Previews;

/**
 * Deletes a directory tree that Smallgate created itself (ADR 0004): an
 * unpacked draft that failed, or an old upload that is no longer kept.
 *
 * It never follows a symlink. A link anywhere inside is removed as a link and
 * its target is left alone; a $path that is itself a link, or that does not
 * resolve to somewhere strictly inside $within, is refused outright.
 */
class DirectoryRemover
{
    /**
     * @return bool whether the tree is gone
     */
    public function remove(string $path, string $within): bool
    {
        $root = realpath($within);

        if ($root === false || is_link($path) || ! is_dir($path)) {
            return false;
        }

        $real = realpath($path);

        if ($real === false || ! str_starts_with($real, rtrim($root, '/').'/')) {
            return false;
        }

        return $this->removeTree($real);
    }

    private function removeTree(string $directory): bool
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;

            // is_link() first: is_dir() would follow a link to its target.
            if (is_link($path) || ! is_dir($path)) {
                if (! @unlink($path)) {
                    return false;
                }

                continue;
            }

            if (! $this->removeTree($path)) {
                return false;
            }
        }

        return @rmdir($directory);
    }
}
