<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

use PHPMailer\PHPMailer\PHPMailer;
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
$email_ids = $input['email_ids'] ?? [];

if (empty($email_ids) || !is_array($email_ids)) {
    echo json_encode(['success' => false, 'message' => 'No emails selected']);
    exit;
}

// Sanitize IDs
$email_ids = array_map('intval', $email_ids);
$placeholders = implode(',', array_fill(0, count($email_ids), '?'));

try {
    $db = getDB();
    
    // Get email logs
    $stmt = $db->prepare("SELECT * FROM email_logs WHERE id IN ($placeholders)");
    $stmt->execute($email_ids);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get email settings
    $settings = [];
    $settings_stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'");
    while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $success_count = 0;
    $fail_count = 0;
    $errors = [];
    
    foreach ($logs as $log) {
        try {
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
            }
            
            // Recipients
            $mail->setFrom(
                $settings['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'],
                $settings['from_name'] ?? 'E-Commerce System'
            );
            $mail->addAddress($log['recipient_email'], $log['recipient_name'] ?? '');
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $log['subject'];
            $mail->Body    = $log['message'];
            $mail->AltBody = strip_tags($log['message']);
            
            $mail->send();
            
            // Update log status
            $update = $db->prepare("UPDATE email_logs SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $update->execute([$log['id']]);
            
            $success_count++;
            
        } catch (Exception $e) {
            $fail_count++;
            $errors[] = "Email ID {$log['id']}: " . $e->getMessage();
            
            // Update with error
            $update = $db->prepare("UPDATE email_logs SET status = 'failed', error_message = ? WHERE id = ?");
            $update->execute([$e->getMessage(), $log['id']]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "$success_count email(s) resent successfully, $fail_count failed",
        'success_count' => $success_count,
        'fail_count' => $fail_count,
        'errors' => $errors
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}