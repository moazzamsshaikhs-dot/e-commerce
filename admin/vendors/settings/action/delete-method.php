<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method_id = intval($_POST['method_id'] ?? 0);
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify method belongs to vendor
        $stmt = $db->prepare("SELECT id, method_name FROM vendor_shipping_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$method_id, $vendor_id]);
        $method = $stmt->fetch();
        
        if (!$method) {
            echo json_encode(['success' => false, 'message' => 'Shipping method not found']);
            exit;
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        // Remove zone assignments
        $stmt = $db->prepare("DELETE FROM vendor_zone_methods WHERE method_id = ?");
        $stmt->execute([$method_id]);
        
        // Delete method
        $stmt = $db->prepare("DELETE FROM vendor_shipping_methods WHERE id = ?");
        $stmt->execute([$method_id]);
        
        $db->commit();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'shipping_method', "Deleted shipping method: {$method['method_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Shipping method deleted']);
        
    } catch(PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>