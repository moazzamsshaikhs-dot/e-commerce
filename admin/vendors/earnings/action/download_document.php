<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

$document_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vendor_id = $_SESSION['user_id'];

if (!$document_id) {
    die('Invalid document ID');
}

try {
    $db = getDB();
    
    // Get document info - ensure it belongs to the vendor
    $stmt = $db->prepare("
        SELECT * FROM vendor_documents 
        WHERE id = ? AND vendor_id = ?
    ");
    $stmt->execute([$document_id, $vendor_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        die('Document not found');
    }
    
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/documents/' . $document['document_file'];
    
    if (!file_exists($file_path)) {
        die('File not found');
    }
    
    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($document['document_file']) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read file
    readfile($file_path);
    exit;
    
} catch(PDOException $e) {
    error_log("Document download error: " . $e->getMessage());
    die('Error downloading document');
}
?>