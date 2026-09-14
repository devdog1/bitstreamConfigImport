<?php
/**
 * Bitstreams Plugin EIA Grid Model
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution
}

require_once __DIR__ . '/db.php';

if (!function_exists('bitstreams_get_eia_grid')) {
    function bitstreams_get_eia_grid() {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();

        if ($pdb) {
            $tb = $pdb->getTableName('eia_grid');
            try {
                $stmt = $pdb->query("SELECT * FROM {$tb} ORDER BY CAST(CenterFreq AS DECIMAL(10,2)) ASC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            return [];
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM plug_bitstreams_eia_grid ORDER BY CAST(CenterFreq AS DECIMAL(10,2)) ASC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }

        return [];
    }
}
