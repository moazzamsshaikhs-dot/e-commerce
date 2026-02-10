<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tax_id = trim($_POST['tax_id']);
    $country = $_POST['country'];
    $business_name = trim($_POST['business_name']);
    $vendor_id = $_SESSION['user_id'];
    
    try {
        $db = getDB();
        
        // Update user tax information
        $stmt = $db->prepare("
            UPDATE users 
            SET tax_id = ?, country = ?, full_name = COALESCE(NULLIF(?, ''), full_name)
            WHERE id = ?
        ");
        $stmt->execute([$tax_id, $country, $business_name, $vendor_id]);
        
        // Log the update
        logActivity($vendor_id, 'update_tax_info', 'Updated tax information');
        
        $_SESSION['success'] = 'Tax information updated successfully!';
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error updating tax information: ' . $e->getMessage();
    }
    
    header('Location: tax.php');
    exit();
}
?>