<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

header('Content-Type: application/json');

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$vendor_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $email_type = $input['type'] ?? 'order';
        $email_address = $input['email'] ?? $_SESSION['email'] ?? '';
        
        if (empty($email_address)) {
            throw new Exception('Email address is required');
        }
        
        // Get vendor details
        $db = getDB();
        $stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ?");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch();
        
        if (!$vendor) {
            throw new Exception('Vendor not found');
        }
        
        // Create test email content based on type
        $email_data = createTestEmailContent($email_type, $vendor, $email_address);
        
        // Send email using your email function
        $sent = sendEmailNotification($email_address, $email_data['subject'], $email_data['content']);
        
        if ($sent) {
            // Log the test email
            $stmt = $db->prepare("
                INSERT INTO notification_logs 
                (vendor_id, notification_type, recipient, subject, status, created_at)
                VALUES (?, ?, ?, ?, 'sent', NOW())
            ");
            $stmt->execute([$vendor_id, 'test_email', $email_address, $email_data['subject']]);
            
            // Log activity
            logVendorActivity($vendor_id, 'send_test_email', "Sent test email ($email_type) to $email_address");
            
            echo json_encode([
                'success' => true,
                'message' => 'Test email sent successfully!',
                'data' => [
                    'type' => $email_type,
                    'recipient' => $email_address,
                    'subject' => $email_data['subject']
                ]
            ]);
        } else {
            throw new Exception('Failed to send email');
        }
        
    } catch(Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function createTestEmailContent($type, $vendor, $email) {
    $vendor_name = $vendor['full_name'] ?: $vendor['username'];
    $current_date = date('d M Y, h:i A');
    
    switch($type) {
        case 'order':
            return [
                'subject' => 'Test: New Order Received - Order #TEST-001',
                'content' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; text-align: center;">
                            <h1 style="color: white; margin: 0;">New Order Received!</h1>
                        </div>
                        <div style="padding: 30px; background: #f8f9fa;">
                            <h2>Hello ' . htmlspecialchars($vendor_name) . ',</h2>
                            <p>This is a test notification for a new order.</p>
                            
                            <div style="background: white; border-radius: 10px; padding: 20px; margin: 20px 0;">
                                <h3>Order Details</h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Order ID:</strong></td>
                                        <td style="padding: 10px; border-bottom: 1px solid #eee;">TEST-001</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Customer:</strong></td>
                                        <td style="padding: 10px; border-bottom: 1px solid #eee;">Test Customer</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Amount:</strong></td>
                                        <td style="padding: 10px; border-bottom: 1px solid #eee;">$99.99</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px;"><strong>Date:</strong></td>
                                        <td style="padding: 10px;">' . $current_date . '</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div style="text-align: center; margin-top: 30px;">
                                <a href="#" style="background: #0d6efd; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                                    View Order Details
                                </a>
                            </div>
                            
                            <p style="margin-top: 30px; color: #666; font-size: 14px;">
                                <em>This is a test email. No action is required.</em>
                            </p>
                        </div>
                        <div style="background: #343a40; color: white; padding: 20px; text-align: center; font-size: 12px;">
                            <p>© ' . date('Y') . ' Your Store Name. All rights reserved.</p>
                            <p>This is a test email sent to ' . htmlspecialchars($email) . '</p>
                        </div>
                    </div>
                '
            ];
            
        case 'review':
            return [
                'subject' => 'Test: New Review Received - Rating: 5 Stars',
                'content' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; text-align: center;">
                            <h1 style="color: white; margin: 0;">New Review Received!</h1>
                        </div>
                        <div style="padding: 30px; background: #f8f9fa;">
                            <h2>Hello ' . htmlspecialchars($vendor_name) . ',</h2>
                            <p>This is a test notification for a new product review.</p>
                            
                            <div style="background: white; border-radius: 10px; padding: 20px; margin: 20px 0;">
                                <h3>Review Details</h3>
                                
                                <div style="margin: 15px 0;">
                                    <strong>Product:</strong> Test Product<br>
                                    <strong>Rating:</strong> ⭐⭐⭐⭐⭐ (5/5)<br>
                                    <strong>Reviewer:</strong> Test Customer<br>
                                    <strong>Date:</strong> ' . $current_date . '
                                </div>
                                
                                <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                                    <strong>Review:</strong><br>
                                    "This is a test review for demonstration purposes. The product was excellent!"
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 30px;">
                                <a href="#" style="background: #198754; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                                    View Review
                                </a>
                            </div>
                        </div>
                    </div>
                '
            ];
            
        case 'payment':
            return [
                'subject' => 'Test: Payment Received - $99.99',
                'content' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 20px; text-align: center;">
                            <h1 style="color: white; margin: 0;">Payment Received!</h1>
                        </div>
                        <div style="padding: 30px; background: #f8f9fa;">
                            <h2>Hello ' . htmlspecialchars($vendor_name) . ',</h2>
                            <p>This is a test notification for a payment received.</p>
                            
                            <div style="background: white; border-radius: 10px; padding: 20px; margin: 20px 0; text-align: center;">
                                <div style="font-size: 48px; color: #198754; margin: 20px 0;">
                                    $99.99
                                </div>
                                <div style="font-size: 18px; color: #666;">
                                    Payment successfully received
                                </div>
                            </div>
                            
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Transaction ID:</strong></td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">TXN-TEST-001</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Order ID:</strong></td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">ORD-TEST-001</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>Payment Method:</strong></td>
                                    <td style="padding: 10px; border-bottom: 1px solid #eee;">Credit Card</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px;"><strong>Date:</strong></td>
                                    <td style="padding: 10px;">' . $current_date . '</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                '
            ];
            
        case 'report':
            return [
                'subject' => 'Test: Weekly Sales Report - ' . date('M d, Y'),
                'content' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); padding: 20px; text-align: center;">
                            <h1 style="color: white; margin: 0;">Weekly Sales Report</h1>
                        </div>
                        <div style="padding: 30px; background: #f8f9fa;">
                            <h2>Hello ' . htmlspecialchars($vendor_name) . ',</h2>
                            <p>This is a test weekly sales report for demonstration.</p>
                            
                            <div style="background: white; border-radius: 10px; padding: 20px; margin: 20px 0;">
                                <h3>Sales Summary (Last 7 Days)</h3>
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr style="background: #f8f9fa;">
                                        <td style="padding: 10px;"><strong>Total Orders:</strong></td>
                                        <td style="padding: 10px; text-align: right;"><strong>15</strong></td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px;">Total Revenue:</td>
                                        <td style="padding: 10px; text-align: right;">$1,499.85</td>
                                    </tr>
                                    <tr style="background: #f8f9fa;">
                                        <td style="padding: 10px;">Total Earnings:</td>
                                        <td style="padding: 10px; text-align: right;">$1,049.90</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 10px;">Products Sold:</td>
                                        <td style="padding: 10px; text-align: right;">25</td>
                                    </tr>
                                    <tr style="background: #f8f9fa;">
                                        <td style="padding: 10px;">New Customers:</td>
                                        <td style="padding: 10px; text-align: right;">8</td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div style="text-align: center; margin-top: 30px;">
                                <a href="#" style="background: #6f42c1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                                    View Detailed Report
                                </a>
                            </div>
                        </div>
                    </div>
                '
            ];
            
        default:
            return [
                'subject' => 'Test Notification from Your Store',
                'content' => '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                        <div style="background: #0d6efd; padding: 20px; text-align: center;">
                            <h1 style="color: white; margin: 0;">Test Notification</h1>
                        </div>
                        <div style="padding: 30px; background: #f8f9fa;">
                            <h2>Hello ' . htmlspecialchars($vendor_name) . ',</h2>
                            <p>This is a test email notification to verify your email settings.</p>
                            <p>If you received this email, your notification settings are working correctly.</p>
                            <div style="text-align: center; margin: 30px 0;">
                                <div style="background: white; padding: 20px; border-radius: 10px; display: inline-block;">
                                    <strong>Email:</strong> ' . htmlspecialchars($email) . '<br>
                                    <strong>Time:</strong> ' . $current_date . '
                                </div>
                            </div>
                        </div>
                    </div>
                '
            ];
    }
}

function sendEmailNotification($to, $subject, $content) {
    // Use your existing email sending function
    // This is a placeholder - implement with your email system
    try {
        // For testing, just log the email
        error_log("Test Email: To: $to, Subject: $subject");
        return true;
    } catch(Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        return false;
    }
}

function logVendorActivity($vendor_id, $activity_type, $description) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_activities 
            (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $vendor_id,
            $activity_type,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch(Exception $e) {
        // Silently fail logging
    }
}
?>