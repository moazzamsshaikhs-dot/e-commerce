<?php
class Encryption {
    private static $key;
    private static $method = 'AES-256-CBC';
    
    public static function init() {
        self::$key = hash('sha256', ENCRYPTION_KEY, true);
    }
    
    public static function encrypt($data) {
        self::init();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$method));
        $encrypted = openssl_encrypt($data, self::$method, self::$key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    public static function decrypt($data) {
        self::init();
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, self::$method, self::$key, 0, $iv);
    }
}