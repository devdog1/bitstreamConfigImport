<?php
/**
 * Bitstreams Plugin Settings View
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/../models/bitstreams-model.php';

$localaddr = bitstreams_get_setting('localaddr', '172.17.233.130');
$defaultTemplateId = bitstreams_get_setting('default_template_id', '13');
$defaultRegion = bitstreams_get_setting('default_region', 'Bitstreams');

$servers = bitstreams_get_servers();
$incaHosts = bitstreams_get_inca_hosts();

$apiUrl = function_exists('url_for') ? url_for('bitstreams_api') : 'index.php?route=bitstreams_api';
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fa-solid fa-sliders text-primary me-2"></i>Bitstreams Plugin Settings</h2>
            <p class="text-muted mb-0">Manage global settings, Bitstreams Edge servers, and INCA host configurations stored in database tables.</p>
        </div>
    </div>

    <!-- Global Settings Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-gear me-2 text-secondary"></i>Global Configuration</h5>
        </div>
        <div class="card-body">
            <form id="globalSettingsForm" onsubmit="saveGlobalSettings(event)">
                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="localaddr" class="form-label fw-bold">Default Local Interface Address</label>
                        <input type="text" class="form-control" id="localaddr" name="localaddr" value="<?= htmlspecialchars($localaddr) ?>" required>
                        <div class="form-text">Server local interface address (e.g. 172.17.233.130)</div>
                    </div>
                    <div class="col-md-4">
                        <label for="default_template_id" class="form-label fw-bold">Default Bitstreams Template ID</label>
                        <input type="number" class="form-control" id="default_template_id" name="default_template_id" value="<?= htmlspecialchars($defaultTemplateId) ?>" required>
                        <div class="form-text">Template ID used during initial session generation</div>
                    </div>
                    <div class="col-md-4">
                        <label for="default_region" class="form-label fw-bold">Default Region</label>
                        <input type="text" class="form-control" id="default_region" name="default_region" value="<?= htmlspecialchars($defaultRegion) ?>" required>
                        <div class="form-text">Bitstreams target region (e.g. Bitstreams)</div>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <button type="submit" class="btn btn-primary fw-bold" id="saveGlobalBtn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Global Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bitstreams Servers Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-server me-2 text-primary"></i>Bitstreams Edge Servers</h5>
            <button class="btn btn-sm btn-primary fw-bold" onclick="openServerModal()">
                <i class="fa-solid fa-plus me-1"></i> Add Server
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Key</th>
                            <th>Name</th>
                            <th>Address & Protocol</th>
                            <th>Token ID</th>
                            <th>Local Interface</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($servers)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No Bitstreams servers configured.</td></tr>
                        <?php else: ?>
                            <?php foreach ($servers as $sKey => $s): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($sKey) ?></code></td>
                                    <td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td>
                                    <td><?= htmlspecialchars($s['protocol']) ?>://<?= htmlspecialchars($s['address']) ?></td>
                                    <td><small class="font-monospace text-muted"><?= htmlspecialchars(substr($s['token_id'], 0, 16)) ?>...</small></td>
                                    <td><?= htmlspecialchars($s['localaddr'] ?: $localaddr) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick='editServer(<?= json_encode(array_merge(['server_key' => $sKey], $s)) ?>)'>
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteServer('<?= htmlspecialchars($sKey) ?>')">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- INCA Hosts Section -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fa-solid fa-tower-broadcast me-2 text-info"></i>INCA Device Hosts</h5>
            <button class="btn btn-sm btn-info text-white fw-bold" onclick="openIncaHostModal()">
                <i class="fa-solid fa-plus me-1"></i> Add INCA Host
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Key</th>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Username</th>
                            <th>SNMP Community</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($incaHosts)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No INCA hosts configured.</td></tr>
                        <?php else: ?>
                            <?php foreach ($incaHosts as $hKey => $h): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($hKey) ?></code></td>
                                    <td class="fw-bold"><?= htmlspecialchars($h['name']) ?></td>
                                    <td><?= htmlspecialchars($h['address']) ?></td>
                                    <td><?= htmlspecialchars($h['username']) ?></td>
                                    <td><code><?= htmlspecialchars($h['snmp_community']) ?></code></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-1" onclick='editIncaHost(<?= json_encode(array_merge(['host_key' => $hKey], $h)) ?>)'>
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteIncaHost('<?= htmlspecialchars($hKey) ?>')">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Server Modal -->
<div class="modal fade" id="serverModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serverModalTitle">Bitstreams Server</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="serverForm" onsubmit="saveServer(event)">
                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_server_key" class="form-label fw-bold">Server Key</label>
                        <input type="text" class="form-control" id="modal_server_key" name="server_key" placeholder="e.g. server1" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_server_name" class="form-label fw-bold">Display Name</label>
                        <input type="text" class="form-control" id="modal_server_name" name="name" placeholder="e.g. Production Edge 1" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label for="modal_server_protocol" class="form-label fw-bold">Protocol</label>
                            <select class="form-select" id="modal_server_protocol" name="protocol">
                                <option value="http">HTTP</option>
                                <option value="https">HTTPS</option>
                            </select>
                        </div>
                        <div class="col-8">
                            <label for="modal_server_address" class="form-label fw-bold">Server Address</label>
                            <input type="text" class="form-control" id="modal_server_address" name="address" placeholder="127.0.0.1:8080" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_token_id" class="form-label fw-bold">Token ID</label>
                        <input type="text" class="form-control" id="modal_token_id" name="token_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_token_secret" class="form-label fw-bold">Token Secret</label>
                        <input type="password" class="form-control" id="modal_token_secret" name="token_secret" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_server_localaddr" class="form-label fw-bold">Local Interface Address (Optional)</label>
                        <input type="text" class="form-control" id="modal_server_localaddr" name="localaddr" placeholder="Defaults to global localaddr">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Server</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- INCA Host Modal -->
<div class="modal fade" id="incaModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incaModalTitle">INCA Device Host</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="incaForm" onsubmit="saveIncaHost(event)">
                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_host_key" class="form-label fw-bold">Host Key</label>
                        <input type="text" class="form-control" id="modal_host_key" name="host_key" placeholder="e.g. inca1" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_host_name" class="form-label fw-bold">Display Name</label>
                        <input type="text" class="form-control" id="modal_host_name" name="name" placeholder="e.g. INCA Transcoder 1" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_host_address" class="form-label fw-bold">IP Address</label>
                        <input type="text" class="form-control" id="modal_host_address" name="address" placeholder="10.0.0.50" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="modal_host_username" class="form-label fw-bold">Username</label>
                            <input type="text" class="form-control" id="modal_host_username" name="username" value="admin" required>
                        </div>
                        <div class="col-6">
                            <label for="modal_host_password" class="form-label fw-bold">Password</label>
                            <input type="password" class="form-control" id="modal_host_password" name="password" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_snmp_community" class="form-label fw-bold">SNMP Community String</label>
                        <input type="text" class="form-control" id="modal_snmp_community" name="snmp_community" value="public" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white fw-bold">Save INCA Host</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const API_URL = "<?= $apiUrl ?>";

    async function saveGlobalSettings(e) {
        e.preventDefault();
        const btn = document.getElementById("saveGlobalBtn");
        btn.disabled = true;

        const form = new FormData(document.getElementById("globalSettingsForm"));
        form.append("action", "save_settings");

        try {
            const res = await fetch(API_URL, { method: "POST", body: form });
            const data = await res.json();
            if (data.success) {
                alert("Global settings saved successfully.");
            } else {
                alert("Error: " + (data.error || "Failed to save settings"));
            }
        } catch (err) {
            alert("Request failed: " + err);
        } finally {
            btn.disabled = false;
        }
    }

    function openServerModal() {
        document.getElementById("serverModalTitle").textContent = "Add Bitstreams Server";
        document.getElementById("modal_server_key").readOnly = false;
        document.getElementById("serverForm").reset();
        new bootstrap.Modal(document.getElementById("serverModal")).show();
    }

    function editServer(data) {
        document.getElementById("serverModalTitle").textContent = "Edit Bitstreams Server";
        document.getElementById("modal_server_key").value = data.server_key;
        document.getElementById("modal_server_key").readOnly = true;
        document.getElementById("modal_server_name").value = data.name;
        document.getElementById("modal_server_protocol").value = data.protocol || 'http';
        document.getElementById("modal_server_address").value = data.address;
        document.getElementById("modal_token_id").value = data.token_id;
        document.getElementById("modal_token_secret").value = data.token_secret;
        document.getElementById("modal_server_localaddr").value = data.localaddr || '';
        new bootstrap.Modal(document.getElementById("serverModal")).show();
    }

    async function saveServer(e) {
        e.preventDefault();
        const form = new FormData(document.getElementById("serverForm"));
        form.append("action", "save_server");

        try {
            const res = await fetch(API_URL, { method: "POST", body: form });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert("Error: " + (data.error || "Failed to save server"));
            }
        } catch (err) {
            alert("Request failed: " + err);
        }
    }

    async function deleteServer(sKey) {
        if (!confirm(`Are you sure you want to delete server '${sKey}'?`)) return;

        const form = new FormData();
        form.append("action", "delete_server");
        form.append("server_key", sKey);

        try {
            const res = await fetch(API_URL, { method: "POST", body: form });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert("Error: " + (data.error || "Failed to delete server"));
            }
        } catch (err) {
            alert("Request failed: " + err);
        }
    }

    function openIncaHostModal() {
        document.getElementById("incaModalTitle").textContent = "Add INCA Host";
        document.getElementById("modal_host_key").readOnly = false;
        document.getElementById("incaForm").reset();
        new bootstrap.Modal(document.getElementById("incaModal")).show();
    }

    function editIncaHost(data) {
        document.getElementById("incaModalTitle").textContent = "Edit INCA Host";
        document.getElementById("modal_host_key").value = data.host_key;
        document.getElementById("modal_host_key").readOnly = true;
        document.getElementById("modal_host_name").value = data.name;
        document.getElementById("modal_host_address").value = data.address;
        document.getElementById("modal_host_username").value = data.username;
        document.getElementById("modal_host_password").value = data.password;
        document.getElementById("modal_snmp_community").value = data.snmp_community;
        new bootstrap.Modal(document.getElementById("incaModal")).show();
    }

    async function saveIncaHost(e) {
        e.preventDefault();
        const form = new FormData(document.getElementById("incaForm"));
        form.append("action", "save_inca_host");

        try {
            const res = await fetch(API_URL, { method: "POST", body: form });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert("Error: " + (data.error || "Failed to save INCA host"));
            }
        } catch (err) {
            alert("Request failed: " + err);
        }
    }

    async function deleteIncaHost(hKey) {
        if (!confirm(`Are you sure you want to delete INCA host '${hKey}'?`)) return;

        const form = new FormData();
        form.append("action", "delete_inca_host");
        form.append("host_key", hKey);

        try {
            const res = await fetch(API_URL, { method: "POST", body: form });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert("Error: " + (data.error || "Failed to delete INCA host"));
            }
        } catch (err) {
            alert("Request failed: " + err);
        }
    }
</script>
