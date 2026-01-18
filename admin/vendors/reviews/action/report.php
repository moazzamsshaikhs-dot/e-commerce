<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to report reviews.']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$review_id = intval($_POST['review_id'] ?? 0);
$report_reason = trim($_POST['reason'] ?? '');
$additional_info = trim($_POST['additional_info'] ?? '');

if ($review_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID.']);
    exit;
}

if (empty($report_reason)) {
    echo json_encode(['success' => false, 'message' => 'Please select a report reason.']);
    exit;
}

try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    // Check if review exists
    $stmt = $db->prepare("
        SELECT r.*, u.username as author_name, p.name as product_name
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN products p ON r.product_id = p.id
        WHERE r.id = ?
    ");
    $stmt->execute([$review_id]);
    $review = $stmt->fetch();
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Review not found.']);
        exit;
    }
    
    // Check if user already reported this review
    $stmt = $db->prepare("
        SELECT id FROM review_reports 
        WHERE review_id = ? AND user_id = ?
    ");
    $stmt->execute([$review_id, $user_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'You already reported this review.']);
        exit;
    }
    
    // Check if review is own review
    if ($review['user_id'] == $user_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot report your own review.']);
        exit;
    }
    
    // Add report
    $stmt = $db->prepare("
        INSERT INTO review_reports 
        (review_id, user_id, reason, additional_info, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->execute([$review_id, $user_id, $report_reason, $additional_info]);
    $report_id = $db->lastInsertId();
    
    // Update review report count
    $stmt = $db->prepare("
        UPDATE reviews 
        SET report_count = report_count + 1 
        WHERE id = ?
    ");
    $stmt->execute([$review_id]);
    
    // Log activity
    logUserActivity($user_id, 'review_reported', 
                   "Reported review #{$review_id} by {$review['author_name']}. Reason: {$report_reason}");
    
    // Notify admin and vendor
    notifyReport($review_id, $report_id, $user_id, $report_reason);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Review reported successfully. Our team will review it.',
        'report_id' => $report_id
    ]);
    
} catch(PDOException $e) {
    error_log("Review Report Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Helper function to notify admin and vendor
function notifyReport($review_id, $report_id, $reporter_id, $reason) {
    global $db, $review;
    
    try {
        // Get reporter details
        $stmt = $db->prepare("SELECT username, full_name FROM users WHERE id = ?");
        $stmt->execute([$reporter_id]);
        $reporter = $stmt->fetch();
        
        // Notify admins
        $stmt = $db->prepare("SELECT id FROM users WHERE user_type = 'admin' AND account_status = 'active'");
        $stmt->execute();
        $admins = $stmt->fetchAll();
        
        foreach ($admins as $admin) {
            $stmt = $db->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, is_read, created_at)
                VALUES (?, ?, ?, 'warning', 0, NOW())
            ");
            
            $title = "Review Reported";
            $message = "Review #{$review_id} on '{$review['product_name']}' was reported by {$reporter['full_name']}. Reason: {$reason}";
            
            $stmt->execute([$admin['id'], $title, $message]);
        }
        
        // Notify vendor (if not the reporter)
        if ($review['vendor_id'] && $review['vendor_id'] != $reporter_id) {
            $stmt = $db->prepare("
                INSERT INTO notifications 
                (user_id, title, message, type, is_read, created_at)
                VALUES (?, ?, ?, 'warning', 0, NOW())
            ");
            
            $title = "Your Product Review Reported";
            $message = "A review on your product '{$review['product_name']}' was reported.";
            
            $stmt->execute([$review['vendor_id'], $title, $message]);
        }
        
        return true;
        
    } catch(PDOException $e) {
        error_log("Report Notification Error: " . $e->getMessage());
        return false;
    }
}
?>