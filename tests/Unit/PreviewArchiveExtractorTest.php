<?php

/**
 * Unpacking an uploaded draft (ADR 0004). Every archive here is a real ZIP,
 * built in the test -- including ones with traversal names, symlinks and size
 * fields that lie, which ZipArchive stores exactly as given.
 */

use App\Services\Previews\DirectoryRemover;
use App\Services\Previews\ExtractedArchive;
use App\Services\Previews\PreviewArchiveExtractor;
use App\Services\Previews\PreviewArchiveRejected;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/smallgate-extract-'.bin2hex(random_bytes(6));
    mkdir($this->base.'/work', 0777, true);
    $this->destination = $this->base.'/work/upload';
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->base);
});

/**
 * @param  array<string, string|null>  $entries  name => content, null for a folder
 * @param  array<string, int>  $modes  name => Unix mode bits
 */
function zipOf(string $base, array $entries, array $modes = []): string
{
    $path = $base.'/'.bin2hex(random_bytes(4)).'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($entries as $name => $content) {
        $content === null ? $zip->addEmptyDir(rtrim($name, '/')) : $zip->addFromString($name, $content);
    }

    foreach ($modes as $name => $mode) {
        $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $mode << 16);
    }

    $zip->close();

    return $path;
}

/**
 * Overwrite the uncompressed size an entry declares, in its local header and
 * in the central directory -- what a crafted archive does.
 */
function lieAboutSize(string $zipPath, string $name, int $declared): void
{
    $bytes = file_get_contents($zipPath);

    foreach ([["PK\x03\x04", 22, 26, 30], ["PK\x01\x02", 24, 28, 46]] as [$signature, $sizeAt, $nameLengthAt, $header]) {
        $position = 0;

        while (($position = strpos($bytes, $signature, $position)) !== false) {
            $length = unpack('v', substr($bytes, $position + $nameLengthAt, 2))[1];

            if (substr($bytes, $position + $header, $length) === $name) {
                $bytes = substr_replace($bytes, pack('V', $declared), $position + $sizeAt, 4);
            }

            $position += 4;
        }
    }

    file_put_contents($zipPath, $bytes);
}

function extractInto(string $zipPath, string $destination): ExtractedArchive
{
    return app(PreviewArchiveExtractor::class)->extract($zipPath, $destination);
}

function rejectionOf(string $zipPath, string $destination): string
{
    try {
        extractInto($zipPath, $destination);
    } catch (PreviewArchiveRejected $rejection) {
        return $rejection->reason;
    }

    throw new RuntimeException('The archive was not rejected.');
}

/* ------------------------------------------------------------ what works */

it('unpacks a draft and counts what it wrote', function () {
    $zip = zipOf($this->base, [
        'index.html' => '<h1>Start</h1>',
        'css/app.css' => 'body{}',
        'img/' => null,
        'img/logo.png' => 'png',
    ]);

    $result = extractInto($zip, $this->destination);

    expect($result->files)->toBe(3)
        ->and($result->bytes)->toBe(strlen('<h1>Start</h1>') + strlen('body{}') + strlen('png'))
        ->and(file_get_contents($this->destination.'/index.html'))->toBe('<h1>Start</h1>')
        ->and(file_get_contents($this->destination.'/css/app.css'))->toBe('body{}')
        ->and(is_dir($this->destination.'/img'))->toBeTrue();
});

it('unwraps a single folder around everything', function () {
    $zip = zipOf($this->base, [
        'dist/' => null,
        'dist/index.html' => 'start',
        'dist/assets/app.js' => 'js',
    ]);

    extractInto($zip, $this->destination);

    expect(file_exists($this->destination.'/index.html'))->toBeTrue()
        ->and(file_exists($this->destination.'/assets/app.js'))->toBeTrue()
        ->and(file_exists($this->destination.'/dist'))->toBeFalse();
});

it('keeps a top folder when files sit next to it', function () {
    $zip = zipOf($this->base, ['index.html' => 'start', 'assets/app.js' => 'js']);

    extractInto($zip, $this->destination);

    expect(file_exists($this->destination.'/assets/app.js'))->toBeTrue();
});

it('skips hidden entries, macOS metadata and the reserved prefix', function () {
    $zip = zipOf($this->base, [
        'dist/index.html' => 'start',
        'dist/.DS_Store' => 'x',
        'dist/.git/config' => 'x',
        'dist/__smallgate/zugang' => 'x',
        '__MACOSX/dist/._index.html' => 'x',
    ]);

    $result = extractInto($zip, $this->destination);

    expect($result->files)->toBe(1)
        ->and(scandir($this->destination))->toBe(['.', '..', 'index.html']);
});

/* --------------------------------------------------------- what it refuses */

it('rejects a path that could leave the destination', function (string $name) {
    $zip = zipOf($this->base, ['index.html' => 'start', $name => 'evil']);

    expect(rejectionOf($zip, $this->destination))->toBe('unsafe_path')
        ->and(file_exists($this->destination))->toBeFalse()
        ->and(file_exists($this->base.'/work/evil.txt'))->toBeFalse()
        ->and(file_exists($this->base.'/evil.txt'))->toBeFalse();
})->with([
    'parent' => '../evil.txt',
    'parent further in' => 'assets/../../evil.txt',
    'absolute' => '/tmp/evil.txt',
    'drive letter' => 'C:/evil.txt',
    'backslash' => '..\\evil.txt',
    'current directory' => 'assets/./evil.txt',
    'empty segment' => 'assets//evil.txt',
]);

it('writes legacy byte names as libzip decodes them, inside the destination', function () {
    // Names without the UTF-8 flag are read as CP437: a control byte arrives
    // as a harmless character, while "/" and ".." stay what they are and are
    // checked above. What is checked is exactly what is written.
    $zip = zipOf($this->base, ['index.html' => 'start', "steuer\x01.txt" => 'x', "byte\xff.txt" => 'x']);

    expect(extractInto($zip, $this->destination)->files)->toBe(3)
        ->and(file_exists($this->destination."/steuer\u{263A}.txt"))->toBeTrue()
        ->and(file_exists($this->destination."/byte\u{00A0}.txt"))->toBeTrue()
        ->and(scandir($this->base.'/work'))->toBe(['.', '..', 'upload']);
});

it('rejects a symlink in the archive', function () {
    $zip = zipOf($this->base, ['index.html' => 'start', 'passwd' => '/etc/passwd'], ['passwd' => 0120777]);

    expect(rejectionOf($zip, $this->destination))->toBe('link')
        ->and(file_exists($this->destination))->toBeFalse();
});

it('rejects an entry that is neither file nor folder', function () {
    $zip = zipOf($this->base, ['index.html' => 'start', 'pipe' => ''], ['pipe' => 0010644]);

    expect(rejectionOf($zip, $this->destination))->toBe('special_file');
});

it('rejects a name that is a file and a folder at once', function () {
    $zip = zipOf($this->base, ['index.html' => 'start', 'assets' => 'file', 'assets/app.js' => 'js']);

    expect(rejectionOf($zip, $this->destination))->toBe('conflict')
        ->and(file_exists($this->destination))->toBeFalse();
});

it('rejects a draft without index.html at its top', function (array $entries) {
    expect(rejectionOf(zipOf($this->base, $entries), $this->destination))->toBe('no_index');
})->with([
    'none at all' => [['seite.html' => 'x']],
    'only deeper down' => [['index.html.bak' => 'x', 'sub/index.html' => 'x']],
    'only hidden' => [['.index.html' => 'x']],
]);

it('rejects an archive that is not a zip, or an empty one', function () {
    file_put_contents($this->base.'/fake.zip', 'kein zip');
    // The smallest valid zip: an end of central directory record, no entries.
    file_put_contents($this->base.'/empty.zip', "PK\x05\x06".str_repeat("\0", 18));

    expect(rejectionOf($this->base.'/fake.zip', $this->destination))->toBe('not_a_zip')
        ->and(rejectionOf($this->base.'/missing.zip', $this->destination))->toBe('not_a_zip')
        ->and(rejectionOf($this->base.'/empty.zip', $this->destination))->toBe('empty');
});

/* ------------------------------------------------------------ zip bombs */

it('enforces the limits on files, depth, path length and archive size', function (string $limit, int $value, array $entries, string $reason) {
    config(["previews.uploads.{$limit}" => $value]);

    expect(rejectionOf(zipOf($this->base, $entries), $this->destination))->toBe($reason)
        ->and(file_exists($this->destination))->toBeFalse();
})->with([
    'files' => ['max_files', 2, ['index.html' => 'x', 'a.css' => 'x', 'b.css' => 'x'], 'too_many_files'],
    'depth' => ['max_depth', 2, ['index.html' => 'x', 'a/b/c.css' => 'x'], 'too_deep'],
    'path length' => ['max_path_length', 20, ['index.html' => 'x', 'ein/sehr/langer/pfad.css' => 'x'], 'path_too_long'],
    'zip size' => ['max_zip_bytes', 10, ['index.html' => 'x'], 'zip_too_large'],
    'declared size' => ['max_extracted_bytes', 1000, ['index.html' => str_repeat('A', 2000)], 'too_large_unpacked'],
]);

it('counts what it writes instead of believing the archive', function () {
    config(['previews.uploads.max_extracted_bytes' => 1000]);

    $zip = zipOf($this->base, ['index.html' => 'start', 'bombe.bin' => str_repeat('A', 5000)]);
    lieAboutSize($zip, 'bombe.bin', 10);

    // The archive now claims ten bytes; ZipArchive still delivers all 5000.
    $archive = new ZipArchive;
    $archive->open($zip, ZipArchive::RDONLY);
    expect($archive->statName('bombe.bin')['size'])->toBe(10);
    $archive->close();

    expect(rejectionOf($zip, $this->destination))->toBe('too_large_unpacked')
        ->and(file_exists($this->destination))->toBeFalse();
});

it('never writes into a destination that already exists', function () {
    mkdir($this->destination);
    file_put_contents($this->destination.'/vorher.txt', 'bleibt');

    expect(rejectionOf(zipOf($this->base, ['index.html' => 'x']), $this->destination))->toBe('write_failed')
        ->and(file_get_contents($this->destination.'/vorher.txt'))->toBe('bleibt');
});

it('names nothing from the archive in its message', function () {
    $zip = zipOf($this->base, ['index.html' => 'x', '../geheimer-kundenname.txt' => 'x']);

    try {
        extractInto($zip, $this->destination);
    } catch (PreviewArchiveRejected $rejection) {
        expect($rejection->germanMessage())->not->toContain('geheimer-kundenname')
            ->and($rejection->getMessage())->not->toContain('geheimer-kundenname');
    }
});

/* -------------------------------------------------------- removing again */

it('removes a tree but never what a link inside points to', function () {
    mkdir($this->base.'/work/tree/sub', 0777, true);
    mkdir($this->base.'/outside');
    file_put_contents($this->base.'/outside/keep.txt', 'bleibt');
    file_put_contents($this->base.'/work/tree/sub/file.txt', 'weg');
    symlink($this->base.'/outside', $this->base.'/work/tree/link-dir');
    symlink($this->base.'/outside/keep.txt', $this->base.'/work/tree/link-file');

    expect((new DirectoryRemover)->remove($this->base.'/work/tree', $this->base.'/work'))->toBeTrue()
        ->and(file_exists($this->base.'/work/tree'))->toBeFalse()
        ->and(file_get_contents($this->base.'/outside/keep.txt'))->toBe('bleibt');
});

it('refuses to remove a link, the root itself or anything outside it', function (Closure $path) {
    mkdir($this->base.'/outside');
    symlink($this->base.'/outside', $this->base.'/work/link');

    expect((new DirectoryRemover)->remove($path($this->base), $this->base.'/work'))->toBeFalse()
        ->and(is_dir($this->base.'/outside'))->toBeTrue()
        ->and(is_dir($this->base.'/work'))->toBeTrue();
})->with([
    'a link' => fn (string $base) => $base.'/work/link',
    'the root itself' => fn (string $base) => $base.'/work',
    'outside the root' => fn (string $base) => $base.'/outside',
    'escaping with ..' => fn (string $base) => $base.'/work/../outside',
]);
