<?php
/**
 * Bitstreams Plugin INCA Migration View
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/../models/bitstreams-model.php';

$servers = bitstreams_get_servers();
$incaHosts = bitstreams_get_inca_hosts();

$localaddr = bitstreams_get_setting('localaddr', '172.17.233.130');
$defaultRegion = bitstreams_get_setting('default_region', 'Bitstreams');

$apiUrl = function_exists('url_for') ? url_for('bitstreams_api') : 'index.php?route=bitstreams_api';
?>

<div class="container-fluid p-4">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h2><i class="fa-solid fa-file-import text-primary me-2"></i>INCA Migration Tool</h2>
            <p class="text-muted mb-4">Import XML configurations from INCA devices or files and generate Bitstreams sessions.</p>

            <form id="generateForm">
                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="backup" class="form-label fw-bold">INCA Backup XML Content</label>
                            <textarea name="backup" id="backup" rows="12" class="form-control font-monospace" placeholder="Paste INCA Backup XML content here..."></textarea>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-light mb-3 border-0">
                            <div class="card-body">
                                <label for="inca_host" class="form-label fw-bold">Fetch directly from INCA</label>
                                <select id="inca_host" class="form-select mb-2">
                                    <option value="">Select INCA device...</option>
                                    <?php foreach ($incaHosts as $key => $host): ?>
                                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($host['name']) ?> (<?= htmlspecialchars($host['address']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline-secondary w-100 mb-3" onclick="fetchFromInca()" id="fetchIncaBtn">
                                    <i class="fa-solid fa-download me-1"></i> Fetch Backup
                                </button>

                                <hr>

                                <label for="backup_file" class="form-label fw-bold">Or upload Backup XML File</label>
                                <input type="file" name="backup_file" id="backup_file" class="form-control mb-3">
                            </div>
                        </div>
                        <button type="button" onclick="generateSessions()" id="generateBtn" class="btn btn-primary w-100 p-3 fw-bold">
                            <i class="fa-solid fa-gears me-1"></i> Generate Bitstreams Sessions
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="sessionsContainer" class="mt-4 d-none">
        <ul class="nav nav-tabs" id="sessionTabs" role="tablist"></ul>
        <div class="tab-content mt-4" id="sessionTabsContent"></div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import to Bitstreams</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6 border-end">
                        <div class="mb-3">
                            <label for="server_select" class="form-label fw-bold">Bitstreams Server</label>
                            <select id="server_select" class="form-select" onchange="onServerChange()">
                                <option value="">Select a server...</option>
                                <?php foreach ($servers as $key => $server): ?>
                                    <option value="<?= htmlspecialchars($key) ?>"
                                            data-localaddr="<?= htmlspecialchars($server['localaddr'] ?: $localaddr) ?>">
                                        <?= htmlspecialchars($server['name']) ?> (<?= htmlspecialchars($server['address']) ?>)
                                    </option>
                                <?php endforeach; ?>
                                <option value="custom" data-localaddr="<?= htmlspecialchars($localaddr) ?>">Custom Address...</option>
                            </select>
                        </div>
                        <div id="custom_server_div" class="d-none bg-light p-2 mb-3 rounded border">
                            <div class="mb-2">
                                <label for="protocol_custom" class="form-label small">Protocol</label>
                                <select id="protocol_custom" class="form-select form-select-sm" onchange="fetchTemplates()">
                                    <option value="http">HTTP</option>
                                    <option value="https">HTTPS</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <label for="server_custom" class="form-label small">Custom Server Address</label>
                                <input id="server_custom" class="form-control form-control-sm" placeholder="e.g., 10.0.0.1:8080" onchange="fetchTemplates()">
                            </div>
                            <div class="mb-2">
                                <label for="token_id_custom" class="form-label small">Token ID</label>
                                <input id="token_id_custom" class="form-control form-control-sm" onchange="fetchTemplates()">
                            </div>
                            <div class="mb-2">
                                <label for="token_secret_custom" class="form-label small">Token Secret</label>
                                <input id="token_secret_custom" class="form-control form-control-sm" type="password" onchange="fetchTemplates()">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="template" class="form-label fw-bold">Select Template</label>
                            <select id="template" class="form-select" onchange="showTemplateDetails()"></select>
                        </div>
                        <div id="template_details" class="mb-3 small"></div>
                        <div class="mb-3">
                            <label for="region" class="form-label fw-bold">Region</label>
                            <input id="region" class="form-control" value="<?= htmlspecialchars($defaultRegion) ?>">
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="bg-primary-subtle p-3 rounded h-100 border border-primary-subtle">
                                    <label class="form-label fw-bold">Output URLs</label>
                                    <div class="row g-2 mb-3">
                                        <div class="col-12">
                                            <label for="multicast" class="form-label small">Base Multicast IP</label>
                                            <input id="multicast" class="form-control form-control-sm" placeholder="232.x.x.x" onchange="refreshOutputUrls()">
                                        </div>
                                        <div class="col-12">
                                            <label for="local_addr" class="form-label small">Local Interface Address</label>
                                            <input id="local_addr" class="form-control form-control-sm" value="<?= htmlspecialchars($localaddr) ?>" onchange="refreshOutputUrls()">
                                        </div>
                                        <div class="col-12 mt-2">
                                            <label for="program_number" class="form-label small">MPEG-TS Program Number</label>
                                            <input id="program_number" type="number" class="form-control form-control-sm" value="1">
                                        </div>
                                    </div>
                                    <div id="output_urls_container"></div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="bg-secondary-subtle p-3 rounded h-100 border border-secondary-subtle">
                                    <label class="form-label fw-bold">PID Remapping</label>
                                    <div class="row g-2 mb-2">
                                        <div class="col-12">
                                            <label for="pid_pmt" class="form-label small">PMT PID</label>
                                            <input id="pid_pmt" class="form-control form-control-sm" value="1906">
                                        </div>
                                        <div class="col-12">
                                            <label for="pid_video" class="form-label small">Video PID</label>
                                            <input id="pid_video" class="form-control form-control-sm" value="400">
                                        </div>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label for="pid_aac" class="form-label small">AAC Audio PID</label>
                                            <input id="pid_aac" class="form-control form-control-sm" value="483">
                                        </div>
                                        <div class="col-12">
                                            <label for="pid_ac3" class="form-label small">AC3 Audio PID</label>
                                            <input id="pid_ac3" class="form-control form-control-sm" value="482">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary fw-bold" onclick="submitImport(event)">Create Session</button>
            </div>
        </div>
    </div>
</div>

<script>
    const API_BASE = "<?= $apiUrl ?>";
    let currentJSON = null;
    let importModal = null;
    let sessions = [];
    let currentTemplates = [];

    async function fetchFromInca() {
        const hostKey = document.getElementById("inca_host").value;
        if (!hostKey) {
            alert("Please select an INCA device first.");
            return;
        }

        const btn = document.getElementById("fetchIncaBtn");
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Fetching...';

        const form = new FormData();
        form.append("action", "fetch_inca_backup");
        form.append("host_key", hostKey);

        try {
            const response = await fetch(API_BASE, { method: "POST", body: form });
            const result = await response.json();

            if (response.ok && result.xml) {
                document.getElementById("backup").value = result.xml;
                alert("Backup fetched successfully.");
            } else {
                alert("Error: " + (result.error || "Failed to fetch backup."));
            }
        } catch (e) {
            alert("Request failed: " + e);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function copyJSON(id) {
        navigator.clipboard.writeText(document.getElementById(id).innerText);
    }

    async function generateSessions() {
        const btn = document.getElementById("generateBtn");
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';

        const form = document.getElementById("generateForm");
        const formData = new FormData(form);
        formData.append("action", "generate");

        const serverSelect = document.getElementById("server_select");
        if (serverSelect.value) {
            formData.append("server_key", serverSelect.value);
            if (serverSelect.value === "custom") {
                formData.append("custom_localaddr", document.getElementById("local_addr").value);
            }
        }

        try {
            const response = await fetch(API_BASE, { method: "POST", body: formData });
            sessions = await response.json();
            renderSessions();
        } catch (e) {
            alert("Error generating sessions: " + e);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function renderSessions() {
        const container = document.getElementById("sessionsContainer");
        const tabs = document.getElementById("sessionTabs");
        const content = document.getElementById("sessionTabsContent");

        tabs.innerHTML = "";
        content.innerHTML = "";

        if (sessions.length === 0) {
            container.classList.add("d-none");
            return;
        }

        container.classList.remove("d-none");

        sessions.forEach((session, i) => {
            const tabId = `tab${i}`;
            const btnId = `tab-btn-${i}`;

            const li = document.createElement("li");
            li.className = "nav-item";
            li.role = "presentation";

            const btn = document.createElement("button");
            btn.className = `nav-link ${i === 0 ? 'active' : ''}`;
            btn.id = btnId;
            btn.setAttribute("data-bs-toggle", "tab");
            btn.setAttribute("data-bs-target", `#${tabId}`);
            btn.type = "button";
            btn.role = "tab";
            btn.textContent = session.name;
            li.appendChild(btn);
            tabs.appendChild(li);

            const pane = document.createElement("div");
            pane.className = `tab-pane fade ${i === 0 ? 'show active' : ''}`;
            pane.id = tabId;
            pane.role = "tabpanel";

            const copyBtn = document.createElement("button");
            copyBtn.className = "btn btn-success me-2";
            copyBtn.onclick = () => copyJSON(`json${i}`);
            copyBtn.textContent = "Copy JSON";
            pane.appendChild(copyBtn);

            const importBtn = document.createElement("button");
            importBtn.className = "btn btn-primary";
            importBtn.onclick = () => openImport(i);
            importBtn.textContent = "Add to Bitstreams";
            pane.appendChild(importBtn);

            const pre = document.createElement("pre");
            pre.id = `json${i}`;
            pre.className = "mt-3 bg-dark text-white p-3 rounded";
            pre.textContent = session.json;
            pane.appendChild(pre);

            content.appendChild(pane);
        });
    }

    async function openImport(index) {
        currentJSON = JSON.parse(sessions[index].json);

        let currentIP = currentJSON.playbacks[1].output_urls[0].urls[0].match(/udp:\/\/([^:]+)/)[1];
        document.getElementById("multicast").value = currentIP;

        if (!importModal) {
            importModal = new bootstrap.Modal(document.getElementById('importModal'));
        }

        document.getElementById("server_select").value = "";
        document.getElementById("custom_server_div").classList.add("d-none");
        document.getElementById("template").innerHTML = "<option>Select a server first...</option>";

        importModal.show();
    }

    function refreshOutputUrls() {
        const select = document.getElementById("template");
        const templateId = parseInt(select.value);
        const container = document.getElementById("output_urls_container");
        const baseIp = document.getElementById("multicast").value || "232.0.0.1";
        const localAddr = document.getElementById("local_addr").value;

        if (!templateId) {
            container.innerHTML = "";
            return;
        }

        const template = currentTemplates.find(t => t.id === templateId);
        if (!template || !template.output || !template.output.video) {
            container.innerHTML = "";
            return;
        }

        const numRenditions = template.output.video.length;
        let html = '<label class="form-label">Output URLs</label>';
        for (let i = 0; i < numRenditions; i++) {
            const port = 3001 + i;
            const url = `udp://${baseIp}:${port}?localaddr=${localAddr}`;
            html += `
                <div class="input-group mb-2">
                    <span class="input-group-text">#${i + 1}</span>
                    <input type="text" class="form-control output-url-input" value="${url}">
                </div>
            `;
        }
        container.innerHTML = html;
    }

    function showTemplateDetails() {
        const select = document.getElementById("template");
        const detailsDiv = document.getElementById("template_details");
        const templateId = parseInt(select.value);

        if (!templateId) {
            detailsDiv.innerHTML = "";
            refreshOutputUrls();
            return;
        }

        const template = currentTemplates.find(t => t.id === templateId);
        if (!template || !template.output) {
            detailsDiv.innerHTML = "No details available for this template.";
            return;
        }

        let html = '<div class="card bg-secondary-subtle border-0"><div class="card-body p-2">';

        if (template.output.video && template.output.video.length > 0) {
            html += '<strong>Video:</strong><ul class="mb-1">';
            template.output.video.forEach(v => {
                html += `<li>${v.width}x${v.height} @ ${v.fps}fps (${v.codec}, ${v.bitrate}k)</li>`;
            });
            html += '</ul>';
        }

        if (template.output.audio && template.output.audio.length > 0) {
            html += '<strong>Audio:</strong><ul class="mb-0">';
            template.output.audio.forEach(a => {
                html += `<li>${a.codec} (${a.bitrate}k, ${a.samplerate})</li>`;
            });
            html += '</ul>';
        }

        html += '</div></div>';
        detailsDiv.innerHTML = html;

        refreshOutputUrls();
    }

    function onServerChange() {
        const select = document.getElementById("server_select");
        const option = select.options[select.selectedIndex];

        if (option && option.dataset.localaddr) {
            document.getElementById("local_addr").value = option.dataset.localaddr;
            refreshOutputUrls();
        }

        fetchTemplates();
    }

    async function fetchTemplates() {
        const select = document.getElementById("server_select");
        const customDiv = document.getElementById("custom_server_div");
        const templateSelect = document.getElementById("template");

        let serverKey = select.value;
        if (serverKey === "custom") {
            customDiv.classList.remove("d-none");
        } else {
            customDiv.classList.add("d-none");
        }

        if (!serverKey) return;

        templateSelect.innerHTML = "<option>Loading templates...</option>";

        let form = new FormData();
        form.append("action", "templates");
        form.append("server_key", serverKey);

        if (serverKey === "custom") {
            form.append("custom_protocol", document.getElementById("protocol_custom").value);
            form.append("custom_address", document.getElementById("server_custom").value);
            form.append("custom_token_id", document.getElementById("token_id_custom").value);
            form.append("custom_token_secret", document.getElementById("token_secret_custom").value);
        }

        try {
            let response = await fetch(API_BASE, { method: "POST", body: form });
            let result = await response.json();

            let list = [];
            if (result && result.data && Array.isArray(result.data.list)) {
                list = result.data.list;
            } else if (Array.isArray(result)) {
                list = result;
            } else {
                throw new Error("Invalid response format from server.");
            }

            templateSelect.innerHTML = '<option value="">Select a template...</option>';
            currentTemplates = list;
            list.forEach(t => {
                let option = document.createElement("option");
                option.value = t.id;
                option.textContent = t.name;
                templateSelect.appendChild(option);
            });
        } catch (e) {
            templateSelect.innerHTML = "<option>Error loading templates</option>";
            console.error(e);
        }
    }

    async function submitImport(event) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';

        let payload = structuredClone(currentJSON);
        let template = parseInt(document.getElementById("template").value);
        let region = document.getElementById("region").value;
        let serverKey = document.getElementById("server_select").value;
        let local_addr = document.getElementById("local_addr").value;
        let programNumber = parseInt(document.getElementById("program_number").value);

        payload.regions = [region];
        const outputUrlInputs = document.querySelectorAll(".output-url-input");
        const outputUrls = Array.from(outputUrlInputs).map(input => input.value);

        const pmtPid = document.getElementById("pid_pmt").value;
        const videoPid = document.getElementById("pid_video").value;
        const aacPid = document.getElementById("pid_aac").value;
        const ac3Pid = document.getElementById("pid_ac3").value;

        let mappings = [
            { "order": "0", "type": "PMT", "lang": "*", "input_pid": "*", "codec": "*", "mode": "remap", "output_pid": pmtPid },
            { "order": "1", "type": "video", "lang": "*", "input_pid": "*", "codec": "*", "mode": "remap", "output_pid": videoPid },
            { "order": "2", "type": "audio", "lang": "*", "input_pid": "*", "codec": "aac", "mode": "remap", "output_pid": aacPid },
            { "order": "3", "type": "audio", "lang": "*", "input_pid": "*", "codec": "ac3", "mode": "remap", "output_pid": ac3Pid },
            { "order": "#", "type": "*", "lang": "*", "input_pid": "*", "codec": "*", "mode": "drop", "output_pid": "*" }
        ];

        payload.playbacks.forEach(p => {
            p.template_id = template;
            if (p.output_type == "multicast") {
                p.output_urls[0].region = region;
                p.output_urls[0].urls = outputUrls;
                p.mpegts_settings = {
                    "enable": true,
                    "program_number": programNumber
                };
                p.stream_remap = {
                    "enabled": true,
                    "stream_mappings": mappings
                };
            }
        });

        payload.input_urls.forEach(iu => {
            iu.region = region;
            iu.urls = iu.urls.map(url => url.replace(/localaddr=[^&]+/, `localaddr=${local_addr}`));
        });

        let form = new FormData();
        form.append("action", "push");
        form.append("server_key", serverKey);
        form.append("payload", JSON.stringify(payload));

        if (serverKey === "custom") {
            form.append("custom_protocol", document.getElementById("protocol_custom").value);
            form.append("custom_address", document.getElementById("server_custom").value);
            form.append("custom_token_id", document.getElementById("token_id_custom").value);
            form.append("custom_token_secret", document.getElementById("token_secret_custom").value);
        }

        try {
            let response = await fetch(API_BASE, { method: "POST", body: form });
            let result = await response.json();

            if (result.code >= 200 && result.code < 300) {
                let bsResponse = {};
                try {
                    bsResponse = JSON.parse(result.response);
                } catch(e) {}

                if (bsResponse.err_code === 0) {
                    alert("Session created successfully.");
                    importModal.hide();
                } else {
                    alert("Error: " + (bsResponse.err_message || "Session creation failed."));
                }
            } else {
                let bsResponse = {};
                try {
                    bsResponse = JSON.parse(result.response);
                } catch(e) {}

                alert("Error: " + (bsResponse.err_message || result.response || "Unknown error"));
            }
        } catch (e) {
            alert("Request failed: " + e);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }
</script>
