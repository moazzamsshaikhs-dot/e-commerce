<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method_id = intval($_POST['method_id'] ?? 0);
    $method_name = trim($_POST['method_name'] ?? '');
    $method_type = $_POST['method_type'] ?? 'flat_rate';
    $cost = floatval($_POST['cost'] ?? 0);
    $free_shipping = isset($_POST['free_shipping']) ? 1 : 0;
    $min_order_amount = floatval($_POST['min_order_amount'] ?? 0);
    $max_order_amount = floatval($_POST['max_order_amount'] ?? 0);
    $estimated_days = intval($_POST['estimated_days'] ?? 3);
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify method belongs to vendor
        $stmt = $db->prepare("SELECT id FROM vendor_shipping_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$method_id, $vendor_id]);
        $method = $stmt->fetch();
        
        if (!$method) {
            $_SESSION['error'] = 'Shipping method not found';
            header('Location: ../shipping.php');
            exit;
        }
        
        if (empty($method_name)) {
            throw new Exception('Method name is required.');
        }
        
        $method_data = json_encode([
            'type' => $method_type,
            'cost' => $cost,
            'free_shipping' => $free_shipping,
            'min_order_amount' => $min_order_amount,
            'max_order_amount' => $max_order_amount,
            'estimated_days' => $estimated_days
        ]);
        
        // Update method
        $stmt = $db->prepare("
            UPDATE vendor_shipping_methods 
            SET method_name = ?, method_data = ?, is_enabled = ?, updated_at = NOW()
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$method_name, $method_data, $is_enabled, $method_id, $vendor_id]);
        
        // Log activity
        logActivity($vendor_id, 'shipping_method', "Updated shipping method: {$method_name}");
        
        $_SESSION['success'] = 'Shipping method updated successfully!';
        redirect(SITE_URL . 'admin/vendors/settings/shipping.php');

    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect(SITE_URL . 'admin/vendors/settings/shipping.php');
    }
}
?>