<?php
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-Pagination-Total',
        'X-Pagination-Per-Page',
        'X-Pagination-Current-Page',
        'X-Pagination-Last-Page'
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
