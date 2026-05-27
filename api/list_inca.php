<?php
require_once '../functions.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$all_streams = [];
$specific_key = $_GET['key'] ?? null;

$hosts = $CONFIG['inca_hosts'];
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

    $streams = getIncaStreams($address, $community);

    // Fetch enriched info via INCA JSON APIs
    $user = $host['username'];
    $pass = $host['password'];

    $sourcesRaw = incaApiCall($address, "/system/sources", $user, $pass);
    $outputsRaw = incaApiCall($address, "/dvp/streams/ip/outputs", $user, $pass);
    $profilesRaw = incaApiCall($address, "/dvp/video/profiles", $user, $pass);

    $enriched_data = [];

    if ($sourcesRaw && $outputsRaw && $profilesRaw) {
        // Map Sources
        $sources = [];
        foreach ($sourcesRaw as $s) {
            $sources[$s['id']] = [
                'id' => $s['id'],
                'stream_id' => $s['stream_id'],
                'dn' => $s['label'] ?? $s['name'],
                'address' => explode(':', $s['description'] ?? '')[0] ?? '',
                'port' => explode(':', $s['description'] ?? '')[1] ?? '',
                'ssm' => '' // API sources list doesn't show SSM by default in this view
            ];
        }

        // Map Profiles
        $profiles = [];
        foreach ($profilesRaw as $p) {
            $profiles[$p['id']] = [
                'name' => $p['dn'],
                'codec' => $p['codec'],
                'bitrate' => $p['bitrate'],
                'resolution' => $p['width'] . "x" . $p['height'],
                'fps' => $p['framerate']
            ];
        }

        // Build Enriched Data from Outputs
        foreach ($outputsRaw as $o) {
            $name = $o['dn'];
            $src_id = $o['sourceId'];
            $source = $sources[$src_id] ?? null;

            $outputs_detailed = [];
            if (isset($o['streams'])) {
                foreach ($o['streams'] as $i => $s) {
                    if (!$s['enabled']) continue;
                    $outputs_detailed[] = [
                        'prog_id' => "output_{$o['lid']}_" . ($i + 1),
                        'dest' => $s['network']['address'] . ":" . $s['network']['port'],
                        'profile' => $profiles[$s['video']['profileId'] ?? ''] ?? null
                    ];
                }
            }

            $enriched_data[strtolower($name)] = [
                'lid' => $o['lid'],
                'uuid' => $o['id'],
                'source' => $source,
                'filter' => $o['sourceFilter'] ?? '',
                'outputs_detailed' => $outputs_detailed
            ];
        }
    }

    $grouped = [];
    foreach ($streams as $stream) {
        $name = $stream['name'];
        if (!isset($grouped[$name])) {
            $grouped[$name] = [
                'stream_id' => "inca_{$key}_" . md5($name),
                'name' => $name,
                'status' => 'inca_active',
                'server_name' => $host['name'],
                'server_key' => $key,
                'server_address' => $address,
                'server_user' => $host['username'] ?? '',
                'server_pass' => $host['password'] ?? '',
                'type' => 'inca',
                'instances' => [],
                'enriched' => $enriched_data[strtolower($name)] ?? null
            ];
        }
        $grouped[$name]['instances'][] = $stream;
    }

    foreach ($grouped as $g) {
        $totalBitrate = 0;
        $totalErrors = 0;
        foreach ($g['instances'] as $inst) {
            $totalBitrate += (int)$inst['bitrate'];
            $totalErrors += (int)$inst['errors'];
        }
        $g['bitrate'] = $totalBitrate;
        $g['errors'] = $totalErrors;
        $all_streams[] = $g;
    }
}

echo json_encode($all_streams);
