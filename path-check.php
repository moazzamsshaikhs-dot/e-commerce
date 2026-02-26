<?php
// path-check.php - Save in e-commerce root
echo "<h1>Path Check</h1>";

$paths = [
    'Current script' => __FILE__,
    'Document root' => $_SERVER['DOCUMENT_ROOT'],
    'SITE_URL' => defined('SITE_URL') ? SITE_URL : 'Not defined',
    'Action file path' => $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/admin/vendors/settings/action/bank/add-bank-account.php',
    'Action URL' => '/e-commerce/admin/vendors/settings/action/bank/add-bank-account.php',
    'Full URL' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/e-commerce/admin/vendors/settings/action/bank/add-bank-account.php'
];

foreach($paths as $name => $path) {
    echo "<h3>$name:</h3>";
    echo "<p>$path</p>";
    if (strpos($name, 'path') !== false && file_exists($path)) {
        echo "<p style='color:green'>✅ File exists</p>";
    } elseif (strpos($name, 'path') !== false) {
        echo "<p style='color:red'>❌ File does NOT exist</p>";
    }
}

echo "<h2>Try these URLs in browser:</h2>";
echo "<ul>";
echo "<li><a href='/e-commerce/admin/vendors/settings/action/bank/add-bank-account.php' target='_blank'>/e-commerce/admin/vendors/settings/action/bank/add-bank-account.php</a></li>";
echo "<li><a href='" . SITE_URL . "admin/vendors/settings/action/bank/add-bank-account.php' target='_blank'>" . SITE_URL . "admin/vendors/settings/action/bank/add-bank-account.php</a></li>";
echo "</ul>";
?>