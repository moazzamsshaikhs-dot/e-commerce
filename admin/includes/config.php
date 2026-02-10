<?php
// Database Configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default password is empty
define('DB_NAME', 'ecommerce_db');

// Site Configuration
define('SITE_URL', 'http://localhost/e-commerce/');
define('SITE_NAME', 'ShopEase Pro');

// Email Configuration (for OTP sending)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-email-password');
define('SMTP_PORT', 587);

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

// File Upload Configuration
define('UPLOAD_PATHS', $_SERVER['DOCUMENT_ROOT'] . '/ecommerce-project/assets/uploads/');
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
// Start Session
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

// Function to check if user is regular user
function isUser() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user';
}

// Redirect function
function redirect($url) {
    echo "<script>window.location.href='" . $url . "';</script>";
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
    // return mail($email, $subject, $message, $headers);
    
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
?>










<?php
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

// Generate secure token
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Check if user subscription is active
function isSubscriptionActive($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT subscription_plan, subscription_expiry 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        // Free plan is always active
        if ($user['subscription_plan'] === 'free') {
            return true;
        }
        
        // Check if premium/business subscription has expired
        if ($user['subscription_expiry'] && strtotime($user['subscription_expiry']) < time()) {
            // Auto-downgrade to free plan
            $update_stmt = $db->prepare("
                UPDATE users 
                SET subscription_plan = 'free', subscription_expiry = NULL 
                WHERE id = ?
            ");
            $update_stmt->execute([$user_id]);
            return false;
        }
        
        return true;
    } catch(PDOException $e) {
        error_log("Subscription check failed: " . $e->getMessage());
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
            LEFT JOIN subscription_plans p ON p.name = u.subscription_plan
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

// Send security alert email
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
        
        // Log the alert
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





// Helper function for redirect
// function redirect($url) {
//     header("Location: $url");
//     exit();
// }

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

?>


<?php
// Add these functions to your config.php file or create a separate helpers.php file

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
        mail($admin_email, $subject, $message);
        
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
function maskTaxID($taxId) {
    if (empty($taxId)) return '';
    $taxId = preg_replace('/[^0-9]/', '', $taxId);
    if (strlen($taxId) <= 4) return str_repeat('•', strlen($taxId));
    return str_repeat('•', strlen($taxId) - 4) . substr($taxId, -4);
}
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

function logActivity($user_id, $activity_type, $description,) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $activity_type, $description, getUserIP(), $_SERVER['HTTP_USER_AGENT'] ?? '']);
    } catch(PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}
?>