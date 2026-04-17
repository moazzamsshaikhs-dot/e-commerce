<?php
// Database Configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_USER', 'u124468513_Moazzam');
define('DB_PASS', 'moa123zz45%6aM789');
define('DB_NAME', 'u124468513_ecommerce_db');

// Site Configuration
define('SITE_URL', 'https://shopeasepro.com/');
define('SITE_NAME', 'ShopEase Pro');

// Email Configuration
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USER', 'shopeasepro2@shopeasepro.com');
define('SMTP_PASS', 'moa123zz45%6aM789');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');

// Email Addresses
define('FROM_EMAIL', 'shopeasepro2@shopeasepro.com');
define('FROM_NAME', 'ShopEase Pro');
define('ADMIN_EMAIL', 'shopeasepro2@gmail.com');

// OTP Configuration
define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

// Security Configuration
define('ENABLE_SECURITY_CHECKS', false);
define('SESSION_TIMEOUT_MINUTES', 30);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT_MINUTES', 15);
define('PASSWORD_MIN_LENGTH', 6);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true); 
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SYMBOL', true);

// File Upload Configuration
define('UPLOAD_PATHS', $_SERVER['DOCUMENT_ROOT'] . SITE_URL .'assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
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

// Path Configuration
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/');
define('UPLOAD_PATH', ROOT_PATH . 'assets/images/products/');
define('UPLOAD_URL', SITE_URL . 'assets/images/products/');

// Version
define('CURRENT_VERSION', '1.1.0');

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Karachi');

// DATABASE CONNECTION
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

// SESSION & AUTH FUNCTIONS
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

function isVendor() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor';
}

function isUser() {
    return isLoggedIn() && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'user';
}

function redirect($url) {
    header('Location: ' . SITE_URL . $url);
    exit();
}

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

// SECURITY FUNCTIONS
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Only define this function ONCE (not in auth-check.php)
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateOTP($length = 6) {
    return str_pad(random_int(0, 999999), $length, '0', STR_PAD_LEFT);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// EMAIL FUNCTIONS
function sendOTPEmail($email, $otp) {
    $subject = "Your OTP Code - " . SITE_NAME;
    $message = "Your OTP code is: $otp\nValid for " . OTP_EXPIRY_MINUTES . " minutes";
    $headers = "From: " . NO_REPLY_EMAIL . "\r\n";
    
    error_log("OTP for $email: $otp");
    return mail($email, $subject, $message, $headers);
}

function sendOTPSMS($phone, $otp) {
    error_log("SMS OTP for $phone: $otp");
    return true;
}

function sendEmail($to, $subject, $message) {
    $headers = "From: " . NO_REPLY_EMAIL . "\r\n" .
               "Reply-To: " . SUPPORT_EMAIL . "\r\n" .
               "X-Mailer: PHP/" . phpversion();
    
    error_log("Email to $to: Subject: $subject");
    return mail($to, $subject, $message, $headers);
}


// ACTIVITY LOGGING
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

function logActivity($user_id, $activity_type, $description) {
    return logUserActivity($user_id, $activity_type, $description);
}

function logVendorActivity($user_id, $activity_type, $description) {
    return logUserActivity($user_id, $activity_type, $description);
}

// SESSION MANAGEMENT
function createUserSession($user_id, $token, $ip, $user_agent) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $token, $ip, $user_agent]);
        return true;
    } catch(PDOException $e) {
        error_log("Session creation failed: " . $e->getMessage());
        return false;
    }
}

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

// LOGIN ATTEMPT TRACKING
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
        error_log("Login attempt tracking failed: " . $e->getMessage());
        return false;
    }
}

function isIPBlocked($ip) {
    try {
        $db = getDB();
        $time_threshold = date('Y-m-d H:i:s', strtotime('-' . LOGIN_TIMEOUT_MINUTES . ' minutes'));
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? AND success = 0 AND attempted_at > ?
        ");
        $stmt->execute([$ip, $time_threshold]);
        $result = $stmt->fetch();
        
        return ($result['attempts'] >= MAX_LOGIN_ATTEMPTS);
    } catch(PDOException $e) {
        return false;
    }
}

// SUBSCRIPTION FUNCTIONS
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

// PRODUCT FUNCTIONS
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

// NOTIFICATION FUNCTIONS
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
        mail($user['email'], $subject, $message);
        
        return true;
    } catch(PDOException $e) {
        error_log("Security alert failed: " . $e->getMessage());
        return false;
    }
}

function sendOrderStatusUpdateNotification($user_id, $order_id, $new_status) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        $subject = "Order #$order_id Status Update - " . SITE_NAME;
        $message = "Hello " . $user['full_name'] . ",\n\n";
        $message .= "Your order with ID #$order_id has been updated to: " . ucfirst($new_status) . ".\n\n";
        $message .= "Thank you for shopping with us!\n" . SITE_NAME . " Team";
        
        error_log("Order status update for {$user['email']}: Order #$order_id is now '$new_status'");
        mail($user['email'], $subject, $message);
        
        return true;
    } catch(PDOException $e) {
        error_log("Order status notification failed: " . $e->getMessage());
        return false;
    }
}

function sendNewOrderNotification($admin_email, $order_id, $customer_name, $total_amount) {
    $subject = "New Order Placed - " . SITE_NAME;
    $message = "Hello Admin,\n\nA new order has been placed:\n";
    $message .= "Order ID: #$order_id\n";
    $message .= "Customer Name: $customer_name\n";
    $message .= "Total Amount: $" . number_format($total_amount, 2) . "\n\n";
    $message .= "Please review the order.\n" . SITE_NAME . " Team";
    
    error_log("New order notification: Order #$order_id by '$customer_name'");
    mail($admin_email, $subject, $message);
    
    return true;
}

// UTILITY FUNCTIONS
function formatDate($date, $format = 'd M Y h:i A') {
    return date($format, strtotime($date));
}

function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
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

// VENDOR FUNCTIONS
function sendVendorRegistrationAlert($vendor_id) {
    error_log("Vendor registration alert for vendor: $vendor_id");
    return true;
}

function isVendorApproved() {
    if (!isVendor()) return false;
    
    if (isset($_SESSION['vendor_status'])) {
        return $_SESSION['vendor_status'] === 'approved';
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $status = $stmt->fetchColumn();
        $_SESSION['vendor_status'] = $status;
        return $status === 'approved';
    } catch (PDOException $e) {
        return false;
    }
}

function sendWithdrawalRequestNotification($admin_email, $vendor_name, $withdrawal_amount, $withdrawal_method, $account_details, $notes) {
    $subject = "New Withdrawal Request - " . SITE_NAME;
    $message = "Hello Admin,\n\n";
    $message .= "Vendor '$vendor_name' has requested a withdrawal of: $" . number_format($withdrawal_amount, 2) . "\n";
    $message .= "Method: $withdrawal_method\n";
    $message .= "Please review the request.\n" . SITE_NAME . " Team";
    
    error_log("Withdrawal request from '$vendor_name': Amount $" . number_format($withdrawal_amount, 2));
    mail($admin_email, $subject, $message);
    
    return true;
}

function sendWithdrawalStatusNotification($vendor_email, $vendor_name, $withdrawal_amount, $withdrawal_method, $status, $notes = '') {
    $subject = "Withdrawal Request Update - " . SITE_NAME;
    $message = "Hello $vendor_name,\n\n";
    $message .= "Your withdrawal request of $" . number_format($withdrawal_amount, 2) . " via $withdrawal_method is now: " . ucfirst($status) . ".\n\n";
    $message .= "Thank you for being a valued vendor!\n" . SITE_NAME . " Team";
    
    error_log("Withdrawal status update for '$vendor_name': Amount $" . number_format($withdrawal_amount, 2) . " is now '$status'");
    mail($vendor_email, $subject, $message);
    
    return true;
}

function sendLowStockAlert($admin_email, $product_name, $current_stock) {
    $subject = "Low Stock Alert - " . SITE_NAME;
    $message = "Hello Admin,\n\n";
    $message .= "The product '$product_name' is low on stock. Current stock: $current_stock.\n\n";
    $message .= "Please restock it as soon as possible.\n" . SITE_NAME . " Team";
    
    error_log("Low stock alert for product '$product_name': Current stock is $current_stock");
    mail($admin_email, $subject, $message);
    
    return true;
}

function sendOrderCancellationNotification($admin_email, $order_id, $customer_name, $cancellation_reason) {
    $subject = "Order Cancellation - " . SITE_NAME;
    $message = "Hello Admin,\n\n";
    $message .= "Order #$order_id has been cancelled by $customer_name.\n";
    if (!empty($cancellation_reason)) {
        $message .= "Reason: $cancellation_reason\n";
    }
    $message .= "\nPlease review the cancellation.\n" . SITE_NAME . " Team";
    
    error_log("Order cancellation notification: Order #$order_id cancelled by '$customer_name'");
    mail($admin_email, $subject, $message);
    
    return true;
}

// ============================================
// EXPORT FUNCTIONS
// ============================================
function exportToCSV($data, $filename, $vendorInfo = null) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($vendorInfo) {
        fwrite($output, "# Vendor: {$vendorInfo['full_name']} ({$vendorInfo['email']})\n");
        fwrite($output, "# Export Date: " . date('Y-m-d H:i:s') . "\n");
        fwrite($output, "# Total Records: " . (count($data) - 1) . "\n\n");
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

function logExportActivity($user_id, $filename, $record_count, $format, $type, $status = 'success') {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO import_export_logs 
                              (type, filename, settings_count, import_mode, user_id, status, created_at) 
                              VALUES ('export', ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$filename, $record_count, $format . '|' . $type, $user_id, $status]);
        return true;
    } catch(PDOException $e) {
        error_log("Export log error: " . $e->getMessage());
        return false;
    }
}

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

// ENCRYPTION FUNCTIONS
function encryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY ?? 'fallback-key');
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptData($data) {
    $key = hash('sha256', ENCRYPTION_KEY ?? 'fallback-key');
    $data = base64_decode($data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}
// DOM HELPER FUNCTIONS
function getAttribute($element, $attribute, $default = '') {
    if (!($element instanceof DOMElement)) {
        return $default;
    }
    
    if ($element->hasAttribute($attribute)) {
        return $element->getAttribute($attribute);
    }
    
    return $default;
}

function hasAttribute($element, $attribute) {
    if (!($element instanceof DOMElement)) {
        return false;
    }
    
    return $element->hasAttribute($attribute);
}

function getElementsByTagName($doc, $tagName, $attrName = null, $attrValue = null) {
    $elements = $doc->getElementsByTagName($tagName);
    
    if ($attrName === null) {
        return $elements;
    }
    
    $filtered = [];
    foreach ($elements as $element) {
        if ($element->hasAttribute($attrName)) {
            if ($attrValue === null || $element->getAttribute($attrName) == $attrValue) {
                $filtered[] = $element;
            }
        }
    }
    
    $result = new ArrayObject($filtered);
    return $result->getIterator();
}

function getMetaTag($doc, $name) {
    $metas = $doc->getElementsByTagName('meta');
    
    foreach ($metas as $meta) {
        if (getAttribute($meta, 'name') == $name) {
            return getAttribute($meta, 'content');
        }
    }
    
    return null;
}

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

function getTextContent($doc) {
    $xpath = new DOMXPath($doc);
    
    foreach ($xpath->query('//script | //style') as $node) {
        $node->parentNode->removeChild($node);
    }
    
    $text = $doc->textContent;
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    return $text;
}

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

// Define ENCRYPTION_KEY if not defined
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', '5f8d9a2b3c4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a');
}
?>