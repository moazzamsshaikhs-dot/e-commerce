<?php
// action/settings/update-general.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Update General Settings Started ===");
error_log("POST data: " . print_r($_POST, true));

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    error_log("Access denied - not vendor");
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // Get and sanitize form data
    $store_name = trim($_POST['store_name'] ?? '');
    $store_description = trim($_POST['store_description'] ?? '');
    $store_category = $_POST['store_category'] ?? '';
    $store_phone = trim($_POST['store_phone'] ?? '');
    $store_email = trim($_POST['store_email'] ?? '');
    $store_website = trim($_POST['store_website'] ?? '');
    $store_currency = $_POST['store_currency'] ?? 'USD';
    $store_timezone = $_POST['store_timezone'] ?? 'UTC';
    $store_language = $_POST['store_language'] ?? 'en';
    
    error_log("Form data: name=$store_name, email=$store_email, category=$store_category");
    
    // Validation
    $errors = [];
    
    if (empty($store_name)) {
        $errors[] = 'Store name is required.';
    }
    
    if (!empty($store_email) && !filter_var($store_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
    
    if (!empty($store_website) && !filter_var($store_website, FILTER_VALIDATE_URL) && !filter_var('https://' . $store_website, FILTER_VALIDATE_URL)) {
        // Allow URLs without protocol
        $store_website = 'https://' . ltrim($store_website, 'https://');
        if (!filter_var($store_website, FILTER_VALIDATE_URL)) {
            $errors[] = 'Invalid website URL format.';
        }
    }
    
    if (!empty($errors)) {
        error_log("Validation errors: " . implode('. ', $errors));
        throw new Exception(implode('. ', $errors));
    }
    
    $db->beginTransaction();
    error_log("Transaction started");
    
    // Check if vendor_settings record exists
    $stmt = $db->prepare("SELECT vendor_id FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing
        error_log("Updating existing vendor_settings");
        $sql = "UPDATE vendor_settings SET 
                store_name = ?, store_description = ?, vendor_category = ?,
                store_phone = ?, store_email = ?, store_website = ?,
                store_currency = ?, store_timezone = ?, store_language = ?,
                updated_at = NOW()
                WHERE vendor_id = ?";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $store_name, $store_description, $store_category,
            $store_phone, $store_email, $store_website,
            $store_currency, $store_timezone, $store_language,
            $vendor_id
        ]);
    } else {
        // Insert new
        error_log("Inserting new vendor_settings");
        $sql = "INSERT INTO vendor_settings 
                (vendor_id, store_name, store_description, vendor_category,
                 store_phone, store_email, store_website, store_currency,
                 store_timezone, store_language, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $vendor_id, $store_name, $store_description, $store_category,
            $store_phone, $store_email, $store_website,
            $store_currency, $store_timezone, $store_language
        ]);
    }
    
    if (!$result) {
        $error = $stmt->errorInfo();
        error_log("Failed to update settings: " . print_r($error, true));
        throw new Exception('Failed to update settings: ' . $error[2]);
    }
    
    // Also update user table if email changed
    $stmt = $db->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $current_email = $stmt->fetchColumn();
    
    if ($store_email && $store_email !== $current_email) {
        error_log("Updating user email from $current_email to $store_email");
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([$store_email, $vendor_id]);
        $_SESSION['email'] = $store_email;
    }
    
    // Update vendor category in users table
    if ($store_category) {
        error_log("Updating vendor category to $store_category");
        $stmt = $db->prepare("UPDATE users SET vendor_category = ? WHERE id = ?");
        $stmt->execute([$store_category, $vendor_id]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'update_settings', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Updated general store settings", $ip, $ua]);
    
    $db->commit();
    error_log("Transaction committed successfully");
    
    echo json_encode([
        'success' => true,
        'message' => 'General settings updated successfully!'
    ]);
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        error_log("Transaction rolled back");
    }
    error_log("PDO Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        error_log("Transaction rolled back");
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>