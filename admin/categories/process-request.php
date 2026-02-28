<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: ' . SITE_URL . 'admin/categories/index.php');
    exit();
}

$db = getDB();
$request_id = (int)($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';
$rejection_reason = trim($_POST['rejection_reason'] ?? '');

if (!$request_id || !in_array($action, ['approve', 'reject'])) {
    $_SESSION['error'] = 'Invalid request';
    header('Location: index.php');
    exit();
}

try {
    $db->beginTransaction();
    
    // Get request details
    $stmt = $db->prepare("
        SELECT ccr.*, 
               c.name as category_name,
               u.full_name as vendor_name,
               u.email as vendor_email
        FROM category_change_requests ccr
        JOIN categories c ON ccr.category_id = c.id
        JOIN users u ON ccr.vendor_id = u.id
        WHERE ccr.id = ?
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        throw new Exception('Request not found');
    }
    
    if ($action === 'approve') {
        // Update request status
        $stmt = $db->prepare("
            UPDATE category_change_requests 
            SET status = 'approved', processed_by = ?, processed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $request_id]);
        
        if ($request['request_type'] == 'use_category') {
            // Update vendor's category
            $stmt = $db->prepare("UPDATE users SET vendor_category = ? WHERE id = ?");
            $stmt->execute([$request['category_id'], $request['vendor_id']]);
            
            $message = "Your request to use category '{$request['category_name']}' has been approved.";
        } else {
            // Update commission rate in vendor_category_commissions
            $stmt = $db->prepare("
                INSERT INTO vendor_category_commissions (vendor_id, category_id, commission_rate, approved_by, approved_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    commission_rate = VALUES(commission_rate),
                    approved_by = VALUES(approved_by),
                    approved_at = VALUES(approved_at)
            ");
            $stmt->execute([
                $request['vendor_id'],
                $request['category_id'],
                $request['new_commission_rate'],
                $_SESSION['user_id']
            ]);
            
            $message = "Your commission rate change request for category '{$request['category_name']}' has been approved.";
        }
        
        // Create notification
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, 'Request Approved', ?, 'success', NOW())
        ");
        $stmt->execute([$request['vendor_id'], $message]);
        
        $_SESSION['success'] = 'Request approved successfully!';
        
    } else {
        // Reject request
        $stmt = $db->prepare("
            UPDATE category_change_requests 
            SET status = 'rejected', processed_by = ?, processed_at = NOW(), rejection_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $rejection_reason, $request_id]);
        
        // Create notification
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at)
            VALUES (?, 'Request Rejected', ?, 'error', NOW())
        ");
        $stmt->execute([$request['vendor_id'], "Your request was rejected. Reason: {$rejection_reason}"]);
        
        $_SESSION['success'] = 'Request rejected successfully!';
    }
    
    // Log activity
    logUserActivity($_SESSION['user_id'], 'category_request', 
                    "{$action}d request for category: {$request['category_name']}");
    
    $db->commit();
    
} catch(Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: index.php');
exit();