<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Load PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once '../../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once '../../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once '../../../vendor/phpmailer/phpmailer/src/SMTP.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$template_id = $input['template_id'] ?? 0;
$test_email = $input['email'] ?? '';

if ($template_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
    exit;
}

if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Valid email address required']);
    exit;
}

try {
    $db = getDB();
    
    // Get template details
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        echo json_encode(['success' => false, 'message' => 'Template not found']);
        exit;
    }
    
    // Get email settings
    $settings = [];
    $settings_stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'");
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Prepare email content (replace variables with sample data)
    $subject = $template['subject'];
    $content = $template['content'];
    
    // Sample variables for testing
    $variables = [
        '{{site_name}}' => 'ShopEase Pro',
        '{{site_url}}' => 'https://shopeasepro.com',
        '{{user_name}}' => 'Test User',
        '{{user_email}}' => $test_email,
        '{{order_number}}' => 'ORD-2024-001',
        '{{order_total}}' => '$99.99',
        '{{reset_link}}' => 'https://shopeasepro.com/reset-password?token=test123',
        '{{current_date}}' => date('F j, Y'),
        '{{current_year}}' => date('Y')
    ];
    
    foreach ($variables as $key => $value) {
        $subject = str_replace($key, $value, $subject);
        $content = str_replace($key, $value, $content);
    }
    
    // Create new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        if (!empty($settings['smtp_host'])) {
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_username'] ?? '';
            $mail->Password   = $settings['smtp_password'] ?? '';
            $mail->SMTPSecure = $settings['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $settings['smtp_port'] ?? 587;
        }
        
        // Recipients
        $mail->setFrom(
            $settings['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'],
            $settings['from_name'] ?? 'E-Commerce System'
        );
        $mail->addAddress($test_email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $content;
        $mail->AltBody = strip_tags($content);
        
        $mail->send();
        
        // Log the test email
        $log_stmt = $db->prepare("
            INSERT INTO email_logs (template_key, recipient_email, subject, message, status, sent_at, created_at)
            VALUES (?, ?, ?, ?, 'sent', NOW(), NOW())
        ");
        $log_stmt->execute([$template['template_key'], $test_email, $subject, $content]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully to ' . $test_email
        ]);
        
    } catch (Exception $e) {
        // Log failed attempt
        $log_stmt = $db->prepare("
            INSERT INTO email_logs (template_key, recipient_email, subject, message, status, error_message, created_at)
            VALUES (?, ?, ?, ?, 'failed', ?, NOW())
        ");
        $log_stmt->execute([$template['template_key'], $test_email, $subject, $content, $e->getMessage()]);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send test email: ' . $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}