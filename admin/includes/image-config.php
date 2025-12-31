<?php
// Database Configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP default password is empty
define('DB_NAME', 'ecommerce_db');

// Site Configuration
define('SITE_URL', 'http://localhost/e-commerce/');
define('SITE_NAME', 'ShopEase Pro');

// Product Image Configuration
define('PRODUCT_IMAGE_PATH', $_SERVER['DOCUMENT_ROOT'] . '../assets/images/products/');
define('PRODUCT_IMAGE_URL', SITE_URL . 'assets/images/products/');

// Allowed image types for products
define('ALLOWED_PRODUCT_IMAGES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp']);
define('MAX_PRODUCT_IMAGE_SIZE', 10 * 1024 * 1024); // 10MB

// Email Configuration (for OTP sending)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-email-password');
define('SMTP_PORT', 587);

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

// Security Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT_MINUTES', 15);
define('SESSION_TIMEOUT_MINUTES', 30);
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SYMBOL', true);

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Karachi');

// Make sure product image directory exists
if (!file_exists(PRODUCT_IMAGE_PATH)) {
    mkdir(PRODUCT_IMAGE_PATH, 0777, true);
}

// Create default product image if it doesn't exist
$default_image_path = PRODUCT_IMAGE_PATH . 'default.jpg';
if (!file_exists($default_image_path)) {
    // Create a simple default image
    $default_image = imagecreatetruecolor(400, 400);
    $bg_color = imagecolorallocate($default_image, 240, 240, 240);
    $text_color = imagecolorallocate($default_image, 180, 180, 180);
    imagefill($default_image, 0, 0, $bg_color);
    imagestring($default_image, 5, 120, 180, 'No Image', $text_color);
    imagestring($default_image, 3, 100, 220, 'Product Image', $text_color);
    imagejpeg($default_image, $default_image_path, 80);
    imagedestroy($default_image);
}

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
    header("Location: $url");
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

/**
 * Upload product image with validation (Simplified version)
 */
function uploadProductImage($file, $existing_image = '') {
    $errors = [];
    $new_filename = '';
    
    if (isset($file['name']) && !empty($file['name'])) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . $file['error'];
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check file size
        if ($file['size'] > MAX_PRODUCT_IMAGE_SIZE) {
            $errors[] = "File size too large. Maximum allowed: 10MB";
        }
        
        // Get file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check if extension is allowed
        if (!in_array($file_extension, ALLOWED_PRODUCT_IMAGES)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', ALLOWED_PRODUCT_IMAGES);
        }
        
        // Basic image validation
        $image_info = @getimagesize($file['tmp_name']);
        if (!$image_info) {
            $errors[] = "Uploaded file is not a valid image";
        }
        
        if (empty($errors)) {
            // Generate unique filename
            $unique_id = uniqid('product_', true);
            $new_filename = $unique_id . '_' . time() . '.' . $file_extension;
            $upload_path = PRODUCT_IMAGE_PATH . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($existing_image) && $existing_image !== 'default.jpg') {
                    $old_image_path = PRODUCT_IMAGE_PATH . $existing_image;
                    if (file_exists($old_image_path) && is_file($old_image_path)) {
                        @unlink($old_image_path);
                    }
                }
                
                return [
                    'success' => true, 
                    'image_name' => $new_filename,
                    'full_path' => $upload_path,
                    'url' => PRODUCT_IMAGE_URL . $new_filename
                ];
            } else {
                $errors[] = "Failed to move uploaded file. Check directory permissions.";
            }
        }
    } else {
        // No new image uploaded
        if (!empty($existing_image)) {
            return [
                'success' => true, 
                'image_name' => $existing_image,
                'url' => PRODUCT_IMAGE_URL . $existing_image
            ];
        } else {
            return [
                'success' => true, 
                'image_name' => 'default.jpg',
                'url' => PRODUCT_IMAGE_URL . 'default.jpg'
            ];
        }
    }
    
    return ['success' => false, 'errors' => $errors];
}

/**
 * Get upload error message (simplified)
 */
function getUploadError($error_code) {
    $errors = [
        UPLOAD_ERR_OK => 'No error',
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

/**
 * Get all product categories
 */
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
?>