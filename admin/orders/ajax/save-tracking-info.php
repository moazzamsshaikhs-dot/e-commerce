<?php
// admin/orders/ajax/save-tracking-info.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$tracking_number = trim($_POST['tracking_number'] ?? '');
$carrier_id = isset($_POST['shipping_carrier_id']) ? (int)$_POST['shipping_carrier_id'] : null;

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

try {
    $db = getDB();
    
    // Update order
    $stmt = $db->prepare("
        UPDATE orders 
        SET tracking_number = ?, shipping_carrier_id = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$tracking_number ?: null, $carrier_id ?: null, $order_id]);
    
    // Log activity
    logUserActivity($_SESSION['user_id'], 'tracking_updated', 
        "Updated tracking info for order #{$order_id}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Tracking information saved successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}