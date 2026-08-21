<?php

declare(strict_types=1);

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => getenv('DB_HOST') ?: '127.0.0.1',
            'database' => getenv('DB_DATABASE') ?: 'peanut_admin',
            'username' => getenv('DB_USERNAME') ?: 'peanut_admin',
            'password' => getenv('DB_PASSWORD') ?: 'peanut_admin_dev',
            'hostport' => (int) (getenv('DB_PORT') ?: 3306),
            'charset' => 'utf8mb4',
            'prefix' => 'pa_',
            'fields_strict' => true,
            'break_reconnect' => false,
        ],
    ],
];
