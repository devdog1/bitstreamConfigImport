<?php
/**
 * Bitstreams Plugin Video URL Manager View
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/../models/bitstreams-model.php';

$canView = true;
$canEdit = true;

if (function_exists('has_permission')) {
    $canView = has_permission('bitstreams_view') || has_permission('videoLinks.view') || has_permission('videoLinks_view');
    $canEdit = has_permission('bitstreams_edit') || has_permission('videoLinks.edit') || has_permission('videoLinks_edit');
}

if (!$canView) {
    http_response_code(403);
    die("Unauthorized: You do not have permission to view Video Links.");
}

$pageRoute = function_exists('url_for') ? url_for('bitstreams_video_links') : 'index.php?route=bitstreams_video_links';

/*
|--------------------------------------------------------------------------
| HANDLE CSV EXPORT
|--------------------------------------------------------------------------
*/
if (isset($_GET['export'])) {
    if (!$canEdit) {
        http_response_code(403);
        die("Unauthorized");
    }
    bitstreams_export_video_links_csv();
}

/*
|--------------------------------------------------------------------------
| HANDLE DELETE
|--------------------------------------------------------------------------
*/
$action = $_GET['action'] ?? "";
if ($action === "delete" && isset($_GET['id'])) {
    if (!$canEdit) {
        http_response_code(403);
        die("Unauthorized");
    }
    bitstreams_delete_video_link($_GET['id']);
    header("Location: " . $pageRoute);
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLE SAVE (ADD / EDIT)
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['save'])) {
    if (!$canEdit) {
        http_response_code(403);
        die("Unauthorized");
    }

    if (function_exists('validate_csrf')) {
        validate_csrf();
    }

    bitstreams_save_video_link([
        'id'       => $_POST['id'] ?? null,
        'category' => $_POST['category'] ?? '',
        'device'   => $_POST['device'] ?? '',
        'purpose'  => $_POST['purpose'] ?? '',
        'url'      => $_POST['url'] ?? ''
    ]);

    header("Location: " . $pageRoute);
    exit;
}

/*
|--------------------------------------------------------------------------
| HANDLE IMPORT CSV
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['import'])) {
    if (!$canEdit) {
        http_response_code(403);
        die("Unauthorized");
    }

    if (function_exists('validate_csrf')) {
        validate_csrf();
    }

    if (!empty($_FILES['csv_file']['tmp_name'])) {
        bitstreams_import_video_links_csv($_FILES['csv_file']['tmp_name']);
    }

    header("Location: " . $pageRoute);
    exit;
}

/*
|--------------------------------------------------------------------------
| LOAD DATA
|--------------------------------------------------------------------------
*/
$data = bitstreams_get_video_links();

$grouped = [];
foreach ($data as $d) {
    $cat = $d['category'] ?: 'Uncategorized';
    $grouped[$cat][] = $d;
}
?>

<style>
html { scroll-behavior: smooth; }

#sidebarNav {
    position: sticky;
    top: 10px;
    max-height: 80vh;
    overflow-y: auto;
}

.category-link {
    display: block;
    padding: 8px 12px;
    margin-bottom: 6px;
    background: #f8f9fa;
    border-radius: 6px;
    text-decoration: none;
    color: #333;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
}

.category-link:hover {
    background: #e9ecef;
    color: #0d6efd;
}
</style>

<div class="container-fluid p-4">

    <!-- HEADER & CONTROLS -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fa-solid fa-link text-primary me-2"></i>Video URL Dashboard</h2>
            <p class="text-muted mb-0">Organize and query video streams, monitoring links, and device playback URLs.</p>
        </div>
        <div>
            <?php if ($canEdit): ?>
                <button class="btn btn-outline-success btn-sm me-2 fw-bold" data-bs-toggle="modal" data-bs-target="#importModal">
                    <i class="fa-solid fa-file-csv me-1"></i> Import CSV
                </button>
                <a href="<?= $pageRoute ?>&export=1" class="btn btn-success btn-sm fw-bold">
                    <i class="fa-solid fa-file-export me-1"></i> Export CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- CONTROLS CARD -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <button class="btn btn-sm btn-primary me-1 fw-bold" onclick="expandAll()">
                    <i class="fa-solid fa-angles-down me-1"></i> Expand All
                </button>
                <button class="btn btn-sm btn-secondary fw-bold" onclick="collapseAll()">
                    <i class="fa-solid fa-angles-up me-1"></i> Collapse All
                </button>
            </div>
            <div class="text-muted small fw-bold">
                Categories: <span class="text-primary"><?= count($grouped) ?></span> | Total URLs: <span class="text-info"><?= count($data) ?></span>
            </div>
        </div>
    </div>

    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-md-3 mb-4">
            <div id="sidebarNav" class="card shadow-sm border-0 p-3">
                <strong class="mb-3 text-secondary border-bottom pb-2"><i class="fa-solid fa-folder me-2"></i>Categories</strong>

                <?php if (empty($grouped)): ?>
                    <p class="text-muted small">No categories created yet.</p>
                <?php else: ?>
                    <?php
                    $i = 0;
                    foreach($grouped as $cat => $items):
                        $target = "cat_" . $i++;
                    ?>
                        <a class="category-link" href="#<?= $target ?>">
                            <i class="fa-regular fa-folder-open me-2 text-primary"></i>
                            <?= htmlspecialchars($cat) ?> <span class="badge bg-secondary rounded-pill float-end"><?= count($items) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-9">

            <!-- ADD FORM -->
            <?php if ($canEdit): ?>
                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fa-solid fa-plus me-2 text-primary"></i>Add New Video URL</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="<?= $pageRoute ?>" class="row g-2">
                            <?php if (function_exists('csrf_field')) csrf_field(); ?>
                            <input type="hidden" name="save" value="1">

                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Category</label>
                                <input class="form-control" name="category" placeholder="e.g. Master Control" required>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label small fw-bold mb-1">Device</label>
                                <input class="form-control" name="device" placeholder="e.g. INCA 1" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">Purpose</label>
                                <input class="form-control" name="purpose" placeholder="e.g. Primary Preview" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-1">URL</label>
                                <input class="form-control" name="url" placeholder="http://... or udp://..." required>
                            </div>

                            <div class="col-md-1 d-flex align-items-end">
                                <button class="btn btn-primary w-100 fw-bold">Add</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ACCORDION LIST -->
            <div class="accordion shadow-sm rounded overflow-hidden" id="videoAccordion">

                <?php if (empty($grouped)): ?>
                    <div class="card p-4 text-center text-muted">
                        <i class="fa-solid fa-link-slash fs-1 mb-2 text-secondary"></i>
                        <p class="mb-0">No video URLs added yet. Use the form above to add a new video URL or import a CSV file.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $i = 0;
                    foreach($grouped as $category => $items):
                        $collapseId = "cat_" . $i++;
                    ?>

                    <div class="accordion-item border-0 mb-2 shadow-sm rounded overflow-hidden" id="<?= $collapseId ?>">

                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed fw-bold py-3" type="button" data-bs-toggle="collapse" data-bs-target="#c<?= $collapseId ?>">
                                <i class="fa-solid fa-folder text-primary me-2"></i>
                                <?= htmlspecialchars($category) ?>
                                <span class="badge bg-primary rounded-pill ms-2"><?= count($items) ?></span>
                            </button>
                        </h2>

                        <div id="c<?= $collapseId ?>" class="accordion-collapse collapse">
                            <div class="accordion-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Device</th>
                                                <th>Purpose</th>
                                                <th>URL</th>
                                                <?php if ($canEdit): ?><th class="text-end" style="width: 120px;">Actions</th><?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($items as $d): ?>
                                                <tr>
                                                    <td class="fw-bold"><?= htmlspecialchars($d["device"]) ?></td>
                                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($d["purpose"]) ?></span></td>
                                                    <td>
                                                        <a href="<?= htmlspecialchars($d["url"]) ?>" target="_blank" class="text-decoration-none font-monospace small">
                                                            <?= htmlspecialchars($d["url"]) ?> <i class="fa-solid fa-up-right-from-square small ms-1"></i>
                                                        </a>
                                                    </td>

                                                    <?php if ($canEdit): ?>
                                                        <td class="text-end">
                                                            <a class="btn btn-sm btn-outline-danger"
                                                               href="<?= $pageRoute ?>&action=delete&id=<?= $d["id"] ?>"
                                                               onclick="return confirm('Are you sure you want to delete this link?')">
                                                               <i class="fa-solid fa-trash"></i> Delete
                                                            </a>
                                                        </td>
                                                    <?php endif; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>

                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

        </div>
    </div>

</div>

<!-- CSV IMPORT MODAL -->
<?php if ($canEdit): ?>
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-file-csv me-2 text-success"></i>Import Video URLs from CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?= $pageRoute ?>" enctype="multipart/form-data">
                <?php if (function_exists('csrf_field')) csrf_field(); ?>
                <input type="hidden" name="import" value="1">
                <div class="modal-body">
                    <p class="text-muted small">Select a CSV file containing columns for <code>id</code>, <code>category</code>, <code>device</code>, <code>purpose</code>, and <code>url</code>.</p>
                    <div class="mb-3">
                        <label for="csv_file" class="form-label fw-bold">Upload CSV File</label>
                        <input type="file" class="form-control" name="csv_file" id="csv_file" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Import CSV</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function expandAll(){
    document.querySelectorAll('.accordion-collapse').forEach(el => {
        const c = bootstrap.Collapse.getOrCreateInstance(el, {toggle: false});
        c.show();
    });
}

function collapseAll(){
    document.querySelectorAll('.accordion-collapse.show').forEach(el => {
        const c = bootstrap.Collapse.getOrCreateInstance(el, {toggle: false});
        c.hide();
    });
}
</script>
