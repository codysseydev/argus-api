<?php

declare(strict_types=1);

return [
    // URL prefix all Argus API routes mount under.
    'prefix' => 'argus-api',

    // The authentication seam. Authentication is the consuming app's job; this
    // package only declares the stack its routes sit behind. Defaults to
    // Sanctum's stateful guard. Replace with the app's own guard(s) as needed.
    'middleware' => ['auth:sanctum'],

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
