<?php
// includes/payments/MastercardGateway.php

require_once 'StripeGateway.php';

class MastercardGateway extends StripeGateway {
    protected function loadConfig() {
        parent::loadConfig();
    }
    
    protected function getGatewayName() {
        return 'mastercard';
    }
    
    public function processPayment($amount, $currency, $paymentData) {
        // Mastercard uses Stripe infrastructure
        return parent::processPayment($amount, $currency, $paymentData);
    }
}