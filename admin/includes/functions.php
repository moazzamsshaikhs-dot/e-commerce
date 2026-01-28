<?php   
function logActivity($user_id, $activity_type, $description) {
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
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch(Exception $e) {
        // Log error silently
        error_log('Activity log error: ' . $e->getMessage());
    }
}


// Calculate tax for an order
function calculateTax($amount, $country, $state = null, $city = null, $postcode = null, $tax_class_id = null) {
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'] ?? 0;
        
        // Get tax rate based on location and tax class
        $query = "
            SELECT rate, compound 
            FROM vendor_tax_rates 
            WHERE vendor_id = ? 
            AND country = ?
            AND (state = ? OR state IS NULL OR state = '')
            AND (city = ? OR city IS NULL OR city = '')
            AND (postcode = ? OR postcode IS NULL OR postcode = '')
            AND (? IS NULL OR tax_class_id = ?)
            ORDER BY priority DESC, state IS NULL, city IS NULL, postcode IS NULL
            LIMIT 1
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$vendor_id, $country, $state, $city, $postcode, $tax_class_id, $tax_class_id]);
        $tax_rate = $stmt->fetch();
        
        if ($tax_rate) {
            $tax_amount = ($amount * $tax_rate['rate']) / 100;
            return [
                'rate' => $tax_rate['rate'],
                'amount' => $tax_amount,
                'compound' => $tax_rate['compound']
            ];
        }
        
        // Return zero tax if no rate found
        return ['rate' => 0, 'amount' => 0, 'compound' => 0];
        
    } catch(PDOException $e) {
        error_log('Tax calculation error: ' . $e->getMessage());
        return ['rate' => 0, 'amount' => 0, 'compound' => 0];
    }
}

// Check if customer is tax exempt
function isTaxExempt($customer_email, $country = null) {
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'] ?? 0;
        
        $query = "
            SELECT id 
            FROM vendor_tax_exemptions 
            WHERE vendor_id = ? 
            AND customer_email = ?
            AND (valid_from IS NULL OR valid_from <= CURDATE())
            AND (valid_to IS NULL OR valid_to >= CURDATE())
        ";
        
        if ($country) {
            $query .= " AND (country IS NULL OR country = ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$vendor_id, $customer_email, $country]);
        } else {
            $stmt = $db->prepare($query);
            $stmt->execute([$vendor_id, $customer_email]);
        }
        
        return $stmt->fetch() !== false;
        
    } catch(PDOException $e) {
        error_log('Tax exemption check error: ' . $e->getMessage());
        return false;
    }
}
?>