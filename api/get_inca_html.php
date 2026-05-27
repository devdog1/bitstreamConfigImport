<?php
require_once '../functions.php';

header("Content-Type: text/html");

$serverKey = $_GET['server_key'] ?? '';
$progId = $_GET['prog_id'] ?? '';

if (!$serverKey || !$progId || !isset($CONFIG['inca_hosts'][$serverKey])) {
    http_response_code(400);
    echo "Invalid request parameters.";
    exit;
}

$host = $CONFIG['inca_hosts'][$serverKey];
$path = "/devices/1/programs/{$progId}";
$html = incaRawCall($host['address'], $path, $host['username'], $host['password']);

if (!$html) {
    echo "Could not fetch details from INCA.";
    exit;
}

// Proxy images: Replace {{IMG /path}} or standard <img> src
$proxyUrl = "api/inca_proxy_resource.php?server_key=" . urlencode($serverKey) . "&path=";

$html = preg_replace_callback('/\{\{IMG (.*?)\}\}/i', function($m) use ($proxyUrl) {
    return '<img src="' . $proxyUrl . urlencode($m[1]) . '" class="img-fluid border mt-1 mb-1">';
}, $html);

// Handle standard img tags if any
$html = preg_replace('/<img src="(.*?)"/i', '<img src="' . $proxyUrl . '$1"', $html);

// Remove specific INCA UI buttons that won't work in this context (like audit or download)
$html = preg_replace('/<table class="ts_buttons">.*?<\/table>/s', '', $html);

echo $html;
