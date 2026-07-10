<?php

return [
    'app_name' => 'trivial',
    'base_url' => 'https://tu-dominio.example',
    'anonymous_room_retention_days' => 30,
    'mail' => [
        'transport' => 'native',
        'from' => 'no-reply@tu-dominio.example',
    ],
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'trivial',
        'username' => 'usuario_mysql',
        'password' => 'password_mysql',
        'charset' => 'utf8mb4',
    ],
];
