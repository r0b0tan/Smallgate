<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | Customer users are never self-registered; an administrator invites them.
    | The plaintext token only ever exists inside the invitation mail -- the
    | database stores a SHA-256 hash of it (see App\Models\Invitation).
    |
    */

    'invitations' => [
        // How long an invitation link stays valid, in hours.
        'ttl_hours' => (int) env('INVITATION_TTL_HOURS', 72),

        // Byte length of the random token before hex encoding.
        'token_bytes' => 32,
    ],

    /*
    |--------------------------------------------------------------------------
    | Login Throttling
    |--------------------------------------------------------------------------
    |
    | Failed logins are rate limited twice: per email+IP combination, so
    | guessing against one account never locks another one out, and per IP
    | across all addresses, so one source cannot try a few passwords against
    | every account (password spraying). A successful login clears only the
    | first counter -- signing into one's own account must not reset the
    | budget for guessing at others. Distributed guessing from many source
    | addresses is not covered by this.
    |
    */

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'max_attempts_per_ip' => (int) env('LOGIN_MAX_ATTEMPTS_PER_IP', 20),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Hosts and Proxies
    |--------------------------------------------------------------------------
    |
    | Password reset and invitation mails contain absolute links. Laravel builds
    | those from the incoming request, so without an allowlist a forged Host
    | header puts an attacker's domain into a mail that carries a valid token.
    | Requests whose Host is not listed here are rejected outside the local
    | environment; AppServiceProvider additionally pins every generated URL to
    | APP_URL, which also covers queue workers and console commands.
    |
    | The host of APP_URL is always trusted. TRUSTED_HOSTS adds further names,
    | comma separated -- a second domain, or the internal name a health check
    | uses. Subdomains are deliberately NOT trusted: preview subdomains are a
    | separate service, not the portal.
    |
    | TRUSTED_PROXIES lists the reverse proxies allowed to set X-Forwarded-*.
    | With none configured those headers are ignored, which is the safe default
    | but makes the application see http behind a TLS terminating proxy. Use
    | concrete addresses or CIDR ranges. "*" trusts whatever sits in front of
    | the application and is only acceptable when nothing else can reach it.
    |
    */

    // Regular expressions, because that is what Symfony's trusted host check
    // takes. Anchored and quoted, so no configured name can match more than
    // itself.
    'trusted_hosts' => array_map(
        static fn (string $host): string => '^'.preg_quote($host, '#').'$',
        array_values(array_filter(array_map('trim', array_merge(
            [(string) parse_url((string) env('APP_URL', ''), PHP_URL_HOST)],
            explode(',', (string) env('TRUSTED_HOSTS', ''))
        ))))
    ),

    'trusted_proxies' => match ($proxies = trim((string) env('TRUSTED_PROXIES', ''))) {
        '' => null,
        '*' => '*',
        default => array_values(array_filter(array_map('trim', explode(',', $proxies)))),
    },

    /*
    |--------------------------------------------------------------------------
    | Display Time Zone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC. The customer portal shows dates in this
    | zone.
    |
    */

    'display_timezone' => env('DISPLAY_TIMEZONE', 'Europe/Berlin'),

    /*
    |--------------------------------------------------------------------------
    | Contact
    |--------------------------------------------------------------------------
    |
    | The customer's message archive offers a mail to this address. There is
    | deliberately no chat: conversations stay in email. Without an address
    | the button is not shown.
    |
    */

    'contact_email' => env('CONTACT_EMAIL', env('LEGAL_EMAIL', '')),

    /*
    |--------------------------------------------------------------------------
    | Activity Log
    |--------------------------------------------------------------------------
    |
    | The administration's log of who did what. Entries only point at users
    | and records -- no names, addresses, comments or IP addresses are copied
    | into it -- and are deleted after this many days.
    |
    */

    'activity' => [
        'retention_days' => (int) env('ACTIVITY_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legal Pages
    |--------------------------------------------------------------------------
    |
    | Imprint and privacy policy are placeholders for now and are configured
    | entirely through environment variables. No personal data is hard coded
    | into the repository.
    |
    */

    'legal' => [
        'company' => env('LEGAL_COMPANY', ''),
        'address' => env('LEGAL_ADDRESS', ''),
        'email' => env('LEGAL_EMAIL', ''),
        'phone' => env('LEGAL_PHONE', ''),
        'represented_by' => env('LEGAL_REPRESENTED_BY', ''),
        'vat_id' => env('LEGAL_VAT_ID', ''),
        'register_entry' => env('LEGAL_REGISTER_ENTRY', ''),
    ],

];
