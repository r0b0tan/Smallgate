<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A preview hostname must be a single label directly below the configured
 * preview base domain, e.g. "acme.preview.example.com".
 *
 * Pinning it to the base domain is what makes the single wildcard DNS record
 * sufficient, and it stops an administrator from accidentally claiming a
 * hostname the portal does not control.
 */
class PreviewHostname implements ValidationRule
{
    /**
     * One DNS label: lowercase letters, digits and inner hyphens. Shared with
     * the preview host routes and the trusted host pattern, so all three agree
     * on what a preview host is.
     */
    public const LABEL_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Der Hostname ist ungültig.');

            return;
        }

        $host = mb_strtolower(trim($value));
        $base = mb_strtolower((string) config('previews.base_domain'));

        if ($base === '') {
            $fail('Es ist keine Preview-Basisdomain konfiguriert.');

            return;
        }

        $suffix = '.'.$base;

        if (! str_ends_with($host, $suffix)) {
            $fail("Der Hostname muss auf {$suffix} enden.");

            return;
        }

        $label = substr($host, 0, -strlen($suffix));

        // Exactly one label: "a.b.preview..." would need its own DNS record.
        if ($label === '' || str_contains($label, '.')) {
            $fail("Der Hostname muss genau eine Subdomain unterhalb von {$base} sein.");

            return;
        }

        if (preg_match('/^'.self::LABEL_PATTERN.'$/', $label) !== 1) {
            $fail('Die Subdomain darf nur Kleinbuchstaben, Ziffern und Bindestriche enthalten.');

            return;
        }

        if (strlen($label) > 63) {
            $fail('Die Subdomain darf höchstens 63 Zeichen lang sein.');
        }
    }
}
