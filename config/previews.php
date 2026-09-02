<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preview Base Domain
    |--------------------------------------------------------------------------
    |
    | A single wildcard DNS record (*.preview.example.com) points at the
    | preview server, so Smallgate never has to create DNS records itself.
    | Preview hostnames must be a direct label under this domain; the value is
    | enforced server side in App\Rules\PreviewHostname.
    |
    */

    'base_domain' => env('PREVIEW_BASE_DOMAIN', 'preview.example.com'),

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
    */

    'target_types' => ['static_directory', 'upstream_url'],

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

];
