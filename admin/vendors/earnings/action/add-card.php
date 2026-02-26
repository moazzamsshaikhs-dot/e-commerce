<?php
// admin/vendors/earnings/action/add-card.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log file for debugging
$log_file = dirname(__DIR__, 3) . '/logs/card_debug.log';
$log_dir = dirname($log_file);
if (!file_exists($log_dir)) {
    mkdir($log_dir, 0777, true);
}

function debug_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

debug_log("=== Add Card Started ===");
debug_log("POST data: " . print_r($_POST, true));

if ($_SESSION['user_type'] !== 'vendor') {
    debug_log("Access denied - not vendor");
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ../withdraw.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debug_log("Invalid request method");
    $_SESSION['error'] = 'Invalid request method';
    header('Location: ../withdraw.php');
    exit();
}

// Verify CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    debug_log("CSRF token mismatch");
    $_SESSION['error'] = 'Invalid security token. Please refresh the page.';
    header('Location: ../withdraw.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
debug_log("Vendor ID: $vendor_id");

// Encryption function (simplified for now)
function encryptCardData($data) {
    // In production, use a proper encryption key from config
    $key = 'your-secret-key-here-change-this-in-production';
    $key = hash('sha256', $key, true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

// Luhn algorithm to validate card number
function luhnCheck($number) {
    $number = preg_replace('/\D/', '', $number);
    $sum = 0;
    $alt = false;
    
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = (int)$number[$i];
        
        if ($alt) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
        $alt = !$alt;
    }
    
    return $sum % 10 === 0;
}

// Detect card brand from number
function detectCardBrand($number) {
    $number = preg_replace('/\D/', '', $number);
    
    $patterns = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
        'mastercard' => '/^5[1-5][0-9]{14}$|^2[2-7][0-9]{14}$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/'
    ];
    
    foreach ($patterns as $brand => $pattern) {
        if (preg_match($pattern, $number)) {
            return $brand;
        }
    }
    
    return 'unknown';
}

try {
    $db = getDB();
    
    // Get and sanitize form data
    $card_type = $_POST['card_type'] ?? '';
    $card_holder_name = trim($_POST['card_holder_name'] ?? '');
    
    // Remove all spaces and dashes from card number
    $card_number = preg_replace('/[\s-]/', '', $_POST['card_number'] ?? '');
    
    $expiry_month = $_POST['expiry_month'] ?? '';
    $expiry_year = $_POST['expiry_year'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    debug_log("Form data: type=$card_type, holder=$card_holder_name, number=****" . substr($card_number, -4) . ", length=" . strlen($card_number));
    
    // Validation
    $errors = [];
    
    // Validate card type
    if (!in_array($card_type, ['visa', 'mastercard', 'amex'])) {
        $errors[] = 'Invalid card type. Must be Visa, Mastercard, or American Express';
    }
    
    // Validate card holder name
    if (empty($card_holder_name)) {
        $errors[] = 'Card holder name is required';
    } elseif (strlen($card_holder_name) < 3) {
        $errors[] = 'Card holder name must be at least 3 characters';
    } elseif (!preg_match('/^[A-Za-z\s\.]+$/', $card_holder_name)) {
        $errors[] = 'Card holder name should contain only letters, spaces and dots';
    }
    
    // Validate card number
    if (empty($card_number)) {
        $errors[] = 'Card number is required';
    } else {
        // Remove any non-digits just to be safe
        $card_number = preg_replace('/\D/', '', $card_number);
        
        // Check length based on card type
        $valid_length = false;
        if ($card_type == 'visa') {
            $valid_length = in_array(strlen($card_number), [13, 16, 19]);
            if (!$valid_length) {
                $errors[] = 'Visa card number must be 13, 16, or 19 digits';
            }
        } elseif ($card_type == 'mastercard') {
            $valid_length = strlen($card_number) == 16;
            if (!$valid_length) {
                $errors[] = 'Mastercard number must be 16 digits';
            }
        } elseif ($card_type == 'amex') {
            $valid_length = strlen($card_number) == 15;
            if (!$valid_length) {
                $errors[] = 'American Express card number must be 15 digits';
            }
        }
        
        // Check if it's all digits
        if (!ctype_digit($card_number)) {
            $errors[] = 'Card number must contain only digits';
        }
        
        // Luhn algorithm check
        if (empty($errors) && !luhnCheck($card_number)) {
            $errors[] = 'Invalid card number (failed checksum)';
        }
        
        // Check card brand matches
        $detected_brand = detectCardBrand($card_number);
        if ($detected_brand !== 'unknown' && $detected_brand !== $card_type) {
            $errors[] = "Card number doesn't match selected type. Detected: " . ucfirst($detected_brand);
        }
    }
    
    // Validate expiry date
    if (empty($expiry_month) || empty($expiry_year)) {
        $errors[] = 'Expiry date is required';
    } else {
        $current_year = (int)date('Y');
        $current_month = (int)date('m');
        $exp_year = (int)$expiry_year;
        $exp_month = (int)$expiry_month;
        
        if ($exp_year < $current_year || ($exp_year == $current_year && $exp_month < $current_month)) {
            $errors[] = 'Card has expired';
        }
        
        if ($exp_month < 1 || $exp_month > 12) {
            $errors[] = 'Invalid expiry month';
        }
        
        if ($exp_year < $current_year || $exp_year > $current_year + 20) {
            $errors[] = 'Invalid expiry year';
        }
    }
    
    // Validate CVV
    if (empty($cvv)) {
        $errors[] = 'CVV is required';
    } else {
        $cvv = preg_replace('/\D/', '', $cvv);
        if ($card_type == 'amex') {
            if (strlen($cvv) != 4) {
                $errors[] = 'American Express CVV must be 4 digits';
            }
        } else {
            if (strlen($cvv) != 3) {
                $errors[] = 'CVV must be 3 digits for Visa/Mastercard';
            }
        }
        if (!ctype_digit($cvv)) {
            $errors[] = 'CVV must contain only digits';
        }
    }
    
    // If there are errors, store them and redirect
    if (!empty($errors)) {
        debug_log("Validation errors: " . implode('. ', $errors));
        $_SESSION['form_errors'] = $errors;
        header('Location: ../withdraw.php');
        exit();
    }
    
    $card_last_four = substr($card_number, -4);
    
    // Check if card already exists (using last 4 digits and expiry)
    $stmt = $db->prepare("
        SELECT id FROM vendor_cards 
        WHERE vendor_id = ? AND card_last_four = ? AND expiry_month = ? AND expiry_year = ?
    ");
    $stmt->execute([$vendor_id, $card_last_four, $expiry_month, $expiry_year]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = 'This card is already registered';
        header('Location: ../withdraw.php');
        exit();
    }
    
    $db->beginTransaction();
    debug_log("Transaction started");
    
    // If setting as default, unset other defaults
    if ($is_default) {
        debug_log("Setting as default, unsetting others");
        $stmt = $db->prepare("UPDATE vendor_cards SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
    }
    
    // Check if vendor_payment_methods table exists, if not, insert directly into vendor_cards
    try {
        // First check if vendor_payment_methods table exists
        $stmt = $db->query("SHOW TABLES LIKE 'vendor_payment_methods'");
        if ($stmt->rowCount() > 0) {
            // Insert into vendor_payment_methods first
            $stmt = $db->prepare("
                INSERT INTO vendor_payment_methods (vendor_id, method_type, is_default, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$vendor_id, $card_type, $is_default]);
            $payment_method_id = $db->lastInsertId();
            debug_log("Payment method ID: $payment_method_id");
        } else {
            $payment_method_id = null;
            debug_log("vendor_payment_methods table doesn't exist, skipping");
        }
    } catch(Exception $e) {
        debug_log("Payment methods table error: " . $e->getMessage());
        $payment_method_id = null;
    }
    
    // Encrypt card number
    $encrypted_card = encryptCardData($card_number);
    debug_log("Card encrypted");
    
    // Insert into vendor_cards
    $stmt = $db->prepare("
        INSERT INTO vendor_cards 
        (vendor_id, card_type, card_holder_name, card_number_encrypted, 
         card_last_four, expiry_month, expiry_year, is_default, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $vendor_id,
        $card_type,
        $card_holder_name,
        $encrypted_card,
        $card_last_four,
        $expiry_month,
        $expiry_year,
        $is_default
    ]);
    
    if (!$result) {
        $error = $stmt->errorInfo();
        debug_log("Failed to insert card: " . print_r($error, true));
        throw new Exception('Failed to insert card details: ' . ($error[2] ?? 'Unknown error'));
    }
    
    debug_log("Card inserted successfully with ID: " . $db->lastInsertId());
    
    // Log activity
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) 
            VALUES (?, 'add_card', ?, ?, ?, NOW())
        ");
        $log->execute([$vendor_id, "Added {$card_type} card ending in {$card_last_four}", $ip, $ua]);
        debug_log("Activity logged");
    } catch(Exception $e) {
        debug_log("Activity log failed: " . $e->getMessage());
        // Continue even if logging fails
    }
    
    $db->commit();
    debug_log("Transaction committed");
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $_SESSION['success'] = ucfirst($card_type) . ' card ending in ' . $card_last_four . ' added successfully! It will be verified within 24-48 hours.';
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        debug_log("Transaction rolled back");
    }
    debug_log("PDO Error in add card: " . $e->getMessage());
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        debug_log("Transaction rolled back");
    }
    debug_log("Add card error for vendor $vendor_id: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

header('Location: ../withdraw.php');
exit();
?>