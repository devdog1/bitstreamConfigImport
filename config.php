<?php
/*
|--------------------------------------------------------------------------
| Bitstreams API Credentials
|--------------------------------------------------------------------------
*/

$BITSTREAMS_TOKEN_ID = "YOUR_TOKEN_ID";
$BITSTREAMS_TOKEN_SECRET = "YOUR_TOKEN_SECRET";

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
        'Default Server' => '127.0.0.1:8080',
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
