<?php
// action/settings/upload-logo.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Upload Logo Started ===");

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

// Helper function for image upload (WITHOUT RESIZE)
function uploadVendorImage($file, $type = 'logo', $vendor_id) {
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Check for errors
    if ($file_error !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];
        $error_message = $upload_errors[$file_error] ?? 'Unknown upload error';
        throw new Exception('File upload error: ' . $error_message);
    }
    
    // Check file size
    if ($file_size > $max_size) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Check extension
    if (!in_array($file_ext, $allowed_ext)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WebP');
    }
    
    // Create upload directory if not exists
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/vendors/';
    
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        throw new Exception('Upload directory is not writable');
    }
    
    // Generate unique filename
    $new_filename = 'vendor_' . $vendor_id . '_' . $type . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $new_filename;
    
    // Move uploaded file (NO RESIZE)
    if (!move_uploaded_file($file_tmp, $upload_path)) {
        throw new Exception('Failed to upload file. Check directory permissions.');
    }
    
    return $new_filename;
}

try {
    $db = getDB();
    
    if (!isset($_FILES['store_logo']) || $_FILES['store_logo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $logo = uploadVendorImage($_FILES['store_logo'], 'logo', $vendor_id);
    error_log("Logo uploaded: $logo");
    
    $db->beginTransaction();
    
    // Check if vendor_settings exists
    $stmt = $db->prepare("SELECT vendor_id FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Get old logo to delete
        $stmt = $db->prepare("SELECT store_logo FROM vendor_settings WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        $old_logo = $stmt->fetchColumn();
        
        // Update
        $stmt = $db->prepare("UPDATE vendor_settings SET store_logo = ?, updated_at = NOW() WHERE vendor_id = ?");
        $stmt->execute([$logo, $vendor_id]);
        
        // Delete old logo file if exists
        if ($old_logo && $old_logo !== $logo) {
            $old_path = $_SERVER['DOCUMENT_ROOT'] . SITE_URL .'/uploads/vendors/' . $old_logo;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO vendor_settings (vendor_id, store_logo, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->execute([$vendor_id, $logo]);
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    try {
        $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'upload_logo', ?, ?, ?, NOW())");
        $log->execute([$vendor_id, "Uploaded new store logo", $ip, $ua]);
    } catch(PDOException $e) {
        // Ignore logging errors
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logo uploaded successfully!',
        'filename' => $logo
    ]);
    
  
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in upload-logo.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
// redirect to settings page after upload
?>