<?php
/**
 * Bitstreams Plugin Video & Voice Links Model
 */

if (!defined('APP_ROOT') && !class_exists('PluginManager')) {
    // Prevent direct execution
}

require_once __DIR__ . '/db.php';

/* =========================================================
 * VIDEO LINKS DB MODEL HELPERS
 * ========================================================= */

if (!function_exists('bitstreams_get_video_links')) {
    function bitstreams_get_video_links() {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();
        $data = [];

        if ($pdb) {
            $tb = $pdb->getTableName('video_links');
            try {
                $stmt = $pdb->query("SELECT * FROM {$tb} ORDER BY category ASC, id ASC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            return [];
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM plug_bitstreams_video_links ORDER BY category ASC, id ASC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }

        return $data;
    }
}

if (!function_exists('bitstreams_save_video_link')) {
    function bitstreams_save_video_link($data) {
        bitstreams_ensure_tables();
        $id = isset($data['id']) && !empty($data['id']) ? (int)$data['id'] : null;
        $category = trim($data['category'] ?? '');
        $device = trim($data['device'] ?? '');
        $purpose = trim($data['purpose'] ?? '');
        $url = trim($data['url'] ?? '');

        if (!$category || !$device || !$purpose || !$url) {
            return false;
        }

        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('video_links');
            if ($id) {
                $pdb->query("
                    UPDATE {$tb}
                    SET category = ?, device = ?, purpose = ?, url = ?
                    WHERE id = ?
                ", [$category, $device, $purpose, $url, $id]);
            } else {
                $pdb->query("
                    INSERT INTO {$tb} (category, device, purpose, url)
                    VALUES (?, ?, ?, ?)
                ", [$category, $device, $purpose, $url]);
            }
            return true;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE plug_bitstreams_video_links
                    SET category = ?, device = ?, purpose = ?, url = ?
                    WHERE id = ?
                ");
                $stmt->execute([$category, $device, $purpose, $url, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO plug_bitstreams_video_links (category, device, purpose, url)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$category, $device, $purpose, $url]);
            }
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_delete_video_link')) {
    function bitstreams_delete_video_link($id) {
        bitstreams_ensure_tables();
        $id = (int)$id;

        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('video_links');
            $pdb->query("DELETE FROM {$tb} WHERE id = ?", [$id]);
            return true;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM plug_bitstreams_video_links WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_import_video_links_csv')) {
    function bitstreams_import_video_links_csv($tmpFilePath) {
        if (!file_exists($tmpFilePath)) return false;

        $handle = fopen($tmpFilePath, "r");
        if (!$handle) return false;

        // Header row
        fgetcsv($handle);

        while (($r = fgetcsv($handle)) !== false) {
            if (count($r) >= 4) {
                $category = $r[1] ?? $r[0];
                $device = $r[2] ?? $r[1];
                $purpose = $r[3] ?? $r[2];
                $url = $r[4] ?? $r[3];

                if ($category && $device && $purpose && $url) {
                    bitstreams_save_video_link([
                        'category' => $category,
                        'device'   => $device,
                        'purpose'  => $purpose,
                        'url'      => $url
                    ]);
                }
            }
        }

        fclose($handle);
        return true;
    }
}

if (!function_exists('bitstreams_export_video_links_csv')) {
    function bitstreams_export_video_links_csv() {
        $links = bitstreams_get_video_links();

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=videoURLs_export.csv");

        $out = fopen("php://output", "w");
        fputcsv($out, ["id", "category", "device", "purpose", "url"]);

        foreach ($links as $r) {
            fputcsv($out, [$r['id'], $r['category'], $r['device'], $r['purpose'], $r['url']]);
        }

        fclose($out);
        exit;
    }
}

/* =========================================================
 * VOICE LINKS DB MODEL HELPERS
 * ========================================================= */

if (!function_exists('bitstreams_get_voice_links')) {
    function bitstreams_get_voice_links() {
        bitstreams_ensure_tables();
        $pdb = bitstreams_get_db_helper();

        if ($pdb) {
            $tb = $pdb->getTableName('voice_links');
            try {
                $stmt = $pdb->query("SELECT * FROM {$tb} ORDER BY category ASC, id ASC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
            return [];
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT * FROM plug_bitstreams_voice_links ORDER BY category ASC, id ASC");
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {}
        }

        return [];
    }
}

if (!function_exists('bitstreams_save_voice_link')) {
    function bitstreams_save_voice_link($data) {
        bitstreams_ensure_tables();
        $id = isset($data['id']) && !empty($data['id']) ? (int)$data['id'] : null;
        $category = trim($data['category'] ?? '');
        $device = trim($data['device'] ?? '');
        $purpose = trim($data['purpose'] ?? '');
        $url = trim($data['url'] ?? '');

        if (!$category || !$device || !$purpose || !$url) {
            return false;
        }

        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('voice_links');
            if ($id) {
                $pdb->query("
                    UPDATE {$tb}
                    SET category = ?, device = ?, purpose = ?, url = ?
                    WHERE id = ?
                ", [$category, $device, $purpose, $url, $id]);
            } else {
                $pdb->query("
                    INSERT INTO {$tb} (category, device, purpose, url)
                    VALUES (?, ?, ?, ?)
                ", [$category, $device, $purpose, $url]);
            }
            return true;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            if ($id) {
                $stmt = $pdo->prepare("
                    UPDATE plug_bitstreams_voice_links
                    SET category = ?, device = ?, purpose = ?, url = ?
                    WHERE id = ?
                ");
                $stmt->execute([$category, $device, $purpose, $url, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO plug_bitstreams_voice_links (category, device, purpose, url)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$category, $device, $purpose, $url]);
            }
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_delete_voice_link')) {
    function bitstreams_delete_voice_link($id) {
        bitstreams_ensure_tables();
        $id = (int)$id;

        $pdb = bitstreams_get_db_helper();
        if ($pdb) {
            $tb = $pdb->getTableName('voice_links');
            $pdb->query("DELETE FROM {$tb} WHERE id = ?", [$id]);
            return true;
        }

        $pdo = bitstreams_get_pdo();
        if ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM plug_bitstreams_voice_links WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        }

        return false;
    }
}

if (!function_exists('bitstreams_import_voice_links_csv')) {
    function bitstreams_import_voice_links_csv($tmpFilePath) {
        if (!file_exists($tmpFilePath)) return false;

        $handle = fopen($tmpFilePath, "r");
        if (!$handle) return false;

        // Header row
        fgetcsv($handle);

        while (($r = fgetcsv($handle)) !== false) {
            if (count($r) >= 4) {
                $category = $r[1] ?? $r[0];
                $device = $r[2] ?? $r[1];
                $purpose = $r[3] ?? $r[2];
                $url = $r[4] ?? $r[3];

                if ($category && $device && $purpose && $url) {
                    bitstreams_save_voice_link([
                        'category' => $category,
                        'device'   => $device,
                        'purpose'  => $purpose,
                        'url'      => $url
                    ]);
                }
            }
        }

        fclose($handle);
        return true;
    }
}

if (!function_exists('bitstreams_export_voice_links_csv')) {
    function bitstreams_export_voice_links_csv() {
        $links = bitstreams_get_voice_links();

        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=voiceURLs_export.csv");

        $out = fopen("php://output", "w");
        fputcsv($out, ["id", "category", "device", "purpose", "url"]);

        foreach ($links as $r) {
            fputcsv($out, [$r['id'], $r['category'], $r['device'], $r['purpose'], $r['url']]);
        }

        fclose($out);
        exit;
    }
}
