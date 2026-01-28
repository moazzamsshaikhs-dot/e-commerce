<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $method_id = intval($data['method_id'] ?? 0);
    $enabled = intval($data['enabled'] ?? 0);
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify method belongs to vendor
        $stmt = $db->prepare("SELECT id FROM vendor_shipping_methods WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$method_id, $vendor_id]);
        $method = $stmt->fetch();
        
        if (!$method) {
            echo json_encode(['success' => false, 'message' => 'Shipping method not found']);
            exit;
        }
        
        // Update method status
        $stmt = $db->prepare("UPDATE vendor_shipping_methods SET is_enabled = ? WHERE id = ?");
        $stmt->execute([$enabled, $method_id]);
        
        // Log activity
        $action = $enabled ? 'enabled' : 'disabled';
        logActivity($_SESSION['user_id'], 'shipping_method', "{$action} shipping method ID: {$method_id}");
        
        echo json_encode(['success' => true, 'message' => 'Shipping method updated']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>