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

    $result = apiCall("{$protocol}://{$address}/api/v3/streams/", $tokenId, $tokenSecret);

    if ($result['code'] >= 200 && $result['code'] < 300) {
        $data = json_decode($result['response'], true);
        if (isset($data['data']['list'])) {
            foreach ($data['data']['list'] as $stream) {
                $stream['server_name'] = $server['name'];
                $stream['server_key'] = $key;
                $all_streams[] = $stream;
            }
        }
    }
}

echo json_encode($all_streams);
