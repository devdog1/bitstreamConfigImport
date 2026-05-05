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

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Server</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="streamsTableBody">
                    <tr>
                        <td colspan="4" class="text-center p-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
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
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSourceInfo">Source Info</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabEvents">Events</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tabSourceInfo">
                        <pre id="sourceInfoPre" class="bg-dark text-white p-3 rounded" style="max-height: 500px; overflow: auto;"></pre>
                    </div>
                    <div class="tab-pane fade" id="tabEvents">
                        <pre id="eventsPre" class="bg-dark text-white p-3 rounded" style="max-height: 500px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

    async function loadStreams() {
        const tbody = document.getElementById("streamsTableBody");
        if (tbody.innerHTML.trim() === "") {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center p-5"><div class="spinner-border text-primary" role="status"></div></td></tr>';
        }

        try {
            const response = await fetch("api/list_all_streams.php");
            const streams = await response.json();

            tbody.innerHTML = "";

            if (streams.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">No streams found on configured servers.</td></tr>';
                return;
            }

            streams.forEach(stream => {
                const tr = document.createElement("tr");

                const statusBadge = stream.status === 'active'
                    ? '<span class="badge bg-success">Active</span>'
                    : (stream.status === 'disconnected' ? '<span class="badge bg-danger">Disconnected</span>' : `<span class="badge bg-secondary">${stream.status}</span>`);

                const actions = `
                    <button class="btn btn-sm btn-info text-white" onclick="viewDetails('${stream.server_key}', '${stream.stream_id}', '${stream.name.replace(/'/g, "\\'")}')">Details</button>
                    <button class="btn btn-sm btn-primary" onclick="streamAction('${stream.server_key}', '${stream.stream_id}', 'start')" ${stream.status === 'active' ? 'disabled' : ''}>Start</button>
                    <button class="btn btn-sm btn-danger" onclick="streamAction('${stream.server_key}', '${stream.stream_id}', 'stop')" ${stream.status !== 'active' ? 'disabled' : ''}>Stop</button>
                    <button class="btn btn-sm btn-warning" onclick="streamAction('${stream.server_key}', '${stream.stream_id}', 'restart')">Restart</button>
                `;

                tr.innerHTML = `
                    <td class="align-middle fw-bold">${stream.name}</td>
                    <td class="align-middle">${statusBadge}</td>
                    <td class="align-middle">${stream.server_name}</td>
                    <td class="align-middle">${actions}</td>
                `;
                tbody.appendChild(tr);
            });
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="4" class="text-center text-danger">Error loading streams: ${e}</td></tr>`;
        }
    }

    async function viewDetails(serverKey, streamId, streamName) {
        document.getElementById('detailsModalTitle').textContent = `Stream Details: ${streamName}`;
        document.getElementById('sourceInfoPre').textContent = 'Loading...';
        document.getElementById('eventsPre').textContent = 'Loading...';
        detailsModal.show();

        try {
            const response = await fetch(`api/get_stream_details.php?server_key=${serverKey}&stream_id=${streamId}`);
            const data = await response.json();

            document.getElementById('sourceInfoPre').textContent = JSON.stringify(data.source_info, null, 2);
            document.getElementById('eventsPre').textContent = JSON.stringify(data.events, null, 2);
        } catch (e) {
            document.getElementById('sourceInfoPre').textContent = 'Error loading details: ' + e;
            document.getElementById('eventsPre').textContent = 'Error loading details: ' + e;
        }
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
