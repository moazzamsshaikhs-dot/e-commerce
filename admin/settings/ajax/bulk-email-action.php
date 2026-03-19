<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$ids = $input['ids'] ?? [];

if (empty($ids) || !is_array($ids)) {
    echo json_encode(['success' => false, 'message' => 'No items selected']);
    exit;
}

// Sanitize IDs
$ids = array_map('intval', $ids);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $db = getDB();
    
    switch ($action) {
        case 'delete':
            $stmt = $db->prepare("DELETE FROM email_logs WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $message = count($ids) . ' log(s) deleted successfully';
            break;
            
        case 'resend':
            // Get emails to resend
            $stmt = $db->prepare("SELECT * FROM email_logs WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $success_count = 0;
            $fail_count = 0;
            
            // Get email settings
            $settings = [];
            $settings_stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'");
            while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            $from_email = $settings['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST'];
            $from_name = $settings['from_name'] ?? 'E-Commerce System';
            
            foreach ($logs as $log) {
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: $from_name <$from_email>\r\n";
                
                if (mail($log['recipient_email'], $log['subject'], $log['message'], $headers)) {
                    $success_count++;
                    $update = $db->prepare("UPDATE email_logs SET status = 'sent', sent_at = NOW() WHERE id = ?");
                    $update->execute([$log['id']]);
                } else {
                    $fail_count++;
                }
            }
            
            $message = "$success_count email(s) resent successfully, $fail_count failed";
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}