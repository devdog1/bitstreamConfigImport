<?php
require_once '../functions.php';

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$all_streams = [];

foreach ($CONFIG['inca_hosts'] as $key => $host) {
    $address = $host['address'];
    $community = $host['snmp_community'] ?? 'public';

    $streams = getIncaStreams($address, $community);

    // Fetch and parse XML backup for enriched info
    $xml = fetchIncaBackup($address, $host['username'], $host['password']);
    $enriched_data = [];
    if ($xml) {
        // Regex-based parsing to avoid SimpleXMLElement dependency
        $sources = [];
        preg_match_all('/<iptv_source id="([^"]+)"[^>]*>(.*?)<\/iptv_source>/s', $xml, $sourceMatches, PREG_SET_ORDER);
        foreach ($sourceMatches as $sm) {
            $id = $sm[1];
            $inner = $sm[2];
            $sources[$id] = [
                'dn' => extractValue($inner, 'dn'),
                'address' => extractValue($inner, 'address'),
                'port' => extractValue($inner, 'port'),
                'ssm' => extractValue($inner, 'ssm_address')
            ];
        }

        preg_match_all('/<transport_stream[^>]*>(.*?)<\/transport_stream>/s', $xml, $tsMatches, PREG_SET_ORDER);
        foreach ($tsMatches as $tsm) {
            $inner = $tsm[1];
            $name = extractValue($inner, 'dn');
            $src_id = extractValue($inner, 'ts_src');
            $source = $sources[$src_id] ?? null;

            $outputs = [];
            preg_match_all('/<iptv_output[^>]*>(.*?)<\/iptv_output>/s', $inner, $outMatches, PREG_SET_ORDER);
            foreach ($outMatches as $om) {
                $outInner = $om[1];
                $outputs[] = extractValue($outInner, 'address') . ":" . extractValue($outInner, 'port');
            }

            if ($name) {
                $enriched_data[strtolower($name)] = [
                    'source' => $source,
                    'filter' => extractValue($inner, 'ts_filter'),
                    'outputs' => $outputs
                ];
            }
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
