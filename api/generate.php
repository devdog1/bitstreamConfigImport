<?php
require_once '../functions.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$sessions = [];
$backup_content = $_POST["backup"] ?? "";

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
            "json" => generateSession($channel)
        ];
    }
}

echo json_encode($sessions);
