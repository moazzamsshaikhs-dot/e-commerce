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
    $zone_ids = $_POST['zone_ids'] ?? [];
    
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
        
        // Begin transaction
        $db->beginTransaction();
        
        // Remove existing zone assignments
        $stmt = $db->prepare("DELETE FROM vendor_zone_methods WHERE method_id = ?");
        $stmt->execute([$method_id]);
        
        // Add new zone assignments
        if (!empty($zone_ids)) {
            $stmt = $db->prepare("INSERT INTO vendor_zone_methods (zone_id, method_id) VALUES (?, ?)");
            foreach ($zone_ids as $zone_id) {
                $zone_id = intval($zone_id);
                if ($zone_id > 0) {
                    // Verify zone belongs to vendor
                    $check = $db->prepare("SELECT id FROM vendor_shipping_zones WHERE id = ? AND vendor_id = ?");
                    $check->execute([$zone_id, $vendor_id]);
                    if ($check->fetch()) {
                        $stmt->execute([$zone_id, $method_id]);
                    }
                }
            }
        }
        
        $db->commit();
        
        // Log activity
        logActivity($_SESSION['user_id'], 'shipping_method', "Updated zone assignments for method ID: {$method_id}");
        
        echo json_encode(['success' => true, 'message' => 'Zone assignments updated']);
        
    } catch(PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>