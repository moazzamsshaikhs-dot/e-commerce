<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rate_id = intval($_POST['rate_id'] ?? 0);
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify rate belongs to vendor
        $stmt = $db->prepare("SELECT id, rate_name FROM vendor_tax_rates WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$rate_id, $vendor_id]);
        $rate = $stmt->fetch();
        
        if (!$rate) {
            echo json_encode(['success' => false, 'message' => 'Tax rate not found']);
            exit;
        }
        
        // Delete rate
        $stmt = $db->prepare("DELETE FROM vendor_tax_rates WHERE id = ?");
        $stmt->execute([$rate_id]);
        
        // Log activity
        logActivity($vendor_id, 'tax_rate', "Deleted tax rate: {$rate['rate_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Tax rate deleted']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>