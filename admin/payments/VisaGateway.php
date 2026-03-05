<?php
// includes/payments/VisaGateway.php

require_once 'StripeGateway.php';

class VisaGateway extends StripeGateway {
    protected function loadConfig() {
        parent::loadConfig();
    }
    
    protected function getGatewayName() {
        return 'visa';
    }
    
    public function processPayment($amount, $currency, $paymentData) {
        // Visa uses Stripe infrastructure
        return parent::processPayment($amount, $currency, $paymentData);
    }
}