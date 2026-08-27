<?php
/**
 * Bitstreams Plugin DAC Digital EIA Grid View
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/../models/bitstreams-model.php';

$dacqueryAddress = bitstreams_get_setting('dacqueryAddress', '127.0.0.1');
$EIAs = bitstreams_get_eia_grid();

$MapID = $_GET['MapID'] ?? 19;
$format = $_GET['format'] ?? "mobile";
$pageRoute = function_exists('url_for') ? url_for('bitstreams_dac_eia_grid') : 'index.php?route=bitstreams_dac_eia_grid';

function fetchDacJson($url) {
    $ctx = stream_context_create(['http' => ['timeout' => 5]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res ? json_decode($res, true) : [];
}

$services = fetchDacJson("http://{$dacqueryAddress}/stbResponseAPI.php?command=getQueuingDevice&output=json");
$channels = fetchDacJson("http://{$dacqueryAddress}/stbResponseAPI.php?command=lineup&vcm_index={$MapID}&output=json");
$maps = fetchDacJson("http://{$dacqueryAddress}/stbResponseAPI.php?command=vcm&output=json");

$vcmName = $channels[0]['VCM_name'] ?? "VCM {$MapID}";

$counts = ['music' => 0, 'sd' => 0, 'hd' => 0, 'mpeg4' => 0, 'mpeg2' => 0];
$eiaUse = ['DV' => 0, 'DO' => 0];
$filteredList = [];
$i = 0;

if (is_array($services)) {
    foreach ($services as $service) {
        $filteredList[$i]['source_name'] = $service['source_name'] ?? '';
        $filteredList[$i]['serivce_provider_name'] = $service['serivce_provider_name'] ?? ($service['service_provider_name'] ?? '');
        $filteredList[$i]['service_index'] = $service['service_index'] ?? '';

        if (!empty($service['attributes']['service_mpeg4'])) {
            $filteredList[$i]['service_mpeg4'] = 1;
            $counts['mpeg4']++;
        } else {
            $filteredList[$i]['service_mpeg4'] = 0;
            $counts['mpeg2']++;
        }

        if (!empty($service['attributes']['service_hd'])) {
            $filteredList[$i]['service_hd'] = 1;
            $counts['hd']++;
        } else {
            $filteredList[$i]['service_hd'] = 0;
            $counts['sd']++;
        }

        if (!empty($service['logicalPort']) && is_array($service['logicalPort'])) {
            foreach ($service['logicalPort'] as $logicalPort) {
                if (str_contains($logicalPort['headend_deviceName'] ?? '', 'BDN')) {
                    $filteredList[$i]['edgeDevice'] = $logicalPort['headend_deviceName'];
                    $eia = substr($logicalPort['headend_deviceName'], -2);
                    $filteredList[$i]['EIA'][] = $eia;
                    $filteredList[$i]['mpeg_service_number'] = $logicalPort['mpeg_service_number'] ?? '0';
                }
                if (str_contains($logicalPort['headend_deviceName'] ?? '', 'NE_SEM')) {
                    $filteredList[$i]['SEM'] = $logicalPort['headend_deviceName'];
                    $filteredList[$i]['SemPort'] = $logicalPort['logical_port_name'] ?? 0;
                    $filteredList[$i]['queuingStatus'] = $logicalPort['queuing_state'] ?? 0;
                }
            }
        }

        if (empty($filteredList[$i]['SEM'])) {
            $filteredList[$i]['SEM'] = 'Not set';
            $filteredList[$i]['SemPort'] = 0;
            $filteredList[$i]['queuingStatus'] = 0;
        }

        if (empty($filteredList[$i]['edgeDevice'])) {
            $filteredList[$i]['edgeDevice'] = 'Not set';
            $filteredList[$i]['EIA'][] = '0';
            $filteredList[$i]['mpeg_service_number'] = '0';
        }

        $i++;
    }
}
?>

<div class="container-fluid p-4">

    <!-- HEADER & CONTROLS -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fa-solid fa-table-cells text-primary me-2"></i>DAC Digital EIA Grid</h2>
            <p class="text-muted mb-0">Showing <strong><?= htmlspecialchars($vcmName) ?></strong> Channel Lineup from DAC Host: <code><?= htmlspecialchars($dacqueryAddress) ?></code></p>
        </div>
        <div>
            <div class="btn-group" role="group">
                <a href="<?= $pageRoute ?>&format=mobile&MapID=<?= $MapID ?>" class="btn btn-sm <?= $format === 'mobile' ? 'btn-primary' : 'btn-outline-primary' ?> fw-bold">
                    <i class="fa-solid fa-grid-2 me-1"></i> Grid
                </a>
                <a href="<?= $pageRoute ?>&format=adv&MapID=<?= $MapID ?>" class="btn btn-sm <?= $format === 'adv' ? 'btn-primary' : 'btn-outline-primary' ?> fw-bold">
                    <i class="fa-solid fa-list-check me-1"></i> Detailed
                </a>
                <a href="<?= $pageRoute ?>&format=table2&MapID=<?= $MapID ?>" class="btn btn-sm <?= $format === 'table2' ? 'btn-primary' : 'btn-outline-primary' ?> fw-bold">
                    <i class="fa-solid fa-table me-1"></i> Table View
                </a>
            </div>
        </div>
    </div>

    <!-- MAP SELECTOR & SUMMARY STATS -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="fa-solid fa-map-location-dot text-primary fs-3 me-3"></i>
                    <div class="w-100">
                        <label for="mapSelect" class="form-label small fw-bold mb-1">Select Channel Map (VCM)</label>
                        <select id="mapSelect" class="form-select form-select-sm" onchange="location.href='<?= $pageRoute ?>&format=<?= $format ?>&MapID=' + this.value;">
                            <?php if (empty($maps)): ?>
                                <option value="<?= $MapID ?>">VCM Index <?= $MapID ?></option>
                            <?php else: ?>
                                <?php foreach ($maps as $m): ?>
                                    <option value="<?= $m['vcm_index'] ?? $m['id'] ?? $MapID ?>" <?= ($m['vcm_index'] ?? '') == $MapID ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($m['VCM_name'] ?? $m['name'] ?? "VCM {$MapID}") ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="row g-2 w-100 text-center">
                        <div class="col"><span class="badge bg-info p-2 w-100">HD: <?= $counts['hd'] ?></span></div>
                        <div class="col"><span class="badge bg-primary p-2 w-100">SD: <?= $counts['sd'] ?></span></div>
                        <div class="col"><span class="badge bg-warning text-dark p-2 w-100">Music: <?= $counts['music'] ?></span></div>
                        <div class="col"><span class="badge bg-danger p-2 w-100">MPEG4: <?= $counts['mpeg4'] ?></span></div>
                        <div class="col"><span class="badge bg-secondary p-2 w-100">MPEG2: <?= $counts['mpeg2'] ?></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LEGEND -->
    <div class="alert alert-light border shadow-sm d-flex align-items-center justify-content-between mb-4">
        <div class="fw-bold"><i class="fa-solid fa-tags text-secondary me-2"></i>Legend:</div>
        <div>
            <span class="badge bg-info me-2"><i class="fa-solid fa-tv me-1"></i> HD Service</span>
            <span class="badge bg-primary me-2"><i class="fa-solid fa-tv me-1"></i> SD Service</span>
            <span class="badge bg-warning text-dark me-2"><i class="fa-solid fa-music me-1"></i> Music Service</span>
            <span class="badge bg-danger me-2">MPEG4</span>
            <span class="badge bg-secondary">MPEG2</span>
        </div>
    </div>

    <?php if (empty($services) && empty($channels)): ?>
        <div class="alert alert-warning border-0 shadow-sm p-4 text-center">
            <i class="fa-solid fa-triangle-exclamation fs-2 mb-2 text-warning"></i>
            <h5>Unable to connect to DAC STB Response API</h5>
            <p class="mb-0">Could not retrieve channel map data from DAC Host <code><?= htmlspecialchars($dacqueryAddress) ?></code>. Please verify the <strong>DAC Query Address</strong> setting in <a href="index.php?route=bitstreams_settings" class="fw-bold text-decoration-none">Plugin Settings</a>.</p>
        </div>
    <?php else: ?>

        <?php if ($format === 'mobile'): ?>
            <!-- MOBILE / GRID VIEW -->
            <div class="row g-3">
                <?php
                foreach ($EIAs as $EIA):
                    if (($EIA['Use'] ?? '') === 'Digital Video') $eiaUse['DV']++;
                    if (($EIA['Use'] ?? '') === 'Docsis') $eiaUse['DO']++;
                ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card h-100 shadow-sm border-0">
                            <div class="card-header bg-white py-2">
                                <h6 class="mb-0 fw-bold text-primary">
                                    EIA <?= htmlspecialchars($EIA['EIANum']) ?>
                                    <small class="text-muted fw-normal"> - <?= htmlspecialchars($EIA['Use']) ?> (<?= htmlspecialchars($EIA['CenterFreq']) ?> MHz)</small>
                                </h6>
                            </div>
                            <div class="card-body p-2">
                                <?php
                                $foundAny = false;
                                foreach ($filteredList as $service) {
                                    if (isset($service['EIA']) && in_array($EIA['EIANum'], $service['EIA'])) {
                                        $foundAny = true;
                                        $channelNum = "";
                                        if (is_array($channels)) {
                                            foreach ($channels as $channel) {
                                                if (($channel['service_index'] ?? '') == ($service['service_index'] ?? '')) {
                                                    $channelNum = !empty($channelNum) ? $channelNum . " / " . $channel['channel_number'] : $channel['channel_number'];
                                                }
                                            }
                                        }
                                        if (empty($channelNum)) $channelNum = 'NA';

                                        $badgeBg = "bg-primary";
                                        if (!empty($service['service_hd'])) {
                                            $badgeBg = "bg-info";
                                        } elseif ((int)$channelNum >= 599 && (int)$channelNum <= 699) {
                                            $badgeBg = "bg-warning text-dark";
                                        }

                                        $codecBadge = !empty($service['service_mpeg4'])
                                            ? '<span class="badge bg-danger ms-1 float-end">MPEG4</span>'
                                            : '<span class="badge bg-secondary ms-1 float-end">MPEG2</span>';

                                        echo '<div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded ' . $badgeBg . ' text-white">';
                                        echo '<span><strong class="me-2">' . htmlspecialchars($channelNum) . '</strong> ' . htmlspecialchars($service['source_name']) . '</span>';
                                        echo $codecBadge;
                                        echo '</div>';
                                    }
                                }
                                if (!$foundAny) {
                                    echo '<div class="text-muted small p-2 text-center">Unassigned / Idle</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($format === 'adv'): ?>
            <!-- ADVANCED DETAILED VIEW -->
            <div class="row g-3">
                <?php foreach ($EIAs as $EIA): ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0 mb-2">
                            <div class="card-header bg-white py-2">
                                <h5 class="mb-0 fw-bold text-primary">
                                    EIA <?= htmlspecialchars($EIA['EIANum']) ?> - <?= htmlspecialchars($EIA['CenterFreq']) ?> MHz - <?= htmlspecialchars($EIA['Use']) ?>
                                </h5>
                            </div>
                            <div class="card-body p-3">
                                <div class="row g-3">
                                    <?php
                                    foreach ($filteredList as $service) {
                                        if (isset($service['EIA']) && in_array($EIA['EIANum'], $service['EIA'])) {
                                            $channelNum = "";
                                            if (is_array($channels)) {
                                                foreach ($channels as $channel) {
                                                    if (($channel['service_index'] ?? '') == ($service['service_index'] ?? '')) {
                                                        $channelNum = !empty($channelNum) ? $channelNum . " / " . $channel['channel_number'] : $channel['channel_number'];
                                                    }
                                                }
                                            }
                                            if (empty($channelNum)) $channelNum = '00';

                                            $badgeBg = "bg-primary";
                                            if (!empty($service['service_hd'])) {
                                                $badgeBg = "bg-info";
                                            } elseif ((int)$channelNum >= 599 && (int)$channelNum <= 699) {
                                                $badgeBg = "bg-warning text-dark";
                                            }

                                            echo '<div class="col-md-4 col-lg-3">';
                                            echo '<div class="border rounded p-2 bg-light h-100">';
                                            echo '<div class="p-1 mb-2 rounded text-white ' . $badgeBg . ' d-flex justify-content-between">';
                                            echo '<span><strong>' . htmlspecialchars($channelNum) . '</strong> ' . htmlspecialchars(substr($service['source_name'], 0, 25)) . '</span>';
                                            echo !empty($service['service_mpeg4']) ? '<span class="badge bg-danger">MPEG4</span>' : '<span class="badge bg-secondary">MPEG2</span>';
                                            echo '</div>';
                                            echo '<small class="d-block"><strong>Provider:</strong> ' . htmlspecialchars($service['serivce_provider_name']) . '</small>';
                                            echo '<small class="d-block"><strong>MPEG #:</strong> ' . htmlspecialchars($service['mpeg_service_number']) . '</small>';
                                            echo '<small class="d-block"><strong>SEM:</strong> ' . htmlspecialchars($service['SEM']) . '</small>';
                                            echo '<small class="d-block"><strong>SEM Port:</strong> ' . htmlspecialchars($service['SemPort']) . '</small>';
                                            echo '</div></div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($format === 'table2'): ?>
            <!-- DATA TABLE VIEW -->
            <div class="card p-3 shadow-sm border-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="serviceTable">
                        <thead class="table-light">
                            <tr>
                                <th>Service Name</th>
                                <th>Service Provider</th>
                                <th>Channel Number</th>
                                <th>Codec</th>
                                <th>Format</th>
                                <th>MPEG #</th>
                                <th>EIA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredList as $service): ?>
                                <?php
                                $channelNum = "";
                                if (is_array($channels)) {
                                    foreach ($channels as $channel) {
                                        if (($channel['service_index'] ?? '') == ($service['service_index'] ?? '')) {
                                            $channelNum = !empty($channelNum) ? $channelNum . " / " . $channel['channel_number'] : $channel['channel_number'];
                                        }
                                    }
                                }
                                if (empty($channelNum)) $channelNum = "Not Set for Ch Map";
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($service['source_name']) ?></td>
                                    <td><?= htmlspecialchars($service['serivce_provider_name']) ?></td>
                                    <td><code><?= htmlspecialchars($channelNum) ?></code></td>
                                    <td><?= !empty($service['service_mpeg4']) ? '<span class="badge bg-danger">MPEG4</span>' : '<span class="badge bg-secondary">MPEG2</span>' ?></td>
                                    <td><?= !empty($service['service_hd']) ? '<span class="badge bg-info">HD</span>' : '<span class="badge bg-primary">SD</span>' ?></td>
                                    <td><?= htmlspecialchars($service['mpeg_service_number']) ?></td>
                                    <td><code><?= htmlspecialchars($service['EIA'][0] ?? 'N/A') ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    if (typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined' && $('#serviceTable').length > 0) {
        $('#serviceTable').DataTable({
            "order": [[0, "asc"]],
            "pageLength": 50
        });
    }
});
</script>
