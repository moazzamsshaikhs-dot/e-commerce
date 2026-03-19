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
    
    // Get all products
    $products = $db->query("SELECT id, name, updated_at FROM products WHERE stock > 0")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all categories
    $categories = $db->query("SELECT slug, updated_at FROM categories WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    
    // Static pages
    $pages = [
        ['url' => '', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => 'about', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => 'contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => 'products', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => 'blog', 'priority' => '0.7', 'changefreq' => 'weekly']
    ];
    
    // Start building sitemap XML
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Add static pages
    foreach ($pages as $page) {
        $url = SITE_URL . $page['url'];
        $lastmod = date('Y-m-d');
        
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . $url . '</loc>' . "\n";
        $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        $xml .= '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
        $xml .= '    <priority>' . $page['priority'] . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    
    // Add products
    foreach ($products as $product) {
        $url = SITE_URL . 'product-details.php?id=' . $product['id'];
        $lastmod = date('Y-m-d', strtotime($product['updated_at']));
        
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . $url . '</loc>' . "\n";
        $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>0.6</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    
    // Add categories
    foreach ($categories as $category) {
        $url = SITE_URL . 'category.php?slug=' . $category['slug'];
        $lastmod = date('Y-m-d', strtotime($category['updated_at']));
        
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . $url . '</loc>' . "\n";
        $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>0.7</priority>' . "\n";
        $xml .= '  </url>' . "\n";
    }
    
    $xml .= '</urlset>';
    
    // Save sitemap to file
    $sitemap_file = $_SERVER['DOCUMENT_ROOT'] . '/sitemap.xml';
    file_put_contents($sitemap_file, $xml);
    
    // Update robots.txt to include sitemap
    $robots_file = $_SERVER['DOCUMENT_ROOT'] . '/robots.txt';
    if (file_exists($robots_file)) {
        $robots_content = file_get_contents($robots_file);
        if (strpos($robots_content, 'Sitemap:') === false) {
            file_put_contents($robots_file, $robots_content . "\nSitemap: " . SITE_URL . "sitemap.xml\n");
        }
    }
    
    $url_count = count($pages) + count($products) + count($categories);
    
    echo json_encode([
        'success' => true,
        'message' => 'Sitemap generated successfully',
        'url_count' => $url_count,
        'filename' => 'sitemap.xml'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}