<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $webhook_id = intval($_POST['webhook_id'] ?? 0);
        
        if ($webhook_id <= 0) {
            throw new Exception('Invalid webhook ID');
        }
        
        // Get webhook details
        $stmt = $db->prepare("SELECT webhook_url, secret_key, webhook_name FROM vendor_webhooks WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$webhook_id, $vendor_id]);
        $webhook = $stmt->fetch();
        
        if (!$webhook) {
            throw new Exception('Webhook not found');
        }
        
        // Create test payload
        $payload = [
            'event' => 'test.webhook',
            'timestamp' => date('c'),
            'data' => [
                'test_id' => uniqid('test_'),
                'message' => 'This is a test webhook payload',
                'status' => 'test'
            ]
        ];
        
        $payload_json = json_encode($payload);
        
        // Calculate signature if secret key exists
        $headers = [
            'Content-Type: application/json',
            'User-Agent: ShopEase-Webhook/1.0'
        ];
        
        if (!empty($webhook['secret_key'])) {
            $signature = hash_hmac('sha256', $payload_json, $webhook['secret_key']);
            $headers[] = 'X-Webhook-Signature: sha256=' . $signature;
        }
        
        $headers[] = 'X-Webhook-Event: test.webhook';
        $headers[] = 'X-Webhook-Test: true';
        
        // Send webhook using cURL
        $ch = curl_init($webhook['webhook_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $response_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        // Determine if successful
        $success = ($http_code >= 200 && $http_code < 300) && empty($error);
        
        // Log the test
        $stmt = $db->prepare("
            INSERT INTO vendor_webhook_logs 
            (vendor_id, webhook_id, event, payload, response, status_code, 
             response_time, success, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $webhook_id,
            'test.webhook',
            $payload_json,
            $response,
            $http_code,
            $response_time,
            $success ? 1 : 0,
            $error
        ]);
        
        // Update webhook last delivery info
        if ($success) {
            $stmt = $db->prepare("
                UPDATE vendor_webhooks 
                SET last_delivered = NOW(),
                    delivery_success = 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$webhook_id]);
        }
        
        // Log activity
        logVendorActivity($vendor_id, 'test_webhook', "Tested webhook #$webhook_id - {$webhook['webhook_name']}");
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Test webhook sent successfully!' : 'Failed to send test webhook',
            'data' => [
                'http_code' => $http_code,
                'response_time' => round($response_time * 1000, 2) . 'ms',
                'error' => $error
            ]
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function logVendorActivity($vendor_id, $activity_type, $description) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_activities 
            (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $activity_type,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch(Exception $e) {
        // Silently fail logging
    }
}
?>