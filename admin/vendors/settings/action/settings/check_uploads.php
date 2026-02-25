<?php
echo "<h1>Upload Directory Check</h1>";

$upload_dir = __DIR__ . '/uploads/vendors/';
echo "<h2>Upload Directory: $upload_dir</h2>";

if (file_exists($upload_dir)) {
    echo " Directory exists<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4) . "<br>";
    
    if (is_writable($upload_dir)) {
        echo " Directory is writable<br>";
    } else {
        echo " Directory is NOT writable<br>";
    }
} else {
    echo " Directory does NOT exist<br>";
    
    // Try to create it
    if (mkdir($upload_dir, 0777, true)) {
        echo " Directory created successfully<br>";
    } else {
        echo " Failed to create directory<br>";
    }
}

// Test write
$test_file = $upload_dir . 'test.txt';
if (file_put_contents($test_file, 'test')) {
    echo " Can write to directory<br>";
    unlink($test_file);
} else {
    echo " Cannot write to directory<br>";
}

// Check database
try {
    require_once 'includes/config.php';
    $db = getDB();
    
    echo "<h2>Database Check</h2>";
    
    // Check if vendor_settings table exists
    $stmt = $db->query("SHOW TABLES LIKE 'vendor_settings'");
    if ($stmt->rowCount() > 0) {
        echo " Directory is writable<br>";
    } else {
        echo " Directory is NOT writable<br>";
    }
    
    // Check current vendor settings
    session_start();
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM vendor_settings WHERE vendor_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $settings = $stmt->fetch();
        
        echo "<h3>Current Settings:</h3>";
        echo "<pre>";
        print_r($settings);
        echo "</pre>";
    }
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>