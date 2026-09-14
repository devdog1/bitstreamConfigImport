<?php
/**
 * Bitstreams Plugin Servers and INCA Hosts Model
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution
}

require_once __DIR__ . '/db.php';

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
