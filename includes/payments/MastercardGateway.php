<?php
namespace Ecommerce\Payments;

class MastercardGateway extends StripeGateway {
    
    protected $gatewayCode = 'mastercard';
    protected $gatewayName = 'Mastercard';
    
    public function initialize($config) {
        parent::initialize($config);
        return $this;
    }
    
    public function getPaymentMethods() {
        return [
            ['id' => 'mastercard', 'name' => 'Mastercard', 'icon' => 'fab fa-cc-mastercard']
        ];
    }
}