<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    die('Access denied');
}

echo "<h1>Commission Rate Debug</h1>";

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Check vendor_categories table structure
    echo "<h2>vendor_categories Table Structure</h2>";
    $stmt = $db->query("DESCRIBE vendor_categories");
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
    
    // Check vendor_categories data
    echo "<h2>vendor_categories Data</h2>";
    $stmt = $db->query("SELECT * FROM vendor_categories");
    $categories = $stmt->fetchAll();
    
    if (empty($categories)) {
        echo "<p>No categories found.</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        // Get column names from first row
        $first = $categories[0];
        echo "<tr>";
        foreach (array_keys($first) as $key) {
            echo "<th>" . $key . "</th>";
        }
        echo "</tr>";
        
        foreach ($categories as $cat) {
            echo "<tr>";
            foreach ($cat as $value) {
                echo "<td>" . ($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check user's category
    echo "<h2>User's Category</h2>";
    $stmt = $db->prepare("SELECT vendor_category FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $user_category = $stmt->fetchColumn();
    
    echo "User ID: " . $vendor_id . "<br>";
    echo "User Category: " . ($user_category ?: 'NULL') . "<br>";
    
    // Check if category matches
    if ($user_category) {
        $stmt = $db->prepare("SELECT * FROM vendor_categories WHERE slug = ?");
        $stmt->execute([$user_category]);
        $match = $stmt->fetch();
        
        if ($match) {
            echo "<h3>Matching Category Found:</h3>";
            echo "<pre>";
            print_r($match);
            echo "</pre>";
        } else {
            echo "<p>No matching category found in vendor_categories for slug: " . $user_category . "</p>";
        }
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>