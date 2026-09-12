<?php

return [
    'google' => [
        'form_url' => env('GOOGLE_FORM_URL', '#'),
        'sheets_url' => env('GOOGLE_SHEETS_URL', '#'),
        'qr_endpoint' => env('GOOGLE_QR_ENDPOINT'),
        'dashboard_endpoint' => env('GOOGLE_DASHBOARD_ENDPOINT'),
        'dashboard_token' => env('GOOGLE_DASHBOARD_TOKEN'),
    ],
];
