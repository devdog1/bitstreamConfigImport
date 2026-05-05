<?php
require_once '../functions.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$server_key = $_POST["server_key"] ?? "";
$stream_id = $_POST["stream_id"] ?? "";
$action = $_POST["action"] ?? ""; // "start", "stop", "restart"

if (!isset($CONFIG['servers'][$server_key]) || empty($stream_id) || !in_array($action, ["start", "stop", "restart"])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid Request"]);
    exit;
}

$server = $CONFIG['servers'][$server_key];
$address = $server['address'];
$protocol = $server['protocol'] ?? 'http';
$tokenId = $server['token_id'];
$tokenSecret = $server['token_secret'];

function performAction($action, $protocol, $address, $stream_id, $tokenId, $tokenSecret) {
    // Bitstreams API usually uses POST to /api/v3/streams/{id}/{action}
    $url = "{$protocol}://{$address}/api/v3/streams/{$stream_id}/{$action}/";
    return apiCall($url, $tokenId, $tokenSecret, 'POST');
}

if ($action === "restart") {
    performAction("stop", $protocol, $address, $stream_id, $tokenId, $tokenSecret);
    sleep(1);
    $result = performAction("start", $protocol, $address, $stream_id, $tokenId, $tokenSecret);
} else {
    $result = performAction($action, $protocol, $address, $stream_id, $tokenId, $tokenSecret);
}

echo json_encode($result);
