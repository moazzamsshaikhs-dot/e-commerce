<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        echo json_encode(['success' => false, 'message' => 'Vendor account not approved.']);
        exit;
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
    exit;
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$review_id = intval($_POST['review_id'] ?? 0);
$action = $_POST['action'] ?? 'approve'; // 'approve' or 'unapprove'

if ($review_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID.']);
    exit;
}

try {
    // Verify review belongs to vendor's product
    $stmt = $db->prepare("
        SELECT r.id, r.product_id, p.name as product_name, u.username as customer_name
        FROM reviews r
        JOIN products p ON r.product_id = p.id
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ? AND p.vendor_id = ?
    ");
    $stmt->execute([$review_id, $_SESSION['user_id']]);
    $review = $stmt->fetch();
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Review not found or access denied.']);
        exit;
    }
    
    $new_status = ($action === 'approve') ? 1 : 0;
    $action_text = ($action === 'approve') ? 'approved' : 'unapproved';
    
    // Update review approval status
    $stmt = $db->prepare("UPDATE reviews SET is_approved = ? WHERE id = ?");
    $stmt->execute([$new_status, $review_id]);
    
    // Log the action
    logUserActivity($_SESSION['user_id'], 'review_' . $action, 
                   "{$action_text} review #{$review_id} for product: {$review['product_name']}");
    
    // Update product average rating if needed
    if ($action === 'approve') {
        updateProductRating($review['product_id']);
    }
    
    // Send notification to customer
    if (SEND_NOTIFICATIONS) {
        sendReviewNotification($review_id, $action);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => "Review {$action_text} successfully!",
        'action' => $action,
        'new_status' => $new_status
    ]);
    
} catch(PDOException $e) {
    error_log("Review Approval Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Helper function to update product average rating
function updateProductRating($product_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as review_count
            FROM reviews 
            WHERE product_id = ? AND is_approved = 1
        ");
        $stmt->execute([$product_id]);
        $stats = $stmt->fetch();
        
        // Update product table
        $stmt = $db->prepare("
            UPDATE products 
            SET average_rating = ?, review_count = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $stats['avg_rating'] ?? 0,
            $stats['review_count'] ?? 0,
            $product_id
        ]);
        
    } catch(PDOException $e) {
        error_log("Update Product Rating Error: " . $e->getMessage());
    }
}

// Helper function to send notification
function sendReviewNotification($review_id, $action) {
    global $db;
    
    try {
        // Get review details
        $stmt = $db->prepare("
            SELECT r.*, p.name as product_name, u.email, u.full_name
            FROM reviews r
            JOIN products p ON r.product_id = p.id
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$review_id]);
        $review = $stmt->fetch();
        
        if (!$review) return false;
        
        $subject = ($action === 'approve') 
            ? "Your Review Has Been Approved" 
            : "Your Review Status Changed";
        
        $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #f8f9fa; padding: 20px; border-radius: 5px; }
                    .content { padding: 20px; }
                    .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Review Status Update</h2>
                    </div>
                    <div class='content'>
                        <p>Hello {$review['full_name']},</p>
                        <p>Your review for <strong>{$review['product_name']}</strong> has been <strong>{$action}d</strong> by the vendor.</p>
                        
                        <p><strong>Your Review:</strong></p>
                        <blockquote style='background: #f8f9fa; padding: 15px; border-left: 4px solid #007bff;'>
                            {$review['review_text']}
                        </blockquote>
                        
                        <p>Rating: {$review['rating']}/5 stars</p>
                        
                        <p>
                            <a href='" . SITE_URL . "product.php?id={$review['product_id']}#reviews' class='button'>
                                View Product Reviews
                            </a>
                        </p>
                        
                        <p>Thank you for sharing your feedback!</p>
                        <p>Best regards,<br>The Vendor Team</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Send email using your email function
        // sendEmail($review['email'], $subject, $message);
        
        return true;
        
    } catch(PDOException $e) {
        error_log("Send Notification Error: " . $e->getMessage());
        return false;
    }
}
?>