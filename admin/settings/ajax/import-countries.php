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
    if (!in_array($file_ext, ['json', 'csv'])) {
        throw new Exception('Invalid file format. Only JSON and CSV files are allowed.');
    }
    
    // Read file content
    $content = file_get_contents($file['tmp_name']);
    
    // Parse based on file type
    $countries_data = [];
    if ($file_ext === 'json') {
        $countries_data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON format: ' . json_last_error_msg());
        }
    } else { // CSV
        $rows = array_map('str_getcsv', explode("\n", $content));
        $headers = array_shift($rows);
        foreach ($rows as $row) {
            if (count($row) === count($headers) && !empty($row[0])) {
                $countries_data[] = array_combine($headers, $row);
            }
        }
    }
    
    $db->beginTransaction();
    
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    
    foreach ($countries_data as $country) {
        if (!isset($country['code']) || !isset($country['name'])) continue;
        
        // Check if country exists
        $stmt = $db->prepare("SELECT code FROM countries WHERE code = ?");
        $stmt->execute([$country['code']]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing
            $stmt = $db->prepare("
                UPDATE countries 
                SET name = ?, currency_code = ?, currency_symbol = ?, phone_code = ?, is_active = ?, updated_at = NOW()
                WHERE code = ?
            ");
            $stmt->execute([
                $country['name'],
                $country['currency_code'] ?? 'USD',
                $country['currency_symbol'] ?? '$',
                $country['phone_code'] ?? '',
                $country['is_active'] ?? 1,
                $country['code']
            ]);
            $updated++;
        } else {
            // Insert new
            $stmt = $db->prepare("
                INSERT INTO countries (code, name, currency_code, currency_symbol, phone_code, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $country['code'],
                $country['name'],
                $country['currency_code'] ?? 'USD',
                $country['currency_symbol'] ?? '$',
                $country['phone_code'] ?? '',
                $country['is_active'] ?? 1
            ]);
            $imported++;
        }
    }
    
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