<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$plan_slug = $data['plan_slug'] ?? '';

try {
    switch ($action) {
        case 'upgrade':
            // Get plan details
            $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE slug = ?");
            $stmt->execute([$plan_slug]);
            $plan = $stmt->fetch();
            
            if (!$plan) {
                throw new Exception('Plan not found');
            }
            
            // Calculate expiry date
            $expiry_date = date('Y-m-d', strtotime("+" . $plan['duration_days'] . " days"));
            
            // Update user subscription
            $stmt = $db->prepare("
                UPDATE users 
                SET subscription_plan = ?, subscription_expiry = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            
            // Extract plan name from slug (e.g., 'premium-plan' -> 'premium')
            $plan_name = str_replace('-plan', '', $plan_slug);
            
            $stmt->execute([$plan_name, $expiry_date, $user_id]);
            
            // Update session
            $_SESSION['subscription_plan'] = $plan_name;
            
            logUserActivity($user_id, 'plan_upgrade', 'Upgraded to ' . $plan_name . ' plan');
            echo json_encode(['success' => true, 'message' => 'Plan upgraded successfully']);
            break;
            
        case 'downgrade':
            // Update user to free plan
            $stmt = $db->prepare("
                UPDATE users 
                SET subscription_plan = 'free', subscription_expiry = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            
            // Update session
            $_SESSION['subscription_plan'] = 'free';
            
            logUserActivity($user_id, 'plan_downgrade', 'Downgraded to free plan');
            echo json_encode(['success' => true, 'message' => 'Plan downgraded successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Upgrade AJAX Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}