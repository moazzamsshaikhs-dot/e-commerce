<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = intval($_POST['class_id'] ?? 0);
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify class belongs to vendor and is not default
        $stmt = $db->prepare("SELECT id, class_name FROM vendor_tax_classes WHERE id = ? AND vendor_id = ? AND is_default = 0");
        $stmt->execute([$class_id, $vendor_id]);
        $class = $stmt->fetch();
        
        if (!$class) {
            echo json_encode(['success' => false, 'message' => 'Tax class not found or cannot delete default class']);
            exit;
        }
        
        // Check if class has tax rates
        $stmt = $db->prepare("SELECT COUNT(*) FROM vendor_tax_rates WHERE tax_class_id = ?");
        $stmt->execute([$class_id]);
        $rate_count = $stmt->fetchColumn();
        
        if ($rate_count > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete tax class with associated tax rates']);
            exit;
        }
        
        // Delete class
        $stmt = $db->prepare("DELETE FROM vendor_tax_classes WHERE id = ?");
        $stmt->execute([$class_id]);
        
        // Log activity
        logActivity($vendor_id, 'tax_class', "Deleted tax class: {$class['class_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Tax class deleted']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>