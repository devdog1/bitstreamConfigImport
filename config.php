<?php
/*
|--------------------------------------------------------------------------
| Application Configuration
|--------------------------------------------------------------------------
*/

$CONFIG = [
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
    'inca_hosts' => [
        'inca1' => [
            'name' => 'INCA Host 1',
            'address' => '10.0.0.50',
            'username' => 'admin',
            'password' => 'admin',
            'snmp_community' => 'public',
        ],
    ],
    'servers' => [
        'default' => [
            'name' => 'Default Server',
            'address' => '127.0.0.1:8080',
            'protocol' => 'http',
            'token_id' => 'YOUR_TOKEN_ID',
            'token_secret' => 'YOUR_TOKEN_SECRET',
            'localaddr' => '172.17.233.130',
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
