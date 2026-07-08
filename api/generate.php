<?php
require_once '../functions.php';


header("Content-Type: application/json");

checkPermission('bitstream.edit');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$sessions = [];
$backup_content = $_POST["backup"] ?? "";
$server_key = $_POST["server_key"] ?? "";

$serverConfig = null;
if (isset($config['servers'][$server_key])) {
    $serverConfig = $config['servers'][$server_key];
} elseif ($server_key === "custom") {
    $serverConfig = [
        'localaddr' => $_POST["custom_localaddr"] ?? $config['localaddr']
    ];
}

if (isset($_FILES["backup_file"]) && $_FILES["backup_file"]["error"] == UPLOAD_ERR_OK) {
    $backup_content = file_get_contents($_FILES["backup_file"]["tmp_name"]);
}

if (!empty($backup_content)) {
    $channels = parseChannels($backup_content);
    foreach ($channels as $channel) {
        if (!$channel["a"] || !$channel["b"]) {
            continue;
        }
        $sessions[] = [
            "name" => $channel["name"],
            "json" => generateSession($channel, $serverConfig)
        ];
    }
}

echo json_encode($sessions);
