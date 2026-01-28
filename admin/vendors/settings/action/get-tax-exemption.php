<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$exemption_id = intval($_GET['id'] ?? 0);

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get exemption details
    $stmt = $db->prepare("SELECT * FROM vendor_tax_exemptions WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$exemption_id, $vendor_id]);
    $exemption = $stmt->fetch();
    
    if (!$exemption) {
        echo json_encode(['success' => false, 'message' => 'Tax exemption not found']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'exemption' => [
            'id' => $exemption['id'],
            'customer_name' => $exemption['customer_name'],
            'customer_email' => $exemption['customer_email'],
            'tax_number' => $exemption['tax_number'],
            'country' => $exemption['country'],
            'exemption_type' => $exemption['exemption_type'],
            'valid_from' => $exemption['valid_from'],
            'valid_to' => $exemption['valid_to'],
            'notes' => $exemption['notes']
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>