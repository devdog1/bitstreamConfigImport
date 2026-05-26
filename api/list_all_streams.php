<?php
require_once '../functions.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$all_streams = [];

foreach ($CONFIG['servers'] as $key => $server) {
    $address = $server['address'];
    $protocol = $server['protocol'] ?? 'http';
    $tokenId = $server['token_id'];
    $tokenSecret = $server['token_secret'];

    $streams = fetchAllStreams("{$protocol}://{$address}/api/v3/streams/", $tokenId, $tokenSecret);

    foreach ($streams as $stream) {
        $stream['server_name'] = $server['name'];
        $stream['server_key'] = $key;
        $stream['server_protocol'] = $protocol;
        $stream['server_address'] = $address;
        $stream['type'] = 'bitstreams';
        $all_streams[] = $stream;
    }
}

foreach ($CONFIG['inca_hosts'] as $key => $host) {
    $address = $host['address'];
    $community = $host['snmp_community'] ?? 'public';

    $streams = getIncaStreams($address, $community);

    $grouped = [];
    foreach ($streams as $stream) {
        $name = $stream['name'];
        if (!isset($grouped[$name])) {
            $grouped[$name] = [
                'stream_id' => "inca_{$key}_" . md5($name),
                'name' => $name,
                'status' => 'inca_active',
                'server_name' => $host['name'],
                'server_key' => $key,
                'server_address' => $address,
                'server_user' => $host['username'] ?? '',
                'server_pass' => $host['password'] ?? '',
                'type' => 'inca',
                'instances' => []
            ];
        }
        $grouped[$name]['instances'][] = $stream;
    }

    foreach ($grouped as $g) {
        // Calculate aggregate bitrate and errors for display
        $totalBitrate = 0;
        $totalErrors = 0;
        foreach ($g['instances'] as $inst) {
            $totalBitrate += (int)$inst['bitrate'];
            $totalErrors += (int)$inst['errors'];
        }
        $g['bitrate'] = $totalBitrate;
        $g['errors'] = $totalErrors;
        $all_streams[] = $g;
    }
}

echo json_encode($all_streams);
