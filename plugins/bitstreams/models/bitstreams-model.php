<?php
/**
 * Bitstreams Plugin Model
 * Handles database interaction for settings, servers, and INCA hosts inside plug_bitstreams_* namespace.
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution if required
}

if (!function_exists('bitstreams_get_db_helper')) {
    function bitstreams_get_db_helper() {
        if (class_exists('PluginDatabase')) {
            return new PluginDatabase('bitstreams');
        }
        return null;
    }
}

if (!function_exists('bitstreams_get_pdo')) {
    function bitstreams_get_pdo() {
        if (function_exists('get_db_connection')) {
            try {
                return get_db_connection();
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
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

        bitstreams_seed_defaults();
        $ensured = true;
    }
}

if (!function_exists('bitstreams_seed_defaults')) {
    function bitstreams_seed_defaults() {
        global $CONFIG;

        // Seed Settings if empty
        if (count(bitstreams_get_all_settings(false)) === 0) {
            $defaultLocaladdr = $CONFIG['localaddr'] ?? '172.17.233.130';
            $defaultTemplateId = $CONFIG['default_template_id'] ?? 13;
            $defaultRegion = $CONFIG['default_region'] ?? 'Bitstreams';

            bitstreams_set_setting('localaddr', $defaultLocaladdr);
            bitstreams_set_setting('default_template_id', (string)$defaultTemplateId);
            bitstreams_set_setting('default_region', $defaultRegion);
        }

        // Seed Servers if empty
        if (count(bitstreams_get_servers(false)) === 0) {
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
        if (count(bitstreams_get_inca_hosts(false)) === 0) {
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
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('settings');
            $stmt = $pdb->query("SELECT setting_value FROM {$tb} WHERE setting_key = ?", [$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : $default;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT setting_value FROM plug_bitstreams_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['setting_value'] : $default;
        }

        return $default;
    }
}

if (!function_exists('bitstreams_set_setting')) {
    function bitstreams_set_setting($key, $value) {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('settings');
            $pdb->query("
                INSERT INTO {$tb} (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ", [$key, $value, $value]);
            return true;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            $stmt = $pdo->prepare("
                INSERT INTO plug_bitstreams_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_get_all_settings')) {
    function bitstreams_get_all_settings($ensure = true) {
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
            } catch (Exception $e) {}
        }
        return $settings;
    }
}

if (!function_exists('bitstreams_get_servers')) {
    function bitstreams_get_servers($ensure = true) {
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
            return true;
        }

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
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_delete_server')) {
    function bitstreams_delete_server($server_key) {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('servers');
            $pdb->query("DELETE FROM {$tb} WHERE server_key = ?", [$server_key]);
            return true;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM plug_bitstreams_servers WHERE server_key = ?");
            $stmt->execute([$server_key]);
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_get_inca_hosts')) {
    function bitstreams_get_inca_hosts($ensure = true) {
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
            return true;
        }

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
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_delete_inca_host')) {
    function bitstreams_delete_inca_host($host_key) {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('inca_hosts');
            $pdb->query("DELETE FROM {$tb} WHERE host_key = ?", [$host_key]);
            return true;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM plug_bitstreams_inca_hosts WHERE host_key = ?");
            $stmt->execute([$host_key]);
            return true;
        }

        return false;
    }
}
