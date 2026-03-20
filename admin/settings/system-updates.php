<?php
// Add this at the beginning of system-updates.php after the includes
// Create required directories if they don't exist

// Create uploads/updates directory
$uploads_dir = __DIR__ . '/../../uploads';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0777, true);
    // Create index.html to prevent directory listing
    file_put_contents($uploads_dir . '/index.html', '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1><p>You don\'t have permission to access this resource.</p></body></html>');
}

$updates_dir = $uploads_dir . '/updates';
if (!is_dir($updates_dir)) {
    mkdir($updates_dir, 0777, true);
    // Create index.html
    file_put_contents($updates_dir . '/index.html', '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1><p>You don\'t have permission to access this resource.</p></body></html>');
}

// Create logs directory
$logs_dir = __DIR__ . '/../../logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0777, true);
    // Create index.html
    file_put_contents($logs_dir . '/index.html', '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1><p>You don\'t have permission to access this resource.</p></body></html>');
}

// Create .htaccess to protect directories
$htaccess_content = <<<HTACCESS
# Prevent directory listing
Options -Indexes

# Deny access to all files
<FilesMatch ".*">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Allow PHP files only from specific IPs (optional)
<FilesMatch "\.(php)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
HTACCESS;

file_put_contents($uploads_dir . '/.htaccess', $htaccess_content);
file_put_contents($updates_dir . '/.htaccess', $htaccess_content);
file_put_contents($logs_dir . '/.htaccess', $htaccess_content);

// Create .gitignore to exclude logs from version control
$gitignore_content = <<<GITIGNORE
# Ignore all files in this directory
*
# Except .gitignore
!.gitignore
GITIGNORE;

file_put_contents($logs_dir . '/.gitignore', $gitignore_content);
?>