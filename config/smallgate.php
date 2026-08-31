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
    | Failed logins are rate limited per email+IP combination. Successful
    | logins clear the counter.
    |
    */

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('LOGIN_DECAY_SECONDS', 60),
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
        'company' => env('LEGAL_COMPANY', 'Clickit Digital'),
        'address' => env('LEGAL_ADDRESS', ''),
        'email' => env('LEGAL_EMAIL', ''),
        'phone' => env('LEGAL_PHONE', ''),
        'represented_by' => env('LEGAL_REPRESENTED_BY', ''),
        'vat_id' => env('LEGAL_VAT_ID', ''),
        'register_entry' => env('LEGAL_REGISTER_ENTRY', ''),
    ],

];
