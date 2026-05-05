<?php
/*
|--------------------------------------------------------------------------
| Application Configuration
|--------------------------------------------------------------------------
*/

$CONFIG = [
    'localaddr' => '172.17.233.130',
    'default_template_id' => 13,
    'default_region' => 'Bitstreams',
    'servers' => [
        'default' => [
            'name' => 'Default Server',
            'address' => '127.0.0.1:8080',
            'token_id' => 'YOUR_TOKEN_ID',
            'token_secret' => 'YOUR_TOKEN_SECRET',
        ],
    ],
];

/*
|--------------------------------------------------------------------------
| Local Overrides
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__ . '/config.local.php')) {
    include __DIR__ . '/config.local.php';
}
