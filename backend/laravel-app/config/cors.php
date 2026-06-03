<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    | Configura qué orígenes (dominios) pueden consumir esta API.
    | El frontend React corre en un puerto distinto al backend Laravel,
    | por lo que CORS debe estar habilitado explícitamente.
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',    // React en desarrollo (Vite default)
        'http://localhost:5173',    // React en desarrollo (Vite alternativo)
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Importante para Sanctum con cookies (SPA stateful).
    // Para API pura con tokens Bearer, puede ser false.
    'supports_credentials' => true,

];
