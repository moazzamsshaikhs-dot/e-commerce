<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once SITE_URL .'vendor/phpmailer/phpmailer/src/Exception.php';
require_once SITE_URL .'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once SITE_URL .'vendor/phpmailer/phpmailer/src/SMTP.php';

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
    
    $mail = new PHPMailer(true);
    
    // Server settings
    if (!empty($settings['smtp_host'])) {
        $mail->isSMTP();
        $mail->Host       = $settings['smtp_host'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['smtp_username'] ?? '';
        $mail->Password   = $settings['smtp_password'] ?? '';
        $mail->SMTPSecure = $settings['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $settings['smtp_port'] ?? 587;
        
        // Enable verbose debug output for testing (optional)
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    }
    
    // Recipients
    $mail->setFrom(
        $settings['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'],
        $settings['from_name'] ?? 'E-Commerce System'
    );
    $mail->addAddress($test_email);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from ' . $_SERVER['HTTP_HOST'];
    $mail->Body    = '<h2>SMTP Test Successful!</h2>
                     <p>Your email configuration is working correctly.</p>
                     <p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
    $mail->AltBody = 'SMTP Test Successful! Your email configuration is working correctly.';
    
    $mail->send();
    
    echo json_encode([
        'success' => true,
        'message' => 'Test email sent successfully to ' . $test_email
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send test email: ' . $e->getMessage()
    ]);
}