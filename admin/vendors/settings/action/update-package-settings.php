<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    redirect(SITE_URL . 'admin/vendors/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weight_unit = $_POST['weight_unit'] ?? 'kg';
    $dimension_unit = $_POST['dimension_unit'] ?? 'cm';
    $default_weight = floatval($_POST['default_weight'] ?? 1);
    $default_length = floatval($_POST['default_length'] ?? 30);
    $default_width = floatval($_POST['default_width'] ?? 20);
    $default_height = floatval($_POST['default_height'] ?? 10);
    $packaging_instructions = trim($_POST['packaging_instructions'] ?? '');
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        $package_dimensions = json_encode([
            'weight_unit' => $weight_unit,
            'dimension_unit' => $dimension_unit,
            'default_weight' => $default_weight,
            'default_length' => $default_length,
            'default_width' => $default_width,
            'default_height' => $default_height
        ]);
        
        // Update or insert package settings
        $stmt = $db->prepare("
            INSERT INTO vendor_shipping_settings 
            (vendor_id, package_dimensions, packaging_instructions, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            package_dimensions = ?, packaging_instructions = ?, updated_at = NOW()
        ");
        $stmt->execute([
            $vendor_id, $package_dimensions, $packaging_instructions,
            $package_dimensions, $packaging_instructions
        ]);
        
        // Log activity
        logActivity($vendor_id, 'shipping_settings', 'Updated package settings');
        
        $_SESSION['success'] = 'Package settings updated successfully!';
        header('Location: ../shipping.php#packaging');
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../shipping.php#packaging');
    }
}
?>