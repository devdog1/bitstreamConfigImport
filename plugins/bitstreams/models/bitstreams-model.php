<?php
/**
 * Bitstreams Plugin Model
 * Handles database interaction for settings, servers, and INCA hosts inside plug_bitstreams_* namespace.
 * Reuses singleton connections and implements request-level static caching to prevent DB connection exhaustion.
 * Also provides core Bitstreams & INCA API communication helper functions.
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution if required
}

// In-memory request caches
$GLOBALS['BITSTREAMS_CACHE_SETTINGS'] = null;
$GLOBALS['BITSTREAMS_CACHE_SERVERS'] = null;
$GLOBALS['BITSTREAMS_CACHE_INCA_HOSTS'] = null;

if (!function_exists('bitstreams_clear_cache')) {
    function bitstreams_clear_cache($type = null) {
        if ($type === 'settings' || $type === null) {
            $GLOBALS['BITSTREAMS_CACHE_SETTINGS'] = null;
        }
        if ($type === 'servers' || $type === null) {
            $GLOBALS['BITSTREAMS_CACHE_SERVERS'] = null;
        }
        if ($type === 'inca_hosts' || $type === null) {
            $GLOBALS['BITSTREAMS_CACHE_INCA_HOSTS'] = null;
        }
    }
}

if (!function_exists('bitstreams_get_db_helper')) {
    function bitstreams_get_db_helper() {
        static $pdb = null;
        if ($pdb === null && class_exists('PluginDatabase')) {
            $pdb = new PluginDatabase('bitstreams');
        }
        return $pdb;
    }
}

if (!function_exists('bitstreams_get_pdo')) {
    function bitstreams_get_pdo() {
        static $pdo = null;
        if ($pdo === null && function_exists('get_db_connection')) {
            try {
                $pdo = get_db_connection();
            } catch (Exception $e) {
                return null;
            }
        }
        return $pdo;
    }
}

if (!function_exists('bitstreams_ensure_tables')) {
    function bitstreams_ensure_tables() {
        static $ensured = false;
        if ($ensured) return;

        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $pdb->createTable('settings', "
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ");

            $pdb->createTable('servers', "
                id INT AUTO_INCREMENT PRIMARY KEY,
                server_key VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                address VARCHAR(255) NOT NULL,
                protocol VARCHAR(10) DEFAULT 'http',
                token_id VARCHAR(255) NOT NULL,
                token_secret VARCHAR(255) NOT NULL,
                localaddr VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ");

            $pdb->createTable('inca_hosts', "
                id INT AUTO_INCREMENT PRIMARY KEY,
                host_key VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                address VARCHAR(255) NOT NULL,
                username VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                snmp_community VARCHAR(255) DEFAULT 'public',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ");
        } else {
            $pdo = bitstreams_get_pdo();
            if ($pdo) {
                $sql = file_get_contents(__DIR__ . '/../sql/install.sql');
                if ($sql) {
                    $pdo->exec($sql);
                }
            }
        }

        $ensured = true;
        bitstreams_seed_defaults();
    }
}

if (!function_exists('bitstreams_seed_defaults')) {
    function bitstreams_seed_defaults() {
        global $CONFIG;

        // Seed Settings if empty
        $currentSettings = bitstreams_get_all_settings(false);
        if (count($currentSettings) === 0) {
            $defaultLocaladdr = $CONFIG['localaddr'] ?? '172.17.233.130';
            $defaultTemplateId = $CONFIG['default_template_id'] ?? 13;
            $defaultRegion = $CONFIG['default_region'] ?? 'Bitstreams';

            bitstreams_set_setting('localaddr', $defaultLocaladdr);
            bitstreams_set_setting('default_template_id', (string)$defaultTemplateId);
            bitstreams_set_setting('default_region', $defaultRegion);
        }

        // Seed Servers if empty
        $currentServers = bitstreams_get_servers(false);
        if (count($currentServers) === 0) {
            $defaultServers = $CONFIG['servers'] ?? [
                'default' => [
                    'name' => 'Default Server',
                    'address' => '127.0.0.1:8080',
                    'protocol' => 'http',
                    'token_id' => 'YOUR_TOKEN_ID',
                    'token_secret' => 'YOUR_TOKEN_SECRET',
                    'localaddr' => '172.17.233.130'
                ]
            ];

            foreach ($defaultServers as $key => $s) {
                bitstreams_save_server($key, $s);
            }
        }

        // Seed INCA Hosts if empty
        $currentHosts = bitstreams_get_inca_hosts(false);
        if (count($currentHosts) === 0) {
            $defaultInca = $CONFIG['inca_hosts'] ?? [
                'inca1' => [
                    'name' => 'INCA Host 1',
                    'address' => '10.0.0.50',
                    'username' => 'admin',
                    'password' => 'admin',
                    'snmp_community' => 'public'
                ]
            ];

            foreach ($defaultInca as $key => $h) {
                bitstreams_save_inca_host($key, $h);
            }
        }
    }
}

if (!function_exists('bitstreams_get_setting')) {
    function bitstreams_get_setting($key, $default = null) {
        $settings = bitstreams_get_all_settings();
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('bitstreams_set_setting')) {
    function bitstreams_set_setting($key, $value) {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        $success = false;

        if ($pdb) {
            $tb = $pdb->getTableName('settings');
            $pdb->query("
                INSERT INTO {$tb} (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ", [$key, $value, $value]);
            $success = true;
        } else {
            $pdo = bitstreams_get_pdo();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO plug_bitstreams_settings (setting_key, setting_value)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?
                ");
                $stmt->execute([$key, $value, $value]);
                $success = true;
            }
        }

        if ($success) {
            bitstreams_clear_cache('settings');
        }
        return $success;
    }
}

if (!function_exists('bitstreams_get_all_settings')) {
    function bitstreams_get_all_settings($ensure = true) {
        if ($GLOBALS['BITSTREAMS_CACHE_SETTINGS'] !== null) {
            return $GLOBALS['BITSTREAMS_CACHE_SETTINGS'];
        }

        if ($ensure) bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        $settings = [];

        if ($pdb) {
            $tb = $pdb->getTableName('settings');
            try {
                $stmt = $pdb->query("SELECT setting_key, setting_value FROM {$tb}");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                $GLOBALS['BITSTREAMS_CACHE_SETTINGS'] = $settings;
            } catch (Exception $e) {}
            return $settings;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM plug_bitstreams_settings");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                $GLOBALS['BITSTREAMS_CACHE_SETTINGS'] = $settings;
            } catch (Exception $e) {}
        }
        return $settings;
    }
}

if (!function_exists('bitstreams_get_servers')) {
    function bitstreams_get_servers($ensure = true) {
        if ($GLOBALS['BITSTREAMS_CACHE_SERVERS'] !== null) {
            return $GLOBALS['BITSTREAMS_CACHE_SERVERS'];
        }

        if ($ensure) bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        $servers = [];

        if ($pdb) {
            $tb = $pdb->getTableName('servers');
            try {
                $stmt = $pdb->query("SELECT * FROM {$tb} ORDER BY id ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $servers[$row['server_key']] = [
                        'name' => $row['name'],
                        'address' => $row['address'],
                        'protocol' => $row['protocol'] ?: 'http',
                        'token_id' => $row['token_id'],
                        'token_secret' => $row['token_secret'],
                        'localaddr' => $row['localaddr']
                    ];
                }
                $GLOBALS['BITSTREAMS_CACHE_SERVERS'] = $servers;
            } catch (Exception $e) {}
            return $servers;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM plug_bitstreams_servers ORDER BY id ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $servers[$row['server_key']] = [
                        'name' => $row['name'],
                        'address' => $row['address'],
                        'protocol' => $row['protocol'] ?: 'http',
                        'token_id' => $row['token_id'],
                        'token_secret' => $row['token_secret'],
                        'localaddr' => $row['localaddr']
                    ];
                }
                $GLOBALS['BITSTREAMS_CACHE_SERVERS'] = $servers;
            } catch (Exception $e) {}
        }

        return $servers;
    }
}

if (!function_exists('bitstreams_get_server')) {
    function bitstreams_get_server($server_key) {
        $servers = bitstreams_get_servers();
        return $servers[$server_key] ?? null;
    }
}

if (!function_exists('bitstreams_save_server')) {
    function bitstreams_save_server($server_key, $data) {
        bitstreams_ensure_tables();
        $name = $data['name'] ?? $server_key;
        $address = $data['address'] ?? '';
        $protocol = $data['protocol'] ?? 'http';
        $tokenId = $data['token_id'] ?? '';
        $tokenSecret = $data['token_secret'] ?? '';
        $localaddr = $data['localaddr'] ?? null;
        $success = false;

        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('servers');
            $pdb->query("
                INSERT INTO {$tb} (server_key, name, address, protocol, token_id, token_secret, localaddr)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    address = VALUES(address),
                    protocol = VALUES(protocol),
                    token_id = VALUES(token_id),
                    token_secret = VALUES(token_secret),
                    localaddr = VALUES(localaddr)
            ", [$server_key, $name, $address, $protocol, $tokenId, $tokenSecret, $localaddr]);
            $success = true;
        } else {
            $pdo = bitstreams_get_pdo();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO plug_bitstreams_servers (server_key, name, address, protocol, token_id, token_secret, localaddr)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        address = VALUES(address),
                        protocol = VALUES(protocol),
                        token_id = VALUES(token_id),
                        token_secret = VALUES(token_secret),
                        localaddr = VALUES(localaddr)
                ");
                $stmt->execute([$server_key, $name, $address, $protocol, $tokenId, $tokenSecret, $localaddr]);
                $success = true;
            }
        }

        if ($success) {
            bitstreams_clear_cache('servers');
        }
        return $success;
    }
}

if (!function_exists('bitstreams_delete_server')) {
    function bitstreams_delete_server($server_key) {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        $success = false;

        if ($pdb) {
            $tb = $pdb->getTableName('servers');
            $pdb->query("DELETE FROM {$tb} WHERE server_key = ?", [$server_key]);
            $success = true;
        } else {
            $pdo = bitstreams_get_pdo();
            if ($pdo) {
                $stmt = $pdo->prepare("DELETE FROM plug_bitstreams_servers WHERE server_key = ?");
                $stmt->execute([$server_key]);
                $success = true;
            }
        }

        if ($success) {
            bitstreams_clear_cache('servers');
        }
        return $success;
    }
}

if (!function_exists('bitstreams_get_inca_hosts')) {
    function bitstreams_get_inca_hosts($ensure = true) {
        if ($GLOBALS['BITSTREAMS_CACHE_INCA_HOSTS'] !== null) {
            return $GLOBALS['BITSTREAMS_CACHE_INCA_HOSTS'];
        }

        if ($ensure) bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        $hosts = [];

        if ($pdb) {
            $tb = $pdb->getTableName('inca_hosts');
            try {
                $stmt = $pdb->query("SELECT * FROM {$tb} ORDER BY id ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $hosts[$row['host_key']] = [
                        'name' => $row['name'],
                        'address' => $row['address'],
                        'username' => $row['username'],
                        'password' => $row['password'],
                        'snmp_community' => $row['snmp_community'] ?: 'public'
                    ];
                }
                $GLOBALS['BITSTREAMS_CACHE_INCA_HOSTS'] = $hosts;
            } catch (Exception $e) {}
            return $hosts;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM plug_bitstreams_inca_hosts ORDER BY id ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $hosts[$row['host_key']] = [
                        'name' => $row['name'],
                        'address' => $row['address'],
                        'username' => $row['username'],
                        'password' => $row['password'],
                        'snmp_community' => $row['snmp_community'] ?: 'public'
                    ];
                }
                $GLOBALS['BITSTREAMS_CACHE_INCA_HOSTS'] = $hosts;
            } catch (Exception $e) {}
        }

        return $hosts;
    }
}

if (!function_exists('bitstreams_get_inca_host')) {
    function bitstreams_get_inca_host($host_key) {
        $hosts = bitstreams_get_inca_hosts();
        return $hosts[$host_key] ?? null;
    }
}

if (!function_exists('bitstreams_save_inca_host')) {
    function bitstreams_save_inca_host($host_key, $data) {
        bitstreams_ensure_tables();
        $name = $data['name'] ?? $host_key;
        $address = $data['address'] ?? '';
        $username = $data['username'] ?? 'admin';
        $password = $data['password'] ?? '';
        $snmpCommunity = $data['snmp_community'] ?? 'public';
        $success = false;

        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('inca_hosts');
            $pdb->query("
                INSERT INTO {$tb} (host_key, name, address, username, password, snmp_community)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    address = VALUES(address),
                    username = VALUES(username),
                    password = VALUES(password),
                    snmp_community = VALUES(snmp_community)
            ", [$host_key, $name, $address, $username, $password, $snmpCommunity]);
            $success = true;
        } else {
            $pdo = bitstreams_get_pdo();
            if ($pdo) {
                $stmt = $pdo->prepare("
                    INSERT INTO plug_bitstreams_inca_hosts (host_key, name, address, username, password, snmp_community)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        address = VALUES(address),
                        username = VALUES(username),
                        password = VALUES(password),
                        snmp_community = VALUES(snmp_community)
                ");
                $stmt->execute([$host_key, $name, $address, $username, $password, $snmpCommunity]);
                $success = true;
            }
        }

        if ($success) {
            bitstreams_clear_cache('inca_hosts');
        }
        return $success;
    }
}

if (!function_exists('bitstreams_delete_inca_host')) {
    function bitstreams_delete_inca_host($host_key) {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        $success = false;

        if ($pdb) {
            $tb = $pdb->getTableName('inca_hosts');
            $pdb->query("DELETE FROM {$tb} WHERE host_key = ?", [$host_key]);
            $success = true;
        } else {
            $pdo = bitstreams_get_pdo();
            if ($pdo) {
                $stmt = $pdo->prepare("DELETE FROM plug_bitstreams_inca_hosts WHERE host_key = ?");
                $stmt->execute([$host_key]);
                $success = true;
            }
        }

        if ($success) {
            bitstreams_clear_cache('inca_hosts');
        }
        return $success;
    }
}

/* =========================================================
 * CORE BITSTREAMS & INCA API COMMUNICATION HELPERS
 * ========================================================= */

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
