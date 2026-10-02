<?php

namespace App\Services\Previews;

use App\Enums\PreviewTargetType;
use App\Models\Preview;
use Illuminate\Support\Facades\Process;

/**
 * Takes the thumbnail screenshot of a preview by handing it to
 * scripts/preview-screenshot.mjs (Playwright + Chromium).
 *
 * The browser is only ever pointed at the preview's own, allow-listed target,
 * and the target is checked again here right before the browser starts -- the
 * same PreviewTargetGuard the admin form and the provisioner use. For an
 * upstream URL this class also closes the gap the guard documents: the host is
 * resolved here, every address must be public, and the browser is pinned to
 * the checked address so a second, different DNS answer never reaches it.
 */
class PreviewScreenshotter
{
    /**
     * The only variables the screenshot process inherits. None of them is a
     * secret.
     */
    private const ENVIRONMENT_ALLOWLIST = ['PATH', 'HOME', 'TMPDIR', 'LANG', 'TZ'];

    public function __construct(private readonly PreviewTargetGuard $guard) {}

    /**
     * Write a JPEG screenshot of the preview to $outputPath.
     *
     * @throws ThumbnailFailed with a reason that is safe to log
     */
    public function capture(Preview $preview, string $outputPath): void
    {
        $config = (array) config('previews.thumbnails');
        $timeout = max(5, (int) ($config['timeout_seconds'] ?? 45));

        $spec = [
            ...$this->target($preview),
            'output' => $outputPath,
            'viewport' => $config['viewport'],
            'scale' => (float) $config['scale'],
            'timeoutMs' => $timeout * 1000,
            'chromium' => (string) ($config['chromium'] ?? ''),
            'sandbox' => (bool) ($config['sandbox'] ?? true),
        ];

        // The spec goes in on stdin rather than as arguments: the process list
        // is readable by every user on the machine.
        $result = Process::path(base_path())
            ->timeout($timeout + 15)
            ->input(json_encode($spec, JSON_THROW_ON_ERROR))
            ->run([
                ...$this->cleanEnvironment(),
                (string) $config['node'],
                base_path('scripts/preview-screenshot.mjs'),
            ]);

        $answer = json_decode(trim($result->output()), true);

        if (! $result->successful() || ! is_array($answer) || ($answer['ok'] ?? false) !== true) {
            // Only the script's own coarse code is passed on -- never stderr,
            // which could contain a URL or a path.
            $code = is_array($answer) && is_string($answer['error'] ?? null) ? $answer['error'] : 'process_failed';

            throw new ThumbnailFailed(preg_replace('/[^a-z_]/', '', $code) ?: 'process_failed');
        }

        if (! is_file($outputPath) || ! $this->isJpeg($outputPath)) {
            throw new ThumbnailFailed('no_image');
        }
    }

    /**
     * @return array<string, string>
     */
    private function target(Preview $preview): array
    {
        if ($this->guard->rejectionReason($preview->target_type, $preview->target) !== null) {
            throw new ThumbnailFailed('target_rejected');
        }

        return match ($preview->target_type) {
            PreviewTargetType::StaticDirectory => $this->staticTarget((string) $preview->target),
            PreviewTargetType::UpstreamUrl => $this->upstreamTarget((string) $preview->target),
        };
    }

    /**
     * @return array<string, string>
     */
    private function staticTarget(string $target): array
    {
        // The guard already resolved symlinks of an existing path; a missing
        // directory simply has nothing to show.
        $root = realpath($target);

        if ($root === false || ! is_dir($root)) {
            throw new ThumbnailFailed('target_missing');
        }

        return ['mode' => 'static', 'root' => $root];
    }

    /**
     * @return array<string, string>
     */
    private function upstreamTarget(string $url): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $addresses = $this->resolve($host);

        if ($addresses === []) {
            throw new ThumbnailFailed('dns_failed');
        }

        foreach ($addresses as $address) {
            // A host on the allowlist that resolves to a loopback, private,
            // link-local, shared or reserved address is refused outright.
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new ThumbnailFailed('address_not_public');
            }
        }

        return ['mode' => 'upstream', 'url' => $url, 'host' => $host, 'address' => $addresses[0]];
    }

    /**
     * IPv4 addresses of a host. Separate so tests can avoid real DNS.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : array_values($addresses);
    }

    /**
     * Start the script through `env -i` with only what Node and Chromium need.
     * The worker's environment carries APP_KEY, the database and the mail
     * password; a browser that renders foreign pages has no use for any of
     * them, and must not hold them should a page ever break out of the
     * renderer. Done with env(1) rather than Process::env(), which only adds
     * to the inherited environment.
     *
     * @return list<string>
     */
    private function cleanEnvironment(): array
    {
        $keep = [];

        foreach (self::ENVIRONMENT_ALLOWLIST as $name) {
            $value = getenv($name);

            if (is_string($value) && $value !== '') {
                $keep[] = $name.'='.$value;
            }
        }

        return ['env', '-i', ...$keep];
    }

    private function isJpeg(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 3);
        fclose($handle);

        return $magic === "\xFF\xD8\xFF";
    }
}
