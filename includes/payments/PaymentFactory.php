<?php
namespace Ecommerce\Payments;

class PaymentFactory {
    
    private static $gateways = [
        'bank' => BankTransfer::class,
        'paypal' => PayPalGateway::class,
        'stripe' => StripeGateway::class,
        'easypaisa' => EasypaisaGateway::class,
        'jazzcash' => JazzCashGateway::class,
        'cod' => CashOnDelivery::class,
        'visa' => VisaGateway::class,
        'mastercard' => MastercardGateway::class
    ];
    
    public static function create($gatewayCode, $db = null) {
        if (!isset(self::$gateways[$gatewayCode])) {
            throw new \Exception("Payment gateway '{$gatewayCode}' not found");
        }
        
        $gatewayClass = self::$gateways[$gatewayCode];
        return new $gatewayClass($db);
    }
    
    public static function getActiveGateways($db) {
        try {
            $stmt = $db->prepare("
                SELECT * FROM payment_gateways 
                WHERE is_active = 1 
                ORDER BY sort_order
            ");
            $stmt->execute();
            $gateways = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $activeGateways = [];
            foreach ($gateways as $gateway) {
                try {
                    $instance = self::create($gateway['gateway_code'], $db);
                    $instance->initialize($gateway);
                    $activeGateways[$gateway['gateway_code']] = [
                        'instance' => $instance,
                        'config' => $gateway
                    ];
                } catch (\Exception $e) {
                    error_log("Gateway error: " . $e->getMessage());
                    continue;
                }
            }
            
            // If no gateways, get from admin_accounts
            if (empty($activeGateways)) {
                $activeGateways = self::getGatewaysFromAdminAccounts($db);
            }
            
            return $activeGateways;
            
        } catch (\Exception $e) {
            error_log("Error in getActiveGateways: " . $e->getMessage());
            return self::getGatewaysFromAdminAccounts($db);
        }
    }
    
    public static function getActivePayoutGateways($db) {
        // Get gateways that can be used for vendor payouts
        $payoutGateways = ['bank', 'paypal', 'stripe', 'easypaisa', 'jazzcash'];
        $activeGateways = self::getActiveGateways($db);
        
        return array_intersect_key($activeGateways, array_flip($payoutGateways));
    }
    
    private static function getGatewaysFromAdminAccounts($db) {
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT account_type 
                FROM admin_accounts 
                WHERE is_active = 1
            ");
            $stmt->execute();
            $accountTypes = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $activeGateways = [];
            $order = 0;
            
            foreach ($accountTypes as $code) {
                if (isset(self::$gateways[$code])) {
                    try {
                        $instance = self::create($code, $db);
                        $activeGateways[$code] = [
                            'instance' => $instance,
                            'config' => [
                                'gateway_code' => $code,
                                'gateway_name' => ucfirst($code),
                                'gateway_icon' => self::getIconForGateway($code),
                                'is_active' => 1,
                                'sort_order' => $order++
                            ]
                        ];
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
            
            // Always add COD
            if (!isset($activeGateways['cod'])) {
                $instance = self::create('cod', $db);
                $activeGateways['cod'] = [
                    'instance' => $instance,
                    'config' => [
                        'gateway_code' => 'cod',
                        'gateway_name' => 'Cash on Delivery',
                        'gateway_icon' => 'fas fa-money-bill-wave',
                        'is_active' => 1,
                        'sort_order' => 99
                    ]
                ];
            }
            
            return $activeGateways;
            
        } catch (\Exception $e) {
            error_log("Error in getGatewaysFromAdminAccounts: " . $e->getMessage());
            return [];
        }
    }
    
    private static function getIconForGateway($code) {
        $icons = [
            'paypal' => 'fab fa-paypal',
            'stripe' => 'fab fa-stripe',
            'bank' => 'fas fa-university',
            'easypaisa' => 'fas fa-mobile-alt',
            'jazzcash' => 'fas fa-mobile-alt',
            'visa' => 'fab fa-cc-visa',
            'mastercard' => 'fab fa-cc-mastercard',
            'cod' => 'fas fa-money-bill-wave'
        ];
        
        return $icons[$code] ?? 'fas fa-credit-card';
    }
}