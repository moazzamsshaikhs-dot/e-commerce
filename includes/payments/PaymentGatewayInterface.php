<?php
namespace Ecommerce\Payments;

interface PaymentGatewayInterface {
    public function initialize($config);
    public function processCustomerPayment($order, $paymentData);
    public function processVendorPayout($vendorId, $amount, $payoutData);
    public function verifyPayment($transactionId);
    public function refundPayment($transactionId, $amount = null);
    public function getPaymentMethods();
    public function validateAccount($accountDetails);
    public function getPaymentForm($order, $userAccounts = []);
}