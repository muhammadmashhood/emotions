<?php

return [
    'typeform' => [
        'default_base_url' => env('TYPEFORM_DEFAULT_BASE_URL', 'https://api.typeform.com'),
        'rate_limit_per_second' => (int) env('TYPEFORM_RATE_LIMIT_PER_SECOND', 2),
        'connect_timeout' => 5,
        'timeout' => 20,
        'max_retries' => 3,
    ],

    // Extra origins allowed in script-src (space separated), e.g. an error-tracking CDN.
    'csp_extra_sources' => env('CSP_EXTRA_SOURCES', ''),

    'webhook_delivery_retention_days' => (int) env('WEBHOOK_DELIVERY_RETENTION_DAYS', 90),

    'demo_evaluations' => (int) env('DEMO_EVALUATIONS', 300),

    // Local seeding only. Never set in production.
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],
];
