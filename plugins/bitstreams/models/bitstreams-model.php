<?php
/**
 * Bitstreams Plugin Main Model Loader
 * Loads modular model components for database connections, servers, links, EIA grid, and external APIs.
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/servers.php';
require_once __DIR__ . '/links.php';
require_once __DIR__ . '/eia.php';
require_once __DIR__ . '/api.php';
