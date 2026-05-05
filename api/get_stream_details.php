<?php
require_once '../functions.php';

header("Content-Type: application/json");

$serverKey = $_GET['server_key'] ?? '';
$streamId = $_GET['stream_id'] ?? '';

if (!$serverKey || !$streamId || !isset($CONFIG['servers'][$serverKey])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid parameters"]);
    exit;
}

$server = $CONFIG['servers'][$serverKey];
$address = $server['address'];
$protocol = $server['protocol'] ?? 'http';
$tokenId = $server['token_id'];
$tokenSecret = $server['token_secret'];

$baseUrl = "{$protocol}://{$address}/api/v3/streams/{$streamId}/";

$events = apiCall($baseUrl . "events/", $tokenId, $tokenSecret);
$sourceInfo = apiCall($baseUrl . "source_info/", $tokenId, $tokenSecret);
$sourceReports = apiCall($baseUrl . "source_reports/", $tokenId, $tokenSecret);

echo json_encode([
    "events" => json_decode($events['response'], true),
    "source_info" => json_decode($sourceInfo['response'], true),
    "source_reports" => json_decode($sourceReports['response'], true)
]);
