<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Vendor account not approved.']);
        exit;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$action = $_POST['action'] ?? '';
$review_id = intval($_POST['review_id'] ?? 0);
$response_text = trim($_POST['response_text'] ?? '');
$is_public = isset($_POST['is_public']) ? 1 : 0;

try {
    // Verify review belongs to vendor's product
    $stmt = $db->prepare("
        SELECT r.id 
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        WHERE r.id = ? AND p.vendor_id = ?
    ");
    $stmt->execute([$review_id, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Review not found or access denied.']);
        exit;
    }
    
    // Check if response already exists
    $stmt = $db->prepare("SELECT id FROM vendor_responses WHERE review_id = ?");
    $stmt->execute([$review_id]);
    $existing_response = $stmt->fetch();
    
    if ($existing_response) {
        // Update existing response
        $stmt = $db->prepare("
            UPDATE vendor_responses 
            SET response_text = ?, is_public = ?, is_edited = 1, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$response_text, $is_public, $existing_response['id']]);
        
        // Log the update
        logUserActivity($_SESSION['user_id'], 'review_response_updated', 
                       "Updated response to review #{$review_id}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Response updated successfully!',
            'action' => 'updated'
        ]);
        
    } else {
        // Insert new response
        $stmt = $db->prepare("
            INSERT INTO vendor_responses (review_id, vendor_id, response_text, is_public, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$review_id, $_SESSION['user_id'], $response_text, $is_public]);
        $response_id = $db->lastInsertId();
        
        // Mark review as responded
        $stmt = $db->prepare("UPDATE reviews SET vendor_responded = 1 WHERE id = ?");
        $stmt->execute([$review_id]);
        
        // Log the action
        logUserActivity($_SESSION['user_id'], 'review_response_added', 
                       "Added response to review #{$review_id}");
        
        // Send notification to customer (if enabled)
        sendCustomerNotification($review_id, 'vendor_response');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Response sent successfully!',
            'action' => 'created',
            'response_id' => $response_id
        ]);
    }
    
} catch(PDOException $e) {
    error_log("Review Response Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Helper function to send notification
function sendCustomerNotification($review_id, $type) {
    // Implement notification logic here
    // This could be email, in-app notification, etc.
    return true;
}