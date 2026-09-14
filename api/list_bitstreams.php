<?php
require_once '../functions.php';

header("Content-Type: application/json");

checkPermission('bitstream.view');

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$all_streams = [];
$specific_key = $_GET['key'] ?? null;

$servers = bitstreams_get_servers();
if ($specific_key) {
    if (isset($servers[$specific_key])) {
        $servers = [$specific_key => $servers[$specific_key]];
    } else {
        $servers = [];
    }
}

foreach ($servers as $key => $server) {
    $address = $server['address'];
    $protocol = $server['protocol'] ?? 'http';
    $tokenId = $server['token_id'];
    $tokenSecret = $server['token_secret'];

    $streams = fetchAllStreams("{$protocol}://{$address}/api/v3/streams/", $tokenId, $tokenSecret);

    foreach ($streams as $stream) {
        $streamItem = $stream;
        $streamItem['stream_id'] = $stream['id'] ?? $stream['stream_id'] ?? '';
        $streamItem['server_name'] = $server['name'];
        $streamItem['server_key'] = $key;
        $streamItem['server_protocol'] = $protocol;
        $streamItem['server_address'] = $address;
        $streamItem['type'] = 'bitstreams';
        $all_streams[] = $streamItem;
    }
}

echo json_encode($all_streams);
