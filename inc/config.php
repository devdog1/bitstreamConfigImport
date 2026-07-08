<?php
/*
|--------------------------------------------------------------------------
| Application Configuration
|--------------------------------------------------------------------------
*/

$config = [
    'db' => [
        'local' => [
            'dbhost' => 'localhost',
            'dbname' => 'bitstreams_db',
            'dbuser' => 'dbuser',
            'dbpass' => 'dbpass',
        ]
    ],
    'azure' => [
        'clientId'     => 'YOUR_AZURE_CLIENT_ID',
        'clientSecret' => 'YOUR_AZURE_CLIENT_SECRET',
        'redirectUri'  => 'http://localhost:8000/callback.php',
        'tenantId'     => 'common',
    ],
    'localaddr' => '172.17.233.130',
    'default_template_id' => 13,
    'default_region' => 'Bitstreams',
];
