<?php

namespace App\Contracts;

use App\Models\Preview;

/**
 * The boundary between Smallgate and whatever actually serves preview
 * subdomains. This is one of the few genuine system boundaries in the app and
 * therefore one of the few places that earns an interface.
 *
 * The MVP ships only NullPreviewProvisioner. A real implementation -- writing
 * vhost fragments, reloading a gateway, issuing certificates -- is deliberately
 * deferred; see docs/adr/0001-preview-subdomain-architecture.md for the options
 * still open and the security problems each of them carries.
 *
 * Implementations must treat every argument as untrusted and must never accept
 * a target that was not validated against config('previews') first.
 */
interface PreviewProvisioner
{
    /**
     * Make the preview reachable under its hostname.
     *
     * @return PreviewProvisioningResult what was (or would have been) done
     */
    public function provision(Preview $preview): PreviewProvisioningResult;

    /**
     * Stop serving the preview without deleting its record.
     */
    public function deprovision(Preview $preview): PreviewProvisioningResult;
}
