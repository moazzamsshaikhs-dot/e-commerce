<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$rate_id = intval($_GET['id'] ?? 0);

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get rate details
    $stmt = $db->prepare("
        SELECT tr.*, tc.class_name 
        FROM vendor_tax_rates tr
        LEFT JOIN vendor_tax_classes tc ON tr.tax_class_id = tc.id
        WHERE tr.id = ? AND tr.vendor_id = ?
    ");
    $stmt->execute([$rate_id, $vendor_id]);
    $rate = $stmt->fetch();
    
    if (!$rate) {
        echo json_encode(['success' => false, 'message' => 'Tax rate not found']);
        exit;
    }
    
    // Get tax classes for dropdown
    $stmt = $db->prepare("SELECT id, class_name FROM vendor_tax_classes WHERE vendor_id = ? ORDER BY class_name");
    $stmt->execute([$vendor_id]);
    $classes = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'rate' => [
            'id' => $rate['id'],
            'tax_class_id' => $rate['tax_class_id'],
            'country' => $rate['country'],
            'state' => $rate['state'],
            'city' => $rate['city'],
            'postcode' => $rate['postcode'],
            'rate' => $rate['rate'],
            'rate_name' => $rate['rate_name'],
            'priority' => $rate['priority'],
            'compound' => $rate['compound'],
            'shipping' => $rate['shipping']
        ],
        'classes' => $classes
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>