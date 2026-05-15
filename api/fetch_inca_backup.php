<?php
require_once '../functions.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$host_key = $_POST["host_key"] ?? "";

if (!isset($CONFIG['inca_hosts'][$host_key])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid INCA host"]);
    exit;
}

$host = $CONFIG['inca_hosts'][$host_key];
$address = $host['address'];
$user = $host['username'];
$pass = $host['password'];

$url = "http://{$address}/sys/svc/core/api/v1/devices/1/configuration.bak";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($code >= 200 && $code < 300) {
    echo json_encode(["xml" => $response]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch backup from INCA. HTTP Code: {$code}. Error: {$error}"]);
}
