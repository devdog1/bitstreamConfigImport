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
        try {
            $sxe = new SimpleXMLElement($xml);

            // Map Sources
            $sources = [];
            if ($sxe->iptv_sources && $sxe->iptv_sources->iptv_source) {
                foreach ($sxe->iptv_sources->iptv_source as $s) {
                    $sources[(string)$s['id']] = [
                        'dn' => (string)$s->dn,
                        'address' => (string)$s->address,
                        'port' => (string)$s->port,
                        'ssm' => (string)$s->ssm_address
                    ];
                }
            }

            // Map Transport Streams
            if ($sxe->transport_streams && $sxe->transport_streams->transport_stream) {
                foreach ($sxe->transport_streams->transport_stream as $ts) {
                    $name = (string)$ts->dn;
                    $src_id = (string)$ts->ts_src;
                    $source = $sources[$src_id] ?? null;

                    $outputs = [];
                    if ($ts->outputs && $ts->outputs->iptv_output) {
                        foreach ($ts->outputs->iptv_output as $out) {
                            $outputs[] = (string)$out->address . ":" . (string)$out->port;
                        }
                    }

                    $enriched_data[strtolower($name)] = [
                        'source' => $source,
                        'filter' => (string)$ts->ts_filter,
                        'outputs' => $outputs
                    ];
                }
            }
        } catch (Exception $e) {
            // Skip enrichment on parse error
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
