<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$doc_ids = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_ids <= 0) {
    $_SESSION['error'] = 'Invalid document ID';
    header('Location: ../tax.php');
    exit();
}

try {
    $db = getDB();
    
    // Verify document belongs to vendor
    $stmt = $db->prepare("SELECT * FROM vendor_documents WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$doc_ids, $vendor_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        $_SESSION['error'] = 'Document not found or access denied';
        header('Location: ../tax.php');
        exit();
    }
    
    // Delete the document file from server
    $file_path = SITE_URL . 'uploads/tax_documents/' . $document['document_file'];
    
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete from database
    $stmt = $db->prepare("DELETE FROM vendor_documents WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$doc_ids, $vendor_id]);
    
    $_SESSION['success'] = 'Document deleted successfully';
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error deleting document: ' . $e->getMessage();
}

header('Location: ../tax.php');
exit();
?>