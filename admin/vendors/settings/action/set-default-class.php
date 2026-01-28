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
        
        // Verify class belongs to vendor
        $stmt = $db->prepare("SELECT id, class_name FROM vendor_tax_classes WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$class_id, $vendor_id]);
        $class = $stmt->fetch();
        
        if (!$class) {
            echo json_encode(['success' => false, 'message' => 'Tax class not found']);
            exit;
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        // Remove default from all classes
        $stmt = $db->prepare("UPDATE vendor_tax_classes SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        
        // Set new default class
        $stmt = $db->prepare("UPDATE vendor_tax_classes SET is_default = 1 WHERE id = ?");
        $stmt->execute([$class_id]);
        
        $db->commit();
        
        // Log activity
        logActivity($vendor_id, 'tax_class', "Set default tax class: {$class['class_name']}");
        
        echo json_encode(['success' => true, 'message' => 'Default tax class updated']);
        
    } catch(PDOException $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>