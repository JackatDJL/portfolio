<?php

return [
    'access_token_hash' => env('CV_ACCESS_TOKEN_HASH'),
    'pdf_base_url' => env('CV_PDF_BASE_URL', env('APP_URL', 'http://localhost:8000')),
    'pdf_timeout' => (int) env('CV_PDF_TIMEOUT', 60),
    'bun' => env('CV_BUN_BINARY', 'bun'),
];
