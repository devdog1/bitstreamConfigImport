<?php
require_once '../functions.php';

header("Content-Type: application/json");

checkPermission('bitstream.view');

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$all_streams = [];
$specific_key = $_GET['key'] ?? null;

$hosts = bitstreams_get_inca_hosts();
if ($specific_key) {
    if (isset($hosts[$specific_key])) {
        $hosts = [$specific_key => $hosts[$specific_key]];
    } else {
        $hosts = [];
    }
}

foreach ($hosts as $key => $host) {
    $address = $host['address'];
    $community = $host['snmp_community'] ?? 'public';
    $user = $host['username'];
    $pass = $host['password'];

    // 1. Fetch info via INCA JSON APIs first
    $sourcesRaw = incaApiCall($address, "/system/sources", $user, $pass);
    $outputsRaw = incaApiCall($address, "/dvp/streams/ip/outputs", $user, $pass);
    $profilesRaw = incaApiCall($address, "/dvp/video/profiles", $user, $pass);

    if (!$outputsRaw) continue;

    // 2. Map Sources for lookups
    $sourcesMap = [];
    if ($sourcesRaw) {
        foreach ($sourcesRaw as $s) {
            $sourcesMap[$s['id']] = [
                'id' => $s['id'],
                'stream_id' => $s['stream_id'],
                'dn' => $s['label'] ?? $s['name'],
                'address' => explode(':', $s['description'] ?? '')[0] ?? '',
                'port' => explode(':', $s['description'] ?? '')[1] ?? '',
                'ssm' => ''
            ];
        }
    }

    // 3. Map Profiles for lookups
    $profilesMap = [];
    if ($profilesRaw) {
        foreach ($profilesRaw as $p) {
            $profilesMap[$p['id']] = [
                'name' => $p['dn'],
                'codec' => $p['codec'],
                'bitrate' => $p['bitrate'],
                'resolution' => $p['width'] . "x" . $p['height'],
                'fps' => $p['framerate']
            ];
        }
    }

    // 4. Get SNMP streams for matching
    $snmpStreams = getIncaStreams($address, $community);
    $snmpMap = [];
    foreach ($snmpStreams as $s) {
        $snmpMap[strtolower($s['name'])][] = $s;
    }

    // 5. Build the list based on API outputs
    foreach ($outputsRaw as $o) {
        $name = $o['dn'];
        $lowName = strtolower($name);

        $src_id = $o['sourceId'];
        $source = $sourcesMap[$src_id] ?? null;

        $outputs_detailed = [];
        if (isset($o['streams'])) {
            foreach ($o['streams'] as $i => $s) {
                if (!$s['enabled']) continue;
                $outputs_detailed[] = [
                    'prog_id' => "output_{$o['lid']}_" . ($i + 1),
                    'dest' => $s['network']['address'] . ":" . $s['network']['port'],
                    'profile' => $profilesMap[$s['video']['profileId'] ?? ''] ?? null
                ];
            }
        }

        // Match with SNMP data
        $instances = $snmpMap[$lowName] ?? [];
        $status = empty($instances) ? 'inca_down' : 'inca_active';

        $totalBitrate = 0;
        $totalErrors = 0;
        foreach ($instances as $inst) {
            $totalBitrate += (int)$inst['bitrate'];
            $totalErrors += (int)$inst['errors'];
        }

        $uuidVal = $o['uuid'] ?? $o['id'] ?? '';

        $all_streams[] = [
            'stream_id' => "inca_{$key}_" . md5($name),
            'name' => $name,
            'status' => $status,
            'server_name' => $host['name'],
            'server_key' => $key,
            'server_address' => $address,
            'server_user' => $user,
            'server_pass' => $pass,
            'type' => 'inca',
            'bitrate' => $totalBitrate,
            'errors' => $totalErrors,
            'instances' => $instances,
            'enriched' => [
                'lid' => $o['lid'] ?? '',
                'uuid' => $uuidVal,
                'source' => $source,
                'filter' => $o['sourceFilter'] ?? '',
                'outputs_detailed' => $outputs_detailed
            ]
        ];
    }
}

echo json_encode($all_streams);
