<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zone_id = intval($_POST['zone_id'] ?? 0);
    $zone_name = trim($_POST['zone_name'] ?? '');
    $countries = $_POST['countries'] ?? [];
    $states = $_POST['states'] ?? [];
    $postal_codes = trim($_POST['postal_codes'] ?? '');
    $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Verify zone belongs to vendor
        $stmt = $db->prepare("SELECT id FROM vendor_shipping_zones WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$zone_id, $vendor_id]);
        $zone = $stmt->fetch();
        
        if (!$zone) {
            $_SESSION['error'] = 'Shipping zone not found';
            header('Location: ../shipping.php');
            exit;
        }
        
        if (empty($zone_name)) {
            throw new Exception('Zone name is required.');
        }
        
        $zone_data = json_encode([
            'countries' => $countries,
            'states' => $states,
            'postal_codes' => $postal_codes
        ]);
        
        // Update zone
        $stmt = $db->prepare("
            UPDATE vendor_shipping_zones 
            SET zone_name = ?, zone_data = ?, is_enabled = ?, updated_at = NOW()
            WHERE id = ? AND vendor_id = ?
        ");
        $stmt->execute([$zone_name, $zone_data, $is_enabled, $zone_id, $vendor_id]);
        
        // Log activity
        logActivity($vendor_id, 'shipping_zone', "Updated shipping zone: {$zone_name}");
        
        $_SESSION['success'] = 'Shipping zone updated successfully!';
        redirect(SITE_URL . 'admin/vendors/settings/shipping.php');
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        redirect(SITE_URL . 'admin/vendors/settings/shipping.php');
    }
}
?>