<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exemption_id = intval($_POST['exemption_id'] ?? 0);
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify exemption belongs to vendor
        $stmt = $db->prepare("SELECT id, customer_name FROM vendor_tax_exemptions WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$exemption_id, $vendor_id]);
        $exemption = $stmt->fetch();
        
        if (!$exemption) {
            echo json_encode(['success' => false, 'message' => 'Tax exemption not found']);
            exit;
        }
        
        // Delete exemption
        $stmt = $db->prepare("DELETE FROM vendor_tax_exemptions WHERE id = ?");
        $stmt->execute([$exemption_id]);
        
        // Log activity
        logActivity($vendor_id, 'tax_exemption', "Deleted tax exemption for: {$exemption['customer_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Tax exemption deleted']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>