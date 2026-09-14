<?php
/**
 * Bitstreams Plugin Telephony NXX / Local Calling Circles View
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/../models/bitstreams-model.php';

$canView = true;
if (function_exists('has_permission')) {
    $canView = has_permission('bitstreams_view') || has_permission('bitstream.view');
}

if (!$canView) {
    http_response_code(403);
    die("Unauthorized: You do not have permission to view Telephony NXX data.");
}

$dwPdo = bitstreams_get_dw_pdo();
$nxxData = [];
$dbConnected = false;
$dbError = null;

if ($dwPdo) {
    try {
        $sql = "
            SELECT
                nn.exchange,
                nn.regionCommunity,
                CONCAT(nn.npa, '-', nn.nxx) AS npanxx,
                lp.sipDomain,
                lp.lrn,
                lcd.community AS localCallCommunity,
                CONCAT(lcd.npa, '-', lcd.nxx) AS localCallNPAnxx,
                lp.switch,
                lp.cfsSubGrp
            FROM cdr.lcgLookupParameters lp
            LEFT JOIN cdr.lcgPrefixData pd
                ON pd.lookupParameterId = lp.id
                AND pd.reportDateTime = (
                    SELECT MAX(reportDateTime)
                    FROM cdr.lcgPrefixData
                )
            LEFT JOIN cdr.lcgNPANXX nn ON nn.lcgPrefixDataId = pd.id
            LEFT JOIN cdr.lcgLocalCallingDestinations lcd ON lcd.lcgNPANXXid = nn.id
            ORDER BY nn.regionCommunity, lcd.community, lcd.npa, lcd.nxx
        ";
        $stmt = $dwPdo->query($sql);
        $nxxData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $dbConnected = true;
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}

// Extract unique filter options for dropdowns
$regions = [];
$switches = [];
$localCommunities = [];

foreach ($nxxData as $row) {
    if (!empty($row['regionCommunity'])) $regions[$row['regionCommunity']] = true;
    if (!empty($row['switch'])) $switches[$row['switch']] = true;
    if (!empty($row['localCallCommunity'])) $localCommunities[$row['localCallCommunity']] = true;
}

ksort($regions);
ksort($switches);
ksort($localCommunities);
?>

<div class="container-fluid p-4">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fa-solid fa-phone-nodes text-primary me-2"></i>Telephony NXX & Local Calling Circles</h2>
            <p class="text-muted mb-0">Query NPA-NXX exchanges, region communities, local calling destinations, switches, and SIP domains from DataWarehouse.</p>
        </div>
        <div>
            <a href="<?= function_exists('url_for') ? url_for('bitstreams_settings') : 'index.php?route=bitstreams_settings' ?>" class="btn btn-outline-secondary btn-sm fw-bold">
                <i class="fa-solid fa-sliders me-1"></i> DW DB Settings
            </a>
        </div>
    </div>

    <?php if (!$dbConnected): ?>
        <div class="alert alert-warning border-0 shadow-sm p-4 mb-4">
            <h5 class="fw-bold text-warning"><i class="fa-solid fa-triangle-exclamation me-2"></i>DataWarehouse Database Disconnected</h5>
            <p class="mb-2">Could not connect to the DataWarehouse MySQL database at <code><?= htmlspecialchars(bitstreams_get_setting('dw_dbhost', '127.0.0.1')) ?></code>.</p>
            <?php if ($dbError): ?>
                <div class="small text-danger font-monospace bg-light p-2 rounded mb-2"><?= htmlspecialchars($dbError) ?></div>
            <?php endif; ?>
            <p class="mb-0 small">Please update your DataWarehouse DB credentials in <a href="<?= function_exists('url_for') ? url_for('bitstreams_settings') : 'index.php?route=bitstreams_settings' ?>" class="fw-bold">Plugin Settings</a>.</p>
        </div>
    <?php endif; ?>

    <!-- FILTER BAR -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="nxxSearch" class="form-label small fw-bold mb-1"><i class="fa-solid fa-magnifying-glass me-1 text-primary"></i>Quick Search</label>
                    <input type="text" id="nxxSearch" class="form-control form-control-sm" placeholder="Search Exchange, NPA-NXX, LRN...">
                </div>
                <div class="col-md-3">
                    <label for="filterRegion" class="form-label small fw-bold mb-1"><i class="fa-solid fa-earth-americas me-1 text-info"></i>Region Community</label>
                    <select id="filterRegion" class="form-select form-select-sm">
                        <option value="">All Region Communities</option>
                        <?php foreach (array_keys($regions) as $reg): ?>
                            <option value="<?= htmlspecialchars($reg) ?>"><?= htmlspecialchars($reg) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterLocalCommunity" class="form-label small fw-bold mb-1"><i class="fa-solid fa-city me-1 text-success"></i>Local Call Community</label>
                    <select id="filterLocalCommunity" class="form-select form-select-sm">
                        <option value="">All Local Call Communities</option>
                        <?php foreach (array_keys($localCommunities) as $lc): ?>
                            <option value="<?= htmlspecialchars($lc) ?>"><?= htmlspecialchars($lc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="filterSwitch" class="form-label small fw-bold mb-1"><i class="fa-solid fa-server me-1 text-warning"></i>Switch</label>
                    <select id="filterSwitch" class="form-select form-select-sm">
                        <option value="">All Switches</option>
                        <?php foreach (array_keys($switches) as $sw): ?>
                            <option value="<?= htmlspecialchars($sw) ?>"><?= htmlspecialchars($sw) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-sm btn-outline-secondary w-100 fw-bold" onclick="resetNxxFilters()" title="Reset Filters">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- DATA CARD -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Local Calling Circles (LCG) Table</h6>
            <span class="badge bg-primary rounded-pill" id="recordCountBadge"><?= count($nxxData) ?> records</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="nxxTable">
                    <thead class="table-light">
                        <tr>
                            <th>Exchange</th>
                            <th>Region Community</th>
                            <th>NPA-NXX</th>
                            <th>SIP Domain</th>
                            <th>LRN</th>
                            <th>Local Call Community</th>
                            <th>Local Call NPA-NXX</th>
                            <th>Switch</th>
                            <th>CFS SubGrp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($nxxData)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">No NXX records retrieved or database unavailable.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($nxxData as $row): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['exchange'] ?? '') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['regionCommunity'] ?? '') ?></span></td>
                                    <td><code><?= htmlspecialchars($row['npanxx'] ?? '') ?></code></td>
                                    <td><small class="font-monospace text-muted"><?= htmlspecialchars($row['sipDomain'] ?? '') ?></small></td>
                                    <td><code><?= htmlspecialchars($row['lrn'] ?? '') ?></code></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($row['localCallCommunity'] ?? '') ?></td>
                                    <td><code><?= htmlspecialchars($row['localCallNPAnxx'] ?? '') ?></code></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['switch'] ?? '') ?></span></td>
                                    <td><?= htmlspecialchars($row['cfsSubGrp'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let nxxDataTable = null;

document.addEventListener("DOMContentLoaded", () => {
    if (typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined' && $('#nxxTable').length > 0) {
        nxxDataTable = $('#nxxTable').DataTable({
            "order": [[1, "asc"], [5, "asc"]],
            "pageLength": 25,
            "dom": '<"d-flex justify-content-between align-items-center mb-3"l>rtip'
        });

        // Quick search
        $('#nxxSearch').on('keyup change clear', function() {
            nxxDataTable.search(this.value).draw();
            updateCountBadge();
        });

        // Region Filter (Column 1)
        $('#filterRegion').on('change', function() {
            const val = $.fn.dataTable.util.escapeRegex(this.value);
            nxxDataTable.column(1).search(val ? '^' + val + '$' : '', true, false).draw();
            updateCountBadge();
        });

        // Local Call Community Filter (Column 5)
        $('#filterLocalCommunity').on('change', function() {
            const val = $.fn.dataTable.util.escapeRegex(this.value);
            nxxDataTable.column(5).search(val ? '^' + val + '$' : '', true, false).draw();
            updateCountBadge();
        });

        // Switch Filter (Column 7)
        $('#filterSwitch').on('change', function() {
            const val = $.fn.dataTable.util.escapeRegex(this.value);
            nxxDataTable.column(7).search(val ? '^' + val + '$' : '', true, false).draw();
            updateCountBadge();
        });
    }
});

function updateCountBadge() {
    if (nxxDataTable) {
        const count = nxxDataTable.rows({ filter: 'applied' }).count();
        document.getElementById('recordCountBadge').textContent = `${count} records`;
    }
}

function resetNxxFilters() {
    document.getElementById('nxxSearch').value = '';
    document.getElementById('filterRegion').value = '';
    document.getElementById('filterLocalCommunity').value = '';
    document.getElementById('filterSwitch').value = '';

    if (nxxDataTable) {
        nxxDataTable.search('').columns().search('').draw();
        updateCountBadge();
    }
}
</script>
