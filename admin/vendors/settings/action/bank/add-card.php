<?php
// action/bank/add-card.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: application/json');

error_log("=== Add Card Started ===");
error_log("POST data: " . print_r($_POST, true));

if ($_SESSION['user_type'] !== 'vendor') {
    error_log("Access denied - not vendor");
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method");
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    error_log("CSRF token mismatch");
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$vendor_id = $_SESSION['user_id'];
error_log("Vendor ID: $vendor_id");

// Encryption function
function encryptCardData($data) {
    if (!defined('ENCRYPTION_KEY')) {
        // Fallback for development - replace with proper key in production
        $key = 'your-secret-key-here-must-be-32-chars';
    } else {
        $key = ENCRYPTION_KEY;
    }
    
    // Ensure key is 32 bytes for AES-256
    $key = hash('sha256', $key, true);
    
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    
    if ($encrypted === false) {
        error_log("Encryption failed");
        return base64_encode($data); // Fallback
    }
    
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
    $card_number = trim(preg_replace('/\s+/', '', $_POST['card_number'] ?? ''));
    $expiry_month = $_POST['expiry_month'] ?? '';
    $expiry_year = $_POST['expiry_year'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    error_log("Form data: type=$card_type, holder=$card_holder_name, number=****" . substr($card_number, -4));
    
    // Validation
    $errors = [];
    
    if (!in_array($card_type, ['visa', 'mastercard', 'amex'])) {
        $errors[] = 'Invalid card type. Must be Visa, Mastercard, or American Express';
    }
    
    if (empty($card_holder_name)) {
        $errors[] = 'Card holder name is required';
    } elseif (strlen($card_holder_name) < 3) {
        $errors[] = 'Card holder name must be at least 3 characters';
    } elseif (!preg_match('/^[A-Za-z\s]+$/', $card_holder_name)) {
        $errors[] = 'Card holder name should contain only letters and spaces';
    }
    
    if (empty($card_number)) {
        $errors[] = 'Card number is required';
    } elseif (!preg_match('/^\d{13,19}$/', $card_number)) {
        $errors[] = 'Invalid card number length';
    } elseif (!luhnCheck($card_number)) {
        $errors[] = 'Invalid card number (failed checksum)';
    }
    
    $detected_brand = detectCardBrand($card_number);
    if ($detected_brand !== $card_type && $detected_brand !== 'unknown') {
        $errors[] = "Card number doesn't match selected type. Detected: " . ucfirst($detected_brand);
    }
    
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
    }
    
    if (empty($cvv)) {
        $errors[] = 'CVV is required';
    } elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
        $errors[] = 'CVV must be 3 or 4 digits';
    }
    
    if (!empty($errors)) {
        error_log("Validation errors: " . implode('. ', $errors));
        throw new Exception(implode('. ', $errors));
    }
    
    $card_last_four = substr($card_number, -4);
    
    // Get vendor's country
    $stmt = $db->prepare("SELECT country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $country = $stmt->fetchColumn() ?: 'US';
    
    // Check if card already exists
    $stmt = $db->prepare("
        SELECT id FROM vendor_cards 
        WHERE vendor_id = ? AND card_last_four = ? AND expiry_month = ? AND expiry_year = ?
    ");
    $stmt->execute([$vendor_id, $card_last_four, $expiry_month, $expiry_year]);
    if ($stmt->fetch()) {
        throw new Exception('This card is already registered');
    }
    
    $db->beginTransaction();
    
    // If setting as default, unset other defaults
    if ($is_default) {
        $stmt = $db->prepare("UPDATE vendor_payment_methods SET is_default = 0 WHERE vendor_id = ? AND method_type IN ('visa', 'mastercard', 'amex')");
        $stmt->execute([$vendor_id]);
    }
    
    // Insert into vendor_payment_methods
    $stmt = $db->prepare("
        INSERT INTO vendor_payment_methods (vendor_id, method_type, country_code, is_default, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $result = $stmt->execute([$vendor_id, $card_type, $country, $is_default]);
    
    if (!$result) {
        throw new Exception('Failed to create payment method record');
    }
    
    $payment_method_id = $db->lastInsertId();
    error_log("Payment method ID: $payment_method_id");
    
    // Encrypt card number
    $encrypted_card = encryptCardData($card_number);
    
    // Insert into vendor_cards
    $stmt = $db->prepare("
        INSERT INTO vendor_cards 
        (payment_method_id, vendor_id, card_type, card_holder_name, card_number_encrypted, 
         card_last_four, expiry_month, expiry_year, is_default, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $result = $stmt->execute([
        $payment_method_id, 
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
        throw new Exception('Failed to insert card details');
    }
    
    // Log activity
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at) VALUES (?, 'add_card', ?, ?, ?, NOW())");
    $log->execute([$vendor_id, "Added {$card_type} card ending in {$card_last_four}", $ip, $ua]);
    
    $db->commit();
    error_log("Card added successfully");
    
    // Generate new CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($card_type) . ' card added successfully',
        'csrf_token' => $_SESSION['csrf_token']
    ]);
    
} catch(PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("PDO Error in add card: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    
} catch(Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Add card error for vendor $vendor_id: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>