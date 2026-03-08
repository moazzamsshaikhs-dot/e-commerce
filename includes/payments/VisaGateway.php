<?php
namespace Ecommerce\Payments;

class VisaGateway extends StripeGateway {
    
    protected $gatewayCode = 'visa';
    protected $gatewayName = 'Visa';
    
    public function initialize($config) {
        parent::initialize($config);
        return $this;
    }
    
    public function getPaymentMethods() {
        return [
            ['id' => 'visa', 'name' => 'Visa Card', 'icon' => 'fab fa-cc-visa']
        ];
    }
}