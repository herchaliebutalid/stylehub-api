<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| StyleHub exposes its Laravel API to:
|
| 1. The Administrator web application running in a browser.
| 2. The Customer / Shop Owner React Native mobile application.
|
| Native React Native requests are not subject to browser CORS rules.
| Therefore browser origins are intentionally limited to the Administrator
| web application's allowed origins.
|
| Never use "*" for production Administrator origins.
|
*/

$allowedOrigins = array_values(
    array_filter(
        array_map(
            'trim',
            explode(
                ',',
                (string) env(
                    'CORS_ALLOWED_ORIGINS',
                    'http://localhost:5173,http://127.0.0.1:5173'
                )
            )
        ),
        static fn (string $origin): bool =>
            $origin !== ''
    )
);

return [

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | CORS handling is restricted to API requests.
    |
    */

    'paths' => [
        'api/*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed HTTP Methods
    |--------------------------------------------------------------------------
    |
    | StyleHub currently uses these methods across its versioned API.
    |
    | OPTIONS is required for browser preflight requests.
    |
    */

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Origins are read from the environment so development and production
    | deployments can define different Administrator frontend hosts.
    |
    */

    'allowed_origins' =>
        $allowedOrigins,

    /*
    |--------------------------------------------------------------------------
    | Allowed Origin Patterns
    |--------------------------------------------------------------------------
    |
    | No wildcard origin patterns are currently needed.
    |
    */

    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Allowed Request Headers
    |--------------------------------------------------------------------------
    |
    | Authorization is required because StyleHub uses Sanctum bearer tokens.
    |
    */

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'Origin',
        'X-Requested-With',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed Response Headers
    |--------------------------------------------------------------------------
    |
    | No internal response headers need to be exposed to browser JavaScript.
    |
    */

    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Preflight Cache
    |--------------------------------------------------------------------------
    |
    | Browsers may cache successful CORS preflight responses for one hour.
    |
    */

    'max_age' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | The current StyleHub API authentication architecture uses explicit
    | bearer tokens rather than browser cross-origin session cookies.
    |
    | Therefore credentialed CORS requests remain disabled.
    |
    */

    'supports_credentials' => false,

];