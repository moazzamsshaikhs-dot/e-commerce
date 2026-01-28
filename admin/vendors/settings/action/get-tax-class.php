<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$class_id = intval($_GET['id'] ?? 0);

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get class details
    $stmt = $db->prepare("SELECT * FROM vendor_tax_classes WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$class_id, $vendor_id]);
    $class = $stmt->fetch();
    
    if (!$class) {
        echo json_encode(['success' => false, 'message' => 'Tax class not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'class' => [
            'id' => $class['id'],
            'class_name' => $class['class_name'],
            'class_description' => $class['class_description'],
            'sort_order' => $class['sort_order'],
            'is_active' => $class['is_active']
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>