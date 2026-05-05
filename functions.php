<?php
require_once __DIR__ . '/config.php';

function bsAuth($tokenId, $tokenSecret)
{
    return base64_encode($tokenId . ":" . $tokenSecret);
}

function extractValue($block, $tag)
{
    if (preg_match('/<' . preg_quote($tag) . '>(.*?)<\/' . preg_quote($tag) . '>/s', $block, $m)) {
        return trim($m[1]);
    }
    return "";
}

function parseChannels($text)
{
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

function generateSession($channel)
{
    global $CONFIG;
    $out = $channel["out"] ?: "232.0.0.1";
    $localaddr = $CONFIG['localaddr'];

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
            "region" => $CONFIG['default_region'],
            "urls" => [
                "udp://{$channel["address"]}:{$channel["port"]}?sources={$channel["a"]}&localaddr={$localaddr}",
                "udp://{$channel["address"]}:{$channel["port"]}?sources={$channel["b"]}&localaddr={$localaddr}"
            ]
        ]],
        "name" => $channel["name"],
        "playbacks" => [
            [
                "output_name" => $channel["name"] . "-web",
                "template_id" => $CONFIG['default_template_id'],
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
                "template_id" => $CONFIG['default_template_id'],
                "output_type" => "multicast",
                "output_urls" => [[
                    "region" => $CONFIG['default_region'],
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
            $CONFIG['default_region']
        ],
        "srt_passphrase" => ""
    ];

    return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function apiCall($url, $tokenId, $tokenSecret, $method = 'GET', $payload = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $headers = ["Authorization: Basic " . bsAuth($tokenId, $tokenSecret)];

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
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
