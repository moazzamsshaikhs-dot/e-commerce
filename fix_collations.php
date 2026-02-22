<?php
require_once 'includes/config.php';

echo "<h1>Fixing Database Collations</h1>";

try {
    $db = getDB();
    
    // Disable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "✓ Foreign key checks disabled<br>";
    
    // Get all tables
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>Converting tables...</h2>";
    
    foreach ($tables as $table) {
        try {
            $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            $db->exec($sql);
            echo "✓ Converted table: $table<br>";
        } catch (PDOException $e) {
            echo "✗ Failed to convert $table: " . $e->getMessage() . "<br>";
            
            // Try to get column info
            $col_stmt = $db->query("SHOW FULL COLUMNS FROM `$table`");
            $columns = $col_stmt->fetchAll();
            
            echo "  Converting columns individually...<br>";
            
            foreach ($columns as $column) {
                if (strpos($column['Type'], 'varchar') !== false || 
                    strpos($column['Type'], 'text') !== false || 
                    strpos($column['Type'], 'enum') !== false) {
                    
                    try {
                        $col_sql = "ALTER TABLE `$table` MODIFY `{$column['Field']}` {$column['Type']} 
                                    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
                        $db->exec($col_sql);
                        echo "  ✓ Converted column: {$column['Field']}<br>";
                    } catch (PDOException $e2) {
                        echo "  ✗ Failed to convert column {$column['Field']}: " . $e2->getMessage() . "<br>";
                    }
                }
            }
        }
        echo "<br>";
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✓ Foreign key checks re-enabled<br>";
    
    // Verify collations
    echo "<h2>Updated Collations:</h2>";
    $stmt = $db->query("
        SELECT TABLE_NAME, TABLE_COLLATION 
        FROM information_schema.tables 
        WHERE table_schema = 'ecommerce_db' 
        ORDER BY TABLE_NAME
    ");
    $results = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Table</th><th>Collation</th></tr>";
    foreach ($results as $row) {
        $color = $row['TABLE_COLLATION'] == 'utf8mb4_unicode_ci' ? 'green' : 'red';
        echo "<tr>";
        echo "<td>" . $row['TABLE_NAME'] . "</td>";
        echo "<td style='color: $color'>" . $row['TABLE_COLLATION'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>