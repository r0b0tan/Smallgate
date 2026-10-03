<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preview Base Domain
    |--------------------------------------------------------------------------
    |
    | Static previews are served by Smallgate itself, one host per preview
    | directly below this domain (ADR 0003). It should be a registrable domain
    | of its own, not a subdomain of the portal's, so a draft never counts as
    | the portal's site. A single wildcard DNS record points at the server;
    | Smallgate never creates DNS records itself. Preview hostnames must be a
    | direct label under this domain, enforced in App\Rules\PreviewHostname.
    |
    */

    'base_domain' => env('PREVIEW_BASE_DOMAIN', 'preview.example.com'),

    /*
    |--------------------------------------------------------------------------
    | Access on the Preview Host
    |--------------------------------------------------------------------------
    |
    | How a signed-in portal user gets onto a preview host (ADR 0003): the
    | portal hands out a one-time token that is valid for seconds, the preview
    | host exchanges it for a session of its own that ends after a fixed time
    | and is never extended. Every request re-checks PreviewPolicy::open, so a
    | revoked right takes effect at once, whatever these values are.
    |
    */

    'access' => [
        'handoff_seconds' => 60,
        'session_hours' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploaded Drafts
    |--------------------------------------------------------------------------
    |
    | An administrator uploads a static draft as a ZIP; the queue worker
    | unpacks it into a folder of its own and only then points the preview at
    | it (ADR 0004). The limits stop zip bombs: the unpacked size is counted
    | while writing, never taken from the archive's own headers. The ZIP limit
    | has to stay within upload_max_filesize (docker/php/php.ini) and the
    | upload route's client_max_body_size in nginx.
    |
    | "keep" is how many uploads of one preview stay on disk, the current one
    | included. Older ones are deleted -- only folders Smallgate created and
    | recorded itself, never the current target.
    |
    */

    'uploads' => [
        'max_zip_bytes' => 64 * 1024 * 1024,
        'max_extracted_bytes' => 256 * 1024 * 1024,
        'max_files' => 5000,
        'max_depth' => 20,
        'max_path_length' => 255,
        'keep' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provisioner
    |--------------------------------------------------------------------------
    |
    | Which App\Contracts\PreviewProvisioner implementation is bound. The MVP
    | ships only the "null" driver: it records intent and touches nothing on the
    | server -- no files outside the project directory, no privileged commands.
    | See docs/adr/0001-preview-subdomain-architecture.md.
    |
    | Supported: "null"
    |
    | Note the ?: rather than a default argument: Laravel's env() helper turns
    | the literal string "null" into PHP null, so a plain default would never
    | be reached and the binding would fail to resolve.
    |
    */

    'provisioner' => env('PREVIEW_PROVISIONER') ?: 'null',

    /*
    |--------------------------------------------------------------------------
    | Allowed Target Types
    |--------------------------------------------------------------------------
    |
    | A preview target is either a directory below an allow-listed root, or an
    | upstream URL whose host is explicitly allow-listed. Customers can never
    | choose or influence either value -- only administrators can, and even
    | their input is validated against the allowlists below.
    |
    | Only the types listed here can be chosen, provisioned, screenshotted or
    | opened; PreviewTargetGuard rejects every other one. Without a preview
    | domain (PREVIEW_BASE_DOMAIN) a static directory has nowhere to be served,
    | so such an installation lists "upstream_url" only.
    |
    */

    'target_types' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) (env('PREVIEW_TARGET_TYPES') ?: 'static_directory,upstream_url'))
    ))),

    /*
    | Absolute directory roots a "static_directory" target may live under.
    | Targets are resolved with realpath() and must stay inside one of these
    | roots, which is what stops ../ path traversal.
    */
    'allowed_roots' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PREVIEW_ALLOWED_ROOTS', storage_path('app/previews')))
    ))),

    /*
    | Hosts an "upstream_url" target may point at. Anything not listed here is
    | rejected, as are IP literals, embedded credentials and non-HTTPS schemes.
    | Note that hostnames are NOT resolved here: a listed host that points at a
    | loopback, link-local, private or metadata address is still accepted. Any
    | component that actually opens a connection must resolve the host itself,
    | reject non-public addresses, pin the validated address and re-check every
    | redirect.
    */
    'allowed_upstream_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PREVIEW_ALLOWED_UPSTREAM_HOSTS', ''))
    ))),

    /*
    | Schemes accepted for "upstream_url" targets.
    */
    'allowed_upstream_schemes' => ['https'],

    /*
    |--------------------------------------------------------------------------
    | Thumbnails
    |--------------------------------------------------------------------------
    |
    | Every successful provisioning queues one screenshot of the preview's
    | target (App\Jobs\GeneratePreviewThumbnail), taken with Playwright and
    | Chromium by scripts/preview-screenshot.mjs. Never on a page view.
    |
    | The browser only ever sees the allow-listed target: a static directory
    | is served to it from disk without any network, an upstream URL is
    | pinned to its resolved public address, and every other host, IP
    | literal and redirect is blocked.
    |
    | "chromium" is the browser executable; leave it empty to use the
    | browser Playwright downloads itself (`npx playwright install chromium`).
    |
    | "sandbox" keeps Chromium's own process sandbox on. In Docker it needs the
    | seccomp profile docker/seccomp/chromium.json (the worker service has it).
    | Without a working sandbox no screenshot is taken -- switch it off only
    | for a local setup that cannot provide one.
    |
    */

    'thumbnails' => [
        'enabled' => (bool) env('PREVIEW_THUMBNAILS_ENABLED', true),
        'node' => env('PREVIEW_THUMBNAIL_NODE') ?: 'node',
        'chromium' => env('PREVIEW_THUMBNAIL_CHROMIUM', '/usr/bin/chromium'),
        'sandbox' => (bool) env('PREVIEW_THUMBNAIL_SANDBOX', true),
        'timeout_seconds' => (int) env('PREVIEW_THUMBNAIL_TIMEOUT', 45),
        'viewport' => ['width' => 1440, 'height' => 900],
        // The screenshot is taken at the viewport above and stored at half
        // its size, which is plenty for a card and keeps the file small.
        'scale' => 0.5,
        'disk' => 'local',
        'directory' => 'preview-thumbnails',
    ],

];
