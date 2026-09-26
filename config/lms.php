<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CSRF exempt paths
    |--------------------------------------------------------------------------
    |
    | Paths that must accept a request without a CSRF token. Only machine
    | callers belong here, such as the payment provider, because they cannot
    | hold a session or read a token. Every path must be verified by a
    | signature or a secret of its own.
    |
    | A feature test cannot catch a missing entry here, because the framework
    | skips CSRF checks while the test suite runs. tests/Feature/Phase12/
    | WebhookCsrfExemptionTest.php therefore checks this list against the
    | registered routes instead.
    |
    */

    'csrf_exempt_paths' => [
        'webhooks/*',
    ],

];
