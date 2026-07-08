<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'AzureADSSO.php';

$auth = new Auth($CONFIG);
$auth->requireLogin();

if (!$auth->hasPermission('bitstream.edit')) {
    http_response_code(403);
    die("Access Denied: You do not have the 'bitstream.edit' permission.");
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bitstreams CSV Import</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
        .sticky-header th {
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Bitstreams Tool</a>
        <div class="navbar-nav">
            <a class="nav-link" href="index.php">Migration</a>
            <a class="nav-link active" href="csv_import.php">CSV Import</a>
            <a class="nav-link" href="overview.php">Overview</a>
            <a class="nav-link" href="logout.php">Logout (<?= htmlspecialchars($auth->user()['name']) ?>)</a>
        </div>
    </div>
</nav>

<div class="container-fluid p-4">
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title mb-4">Bitstreams CSV Import</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="csv_file" class="form-label fw-bold">Upload CSV File</label>
                    <input type="file" id="csv_file" class="form-control" accept=".csv">
                    <div class="form-text">
                        Expected format: Source Multicast, Program Number, Source Name, Output Multicast, Starting Port, PMT PID, Video PID, AAC PID, AC3 PID, Template ID
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="server_select" class="form-label fw-bold">Bitstreams Server</label>
                    <select id="server_select" class="form-select" onchange="onServerChange()">
                        <option value="">Select a server...</option>
                        <?php foreach ($CONFIG['servers'] as $key => $server): ?>
                            <option value="<?= htmlspecialchars($key) ?>"
                                    data-localaddr="<?= htmlspecialchars($server['localaddr'] ?? $CONFIG['localaddr']) ?>">
                                <?= htmlspecialchars($server['name']) ?> (<?= htmlspecialchars($server['address']) ?>)
                            </option>
                        <?php endforeach; ?>
                        <option value="custom">Custom Address...</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="local_addr" class="form-label fw-bold">Local Interface Address</label>
                    <input id="local_addr" class="form-control" value="<?= htmlspecialchars($CONFIG['localaddr']) ?>">
                </div>
                <div class="col-md-2">
                    <label for="region" class="form-label fw-bold">Region</label>
                    <input id="region" class="form-control" value="<?= htmlspecialchars($CONFIG['default_region']) ?>">
                </div>
            </div>

            <div id="custom_server_div" class="d-none mt-3 p-3 bg-light border rounded">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label small">Protocol</label>
                        <select id="protocol_custom" class="form-select form-select-sm" onchange="fetchTemplates()">
                            <option value="http">HTTP</option>
                            <option value="https">HTTPS</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Custom Server Address</label>
                        <input id="server_custom" class="form-control form-control-sm" placeholder="e.g., 10.0.0.1:8080" onchange="fetchTemplates()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Token ID</label>
                        <input id="token_id_custom" class="form-control form-control-sm" onchange="fetchTemplates()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Token Secret</label>
                        <input id="token_secret_custom" class="form-control form-control-sm" type="password" onchange="fetchTemplates()">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="importContainer" class="d-none">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Verify and Edit Data</h5>
                <button class="btn btn-primary" onclick="pushToBitstreams()" id="pushBtn">
                    <i class="bi bi-cloud-upload me-2"></i>Push All to Bitstreams
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 sticky-header" id="csvTable">
                        <thead class="table-light">
                            <tr>
                                <th>Source Multicast</th>
                                <th>Prog #</th>
                                <th>Name</th>
                                <th>Output MC</th>
                                <th>Start Port</th>
                                <th>PMT</th>
                                <th>Video</th>
                                <th>AAC</th>
                                <th>AC3</th>
                                <th>Template ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let csvData = [];
    let currentTemplates = [];
    let templatesLoading = false;

    document.getElementById('csv_file').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            parseCSV(text);
        };
        reader.readAsText(file);
    });

    function parseCSV(text) {
        const lines = text.split(/\r?\n/);
        csvData = [];

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;

            // Robust CSV parsing for comma-separated values with optional quotes
            const cols = [];
            let current = "";
            let inQuotes = false;
            for (let j = 0; j < line.length; j++) {
                const char = line[j];
                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    cols.push(current.trim());
                    current = "";
                } else {
                    current += char;
                }
            }
            cols.push(current.trim());

            if (cols.length < 5) continue;

            // Skip header if present
            if (i === 0 && (cols[0].toLowerCase().includes("source") || cols[2].toLowerCase().includes("name"))) {
                continue;
            }

            csvData.push({
                source_multicast: cols[0] || '',
                program_number: cols[1] || '1',
                name: cols[2] || '',
                output_multicast: cols[3] || '',
                output_port: cols[4] || '3001',
                pid_pmt: cols[5] || '1906',
                pid_video: cols[6] || '400',
                pid_aac: cols[7] || '483',
                pid_ac3: cols[8] || '482',
                template_id: cols[9] || '13',
                status: 'Pending'
            });
        }

        renderTable();
        document.getElementById('importContainer').classList.remove('d-none');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderTable() {
        const tbody = document.querySelector('#csvTable tbody');
        tbody.innerHTML = '';

        csvData.forEach((row, index) => {
            const tr = document.createElement('tr');

            const createInput = (field, type, style = "") => {
                const input = document.createElement('input');
                input.type = type;
                input.className = 'form-control form-control-sm';
                input.value = row[field];
                if (style) input.style = style;
                input.addEventListener('change', (e) => updateData(index, field, e.target.value));
                return input;
            };

            const td1 = document.createElement('td'); td1.appendChild(createInput('source_multicast', 'text'));
            const td2 = document.createElement('td'); td2.appendChild(createInput('program_number', 'number', 'width: 70px'));
            const td3 = document.createElement('td'); td3.appendChild(createInput('name', 'text'));
            const td4 = document.createElement('td'); td4.appendChild(createInput('output_multicast', 'text'));
            const td5 = document.createElement('td'); td5.appendChild(createInput('output_port', 'number', 'width: 80px'));
            const td6 = document.createElement('td'); td6.appendChild(createInput('pid_pmt', 'number', 'width: 70px'));
            const td7 = document.createElement('td'); td7.appendChild(createInput('pid_video', 'number', 'width: 70px'));
            const td8 = document.createElement('td'); td8.appendChild(createInput('pid_aac', 'number', 'width: 70px'));
            const td9 = document.createElement('td'); td9.appendChild(createInput('pid_ac3', 'number', 'width: 70px'));
            const td10 = document.createElement('td'); td10.appendChild(createInput('template_id', 'number', 'width: 70px'));

            const tdStatus = document.createElement('td');
            tdStatus.className = 'status-cell';
            tdStatus.textContent = row.status;

            [td1, td2, td3, td4, td5, td6, td7, td8, td9, td10, tdStatus].forEach(td => tr.appendChild(td));
            tbody.appendChild(tr);
        });
    }

    function updateData(index, field, value) {
        csvData[index][field] = value;
    }

    function onServerChange() {
        const select = document.getElementById("server_select");
        const option = select.options[select.selectedIndex];
        const customDiv = document.getElementById("custom_server_div");

        if (select.value === "custom") {
            customDiv.classList.remove("d-none");
        } else {
            customDiv.classList.add("d-none");
            if (option && option.dataset.localaddr) {
                document.getElementById("local_addr").value = option.dataset.localaddr;
            }
        }

        fetchTemplates();
    }

    async function fetchTemplates() {
        const select = document.getElementById("server_select");
        let serverKey = select.value;
        if (!serverKey) return;

        templatesLoading = true;
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
            if (result && result.data && Array.isArray(result.data.list)) {
                currentTemplates = result.data.list;
            }
        } catch (e) {
            console.error("Error fetching templates:", e);
        } finally {
            templatesLoading = false;
        }
    }

    async function pushToBitstreams() {
        const serverKey = document.getElementById("server_select").value;
        if (!serverKey) {
            alert("Please select a Bitstreams server first.");
            return;
        }

        if (templatesLoading) {
            alert("Please wait while templates are loading...");
            return;
        }

        const pushBtn = document.getElementById("pushBtn");
        pushBtn.disabled = true;

        const localAddr = document.getElementById("local_addr").value;
        const region = document.getElementById("region").value;

        for (let i = 0; i < csvData.length; i++) {
            const row = csvData[i];
            const statusCell = document.querySelectorAll('.status-cell')[i];
            statusCell.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pushing...';

            // Generate Payload
            const payload = generatePayload(row, localAddr, region);

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
                    let bsResponse = {};
                    try { bsResponse = JSON.parse(result.response); } catch(e) {}
                    if (bsResponse.err_code === 0) {
                        statusCell.innerHTML = '<span class="badge bg-success">Success</span>';
                        row.status = 'Success';
                    } else {
                        statusCell.innerHTML = `<span class="badge bg-danger" title="${escapeHtml(bsResponse.err_message || 'Unknown error')}">Failed</span>`;
                        row.status = 'Failed';
                    }
                } else {
                    statusCell.innerHTML = '<span class="badge bg-danger">HTTP Error</span>';
                    row.status = 'Failed';
                }
            } catch (e) {
                statusCell.innerHTML = '<span class="badge bg-danger">Request Failed</span>';
                row.status = 'Failed';
            }
        }

        pushBtn.disabled = false;
    }

    function generatePayload(row, localAddr, region) {
        // Find template to know number of renditions
        const templateId = parseInt(row.template_id);
        const template = currentTemplates.find(t => parseInt(t.id) === templateId);
        const numRenditions = (template && template.output && template.output.video) ? template.output.video.length : 1;

        const outputUrls = [];
        const startPort = parseInt(row.output_port);
        for (let i = 0; i < numRenditions; i++) {
            outputUrls.push(`udp://${row.output_multicast}:${startPort + i}?localaddr=${localAddr}`);
        }

        // Handle source multicast which might already have query params
        let sourceUrl = row.source_multicast;
        if (sourceUrl.includes('?')) {
            if (!sourceUrl.includes('localaddr=')) {
                sourceUrl += `&localaddr=${localAddr}`;
            }
        } else {
            sourceUrl += `?localaddr=${localAddr}`;
        }
        if (!sourceUrl.startsWith('udp://')) {
            sourceUrl = 'udp://' + sourceUrl;
        }

        return {
            "name": row.name,
            "description": row.name,
            "input_type": "multicast_pull",
            "input_urls": [{
                "region": region,
                "urls": [sourceUrl]
            }],
            "regions": [region],
            "playbacks": [
                {
                    "output_name": row.name + "-web",
                    "template_id": templateId,
                    "output_type": "http",
                    "http_settings": {
                        "visibility": "public",
                        "enable_hls": true,
                        "enable_dash": true,
                        "recording_settings": { "enabled": false, "base_path": "" }
                    }
                },
                {
                    "output_name": row.name + "-udp",
                    "template_id": templateId,
                    "output_type": "multicast",
                    "mpegts_settings": {
                        "enable": true,
                        "program_number": parseInt(row.program_number)
                    },
                    "stream_remap": {
                        "enabled": true,
                        "stream_mappings": [
                            { "order": "0", "type": "PMT", "lang": "*", "input_pid": "*", "codec": "*", "mode": "remap", "output_pid": row.pid_pmt },
                            { "order": "1", "type": "video", "lang": "*", "input_pid": "*", "codec": "*", "mode": "remap", "output_pid": row.pid_video },
                            { "order": "2", "type": "audio", "lang": "*", "input_pid": "*", "codec": "aac", "mode": "remap", "output_pid": row.pid_aac },
                            { "order": "3", "type": "audio", "lang": "*", "input_pid": "*", "codec": "ac3", "mode": "remap", "output_pid": row.pid_ac3 },
                            { "order": "#", "type": "*", "lang": "*", "input_pid": "*", "codec": "*", "mode": "drop", "output_pid": "*" }
                        ]
                    },
                    "output_urls": [{
                        "region": region,
                        "urls": outputUrls
                    }]
                }
            ],
            "enable_failover": false
        };
    }
</script>
</body>
</html>
