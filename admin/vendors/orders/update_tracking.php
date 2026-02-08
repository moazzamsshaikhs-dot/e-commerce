<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved.';
        header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$tracking_number = trim($_POST['tracking_number'] ?? '');
$shipping_carrier = isset($_POST['shipping_carrier']) ? (int)$_POST['shipping_carrier'] : null;
$tracking_notes = trim($_POST['tracking_notes'] ?? '');

// Validation
if ($order_id <= 0) {
    $_SESSION['error'] = 'Invalid order ID.';
    header('Location: orders.php');
    exit();
}

if (empty($tracking_number)) {
    $_SESSION['error'] = 'Tracking number is required.';
    header('Location: view.php?id=' . $order_id);
    exit();
}

// Check if vendor has products in this order
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM order_items oi 
                         JOIN products p ON oi.product_id = p.id 
                         WHERE oi.order_id = ? AND p.vendor_id = ?");
    $stmt->execute([$order_id, $vendor_id]);
    $vendor_product_count = $stmt->fetchColumn();
    
    if ($vendor_product_count == 0) {
        $_SESSION['error'] = 'Access denied.';
        header('Location: orders.php');
        exit();
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error verifying order access.';
    header('Location: orders.php');
    exit();
}

// Update tracking information
try {
    $db = getDB();
    
    // Start transaction
    $db->beginTransaction();
    
    // Update order
    $stmt = $db->prepare("UPDATE orders SET 
                         tracking_number = ?,
                         shipping_carrier_id = ?,
                         status = 'shipped',
                         updated_at = NOW()
                         WHERE id = ?");
    $stmt->execute([$tracking_number, $shipping_carrier, $order_id]);
    
    // Add to status history
    $notes = 'Tracking number added: ' . $tracking_number;
    if (!empty($tracking_notes)) {
        $notes .= ' - ' . $tracking_notes;
    }
    
    $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, 'shipped', ?, ?)");
    $stmt->execute([$order_id, $vendor_id, $notes]);
    
    // Add internal note
    $note_text = "Tracking information added: $tracking_number";
    if (!empty($tracking_notes)) {
        $note_text .= " - $tracking_notes";
    }
    
    $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) VALUES (?, ?, 'internal', ?)");
    $stmt->execute([$order_id, $vendor_id, $note_text]);
    
    // Update vendor earnings status
    $stmt = $db->prepare("UPDATE vendor_earnings SET status = 'processing' WHERE order_id = ? AND vendor_id = ?");
    $stmt->execute([$order_id, $vendor_id]);
    
    // Commit transaction
    $db->commit();
    
    $_SESSION['success'] = 'Tracking information updated successfully!';
    
    // Log activity
    if (function_exists('logUserActivity')) {
        logUserActivity($vendor_id, 'order_tracking_update', "Added tracking #$tracking_number to order #$order_id");
    }
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = 'Error updating tracking information: ' . $e->getMessage();
}

// Redirect back to order view
header('Location: view.php?id=' . $order_id);
exit();
?>