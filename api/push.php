<?php
require_once '../functions.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$server_key = $_POST["server_key"] ?? "";
$custom_address = $_POST["custom_address"] ?? "";
$custom_protocol = $_POST["custom_protocol"] ?? "http";
$payload = $_POST["payload"] ?? "";

if ($server_key === "custom") {
    $address = $custom_address;
    $protocol = $custom_protocol;
    $tokenId = $_POST["custom_token_id"] ?? "";
    $tokenSecret = $_POST["custom_token_secret"] ?? "";
} elseif (isset($CONFIG['servers'][$server_key])) {
    $server = $CONFIG['servers'][$server_key];
    $address = $server['address'];
    $protocol = $server['protocol'] ?? 'http';
    $tokenId = $server['token_id'];
    $tokenSecret = $server['token_secret'];
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid Server"]);
    exit;
}

// Added trailing slash to avoid 307 redirects
$result = apiCall("{$protocol}://{$address}/api/v3/streams/", $tokenId, $tokenSecret, 'POST', $payload);
echo json_encode($result);
