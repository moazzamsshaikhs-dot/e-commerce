<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = getDB();
    
    // Get total indexed pages
    $total_urls = 0;
    
    // Products count
    $products = $db->query("SELECT COUNT(*) FROM products WHERE stock > 0")->fetchColumn();
    $total_urls += $products;
    
    // Categories count
    $categories = $db->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn();
    $total_urls += $categories;
    
    // Static pages (custom count)
    $static_pages = 5; // home, about, contact, products, blog
    $total_urls += $static_pages;
    
    // Get last sitemap generation
    $sitemap_file = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
    $last_sitemap = file_exists($sitemap_file) ? date('Y-m-d H:i:s', filemtime($sitemap_file)) : null;
    
    // Get SEO settings
    $settings = [];
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'seo_%' OR setting_key LIKE 'meta_%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    echo json_encode([
        'success' => true,
        'total_urls' => $total_urls,
        'products' => $products,
        'categories' => $categories,
        'static_pages' => $static_pages,
        'last_sitemap' => $last_sitemap,
        'settings' => $settings,
        'has_meta_title' => !empty($settings['meta_title']),
        'has_meta_description' => !empty($settings['meta_description']),
        'has_analytics' => !empty($settings['google_analytics_id'])
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}