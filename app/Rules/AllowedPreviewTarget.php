<?php

namespace App\Rules;

use App\Enums\PreviewTargetType;
use App\Services\Previews\PreviewTargetGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Server side gate for preview targets. The actual decision lives in
 * PreviewTargetGuard, which the provisioner re-uses -- so a target can never be
 * accepted by the form but rejected by the provisioner, or the other way round.
 */
class AllowedPreviewTarget implements ValidationRule
{
    public function __construct(
        private readonly ?PreviewTargetType $type,
        private readonly PreviewTargetGuard $guard = new PreviewTargetGuard,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Das Ziel ist ungültig.');

            return;
        }

        $reason = $this->guard->rejectionReason($this->type, $value);

        if ($reason !== null) {
            $fail($reason);
        }
    }
}
