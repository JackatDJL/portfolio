<?php

return [
    'access_token_hash' => env('CV_ACCESS_TOKEN_HASH'),
    'canonical_base_url' => env('CV_CANONICAL_BASE_URL', 'https://jack.djl.foundation'),
    'pdf_timeout' => (int) env('CV_PDF_TIMEOUT', 60),
    'lualatex' => env('CV_LUALATEX_BINARY', 'lualatex'),
];
