<?php
// includes/email-templates.php
// Complete Email Templates System for E-Commerce Platform with PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message HTML message
 * @param array $attachments Optional attachments
 * @return bool Success status
 */
function sendEmail($to, $subject, $message, $attachments = []) {
    // Load PHPMailer
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        if (getSetting('smtp_enabled', false)) {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host       = getSetting('smtp_host', 'smtp.gmail.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = getSetting('smtp_username', '');
            $mail->Password   = getSetting('smtp_password', '');
            $mail->SMTPSecure = getSetting('smtp_secure', PHPMailer::ENCRYPTION_STARTTLS);
            $mail->Port       = getSetting('smtp_port', 587);
            
            // Debug mode (optional)
            if (getSetting('smtp_debug', false)) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }
        } else {
            // Use PHP mail() function
            $mail->isMail();
        }
        
        // Sender and recipient
        $mail->setFrom(
            getSetting('from_email', 'noreply@' . parse_url(SITE_URL, PHP_URL_HOST)), 
            getSetting('from_name', SITE_NAME)
        );
        $mail->addAddress($to);
        
        // Reply-to
        $mail->addReplyTo(
            getSetting('reply_to_email', 'support@' . parse_url(SITE_URL, PHP_URL_HOST)),
            getSetting('reply_to_name', 'Customer Support')
        );
        
        // Attachments
        foreach ($attachments as $attachment) {
            if (file_exists($attachment)) {
                $mail->addAttachment($attachment);
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        
        // Send email
        return $mail->send();
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Get email template with header and footer
 * @param string $title Email title
 * @param string $content Main content
 * @param array $data Additional data
 * @return string Complete HTML email
 */
function getEmailTemplate($title, $content, $data = []) {
    $site_url = SITE_URL;
    $site_name = SITE_NAME;
    $current_year = date('Y');
    
    // Get logo URL
    $logo_url = $site_url . 'assets/images/logo.png';
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        /* Reset styles */
        body, table, td, p, a {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-color: #f4f7fc;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            padding: 30px 10px;
        }
        
        /* Container */
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        /* Header */
        .email-header {
            background: linear-gradient(135deg, #4361ee 0%, #4cc9f0 100%);
            padding: 30px 20px;
            text-align: center;
        }
        
        .email-header h1 {
            color: white;
            font-size: 24px;
            margin: 0;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .email-header p {
            color: rgba(255,255,255,0.9);
            margin: 10px 0 0;
            font-size: 14px;
        }
        
        /* Logo */
        .email-logo {
            margin-bottom: 20px;
        }
        
        .email-logo img {
            max-width: 150px;
            height: auto;
        }
        
        /* Content */
        .email-content {
            padding: 40px 30px;
            background-color: #ffffff;
        }
        
        .email-content h2 {
            color: #2b2d42;
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .email-content h3 {
            color: #2b2d42;
            font-size: 18px;
            margin: 25px 0 15px;
            font-weight: 600;
        }
        
        .email-content p {
            color: #4a5568;
            margin-bottom: 20px;
            font-size: 15px;
        }
        
        /* Info Box */
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #4361ee;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        
        .info-box table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .info-box td {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-box tr:last-child td {
            border-bottom: none;
        }
        
        .info-label {
            color: #6c757d;
            font-weight: 500;
            width: 40%;
        }
        
        .info-value {
            color: #2b2d42;
            font-weight: 600;
        }
        
        /* Button */
        .email-button {
            text-align: center;
            margin: 30px 0;
        }
        
        .email-button a {
            display: inline-block;
            background: linear-gradient(135deg, #4361ee 0%, #4cc9f0 100%);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
        }
        
        .email-button a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(67, 97, 238, 0.3);
        }
        
        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th {
            background-color: #f8f9fa;
            color: #2b2d42;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            padding: 12px 10px;
            border-bottom: 2px solid #e9ecef;
            text-align: left;
        }
        
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e9ecef;
            color: #4a5568;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .items-table .total-row {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        /* Footer */
        .email-footer {
            background-color: #f8f9fa;
            padding: 30px 20px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .email-footer p {
            color: #6c757d;
            font-size: 13px;
            margin: 5px 0;
        }
        
        .email-footer a {
            color: #4361ee;
            text-decoration: none;
        }
        
        .email-footer a:hover {
            text-decoration: underline;
        }
        
        .social-links {
            margin: 15px 0;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 5px;
            color: #6c757d;
            font-size: 18px;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cfe2ff; color: #084298; }
        .status-shipped { background: #cfe2ff; color: #084298; }
        .status-delivered { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-content {
                padding: 30px 20px;
            }
            
            .email-header h1 {
                font-size: 20px;
            }
            
            .info-box td {
                display: block;
                width: 100%;
                padding: 5px 0;
            }
            
            .info-label {
                width: 100%;
            }
            
            .items-table {
                font-size: 13px;
            }
            
            .items-table th,
            .items-table td {
                padding: 8px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="email-logo">
                <img src="{$logo_url}" alt="{$site_name}" 
                     onerror="this.style.display='none'">
            </div>
            <h1>{$title}</h1>
            <p>{$site_name}</p>
        </div>
        
        <!-- Content -->
        <div class="email-content">
            {$content}
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <div class="social-links">
                <a href="{$site_url}facebook"><i class="fab fa-facebook"></i></a>
                <a href="{$site_url}twitter"><i class="fab fa-twitter"></i></a>
                <a href="{$site_url}instagram"><i class="fab fa-instagram"></i></a>
                <a href="{$site_url}linkedin"><i class="fab fa-linkedin"></i></a>
            </div>
            <p>&copy; {$current_year} {$site_name}. All rights reserved.</p>
            <p>
                <a href="{$site_url}privacy">Privacy Policy</a> | 
                <a href="{$site_url}terms">Terms of Service</a> | 
                <a href="{$site_url}unsubscribe">Unsubscribe</a>
            </p>
            <p style="font-size: 11px;">
                This email was sent to you because you have an account with {$site_name}. 
                If you did not request this email, please ignore it.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Order Confirmation Email
 * @param array $order Order details
 * @param array $items Order items
 * @param array $customer Customer details
 * @return bool Success status
 */
function sendOrderConfirmation($order, $items, $customer) {
    $order_total = number_format($order['total_amount'], 2);
    $order_date = date('d M Y, h:i A', strtotime($order['order_date']));
    
    // Build items table
    $items_html = '';
    $subtotal = 0;
    foreach ($items as $item) {
        $item_total = $item['unit_price'] * $item['quantity'];
        $subtotal += $item_total;
        $items_html .= "
            <tr>
                <td>" . htmlspecialchars($item['name']) . "</td>
                <td>\$" . number_format($item['unit_price'], 2) . "</td>
                <td>{$item['quantity']}</td>
                <td>\$" . number_format($item_total, 2) . "</td>
            </tr>
        ";
    }
    
    $shipping = 5.99;
    $tax = $order['total_amount'] - $subtotal - $shipping;
    
    $content = "
        <h2>Thank You for Your Order!</h2>
        <p>Dear <strong>" . htmlspecialchars($customer['full_name']) . "</strong>,</p>
        <p>Your order has been successfully placed and is being processed. Here are your order details:</p>
        
        <div class='info-box'>
            <table>
                <tr>
                    <td class='info-label'>Order Number:</td>
                    <td class='info-value'>#{$order['order_number']}</td>
                </tr>
                <tr>
                    <td class='info-label'>Order Date:</td>
                    <td class='info-value'>{$order_date}</td>
                </tr>
                <tr>
                    <td class='info-label'>Payment Method:</td>
                    <td class='info-value'>" . strtoupper($order['payment_method']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Payment Status:</td>
                    <td class='info-value'>" . ucfirst($order['payment_status']) . "</td>
                </tr>
            </table>
        </div>
        
        <h3>Order Items</h3>
        <table class='items-table'>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                {$items_html}
                <tr>
                    <td colspan='3' style='text-align: right;'><strong>Subtotal:</strong></td>
                    <td><strong>\$" . number_format($subtotal, 2) . "</strong></td>
                </tr>
                <tr>
                    <td colspan='3' style='text-align: right;'>Shipping:</td>
                    <td>\$" . number_format($shipping, 2) . "</td>
                </tr>
                <tr>
                    <td colspan='3' style='text-align: right;'>Tax:</td>
                    <td>\$" . number_format($tax, 2) . "</td>
                </tr>
                <tr class='total-row'>
                    <td colspan='3' style='text-align: right;'><strong>Total:</strong></td>
                    <td><strong>\${$order_total}</strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class='info-box'>
            <h3 style='margin-top: 0;'>Shipping Address</h3>
            <p>" . nl2br(htmlspecialchars($order['shipping_address'])) . "</p>
            <h3 style='margin-top: 20px;'>Billing Address</h3>
            <p>" . nl2br(htmlspecialchars($order['billing_address'] ?? $order['shipping_address'])) . "</p>
        </div>
        
        <div class='email-button'>
            <a href='" . SITE_URL . "order-details.php?id={$order['id']}'>View Your Order</a>
        </div>
        
        <p style='font-size: 14px; color: #6c757d; margin-top: 30px;'>
            You will receive another email when your order ships. If you have any questions, 
            please contact our support team.
        </p>
    ";
    
    $html = getEmailTemplate('Order Confirmation', $content, ['order' => $order]);
    return sendEmail($customer['email'], "Order Confirmation #{$order['order_number']}", $html);
}

/**
 * Order Status Update Email
 * @param array $order Order details
 * @param string $old_status Previous status
 * @param string $new_status New status
 * @param array $customer Customer details
 * @return bool Success status
 */
function sendOrderStatusUpdate($order, $old_status, $new_status, $customer) {
    $status_icons = [
        'pending' => '⏳',
        'processing' => '⚙️',
        'shipped' => '🚚',
        'delivered' => '✅',
        'cancelled' => '❌'
    ];
    
    $content = "
        <h2>Order Status Updated</h2>
        <p>Dear <strong>" . htmlspecialchars($customer['full_name']) . "</strong>,</p>
        <p>The status of your order <strong>#{$order['order_number']}</strong> has been updated.</p>
        
        <div style='text-align: center; margin: 40px 0;'>
            <div style='display: inline-block; background: #f8f9fa; padding: 20px 40px; border-radius: 10px;'>
                <div style='font-size: 48px; margin-bottom: 10px;'>{$status_icons[$new_status]}</div>
                <div style='font-size: 18px; color: #2b2d42; margin-bottom: 5px;'>
                    Status Changed
                </div>
                <div style='display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap;'>
                    <span class='status-badge status-{$old_status}'>
                        " . ucfirst($old_status) . "
                    </span>
                    <i class='fas fa-arrow-right' style='color: #6c757d;'>→</i>
                    <span class='status-badge status-{$new_status}'>
                        " . ucfirst($new_status) . "
                    </span>
                </div>
            </div>
        </div>
        
        <div class='info-box'>
            <table>
                <tr>
                    <td class='info-label'>Order Number:</td>
                    <td class='info-value'>#{$order['order_number']}</td>
                </tr>
                <tr>
                    <td class='info-label'>New Status:</td>
                    <td class='info-value'>" . ucfirst($new_status) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Total Amount:</td>
                    <td class='info-value'>\$" . number_format($order['total_amount'], 2) . "</td>
                </tr>
            </table>
        </div>
        
        <div class='email-button'>
            <a href='" . SITE_URL . "order-details.php?id={$order['id']}'>Track Your Order</a>
        </div>
    ";
    
    $html = getEmailTemplate('Order Status Update', $content, ['order' => $order]);
    return sendEmail($customer['email'], "Order #{$order['order_number']} is now {$new_status}", $html);
}

/**
 * Shipping Confirmation Email
 * @param array $order Order details
 * @param array $customer Customer details
 * @return bool Success status
 */
function sendShippingConfirmation($order, $customer) {
    $tracking_link = '';
    if (!empty($order['tracking_number']) && !empty($order['tracking_url'])) {
        $tracking_link = "
            <div class='email-button'>
                <a href='{$order['tracking_url']}{$order['tracking_number']}'>Track Your Package</a>
            </div>
        ";
    }
    
    $content = "
        <h2>Your Order Has Shipped!</h2>
        <p>Dear <strong>" . htmlspecialchars($customer['full_name']) . "</strong>,</p>
        <p>Great news! Your order <strong>#{$order['order_number']}</strong> has been shipped and is on its way.</p>
        
        <div class='info-box'>
            <h3 style='margin-top: 0;'>Shipping Details</h3>
            <table>
                <tr>
                    <td class='info-label'>Shipping Method:</td>
                    <td class='info-value'>" . ucfirst($order['shipping_method']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Carrier:</td>
                    <td class='info-value'>" . ($order['carrier_name'] ?? 'Standard') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Tracking Number:</td>
                    <td class='info-value'><code>{$order['tracking_number']}</code></td>
                </tr>
                <tr>
                    <td class='info-label'>Estimated Delivery:</td>
                    <td class='info-value'>" . date('d M Y', strtotime('+5 days')) . "</td>
                </tr>
            </table>
        </div>
        
        {$tracking_link}
        
        <div class='info-box'>
            <h3 style='margin-top: 0;'>Shipping Address</h3>
            <p>" . nl2br(htmlspecialchars($order['shipping_address'])) . "</p>
        </div>
    ";
    
    $html = getEmailTemplate('Order Shipped', $content, ['order' => $order]);
    return sendEmail($customer['email'], "Your Order #{$order['order_number']} Has Shipped", $html);
}

/**
 * Invoice Email
 * @param array $order Order details
 * @param array $items Order items
 * @param array $customer Customer details
 * @param string $pdf_path Optional PDF attachment path
 * @return bool Success status
 */
function sendInvoice($order, $items, $customer, $pdf_path = null) {
    $order_total = number_format($order['total_amount'], 2);
    $order_date = date('d M Y', strtotime($order['order_date']));
    
    // Build items table
    $items_html = '';
    $subtotal = 0;
    foreach ($items as $item) {
        $item_total = $item['unit_price'] * $item['quantity'];
        $subtotal += $item_total;
        $items_html .= "
            <tr>
                <td>" . htmlspecialchars($item['name']) . "</td>
                <td>{$item['quantity']}</td>
                <td>\$" . number_format($item['unit_price'], 2) . "</td>
                <td>\$" . number_format($item_total, 2) . "</td>
            </tr>
        ";
    }
    
    $shipping = 5.99;
    $tax = $order['total_amount'] - $subtotal - $shipping;
    
    $content = "
        <h2>Your Invoice</h2>
        <p>Dear <strong>" . htmlspecialchars($customer['full_name']) . "</strong>,</p>
        <p>Thank you for your order. Please find your invoice below:</p>
        
        <div class='info-box'>
            <table>
                <tr>
                    <td class='info-label'>Invoice Number:</td>
                    <td class='info-value'>INV-" . str_pad($order['id'], 6, '0', STR_PAD_LEFT) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Order Number:</td>
                    <td class='info-value'>#{$order['order_number']}</td>
                </tr>
                <tr>
                    <td class='info-label'>Invoice Date:</td>
                    <td class='info-value'>{$order_date}</td>
                </tr>
                <tr>
                    <td class='info-label'>Due Date:</td>
                    <td class='info-value'>" . date('d M Y', strtotime('+7 days')) . "</td>
                </tr>
            </table>
        </div>
        
        <h3>Invoice Details</h3>
        <table class='items-table'>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                {$items_html}
                <tr>
                    <td colspan='3' style='text-align: right;'><strong>Subtotal:</strong></td>
                    <td><strong>\$" . number_format($subtotal, 2) . "</strong></td>
                </tr>
                <tr>
                    <td colspan='3' style='text-align: right;'>Shipping:</td>
                    <td>\$" . number_format($shipping, 2) . "</td>
                </tr>
                <tr>
                    <td colspan='3' style='text-align: right;'>Tax:</td>
                    <td>\$" . number_format($tax, 2) . "</td>
                </tr>
                <tr class='total-row'>
                    <td colspan='3' style='text-align: right;'><strong>Total:</strong></td>
                    <td><strong>\${$order_total}</strong></td>
                </tr>
            </tbody>
        </table>
        
        <div class='email-button'>
            <a href='" . SITE_URL . "invoice.php?id={$order['id']}'>Download PDF Invoice</a>
        </div>
    ";
    
    $html = getEmailTemplate('Invoice', $content, ['order' => $order]);
    
    $attachments = [];
    if ($pdf_path && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }
    
    return sendEmail($customer['email'], "Invoice for Order #{$order['order_number']}", $html, $attachments);
}

/**
 * Welcome Email for New Users
 * @param array $user User details
 * @param string $password Plain password (only for newly created accounts)
 * @return bool Success status
 */
function sendWelcomeEmail($user, $password = null) {
    $login_link = SITE_URL . 'login.php';
    
    $password_note = '';
    if ($password) {
        $password_note = "
            <div class='info-box' style='background: #fff3cd; border-left-color: #ffb703;'>
                <p style='margin: 0;'><strong>Temporary Password:</strong> <code>{$password}</code></p>
                <p style='margin: 10px 0 0; font-size: 13px;'>Please change your password after logging in.</p>
            </div>
        ";
    }
    
    $content = "
        <h2>Welcome to " . SITE_NAME . "!</h2>
        <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
        <p>Thank you for creating an account with us. We're excited to have you on board!</p>
        
        {$password_note}
        
        <div class='info-box'>
            <h3 style='margin-top: 0;'>Your Account Details</h3>
            <table>
                <tr>
                    <td class='info-label'>Username:</td>
                    <td class='info-value'>" . htmlspecialchars($user['username']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Email:</td>
                    <td class='info-value'>" . htmlspecialchars($user['email']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Account Type:</td>
                    <td class='info-value'>" . ucfirst($user['user_type']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Member Since:</td>
                    <td class='info-value'>" . date('d M Y') . "</td>
                </tr>
            </table>
        </div>
        
        <div class='email-button'>
            <a href='{$login_link}'>Login to Your Account</a>
        </div>
        
        <p>With your account, you can:</p>
        <ul style='color: #4a5568;'>
            <li>Track your orders</li>
            <li>View order history</li>
            <li>Manage your profile</li>
            <li>Save your favorite items</li>
        </ul>
    ";
    
    $html = getEmailTemplate('Welcome to ' . SITE_NAME, $content, ['user' => $user]);
    return sendEmail($user['email'], "Welcome to " . SITE_NAME, $html);
}

/**
 * Password Reset Email
 * @param array $user User details
 * @param string $reset_link Password reset link
 * @return bool Success status
 */
function sendPasswordResetEmail($user, $reset_link) {
    $content = "
        <h2>Password Reset Request</h2>
        <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
        <p>We received a request to reset your password. Click the button below to create a new password:</p>
        
        <div class='email-button'>
            <a href='{$reset_link}'>Reset Password</a>
        </div>
        
        <p style='margin-top: 30px;'>This link will expire in 1 hour for security reasons.</p>
        
        <div class='info-box' style='background: #fff3cd; border-left-color: #ffb703;'>
            <p style='margin: 0;'><strong>Didn't request this?</strong></p>
            <p style='margin: 10px 0 0; font-size: 13px;'>
                If you didn't request a password reset, please ignore this email or contact support.
            </p>
        </div>
    ";
    
    $html = getEmailTemplate('Password Reset Request', $content, ['user' => $user]);
    return sendEmail($user['email'], "Password Reset Request", $html);
}

/**
 * Vendor Verification Email
 * @param array $vendor Vendor details
 * @param string $status Verification status (approved/rejected)
 * @param string $reason Rejection reason (if rejected)
 * @return bool Success status
 */
function sendVendorVerificationEmail($vendor, $status, $reason = null) {
    if ($status === 'approved') {
        $content = "
            <h2>Vendor Account Approved!</h2>
            <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
            <p>Congratulations! Your vendor account has been approved. You can now start selling your products on " . SITE_NAME . ".</p>
            
            <div class='email-button'>
                <a href='" . SITE_URL . "vendor/dashboard.php'>Go to Vendor Dashboard</a>
            </div>
            
            <p>What you can do now:</p>
            <ul style='color: #4a5568;'>
                <li>Add your products</li>
                <li>Manage inventory</li>
                <li>View orders</li>
                <li>Track earnings</li>
            </ul>
        ";
    } else {
        $content = "
            <h2>Vendor Account Update</h2>
            <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
            <p>We regret to inform you that your vendor application has been rejected.</p>
            
            <div class='info-box' style='background: #fee2e2; border-left-color: #ef476f;'>
                <h3 style='margin-top: 0; color: #ef476f;'>Reason for Rejection</h3>
                <p style='margin: 0;'>" . nl2br(htmlspecialchars($reason)) . "</p>
            </div>
            
            <p>You can update your information and apply again.</p>
            
            <div class='email-button'>
                <a href='" . SITE_URL . "vendor/apply.php'>Apply Again</a>
            </div>
        ";
    }
    
    $html = getEmailTemplate('Vendor Account Update', $content, ['vendor' => $vendor]);
    return sendEmail($vendor['email'], "Vendor Account " . ucfirst($status), $html);
}

/**
 * Withdrawal Status Email
 * @param array $withdrawal Withdrawal details
 * @param array $vendor Vendor details
 * @param string $status Withdrawal status
 * @param string $transaction_id Transaction ID (if completed)
 * @return bool Success status
 */
function sendWithdrawalStatusEmail($withdrawal, $vendor, $status, $transaction_id = null) {
    $amount = number_format($withdrawal['request_amount'], 2);
    
    if ($status === 'approved') {
        $content = "
            <h2>Withdrawal Request Approved</h2>
            <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
            <p>Your withdrawal request of <strong>\${$amount}</strong> has been approved and is being processed.</p>
            
            <div class='info-box'>
                <table>
                    <tr>
                        <td class='info-label'>Request Amount:</td>
                        <td class='info-value'>\${$amount}</td>
                    </tr>
                    <tr>
                        <td class='info-label'>Withdrawal Method:</td>
                        <td class='info-value'>" . ucfirst($withdrawal['withdrawal_method']) . "</td>
                    </tr>
                    <tr>
                        <td class='info-label'>Status:</td>
                        <td class='info-value'>Processing</td>
                    </tr>
                </table>
            </div>
            
            <p>Your funds should arrive within 3-5 business days.</p>
        ";
    } elseif ($status === 'completed') {
        $content = "
            <h2>Withdrawal Completed</h2>
            <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
            <p>Your withdrawal request of <strong>\${$amount}</strong> has been successfully processed.</p>
            
            <div class='info-box'>
                <table>
                    <tr>
                        <td class='info-label'>Transaction ID:</td>
                        <td class='info-value'><code>{$transaction_id}</code></td>
                    </tr>
                    <tr>
                        <td class='info-label'>Amount:</td>
                        <td class='info-value'>\${$amount}</td>
                    </tr>
                    <tr>
                        <td class='info-label'>Processed On:</td>
                        <td class='info-value'>" . date('d M Y, h:i A') . "</td>
                    </tr>
                </table>
            </div>
        ";
    } else {
        $content = "
            <h2>Withdrawal Request Rejected</h2>
            <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
            <p>Your withdrawal request of <strong>\${$amount}</strong> has been rejected.</p>
            
            <div class='info-box' style='background: #fee2e2; border-left-color: #ef476f;'>
                <h3 style='margin-top: 0; color: #ef476f;'>Reason for Rejection</h3>
                <p style='margin: 0;'>" . nl2br(htmlspecialchars($withdrawal['rejection_reason'])) . "</p>
            </div>
            
            <p>Please update your information and submit a new request.</p>
        ";
    }
    
    $html = getEmailTemplate('Withdrawal Update', $content, ['withdrawal' => $withdrawal]);
    return sendEmail($vendor['email'], "Withdrawal Request " . ucfirst($status), $html);
}

/**
 * Document Verification Email
 * @param array $vendor Vendor details
 * @param string $document_type Document type
 * @param string $status Verification status
 * @param string $reason Rejection reason (if rejected)
 * @return bool Success status
 */
function sendDocumentVerificationEmail($vendor, $document_type, $status, $reason = null) {
    $doc_name = ucfirst(str_replace('_', ' ', $document_type));
    
    if ($status === 'approved') {
        $content = "
            <h2>Document Verified</h2>
            <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
            <p>Your <strong>{$doc_name}</strong> has been successfully verified.</p>
            
            <div class='info-box' style='background: #d1fae5; border-left-color: #06d6a0;'>
                <p style='margin: 0; color: #05b585;'>
                    <i class='fas fa-check-circle'></i> Your document has been approved.
                </p>
            </div>
        ";
    } else {
        $content = "
            <h2>Document Verification Failed</h2>
            <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
            <p>Your <strong>{$doc_name}</strong> could not be verified.</p>
            
            <div class='info-box' style='background: #fee2e2; border-left-color: #ef476f;'>
                <h3 style='margin-top: 0; color: #ef476f;'>Reason for Rejection</h3>
                <p style='margin: 0;'>" . nl2br(htmlspecialchars($reason)) . "</p>
            </div>
            
            <p>Please upload a new document with correct information.</p>
            
            <div class='email-button'>
                <a href='" . SITE_URL . "vendor/documents.php'>Upload New Document</a>
            </div>
        ";
    }
    
    $html = getEmailTemplate('Document Verification', $content, ['vendor' => $vendor]);
    return sendEmail($vendor['email'], "Document " . ucfirst($status), $html);
}

/**
 * Low Stock Notification Email
 * @param array $product Product details
 * @param array $vendor Vendor details
 * @return bool Success status
 */
function sendLowStockNotification($product, $vendor) {
    $content = "
        <h2>Low Stock Alert</h2>
        <p>Dear <strong>" . htmlspecialchars($vendor['full_name']) . "</strong>,</p>
        <p>Your product <strong>" . htmlspecialchars($product['name']) . "</strong> is running low on stock.</p>
        
        <div class='info-box' style='background: #fff3cd; border-left-color: #ffb703;'>
            <table>
                <tr>
                    <td class='info-label'>Product:</td>
                    <td class='info-value'>" . htmlspecialchars($product['name']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Current Stock:</td>
                    <td class='info-value' style='color: #ef476f;'>{$product['stock']} units</td>
                </tr>
                <tr>
                    <td class='info-label'>SKU:</td>
                    <td class='info-value'>" . ($product['sku'] ?? 'N/A') . "</td>
                </tr>
            </table>
        </div>
        
        <p>Please restock this product to avoid running out of inventory.</p>
        
        <div class='email-button'>
            <a href='" . SITE_URL . "vendor/products.php?id={$product['id']}'>Manage Product</a>
        </div>
    ";
    
    $html = getEmailTemplate('Low Stock Alert', $content, ['product' => $product]);
    return sendEmail($vendor['email'], "Low Stock Alert: {$product['name']}", $html);
}

/**
 * Contact Form Email
 * @param array $data Contact form data
 * @return bool Success status
 */
function sendContactFormEmail($data) {
    $admin_email = getSetting('admin_email', 'admin@' . parse_url(SITE_URL, PHP_URL_HOST));
    
    $content = "
        <h2>New Contact Form Submission</h2>
        <p>You have received a new message from the contact form:</p>
        
        <div class='info-box'>
            <table>
                <tr>
                    <td class='info-label'>Name:</td>
                    <td class='info-value'>" . htmlspecialchars($data['name']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Email:</td>
                    <td class='info-value'>" . htmlspecialchars($data['email']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Phone:</td>
                    <td class='info-value'>" . htmlspecialchars($data['phone'] ?? 'Not provided') . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Subject:</td>
                    <td class='info-value'>" . htmlspecialchars($data['subject']) . "</td>
                </tr>
                <tr>
                    <td class='info-label'>Date:</td>
                    <td class='info-value'>" . date('d M Y, h:i A') . "</td>
                </tr>
            </table>
        </div>
        
        <h3>Message</h3>
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px;'>
            " . nl2br(htmlspecialchars($data['message'])) . "
        </div>
        
        <div class='email-button'>
            <a href='mailto:" . htmlspecialchars($data['email']) . "'>Reply to {$data['name']}</a>
        </div>
    ";
    
    $html = getEmailTemplate('New Contact Form', $content, $data);
    return sendEmail($admin_email, "Contact Form: {$data['subject']}", $html);
}

/**
 * Newsletter Confirmation Email
 * @param string $email Subscriber email
 * @param string $confirm_link Confirmation link
 * @return bool Success status
 */
function sendNewsletterConfirmation($email, $confirm_link) {
    $content = "
        <h2>Confirm Your Subscription</h2>
        <p>Thank you for subscribing to our newsletter!</p>
        <p>Please click the button below to confirm your subscription:</p>
        
        <div class='email-button'>
            <a href='{$confirm_link}'>Confirm Subscription</a>
        </div>
        
        <p style='margin-top: 30px;'>If you didn't request this, please ignore this email.</p>
    ";
    
    $html = getEmailTemplate('Newsletter Subscription', $content);
    return sendEmail($email, "Please confirm your newsletter subscription", $html);
}

/**
 * Get setting value from database
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed Setting value
 */
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $settings = [];
            foreach ($result as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
            $settings = [];
        }
    }
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}
?>