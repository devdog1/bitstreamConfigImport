<?php
/*
|--------------------------------------------------------------------------
| Local Overrides (Inca, Bitstreams Servers, etc.)
|--------------------------------------------------------------------------
*/

$localConfig = [
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
