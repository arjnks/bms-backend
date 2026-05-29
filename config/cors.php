<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://bms-frontend.vercel.app',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:5175',
        'http://localhost:3000',
        'http://10.1.0.75:5173',
    ],

    'allowed_origins_patterns' => [
        // Allow all Vercel preview deployments
        '/^https:\/\/bms-frontend.*\.vercel\.app$/',
        // Allow all localhost ports (for Vite dev server)
        '/^http:\/\/localhost:\d+$/',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
