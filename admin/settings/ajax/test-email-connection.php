<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$test_email = $input['email'] ?? '';

if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Valid email address required']);
    exit;
}

try {
    $db = getDB();
    
    // Get email settings
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $subject = "Test Email from E-Commerce System";
    $message = "<h2>Test Email</h2>";
    $message .= "<p>This is a test email to verify your email configuration.</p>";
    $message .= "<p>Sent at: " . date('Y-m-d H:i:s') . "</p>";
    $message .= "<p>From: " . ($settings['from_name'] ?? 'E-Commerce System') . "</p>";
    
    $from_email = $settings['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'];
    $from_name = $settings['from_name'] ?? 'E-Commerce System';
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: $from_name <$from_email>\r\n";
    
    if (mail($test_email, $subject, $message, $headers)) {
        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully to ' . $test_email
        ]);
    } else {
        $error = error_get_last()['message'] ?? 'Unknown error';
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send test email: ' . $error
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}