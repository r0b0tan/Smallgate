<?php

namespace App\Services\Previews;

use ZipArchive;

/**
 * Unpacks an uploaded draft (ADR 0004). The archive is input from outside
 * that meets the filesystem, so nothing in it is trusted:
 *
 *  - Never ZipArchive::extractTo(). Every entry is checked first and then
 *    written by this class, one file at a time, with fopen(..., 'x').
 *  - One bad entry rejects the whole archive: absolute paths, drive letters,
 *    backslashes, "..", ".", empty segments, control characters, symlinks and
 *    anything that is neither file nor directory. Nothing is quietly repaired.
 *  - Sizes are counted while writing. The archive's own size fields can lie --
 *    a ZIP may declare ten bytes and deliver gigabytes.
 *  - Hidden entries (.DS_Store, .git/), __MACOSX/ and the preview host's
 *    reserved __smallgate/ are skipped; they would never be served anyway.
 *  - A single folder wrapping everything (dist/, build/) is unwrapped, and the
 *    result must have an index.html at its top.
 *
 * Every rejection removes what was written so far. The reason code and its
 * German message never contain a name from the archive.
 */
class PreviewArchiveExtractor
{
    private const CHUNK = 65536;

    /** Room left on the disk beyond the draft itself. */
    private const SPARE_BYTES = 16 * 1024 * 1024;

    public function __construct(private readonly DirectoryRemover $remover) {}

    /**
     * Unpack $archive into $destination, which must not exist yet; its parent
     * must. On a rejection nothing is left behind.
     *
     * @throws PreviewArchiveRejected
     */
    public function extract(string $archive, string $destination): ExtractedArchive
    {
        $limits = (array) config('previews.uploads');
        $size = @filesize($archive);

        if ($size === false) {
            throw new PreviewArchiveRejected('not_a_zip');
        }

        if ($size > $limits['max_zip_bytes']) {
            throw new PreviewArchiveRejected('zip_too_large');
        }

        $zip = new ZipArchive;

        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new PreviewArchiveRejected('not_a_zip');
        }

        try {
            $entries = $this->plan($zip, $limits);

            $declared = array_sum(array_column($entries, 'declared'));
            $free = @disk_free_space(dirname($destination));

            if ($free !== false && $free < $declared + self::SPARE_BYTES) {
                throw new PreviewArchiveRejected('no_space');
            }

            return $this->write($zip, $entries, $destination, $limits);
        } finally {
            $zip->close();
        }
    }

    /**
     * Check every entry before anything is written.
     *
     * @param  array<string, int>  $limits
     * @return list<array{index: int, segments: list<string>, directory: bool, declared: int}>
     */
    private function plan(ZipArchive $zip, array $limits): array
    {
        if ($zip->count() === 0) {
            throw new PreviewArchiveRejected('empty');
        }

        $entries = [];

        for ($index = 0; $index < $zip->count(); $index++) {
            $stat = $zip->statIndex($index);

            if ($stat === false) {
                throw new PreviewArchiveRejected('corrupt');
            }

            $name = (string) $stat['name'];
            $directory = str_ends_with($name, '/');

            match ($this->typeOf($zip, $index)) {
                'link' => throw new PreviewArchiveRejected('link'),
                'special' => throw new PreviewArchiveRejected('special_file'),
                'directory' => $directory = true,
                'file' => $directory ? throw new PreviewArchiveRejected('special_file') : null,
                default => null,
            };

            $segments = $this->segments($name);

            if (strlen($name) > $limits['max_path_length']) {
                throw new PreviewArchiveRejected('path_too_long');
            }

            if (count($segments) > $limits['max_depth']) {
                throw new PreviewArchiveRejected('too_deep');
            }

            if ($segments[0] === '__MACOSX' || $this->hasHiddenSegment($segments)) {
                continue;
            }

            $entries[] = [
                'index' => $index,
                'segments' => $segments,
                'directory' => $directory,
                'declared' => $directory ? 0 : (int) $stat['size'],
            ];
        }

        $entries = $this->unwrap($entries);

        // After unwrapping: dist/__smallgate/ is as reserved as __smallgate/.
        $entries = array_values(array_filter(
            $entries,
            fn (array $entry) => $entry['segments'] !== [] && $entry['segments'][0] !== PreviewFileResolver::RESERVED_PREFIX,
        ));

        $files = array_filter($entries, fn (array $entry) => ! $entry['directory']);

        if (count($files) > $limits['max_files']) {
            throw new PreviewArchiveRejected('too_many_files');
        }

        // Only an early way out: the real limit is counted while writing.
        if (array_sum(array_column($files, 'declared')) > $limits['max_extracted_bytes']) {
            throw new PreviewArchiveRejected('too_large_unpacked');
        }

        $this->rejectConflicts($entries);

        foreach ($files as $file) {
            if ($file['segments'] === ['index.html']) {
                return $entries;
            }
        }

        throw new PreviewArchiveRejected('no_index');
    }

    /**
     * What the Unix mode bits say the entry is, or null where the archive has
     * none (made on Windows) and the trailing slash decides.
     */
    private function typeOf(ZipArchive $zip, int $index): ?string
    {
        if (! $zip->getExternalAttributesIndex($index, $system, $attributes) || $system !== ZipArchive::OPSYS_UNIX) {
            return null;
        }

        return match (($attributes >> 16) & 0170000) {
            0 => null,
            0100000 => 'file',
            0040000 => 'directory',
            0120000 => 'link',
            default => 'special',
        };
    }

    /**
     * @return list<string>
     */
    private function segments(string $name): array
    {
        if (str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('/^[A-Za-z]:/', $name) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $name) === 1
            || ! mb_check_encoding($name, 'UTF-8')) {
            throw new PreviewArchiveRejected('unsafe_path');
        }

        // A folder's trailing slash, if it has one -- the mode bits may call
        // an entry a folder without it.
        $segments = explode('/', str_ends_with($name, '/') ? substr($name, 0, -1) : $name);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new PreviewArchiveRejected('unsafe_path');
            }
        }

        return $segments;
    }

    /**
     * @param  list<string>  $segments
     */
    private function hasHiddenSegment(array $segments): bool
    {
        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop one folder that wraps everything, as build tools like to produce
     * (dist/index.html). Only when nothing but that folder sits at the top.
     *
     * @param  list<array{index: int, segments: list<string>, directory: bool, declared: int}>  $entries
     * @return list<array{index: int, segments: list<string>, directory: bool, declared: int}>
     */
    private function unwrap(array $entries): array
    {
        if ($entries === []) {
            return [];
        }

        $top = $entries[0]['segments'][0];

        foreach ($entries as $entry) {
            if ($entry['segments'][0] !== $top || (! $entry['directory'] && count($entry['segments']) === 1)) {
                return $entries;
            }
        }

        return array_map(function (array $entry) {
            $entry['segments'] = array_slice($entry['segments'], 1);

            return $entry;
        }, $entries);
    }

    /**
     * The same path twice, or one path as a file and as a folder.
     *
     * @param  list<array{index: int, segments: list<string>, directory: bool, declared: int}>  $entries
     */
    private function rejectConflicts(array $entries): void
    {
        $files = [];
        $directories = [];

        foreach ($entries as $entry) {
            $parents = $entry['directory'] ? $entry['segments'] : array_slice($entry['segments'], 0, -1);

            for ($depth = 1; $depth <= count($parents); $depth++) {
                $directories[implode('/', array_slice($parents, 0, $depth))] = true;
            }

            if (! $entry['directory']) {
                $path = implode('/', $entry['segments']);

                if (isset($files[$path])) {
                    throw new PreviewArchiveRejected('conflict');
                }

                $files[$path] = true;
            }
        }

        if (array_intersect_key($files, $directories) !== []) {
            throw new PreviewArchiveRejected('conflict');
        }
    }

    /**
     * @param  list<array{index: int, segments: list<string>, directory: bool, declared: int}>  $entries
     * @param  array<string, int>  $limits
     */
    private function write(ZipArchive $zip, array $entries, string $destination, array $limits): ExtractedArchive
    {
        if (file_exists($destination) || is_link($destination) || ! @mkdir($destination, 0775)) {
            throw new PreviewArchiveRejected('write_failed');
        }

        $written = 0;
        $files = 0;

        try {
            foreach ($entries as $entry) {
                $parents = $entry['directory'] ? $entry['segments'] : array_slice($entry['segments'], 0, -1);
                $this->makeDirectories($destination, $parents);

                if ($entry['directory']) {
                    continue;
                }

                $written += $this->writeFile(
                    $zip,
                    $entry['index'],
                    $destination.'/'.implode('/', $entry['segments']),
                    $limits['max_extracted_bytes'] - $written,
                );
                $files++;
            }
        } catch (PreviewArchiveRejected $rejection) {
            $this->remover->remove($destination, dirname($destination));

            throw $rejection;
        }

        return new ExtractedArchive($files, $written);
    }

    /**
     * One level at a time, below a destination this class created itself.
     *
     * @param  list<string>  $segments
     */
    private function makeDirectories(string $destination, array $segments): void
    {
        $path = $destination;

        foreach ($segments as $segment) {
            $path .= '/'.$segment;

            if (is_link($path)) {
                throw new PreviewArchiveRejected('write_failed');
            }

            if (is_dir($path)) {
                continue;
            }

            if (! @mkdir($path, 0775)) {
                throw new PreviewArchiveRejected('write_failed');
            }
        }
    }

    /**
     * @return int the bytes written
     */
    private function writeFile(ZipArchive $zip, int $index, string $path, int $budget): int
    {
        $in = $zip->getStreamIndex($index);

        if ($in === false) {
            throw new PreviewArchiveRejected('corrupt');
        }

        // 'x': create, never overwrite, never write through an existing link.
        $out = @fopen($path, 'xb');

        if ($out === false) {
            fclose($in);

            throw new PreviewArchiveRejected('write_failed');
        }

        $written = 0;

        try {
            while (! feof($in)) {
                $chunk = fread($in, self::CHUNK);

                if ($chunk === false) {
                    throw new PreviewArchiveRejected('corrupt');
                }

                $written += strlen($chunk);

                if ($written > $budget) {
                    throw new PreviewArchiveRejected('too_large_unpacked');
                }

                if (fwrite($out, $chunk) !== strlen($chunk)) {
                    throw new PreviewArchiveRejected('write_failed');
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return $written;
    }
}
