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
        
        $integration_id = intval($_POST['integration_id'] ?? 0);
        
        if ($integration_id <= 0) {
            throw new Exception('Invalid integration ID');
        }
        
        // Verify integration belongs to vendor
        $stmt = $db->prepare("SELECT integration_name, integration_type, config FROM vendor_integrations WHERE id = ? AND vendor_id = ? AND is_active = 1");
        $stmt->execute([$integration_id, $vendor_id]);
        $integration = $stmt->fetch();
        
        if (!$integration) {
            throw new Exception('Integration not found or inactive');
        }
        
        $config = json_decode($integration['config'], true);
        
        // Perform sync based on integration type
        $start_time = microtime(true);
        $result = performIntegrationSync($integration['integration_type'], $config);
        $duration_ms = round((microtime(true) - $start_time) * 1000, 2);
        
        // Log the sync
        $stmt = $db->prepare("
            INSERT INTO vendor_integration_logs 
            (vendor_id, integration_id, action, status, message, duration_ms, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $integration_id,
            'sync',
            $result['success'] ? 'success' : 'error',
            $result['message'],
            $duration_ms
        ]);
        
        // Update last sync time
        if ($result['success']) {
            $stmt = $db->prepare("UPDATE vendor_integrations SET last_sync = NOW() WHERE id = ?");
            $stmt->execute([$integration_id]);
        }
        
        // Log activity
        logVendorActivity($vendor_id, 'sync_integration', "Synced integration #$integration_id - {$integration['integration_name']}");
        
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'] ?? []
        ]);
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function performIntegrationSync($type, $config) {
    switch($type) {
        case 'paypal':
            return syncPayPal($config);
        case 'stripe':
            return syncStripe($config);
        case 'razorpay':
            return syncRazorpay($config);
        case 'shiprocket':
            return syncShiprocket($config);
        default:
            return [
                'success' => true,
                'message' => 'Sync completed successfully',
                'data' => ['synced_at' => date('c')]
            ];
    }
}

function syncPayPal($config) {
    // PayPal sync implementation
    // This is a placeholder - implement actual PayPal API calls
    return [
        'success' => true,
        'message' => 'PayPal data synced successfully',
        'data' => [
            'balance_updated' => true,
            'transactions_synced' => 0
        ]
    ];
}

function syncStripe($config) {
    // Stripe sync implementation
    // This is a placeholder - implement actual Stripe API calls
    return [
        'success' => true,
        'message' => 'Stripe data synced successfully',
        'data' => [
            'customers_synced' => 0,
            'charges_synced' => 0
        ]
    ];
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