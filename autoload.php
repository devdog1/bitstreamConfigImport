<?php
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/classes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

require_once __DIR__ . '/inc/config.php';

// Include local overrides from the same folder as the script
$scriptDir = dirname($_SERVER['SCRIPT_FILENAME']);
if (file_exists($scriptDir . '/config.local.php')) {
    include $scriptDir . '/config.local.php';
}
