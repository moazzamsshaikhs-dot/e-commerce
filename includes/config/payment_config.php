<?php
// includes/config/payment_config.php

// Stripe Configuration
define('STRIPE_ENVIRONMENT', 'sandbox'); // sandbox or production
define('STRIPE_TEST_KEY', 'pk_test_your_stripe_test_key');
define('STRIPE_TEST_SECRET', 'sk_test_your_stripe_test_secret');
define('STRIPE_LIVE_KEY', 'pk_live_your_stripe_live_key');
define('STRIPE_LIVE_SECRET', 'sk_live_your_stripe_live_secret');
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret');

// PayPal Configuration
define('PAYPAL_ENVIRONMENT', 'sandbox'); // sandbox or production
define('PAYPAL_CLIENT_ID', 'your_paypal_client_id');
define('PAYPAL_CLIENT_SECRET', 'your_paypal_client_secret');
define('PAYPAL_WEBHOOK_ID', 'your_webhook_id');

// Easypaisa Configuration
define('EASYPAISA_ENVIRONMENT', 'sandbox');
define('EASYPAISA_MERCHANT_ID', 'your_merchant_id');
define('EASYPAISA_API_KEY', 'your_api_key');
define('EASYPAISA_API_SECRET', 'your_api_secret');
define('EASYPAISA_STORE_ID', 'your_store_id');

// JazzCash Configuration
define('JAZZCASH_ENVIRONMENT', 'sandbox');
define('JAZZCASH_MERCHANT_ID', 'your_merchant_id');
define('JAZZCASH_PASSWORD', 'your_password');
define('JAZZCASH_INTEGERITY_SALT', 'your_salt');
define('JAZZCASH_RETURN_URL', SITE_URL . 'payment/jazzcash/response.php');

// Common Settings
define('PAYMENT_LOG_FILE', dirname(__DIR__) . '/logs/payments.log');
define('PAYMENT_CURRENCY', 'USD');
define('PAYMENT_SUCCESS_URL', SITE_URL . 'payment/success.php');
define('PAYMENT_CANCEL_URL', SITE_URL . 'payment/cancel.php');