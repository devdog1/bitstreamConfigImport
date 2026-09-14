<?php
/**
 * Bitstreams Plugin Database Connections & Settings Model
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution
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

if (!function_exists('bitstreams_get_dw_pdo')) {
    function bitstreams_get_dw_pdo() {
        static $dwPdo = null;
        if ($dwPdo === null) {
            $host = bitstreams_get_setting('dw_dbhost', '127.0.0.1');
            $user = bitstreams_get_setting('dw_dbuser', 'dw_user');
            $pass = bitstreams_get_setting('dw_dbpass', '');
            if ($host) {
                try {
                    $dsn = "mysql:host={$host};dbname=cdr;charset=utf8mb4";
                    $dwPdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 5
                    ]);
                } catch (Exception $e) {
                    try {
                        $dsn = "mysql:host={$host};charset=utf8mb4";
                        $dwPdo = new PDO($dsn, $user, $pass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_TIMEOUT => 5
                        ]);
                    } catch (Exception $ex) {
                        return null;
                    }
                }
            }
        }
        return $dwPdo;
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

            $pdb->createTable('video_links', "
                id INT AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(255) NOT NULL,
                device VARCHAR(255) NOT NULL,
                purpose VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ");

            $pdb->createTable('eia_grid', "
                id INT AUTO_INCREMENT PRIMARY KEY,
                EIANum VARCHAR(50) NOT NULL,
                CenterFreq VARCHAR(50) NOT NULL,
                `Use` VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ");

            $pdb->createTable('voice_links', "
                id INT AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(255) NOT NULL,
                device VARCHAR(255) NOT NULL,
                purpose VARCHAR(255) NOT NULL,
                url TEXT NOT NULL,
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
            $defaultDacAddress = '127.0.0.1';

            bitstreams_set_setting('localaddr', $defaultLocaladdr);
            bitstreams_set_setting('default_template_id', (string)$defaultTemplateId);
            bitstreams_set_setting('default_region', $defaultRegion);
            bitstreams_set_setting('dacqueryAddress', $defaultDacAddress);
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

        // Seed EIA Grid if empty
        $eiaItems = bitstreams_get_eia_grid();
        if (count($eiaItems) === 0) {
            $sampleEIAs = [
                ['EIANum' => '2', 'CenterFreq' => '57.00', 'Use' => 'Digital Video'],
                ['EIANum' => '3', 'CenterFreq' => '63.00', 'Use' => 'Digital Video'],
                ['EIANum' => '4', 'CenterFreq' => '69.00', 'Use' => 'Digital Video'],
                ['EIANum' => '5', 'CenterFreq' => '79.00', 'Use' => 'Digital Video'],
                ['EIANum' => '6', 'CenterFreq' => '85.00', 'Use' => 'Digital Video'],
                ['EIANum' => '7', 'CenterFreq' => '177.00', 'Use' => 'Digital Video'],
                ['EIANum' => '8', 'CenterFreq' => '183.00', 'Use' => 'Digital Video'],
                ['EIANum' => '9', 'CenterFreq' => '189.00', 'Use' => 'Digital Video'],
                ['EIANum' => '10', 'CenterFreq' => '195.00', 'Use' => 'Digital Video'],
                ['EIANum' => '11', 'CenterFreq' => '201.00', 'Use' => 'Digital Video'],
                ['EIANum' => '12', 'CenterFreq' => '207.00', 'Use' => 'Digital Video'],
                ['EIANum' => '13', 'CenterFreq' => '213.00', 'Use' => 'Digital Video'],
                ['EIANum' => '14', 'CenterFreq' => '123.00', 'Use' => 'Docsis'],
                ['EIANum' => '15', 'CenterFreq' => '129.00', 'Use' => 'Docsis'],
                ['EIANum' => '16', 'CenterFreq' => '135.00', 'Use' => 'Digital Video'],
                ['EIANum' => '17', 'CenterFreq' => '141.00', 'Use' => 'Digital Video'],
                ['EIANum' => '18', 'CenterFreq' => '147.00', 'Use' => 'Digital Video'],
                ['EIANum' => '19', 'CenterFreq' => '153.00', 'Use' => 'Digital Video'],
            ];

            $pdb = bitstreams_get_db_helper();
            if ($pdb) {
                $tb = $pdb->getTableName('eia_grid');
                foreach ($sampleEIAs as $e) {
                    $pdb->query("INSERT INTO {$tb} (EIANum, CenterFreq, `Use`) VALUES (?, ?, ?)", [$e['EIANum'], $e['CenterFreq'], $e['Use']]);
                }
            } else {
                $pdo = bitstreams_get_pdo();
                if ($pdo) {
                    $stmt = $pdo->prepare("INSERT INTO plug_bitstreams_eia_grid (EIANum, CenterFreq, `Use`) VALUES (?, ?, ?)");
                    foreach ($sampleEIAs as $e) {
                        $stmt->execute([$e['EIANum'], $e['CenterFreq'], $e['Use']]);
                    }
                }
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
