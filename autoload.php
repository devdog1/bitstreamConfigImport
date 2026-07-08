<?php
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load Core Config
require_once __DIR__ . '/inc/config.php';
$config = $coreConfig ?? [];

// Load Local Config from the script directory
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
if (file_exists($scriptDir . '/config.local.php')) {
    include $scriptDir . '/config.local.php';
    if (isset($localConfig) && is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}
