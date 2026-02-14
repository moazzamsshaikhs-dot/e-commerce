<?php
// download_document.php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    //header('HTTP/1.0 403 Forbidden');
    header('Location: ' . SITE_URL . 'index.php');
    die('Access denied. Vendor only.');
}

$vendor_id = $_SESSION['user_id'];
$doc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_id <= 0) {
    $_SESSION['error'] = 'Invalid document ID';
    header('Location: ../tax.php');
    exit();
}

try {
    $db = getDB();
    
    // Get document info and verify vendor owns it
    $stmt = $db->prepare("
        SELECT vd.*, u.username 
        FROM vendor_documents vd
        JOIN users u ON vd.vendor_id = u.id
        WHERE vd.id = ? AND vd.vendor_id = ?
    ");
    $stmt->execute([$doc_id, $vendor_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        $_SESSION['error'] = 'Document not found or access denied';
        header('Location: ../tax.php');
        exit();
    }
    
    $file_path = SITE_URL . 'uploads/tax_documents/' . $document['document_file'];
    
    if (!file_exists($file_path)) {
        $_SESSION['error'] = 'File not found on server';
        header('Location: ../tax.php');
        exit();
    }
    
    // Get file info
    $file_name = basename($file_path);
    $file_size = filesize($file_path);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Determine content type
    $content_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // Set headers for download
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . $document['document_type'] . '_' . $vendor_id . '_' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Expires: 0');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Read and output file
    readfile($file_path);
    
    // Log the download
    logActivity($vendor_id, 'download_document', 'Downloaded tax document: ' . $document['document_type']);
    
    exit();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error downloading document: ' . $e->getMessage();
    header('Location: ../tax.php');
    exit();
}
?>