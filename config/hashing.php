<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Smallgate hashes passwords with Argon2id, the memory-hard winner of the
    | Password Hashing Competition. Never replace this with a hand-written
    | hashing or encryption scheme -- passwords must stay one-way hashed.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Only used as a fallback and to transparently verify legacy hashes.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Defaults follow the OWASP Password Storage Cheat Sheet recommendation for
    | Argon2id (64 MiB memory, 4 iterations, 1 degree of parallelism). Raise
    | ARGON_MEMORY / ARGON_TIME on stronger hardware, never lower them in
    | production. The test suite lowers them for speed only.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | Existing hashes are upgraded automatically once these options change.
    |
    */

    'rehash_on_login' => true,

];
