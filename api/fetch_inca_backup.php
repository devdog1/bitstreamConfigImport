<?php
require_once '../functions.php';

header("Content-Type: application/json");

checkPermission('bitstream.edit');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$host_key = $_POST["host_key"] ?? "";
$hosts = bitstreams_get_inca_hosts();

if (!isset($hosts[$host_key])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid INCA host"]);
    exit;
}

$host = $hosts[$host_key];
$xml = fetchIncaBackup($host['address'], $host['username'], $host['password']);

if ($xml) {
    echo json_encode(["xml" => $xml]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Failed to fetch backup from INCA."]);
}
