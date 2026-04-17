<?php
// Database Configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_USER', 'u124468513_Moazzam');
define('DB_PASS', '');
define('DB_NAME', 'u124468513_ecommerce_db');

// Site Configuration
define('SITE_URL', 'https://shopeasepro.com/');
define('SITE_NAME', 'ShopEase Pro');

// Email Configuration
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USER', 'shopeasepro2@shopeasepro.com');
define('SMTP_PASS', '');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');

// Email Addresses
define('FROM_EMAIL', 'shopeasepro2@shopeasepro.com');
define('FROM_NAME', 'ShopEase Pro');
define('ADMIN_EMAIL', 'shopeasepro2@gmail.com');

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_LENGTH', 6);

// Add this to your config.php
define('ENABLE_SECURITY_CHECKS', false); // Set to true for production
define('SESSION_TIMEOUT_MINUTES', 30);
// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');

// Start Session with proper settings
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Karachi');

// Database Connection Function
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Check if user is vendor
function isVendor() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor';
}

// Check if user is regular user
function isUser() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user';
}

// Redirect function - FIXED to prevent loops
function redirect($url) {
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Remove SITE_URL prefix if already present
    $url = ltrim($url, '/');
    if (strpos($url, 'http') !== 0) {
        $url = SITE_URL . $url;
    }
    
    header('Location: ' . $url);
    exit();
}

// Redirect to dashboard based on user type - FIXED to prevent loops
function redirectToDashboard() {
    if (!isLoggedIn()) {
        redirect('login.php');
        return;
    }
    
    $user_type = $_SESSION['user_type'] ?? 'user';
    
    switch ($user_type) {
        case 'admin':
            redirect('admin/dashboard.php');
            break;
        case 'vendor':
            redirect('admin/vendors/dashboard.php');
            break;
        default:
            redirect('user/dashboard.php');
            break;
    }
}

// Safe HTML function
function safe_html($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Password functions
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate OTP
function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

// Generate secure token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Get User IP
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Log user activity
function logUserActivity($user_id, $activity_type, $description) {
    try {
        $db = getDB();
        $ip = getUserIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $activity_type, $description, $ip, $user_agent]);
        return true;
    } catch(PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}


// Send OTP SMS
function sendOTPSMS($phone, $otp) {
    error_log("SMS OTP for $phone: $otp");
    return true;
}

// Create user session
function createUserSession($user_id, $session_token, $ip, $user_agent) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $session_token, $ip, $user_agent]);
        return true;
    } catch(PDOException $e) {
        error_log("Session creation failed: " . $e->getMessage());
        return false;
    }
}

// Security functions
function trackLoginAttempt($username, $ip, $success) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO login_attempts (username, ip_address, success, attempted_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $ip, $success ? 1 : 0]);
        return true;
    } catch(PDOException $e) {
        return false;
    }
}

function isIPBlocked($ip) {
    // Simplified - return false for now
    return false;
}


function isSubscriptionActive($user_id) {
    return true; // Simplified
}


// Add missing columns to users table - Run this SQL
function ensureDatabaseColumns() {
    try {
        $db = getDB();
        
        // Check and add email_verified column
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT DEFAULT 0 AFTER email");
        }
        
        // Check and add account_status column
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'account_status'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN account_status VARCHAR(20) DEFAULT 'active' AFTER user_type");
        }
        
        // Check and add vendor_status column
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'vendor_status'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN vendor_status VARCHAR(20) DEFAULT NULL AFTER user_type");
        }
        
        // Check and add vendor_category column
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'vendor_category'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN vendor_category VARCHAR(100) DEFAULT NULL AFTER vendor_status");
        }
        
        // Check and add vendor_bio column
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'vendor_bio'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN vendor_bio TEXT DEFAULT NULL AFTER vendor_category");
        }
        
        // Check and add login_count column
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'login_count'");
        if ($stmt->rowCount() == 0) {
            $db->exec("ALTER TABLE users ADD COLUMN login_count INT DEFAULT 0 AFTER last_login");
        }
        
        error_log("Database columns checked/added successfully");
    } catch(PDOException $e) {
        error_log("Database column check failed: " . $e->getMessage());
    }
}

// Run database column check
ensureDatabaseColumns();
// Generate CSRF Token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF Token
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sendOTPEmail($email, $otp, $name = '') {
    $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
    
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($email, $name ?: $email);
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code - ' . SITE_NAME;
            $mail->Body = "
            <div style='font-family:Poppins,sans-serif;max-width:500px;margin:0 auto;padding:30px;background:#f8f9fa;border-radius:15px;'>
                <div style='text-align:center;margin-bottom:25px;'>
                    <h2 style='color:#4361ee;'>🔐 Email Verification</h2>
                    <p style='color:#888;'>" . SITE_NAME . "</p>
                </div>
                <div style='background:white;padding:30px;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.05);text-align:center;'>
                    <p style='color:#333;font-size:16px;margin-bottom:5px;'>Hello <strong>" . htmlspecialchars($name ?: $email) . "</strong>,</p>
                    <p style='color:#666;font-size:15px;'>Your One-Time Password (OTP) for email verification is:</p>
                    <div style='background:linear-gradient(135deg,#4361ee,#3a0ca3);color:white;font-size:38px;font-weight:900;letter-spacing:14px;padding:22px 30px;border-radius:12px;margin:25px 0;display:inline-block;'>
                        {$otp}
                    </div>
                    <p style='color:#888;font-size:14px;'>⏰ Valid for <strong>" . OTP_EXPIRY_MINUTES . " minutes</strong> only</p>
                    <div style='background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-top:15px;'>
                        <p style='color:#856404;font-size:13px;margin:0;'>⚠️ Do NOT share this OTP with anyone. Our team will never ask for it.</p>
                    </div>
                </div>
                <p style='text-align:center;color:#aaa;font-size:12px;margin-top:20px;'>
                    If you didn't request this, please ignore this email.<br>
                    &copy; " . date('Y') . " " . SITE_NAME . " - All Rights Reserved
                </p>
            </div>";
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer OTP Error for {$email}: " . $e->getMessage());
            error_log("FALLBACK OTP for {$email}: {$otp}");
            return false;
        }
    } else {
        error_log("DEV OTP for {$email}: {$otp}");
        return true;
    }
}

// Send OTP via SMS (Simulated)


// JSON Response Helper
function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Format Date
function formatDate($date, $format = 'd M Y h:i A') {
    return date($format, strtotime($date));
}


// End user session
function endUserSession($session_token) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE user_sessions 
            SET logout_time = NOW(), is_active = 0 
            WHERE session_token = ?
        ");
        $stmt->execute([$session_token]);
        return true;
    } catch(PDOException $e) {
        error_log("Session ending failed: " . $e->getMessage());
        return false;
    }
}


// Validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
    }
    
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (PASSWORD_REQUIRE_SYMBOL && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}


// Get user subscription details
function getUserSubscription($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.subscription_plan, u.subscription_expiry, p.* 
            FROM users u
            LEFT JOIN subscription_plans p ON p.name COLLATE utf8mb4_unicode_ci = u.subscription_plan COLLATE utf8mb4_unicode_ci
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Get subscription failed: " . $e->getMessage());
        return null;
    }
}

// Get subscription plans
function getSubscriptionPlans() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC");
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Get plans failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Send Security Alert Email
 */
function sendSecurityAlert($user_id, $alert_type, $details = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        $subject = "Security Alert - " . SITE_NAME;
        $message = "Hello " . $user['full_name'] . ",\n\n";
        
        switch($alert_type) {
            case 'login':
                $message .= "New login detected on your account.\n";
                $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
                $message .= "IP Address: " . getUserIP() . "\n";
                $message .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n\n";
                $message .= "If this was not you, please change your password immediately.\n";
                break;
                
            case 'password_change':
                $message .= "Your password has been changed successfully.\n";
                $message .= "Time: " . date('Y-m-d H:i:s') . "\n\n";
                $message .= "If you did not make this change, please contact support immediately.\n";
                break;
                
            case 'profile_update':
                $message .= "Your profile information has been updated.\n";
                $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
                $message .= "Details: " . $details . "\n\n";
                break;
                
            case 'logout':
                $message .= "You have been logged out successfully.\n";
                $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
                if ($details) $message .= "Details: " . $details . "\n";
                break;
                
            default:
                $message .= "Security event: " . $alert_type . "\n";
                $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
                if ($details) $message .= "Details: " . $details . "\n";
                break;
        }
        
        $message .= "\nThank you,\n" . SITE_NAME . " Security Team";
        
        error_log("Security Alert for {$user['email']}: {$alert_type}");
        
        // Send email using mail() function
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
        mail($user['email'], $subject, $message, $headers);
        
        return true;
    } catch(PDOException $e) {
        error_log("Security alert failed: " . $e->getMessage());
        return false;
    }
}

// config.php mein ye functions add karein:

/**
 * Send Vendor Registration Alert to Admin
 */
function sendVendorRegistrationAlert($vendor_id) {
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT username, email, full_name, vendor_category FROM users WHERE id = ?");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch();
        
        if ($vendor) {
            $stmt = $db->prepare("SELECT email, id FROM users WHERE user_type = 'admin'");
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            $subject = "New Vendor Registration - {$vendor['username']}";
            $message = "
            <html>
            <head><title>New Vendor Registration</title></head>
            <body>
                <h2>New Vendor Registration</h2>
                <p>A new vendor has registered on the platform:</p>
                <table cellpadding='5' cellspacing='0' border='0'>
                    <tr><td><strong>Vendor:</strong></td><td>{$vendor['full_name']}</td></tr>
                    <tr><td><strong>Username:</strong></td><td>{$vendor['username']}</td></tr>
                    <tr><td><strong>Email:</strong></td><td>{$vendor['email']}</td></tr>
                    <tr><td><strong>Category:</strong></td><td>{$vendor['vendor_category']}</td></tr>
                </table>
                <p>Please review and approve/reject this vendor from the admin panel.</p>
                <hr>
                <p><small>Sent from " . SITE_NAME . " system</small></p>
            </body>
            </html>";
            
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
            
            foreach ($admins as $admin) {
                mail($admin['email'], $subject, $message, $headers);
                
                $stmt2 = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at) 
                    VALUES (?, 'New Vendor Registration', ?, 'info', NOW())
                ");
                $stmt2->execute([
                    $admin['id'],
                    "New vendor {$vendor['username']} has registered and is waiting for approval."
                ]);
            }
        }
    } catch(PDOException $e) {
        error_log("Vendor registration alert error: " . $e->getMessage());
    }
}


// Check if vendor is approved
function isVendorApproved() {
    if (!isVendor()) return false;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
        
        return $status === 'approved';
    } catch(PDOException $e) {
        return false;
    }
}

/**
 * Send Invoice Email to Customer
 */
function sendInvoiceEmail($invoice_id, $invoice_number, $customer_email, $customer_name, $total_amount) {
    $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
    
    if (!file_exists($vendor_autoload)) {
        error_log("PHPMailer not found - cannot send invoice email");
        return false;
    }
    
    require_once $vendor_autoload;
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($customer_email, $customer_name);
        $mail->addReplyTo(ADMIN_EMAIL, FROM_NAME);
        
        $mail->isHTML(true);
        $mail->Subject = 'Invoice #' . $invoice_number . ' from ' . SITE_NAME;
        
        $invoice_link = SITE_URL . 'admin/view-invoice.php?id=' . $invoice_id;
        $payment_link = SITE_URL . 'payment.php?invoice=' . $invoice_number;
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; padding: 20px; background: #4361ee; color: white; border-radius: 10px 10px 0 0; }
                .content { padding: 30px; background: #f8f9fa; }
                .invoice-details { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
                .amount { font-size: 24px; color: #4361ee; font-weight: bold; }
                .button { display: inline-block; padding: 12px 24px; background: #4361ee; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; border-top: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>" . SITE_NAME . "</h2>
                </div>
                <div class='content'>
                    <h3>Dear {$customer_name},</h3>
                    <p>Thank you for your business! Please find your invoice details below.</p>
                    
                    <div class='invoice-details'>
                        <h4>Invoice #{$invoice_number}</h4>
                        <p><strong>Date:</strong> " . date('F d, Y') . "</p>
                        <p><strong>Due Date:</strong> " . date('F d, Y', strtotime('+30 days')) . "</p>
                        <p><strong>Total Amount:</strong> <span class='amount'>PKR " . number_format($total_amount, 2) . "</span></p>
                    </div>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$invoice_link}' class='button'>View Invoice</a>
                        <a href='{$payment_link}' class='button' style='background: #06d6a0;'>Pay Now</a>
                    </div>
                    
                    <p>Best regards,<br>" . SITE_NAME . " Team</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->AltBody = "Dear {$customer_name},\n\nInvoice #{$invoice_number} has been created.\n\nTotal Amount: PKR " . number_format($total_amount, 2) . "\n\nView Invoice: {$invoice_link}\nPay Now: {$payment_link}\n\nBest regards,\n" . SITE_NAME . " Team";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Invoice email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}



// Send PHPMailer OTP Email (real implementation)
function sendOTPEmailReal($email, $otp, $name = '') {
    $vendor_autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendor_autoload)) {
        // Fallback to basic mail
        error_log("OTP for $email: $otp");
        return true;
    }
    
    require_once $vendor_autoload;
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        $mail->setFrom(SMTP_USER, SITE_NAME);
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = 'Your OTP Code - ' . SITE_NAME;
        $mail->Body = "
        <div style='font-family:Poppins,sans-serif;max-width:500px;margin:0 auto;padding:30px;background:#f8f9fa;border-radius:15px;'>
            <div style='text-align:center;margin-bottom:25px;'>
                <h2 style='color:#4361ee;'>🔐 Email Verification</h2>
            </div>
            <div style='background:white;padding:25px;border-radius:10px;text-align:center;'>
                <p style='color:#666;font-size:16px;'>Hello <strong>" . htmlspecialchars($name ?: $email) . "</strong>,</p>
                <p style='color:#666;'>Your One-Time Password (OTP) for " . SITE_NAME . " is:</p>
                <div style='background:#4361ee;color:white;font-size:36px;font-weight:bold;letter-spacing:12px;padding:20px;border-radius:10px;margin:20px 0;'>
                    {$otp}
                </div>
                <p style='color:#999;font-size:14px;'>This OTP is valid for <strong>" . OTP_EXPIRY_MINUTES . " minutes</strong></p>
                <p style='color:#dc3545;font-size:13px;'>⚠️ Do not share this OTP with anyone.</p>
            </div>
            <p style='text-align:center;color:#999;font-size:12px;margin-top:20px;'>
                If you didn't request this, please ignore this email.<br>
                &copy; " . date('Y') . " " . SITE_NAME . "
            </p>
        </div>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error for $email: " . $e->getMessage());
        error_log("OTP for $email: $otp"); // fallback log
        return false;
    }
}


?>