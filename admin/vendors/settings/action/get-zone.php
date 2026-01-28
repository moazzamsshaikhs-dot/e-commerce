<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$zone_id = intval($_GET['id'] ?? 0);

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get zone details
    $stmt = $db->prepare("SELECT * FROM vendor_shipping_zones WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$zone_id, $vendor_id]);
    $zone = $stmt->fetch();
    
    if (!$zone) {
        echo json_encode(['success' => false, 'message' => 'Shipping zone not found']);
        exit;
    }
    
    // Decode zone data
    $zone_data = json_decode($zone['zone_data'], true);
    
    echo json_encode([
        'success' => true,
        'zone' => [
            'id' => $zone['id'],
            'zone_name' => $zone['zone_name'],
            'countries' => $zone_data['countries'] ?? [],
            'states' => $zone_data['states'] ?? [],
            'postal_codes' => $zone_data['postal_codes'] ?? '',
            'is_enabled' => $zone['is_enabled']
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>