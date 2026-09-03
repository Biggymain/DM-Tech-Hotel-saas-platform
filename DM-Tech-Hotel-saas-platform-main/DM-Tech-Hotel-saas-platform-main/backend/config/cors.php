<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000', 'http://127.0.0.1:3000',
        'http://localhost:3001', 'http://127.0.0.1:3001',
        'http://localhost:3002', 'http://127.0.0.1:3002',
        'http://localhost:3003', 'http://127.0.0.1:3003',
        'http://localhost:3004', 'http://127.0.0.1:3004',
        'http://localhost:3005', 'http://127.0.0.1:3005',
    ],

    'allowed_origins_patterns' => [
        '#^http://[a-zA-Z0-9\._-]+\.localhost(:[0-9]+)?$#',
    ],

    'allowed_headers' => [
        '*',
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-Frontend-Port',
        'X-App-Port',
        'X-Tenant-Slug',
        'X-Hotel-Context',
        'X-Hardware-Id',
        'X-Room-ID',
        'X-Outlet-ID',
        'X-Table-Number',
        'X-Tenant-ID',
        'X-Branch-ID',
        'X-Group-ID',
    ],

    'exposed_headers' => [
        'Authorization',
        'X-Frontend-Port',
        'X-App-Port',
        'X-Tenant-Slug',
    ],

    'max_age' => 86400,

    'supports_credentials' => true,

];
