<?php

namespace App\Contracts;

use App\Enums\PreviewStatus;

/**
 * The outcome of a provisioning attempt. Deliberately a plain value object:
 * the caller decides what to persist, the provisioner only reports.
 */
final readonly class PreviewProvisioningResult
{
    public function __construct(
        public bool $successful,
        public PreviewStatus $status,
        public string $message,
    ) {}

    public static function success(PreviewStatus $status, string $message): self
    {
        return new self(true, $status, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, PreviewStatus::Failed, $message);
    }
}
