<?php
/**
 * Plugin Name: Bitstreams & INCA Stream Manager
 * Description: Convert INCA backups to Bitstreams sessions, monitor stream status via SNMP and Bitstreams REST API, and control live streams.
 * Version: 1.0.0
 * Author: DevDog
 * Permissions: bitstreams_view, bitstreams_edit, bitstreams_settings
 * Roles: manager:bitstreams_view,bitstreams_edit,bitstreams_settings; operator:bitstreams_view,bitstreams_edit; viewer:bitstreams_view
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    exit;
}

require_once __DIR__ . '/models/bitstreams-model.php';

// 1. Navigation Menu Hook
PluginManager::getInstance()->addFilter('theme_nav_links', function ($links) {
    $links[] = [
        'route' => 'bitstreams_overview',
        'label' => 'Bitstreams & INCA',
        'icon'  => 'fa-solid fa-satellite-dish',
        'permission' => 'bitstreams_view',
        'children' => [
            ['label' => 'Stream Overview', 'icon' => 'fa-solid fa-chart-line', 'route' => 'bitstreams_overview', 'permission' => 'bitstreams_view'],
            ['label' => 'INCA Migration Tool', 'icon' => 'fa-solid fa-file-import', 'route' => 'bitstreams_migration', 'permission' => 'bitstreams_edit'],
            ['label' => 'Plugin Settings', 'icon' => 'fa-solid fa-sliders', 'route' => 'bitstreams_settings', 'permission' => 'bitstreams_settings']
        ]
    ];
    return $links;
});

// 2. Route Registrations
PluginManager::getInstance()->registerRoute('bitstreams_overview', function () {
    if (function_exists('has_permission') && !has_permission('bitstreams_view')) {
        die("Access Denied: Missing 'bitstreams_view' permission.");
    }
    require_once __DIR__ . '/views/overview-view.php';
});

PluginManager::getInstance()->registerRoute('bitstreams_migration', function () {
    if (function_exists('has_permission') && !has_permission('bitstreams_edit')) {
        die("Access Denied: Missing 'bitstreams_edit' permission.");
    }
    require_once __DIR__ . '/views/migration-view.php';
});

PluginManager::getInstance()->registerRoute('bitstreams_settings', function () {
    if (function_exists('has_permission') && !has_permission('bitstreams_settings')) {
        die("Access Denied: Missing 'bitstreams_settings' permission.");
    }
    require_once __DIR__ . '/views/settings-view.php';
});

PluginManager::getInstance()->registerRoute('bitstreams_api', function () {
    require_once __DIR__ . '/views/api-router.php';
});

// 3. User-Context Dashboard Card Widget
PluginManager::getInstance()->addAction('index_dashboard_widgets', function($userContext) {
    $servers = bitstreams_get_servers();
    $incaHosts = bitstreams_get_inca_hosts();
    ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card shadow-sm border-start border-4 border-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="bg-primary-subtle rounded-circle p-2 me-3 text-center" style="width: 45px; height: 45px;">
                        <i class="fa-solid fa-satellite-dish text-primary fs-4"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0">Bitstreams & INCA</h6>
                        <small class="text-muted">Stream Monitoring & Migration</small>
                    </div>
                </div>
                <div class="row text-center bg-light rounded p-2 g-2 mb-3 ms-0 me-0">
                    <div class="col-6 border-end">
                        <div class="fs-5 fw-bold text-primary"><?= count($servers) ?></div>
                        <div class="small text-muted">Bitstreams Servers</div>
                    </div>
                    <div class="col-6">
                        <div class="fs-5 fw-bold text-info"><?= count($incaHosts) ?></div>
                        <div class="small text-muted">INCA Hosts</div>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <a href="<?= function_exists('url_for') ? url_for('bitstreams_overview') : 'overview.php' ?>" class="btn btn-sm btn-outline-primary fw-bold">
                        <i class="fa-solid fa-chart-line me-1"></i> Open Stream Overview
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
});

// 4. Lifecycle Activation and Deactivation Hooks
$activateCallback = function() {
    bitstreams_ensure_tables();
    if (function_exists('log_action')) {
        log_action('BITSTREAMS_PLUGIN_ACTIVATE', ['status' => 'success']);
    }
};

PluginManager::getInstance()->addAction('plugin_activate_bitstreams', $activateCallback);
PluginManager::getInstance()->addAction('activate_plugin_bitstreams', $activateCallback);

$deactivateCallback = function() {
    if (function_exists('log_action')) {
        log_action('BITSTREAMS_PLUGIN_DEACTIVATE', ['status' => 'success']);
    }
};

PluginManager::getInstance()->addAction('plugin_deactivate_bitstreams', $deactivateCallback);
PluginManager::getInstance()->addAction('deactivate_plugin_bitstreams', $deactivateCallback);
