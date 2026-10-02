<?php

/**
 * The allowlist that keeps preview targets from becoming a path traversal or
 * SSRF primitive. Configured roots and hosts come from phpunit.xml.
 */

use App\Enums\PreviewTargetType;
use App\Services\Previews\PreviewTargetGuard;

beforeEach(function () {
    $this->guard = new PreviewTargetGuard;
});

/* ------------------------------------------------------------ directories */

it('accepts a directory inside an allowed root', function (string $target) {
    expect($this->guard->isAllowed(PreviewTargetType::StaticDirectory, $target))->toBeTrue();
})->with([
    '/srv/previews',
    '/srv/previews/holzmann',
    '/srv/previews/holzmann/kw12',
    '/srv/previews/./holzmann',
]);

it('rejects a directory outside every allowed root', function (string $target) {
    expect($this->guard->isAllowed(PreviewTargetType::StaticDirectory, $target))->toBeFalse();
})->with([
    'plain escape' => '/etc/passwd',
    'sibling with shared prefix' => '/srv/previews-secret',
    'traversal out' => '/srv/previews/../etc',
    'deep traversal' => '/srv/previews/holzmann/../../../etc/shadow',
    'traversal back in but out first' => '/srv/previews/../../srv/previews-other',
    'relative path' => 'srv/previews/holzmann',
    'home directory' => '/root/.ssh',
]);

it('rejects an empty or missing directory target', function (?string $target) {
    expect($this->guard->isAllowed(PreviewTargetType::StaticDirectory, $target))->toBeFalse();
})->with([null, '', '   ']);

it('rejects a target containing a null byte', function () {
    expect($this->guard->isAllowed(PreviewTargetType::StaticDirectory, "/srv/previews/ok\0/etc/passwd"))
        ->toBeFalse();
});

it('collapses traversal before comparing, not after', function () {
    // The dangerous case: a string that *looks* like it starts inside the root.
    $reason = $this->guard->rejectionReason(
        PreviewTargetType::StaticDirectory,
        '/srv/previews/../../etc'
    );

    expect($reason)->not->toBeNull()
        ->toContain('außerhalb');
});

/* ----------------------------------------------------------- upstream urls */

it('accepts an https url on an allow-listed host', function () {
    expect($this->guard->isAllowed(
        PreviewTargetType::UpstreamUrl,
        'https://staging.clickit-digital.test/holzmann'
    ))->toBeTrue();
});

it('rejects an upstream url that is not allow-listed', function (string $target) {
    expect($this->guard->isAllowed(PreviewTargetType::UpstreamUrl, $target))->toBeFalse();
})->with([
    'foreign host' => 'https://evil.example.com/',
    'subdomain of allowed host' => 'https://sub.staging.clickit-digital.test/',
    'plain http' => 'http://staging.clickit-digital.test/',
    'file scheme' => 'file:///etc/passwd',
    'gopher scheme' => 'gopher://staging.clickit-digital.test/',
    'credentials' => 'https://user:pass@staging.clickit-digital.test/',
    'odd port' => 'https://staging.clickit-digital.test:8080/',
    'ipv4 literal' => 'https://169.254.169.254/latest/meta-data/',
    'loopback literal' => 'https://127.0.0.1/',
    'ipv6 literal' => 'https://[::1]/',
    'no scheme' => 'staging.clickit-digital.test',
]);

it('rejects every upstream url when no host is allow-listed', function () {
    config(['previews.allowed_upstream_hosts' => []]);

    expect($this->guard->isAllowed(
        PreviewTargetType::UpstreamUrl,
        'https://staging.clickit-digital.test/'
    ))->toBeFalse();
});

it('says where upstream hosts are allow-listed, without repeating the target', function () {
    $foreign = $this->guard->rejectionReason(PreviewTargetType::UpstreamUrl, 'https://evil.example.com/');

    config(['previews.allowed_upstream_hosts' => []]);
    $none = $this->guard->rejectionReason(PreviewTargetType::UpstreamUrl, 'https://staging.clickit-digital.test/');

    expect($foreign)->toContain('PREVIEW_ALLOWED_UPSTREAM_HOSTS')->not->toContain('evil.example.com')
        ->and($none)->toContain('PREVIEW_ALLOWED_UPSTREAM_HOSTS')->not->toContain('staging.clickit-digital.test');
});

it('rejects every directory when no root is configured', function () {
    config(['previews.allowed_roots' => []]);

    expect($this->guard->isAllowed(PreviewTargetType::StaticDirectory, '/srv/previews'))->toBeFalse();
});

/* ------------------------------------------------------- switched-off types */

it('rejects an otherwise valid target whose type is switched off', function (PreviewTargetType $type, string $target, string $enabled) {
    expect($this->guard->isAllowed($type, $target))->toBeTrue();

    config(['previews.target_types' => [$enabled]]);

    expect($this->guard->rejectionReason($type, $target))
        ->toBe('Dieser Zieltyp ist auf diesem Server abgeschaltet.');
})->with([
    'static directory' => [PreviewTargetType::StaticDirectory, '/srv/previews/holzmann', 'upstream_url'],
    'upstream url' => [PreviewTargetType::UpstreamUrl, 'https://staging.clickit-digital.test/holzmann', 'static_directory'],
]);

it('rejects every type when none is switched on', function (PreviewTargetType $type) {
    config(['previews.target_types' => []]);

    expect($this->guard->isAllowed($type, '/srv/previews/holzmann'))->toBeFalse()
        ->and($this->guard->isAllowed($type, 'https://staging.clickit-digital.test/holzmann'))->toBeFalse();
})->with(PreviewTargetType::cases());
