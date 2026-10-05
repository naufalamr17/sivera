<?php

return [
    'enabled' => (bool) env('SIVERA_ENABLED', true),
    'url' => env('SIVERA_URL'),
    'app_id' => env('SIVERA_APP_ID'),
    'client_id' => env('SIVERA_CLIENT_ID'),
    'secret' => env('SIVERA_SECRET'),
    'environment' => env('SIVERA_ENVIRONMENT', 'production'),
    'transport' => env('SIVERA_TRANSPORT', 'http'),
    'connect_timeout' => (float) env('SIVERA_CONNECT_TIMEOUT', 0.5),
    'timeout' => (float) env('SIVERA_TIMEOUT', 2),
    'capture_user' => true,
    'capture_request_body' => false,
];
