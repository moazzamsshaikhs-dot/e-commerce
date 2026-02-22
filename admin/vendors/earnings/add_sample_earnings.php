<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Only allow in development mode
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Check if vendor already has earnings
        $stmt = $db->prepare("SELECT COUNT(*) FROM vendor_earnings WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            echo "Vendor already has $count earnings records.";
            exit;
        }
        
        // Get some products for this vendor
        $stmt = $db->prepare("SELECT id, price FROM products WHERE vendor_id = ? LIMIT 5");
        $stmt->execute([$vendor_id]);
        $products = $stmt->fetchAll();
        
        if (empty($products)) {
            echo "No products found for this vendor. Please add products first.";
            exit;
        }
        
        // Get some orders
        $stmt = $db->prepare("SELECT id FROM orders LIMIT 10");
        $stmt->execute();
        $orders = $stmt->fetchAll();
        
        if (empty($orders)) {
            echo "No orders found in the system.";
            exit;
        }
        
        // Insert sample earnings for the last 3 years
        $current_year = date('Y');
        $years = [$current_year, $current_year - 1, $current_year - 2];
        
        $db->beginTransaction();
        
        foreach ($years as $year) {
            // Create 5-10 earnings per year
            $num_earnings = rand(5, 10);
            
            for ($i = 0; $i < $num_earnings; $i++) {
                $product = $products[array_rand($products)];
                $order = $orders[array_rand($orders)];
                
                $product_price = $product['price'];
                $commission_rate = 10; // 10%
                $commission_amount = $product_price * ($commission_rate / 100);
                $vendor_amount = $product_price - $commission_amount;
                
                // Random date within the year
                $month = rand(1, 12);
                $day = rand(1, 28);
                $date = "$year-$month-$day 12:00:00";
                
                $stmt = $db->prepare("
                    INSERT INTO vendor_earnings (
                        vendor_id, order_id, product_id, order_item_id,
                        product_price, commission, commission_amount, vendor_amount,
                        status, created_at
                    ) VALUES (?, ?, ?, 1, ?, ?, ?, ?, 'paid', ?)
                ");
                
                $stmt->execute([
                    $vendor_id,
                    $order['id'],
                    $product['id'],
                    $product_price,
                    $commission_rate,
                    $commission_amount,
                    $vendor_amount,
                    $date
                ]);
            }
        }
        
        $db->commit();
        
        echo "Sample earnings added successfully!";
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "This script is only available in development mode.";
}