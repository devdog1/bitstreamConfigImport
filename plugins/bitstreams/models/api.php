<?php
/**
 * Bitstreams Plugin Core API & Session Generator Helpers
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution
}

require_once __DIR__ . '/db.php';

if (!function_exists('bsAuth')) {
    function bsAuth($tokenId, $tokenSecret) {
        return base64_encode($tokenId . ":" . $tokenSecret);
    }
}

if (!function_exists('extractValue')) {
    function extractValue($block, $tag) {
        if (preg_match('/<' . preg_quote($tag) . '[^>]*>(.*?)<\/' . preg_quote($tag) . '>/s', $block, $m)) {
            return trim($m[1]);
        }
        return "";
    }
}

if (!function_exists('parseChannels')) {
    function parseChannels($text) {
        $channels = [];
        preg_match_all('/<dn>(.*?)<\/dn>(.*?)(?=<dn>|$)/s', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fullName = trim($match[1]);
            $block = $match[0];

            if (!str_contains($fullName, "1680") && !str_contains($fullName, "1681")) {
                continue;
            }

            $name = preg_replace('/\s*-\s*168[01]/', '', $fullName);

            if (!isset($channels[$name])) {
                $channels[$name] = [
                    "name" => $name,
                    "address" => extractValue($block, "address"),
                    "port" => extractValue($block, "port"),
                    "a" => "",
                    "b" => "",
                    "out" => ""
                ];
            }

            $ssm = extractValue($block, "ssm_address");

            if (str_contains($fullName, "1680")) {
                $channels[$name]["a"] = $ssm;
            }

            if (str_contains($fullName, "1681")) {
                $channels[$name]["b"] = $ssm;
            }

            if (preg_match('/232\.\d+\.\d+\.\d+/', $block, $out)) {
                $channels[$name]["out"] = $out[0];
            }
        }

        return $channels;
    }
}

if (!function_exists('generateSession')) {
    function generateSession($channel, $serverConfig = null) {
        global $CONFIG;
        $out = $channel["out"] ?: "232.0.0.1";

        $localaddr = $serverConfig['localaddr'] ?? bitstreams_get_setting('localaddr', $CONFIG['localaddr'] ?? '172.17.233.130');
        $defaultRegion = bitstreams_get_setting('default_region', $CONFIG['default_region'] ?? 'Bitstreams');
        $defaultTemplateId = (int)bitstreams_get_setting('default_template_id', $CONFIG['default_template_id'] ?? 13);

        $json = [
            "capture_card_input" => [
                "id" => "",
                "port" => "",
                "protocol" => "",
                "format" => "",
                "bit_depth" => 0
            ],
            "cover_image_url" => "",
            "description" => $channel["name"],
            "enable_failover" => true,
            "enable_hls_scte35_passthrough" => false,
            "enable_scte104_to_35" => false,
            "enable_slates" => false,
            "enable_srt_encryption" => false,
            "failover_error_threshold_percent" => 3,
            "failover_recovery_interval_seconds" => -1,
            "input_type" => "multicast_pull",
            "input_urls" => [[
                "region" => $defaultRegion,
                "urls" => [
                    "udp://{$channel["address"]}:{$channel["port"]}?sources={$channel["a"]}&localaddr={$localaddr}",
                    "udp://{$channel["address"]}:{$channel["port"]}?sources={$channel["b"]}&localaddr={$localaddr}"
                ]
            ]],
            "name" => $channel["name"],
            "playbacks" => [
                [
                    "output_name" => $channel["name"] . "-web",
                    "template_id" => $defaultTemplateId,
                    "output_type" => "http",
                    "http_settings" => [
                        "visibility" => "public",
                        "enable_hls" => true,
                        "enable_dash" => true,
                        "recording_settings" => [
                            "enabled" => false,
                            "base_path" => ""
                        ]
                    ]
                ],
                [
                    "output_name" => $channel["name"] . "-udp",
                    "template_id" => $defaultTemplateId,
                    "output_type" => "multicast",
                    "mpegts_settings" => [
                        "enable" => true,
                        "program_number" => 1
                    ],
                    "stream_remap" => [
                        "enabled" => true,
                        "stream_mappings" => [
                            [
                                "order" => "0",
                                "type" => "PMT",
                                "lang" => "*",
                                "input_pid" => "*",
                                "codec" => "*",
                                "mode" => "remap",
                                "output_pid" => "1906"
                            ],
                            [
                                "order" => "1",
                                "type" => "video",
                                "lang" => "*",
                                "input_pid" => "*",
                                "codec" => "*",
                                "mode" => "remap",
                                "output_pid" => "400"
                            ],
                            [
                                "order" => "2",
                                "type" => "audio",
                                "lang" => "*",
                                "input_pid" => "*",
                                "codec" => "aac",
                                "mode" => "remap",
                                "output_pid" => "483"
                            ],
                            [
                                "order" => "3",
                                "type" => "audio",
                                "lang" => "*",
                                "input_pid" => "*",
                                "codec" => "ac3",
                                "mode" => "remap",
                                "output_pid" => "482"
                            ],
                            [
                                "order" => "#",
                                "type" => "*",
                                "lang" => "*",
                                "input_pid" => "*",
                                "codec" => "*",
                                "mode" => "drop",
                                "output_pid" => "*"
                            ]
                        ]
                    ],
                    "output_urls" => [[
                        "region" => $defaultRegion,
                        "urls" => [
                            "udp://{$out}:3001?localaddr={$localaddr}",
                            "udp://{$out}:3002?localaddr={$localaddr}",
                            "udp://{$out}:3003?localaddr={$localaddr}",
                            "udp://{$out}:3004?localaddr={$localaddr}",
                            "udp://{$out}:3005?localaddr={$localaddr}"
                        ]
                    ]]
                ]
            ],
            "regions" => [
                $defaultRegion
            ],
            "srt_passphrase" => ""
        ];

        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('apiCall')) {
    function apiCall($url, $tokenId, $tokenSecret, $method = 'GET', $payload = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_POSTREDIR, 3);

        if (str_starts_with($url, 'https')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $headers = ["Authorization: Basic " . bsAuth($tokenId, $tokenSecret)];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers[] = "Content-Type: application/json";
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            if ($payload) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                $headers[] = "Content-Type: application/json";
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['code' => $code, 'response' => $response];
    }
}

if (!function_exists('fetchAllStreams')) {
    function fetchAllStreams($baseUrl, $tokenId, $tokenSecret) {
        $allStreams = [];
        $page = 1;
        $limit = 25;

        while (true) {
            $separator = str_contains($baseUrl, '?') ? '&' : '?';
            $url = "{$baseUrl}{$separator}page={$page}&limit={$limit}";

            $result = apiCall($url, $tokenId, $tokenSecret);

            if ($result['code'] < 200 || $result['code'] >= 300) {
                break;
            }

            $data = json_decode($result['response'], true);
            $list = $data['data']['list'] ?? [];
            $total = $data['data']['total'] ?? 0;

            if (empty($list)) {
                break;
            }

            foreach ($list as $item) {
                $allStreams[] = $item;
            }

            if (count($allStreams) >= $total || count($list) < $limit) {
                break;
            }

            $page++;

            if ($page > 1000) {
                break;
            }
        }

        return $allStreams;
    }
}

if (!function_exists('getIncaStreams')) {
    function getIncaStreams($address, $community) {
        if (!function_exists('snmp2_real_walk')) {
            return [];
        }

        $streams = [];
        $oid_base = ".1.3.6.1.4.1.39938.2.1.1.1.1";

        $descrs = @snmp2_real_walk($address, $community, "{$oid_base}.3");
        if (!$descrs) return [];

        foreach ($descrs as $oid => $name) {
            $parts = explode('.', $oid);
            $index = array_pop($parts);
            $direction = array_pop($parts);

            if ($direction != "2") continue;

            $name = trim(preg_replace('/^[A-Z0-9-]+: /i', '', $name), '" ');

            if (preg_match('/.*test.*|.*xcode.*/i', $name)) continue;

            $bitrate = @snmp2_get($address, $community, "{$oid_base}.6.{$direction}.{$index}");
            $bitrate = trim(preg_replace('/^[A-Z0-9-]+: /i', '', $bitrate));

            $errors = @snmp2_get($address, $community, "{$oid_base}.8.{$direction}.{$index}");
            $errors = trim(preg_replace('/^[A-Z0-9-]+: /i', '', $errors));

            $streams[] = [
                'stream_id' => "inca_{$direction}_{$index}",
                'name' => $name,
                'status' => 'inca_active',
                'bitrate' => $bitrate,
                'errors' => $errors,
                'type' => 'inca'
            ];
        }

        return $streams;
    }
}

if (!function_exists('fetchIncaBackup')) {
    function fetchIncaBackup($address, $user, $pass) {
        $url = "http://{$address}/sys/svc/core/api/v1/devices/1/configuration.bak";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            return $response;
        }

        return null;
    }
}

if (!function_exists('incaRawCall')) {
    function incaRawCall($address, $path, $user, $pass) {
        $url = "http://{$address}/sys/svc/core/api/v1{$path}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}

if (!function_exists('incaApiCall')) {
    function incaApiCall($address, $path, $user, $pass) {
        $url = "http://{$address}/sys/svc/core/api/v1{$path}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pass}");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300) {
            return json_decode($response, true);
        }

        return null;
    }
}
