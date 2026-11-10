<?php

return [
    'provider' => env('APPLICATION_SIGNATURE_PROVIDER', 'unavailable'),
    'sandbox_enabled' => (bool) env('APPLICATION_SIGNATURE_SANDBOX', false),
    'trust_roots_file' => env('APPLICATION_SIGNATURE_TRUST_ROOTS'),
    'root' => storage_path('app/private/application-signatures'),
    'validation_timeout' => 45,
    'max_bytes' => 100 * 1024 * 1024,
];
