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
    
    // Get method details
    $stmt = $db->prepare("
        SELECT m.*, GROUP_CONCAT(zm.zone_id) as zone_ids
        FROM vendor_shipping_methods m
        LEFT JOIN vendor_zone_methods zm ON m.id = zm.method_id
        WHERE m.id = ? AND m.vendor_id = ?
        GROUP BY m.id
    ");
    $stmt->execute([$method_id, $vendor_id]);
    $method = $stmt->fetch();
    
    if (!$method) {
        echo json_encode(['success' => false, 'message' => 'Shipping method not found']);
        exit;
    }
    
    // Get all zones for this vendor
    $stmt = $db->prepare("SELECT id, zone_name FROM vendor_shipping_zones WHERE vendor_id = ? ORDER BY zone_name");
    $stmt->execute([$vendor_id]);
    $zones = $stmt->fetchAll();
    
    // Decode method data
    $method_data = json_decode($method['method_data'], true);
    
    echo json_encode([
        'success' => true,
        'method' => [
            'id' => $method['id'],
            'method_name' => $method['method_name'],
            'method_type' => $method_data['type'] ?? 'flat_rate',
            'cost' => $method_data['cost'] ?? 0,
            'free_shipping' => $method_data['free_shipping'] ?? 0,
            'min_order_amount' => $method_data['min_order_amount'] ?? 0,
            'max_order_amount' => $method_data['max_order_amount'] ?? 0,
            'estimated_days' => $method_data['estimated_days'] ?? 3,
            'is_enabled' => $method['is_enabled'],
            'zone_ids' => $method['zone_ids'] ? explode(',', $method['zone_ids']) : []
        ],
        'zones' => $zones
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>