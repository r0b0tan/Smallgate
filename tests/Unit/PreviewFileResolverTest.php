<?php

/**
 * The resolver is the only thing between a request path on a preview host and
 * the filesystem (ADR 0003). These tests build a real directory tree, symlinks
 * included, because realpath() is part of what is under test.
 */

use App\Services\Previews\PreviewFile;
use App\Services\Previews\PreviewFileResolver;
use App\Services\Previews\PreviewRedirect;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/smallgate-resolver-'.bin2hex(random_bytes(6));
    $this->root = $this->base.'/site';

    $files = [
        'site/index.html' => '<h1>Start</h1>',
        'site/style.css' => 'body{}',
        'site/about/index.html' => '<h1>Über uns</h1>',
        'site/Bilder und Mehr/index.html' => '<h1>Galerie</h1>',
        'site/assets/LOGO.PNG' => 'png',
        'site/assets/data.bin' => 'binary',
        'site/.env' => 'APP_KEY=secret',
        'site/.git/config' => '[core]',
        'site/__smallgate/zugang.html' => 'reserved',
        'secret.txt' => 'outside',
        'site-secret/a.txt' => 'sibling with shared prefix',
    ];

    foreach ($files as $path => $content) {
        @mkdir(dirname($this->base.'/'.$path), 0777, true);
        file_put_contents($this->base.'/'.$path, $content);
    }

    mkdir($this->root.'/empty');
    symlink($this->base.'/secret.txt', $this->root.'/link-out.txt');
    symlink($this->base.'/site-secret', $this->root.'/dir-out');
    symlink($this->root.'/style.css', $this->root.'/link-in.css');
    symlink($this->root.'/.env', $this->root.'/link-hidden.txt');

});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->base);
});

function resolved(string $root, string $path): PreviewFile|PreviewRedirect|null
{
    return (new PreviewFileResolver)->resolve($root, $path);
}

it('serves a file with the type from its extension', function () {
    $file = resolved($this->root, 'style.css');

    expect($file)->toBeInstanceOf(PreviewFile::class)
        ->and($file->path)->toBe(realpath($this->root.'/style.css'))
        ->and($file->mimeType)->toBe('text/css; charset=UTF-8')
        ->and($file->attachment)->toBeFalse();
});

it('serves index.html for the root and for a directory with trailing slash', function (string $path, string $expected) {
    $file = resolved($this->root, $path);

    expect($file)->toBeInstanceOf(PreviewFile::class)
        ->and($file->path)->toBe(realpath($this->root.'/'.$expected))
        ->and($file->mimeType)->toBe('text/html; charset=UTF-8');
})->with([
    'root' => ['', 'index.html'],
    'directory' => ['about/', 'about/index.html'],
]);

it('redirects a directory without trailing slash to the slashed path', function () {
    expect(resolved($this->root, 'about'))
        ->toEqual(new PreviewRedirect('/about/'))
        ->and(resolved($this->root, 'Bilder und Mehr'))
        ->toEqual(new PreviewRedirect('/Bilder%20und%20Mehr/'));
});

it('matches extensions case-insensitively', function () {
    expect(resolved($this->root, 'assets/LOGO.PNG')->mimeType)->toBe('image/png');
});

it('offers an unknown type as a download', function () {
    $file = resolved($this->root, 'assets/data.bin');

    expect($file->mimeType)->toBe('application/octet-stream')
        ->and($file->attachment)->toBeTrue();
});

it('follows a symlink that stays inside the draft', function () {
    expect(resolved($this->root, 'link-in.css')->path)->toBe(realpath($this->root.'/style.css'));
});

it('finds nothing where there is nothing to serve', function (string $path) {
    expect(resolved($this->root, $path))->toBeNull();
})->with([
    'missing file' => 'missing.html',
    'directory without index' => 'empty/',
    'file with trailing slash' => 'style.css/',
]);

it('rejects paths that try to leave the draft', function (string $path) {
    expect(resolved($this->root, $path))->toBeNull();
})->with([
    'parent' => '../secret.txt',
    'parent after a directory' => 'about/../../secret.txt',
    'parent back inside' => 'about/../style.css',
    'current directory' => './style.css',
    'empty segment' => 'about//index.html',
    'leading slash' => '/style.css',
    'backslash' => 'about\\..\\..\\secret.txt',
    'null byte' => "style.css\0.html",
    'symlinked file outside' => 'link-out.txt',
    'symlinked directory outside' => 'dir-out/a.txt',
]);

it('never decodes the path a second time', function (string $path) {
    expect(resolved($this->root, $path))->toBeNull();
})->with([
    '%2e%2e/secret.txt',
    '%252e%252e/secret.txt',
    '..%2fsecret.txt',
]);

it('rejects hidden files and directories', function (string $path) {
    expect(resolved($this->root, $path))->toBeNull();
})->with([
    '.env',
    '.git/config',
    '.git/',
    'symlink to a hidden file' => 'link-hidden.txt',
]);

it('keeps the reserved prefix to the preview host', function (string $path) {
    expect(resolved($this->root, $path))->toBeNull();
})->with(['__smallgate/zugang.html', '__smallgate/', '__smallgate']);

it('finds nothing when the root itself is missing', function () {
    expect(resolved($this->base.'/does-not-exist', 'index.html'))->toBeNull();
});
