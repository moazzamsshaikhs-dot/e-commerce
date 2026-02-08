<?php
// check-status.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
$product_id = $_GET['id'] ?? 0;
$response = ['success' => false];

try {
    $db = getDB();
    
    if ($product_id > 0) {
        // Check specific product status
        $stmt = $db->prepare("
            SELECT id, name, approved_status 
            FROM products 
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$product_id, $vendor_id]);
        
        if ($product = $stmt->fetch()) {
            // Get updated counts
            $counts = getProductCounts($db, $vendor_id);
            
            $response = [
                'success' => true,
                'product_id' => $product['id'],
                'name' => $product['name'],
                'status' => $product['approved_status'],
                'counts' => $counts
            ];
        } else {
            $response['message'] = 'Product not found';
        }
    } else {
        // Check for any status changes since last check
        $last_check = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as changes 
            FROM products 
            WHERE vendor_id = ? 
            AND approved_status != 'pending' 
            AND updated_at > ?
        ");
        $stmt->execute([$vendor_id, $last_check]);
        
        $has_changes = $stmt->fetch()['changes'] > 0;
        $counts = getProductCounts($db, $vendor_id);
        
        $response = [
            'success' => true,
            'has_changes' => $has_changes,
            'counts' => $counts
        ];
    }
} catch(PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Check Status Error: " . $e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($response);

function getProductCounts($db, $vendor_id) {
    $counts = [];
    
    // Pending count
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'pending'");
    $stmt->execute([$vendor_id]);
    $counts['pending'] = (int)$stmt->fetchColumn();
    
    // Approved count
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'approved'");
    $stmt->execute([$vendor_id]);
    $counts['approved'] = (int)$stmt->fetchColumn();
    
    // Rejected count
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'rejected'");
    $stmt->execute([$vendor_id]);
    $counts['rejected'] = (int)$stmt->fetchColumn();
    
    // Total count
    $counts['total'] = $counts['pending'] + $counts['approved'] + $counts['rejected'];
    
    return $counts;
}
?>