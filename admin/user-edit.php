<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('../index.php');
}
// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID.';
    redirect('users.php');
}
$user_id = (int)$_GET['id'];
try {
    // Get database connection
    $db = getDB();
    
    if (!$db) {
        throw new Exception("Database connection failed.");
    }
    
    // Fetch user details
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        redirect('users.php');
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database Error: ' . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}
// Redirect back to users page
redirect('users.php');
?>
