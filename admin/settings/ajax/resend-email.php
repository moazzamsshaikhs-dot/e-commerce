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
$email_id = isset($input['email_id']) ? (int)$input['email_id'] : 0;

if ($email_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid email ID']);
    exit;
}

try {
    $db = getDB();
    
    // Get email log details
    $stmt = $db->prepare("SELECT * FROM email_logs WHERE id = ?");
    $stmt->execute([$email_id]);
    $email_log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$email_log) {
        echo json_encode(['success' => false, 'message' => 'Email log not found']);
        exit;
    }
    
    // Get email settings from database
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
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
        $mail->addAddress($email_log['recipient_email'], $email_log['recipient_name'] ?? '');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $email_log['subject'];
        $mail->Body    = $email_log['message'];
        $mail->AltBody = strip_tags($email_log['message']);
        
        $mail->send();
        
        // Update log status
        $update = $db->prepare("UPDATE email_logs SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $update->execute([$email_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Email resent successfully'
        ]);
        
    } catch (Exception $e) {
        // Update with error
        $update = $db->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?");
        $update->execute([$e->getMessage(), $email_id]);
        
        echo json_encode([
            'success' => false,
            'message' => 'Failed to resend email: ' . $e->getMessage()
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}