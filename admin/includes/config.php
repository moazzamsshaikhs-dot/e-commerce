<?php
// Database Configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default password is empty
// define('DB_NAME', 'icei_41344581_ecommerce_db');
define('DB_NAME','ecommerce_db');

// Site Configuration
define('SITE_URL', 'http://localhost/e-commerce/');
// define('SITE_URL', 'https://shopeasepro.iceiy.com/e-commerce/');
define('SITE_NAME', 'ShopEase Pro');
// define('SITE_URL', 'http://yourdomain.com/'); // With trailing slash
// Email Configuration (for OTP sending)
define('SMTP_HOST', 'shopeasepro2@gmail.com');
define('SMTP_USER', 'shopeasepro2@gmail.com');
define('SMTP_PASS', 'shopeasepro2@gmail.com');
define('SMTP_PORT', 587);

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

// File Upload Configuration
define('UPLOAD_PATHS', $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('THUMBNAIL_WIDTH', 150);
define('THUMBNAIL_HEIGHT', 150);
define('WATERMARK_TEXT', 'ShopEase Pro');
define('NOTIFICATION_EMAIL', 'admin@shopease.com');
define('SUPPORT_EMAIL', 'support@shopease.com');
define('SALES_EMAIL', 'sales@shopease.com');
define('NO_REPLY_EMAIL', 'no-reply@shopease.com');
define('SEND_NOTIFICATIONS', 'true');
define('SITE_PHONE', '03132842740');

// config.php میں یہ شامل کریں
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/'); // یا آپ کے پروجیکٹ کا صحیح راستہ
define('UPLOAD_PATH', ROOT_PATH . 'assets/images/products/');
define('UPLOAD_URL', SITE_URL . 'assets/images/products/');
// Current version (you should define this in config.php)
define('CURRENT_VERSION', '1.0.0');

// Start Session

function secureSessionStart() {
    $session_name = 'secure_session';
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $httponly = true;
    
    session_name($session_name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Strict'
    ]);    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
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

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}
function isVendor() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor';
}
// Function to check if user is regular user
function isUser() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user';
}

// Redirect function
function redirect($url) {
    header('Location: ' . SITE_URL . $url);
    exit();
}


// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

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

// Generate OTP
function generateOTP($length = 6) {
    $digits = '0123456789';
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= $digits[rand(0, 9)];
    }
    return $otp;
}

// Send OTP via Email (Simulated)
function sendOTPEmail($email, $otp) {
    // In production, use PHPMailer or similar
    $subject = "Your OTP Code - " . SITE_NAME;
    $message = "Your OTP code is: $otp\nValid for " . OTP_EXPIRY_MINUTES . " minutes";
    $headers = "From: no-reply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    
    // For demo, we'll just log it
    error_log("OTP for $email: $otp");
    
    // Uncomment to actually send email
   return mail($email, $subject, $message, $headers);
    
    return true; // For demo purposes
}


// Send OTP via SMS (Simulated)
function sendOTPSMS($phone, $otp) {
    // In production, use Twilio or similar service
    error_log("SMS OTP for $phone: $otp");
    return true; // For demo purposes
}

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

// Get User IP Address
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// Password Hash
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify Password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}


// ... پہلے والا کوڈ ...

// Security Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT_MINUTES', 15);
define('SESSION_TIMEOUT_MINUTES', 30);
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SYMBOL', true);

// ... موجودہ کوڈ کے بعد یہ فنکشنز شامل کریں ...

// Track login attempt
function trackLoginAttempt($username, $ip, $success) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO login_attempts (username, ip_address, success) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$username, $ip, $success]);
        return true;
    } catch(PDOException $e) {
        error_log("Login attempt tracking failed: " . $e->getMessage());
        return false;
    }
}

// Check if IP is blocked
function isIPBlocked($ip) {
    try {
        $db = getDB();
        $time_threshold = date('Y-m-d H:i:s', strtotime('-' . LOGIN_TIMEOUT_MINUTES . ' minutes'));
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND success = 0 
            AND attempted_at > ?
        ");
        $stmt->execute([$ip, $time_threshold]);
        $result = $stmt->fetch();
        
        return ($result['attempts'] >= MAX_LOGIN_ATTEMPTS);
    } catch(PDOException $e) {
        return false;
    }
}

// Create user session
function createUserSession($user_id, $token, $ip, $user_agent) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $token, $ip, $user_agent]);
        return true;
    } catch(PDOException $e) {
        error_log("Session creation failed: " . $e->getMessage());
        return false;
    }
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

// Log user activity
function logUserActivity($user_id, $activity_type, $description) {
    try {
        $db = getDB();
        $ip = getUserIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $activity_type, $description, $ip, $user_agent]);
        return true;
    } catch(PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
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
//===========================================

/**
 * Check if user subscription is active
 */
function validateUsername($username) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() !== false;
    } catch(PDOException $e) {
        error_log("Username validation failed: " . $e->getMessage());
        return false;
    }
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
 * Send security alert email
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
        }
        
        $message .= "\nThank you,\n" . SITE_NAME . " Security Team";
        
        error_log("Security Alert for {$user['email']}: {$alert_type}");
        
        // In production, send actual email
        // mail($user['email'], $subject, $message);
        
        return true;
    } catch(PDOException $e) {
        error_log("Security alert failed: " . $e->getMessage());
        return false;
    }
}
// Is code ko comment kardo:
/*
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
*/

// Helper function for logging vendor activities
function logVendorActivity($user_id, $activity_type, $description) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_activities 
            (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $activity_type,
            $description,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch(Exception $e) {
        // Log to file if database fails
        error_log("Activity Log Error: " . $e->getMessage());
    }
}


function getUploadError($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'File partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION => 'File upload stopped'
    ];
    return $errors[$error_code] ?? 'Unknown error';
}


function getProductCategories() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        error_log("Get categories failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Get product by ID
 */
function getProductById($product_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Get product failed: " . $e->getMessage());
        return null;
    }
}



/**
 * Export data to CSV format
 */
function exportToCSV($data, $filename, $vendorInfo = null) {
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add vendor info as comment if provided
    if ($vendorInfo) {
        fwrite($output, "# Vendor: {$vendorInfo['full_name']} ({$vendorInfo['email']})\n");
        fwrite($output, "# Export Date: " . date('Y-m-d H:i:s') . "\n");
        fwrite($output, "# Total Records: " . (count($data) - 1) . "\n\n");
    }
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

/**
 * Log export activity
 */
function logExportActivity($user_id, $filename, $record_count, $format, $type, $status = 'success') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO import_export_logs 
                              (type, filename, settings_count, import_mode, user_id, status) 
                              VALUES ('export', ?, ?, ?, ?, ?)");
        $stmt->execute([$filename, $record_count, $format . '|' . $type, $user_id, $status]);
        return true;
    } catch(PDOException $e) {
        error_log("Export log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if vendor can export (rate limiting)
 */
function canExport($user_id, $max_per_day = 10) {
    try {
        $db = getDB();
        $today = date('Y-m-d');
        $stmt = $db->prepare("SELECT COUNT(*) FROM import_export_logs 
                              WHERE user_id = ? AND DATE(created_at) = ? AND type = 'export'");
        $stmt->execute([$user_id, $today]);
        $count = $stmt->fetchColumn();
        
        return $count < $max_per_day;
    } catch(PDOException $e) {
        error_log("Export check error: " . $e->getMessage());
        return false;
    }
}


function sendOrderStatusUpdateNotification($user_id, $order_id, $new_status) {
    try {
        $db = getDB();
        // Get user email
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        // Prepare email
        $subject = "Order #$order_id Status Update - " . SITE_NAME;
        $message = "Hello " . $user['full_name'] . ",\n\n";
        $message .= "Your order with ID #$order_id has been updated to the following status: " . ucfirst($new_status) . ".\n\n";
        $message .= "Thank you for shopping with us!\n" . SITE_NAME . " Team";
        
        // Log the notification
        error_log("Order status update for {$user['email']}: Order #$order_id is now '$new_status'");
        
        // In production, send actual email
        mail($user['email'], $subject, $message);
        
        return true;
    } catch(PDOException $e) {
        error_log("Order status notification failed: " . $e->getMessage());
        return false;
    }
}

function sendLowStockAlert($admin_email, $product_name, $current_stock) {
    try {
        // Prepare email
        $subject = "Low Stock Alert - " . SITE_NAME;
        $message = "Hello Admin,\n\n";
        $message .= "The product '$product_name' is low on stock. Current stock level: $current_stock.\n\n";
        $message .= "Please restock it as soon as possible.\n" . SITE_NAME . " Team";
        
        // Log the alert
        error_log("Low stock alert for product '$product_name': Current stock is $current_stock");
        
        // In production, send actual email
        mail($admin_email, $subject, $message);
        
        return true;
    } catch(Exception $e) {
        error_log("Low stock alert failed: " . $e->getMessage());
        return false;
    }
}
function sendNewOrderNotification($admin_email, $order_id, $customer_name, $total_amount) {
    try {
        // Prepare email
        $subject = "New Order Placed - " . SITE_NAME;
        $message = "Hello Admin,\n\n";
        $message .= "A new order has been placed with the following details:\n";
        $message .= "Order ID: #$order_id\n";
        $message .= "Customer Name: $customer_name\n";
        $message .= "Total Amount: $" . number_format($total_amount, 2) . "\n\n";
        $message .= "Please review and process the order as soon as possible.\n" . SITE_NAME . " Team";
        
        // Log the notification
        error_log("New order notification: Order #$order_id placed by '$customer_name' with total amount $" . number_format($total_amount, 2));
        
        // In production, send actual email
        mail($admin_email, $subject, $message);
        
        return true;
    } catch(Exception $e) {
        error_log("New order notification failed: " . $e->getMessage());
        return false;
    }
}
function sendOrderCancellationNotification($admin_email, $order_id, $customer_name, $cancellation_reason) {
    try {
        // Prepare email
        $subject = "Order Cancellation - " . SITE_NAME;
        $message = "Hello Admin,\n\n";
        $message .= "The following order has been cancelled:\n";
        $message .= "Order ID: #$order_id\n";
        $message .= "Customer Name: $customer_name\n";
        if (!empty($cancellation_reason)) {
            $message .= "Cancellation Reason: $cancellation_reason\n";
        }
        $message .= "\nPlease review the cancellation and take necessary actions.\n" . SITE_NAME . " Team";
        
        // Log the notification
        error_log("Order cancellation notification: Order #$order_id cancelled by '$customer_name'. Reason: $cancellation_reason");
        
        // In production, send actual email
        mail($admin_email, $subject, $message);
        
        return true;
    } catch(Exception $e) {
        error_log("Order cancellation notification failed: " . $e->getMessage());
        return false;
    }
}
function sendEmail($to, $subject, $message) {
    // In production, use PHPMailer or similar library for better reliability
    $headers = "From: " . NO_REPLY_EMAIL . "\r\n" .
               "Reply-To: " . SUPPORT_EMAIL . "\r\n" .
               "X-Mailer: PHP/" . phpversion();
    
    // For demo, we'll just log the email instead of sending
    error_log("Email to $to: Subject: $subject - Message: $message");
    
    // Uncomment the line below to actually send the email in production
     return mail($to, $subject, $message, $headers);
    
    return true; // For demo purposes
}

function sendWithdrawalRequestNotification($admin_email, $vendor_name, $withdrawal_amount, $withdrawal_method, $account_details, $notes) {
    try {
        // Prepare email
        $subject = "New Withdrawal Request - " . SITE_NAME;
        $message = "Hello Admin,\n\n";
        $message .= "Vendor '$vendor_name' has requested a withdrawal of amount: $" . number_format($withdrawal_amount, 2) . ".\n\n";
        $message .= "Withdrawal Method: $withdrawal_method\n";
        if (!empty($account_details)) {
            $account = json_decode($account_details, true);
            $message .= "Account Details: " . ($account['account_holder_name'] ?? '') . " - " . ($account['bank_name'] ?? '') . "\n";
        }
        if (!empty($notes)) {
            $message .= "Notes: $notes\n";
        }
        $message .= "Please review and process the request as soon as possible.\n" . SITE_NAME . " Team";
        
        // Log the notification
        error_log("Withdrawal request from '$vendor_name': Amount $" . number_format($withdrawal_amount, 2));
        
        // In production, send actual email
        sendEmail($admin_email, $subject, $message);
        
        return true;
    } catch(Exception $e) {
        error_log("Withdrawal request notification failed: " . $e->getMessage());
        return false;
    }
}

function sendWithdrawalStatusNotification($vendor_email, $vendor_name, $withdrawal_amount, $withdrawal_method, $status, $notes = '') {
    try {
        // Prepare email
        $subject = "Withdrawal Request Update - " . SITE_NAME;
        $message = "Hello $vendor_name,\n\n";
        $message .= "Your withdrawal request of amount: $" . number_format($withdrawal_amount, 2) . " via $withdrawal_method has been updated to the following status: " . ucfirst($status) . ".\n\n";
        if (!empty($notes)) {
            $message .= "Notes: $notes\n\n";
        }
        $message .= "Thank you for being a valued vendor!\n" . SITE_NAME . " Team";
        
        // Log the notification
        error_log("Withdrawal status update for '$vendor_name': Amount $" . number_format($withdrawal_amount, 2) . " is now '$status'");
        
        // In production, send actual email
        mail($vendor_email, $subject, $message);
        
        return true;
    } catch(Exception $e) {
        error_log("Withdrawal status notification failed: " . $e->getMessage());
        return false;
    }
}
// function maskTaxID($taxId) {
//     if (empty($taxId)) return '';
//     $taxId = preg_replace('/[^0-9]/', '', $taxId);
//     if (strlen($taxId) <= 4) return str_repeat('•', strlen($taxId));
//     return str_repeat('•', strlen($taxId) - 4) . substr($taxId, -4);
// }
function maskEmail($email) {
    if (empty($email)) return '';
    $parts = explode('@', $email);
    if (count($parts) !== 2) return str_repeat('•', strlen($email));
    
    $name = $parts[0];
    $domain = $parts[1];
    
    $maskedName = strlen($name) > 2 ? substr($name, 0, 1) . str_repeat('•', strlen($name) - 2) . substr($name, -1) : str_repeat('•', strlen($name));
    $maskedDomain = strlen($domain) > 3 ? substr($domain, 0, 1) . str_repeat('•', strlen($domain) - 3) . substr($domain, -2) : str_repeat('•', strlen($domain));
    
    return $maskedName . '@' . $maskedDomain;
}
function maskPhone($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) <= 4) return str_repeat('•', strlen($phone));
    return str_repeat('•', strlen($phone) - 4) . substr($phone, -4);
}

function logActivity($user_id, $activity_type, $description) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $stmt->execute([$user_id, $activity_type, $description, $ip_address, $user_agent]);
    } catch(PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
    return $db;
}


/**
 * Redirect to dashboard based on user type
 */
function redirectToDashboard() {
    if (isset($_SESSION['user_type'])) {
        switch ($_SESSION['user_type']) {
            case 'admin':
                header('Location: ' . SITE_URL . 'admin/dashboard.php');
                break;
            case 'vendor':
                header('Location: ' . SITE_URL . 'admin/vendors/dashboard.php');
                break;
            default:
                header('Location: ' . SITE_URL . 'user/dashboard.php');
        }
    } else {
        header('Location: ' . SITE_URL . 'login.php');
    }
    exit();
}

/**
 * Simple redirect function
 */
function redirects($url) {
    header('Location: ' . $url);
    exit();
}




// define('ENCRYPTION_KEY', '5f8d9a2b3c4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a');

// // Other settings
// define('TIMEZONE', 'Asia/Karachi');
// date_default_timezone_set(TIMEZONE);

// // Error reporting (disable in production)
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// config.php
// define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: 'fallback-key-for-dev-only');
define('ENCRYPTION_KEY', 'your-32-character-secret-key-here-change-it');
// Temporary file banakar run karein
// echo bin2hex(random_bytes(32));
// Output copy karke config.php mein paste karein

function encryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}
function decryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY);
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}
// function encryptCardData($card_number, $expiry_month, $expiry_year) {
//     $data = json_encode([
//         'card_number' => $card_number,
//         'expiry_month' => $expiry_month,
//         'expiry_year' => $expiry_year
//     ]);
//     return encryptData($data);
// }



// Get time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('d M Y', $time);
    }
}

// Log user activity



// ========== NEW DOM HELPER FUNCTIONS ==========

/**
 * Get attribute value from DOM element
 * @param DOMElement $element The DOM element
 * @param string $attribute The attribute name
 * @param string $default Default value if attribute doesn't exist
 * @return string Attribute value or default
 */
function getAttribute($element, $attribute, $default = '') {
    if (!($element instanceof DOMElement)) {
        return $default;
    }
    
    if ($element->hasAttribute($attribute)) {
        return $element->getAttribute($attribute);
    }
    
    return $default;
}

/**
 * Check if DOM element has attribute
 * @param DOMElement $element The DOM element
 * @param string $attribute The attribute name
 * @return bool True if attribute exists
 */
function hasAttribute($element, $attribute) {
    if (!($element instanceof DOMElement)) {
        return false;
    }
    
    return $element->hasAttribute($attribute);
}

/**
 * Get all elements by tag name with optional attribute filter
 * @param DOMDocument $doc The DOM document
 * @param string $tagName The tag name to find
 * @param string $attrName Optional attribute name to filter
 * @param string $attrValue Optional attribute value to match
 * @return DOMNodeList List of matching elements
 */
function getElementsByTagName($doc, $tagName, $attrName = null, $attrValue = null) {
    $elements = $doc->getElementsByTagName($tagName);
    
    if ($attrName === null) {
        return $elements;
    }
    
    // Filter by attribute
    $filtered = [];
    foreach ($elements as $element) {
        if ($element->hasAttribute($attrName)) {
            if ($attrValue === null || $element->getAttribute($attrName) == $attrValue) {
                $filtered[] = $element;
            }
        }
    }
    
    // Convert to array-like object
    $result = new ArrayObject($filtered);
    return $result->getIterator();
}

/**
 * Get meta tag content by name
 * @param DOMDocument $doc The DOM document
 * @param string $name The meta name attribute
 * @return string|null Meta content or null
 */
function getMetaTag($doc, $name) {
    $metas = $doc->getElementsByTagName('meta');
    
    foreach ($metas as $meta) {
        if (getAttribute($meta, 'name') == $name) {
            return getAttribute($meta, 'content');
        }
    }
    
    return null;
}

/**
 * Get all links from DOM with classification
 * @param DOMDocument $doc The DOM document
 * @param string $baseUrl The base URL for internal/external classification
 * @return array Array of links with type classification
 */
function getLinks($doc, $baseUrl) {
    $links = $doc->getElementsByTagName('a');
    $result = [
        'internal' => [],
        'external' => [],
        'all' => []
    ];
    
    $baseHost = parse_url($baseUrl, PHP_URL_HOST);
    
    foreach ($links as $link) {
        $href = getAttribute($link, 'href');
        if (empty($href)) continue;
        
        $linkData = [
            'href' => $href,
            'text' => trim($link->textContent),
            'title' => getAttribute($link, 'title'),
            'rel' => getAttribute($link, 'rel'),
            'target' => getAttribute($link, 'target'),
            'nofollow' => strpos(getAttribute($link, 'rel'), 'nofollow') !== false
        ];
        
        $result['all'][] = $linkData;
        
        // Classify as internal or external
        if (strpos($href, 'http') === 0) {
            $linkHost = parse_url($href, PHP_URL_HOST);
            if ($linkHost === $baseHost) {
                $result['internal'][] = $linkData;
            } else {
                $result['external'][] = $linkData;
            }
        } elseif (strpos($href, '/') === 0 || strpos($href, '#') !== 0) {
            $result['internal'][] = $linkData;
        }
    }
    
    return $result;
}

/**
 * Get all images from DOM with alt text analysis
 * @param DOMDocument $doc The DOM document
 * @return array Array of images with alt status
 */
function getImages($doc) {
    $images = $doc->getElementsByTagName('img');
    $result = [
        'with_alt' => [],
        'without_alt' => [],
        'all' => []
    ];
    
    foreach ($images as $img) {
        $src = getAttribute($img, 'src');
        $alt = getAttribute($img, 'alt');
        $title = getAttribute($img, 'title');
        
        $imageData = [
            'src' => $src,
            'alt' => $alt,
            'title' => $title,
            'has_alt' => hasAttribute($img, 'alt') && !empty($alt)
        ];
        
        $result['all'][] = $imageData;
        
        if ($imageData['has_alt']) {
            $result['with_alt'][] = $imageData;
        } else {
            $result['without_alt'][] = $imageData;
        }
    }
    
    return $result;
}

/**
 * Get heading structure from DOM
 * @param DOMDocument $doc The DOM document
 * @return array Array of heading counts
 */
function getHeadings($doc) {
    $headings = [];
    
    for ($i = 1; $i <= 6; $i++) {
        $tag = 'h' . $i;
        $elements = $doc->getElementsByTagName($tag);
        $headings[$tag] = [
            'count' => $elements->length,
            'content' => []
        ];
        
        foreach ($elements as $element) {
            $headings[$tag]['content'][] = trim($element->textContent);
        }
    }
    
    return $headings;
}

/**
 * Extract text content from DOM
 * @param DOMDocument $doc The DOM document
 * @return string Clean text content
 */
function getTextContent($doc) {
    $xpath = new DOMXPath($doc);
    
    // Remove script and style elements
    foreach ($xpath->query('//script | //style') as $node) {
        $node->parentNode->removeChild($node);
    }
    
    $text = $doc->textContent;
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    return $text;
}

/**
 * Calculate keyword density in text
 * @param string $text The text content
 * @param string $keyword The keyword to check
 * @return array Keyword density information
 */
function calculateKeywordDensity($text, $keyword) {
    $words = str_word_count(strtolower($text), 1);
    $total_words = count($words);
    
    $keyword_lower = strtolower($keyword);
    $keyword_count = substr_count(strtolower($text), $keyword_lower);
    
    $density = $total_words > 0 ? round(($keyword_count / $total_words) * 100, 2) : 0;
    
    return [
        'total_words' => $total_words,
        'keyword_count' => $keyword_count,
        'density' => $density,
        'status' => $density == 0 ? 'missing' : ($density < 0.5 ? 'low' : ($density > 3 ? 'high' : 'optimal'))
    ];
}

// Error reporting for development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
