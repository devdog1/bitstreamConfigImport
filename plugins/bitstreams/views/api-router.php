<?php
/**
 * Bitstreams Plugin API Router
 * Handles all AJAX API endpoints for the Bitstreams plugin.
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/../models/bitstreams-model.php';

$action = $_REQUEST['action'] ?? '';

// Check permission helper for plugin namespace
function checkPluginPermission($perm) {
    if (function_exists('has_permission')) {
        if (!has_permission($perm) && !has_permission('bitstream.view') && !has_permission('bitstream.edit')) {
            http_response_code(403);
            echo json_encode(["error" => "Forbidden: Missing {$perm} permission"]);
            exit;
        }
    }
}

function verifyCsrfIfPost() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (function_exists('csrf_verify')) {
            if (isset($_POST['csrf_token'])) {
                csrf_verify();
            }
        }
    }
}

switch ($action) {

    /* =========================================================
     * 1. STREAM OVERVIEW & MONITORING ENDPOINTS
     * ========================================================= */

    case 'list_bitstreams':
        checkPluginPermission('bitstreams_view');
        header('Content-Type: application/json');

        $servers = bitstreams_get_servers();
        $targetKey = $_GET['key'] ?? null;

        if ($targetKey) {
            if (!isset($servers[$targetKey])) {
                http_response_code(404);
                echo json_encode(["error" => "Server not found"]);
                exit;
            }
            $servers = [$targetKey => $servers[$targetKey]];
        }

        $allStreams = [];

        foreach ($servers as $sKey => $server) {
            $protocol = $server['protocol'] ?? 'http';
            $address = $server['address'];
            $tokenId = $server['token_id'];
            $tokenSecret = $server['token_secret'];

            $baseUrl = "{$protocol}://{$address}/api/v3/streams/";
            $streams = fetchAllStreams($baseUrl, $tokenId, $tokenSecret);

            foreach ($streams as $stream) {
                $status = $stream['status'] ?? 'unknown';

                $allStreams[] = [
                    'stream_id' => $stream['id'] ?? '',
                    'name' => $stream['name'] ?? 'Unnamed',
                    'status' => $status,
                    'bitrate' => $stream['bitrate'] ?? null,
                    'errors' => $stream['errors'] ?? null,
                    'server_key' => $sKey,
                    'server_name' => $server['name'],
                    'server_address' => $address,
                    'server_protocol' => $protocol,
                    'type' => 'bitstreams'
                ];
            }
        }

        echo json_encode($allStreams);
        exit;

    case 'list_inca':
        checkPluginPermission('bitstreams_view');
        header('Content-Type: application/json');

        $incaHosts = bitstreams_get_inca_hosts();
        $targetKey = $_GET['key'] ?? null;

        if ($targetKey) {
            if (!isset($incaHosts[$targetKey])) {
                http_response_code(404);
                echo json_encode(["error" => "INCA host not found"]);
                exit;
            }
            $incaHosts = [$targetKey => $incaHosts[$targetKey]];
        }

        $groupedStreams = [];

        foreach ($incaHosts as $hKey => $host) {
            $address = $host['address'];
            $user = $host['username'];
            $pass = $host['password'];
            $community = $host['snmp_community'] ?? 'public';

            $snmpStreams = getIncaStreams($address, $community);
            $snmpMap = [];
            foreach ($snmpStreams as $s) {
                $snmpMap[$s['name']][] = $s;
            }

            $apiOutputs = incaApiCall($address, '/dvp/streams/ip/outputs', $user, $pass) ?: [];
            $apiSources = incaApiCall($address, '/dvp/streams/ip/sources', $user, $pass) ?: [];

            $sourceMap = [];
            foreach ($apiSources as $src) {
                if (isset($src['stream_id'])) {
                    $sourceMap[$src['stream_id']] = $src;
                }
            }

            $processedNames = [];

            foreach ($apiOutputs as $out) {
                $dn = $out['dn'] ?? '';
                if (!$dn) continue;

                $fullName = trim($dn);
                if (!str_contains($fullName, "1680") && !str_contains($fullName, "1681")) continue;

                $baseName = preg_replace('/\s*-\s*168[01]/', '', $fullName);
                $processedNames[$baseName] = true;

                $instances = $snmpMap[$baseName] ?? [];
                $hasSnmpMatch = !empty($instances);

                if (!isset($groupedStreams[$hKey . '_' . $baseName])) {
                    $enriched = [
                        'uuid' => $out['uuid'] ?? '',
                        'filter' => $out['filter'] ?? '',
                        'outputs_detailed' => $out['outputs'] ?? [],
                        'source' => isset($out['source_stream_id']) ? ($sourceMap[$out['source_stream_id']] ?? null) : null
                    ];

                    $groupedStreams[$hKey . '_' . $baseName] = [
                        'stream_id' => "inca_group_" . md5($baseName),
                        'name' => $baseName,
                        'status' => $hasSnmpMatch ? 'inca_active' : 'inca_down',
                        'bitrate' => $hasSnmpMatch ? ($instances[0]['bitrate'] ?? null) : null,
                        'errors' => $hasSnmpMatch ? ($instances[0]['errors'] ?? null) : null,
                        'server_key' => $hKey,
                        'server_name' => $host['name'],
                        'server_address' => $address,
                        'server_user' => $user,
                        'server_pass' => $pass,
                        'type' => 'inca',
                        'instances' => $instances,
                        'enriched' => $enriched
                    ];
                }
            }

            foreach ($snmpMap as $bName => $instances) {
                if (isset($processedNames[$bName])) continue;

                $groupedStreams[$hKey . '_' . $bName] = [
                    'stream_id' => "inca_group_" . md5($bName),
                    'name' => $bName,
                    'status' => 'inca_active',
                    'bitrate' => $instances[0]['bitrate'] ?? null,
                    'errors' => $instances[0]['errors'] ?? null,
                    'server_key' => $hKey,
                    'server_name' => $host['name'],
                    'server_address' => $address,
                    'server_user' => $user,
                    'server_pass' => $pass,
                    'type' => 'inca',
                    'instances' => $instances,
                    'enriched' => null
                ];
            }
        }

        echo json_encode(array_values($groupedStreams));
        exit;

    case 'stream_action':
        checkPluginPermission('bitstreams_edit');
        verifyCsrfIfPost();
        header('Content-Type: application/json');

        $sKey = $_POST['server_key'] ?? '';
        $streamId = $_POST['stream_id'] ?? '';
        $act = $_POST['action'] ?? '';
        $type = $_POST['type'] ?? 'bitstreams';

        if (!$sKey || !$streamId || !$act) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required fields"]);
            exit;
        }

        if ($type === 'inca') {
            $hosts = bitstreams_get_inca_hosts();
            if (!isset($hosts[$sKey])) {
                http_response_code(404);
                echo json_encode(["error" => "INCA host not found"]);
                exit;
            }
            $host = $hosts[$sKey];

            $apiPath = "/dvp/streams/ip/outputs/{$streamId}/";
            $currentData = incaApiCall($host['address'], $apiPath, $host['username'], $host['password']);

            if (!$currentData) {
                http_response_code(404);
                echo json_encode(["error" => "INCA stream output not found"]);
                exit;
            }

            $currentDataObj = json_decode(json_encode($currentData), false);

            if ($act === 'start' || $act === 'stop') {
                $enableState = ($act === 'start');
                if (isset($currentDataObj->streams) && is_array($currentDataObj->streams)) {
                    foreach ($currentDataObj->streams as $strItem) {
                        $strItem->enabled = $enableState;
                    }
                }

                $payload = json_encode($currentDataObj, JSON_UNESCAPED_SLASHES);
                $url = "http://{$host['address']}/sys/svc/core/api/v1{$apiPath}";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$host['username']}:{$host['password']}");
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                echo json_encode(['code' => $code, 'response' => $resp]);
                exit;
            } elseif ($act === 'restart') {
                if (isset($currentDataObj->streams) && is_array($currentDataObj->streams)) {
                    foreach ($currentDataObj->streams as $strItem) {
                        $strItem->enabled = false;
                    }
                }
                $payload = json_encode($currentDataObj, JSON_UNESCAPED_SLASHES);
                $url = "http://{$host['address']}/sys/svc/core/api/v1{$apiPath}";

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$host['username']}:{$host['password']}");
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_exec($ch);
                curl_close($ch);

                sleep(5);

                if (isset($currentDataObj->streams) && is_array($currentDataObj->streams)) {
                    foreach ($currentDataObj->streams as $strItem) {
                        $strItem->enabled = true;
                    }
                }
                $payload = json_encode($currentDataObj, JSON_UNESCAPED_SLASHES);

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, "{$host['username']}:{$host['password']}");
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                echo json_encode(['code' => $code, 'response' => $resp]);
                exit;
            }

            http_response_code(400);
            echo json_encode(["error" => "Invalid action"]);
            exit;
        } else {
            $servers = bitstreams_get_servers();
            if (!isset($servers[$sKey])) {
                http_response_code(404);
                echo json_encode(["error" => "Server not found"]);
                exit;
            }
            $server = $servers[$sKey];

            $protocol = $server['protocol'] ?? 'http';
            $address = $server['address'];
            $tokenId = $server['token_id'];
            $tokenSecret = $server['token_secret'];

            $baseUrl = "{$protocol}://{$address}/api/v3/streams/{$streamId}/";

            if ($act === 'start') {
                $res = apiCall("{$baseUrl}start/", $tokenId, $tokenSecret, 'PUT');
            } elseif ($act === 'stop') {
                $res = apiCall("{$baseUrl}stop/", $tokenId, $tokenSecret, 'PUT');
            } elseif ($act === 'restart') {
                $stopRes = apiCall("{$baseUrl}stop/", $tokenId, $tokenSecret, 'PUT');
                sleep(2);
                $res = apiCall("{$baseUrl}start/", $tokenId, $tokenSecret, 'PUT');
            } else {
                http_response_code(400);
                echo json_encode(["error" => "Invalid action"]);
                exit;
            }

            echo json_encode($res);
            exit;
        }

    case 'get_stream_details':
        checkPluginPermission('bitstreams_view');
        header('Content-Type: application/json');

        $sKey = $_GET['server_key'] ?? '';
        $streamId = $_GET['stream_id'] ?? '';
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 50;

        if (!$sKey || !$streamId) {
            http_response_code(400);
            echo json_encode(["error" => "Missing server_key or stream_id"]);
            exit;
        }

        $servers = bitstreams_get_servers();
        if (!isset($servers[$sKey])) {
            http_response_code(404);
            echo json_encode(["error" => "Server not found"]);
            exit;
        }
        $server = $servers[$sKey];

        $protocol = $server['protocol'] ?? 'http';
        $address = $server['address'];
        $tokenId = $server['token_id'];
        $tokenSecret = $server['token_secret'];

        $baseUrl = "{$protocol}://{$address}/api/v3/streams/{$streamId}/";

        $streamRes = apiCall($baseUrl, $tokenId, $tokenSecret);
        $sourceInfoRes = apiCall("{$baseUrl}source_info/", $tokenId, $tokenSecret);
        $sourceReportsRes = apiCall("{$baseUrl}source_reports/", $tokenId, $tokenSecret);

        $notifUrl = "{$protocol}://{$address}/api/v3/notifications/?stream_id={$streamId}&page={$page}&limit={$limit}";
        $notifRes = apiCall($notifUrl, $tokenId, $tokenSecret);

        echo json_encode([
            'stream' => json_decode($streamRes['response'], true),
            'source_info' => json_decode($sourceInfoRes['response'], true),
            'source_reports' => json_decode($sourceReportsRes['response'], true),
            'notifications' => json_decode($notifRes['response'], true)
        ]);
        exit;

    case 'get_inca_html':
        checkPluginPermission('bitstreams_view');
        header('Content-Type: text/html');

        $sKey = $_GET['server_key'] ?? '';
        $progId = $_GET['prog_id'] ?? '';

        if (!$sKey || !$progId) {
            http_response_code(400);
            echo "Missing parameters";
            exit;
        }

        $hosts = bitstreams_get_inca_hosts();
        if (!isset($hosts[$sKey])) {
            http_response_code(404);
            echo "INCA host not found";
            exit;
        }
        $host = $hosts[$sKey];

        $rawHtml = incaRawCall($host['address'], "/widgets/ts_view?prog_id={$progId}", $host['username'], $host['password']);

        if (!$rawHtml) {
            echo "Failed to load live data from INCA device.";
            exit;
        }

        $sanitizedHtml = preg_replace_callback('/(src|href)=["\']([^"\']+)["\']/i', function($matches) use ($sKey) {
            $attr = $matches[1];
            $url = $matches[2];
            if (str_contains($url, 'widgets/ts_view') || str_contains($url, '.png') || str_contains($url, '.gif') || str_contains($url, '.css')) {
                return "{$attr}=\"index.php?route=bitstreams_api&action=inca_proxy_resource&server_key={$sKey}&path=" . urlencode($url) . "\"";
            }
            return $matches[0];
        }, $rawHtml);

        echo $sanitizedHtml;
        exit;

    case 'inca_proxy_resource':
        checkPluginPermission('bitstreams_view');
        $sKey = $_GET['server_key'] ?? '';
        $path = $_GET['path'] ?? '';

        if (!$sKey || !$path) {
            http_response_code(400);
            echo "Missing parameters";
            exit;
        }

        $hosts = bitstreams_get_inca_hosts();
        if (!isset($hosts[$sKey])) {
            http_response_code(404);
            echo "INCA host not found";
            exit;
        }
        $host = $hosts[$sKey];

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $content = incaRawCall($host['address'], $path, $host['username'], $host['password']);

        if (str_ends_with($path, '.png')) {
            header('Content-Type: image/png');
        } elseif (str_ends_with($path, '.gif')) {
            header('Content-Type: image/gif');
        } elseif (str_ends_with($path, '.css')) {
            header('Content-Type: text/css');
        } else {
            header('Content-Type: text/plain');
        }

        echo $content;
        exit;

    /* =========================================================
     * 2. MIGRATION & SESSIONS ENDPOINTS
     * ========================================================= */

    case 'templates':
        checkPluginPermission('bitstreams_edit');
        header('Content-Type: application/json');

        $sKey = $_POST['server_key'] ?? '';
        $protocol = $_POST['custom_protocol'] ?? 'http';
        $address = $_POST['custom_address'] ?? '';
        $tokenId = $_POST['custom_token_id'] ?? '';
        $tokenSecret = $_POST['custom_token_secret'] ?? '';

        if ($sKey && $sKey !== 'custom') {
            $servers = bitstreams_get_servers();
            if (!isset($servers[$sKey])) {
                http_response_code(404);
                echo json_encode(["error" => "Server not found"]);
                exit;
            }
            $s = $servers[$sKey];
            $protocol = $s['protocol'] ?? 'http';
            $address = $s['address'];
            $tokenId = $s['token_id'];
            $tokenSecret = $s['token_secret'];
        }

        if (!$address || !$tokenId || !$tokenSecret) {
            http_response_code(400);
            echo json_encode(["error" => "Incomplete server credentials"]);
            exit;
        }

        $url = "{$protocol}://{$address}/api/v3/template/";
        $res = apiCall($url, $tokenId, $tokenSecret);

        if ($res['code'] >= 200 && $res['code'] < 300) {
            echo $res['response'];
        } else {
            http_response_code($res['code']);
            echo json_encode(["error" => "Failed to fetch templates", "details" => $res['response']]);
        }
        exit;

    case 'fetch_inca_backup':
        checkPluginPermission('bitstreams_edit');
        header('Content-Type: application/json');

        $hostKey = $_POST['host_key'] ?? '';
        $hosts = bitstreams_get_inca_hosts();

        if (!$hostKey || !isset($hosts[$hostKey])) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid or missing INCA device selection."]);
            exit;
        }

        $host = $hosts[$hostKey];
        $backupXml = fetchIncaBackup($host['address'], $host['username'], $host['password']);

        if ($backupXml) {
            echo json_encode(["success" => true, "xml" => $backupXml]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to retrieve configuration backup from INCA device."]);
        }
        exit;

    case 'generate':
        checkPluginPermission('bitstreams_edit');
        header('Content-Type: application/json');

        $sKey = $_POST['server_key'] ?? null;
        $customLocalAddr = $_POST['custom_localaddr'] ?? null;
        $serverConfig = null;

        if ($sKey) {
            $servers = bitstreams_get_servers();
            if (isset($servers[$sKey])) {
                $serverConfig = $servers[$sKey];
            } elseif ($sKey === 'custom' && $customLocalAddr) {
                $serverConfig = ['localaddr' => $customLocalAddr];
            }
        }

        if (!$serverConfig) {
            $defaultLocal = bitstreams_get_setting('localaddr', '172.17.233.130');
            $serverConfig = ['localaddr' => $defaultLocal];
        }

        $text = "";
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] === UPLOAD_ERR_OK) {
            $text = file_get_contents($_FILES['backup_file']['tmp_filename'] ?? $_FILES['backup_file']['tmp_name']);
        } elseif (isset($_POST['backup'])) {
            $text = $_POST['backup'];
        }

        $channels = parseChannels($text);
        $result = [];

        foreach ($channels as $channel) {
            $result[] = [
                "name" => $channel["name"],
                "json" => generateSession($channel, $serverConfig)
            ];
        }

        echo json_encode($result);
        exit;

    case 'push':
        checkPluginPermission('bitstreams_edit');
        verifyCsrfIfPost();
        header('Content-Type: application/json');

        $sKey = $_POST['server_key'] ?? '';
        $payload = $_POST['payload'] ?? '';

        $protocol = $_POST['custom_protocol'] ?? 'http';
        $address = $_POST['custom_address'] ?? '';
        $tokenId = $_POST['custom_token_id'] ?? '';
        $tokenSecret = $_POST['custom_token_secret'] ?? '';

        if ($sKey && $sKey !== 'custom') {
            $servers = bitstreams_get_servers();
            if (!isset($servers[$sKey])) {
                http_response_code(404);
                echo json_encode(["error" => "Server not found"]);
                exit;
            }
            $s = $servers[$sKey];
            $protocol = $s['protocol'] ?? 'http';
            $address = $s['address'];
            $tokenId = $s['token_id'];
            $tokenSecret = $s['token_secret'];
        }

        if (!$address || !$tokenId || !$tokenSecret || !$payload) {
            http_response_code(400);
            echo json_encode(["error" => "Incomplete request details"]);
            exit;
        }

        $url = "{$protocol}://{$address}/api/v3/streams/";
        $res = apiCall($url, $tokenId, $tokenSecret, 'POST', $payload);

        echo json_encode($res);
        exit;

    /* =========================================================
     * 3. PLUGIN SETTINGS AND CONFIG MANAGEMENT ENDPOINTS
     * ========================================================= */

    case 'get_settings':
        checkPluginPermission('bitstreams_settings');
        header('Content-Type: application/json');

        echo json_encode([
            'settings' => [
                'localaddr' => bitstreams_get_setting('localaddr', '172.17.233.130'),
                'default_template_id' => bitstreams_get_setting('default_template_id', '13'),
                'default_region' => bitstreams_get_setting('default_region', 'Bitstreams')
            ],
            'servers' => bitstreams_get_servers(),
            'inca_hosts' => bitstreams_get_inca_hosts()
        ]);
        exit;

    case 'save_settings':
        checkPluginPermission('bitstreams_settings');
        verifyCsrfIfPost();
        header('Content-Type: application/json');

        if (isset($_POST['localaddr'])) bitstreams_set_setting('localaddr', trim($_POST['localaddr']));
        if (isset($_POST['default_template_id'])) bitstreams_set_setting('default_template_id', trim($_POST['default_template_id']));
        if (isset($_POST['default_region'])) bitstreams_set_setting('default_region', trim($_POST['default_region']));

        if (function_exists('log_action')) {
            log_action('BITSTREAMS_SETTINGS_UPDATE', $_POST);
        }

        echo json_encode(["success" => true, "message" => "Settings updated successfully"]);
        exit;

    case 'save_server':
        checkPluginPermission('bitstreams_settings');
        verifyCsrfIfPost();
        header('Content-Type: application/json');

        $sKey = trim($_POST['server_key'] ?? '');
        if (!$sKey) {
            http_response_code(400);
            echo json_encode(["error" => "Server Key is required"]);
            exit;
        }

        $sData = [
            'name' => trim($_POST['name'] ?? $sKey),
            'address' => trim($_POST['address'] ?? ''),
            'protocol' => trim($_POST['protocol'] ?? 'http'),
            'token_id' => trim($_POST['token_id'] ?? ''),
            'token_secret' => trim($_POST['token_secret'] ?? ''),
            'localaddr' => trim($_POST['localaddr'] ?? '')
        ];

        bitstreams_save_server($sKey, $sData);

        if (function_exists('log_action')) {
            log_action('BITSTREAMS_SERVER_SAVE', ['server_key' => $sKey]);
        }

        echo json_encode(["success" => true, "message" => "Server saved successfully"]);
        exit;

    case 'delete_server':
        checkPluginPermission('bitstreams_settings');
        verifyCsrfIfPost();
        header('Content-Type: application/json');

        $sKey = trim($_POST['server_key'] ?? '');
        if ($sKey) {
            bitstreams_delete_server($sKey);
            if (function_exists('log_action')) {
                log_action('BITSTREAMS_SERVER_DELETE', ['server_key' => $sKey]);
            }
        }

        echo json_encode(["success" => true, "message" => "Server deleted successfully"]);
        exit;

    case 'save_inca_host':
        checkPluginPermission('bitstreams_settings');
        verifyCsrfIfPost();
        header('Content-Type: application/json');

        $hKey = trim($_POST['host_key'] ?? '');
        if (!$hKey) {
            http_response_code(400);
            echo json_encode(["error" => "Host Key is required"]);
            exit;
        }

        $hData = [
            'name' => trim($_POST['name'] ?? $hKey),
            'address' => trim($_POST['address'] ?? ''),
            'username' => trim($_POST['username'] ?? 'admin'),
            'password' => trim($_POST['password'] ?? ''),
            'snmp_community' => trim($_POST['snmp_community'] ?? 'public')
        ];

        bitstreams_save_inca_host($hKey, $hData);

        if (function_exists('log_action')) {
            log_action('BITSTREAMS_INCA_HOST_SAVE', ['host_key' => $hKey]);
        }

        echo json_encode(["success" => true, "message" => "INCA host saved successfully"]);
        exit;

    case 'delete_inca_host':
        checkPluginPermission('bitstreams_settings');
        verifyCsrfIfPost();
        header('Content-Type: application/json');

        $hKey = trim($_POST['host_key'] ?? '');
        if ($hKey) {
            bitstreams_delete_inca_host($hKey);
            if (function_exists('log_action')) {
                log_action('BITSTREAMS_INCA_HOST_DELETE', ['host_key' => $hKey]);
            }
        }

        echo json_encode(["success" => true, "message" => "INCA host deleted successfully"]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(["error" => "Invalid API action '{$action}'"]);
        exit;
}
