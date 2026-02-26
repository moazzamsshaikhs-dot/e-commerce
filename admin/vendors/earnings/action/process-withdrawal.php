<?php
// admin/vendors/earnings/action/process-withdrawal.php (Add this in the switch case)

// In the validation section, add cases for other methods:
switch($withdrawal_method) {
    case 'bank':
        // existing bank logic
        break;
        
    case 'paypal':
        $paypal_email = trim($_POST['paypal_email'] ?? '');
        if (empty($paypal_email)) {
            $errors[] = 'PayPal email is required';
        } elseif (!filter_var($paypal_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid PayPal email';
        } else {
            $account_details = json_encode(['paypal_email' => $paypal_email]);
        }
        break;
        
    case 'stripe':
        $stripe_account_id = trim($_POST['stripe_account_id'] ?? '');
        if (empty($stripe_account_id)) {
            $errors[] = 'Stripe account ID is required';
        } else {
            $account_details = json_encode(['stripe_account_id' => $stripe_account_id]);
        }
        break;
        
    case 'easypaisa':
    case 'jazzcash':
        $mobile_account_id = intval($_POST['mobile_account_id'] ?? 0);
        if (!$mobile_account_id) {
            $errors[] = 'Please select a mobile account';
        } else {
            $stmt = $db->prepare("
                SELECT * FROM vendor_mobile_accounts 
                WHERE id = ? AND vendor_id = ? AND account_type = ?
            ");
            $stmt->execute([$mobile_account_id, $vendor_id, $withdrawal_method]);
            $mobile = $stmt->fetch();
            
            if (!$mobile) {
                $errors[] = 'Mobile account not found';
            } elseif (empty($mobile['is_verified'])) {
                $errors[] = 'Mobile account must be verified';
            } else {
                $mobile_number = $mobile['mobile_number'];
                $cnic_number = $mobile['cnic_number'] ?? null;
                $account_details = json_encode([
                    'account_type' => $mobile['account_type'],
                    'mobile_number' => substr($mobile['mobile_number'], -4),
                    'account_holder' => $mobile['account_holder_name']
                ]);
            }
        }
        break;
        
    case 'visa':
    case 'mastercard':
    case 'amex':
        $card_id = intval($_POST['card_id'] ?? 0);
        if (!$card_id) {
            $errors[] = 'Please select a card';
        } else {
            $stmt = $db->prepare("
                SELECT * FROM vendor_cards 
                WHERE id = ? AND vendor_id = ?
            ");
            $stmt->execute([$card_id, $vendor_id]);
            $card = $stmt->fetch();
            
            if (!$card) {
                $errors[] = 'Card not found';
            } elseif (empty($card['is_verified'])) {
                $errors[] = 'Card must be verified';
            } else {
                $card_last_four = $card['card_last_four'];
                $account_details = json_encode([
                    'card_type' => $card['card_type'],
                    'card_holder' => $card['card_holder_name'],
                    'card_last_four' => $card['card_last_four']
                ]);
            }
        }
        break;
}