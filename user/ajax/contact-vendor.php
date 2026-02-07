<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

try {
    $db = getDB();
    
    $vendor_id = (int)$_POST['vendor_id'];
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : null;
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    // Get user info
    $stmt = $db->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Get vendor info
    $stmt = $db->prepare("SELECT email, store_email FROM users u LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id WHERE u.id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Save message
    $stmt = $db->prepare("
        INSERT INTO vendor_messages (
            user_id, vendor_id, product_id, subject, message, status, created_at
        ) VALUES (?, ?, ?, ?, ?, 'unread', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $vendor_id, $product_id, $subject, $message]);
    
    // Send email to vendor
    $vendor_email = $vendor['store_email'] ?? $vendor['email'];
    $email_subject = "New Message: $subject";
    $email_body = "
        <h2>New Customer Message</h2>
        <p><strong>From:</strong> {$user['full_name']} ({$user['email']})</p>
        <p><strong>Subject:</strong> $subject</p>
        <p><strong>Message:</strong></p>
        <div style='background:#f8f9fa; padding:15px; border-radius:5px;'>
            $message
        </div>
        <hr>
        <p>Please respond to this message within 24 hours.</p>
    ";
    
    sendEmail($vendor_email, $email_subject, $email_body);
    
    logUserActivity($_SESSION['user_id'], 'vendor_contact', 'Contacted vendor #' . $vendor_id);
    
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
    
} catch (PDOException $e) {
    error_log("Contact Vendor Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error sending message']);
}
?>