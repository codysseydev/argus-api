<?php

declare(strict_types=1);

return [
    // URL prefix all Argus API routes mount under.
    'prefix' => 'argus-api',

    // The guard (or list of guards) to authenticate against. The package derives
    // 'auth:<guard>' middleware from this value and appends it to 'middleware'
    // automatically. Accepts a string or an array of guard names. Set to null
    // to disable guard derivation and take full manual control via 'middleware'.
    'guard' => env('ARGUS_API_GUARD', 'sanctum'),

    // Supporting middleware stack (session, CSRF, throttle, etc.). The auth
    // middleware is derived from 'guard' and appended automatically. Setting
    // 'guard' to null hands full manual control back to this stack.
    'middleware' => [],

    'pagination' => [
        'default_limit' => 100,
        'max_limit' => 500,
    ],

    'authorization' => [
        // Default verdict for every Argus gate the app has NOT overridden. true
        // means any authenticated user passes (authentication already proved the
        // user is valid). Set false to deny by default, or define the gates in
        // your own provider to apply real role checks.
        'allow_by_default' => true,
    ],
];
