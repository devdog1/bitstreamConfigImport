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

    <!-- DATA CARD -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list-ol me-2 text-primary"></i>Local Calling Circles (LCG) Table</h6>
            <span class="badge bg-primary rounded-pill"><?= count($nxxData) ?> records</span>
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
document.addEventListener("DOMContentLoaded", () => {
    if (typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined' && $('#nxxTable').length > 0) {
        $('#nxxTable').DataTable({
            "order": [[1, "asc"], [5, "asc"]],
            "pageLength": 25
        });
    }
});
</script>
