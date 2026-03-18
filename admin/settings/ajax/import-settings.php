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
    if (!isset($_FILES['settings_file']) || $_FILES['settings_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $file = $_FILES['settings_file'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($file_ext, ['json', 'csv'])) {
        throw new Exception('Invalid file format. Only JSON and CSV files are allowed.');
    }
    
    // Read file content
    $content = file_get_contents($file['tmp_name']);
    
    // Parse based on file type
    $settings_data = [];
    if ($file_ext === 'json') {
        $settings_data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
    } else { // CSV
        $rows = array_map('str_getcsv', explode("\n", $content));
        $headers = array_shift($rows);
        foreach ($rows as $row) {
            if (count($row) === count($headers)) {
                $settings_data[] = array_combine($headers, $row);
            }
        }
    }
    
    // Get import mode
    $import_mode = $_POST['import_mode'] ?? 'merge';
    $create_backup = isset($_POST['create_backup']);
    
    $db->beginTransaction();
    
    // Create backup if requested
    if ($create_backup) {
        $backup_dir = '../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        $backup_file = $backup_dir . 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
        $stmt = $db->query("SELECT * FROM settings");
        $current_settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        file_put_contents($backup_file, json_encode($current_settings, JSON_PRETTY_PRINT));
    }
    
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    
    foreach ($settings_data as $setting) {
        if (!isset($setting['setting_key'])) continue;
        
        $check_stmt = $db->prepare("SELECT id, setting_value FROM settings WHERE setting_key = ?");
        $check_stmt->execute([$setting['setting_key']]);
        $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Setting exists
            if ($import_mode === 'replace' || $import_mode === 'update') {
                // Log old value before update
                $log_stmt = $db->prepare("
                    INSERT INTO settings_history (setting_key, old_value, new_value, changed_by, changed_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $setting['setting_key'],
                    $exists['setting_value'],
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
                    $setting['setting_key']
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
                        is_public, is_required, help_text, `group`, sort_order, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $insert_stmt->execute([
                    $setting['setting_key'],
                    $setting['setting_value'] ?? '',
                    $setting['setting_type'] ?? 'text',
                    $setting['validation_rules'] ?? null,
                    $setting['is_public'] ?? 0,
                    $setting['is_required'] ?? 0,
                    $setting['help_text'] ?? null,
                    $setting['group'] ?? 'general',
                    $setting['sort_order'] ?? 0
                ]);
                
                // Log new setting creation
                $log_stmt = $db->prepare("
                    INSERT INTO settings_history (setting_key, old_value, new_value, changed_by, changed_at)
                    VALUES (?, 'NEW', ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $setting['setting_key'],
                    $setting['setting_value'] ?? '',
                    $_SESSION['user_id']
                ]);
                
                $imported++;
            } else {
                $skipped++;
            }
        }
    }
    
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
    
    echo json_encode([
        'success' => true,
        'message' => "Import completed: $imported new, $updated updated, $skipped skipped",
        'imported' => $imported,
        'updated' => $updated,
        'skipped' => $skipped
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}