<?php

return [
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],
];
