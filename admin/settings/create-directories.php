<?php
// admin/settings/create-log-file.php
// Run this once to create the log file

require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    die('Access denied. Admin only.');
}

echo "<h1>Creating Log File</h1>";

// Define paths
$base_dir = dirname(__DIR__);
$logs_dir = $base_dir . '/logs';
$log_file = $logs_dir . '/update_uploads.log';

// Create logs directory if not exists
if (!is_dir($logs_dir)) {
    if (mkdir($logs_dir, 0777, true)) {
        echo "<p style='color: green'>✓ Created logs directory: {$logs_dir}</p>";
        
        // Create index.html for security
        $index_content = '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1><p>You don\'t have permission to access this resource.</p></body></html>';
        file_put_contents($logs_dir . '/index.html', $index_content);
        echo "<p style='color: green'>✓ Created index.html in logs directory</p>";
        
        // Create .htaccess for security
        $htaccess_content = "Options -Indexes\n<FilesMatch \".*\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n<FilesMatch \"\.(log)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>";
        file_put_contents($logs_dir . '/.htaccess', $htaccess_content);
        echo "<p style='color: green'>✓ Created .htaccess in logs directory</p>";
    } else {
        echo "<p style='color: red'>✗ Failed to create logs directory</p>";
    }
} else {
    echo "<p style='color: blue'>ℹ Logs directory already exists: {$logs_dir}</p>";
}

// Create log file
if (!file_exists($log_file)) {
    $initial_content = "# Update Uploads Log\n";
    $initial_content .= "# Created: " . date('Y-m-d H:i:s') . "\n";
    $initial_content .= "# This file logs all manual update uploads\n";
    $initial_content .= "# ========================================\n\n";
    
    if (file_put_contents($log_file, $initial_content)) {
        echo "<p style='color: green'>✓ Created log file: {$log_file}</p>";
        chmod($log_file, 0666);
        echo "<p style='color: green'>✓ Set permissions to 666 (read/write)</p>";
    } else {
        echo "<p style='color: red'>✗ Failed to create log file</p>";
    }
} else {
    echo "<p style='color: blue'>ℹ Log file already exists: {$log_file}</p>";
}

// Display log file info
echo "<h2>Log File Information</h2>";
echo "<ul>";
echo "<li><strong>Path:</strong> {$log_file}</li>";
echo "<li><strong>Size:</strong> " . (file_exists($log_file) ? filesize($log_file) . ' bytes' : 'N/A') . "</li>";
echo "<li><strong>Permissions:</strong> " . (file_exists($log_file) ? substr(sprintf('%o', fileperms($log_file)), -4) : 'N/A') . "</li>";
echo "</ul>";

// Display current log content
if (file_exists($log_file)) {
    echo "<h2>Current Log Content</h2>";
    echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto; max-height: 300px;'>";
    echo htmlspecialchars(file_get_contents($log_file));
    echo "</pre>";
}

echo "<p><a href='system-updates.php' class='btn btn-primary'>Go to System Updates</a></p>";
?>