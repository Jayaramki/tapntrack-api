<?php

return [
    'paths' => ['v1/*', 'api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'https://app.tapntrack.in'),
        'http://localhost:4200',
    ],
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required for cookie-based SPA auth: the browser must send the session +
    // XSRF cookies cross-subdomain (app. -> api.).
    'supports_credentials' => true,
];
