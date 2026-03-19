<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access denied');
}

$sitemap_file = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';

if (file_exists($sitemap_file)) {
    header('Content-Type: application/xml');
    readfile($sitemap_file);
} else {
    // Return empty sitemap structure
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '</urlset>';
}