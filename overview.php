<?php
require_once 'config.php';
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Stream Overview - Bitstreams Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Bitstreams Tool</a>
        <div class="navbar-nav">
            <a class="nav-link" href="index.php">Migration</a>
            <a class="nav-link active" href="overview.php">Overview</a>
        </div>
    </div>
</nav>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Stream Overview</h2>
        <button class="btn btn-outline-primary" onclick="loadStreams()">Refresh Status</button>
    </div>

    <div class="card p-3">
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incaDetailsModalTitle">INCA Stream Instances</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
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

    // Reset player when modal is closed
    document.getElementById('detailsModal').addEventListener('hidden.bs.modal', () => {
        const video = document.getElementById('videoPlayer');
        video.pause();
        video.innerHTML = "";
        video.load();
    });

    async function loadStreams() {
        if (dataTable) {
            dataTable.destroy();
        }

        const tbody = document.getElementById("streamsTableBody");
        tbody.innerHTML = '<tr><td colspan="4" class="text-center p-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';

        try {
            const response = await fetch("api/list_all_streams.php");
            const streams = await response.json();
            allStreamsData = streams;

            tbody.innerHTML = "";

            if (streams.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No streams found on configured servers.</td></tr>';
            } else {
                streams.forEach((stream, idx) => {
                    const tr = document.createElement("tr");

                    let statusBadge = "";
                    let actions = "";
                    let nameHtml = "";

                    if (stream.type === 'inca') {
                        statusBadge = '<span class="badge bg-info">INCA</span>';
                        actions = `<button class="btn btn-sm btn-info text-white" onclick="viewIncaDetails(${idx})">Instances (${stream.instances.length})</button>`;

                        // Construct INCA URL with embedded auth
                        const incaUrl = `http://${stream.server_user}:${stream.server_pass}@${stream.server_address}/controlpanel?deviceid=1`;
                        nameHtml = `<a href="${incaUrl}" target="_blank" class="text-decoration-none">${stream.name}</a>`;
                    } else {
                        statusBadge = stream.status === 'active'
                            ? '<span class="badge bg-success">Active</span>'
                            : (stream.status === 'disconnected' ? '<span class="badge bg-danger">Disconnected</span>' : `<span class="badge bg-secondary">${stream.status}</span>`);

                        actions = `
                            <button class="btn btn-sm btn-info text-white" onclick="viewDetails('${stream.server_key}', '${stream.stream_id}', '${stream.name.replace(/'/g, "\\'")}')">Details</button>
                            <button class="btn btn-sm btn-primary" onclick="streamAction('${stream.server_key}', '${stream.stream_id}', 'start')" ${stream.status === 'active' ? 'disabled' : ''}>Start</button>
                            <button class="btn btn-sm btn-danger" onclick="streamAction('${stream.server_key}', '${stream.stream_id}', 'stop')" ${stream.status !== 'active' ? 'disabled' : ''}>Stop</button>
                            <button class="btn btn-sm btn-warning" onclick="streamAction('${stream.server_key}', '${stream.stream_id}', 'restart')">Restart</button>
                        `;

                        const streamUrl = `${stream.server_protocol}://${stream.server_address}/encoding/live/${stream.stream_id}`;
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

        } catch (e) {
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

            // Render Notifications Table
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

            // Find Playback URLs
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
        video.innerHTML = ""; // Clear sources

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
        document.getElementById('incaDetailsModalTitle').textContent = `INCA Stream: ${stream.name}`;

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

    async function streamAction(serverKey, streamId, action) {
        if (!confirm(`Are you sure you want to ${action} this stream?`)) return;

        const form = new FormData();
        form.append("server_key", serverKey);
        form.append("stream_id", streamId);
        form.append("action", action);

        try {
            const response = await fetch("api/stream_action.php", { method: "POST", body: form });
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

    window.onload = loadStreams;
</script>
</body>
</html>
