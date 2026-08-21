<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'AzureADSSO.php';
require_once __DIR__ . '/plugins/bitstreams/models/bitstreams-model.php';

$auth = new Auth($CONFIG);
$auth->requireLogin();

if (!$auth->hasPermission('bitstream.view') && !$auth->hasPermission('bitstreams_view')) {
    http_response_code(403);
    die("Access Denied: You do not have the 'bitstreams_view' permission.");
}

$servers = bitstreams_get_servers();
$incaHosts = bitstreams_get_inca_hosts();

$deviceConfig = [
    'bitstreams' => array_map(fn($s) => ['name' => $s['name']], $servers),
    'inca' => array_map(fn($h) => ['name' => $h['name']], $incaHosts)
];
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stream Overview - Bitstreams Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .table-responsive {
            min-height: 200px;
        }
        .inca-html-content {
            font-family: sans-serif;
            background: white;
            padding: 15px;
            border-radius: 4px;
        }
        .ts_view_table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background: white;
            border: 1px solid #dee2e6;
        }
        .ts_view_table td {
            padding: 4px 8px;
            border-bottom: 1px solid #eee;
        }
        .ts_titlerow { background: #f8f9fa; }
        .ts_bitrate_cell { text-align: right; font-family: monospace; }
        .ts_buttons { display: none; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Bitstreams Tool</a>
        <div class="navbar-nav">
            <a class="nav-link" href="index.php">Migration</a>
            <a class="nav-link active" href="overview.php">Overview</a>
            <a class="nav-link" href="logout.php">Logout (<?= htmlspecialchars($auth->user()['name']) ?>)</a>
        </div>
    </div>
</nav>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Stream Overview</h2>
        <button class="btn btn-outline-primary" onclick="loadStreams()">Refresh Status</button>
    </div>

    <!-- Device Status Dashboard -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white">
            <h6 class="mb-0 fw-bold">Device Pull Status</h6>
        </div>
        <div class="card-body">
            <div id="deviceStatusList" class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
                <!-- Devices will be injected here -->
            </div>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="streamSearchInput" class="form-control border-start-0" placeholder="Search streams by name, ID or status..." onkeyup="filterStreams()">
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <select id="serverFilterSelect" class="form-select" onchange="filterStreams()">
                        <option value="">All Servers & Hosts</option>
                        <?php foreach ($servers as $sKey => $s): ?>
                            <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['name']) ?> (Bitstreams)</option>
                        <?php endforeach; ?>
                        <?php foreach ($incaHosts as $hKey => $h): ?>
                            <option value="<?= htmlspecialchars($h['name']) ?>"><?= htmlspecialchars($h['name']) ?> (INCA)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 col-6">
                    <select id="statusFilterSelect" class="form-select" onchange="filterStreams()">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="down">Down / Disconnected</option>
                    </select>
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="resetFilters()" title="Reset Filters">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-3 shadow-sm border-0">
        <div class="table-responsive">
            <table id="streamsTable" class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Server</th>
                        <th>Bitrate</th>
                        <th>Errors</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="streamsTableBody">
                    <!-- Data loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="incaDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incaDetailsModalTitle">INCA Stream Instances</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="incaTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#incaTabSummary">Overview</button>
                    </li>
                    <li class="nav-item" id="incaSourceTabLi"></li>
                </ul>

                <div class="tab-content" id="incaTabContent">
                    <div class="tab-pane fade show active" id="incaTabSummary">
                        <div id="incaEnrichedInfo" class="mb-4"></div>

                        <h6 class="fw-bold">SNMP Instances</h6>
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Bitrate</th>
                                    <th>Errors</th>
                                </tr>
                            </thead>
                            <tbody id="incaInstancesBody"></tbody>
                        </table>
                    </div>
                    <div class="tab-pane fade" id="incaTabSource">
                        <div id="incaSourceContent" class="inca-html-content"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalTitle">Stream Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPreview">Preview</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSourceInfo">Source Info</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabNotifications">Notifications</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSourceReports">Source Reports</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tabPreview">
                        <div class="ratio ratio-16x9 bg-dark rounded overflow-hidden">
                            <video id="videoPlayer" controls></video>
                        </div>
                        <div id="noPlaybackMsg" class="alert alert-warning mt-2 d-none">
                            No HTTP/HLS playback available for this stream.
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tabSourceInfo">
                        <pre id="sourceInfoPre" class="bg-dark text-white p-3 rounded" style="max-height: 500px; overflow: auto;"></pre>
                    </div>
                    <div class="tab-pane fade" id="tabNotifications">
                        <div class="table-responsive" style="max-height: 500px; overflow: auto;">
                            <table class="table table-sm table-striped table-hover">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 180px;">Time</th>
                                        <th style="width: 100px;">Type</th>
                                        <th style="width: 150px;">Title</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody id="notificationsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tabSourceReports">
                        <pre id="sourceReportsPre" class="bg-dark text-white p-3 rounded" style="max-height: 500px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const incaDetailsModal = new bootstrap.Modal(document.getElementById('incaDetailsModal'));
    let dataTable = null;
    let allStreamsData = [];

    const DEVICE_CONFIG = <?= json_encode($deviceConfig) ?>;

    document.getElementById('detailsModal').addEventListener('hidden.bs.modal', () => {
        const video = document.getElementById('videoPlayer');
        video.pause();
        video.innerHTML = "";
        video.load();
    });

    function updateDeviceUI(key, platform, status, count = 0) {
        let el = document.getElementById(`dev-${platform}-${key}`);
        if (!el) {
            const container = document.getElementById('deviceStatusList');
            el = document.createElement('div');
            el.id = `dev-${platform}-${key}`;
            el.className = 'col';
            container.appendChild(el);
        }

        const platformLabel = platform === 'bitstreams' ? 'Bitstreams' : 'INCA';
        const name = DEVICE_CONFIG[platform][key] ? DEVICE_CONFIG[platform][key].name : key;

        let badgeClass = 'bg-secondary';
        let statusText = 'Pending';
        let spinner = '';

        if (status === 'pulling') {
            badgeClass = 'bg-primary';
            statusText = 'Pulling...';
            spinner = '<div class="spinner-border spinner-border-sm ms-2" role="status"></div>';
        } else if (status === 'completed') {
            badgeClass = 'bg-success';
            statusText = `Completed (${count})`;
        } else if (status === 'error') {
            badgeClass = 'bg-danger';
            statusText = 'Error';
        }

        el.innerHTML = `
            <div class="card h-100 border-0 bg-light">
                <div class="card-body p-2 d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span class="badge rounded-pill text-dark border small" style="font-size: 0.65rem;">${platformLabel}</span>
                        ${spinner}
                    </div>
                    <div class="fw-bold small text-truncate" title="${name}">${name}</div>
                    <div class="mt-auto pt-1">
                        <span class="badge ${badgeClass} w-100" style="font-size: 0.7rem;">${statusText}</span>
                    </div>
                </div>
            </div>
        `;
    }

    function renderTable(dataToRender = allStreamsData) {
        if (dataTable) {
            dataTable.destroy();
        }

        const tbody = document.getElementById("streamsTableBody");
        tbody.innerHTML = "";

        if (dataToRender.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No streams match active filters.</td></tr>';
        } else {
            dataToRender.forEach((stream, idx) => {
                const tr = document.createElement("tr");

                let statusBadge = "";
                let actions = "";
                let nameHtml = "";

                if (stream.type === 'inca') {
                    statusBadge = stream.status === 'inca_down'
                        ? '<span class="badge bg-danger">Down</span>'
                        : '<span class="badge bg-info">Active</span>';

                    const incaUuid = (stream.enriched && stream.enriched.uuid) ? stream.enriched.uuid : stream.stream_id;

                    const actionsInca = `
                        <button class="btn btn-sm btn-info text-white" onclick="viewIncaDetails(${idx})">Details</button>
                        <button class="btn btn-sm btn-primary" onclick="streamAction('${stream.server_key}', '${incaUuid}', 'start', 'inca')">Start</button>
                        <button class="btn btn-sm btn-danger" onclick="streamAction('${stream.server_key}', '${incaUuid}', 'stop', 'inca')">Stop</button>
                        <button class="btn btn-sm btn-warning" onclick="streamAction('${stream.server_key}', '${incaUuid}', 'restart', 'inca')">Restart</button>
                    `;
                    actions = actionsInca;

                    const incaUrl = `http://${stream.server_user}:${stream.server_pass}@${stream.server_address}/controlpanel?deviceid=1`;
                    nameHtml = `<a href="${incaUrl}" target="_blank" class="text-decoration-none">${stream.name}</a>`;
                } else {
                    statusBadge = stream.status === 'active'
                        ? '<span class="badge bg-success">Active</span>'
                        : (stream.status === 'disconnected' ? '<span class="badge bg-danger">Disconnected</span>' : `<span class="badge bg-secondary">${stream.status}</span>`);

                    const sid = stream.stream_id || stream.id || '';

                    actions = `
                        <button class="btn btn-sm btn-info text-white" onclick="viewDetails('${stream.server_key}', '${sid}', '${stream.name.replace(/'/g, "\\'")}')">Details</button>
                        <button class="btn btn-sm btn-primary" onclick="streamAction('${stream.server_key}', '${sid}', 'start')" ${stream.status === 'active' ? 'disabled' : ''}>Start</button>
                        <button class="btn btn-sm btn-danger" onclick="streamAction('${stream.server_key}', '${sid}', 'stop')" ${stream.status !== 'active' ? 'disabled' : ''}>Stop</button>
                        <button class="btn btn-sm btn-warning" onclick="streamAction('${stream.server_key}', '${sid}', 'restart')">Restart</button>
                    `;

                    const streamUrl = `${stream.server_protocol}://${stream.server_address}/encoding/live/${sid}`;
                    nameHtml = `<a href="${streamUrl}" target="_blank" class="text-decoration-none">${stream.name}</a>`;
                }

                const bitrate = stream.bitrate ? (parseInt(stream.bitrate) / 1000000).toFixed(2) + " Mbps" : "-";
                const errors = stream.errors !== undefined ? stream.errors : "-";

                tr.innerHTML = `
                    <td class="align-middle fw-bold">${nameHtml}</td>
                    <td class="align-middle">${statusBadge}</td>
                    <td class="align-middle">${stream.server_name}</td>
                    <td class="align-middle">${bitrate}</td>
                    <td class="align-middle">${errors}</td>
                    <td class="align-middle">${actions}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        dataTable = $('#streamsTable').DataTable({
            "pageLength": 25,
            "order": [[0, "asc"]]
        });
    }

    function filterStreams() {
        const query = (document.getElementById("streamSearchInput").value || "").toLowerCase();
        const serverFilter = (document.getElementById("serverFilterSelect").value || "").toLowerCase();
        const statusFilter = (document.getElementById("statusFilterSelect").value || "").toLowerCase();

        const filtered = allStreamsData.filter(s => {
            const matchQuery = !query ||
                (s.name && s.name.toLowerCase().includes(query)) ||
                (s.server_name && s.server_name.toLowerCase().includes(query)) ||
                (s.status && s.status.toLowerCase().includes(query)) ||
                (s.stream_id && s.stream_id.toLowerCase().includes(query));

            const matchServer = !serverFilter ||
                (s.server_name && s.server_name.toLowerCase() === serverFilter);

            let matchStatus = true;
            if (statusFilter === 'active') {
                matchStatus = s.status === 'active' || s.status === 'inca_active';
            } else if (statusFilter === 'down') {
                matchStatus = s.status === 'inca_down' || s.status === 'disconnected' || s.status === 'down';
            }

            return matchQuery && matchServer && matchStatus;
        });

        renderTable(filtered);
    }

    function resetFilters() {
        document.getElementById("streamSearchInput").value = "";
        document.getElementById("serverFilterSelect").value = "";
        document.getElementById("statusFilterSelect").value = "";
        renderTable(allStreamsData);
    }

    async function reloadDevice(key, platform) {
        updateDeviceUI(key, platform, 'pulling');
        try {
            const api = platform === 'bitstreams' ? 'api/list_bitstreams.php' : 'api/list_inca.php';
            const r = await fetch(`${api}?key=${key}`);
            const data = await r.json();

            allStreamsData = allStreamsData.filter(s => !(s.server_key === key && s.type === platform));
            allStreamsData.push(...data);

            updateDeviceUI(key, platform, 'completed', data.length);
            filterStreams();
        } catch (e) {
            updateDeviceUI(key, platform, 'error');
            console.error(e);
        }
    }

    async function loadStreams() {
        allStreamsData = [];

        document.getElementById('deviceStatusList').innerHTML = "";
        Object.keys(DEVICE_CONFIG.bitstreams).forEach(key => updateDeviceUI(key, 'bitstreams', 'pending'));
        Object.keys(DEVICE_CONFIG.inca).forEach(key => updateDeviceUI(key, 'inca', 'pending'));

        const pullTasks = [];

        Object.keys(DEVICE_CONFIG.bitstreams).forEach(key => {
            pullTasks.push((async () => {
                updateDeviceUI(key, 'bitstreams', 'pulling');
                try {
                    const r = await fetch(`api/list_bitstreams.php?key=${key}`);
                    const data = await r.json();
                    allStreamsData.push(...data);
                    updateDeviceUI(key, 'bitstreams', 'completed', data.length);
                } catch (e) {
                    updateDeviceUI(key, 'bitstreams', 'error');
                }
            })());
        });

        Object.keys(DEVICE_CONFIG.inca).forEach(key => {
            pullTasks.push((async () => {
                updateDeviceUI(key, 'inca', 'pulling');
                try {
                    const r = await fetch(`api/list_inca.php?key=${key}`);
                    const data = await r.json();
                    allStreamsData.push(...data);
                    updateDeviceUI(key, 'inca', 'completed', data.length);
                } catch (e) {
                    updateDeviceUI(key, 'inca', 'error');
                }
            })());
        });

        try {
            await Promise.all(pullTasks);
            filterStreams();
        } catch (e) {
            const tbody = document.getElementById("streamsTableBody");
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Error loading streams: ${e}</td></tr>`;
        }
    }

    async function viewDetails(serverKey, streamId, streamName) {
        document.getElementById('detailsModalTitle').textContent = `Stream Details: ${streamName}`;
        document.getElementById('sourceInfoPre').textContent = 'Loading...';
        document.getElementById('notificationsTableBody').innerHTML = '<tr><td colspan="4" class="text-center">Loading...</td></tr>';
        document.getElementById('sourceReportsPre').textContent = 'Loading...';
        document.getElementById('noPlaybackMsg').classList.add('d-none');

        detailsModal.show();

        try {
            const response = await fetch(`api/get_stream_details.php?server_key=${serverKey}&stream_id=${streamId}&page=1&limit=50`);
            const data = await response.json();

            const streamData = data.stream;
            const sourceInfo = data.source_info;
            const sourceReports = data.source_reports;
            const notificationsList = (data.notifications && data.notifications.data && data.notifications.data.list) ? data.notifications.data.list : [];

            document.getElementById('sourceInfoPre').textContent = JSON.stringify(sourceInfo, null, 2);
            document.getElementById('sourceReportsPre').textContent = JSON.stringify(sourceReports, null, 2);

            const nBody = document.getElementById('notificationsTableBody');
            nBody.innerHTML = "";

            if (notificationsList.length === 0) {
                nBody.innerHTML = '<tr><td colspan="4" class="text-center">No notifications found.</td></tr>';
            } else {
                notificationsList.forEach(n => {
                    const tr = document.createElement("tr");

                    const typeBadge = n.type === 'error' ? '<span class="badge bg-danger">Error</span>' :
                                    (n.type === 'warning' ? '<span class="badge bg-warning text-dark">Warning</span>' :
                                    `<span class="badge bg-info">${n.type}</span>`);

                    tr.innerHTML = `
                        <td class="small text-nowrap">${n.created_at}</td>
                        <td>${typeBadge}</td>
                        <td class="fw-bold">${n.title}</td>
                        <td class="small">${n.message}</td>
                    `;
                    nBody.appendChild(tr);
                });
            }

            let urls = { hls: null, dash: null };
            const sData = (streamData && streamData.data) ? streamData.data : null;

            if (sData && sData.playbacks) {
                const httpPlayback = sData.playbacks.find(p => p.output_type === 'http' || p.output_type === 'hls');
                if (httpPlayback) {
                    if (httpPlayback.hls_url) urls.hls = httpPlayback.hls_url;
                    if (httpPlayback.dash_url) urls.dash = httpPlayback.dash_url;

                    if (httpPlayback.output_urls) {
                        httpPlayback.output_urls.forEach(uo => {
                            if (!uo.urls) return;
                            uo.urls.forEach(url => {
                                if (url.includes('.m3u8')) urls.hls = url;
                                if (url.includes('.mpd')) urls.dash = url;
                            });
                        });
                    }
                }
            }

            if (urls.hls || urls.dash) {
                initPlayer(urls);
            } else {
                document.getElementById('noPlaybackMsg').classList.remove('d-none');
            }

        } catch (e) {
            document.getElementById('sourceInfoPre').textContent = 'Error loading details: ' + e;
            document.getElementById('notificationsTableBody').innerHTML = `<tr><td colspan="4" class="text-center text-danger">Error: ${e}</td></tr>`;
            document.getElementById('sourceReportsPre').textContent = 'Error loading details: ' + e;
        }
    }

    function initPlayer(urls) {
        const video = document.getElementById('videoPlayer');
        video.innerHTML = "";

        if (urls.dash) {
            const source = document.createElement('source');
            source.src = urls.dash;
            source.type = "application/dash+xml";
            video.appendChild(source);
        }

        if (urls.hls) {
            const source = document.createElement('source');
            source.src = urls.hls;
            source.type = "application/x-mpegURL";
            video.appendChild(source);
        }

        video.load();
    }

    function viewIncaDetails(idx) {
        const stream = allStreamsData[idx];
        const serverKey = stream.server_key;
        document.getElementById('incaDetailsModalTitle').textContent = `INCA Stream: ${stream.name}`;

        const tabsUl = document.getElementById('incaTabs');
        const tabContent = document.getElementById('incaTabContent');

        tabsUl.querySelectorAll('.dynamic-tab').forEach(el => el.remove());
        tabContent.querySelectorAll('.dynamic-tab-pane').forEach(el => el.remove());

        const sourceTabLi = document.getElementById('incaSourceTabLi');
        sourceTabLi.innerHTML = "";
        if (stream.enriched && stream.enriched.source) {
            const s = stream.enriched.source;
            sourceTabLi.innerHTML = `<button class="nav-link dynamic-tab" data-bs-toggle="tab" data-bs-target="#incaTabSource" onclick="loadIncaHtml('${serverKey}', '${s.stream_id}', 'incaSourceContent')">Source (${s.dn})</button>`;
        }

        if (stream.enriched && stream.enriched.outputs_detailed) {
            stream.enriched.outputs_detailed.forEach((od, i) => {
                const tabId = `incaTabOut${i}`;
                const contentId = `incaOutContent${i}`;

                const li = document.createElement('li');
                li.className = 'nav-item dynamic-tab';
                li.innerHTML = `<button class="nav-link" data-bs-toggle="tab" data-bs-target="#${tabId}" onclick="loadIncaHtml('${serverKey}', '${od.prog_id}', '${contentId}')">Output #${i+1}</button>`;
                tabsUl.appendChild(li);

                const pane = document.createElement('div');
                pane.id = tabId;
                pane.className = 'tab-pane fade dynamic-tab-pane';
                pane.innerHTML = `<div id="${contentId}" class="inca-html-content"></div>`;
                tabContent.appendChild(pane);
            });
        }

        const enrichedDiv = document.getElementById('incaEnrichedInfo');
        enrichedDiv.innerHTML = "";
        if (stream.enriched) {
            const e = stream.enriched;
            let html = '<div class="card bg-light border-0 shadow-sm"><div class="card-body">';
            if (e.source) {
                html += `<h6><strong>Input Source:</strong> ${e.source.dn}</h6>`;
                html += `<div class="small text-muted ms-3 mb-2">UDP://${e.source.address}:${e.source.port}</div>`;
            }
            if (e.filter) {
                html += `<h6><strong>PID Filter:</strong> <span class="small font-monospace text-break">${e.filter}</span></h6>`;
            }
            if (e.outputs_detailed && e.outputs_detailed.length > 0) {
                html += `<h6 class="mt-2"><strong>Destinations & Profiles:</strong></h6><div class="list-group list-group-flush border rounded">`;
                e.outputs_detailed.forEach(od => {
                    let profileHtml = od.profile ?
                        `<div class="small text-primary">Profile: ${od.profile.name} (${od.profile.resolution}, ${od.profile.codec}, ${(parseInt(od.profile.bitrate)/1000).toFixed(0)}k)</div>` :
                        '<div class="small text-muted">No transcode profile</div>';
                    html += `
                        <div class="list-group-item p-2">
                            <div class="fw-bold small">UDP://${od.dest}</div>
                            ${profileHtml}
                        </div>`;
                });
                html += '</div>';
            }
            html += '</div></div>';
            enrichedDiv.innerHTML = html;
        }

        const tbody = document.getElementById('incaInstancesBody');
        tbody.innerHTML = "";

        stream.instances.forEach(inst => {
            const tr = document.createElement("tr");
            const bitrate = inst.bitrate ? (parseInt(inst.bitrate) / 1000000).toFixed(2) + " Mbps" : "0.00 Mbps";
            tr.innerHTML = `
                <td>${inst.stream_id}</td>
                <td>${bitrate}</td>
                <td>${inst.errors}</td>
            `;
            tbody.appendChild(tr);
        });

        incaDetailsModal.show();
    }

    async function loadIncaHtml(serverKey, progId, containerId) {
        const div = document.getElementById(containerId);
        div.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Fetching live data...</div></div>';

        try {
            const r = await fetch(`api/get_inca_html.php?server_key=${serverKey}&prog_id=${progId}`);
            const html = await r.text();
            div.innerHTML = html;
        } catch(e) {
            div.innerHTML = `<div class="alert alert-danger mt-3">Error loading details: ${e}</div>`;
        }
    }

    async function streamAction(serverKey, streamId, actionCmd, type = 'bitstreams') {
        if (!confirm(`Are you sure you want to ${actionCmd} this ${type} stream?`)) return;

        const form = new FormData();
        form.append("action", "stream_action");
        form.append("stream_action", actionCmd);
        form.append("server_key", serverKey);
        form.append("stream_id", streamId);
        form.append("type", type);

        try {
            const response = await fetch("api/stream_action.php", { method: "POST", body: form });
            const result = await response.json();

            let bsResponse = {};
            try {
                bsResponse = JSON.parse(result.response);
            } catch(e) {}

            if (result.code >= 200 && result.code < 300 && (bsResponse.err_code === 0 || bsResponse.err_code === undefined)) {
                alert(`Action ${actionCmd} successful.`);
                reloadDevice(serverKey, type);
            } else {
                alert("Error: " + (bsResponse.err_message || result.response || "Action failed"));
            }
        } catch (e) {
            alert("Request failed: " + e);
        }
    }

    window.onload = loadStreams;
</script>
</body>
</html>
