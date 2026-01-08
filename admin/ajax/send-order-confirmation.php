<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

$order_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get order and customer details
    $stmt = $db->prepare("SELECT o.*, u.full_name, u.email 
                          FROM orders o 
                          LEFT JOIN users u ON o.user_id = u.id 
                          WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // In real application, you would send email here
    // This is a simulation for demo purposes
    
    // Example email sending (you would use PHPMailer or similar)
    /*
    $to = $order['email'];
    $subject = "Order Confirmation #" . $order['order_number'];
    $message = "Dear " . $order['full_name'] . ",\n\n";
    $message .= "Thank you for your order #" . $order['order_number'] . ".\n";
    $message .= "Total Amount: $" . $order['total_amount'] . "\n";
    $message .= "Status: " . ucfirst($order['status']) . "\n\n";
    $message .= "You can view your order details at: " . SITE_URL . "order-details.php?id=" . $order_id . "\n\n";
    $message .= "Thank you,\n" . SITE_NAME;
    
    $headers = "From: " . SITE_EMAIL . "\r\n";
    mail($to, $subject, $message, $headers);
    */
    
    // Add notification to database
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) 
                          VALUES (?, 'Order Confirmation', ?, 'success')");
    $message = "Order confirmation email sent for order #" . $order['order_number'];
    $stmt->execute([$order['user_id'], $message]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Order confirmation email sent to ' . $order['email']
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>