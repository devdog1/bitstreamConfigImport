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
$type = $_POST["type"] ?? "bitstreams";

if ($type === 'bitstreams') {
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
        // Bitstreams API uses PUT to /api/v3/streams/{id}/start/ or /api/v3/streams/{id}/stop/
        $url = "{$protocol}://{$address}/api/v3/streams/{$stream_id}/{$action}/";
        return apiCall($url, $tokenId, $tokenSecret, 'PUT');
    }

    if ($action === "restart") {
        performAction("stop", $protocol, $address, $stream_id, $tokenId, $tokenSecret);
        sleep(1);
        $result = performAction("start", $protocol, $address, $stream_id, $tokenId, $tokenSecret);
    } else {
        $result = performAction($action, $protocol, $address, $stream_id, $tokenId, $tokenSecret);
    }
} else if ($type === 'inca') {
    if (!isset($CONFIG['inca_hosts'][$server_key]) || empty($stream_id) || !in_array($action, ["start", "stop", "restart"])) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid Request (INCA)"]);
        exit;
    }

    $host = $CONFIG['inca_hosts'][$server_key];
    $address = $host['address'];
    $user = $host['username'];
    $pass = $host['password'];

    function performIncaAction($action, $address, $stream_id, $user, $pass) {
        // 1. Get current data as raw string to handle empty objects correctly
        $raw = incaRawCall($address, "/dvp/streams/ip/outputs/{$stream_id}", $user, $pass);
        if (!$raw) return ["code" => 500, "response" => "Could not fetch current INCA output state"];

        // Use object-based decoding to distinguish between {} and []
        $data = json_decode($raw, false);
        if (!$data) return ["code" => 500, "response" => "Failed to decode INCA output state"];

        // 2. Modify enabled flag on all streams
        $newState = ($action === 'start');
        if (isset($data->streams) && is_array($data->streams)) {
            foreach ($data->streams as $s) {
                $s->enabled = $newState;
            }
        }

        // 3. PUT back
        $url = "http://{$address}/sys/svc/core/api/v1/dvp/streams/ip/outputs/{$stream_id}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ["code" => $code, "response" => $response];
    }

    if ($action === "restart") {
        performIncaAction("stop", $address, $stream_id, $user, $pass);
        sleep(5); // Wait 5 seconds as requested for INCA
        $result = performIncaAction("start", $address, $stream_id, $user, $pass);
    } else {
        $result = performIncaAction($action, $address, $stream_id, $user, $pass);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Invalid Type"]);
    exit;
}

echo json_encode($result);
