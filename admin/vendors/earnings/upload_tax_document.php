<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendor_id = $_SESSION['user_id'];
    $document_type = $_POST['document_type'];
    $document_number = $_POST['document_number'] ?? null;
    $expiry_date = $_POST['expiry_date'] ?? null;
    
    // File upload handling
    $upload_dir = '../../../uploads/tax_documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = uniqid('tax_doc_') . '_' . basename($_FILES['document_file']['name']);
    $target_file = $upload_dir . $file_name;
    
    // Validate file
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($_FILES['document_file']['type'], $allowed_types)) {
        $_SESSION['error'] = 'Invalid file type. Only PDF, JPG, PNG allowed.';
        header('Location: tax.php');
        exit();
    }
    
    if ($_FILES['document_file']['size'] > $max_size) {
        $_SESSION['error'] = 'File size must be less than 5MB.';
        header('Location: tax.php');
        exit();
    }
    
    // Move uploaded file
    if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_file)) {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO vendor_documents 
                (vendor_id, document_type, document_number, document_file, expiry_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $vendor_id, 
                $document_type, 
                $document_number, 
                $file_name,
                $expiry_date ? date('Y-m-d', strtotime($expiry_date)) : null
            ]);
            
            // Notify admin for verification
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type)
                VALUES (1, 'New Tax Document Uploaded', 
                        'Vendor ID: $vendor_id uploaded a new tax document for verification.', 
                        'info')
            ");
            $stmt->execute();
            
            logActivity($vendor_id, 'upload_tax_document', 'Uploaded tax document: ' . $document_type);
            
            $_SESSION['success'] = 'Tax document uploaded successfully! It will be verified by our team.';
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Error uploading document: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Error uploading file. Please try again.';
    }
    
    header('Location: tax.php');
    exit();
}
?>