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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $webhook_url = filter_var($input['url'] ?? '', FILTER_SANITIZE_URL);
        $event = $input['event'] ?? 'order.created';
        
        if (empty($webhook_url) || !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid webhook URL');
        }
        
        // Create test payload based on event
        $payload = createTestPayload($event);
        $payload_json = json_encode($payload);
        
        // Send webhook
        $headers = [
            'Content-Type: application/json',
            'User-Agent: ShopEase-Webhook-Test/1.0',
            'X-Webhook-Event: ' . $event,
            'X-Webhook-Test: true',
            'X-Webhook-Timestamp: ' . date('c')
        ];
        
        $ch = curl_init($webhook_url);
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
        
        $success = ($http_code >= 200 && $http_code < 300) && empty($error);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Test webhook sent successfully!' : 'Failed to send test webhook',
            'data' => [
                'event' => $event,
                'http_code' => $http_code,
                'response_time' => round($response_time * 1000, 2) . 'ms',
                'error' => $error,
                'response_preview' => substr($response, 0, 200) . (strlen($response) > 200 ? '...' : '')
            ]
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function createTestPayload($event) {
    $base_payload = [
        'event' => $event,
        'timestamp' => date('c'),
        'test' => true,
        'test_id' => uniqid('test_'),
        'vendor_id' => $_SESSION['user_id'] ?? null
    ];
    
    switch($event) {
        case 'order.created':
            $base_payload['data'] = [
                'order_id' => 'ORD-' . rand(10000, 99999),
                'customer_email' => 'test@example.com',
                'total_amount' => 99.99,
                'currency' => 'USD',
                'items' => [
                    ['product_id' => 1, 'name' => 'Test Product', 'quantity' => 1, 'price' => 99.99]
                ],
                'shipping_address' => [
                    'name' => 'Test Customer',
                    'street' => '123 Test Street',
                    'city' => 'Test City',
                    'country' => 'Test Country'
                ]
            ];
            break;
            
        case 'payment.received':
            $base_payload['data'] = [
                'payment_id' => 'PAY-' . rand(10000, 99999),
                'order_id' => 'ORD-' . rand(10000, 99999),
                'amount' => 99.99,
                'currency' => 'USD',
                'payment_method' => 'card',
                'status' => 'completed',
                'customer_email' => 'test@example.com'
            ];
            break;
            
        case 'product.updated':
            $base_payload['data'] = [
                'product_id' => rand(1, 100),
                'name' => 'Updated Test Product',
                'price' => 49.99,
                'stock' => 100,
                'sku' => 'TEST-' . rand(1000, 9999),
                'category' => 'test',
                'updated_fields' => ['price', 'stock']
            ];
            break;
            
        default:
            $base_payload['data'] = [
                'message' => 'Test webhook payload',
                'details' => 'This is a test payload for event: ' . $event
            ];
    }
    
    return $base_payload;
}
?>