<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$method_id = intval($_GET['id'] ?? 0);

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
    
    // Get assigned zones for this method
    $stmt = $db->prepare("
        SELECT z.id, z.zone_name, zm.method_id
        FROM vendor_shipping_zones z
        LEFT JOIN vendor_zone_methods zm ON z.id = zm.zone_id AND zm.method_id = ?
        WHERE z.vendor_id = ?
        ORDER BY z.zone_name
    ");
    $stmt->execute([$method_id, $vendor_id]);
    $zones = $stmt->fetchAll();
    
    // Format response
    $result = [];
    foreach ($zones as $zone) {
        $result[] = [
            'id' => $zone['id'],
            'zone_name' => $zone['zone_name'],
            'assigned' => !is_null($zone['method_id'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'zones' => $result,
        'method_id' => $method_id
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>