<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$category_id = (int)($_GET['id'] ?? 0);

if (!$category_id) {
    $_SESSION['error'] = 'Invalid category';
    header('Location: profile.php');
    exit();
}

$db = getDB();

try {
    // Check if vendor has this category approved
    $stmt = $db->prepare("
        SELECT vcs.*, c.name, c.slug 
        FROM vendor_categories_selected vcs
        JOIN categories c ON vcs.category_id = c.id
        WHERE vcs.vendor_id = ? AND vcs.category_id = ? AND vcs.status = 'approved'
    ");
    $stmt->execute([$vendor_id, $category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        $_SESSION['error'] = 'Category not approved for you';
        header('Location: profile.php');
        exit();
    }
    
    // Set as active in users table
    $stmt = $db->prepare("UPDATE users SET vendor_category = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$category_id, $vendor_id]);
    
    $_SESSION['success'] = "Active category changed to: {$category['name']}";
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("
        INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
        VALUES (?, 'category_change', ?, ?, ?, NOW())
    ");
    $stmt->execute([$vendor_id, "Changed active category to: {$category['name']}", $ip, $ua]);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

redirect('profile.php');
exit();