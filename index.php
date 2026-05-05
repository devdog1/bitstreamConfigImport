<?php
require_once 'config.php';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>INCA Migration Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre {
            background: #212529;
            color: white;
            padding: 20px;
            border-radius: 8px;
            max-height: 700px;
            overflow: auto;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Bitstreams Tool</a>
        <div class="navbar-nav">
            <a class="nav-link active" href="index.php">Migration</a>
            <a class="nav-link" href="overview.php">Overview</a>
        </div>
    </div>
</nav>

<div class="container-fluid p-4">
    <div class="card">
        <div class="card-body">
            <h2>INCA Migration Tool</h2>
            <form id="generateForm">
                <div class="mb-3">
                    <label for="backup" class="form-label">INCA Backup XML Content</label>
                    <textarea name="backup" id="backup" rows="8" class="form-control"></textarea>
                </div>
                <div class="mb-3">
                    <label for="backup_file" class="form-label">Or upload Backup XML File</label>
                    <input type="file" name="backup_file" id="backup_file" class="form-control">
                </div>
                <button type="button" onclick="generateSessions()" id="generateBtn" class="btn btn-primary">Generate Sessions</button>
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import to Bitstreams</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="server_select" class="form-label">Bitstreams Server</label>
                    <select id="server_select" class="form-select" onchange="onServerChange()">
                        <option value="">Select a server...</option>
                        <?php foreach ($CONFIG['servers'] as $key => $server): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                    data-localaddr="<?= htmlspecialchars($server['localaddr'] ?? $CONFIG['localaddr']) ?>">
                                <?= htmlspecialchars($server['name']) ?> (<?= htmlspecialchars($server['address']) ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="custom" data-localaddr="<?= htmlspecialchars($CONFIG['localaddr']) ?>">Custom Address...</option>
                    </select>
                </div>
                <div id="custom_server_div" class="d-none">
                    <div class="mb-3">
                        <label for="protocol_custom" class="form-label">Protocol</label>
                        <select id="protocol_custom" class="form-select" onchange="fetchTemplates()">
                            <option value="http">HTTP</option>
                            <option value="https">HTTPS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="server_custom" class="form-label">Custom Server Address</label>
                        <input id="server_custom" class="form-control" placeholder="e.g., 10.0.0.1:8080" onchange="fetchTemplates()">
                    </div>
                    <div class="mb-3">
                        <label for="token_id_custom" class="form-label">Token ID</label>
                        <input id="token_id_custom" class="form-control" onchange="fetchTemplates()">
                    </div>
                    <div class="mb-3">
                        <label for="token_secret_custom" class="form-label">Token Secret</label>
                        <input id="token_secret_custom" class="form-control" type="password" onchange="fetchTemplates()">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="template" class="form-label">Select Template</label>
                    <select id="template" class="form-select" onchange="showTemplateDetails()"></select>
                </div>
                <div id="template_details" class="mb-3 small"></div>
                <div class="mb-3">
                    <label for="multicast" class="form-label">Base Multicast IP (232.x.x.x)</label>
                    <input id="multicast" class="form-control" placeholder="232.x.x.x" onchange="refreshOutputUrls()">
                </div>
                <div id="output_urls_container" class="mb-3"></div>

                <hr>
                <div class="mb-3">
                    <label class="form-label fw-bold">PID Remapping</label>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="pid_pmt" class="form-label small">PMT PID</label>
                            <input id="pid_pmt" class="form-control form-control-sm" value="1906">
                        </div>
                        <div class="col-6">
                            <label for="pid_video" class="form-label small">Video PID</label>
                            <input id="pid_video" class="form-control form-control-sm" value="400">
                        </div>
                    </div>
                    <div id="audio_pids_container"></div>
                </div>
                <hr>

                <div class="mb-3">
                    <label for="region" class="form-label">Region</label>
                    <input id="region" class="form-control" value="<?= htmlspecialchars($CONFIG['default_region']) ?>">
                </div>
                <div class="mb-3">
                    <label for="local_addr" class="form-label">Local Interface Address</label>
                    <input id="local_addr" class="form-control" value="<?= htmlspecialchars($CONFIG['localaddr']) ?>" onchange="refreshOutputUrls()">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="submitImport(event)">Create Session</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentJSON = null;
    let importModal = null;
    let sessions = [];
    let currentTemplates = [];

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

        // Pass the current selected server to generate sessions with correct localaddr
        const serverSelect = document.getElementById("server_select");
        if (serverSelect.value) {
            formData.append("server_key", serverSelect.value);
            if (serverSelect.value === "custom") {
                formData.append("custom_localaddr", document.getElementById("local_addr").value);
            }
        }

        try {
            const response = await fetch("api/generate.php", { method: "POST", body: formData });
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
            pre.className = "mt-3";
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

        // Reset server select
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
            refreshPidFields();
            return;
        }

        const template = currentTemplates.find(t => t.id === templateId);
        if (!template || !template.output) {
            detailsDiv.innerHTML = "No details available for this template.";
            return;
        }

        let html = '<div class="card bg-secondary-subtle border-0"><div class="card-body p-2">';

        // Video Params
        if (template.output.video && template.output.video.length > 0) {
            html += '<strong>Video:</strong><ul class="mb-1">';
            template.output.video.forEach(v => {
                html += `<li>${v.width}x${v.height} @ ${v.fps}fps (${v.codec}, ${v.bitrate}k)</li>`;
            });
            html += '</ul>';
        }

        // Audio Params
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
        refreshPidFields();
    }

    function refreshPidFields() {
        const select = document.getElementById("template");
        const container = document.getElementById("audio_pids_container");
        const templateId = parseInt(select.value);

        if (!templateId) {
            container.innerHTML = "";
            return;
        }

        const template = currentTemplates.find(t => t.id === templateId);
        if (!template || !template.output || !template.output.audio) {
            container.innerHTML = "";
            return;
        }

        let html = '<label class="form-label small">Audio PIDs</label>';
        template.output.audio.forEach((a, i) => {
            // Default to 483, 482, 481...
            const defaultPid = 483 - i;
            html += `
                <div class="input-group input-group-sm mb-1">
                    <span class="input-group-text">Audio #${i + 1} (${a.codec})</span>
                    <input type="text" class="form-control audio-pid-input" data-codec="${a.codec}" value="${defaultPid}">
                </div>
            `;
        });
        container.innerHTML = html;
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
        form.append("server_key", serverKey);

        if (serverKey === "custom") {
            form.append("custom_protocol", document.getElementById("protocol_custom").value);
            form.append("custom_address", document.getElementById("server_custom").value);
            form.append("custom_token_id", document.getElementById("token_id_custom").value);
            form.append("custom_token_secret", document.getElementById("token_secret_custom").value);
        }

        try {
            let response = await fetch("api/templates.php", { method: "POST", body: form });
            let result = await response.json();

            // Handle the new nested response format
            let list = [];
            if (result && result.data && Array.isArray(result.data.list)) {
                list = result.data.list;
            } else if (Array.isArray(result)) {
                // Fallback for old format if necessary
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
        let multicast = document.getElementById("multicast").value;
        let region = document.getElementById("region").value;
        let serverKey = document.getElementById("server_select").value;
        let local_addr = document.getElementById("local_addr").value;

        payload.regions = [region];
        const outputUrlInputs = document.querySelectorAll(".output-url-input");
        const outputUrls = Array.from(outputUrlInputs).map(input => input.value);

        payload.playbacks.forEach(p => {
            p.template_id = template;
            if (p.output_type == "multicast") {
                p.output_urls[0].region = region;
                p.output_urls[0].urls = outputUrls;
            }
        });

        // PID Remapping
        const pmtPid = document.getElementById("pid_pmt").value;
        const videoPid = document.getElementById("pid_video").value;
        const audioPidInputs = document.querySelectorAll(".audio-pid-input");

        let mappings = [
            {
                "order": "0",
                "type": "PMT",
                "lang": "*",
                "input_pid": "*",
                "codec": "*",
                "mode": "remap",
                "output_pid": pmtPid
            },
            {
                "order": "1",
                "type": "video",
                "lang": "*",
                "input_pid": "*",
                "codec": "*",
                "mode": "remap",
                "output_pid": videoPid
            }
        ];

        audioPidInputs.forEach((input, i) => {
            mappings.push({
                "order": (i + 2).toString(),
                "type": "audio",
                "lang": "*",
                "input_pid": "*",
                "codec": input.dataset.codec || "*",
                "mode": "remap",
                "output_pid": input.value
            });
        });

        mappings.push({
            "order": "#",
            "type": "*",
            "lang": "*",
            "input_pid": "*",
            "codec": "*",
            "mode": "drop",
            "output_pid": "*"
        });

        payload.stream_remap = {
            "enabled": true,
            "stream_mappings": mappings
        };

        payload.input_urls.forEach(iu => {
            iu.region = region;
            iu.urls = iu.urls.map(url => url.replace(/localaddr=[^&]+/, `localaddr=${local_addr}`));
        });

        let form = new FormData();
        form.append("server_key", serverKey);
        form.append("payload", JSON.stringify(payload));

        if (serverKey === "custom") {
            form.append("custom_protocol", document.getElementById("protocol_custom").value);
            form.append("custom_address", document.getElementById("server_custom").value);
            form.append("custom_token_id", document.getElementById("token_id_custom").value);
            form.append("custom_token_secret", document.getElementById("token_secret_custom").value);
        }

        try {
            let response = await fetch("api/push.php", { method: "POST", body: form });
            let result = await response.json();

            if (result.code >= 200 && result.code < 300) {
                // Parse the inner response from the Bitstreams server
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
</body>
</html>
