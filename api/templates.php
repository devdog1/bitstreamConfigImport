<?php
require_once '../functions.php';

header("Content-Type: application/json");

checkPermission('bitstream.edit');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$server_key = $_POST["server_key"] ?? "";
$custom_address = $_POST["custom_address"] ?? "";
$custom_protocol = $_POST["custom_protocol"] ?? "http";

$servers = bitstreams_get_servers();

if ($server_key === "custom") {
    $address = $custom_address;
    $protocol = $custom_protocol;
    $tokenId = $_POST["custom_token_id"] ?? "";
    $tokenSecret = $_POST["custom_token_secret"] ?? "";
} elseif (isset($servers[$server_key])) {
    $server = $servers[$server_key];
    $address = $server['address'];
    $protocol = $server['protocol'] ?? 'http';
    $tokenId = $server['token_id'];
    $tokenSecret = $server['token_secret'];
} else {
    echo json_encode([]);
    exit;
}

if (empty($address)) {
    echo json_encode([]);
    exit;
}

$result = apiCall("{$protocol}://{$address}/api/v3/template/", $tokenId, $tokenSecret);
echo $result['response'];
