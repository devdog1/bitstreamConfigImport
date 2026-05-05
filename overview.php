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

<script>
    async function loadStreams() {
        const tbody = document.getElementById("streamsTableBody");
        // Keep some space if we already have content or show loading
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
