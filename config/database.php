<?php

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '35.194.184.219',
            'port' => 3306,
            'database' => 'autorun_usb2',
            'username' => 'baibapay_test',
            'password' => 'EOMP6Hj6qyAo2rI8',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'timezone' =>'+08:00',
        ],


    ],

    'redis' => [
        'client' => 'predis',
        // 'cluster' => env('REDIS_CLUSTER', false),
        'default' => [
            'host' => '35.189.168.14',
            'password' => 'password1234',
            'port' => 6379,
            'database' => 0,
        ],
        'cache' => [
            'host' => '35.189.168.14',
            'password' => 'password1234',
            'port' => 6379,
            'database' => 0,
        ],
    ],
];
