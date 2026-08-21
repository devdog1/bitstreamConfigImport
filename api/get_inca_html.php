<?php
require_once '../functions.php';

checkPermission('bitstream.view');

header("Content-Type: text/html");

$serverKey = $_GET['server_key'] ?? '';
$progId = $_GET['prog_id'] ?? '';

$hosts = bitstreams_get_inca_hosts();

if (!$serverKey || !$progId || !isset($hosts[$serverKey])) {
    http_response_code(400);
    echo "Invalid request parameters.";
    exit;
}

$host = $hosts[$serverKey];
$path = "/devices/1/programs/{$progId}";
$html = incaRawCall($host['address'], $path, $host['username'], $host['password']);

if (!$html) {
    echo "Could not fetch details from INCA.";
    exit;
}

$proxyUrl = "api/inca_proxy_resource.php?server_key=" . urlencode($serverKey) . "&path=";

$html = preg_replace_callback('/\{\{IMG (.*?)\}\}/i', function($m) use ($proxyUrl) {
    return '<img src="' . $proxyUrl . urlencode($m[1]) . '" class="img-fluid border mt-1 mb-1">';
}, $html);

$html = preg_replace('/<img src="(.*?)"/i', '<img src="' . $proxyUrl . '$1"', $html);
$html = preg_replace('/<table class="ts_buttons">.*?<\/table>/s', '', $html);

echo $html;
