<?php

namespace App\Services\Previews;

use App\Contracts\PreviewProvisioner;
use App\Contracts\PreviewProvisioningResult;
use App\Enums\PreviewStatus;
use App\Models\Preview;
use Illuminate\Support\Facades\Log;

/**
 * The only provisioner the MVP ships.
 *
 * It deliberately does nothing to the server: no file outside the project
 * directory is written, no web server configuration is touched, no shell
 * command runs, and nothing is ever executed with elevated privileges. It only
 * records the intent so the flow, the UI and the tests are exercised end to end
 * while the real infrastructure decision is still open.
 *
 * It does still re-validate the target, so a preview that somehow reached the
 * database with a target outside the configured allowlists is reported as
 * failed rather than quietly marked available.
 */
class NullPreviewProvisioner implements PreviewProvisioner
{
    public function __construct(private readonly PreviewTargetGuard $guard) {}

    public function provision(Preview $preview): PreviewProvisioningResult
    {
        if ($preview->hostname === null) {
            return PreviewProvisioningResult::failure(
                'Die Vorschau hat keinen Hostnamen und kann nicht bereitgestellt werden.'
            );
        }

        $rejection = $this->guard->rejectionReason($preview->target_type, $preview->target);

        if ($rejection !== null) {
            // Log the reason but never the target itself -- it may contain a
            // server path or internal URL.
            Log::warning('Preview provisioning rejected.', [
                'preview_id' => $preview->id,
                'reason' => $rejection,
            ]);

            return PreviewProvisioningResult::failure($rejection);
        }

        return PreviewProvisioningResult::success(
            PreviewStatus::Available,
            'Vorschau vorgemerkt. Die tatsächliche Auslieferung erfolgt in der Provisioning-Phase.'
        );
    }

    public function deprovision(Preview $preview): PreviewProvisioningResult
    {
        return PreviewProvisioningResult::success(
            PreviewStatus::Disabled,
            'Vorschau deaktiviert. Es wurden keine Serverdateien verändert.'
        );
    }
}
