<?php

/**
 * The real thing: scripts/preview-screenshot.mjs with Playwright and Chromium.
 * Skipped where the browser is not installed (it is in the app image).
 */

use App\Models\Preview;
use App\Services\Previews\PreviewScreenshotter;
use App\Services\Previews\ThumbnailFailed;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $chromium = (string) config('previews.thumbnails.chromium');

    if (! is_executable($chromium) || ! is_file(base_path('node_modules/playwright-core/package.json'))) {
        $this->markTestSkipped('Chromium or playwright-core is not installed here.');
    }

    $this->root = storage_path('framework/testing/screenshot-previews');
    File::ensureDirectoryExists($this->root.'/site');
    // These tests are about what the page can reach, not about the OS sandbox,
    // so they also run in containers without the seccomp profile. The sandbox
    // has its own test below.
    config([
        'previews.allowed_roots' => [$this->root],
        'previews.thumbnails.timeout_seconds' => 20,
        'previews.thumbnails.sandbox' => false,
    ]);
    $this->output = $this->root.'/out.jpg';
});

afterEach(function () {
    File::deleteDirectory($this->root);
});

it('renders a static preview at the fixed viewport, isolated from the network', function () {
    // The page only declares itself ready once every forbidden request has
    // failed -- if any of them got through, the screenshot times out.
    File::put($this->root.'/site/index.html', <<<'HTML'
        <!doctype html>
        <html data-smallgate-wait>
        <body style="margin:0;background:#1d6b5c;color:#fff;font:48px sans-serif">
        <h1 id="t">Gasthaus zur Post</h1>
        <script>
        const forbidden = [
            '/../../../../etc/passwd',
            '/%2e%2e/%2e%2e/%2e%2e/etc/passwd',
            'http://127.0.0.1/',
            'http://169.254.169.254/latest/meta-data/',
            'http://db:5432/',
            'https://example.com/',
        ];
        Promise.all(forbidden.map((url) => fetch(url).then((r) => r.ok, () => false)))
            .then((results) => {
                if (!results.some(Boolean)) {
                    document.documentElement.setAttribute('data-smallgate-ready', '');
                }
            });
        </script>
        </body>
        </html>
        HTML);

    $preview = Preview::factory()->available()->create(['target' => $this->root.'/site']);

    app(PreviewScreenshotter::class)->capture($preview, $this->output);

    [$width, $height, $type] = getimagesize($this->output);

    // 1440 x 900 rendered, stored at half size.
    expect([$width, $height, $type])->toBe([720, 450, IMAGETYPE_JPEG]);
});

it('times out cleanly when the page never becomes ready', function () {
    File::put($this->root.'/site/index.html', '<!doctype html><html data-smallgate-wait><body>…</body></html>');
    config(['previews.thumbnails.timeout_seconds' => 5]);

    $preview = Preview::factory()->available()->create(['target' => $this->root.'/site']);

    expect(fn () => app(PreviewScreenshotter::class)->capture($preview, $this->output))
        ->toThrow(ThumbnailFailed::class, 'timeout');

    expect(file_exists($this->output))->toBeFalse();
});

it('never renders without the sandbox once it is required', function () {
    File::put($this->root.'/site/index.html', '<!doctype html><html><body><h1>Gasthaus</h1></body></html>');
    config(['previews.thumbnails.sandbox' => true]);

    $preview = Preview::factory()->available()->create(['target' => $this->root.'/site']);

    // With the seccomp profile (worker) the picture is taken sandboxed; without
    // it (plain container) the only acceptable outcome is a refusal.
    try {
        app(PreviewScreenshotter::class)->capture($preview, $this->output);
        expect(getimagesize($this->output)[2])->toBe(IMAGETYPE_JPEG);
    } catch (ThumbnailFailed $failure) {
        expect($failure->getMessage())->toBe('sandbox_unavailable')
            ->and(file_exists($this->output))->toBeFalse();
    }
});
