<?php

namespace App\Contracts;

use App\Models\Preview;

/**
 * The boundary between Smallgate and anything on the server a preview might
 * need. This is one of the few genuine system boundaries in the app and
 * therefore one of the few places that earns an interface.
 *
 * The MVP ships only NullPreviewProvisioner. Static previews are served by
 * Smallgate itself (docs/adr/0003-preview-delivery.md), so releasing one needs
 * nothing on the server; an implementation that writes vhost fragments,
 * reloads a gateway or issues certificates would need an ADR of its own.
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
