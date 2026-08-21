<?php
/**
 * Bitstreams Plugin Stream Overview View
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/../models/bitstreams-model.php';

$servers = bitstreams_get_servers();
$incaHosts = bitstreams_get_inca_hosts();

$apiUrl = function_exists('url_for') ? url_for('bitstreams_api') : 'index.php?route=bitstreams_api';

$deviceConfig = [
    'bitstreams' => array_map(fn($s) => ['name' => $s['name']], $servers),
    'inca' => array_map(fn($h) => ['name' => $h['name']], $incaHosts)
];
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-chart-line text-primary me-2"></i>Stream Overview</h2>
        <button class="btn btn-outline-primary" onclick="loadStreams()"><i class="fa-solid fa-rotate me-1"></i> Refresh Status</button>
    </div>

    <!-- Device Status Dashboard -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-network-wired me-2 text-secondary"></i>Device Pull Status</h6>
        </div>
        <div class="card-body">
            <div id="deviceStatusList" class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
                <!-- Devices injected via JS -->
            </div>
        </div>
    </div>

    <div class="card p-3 shadow-sm border-0">
        <div class="table-responsive">
            <table id="streamsTable" class="table table-hover mb-0 align-middle">
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
                        <div id="incaSourceContent" class="inca-html-content p-3 bg-white border rounded"></div>
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

<script>
    const API_BASE = "<?= $apiUrl ?>";
    const DEVICE_CONFIG = <?= json_encode($deviceConfig) ?>;

    let detailsModal = null;
    let incaDetailsModal = null;
    let dataTable = null;
    let allStreamsData = [];

    function buildApiUrl(action, extraParams = '') {
        const separator = API_BASE.includes('?') ? '&' : '?';
        return `${API_BASE}${separator}action=${action}${extraParams ? '&' + extraParams : ''}`;
    }

    document.addEventListener("DOMContentLoaded", () => {
        detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
        incaDetailsModal = new bootstrap.Modal(document.getElementById('incaDetailsModal'));

        document.getElementById('detailsModal').addEventListener('hidden.bs.modal', () => {
            const video = document.getElementById('videoPlayer');
            video.pause();
            video.innerHTML = "";
            video.load();
        });

        loadStreams();
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

    function renderTable() {
        if (dataTable && typeof $.fn.DataTable !== 'undefined' && $.fn.DataTable.isDataTable('#streamsTable')) {
            $('#streamsTable').DataTable().destroy();
        }

        const tbody = document.getElementById("streamsTableBody");
        tbody.innerHTML = "";

        if (allStreamsData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">No streams found.</td></tr>';
        } else {
            allStreamsData.forEach((stream, idx) => {
                const tr = document.createElement("tr");

                let statusBadge = "";
                let actions = "";
                let nameHtml = "";

                if (stream.type === 'inca') {
                    statusBadge = stream.status === 'inca_down'
                        ? '<span class="badge bg-danger">Down</span>'
                        : '<span class="badge bg-info">Active</span>';

                    const incaUuid = (stream.enriched && stream.enriched.uuid) ? stream.enriched.uuid : stream.stream_id;

                    actions = `
                        <button class="btn btn-sm btn-info text-white me-1" onclick="viewIncaDetails(${idx})">Details</button>
                        <button class="btn btn-sm btn-primary me-1" onclick="streamAction('${stream.server_key}', '${incaUuid}', 'start', 'inca')">Start</button>
                        <button class="btn btn-sm btn-danger me-1" onclick="streamAction('${stream.server_key}', '${incaUuid}', 'stop', 'inca')">Stop</button>
                        <button class="btn btn-sm btn-warning" onclick="streamAction('${stream.server_key}', '${incaUuid}', 'restart', 'inca')">Restart</button>
                    `;

                    const incaUrl = `http://${stream.server_user}:${stream.server_pass}@${stream.server_address}/controlpanel?deviceid=1`;
                    nameHtml = `<a href="${incaUrl}" target="_blank" class="text-decoration-none fw-bold">${stream.name}</a>`;
                } else {
                    statusBadge = stream.status === 'active'
                        ? '<span class="badge bg-success">Active</span>'
                        : (stream.status === 'disconnected' ? '<span class="badge bg-danger">Disconnected</span>' : `<span class="badge bg-secondary">${stream.status}</span>`);

                    const sid = stream.stream_id || stream.id || '';

                    actions = `
                        <button class="btn btn-sm btn-info text-white me-1" onclick="viewDetails('${stream.server_key}', '${sid}', '${stream.name.replace(/'/g, "\\'")}')">Details</button>
                        <button class="btn btn-sm btn-primary me-1" onclick="streamAction('${stream.server_key}', '${sid}', 'start')" ${stream.status === 'active' ? 'disabled' : ''}>Start</button>
                        <button class="btn btn-sm btn-danger me-1" onclick="streamAction('${stream.server_key}', '${sid}', 'stop')" ${stream.status !== 'active' ? 'disabled' : ''}>Stop</button>
                        <button class="btn btn-sm btn-warning" onclick="streamAction('${stream.server_key}', '${sid}', 'restart')">Restart</button>
                    `;

                    const streamUrl = `${stream.server_protocol}://${stream.server_address}/encoding/live/${sid}`;
                    nameHtml = `<a href="${streamUrl}" target="_blank" class="text-decoration-none fw-bold">${stream.name}</a>`;
                }

                const bitrate = stream.bitrate ? (parseInt(stream.bitrate) / 1000000).toFixed(2) + " Mbps" : "-";
                const errors = stream.errors !== undefined ? stream.errors : "-";

                tr.innerHTML = `
                    <td class="align-middle">${nameHtml}</td>
                    <td class="align-middle">${statusBadge}</td>
                    <td class="align-middle">${stream.server_name}</td>
                    <td class="align-middle">${bitrate}</td>
                    <td class="align-middle">${errors}</td>
                    <td class="align-middle">${actions}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        if (typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
            dataTable = $('#streamsTable').DataTable({
                "pageLength": 25,
                "order": [[0, "asc"]]
            });
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
                    const r = await fetch(buildApiUrl('list_bitstreams', `key=${key}`));
                    const data = await r.json();
                    if (Array.isArray(data)) {
                        allStreamsData.push(...data);
                        updateDeviceUI(key, 'bitstreams', 'completed', data.length);
                    } else {
                        updateDeviceUI(key, 'bitstreams', 'error');
                    }
                } catch (e) {
                    updateDeviceUI(key, 'bitstreams', 'error');
                }
            })());
        });

        Object.keys(DEVICE_CONFIG.inca).forEach(key => {
            pullTasks.push((async () => {
                updateDeviceUI(key, 'inca', 'pulling');
                try {
                    const r = await fetch(buildApiUrl('list_inca', `key=${key}`));
                    const data = await r.json();
                    if (Array.isArray(data)) {
                        allStreamsData.push(...data);
                        updateDeviceUI(key, 'inca', 'completed', data.length);
                    } else {
                        updateDeviceUI(key, 'inca', 'error');
                    }
                } catch (e) {
                    updateDeviceUI(key, 'inca', 'error');
                }
            })());
        });

        try {
            await Promise.all(pullTasks);
            renderTable();
        } catch (e) {
            console.error(e);
        }
    }

    async function streamAction(serverKey, streamId, action, type = 'bitstreams') {
        if (!confirm(`Are you sure you want to ${action} this ${type} stream?`)) return;

        const form = new FormData();
        form.append("action", "stream_action");
        form.append("server_key", serverKey);
        form.append("stream_id", streamId);
        form.append("action", action);
        form.append("type", type);

        try {
            const response = await fetch(API_BASE, { method: "POST", body: form });
            const result = await response.json();

            let bsResponse = {};
            try {
                bsResponse = JSON.parse(result.response);
            } catch(e) {}

            if (result.code >= 200 && result.code < 300 && (bsResponse.err_code === 0 || bsResponse.err_code === undefined)) {
                alert(`Action ${action} successful.`);
                loadStreams();
            } else {
                alert("Error: " + (bsResponse.err_message || result.response || "Action failed"));
            }
        } catch (e) {
            alert("Request failed: " + e);
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
            const response = await fetch(buildApiUrl('get_stream_details', `server_key=${serverKey}&stream_id=${streamId}&page=1&limit=50`));
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
                pane.innerHTML = `<div id="${contentId}" class="inca-html-content p-3 bg-white border rounded"></div>`;
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

        if (stream.instances && stream.instances.length > 0) {
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
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No SNMP instances active.</td></tr>';
        }

        incaDetailsModal.show();
    }

    async function loadIncaHtml(serverKey, progId, containerId) {
        const div = document.getElementById(containerId);
        div.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Fetching live data...</div></div>';

        try {
            const r = await fetch(buildApiUrl('get_inca_html', `server_key=${serverKey}&prog_id=${progId}`));
            const html = await r.text();
            div.innerHTML = html;
        } catch(e) {
            div.innerHTML = `<div class="alert alert-danger mt-3">Error loading details: ${e}</div>`;
        }
    }
</script>
