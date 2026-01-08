<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_id = (int)$_POST['order_id'];
$tracking_number = trim($_POST['tracking_number'] ?? '');
$shipping_carrier_id = !empty($_POST['shipping_carrier_id']) ? (int)$_POST['shipping_carrier_id'] : null;

try {
    $db = getDB();
    
    // Update order with tracking info
    $stmt = $db->prepare("UPDATE orders 
                          SET tracking_number = ?, shipping_carrier_id = ?
                          WHERE id = ?");
    $stmt->execute([$tracking_number, $shipping_carrier_id, $order_id]);
    
    // Add note about tracking update
    $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) 
                          VALUES (?, ?, 'internal', ?)");
    $note = "Tracking information updated";
    if ($tracking_number) {
        $note .= ": " . $tracking_number;
    }
    $stmt->execute([$order_id, $_SESSION['user_id'], $note]);
    
    echo json_encode(['success' => true, 'message' => 'Tracking information saved']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>