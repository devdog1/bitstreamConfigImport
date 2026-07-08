<?php
require_once '../functions.php';


checkPermission('bitstream.view');

$serverKey = $_GET['server_key'] ?? '';
$path = $_GET['path'] ?? '';

if (!$serverKey || !$path || !isset($config['inca_hosts'][$serverKey])) {
    http_response_code(400);
    exit;
}

$host = $config['inca_hosts'][$serverKey];
$url = "http://{$host['address']}/sys/svc/core/api/v1" . $path;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "{$host['username']}:{$host['password']}");
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($response) {
    header("Content-Type: $contentType");
    echo $response;
} else {
    http_response_code(404);
}
