<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$doc_id = (int)($_GET['id'] ?? 0);

if (!$doc_id) {
    $_SESSION['error'] = 'Invalid document';
    header('Location: profile.php');
    exit();
}

$db = getDB();

try {
    // Get document details
    $stmt = $db->prepare("SELECT * FROM vendor_documents WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$doc_id, $vendor_id]);
    $doc = $stmt->fetch();
    
    if (!$doc) {
        throw new Exception('Document not found');
    }
    
    if ($doc['verified']) {
        throw new Exception('Cannot delete verified document');
    }
    
    // Delete file
    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/documents/' . $doc['document_file'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete from database
    $stmt = $db->prepare("DELETE FROM vendor_documents WHERE id = ?");
    $stmt->execute([$doc_id]);
    
    $_SESSION['success'] = 'Document deleted successfully';
    
} catch(Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: profile.php');
exit();