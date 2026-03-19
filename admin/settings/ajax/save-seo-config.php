<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get form data
$meta_title = $_POST['meta_title'] ?? '';
$meta_description = $_POST['meta_description'] ?? '';
$meta_keywords = $_POST['meta_keywords'] ?? '';
$google_analytics_id = $_POST['google_analytics_id'] ?? '';
$google_site_verification = $_POST['google_site_verification'] ?? '';
$robots_txt = $_POST['robots_txt'] ?? '';

try {
    $db = getDB();
    $db->beginTransaction();
    
    // Array of settings to save
    $settings = [
        'meta_title' => $meta_title,
        'meta_description' => $meta_description,
        'meta_keywords' => $meta_keywords,
        'google_analytics_id' => $google_analytics_id,
        'google_site_verification' => $google_site_verification,
        'robots_txt' => $robots_txt
    ];
    
    foreach ($settings as $key => $value) {
        // Check if setting exists
        $stmt = $db->prepare("SELECT id FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing setting
            $stmt = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        } else {
            // Insert new setting
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, `group`, created_at, updated_at) VALUES (?, ?, 'text', 'seo', NOW(), NOW())");
            $stmt->execute([$key, $value]);
        }
        
        // Log the change
        $log_stmt = $db->prepare("INSERT INTO settings_history (setting_key, old_value, new_value, changed_by, changed_at) VALUES (?, ?, ?, ?, NOW())");
        $log_stmt->execute([$key, $exists ? 'updated' : 'NEW', $value, $_SESSION['user_id']]);
    }
    
    // Save robots.txt to file if needed
    if (!empty($robots_txt)) {
        $robots_file = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
        file_put_contents($robots_file, $robots_txt);
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'SEO configuration saved successfully'
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}