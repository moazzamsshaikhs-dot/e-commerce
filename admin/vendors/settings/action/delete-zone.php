<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zone_id = intval($_POST['zone_id'] ?? 0);
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify zone belongs to vendor
        $stmt = $db->prepare("SELECT id, zone_name FROM vendor_shipping_zones WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$zone_id, $vendor_id]);
        $zone = $stmt->fetch();
        
        if (!$zone) {
            echo json_encode(['success' => false, 'message' => 'Shipping zone not found']);
            exit;
        }
        
        // Check if zone has methods assigned
        $stmt = $db->prepare("SELECT COUNT(*) FROM vendor_zone_methods WHERE zone_id = ?");
        $stmt->execute([$zone_id]);
        $method_count = $stmt->fetchColumn();
        
        if ($method_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete zone with assigned shipping methods']);
            exit;
        }
        
        // Delete zone
        $stmt = $db->prepare("DELETE FROM vendor_shipping_zones WHERE id = ?");
        $stmt->execute([$zone_id]);
        
        // Log activity
        logActivity($vendor_id, 'shipping_zone', "Deleted shipping zone: {$zone['zone_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Shipping zone deleted']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>