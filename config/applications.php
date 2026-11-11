<?php

return [
    'python' => env('APPLICATION_PDF_PYTHON', '/opt/portfolio-pdf/bin/python'),
    'timeout' => (int) env('APPLICATION_PDF_TIMEOUT', 90),
    'cache_path' => storage_path('app/private/application-pdf-cache'),
    'work_path' => storage_path('app/private/application-pdf-work'),
    'handwritten_signature_path' => storage_path('app/private/application-handwritten-signature'),
    'max_document_bytes' => 20 * 1024 * 1024,
    'max_total_bytes' => 100 * 1024 * 1024,
    'max_pages' => 300,
    'retention_days' => 30,
];
