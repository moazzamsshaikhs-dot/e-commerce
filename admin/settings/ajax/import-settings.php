<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = getDB();
    
    // Check if file was uploaded
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $file = $_FILES['import_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($file_ext, ['json', 'csv', 'xml', 'zip'])) {
        throw new Exception('Invalid file format. Only JSON, CSV, XML, and ZIP files are allowed.');
    }
    
    // Handle ZIP files
    if ($file_ext === 'zip') {
        $zip = new ZipArchive;
        if ($zip->open($file['tmp_name']) === TRUE) {
            $extract_path = sys_get_temp_dir() . '/import_' . uniqid();
            $zip->extractTo($extract_path);
            $zip->close();
            
            // Find the first JSON/CSV/XML file in the extracted files
            $files = scandir($extract_path);
            $found_file = null;
            foreach ($files as $f) {
                if (preg_match('/\.(json|csv|xml)$/i', $f)) {
                    $found_file = $extract_path . '/' . $f;
                    $file_ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                    break;
                }
            }
            
            if (!$found_file) {
                throw new Exception('No valid settings file found in ZIP archive');
            }
            
            $content = file_get_contents($found_file);
            
            // Clean up temp files
            array_map('unlink', glob("$extract_path/*"));
            rmdir($extract_path);
        } else {
            throw new Exception('Failed to open ZIP file');
        }
    } else {
        $content = file_get_contents($file['tmp_name']);
    }
    
    // Parse based on file type
    $settings_data = [];
    if ($file_ext === 'json') {
        $settings_data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
        // Handle different JSON structures
        if (isset($settings_data['settings'])) {
            $settings_data = $settings_data['settings'];
        } elseif (isset($settings_data['data'])) {
            $settings_data = $settings_data['data'];
        }
    } elseif ($file_ext === 'csv') {
        $rows = array_map('str_getcsv', explode("\n", trim($content)));
        $headers = array_shift($rows);
        $settings_data = [];
        foreach ($rows as $row) {
            if (count($row) === count($headers) && !empty(array_filter($row))) {
                $settings_data[] = array_combine($headers, $row);
            }
        }
    } elseif ($file_ext === 'xml') {
        $xml = simplexml_load_string($content);
        $json = json_encode($xml);
        $settings_data = json_decode($json, true);
        if (isset($settings_data['setting'])) {
            $settings_data = $settings_data['setting'];
        }
    }
    
    // Ensure we have an array
    if (!is_array($settings_data)) {
        $settings_data = [$settings_data];
    }
    
    // Get import parameters
    $import_mode = $_POST['import_mode'] ?? 'merge';
    $conflict_resolution = $_POST['conflict_resolution'] ?? 'skip';
    $create_backup = isset($_POST['create_backup']);
    $dry_run = isset($_POST['dry_run']);
    $preserve_ids = isset($_POST['preserve_ids']);
    
    $db->beginTransaction();
    
    // Create backup if requested
    if ($create_backup && !$dry_run) {
        $backup_dir = '../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        $backup_file = $backup_dir . 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
        $stmt = $db->query("SELECT * FROM settings ORDER BY id");
        $current_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents($backup_file, json_encode($current_settings, JSON_PRETTY_PRINT));
    }
    
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $conflicts = 0;
    
    foreach ($settings_data as $setting) {
        // Skip if no setting_key
        if (!isset($setting['setting_key']) && !isset($setting['setting_key'])) {
            continue;
        }
        
        $setting_key = $setting['setting_key'] ?? $setting['setting_key'];
        
        // Check if setting exists
        $check_stmt = $db->prepare("SELECT id, setting_value FROM settings WHERE setting_key = ?");
        $check_stmt->execute([$setting_key]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Setting exists
            if ($import_mode === 'replace' || $import_mode === 'update') {
                // Check for conflict
                if ($conflict_resolution === 'skip') {
                    $skipped++;
                    continue;
                } elseif ($conflict_resolution === 'rename' && $existing['setting_value'] == ($setting['setting_value'] ?? '')) {
                    $conflicts++;
                    continue;
                }
                
                // Log old value before update
                $log_stmt = $db->prepare("
                    INSERT INTO settings_history (setting_key, old_value, new_value, changed_by, changed_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $setting_key,
                    $existing['setting_value'],
                    $setting['setting_value'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $update_stmt = $db->prepare("
                    UPDATE settings SET 
                        setting_value = ?,
                        setting_type = ?,
                        validation_rules = ?,
                        is_public = ?,
                        is_required = ?,
                        help_text = ?,
                        `group` = ?,
                        category = ?,
                        sort_order = ?,
                        updated_at = NOW()
                    WHERE setting_key = ?
                ");
                $update_stmt->execute([
                    $setting['setting_value'] ?? '',
                    $setting['setting_type'] ?? 'text',
                    $setting['validation_rules'] ?? null,
                    $setting['is_public'] ?? 0,
                    $setting['is_required'] ?? 0,
                    $setting['help_text'] ?? null,
                    $setting['group'] ?? 'general',
                    $setting['category'] ?? '',
                    $setting['sort_order'] ?? 0,
                    $setting_key
                ]);
                $updated++;
            } else {
                $skipped++;
            }
        } else {
            // New setting
            if ($import_mode !== 'update') {
                $insert_stmt = $db->prepare("
                    INSERT INTO settings (
                        setting_key, setting_value, setting_type, validation_rules,
                        is_public, is_required, help_text, `group`, category, sort_order,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insert_stmt->execute([
                    $setting_key,
                    $setting['setting_value'] ?? '',
                    $setting['setting_type'] ?? 'text',
                    $setting['validation_rules'] ?? null,
                    $setting['is_public'] ?? 0,
                    $setting['is_required'] ?? 0,
                    $setting['help_text'] ?? null,
                    $setting['group'] ?? 'general',
                    $setting['category'] ?? '',
                    $setting['sort_order'] ?? 0
                ]);
                
                // Log new setting creation
                $log_stmt = $db->prepare("
                    INSERT INTO settings_history (setting_key, old_value, new_value, changed_by, changed_at)
                    VALUES (?, 'NEW', ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $setting_key,
                    $setting['setting_value'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $imported++;
            } else {
                $skipped++;
            }
        }
    }
    
    if (!$dry_run) {
        // Log import activity
        $log_import = $db->prepare("
            INSERT INTO import_export_logs (type, filename, settings_count, import_mode, user_id, status, created_at)
            VALUES ('import', ?, ?, ?, ?, 'success', NOW())
        ");
        $log_import->execute([
            $file['name'],
            $imported + $updated,
            $import_mode,
            $_SESSION['user_id']
        ]);
        
        $db->commit();
    } else {
        $db->rollBack();
    }
    
    echo json_encode([
        'success' => true,
        'message' => $dry_run ? 'Preview completed' : "Import completed: $imported new, $updated updated, $skipped skipped, $conflicts conflicts",
        'dry_run' => $dry_run,
        'imported' => $imported,
        'updated' => $updated,
        'skipped' => $skipped,
        'conflicts' => $conflicts,
        'total_imported' => $imported + $updated
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}