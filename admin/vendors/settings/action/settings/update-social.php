<?php
// action/settings/update-social.php
session_start();
require_once '../../../../../includes/config.php';
require_once '../../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Update Social Links Started ===");

if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // Get form data
    $social_facebook = trim($_POST['social_facebook'] ?? '');
    $social_instagram = trim($_POST['social_instagram'] ?? '');
    $social_twitter = trim($_POST['social_twitter'] ?? '');
    $social_linkedin = trim($_POST['social_linkedin'] ?? '');
    $social_youtube = trim($_POST['social_youtube'] ?? '');
    $social_pinterest = trim($_POST['social_pinterest'] ?? '');
    
    // Format URLs
    $socials = [
        'facebook' => $social_facebook,
        'instagram' => $social_instagram,
        'twitter' => $social_twitter,
        'linkedin' => $social_linkedin,
        'youtube' => $social_youtube,
        'pinterest' => $social_pinterest
    ];
    
    foreach ($socials as $platform => $value) {
        if (!empty($value)) {
            // Remove any existing domain
            $value = preg_replace('/^(https?:\/\/)?(www\.)?(facebook|instagram|twitter|linkedin|youtube|pinterest)\.com\//', '', $value);
            $socials[$platform] = $value;
        }
    }
    
    error_log("Social data: " . print_r($socials, true));
    
    $db->beginTransaction();
    
    // Check if vendor_settings exists
    $stmt = $db->prepare("SELECT vendor_id FROM vendor_settings WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    
    if ($stmt->fetch()) {
        // Update
        $sql = "UPDATE vendor_settings SET 
                store_social_facebook = ?, store_social_instagram = ?,
                store_social_twitter = ?, store_social_linkedin = ?,
                store_social_youtube = ?, store_social_pinterest = ?,
                updated_at = NOW()
                WHERE vendor_id = ?";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $socials['facebook'], $socials['instagram'],
            $socials['twitter'], $socials['linkedin'],
            $socials['youtube'], $socials['pinterest'],
            $vendor_id
        ]);
    } else {
        // Insert
        $sql = "INSERT INTO vendor_settings 
                (vendor_id, store_social_facebook, store_social_instagram,
                 store_social_twitter, store_social_linkedin,
                 store_social_youtube, store_social_pinterest,
                 created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $vendor_id,
            $socials['facebook'], $socials['instagram'],
            $socials['twitter'], $socials['linkedin'],
            $socials['youtube'], $socials['pinterest']
        ]);
    }
    
    if (!$result) {
        throw new Exception('Failed to update social links');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'update_social', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Updated social media links", $ip, $ua]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Social media links updated successfully!'
    ]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>