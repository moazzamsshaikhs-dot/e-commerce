<?php
require_once './includes/config.php';
require_once './includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('/dashboard.php');
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID.';
    redirect('users.php');
}

$user_id = (int)$_GET['id'];

// Prevent admin from deleting themselves
if ($user_id == $_SESSION['user_id']) {
    $_SESSION['error'] = 'You cannot delete your own account!';
    redirect('users.php');
}

try {
    // Get database connection
    $db = getDB();
    
    if (!$db) {
        throw new Exception("Database connection failed.");
    }
    
    // First, get user details for logging
    $stmt = $db->prepare("SELECT username, full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        redirect('users.php');
    }
    
    // Check if this is the last admin
    if ($user['user_type'] == 'admin') {
        $stmt = $db->prepare("SELECT COUNT(*) as admin_count FROM users WHERE user_type = 'admin' AND id != ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['admin_count'] == 0) {
            $_SESSION['error'] = 'Cannot delete the last administrator!';
            redirect('users.php');
        }
    }
    
    // Start transaction for data integrity
    $db->beginTransaction();
    
    try {
        // 1. Delete user activities
        $stmt = $db->prepare("DELETE FROM user_activities WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 2. Delete notifications
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 3. Delete wishlist items
        $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 4. Delete cart items
        $stmt = $db->prepare("DELETE FROM cart_items WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 5. Handle orders - set user_id to NULL instead of deleting
        $stmt = $db->prepare("UPDATE orders SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 6. Handle payments - set user_id to NULL instead of deleting
        $stmt = $db->prepare("UPDATE payments SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 7. Handle reviews - set user_id to NULL instead of deleting
        $stmt = $db->prepare("UPDATE reviews SET user_id = NULL WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 8. Delete user sessions
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 9. Delete login attempts
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$user['username']]);
        
        // 10. Delete password resets
        $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 11. Delete OTP verifications
        $stmt = $db->prepare("DELETE FROM otp_verification WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 12. Finally, delete the user
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Commit transaction
        $db->commit();
        
        // Log admin activity
        $activity_stmt = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description) 
            VALUES (?, 'user_deleted', ?)
        ");
        $description = "Admin deleted user: " . $user['username'] . " (" . $user['email'] . ")";
        $activity_stmt->execute([$_SESSION['user_id'], $description]);
        
        // Set success message
        $_SESSION['success'] = 'User "' . htmlspecialchars($user['full_name'] ?: $user['username']) . '" has been deleted successfully.';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database Error: ' . $e->getMessage();
    error_log("User deletion error: " . $e->getMessage());
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    error_log("User deletion error: " . $e->getMessage());
}

// Redirect back to users page
redirect('users.php');
?>