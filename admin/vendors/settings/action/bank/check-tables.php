<?php
// action/bank/check-tables.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

header('Content-Type: text/html');

if ($_SESSION['user_type'] !== 'vendor') {
    die('Access denied');
}

echo "<h1>Database Tables Check</h1>";

try {
    $db = getDB();
    
    $tables = [
        'vendor_payment_methods',
        'vendor_bank_accounts',
        'vendor_mobile_accounts',
        'vendor_paypal_accounts',
        'vendor_stripe_accounts',
        'vendor_cards'
    ];
    
    foreach ($tables as $table) {
        echo "<h2>Table: $table</h2>";
        
        // Check if table exists
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            echo "<p style='color:red'> Table does not exist</p>";
            continue;
        }
        
        echo "<p style='color:green'> Table exists</p>";
        
        // Show table structure
        $stmt = $db->query("DESCRIBE $table");
        $columns = $stmt->fetchAll();
        
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>" . $col['Field'] . "</td>";
            echo "<td>" . $col['Type'] . "</td>";
            echo "<td>" . $col['Null'] . "</td>";
            echo "<td>" . $col['Key'] . "</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch(Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>