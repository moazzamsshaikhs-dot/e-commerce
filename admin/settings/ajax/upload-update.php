<?php
// ajax/upload-update.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Check if file was uploaded
if (!isset($_FILES['update_package']) || $_FILES['update_package']['error'] !== UPLOAD_ERR_OK) {
    $error_message = 'No file uploaded or upload error';
    if (isset($_FILES['update_package']['error'])) {
        switch ($_FILES['update_package']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $error_message = 'File exceeds upload_max_filesize limit';
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'File exceeds MAX_FILE_SIZE limit';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File was only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No file was uploaded';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Missing temporary folder';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'File upload stopped by extension';
                break;
        }
    }
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit;
}

$file = $_FILES['update_package'];
$filename = $file['name'];
$tmp_path = $file['tmp_name'];
$file_size = $file['size'];
$file_type = $file['type'];

// Validate file extension
$allowed_extensions = ['zip', 'gz', 'tar'];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!in_array($extension, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: ZIP, TAR.GZ']);
    exit;
}

// Validate file size (max 100MB)
$max_size = 100 * 1024 * 1024; // 100MB
if ($file_size > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size: 100MB']);
    exit;
}

// Create uploads directory if not exists
$upload_dir = __DIR__ . '/../../uploads/updates/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$safe_filename = 'update_' . date('Y-m-d_H-i-s') . '.' . $extension;
$upload_path = $upload_dir . $safe_filename;

// Move uploaded file
if (!move_uploaded_file($tmp_path, $upload_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
    exit;
}

try {
    $db = getDB();
    
    // Extract version from filename or package
    $version = extractVersionFromPackage($upload_path, $extension);
    
    if (!$version) {
        $version = '1.1.0'; // Default version if cannot extract
    }
    
    // Create update record in database
    $stmt = $db->prepare("INSERT INTO system_updates (version, description, release_date, changelog, is_applied) 
                          VALUES (?, 'Manual update', NOW(), ?, 0)");
    $stmt->execute([
        $version,
        "Manual update uploaded on " . date('Y-m-d H:i:s')
    ]);
    
    // Create update session
    $session_id = uniqid('manual_', true);
    $stmt = $db->prepare("INSERT INTO update_sessions (session_id, version, status, progress, logs, started_at) 
                          VALUES (?, ?, 'pending', 0, ?, NOW())");
    $stmt->execute([$session_id, $version, json_encode(['Update package uploaded successfully'])]);
    
    // Log the upload
    $log_message = date('Y-m-d H:i:s') . " - Manual update uploaded: {$safe_filename} (Version: {$version})\n";
    $log_file = __DIR__ . '/../../logs/update_uploads.log';
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0777, true);
    }
    file_put_contents($log_file, $log_message, FILE_APPEND);
    
    echo json_encode([
        'success' => true,
        'message' => 'Update package uploaded successfully',
        'version' => $version,
        'filename' => $safe_filename,
        'session_id' => $session_id
    ]);
    
} catch(PDOException $e) {
    error_log("Upload update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Extract version from update package
 */
function extractVersionFromPackage($package_path, $extension) {
    // Try to extract version from package
    if ($extension === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($package_path) === true) {
            // Look for version file
            $version_file = $zip->getFromName('version.txt');
            if ($version_file) {
                $zip->close();
                return trim($version_file);
            }
            
            // Look for version.json
            $version_json = $zip->getFromName('version.json');
            if ($version_json) {
                $version_data = json_decode($version_json, true);
                $zip->close();
                return $version_data['version'] ?? null;
            }
            
            $zip->close();
        }
    }
    
    // If can't extract, generate version from date
    return '1.1.' . date('Ymd');
}
?>