<?php
require_once 'functions.php';

/*
|--------------------------------------------------------------------------
| Bitstreams Template API
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["api_templates"])) {
    header("Content-Type: application/json");
    $server = trim($_POST["server"]);
    $result = apiCall("http://{$server}/api/v3/templates");
    echo $result['response'];
    exit;
}

/*
|--------------------------------------------------------------------------
| Bitstreams Stream Create API
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["api_push"])) {
    header("Content-Type: application/json");
    $server = trim($_POST["server"]);
    $payload = $_POST["payload"];
    $result = apiCall("http://{$server}/api/v3/streams", 'POST', $payload);
    echo json_encode($result);
    exit;
}

/*
|--------------------------------------------------------------------------
| Generate sessions
|--------------------------------------------------------------------------
*/
$sessions = [];
$backup_content = $_POST["backup"] ?? "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate"])) {
    if (isset($_FILES["backup_file"]) && $_FILES["backup_file"]["error"] == UPLOAD_ERR_OK) {
        $backup_content = file_get_contents($_FILES["backup_file"]["tmp_name"]);
    }

    if (!empty($backup_content)) {
        $channels = parseChannels($backup_content);
        foreach ($channels as $channel) {
            if (!$channel["a"] || !$channel["b"]) {
                continue;
            }
            $sessions[] = [
                "name" => $channel["name"],
                "json" => generateSession($channel)
            ];
        }
    }
}
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

<div class="container-fluid p-4">
    <div class="card">
        <div class="card-body">
            <h2>INCA Migration Tool</h2>
            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="backup" class="form-label">INCA Backup XML Content</label>
                    <textarea name="backup" id="backup" rows="8" class="form-control"><?= htmlspecialchars($backup_content) ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="backup_file" class="form-label">Or upload Backup XML File</label>
                    <input type="file" name="backup_file" id="backup_file" class="form-control">
                </div>
                <button type="submit" name="generate" class="btn btn-primary">Generate Sessions</button>
            </form>
        </div>
    </div>

    <?php if (count($sessions)): ?>
        <ul class="nav nav-tabs mt-4" id="sessionTabs" role="tablist">
            <?php foreach ($sessions as $i => $session): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $i == 0 ? 'active' : '' ?>" id="tab-btn-<?= $i ?>" data-bs-toggle="tab" data-bs-target="#tab<?= $i ?>" type="button" role="tab"><?= htmlspecialchars($session["name"]) ?></button>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="tab-content mt-4" id="sessionTabsContent">
            <?php foreach ($sessions as $i => $session): ?>
                <div class="tab-pane fade <?= $i == 0 ? 'show active' : '' ?>" id="tab<?= $i ?>" role="tabpanel">
                    <button class="btn btn-success me-2" onclick="copyJSON('json<?= $i ?>')">Copy JSON</button>
                    <button class="btn btn-primary" onclick="openImport(<?= $i ?>)">Add to Bitstreams</button>
                    <pre id="json<?= $i ?>" class="mt-3"><?= htmlspecialchars($session["json"]) ?></pre>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
                    <label for="server" class="form-label">Bitstreams Server</label>
                    <input id="server" class="form-control" placeholder="e.g., 10.0.0.1:8080">
                </div>
                <div class="mb-3">
                    <label for="template" class="form-label">Select Template</label>
                    <select id="template" class="form-select"></select>
                </div>
                <div class="mb-3">
                    <label for="multicast" class="form-label">Multicast IP (232.x.x.x)</label>
                    <input id="multicast" class="form-control" placeholder="232.x.x.x">
                </div>
                <div class="mb-3">
                    <label for="region" class="form-label">Region</label>
                    <input id="region" class="form-control" value="<?= htmlspecialchars($CONFIG['default_region']) ?>">
                </div>
                <div class="mb-3">
                    <label for="local_addr" class="form-label">Local Interface Address</label>
                    <input id="local_addr" class="form-control" value="<?= htmlspecialchars($CONFIG['localaddr']) ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="submitImport()">Create Session</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentJSON = null;
    let importModal = null;

    function copyJSON(id) {
        navigator.clipboard.writeText(document.getElementById(id).innerText);
    }

    async function openImport(index) {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';

        currentJSON = JSON.parse(document.getElementById("json" + index).innerText);
        let server = prompt("Bitstreams server (host:port):", document.getElementById("server").value || "");

        if (!server) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }

        document.getElementById("server").value = server;

        let form = new FormData();
        form.append("api_templates", 1);
        form.append("server", server);

        try {
            let response = await fetch("", { method: "POST", body: form });
            let templates = await response.json();

            if (!Array.isArray(templates)) {
                throw new Error("Invalid response from server. Check credentials and server address.");
            }

            let select = document.getElementById("template");
            select.innerHTML = "";
            templates.forEach(t => {
                let option = document.createElement("option");
                option.value = t.id;
                option.innerHTML = t.name;
                select.appendChild(option);
            });

            let currentIP = currentJSON.playbacks[1].output_urls[0].urls[0].match(/udp:\/\/([^:]+)/)[1];
            document.getElementById("multicast").value = currentIP;

            if (!importModal) {
                importModal = new bootstrap.Modal(document.getElementById('importModal'));
            }
            importModal.show();
        } catch (e) {
            alert("Error: " + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    async function submitImport() {
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';

        let payload = structuredClone(currentJSON);
        let template = parseInt(document.getElementById("template").value);
        let multicast = document.getElementById("multicast").value;
        let region = document.getElementById("region").value;
        let server = document.getElementById("server").value;
        let local_addr = document.getElementById("local_addr").value;

        payload.regions = [region];
        payload.playbacks.forEach(p => {
            p.template_id = template;
            if (p.output_type == "multicast") {
                p.output_urls[0].region = region;
                p.output_urls[0].urls = [
                    `udp://${multicast}:3001?localaddr=${local_addr}`,
                    `udp://${multicast}:3002?localaddr=${local_addr}`,
                    `udp://${multicast}:3003?localaddr=${local_addr}`,
                    `udp://${multicast}:3004?localaddr=${local_addr}`,
                    `udp://${multicast}:3005?localaddr=${local_addr}`
                ];
            }
        });

        // Also update input urls localaddr
        payload.input_urls.forEach(iu => {
            iu.region = region;
            iu.urls = iu.urls.map(url => url.replace(/localaddr=[^&]+/, `localaddr=${local_addr}`));
        });

        let form = new FormData();
        form.append("api_push", 1);
        form.append("server", server);
        form.append("payload", JSON.stringify(payload));

        try {
            let response = await fetch("", { method: "POST", body: form });
            let result = await response.json();
            if (result.code >= 200 && result.code < 300) {
                alert("Session created successfully.");
                importModal.hide();
            } else {
                alert("Error: " + (result.response || "Unknown error"));
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
