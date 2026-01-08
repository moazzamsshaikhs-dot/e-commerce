<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = getDB();
    
    // Generate sitemap XML
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    $url_count = 0;
    
    // Add homepage
    $xml .= '  <url>' . "\n";
    $xml .= '    <loc>' . htmlspecialchars(SITE_URL) . '</loc>' . "\n";
    $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    $xml .= '    <changefreq>daily</changefreq>' . "\n";
    $xml .= '    <priority>1.0</priority>' . "\n";
    $xml .= '  </url>' . "\n";
    $url_count++;
    
    // Add static pages
    $pages = [
        ['url' => 'about', 'priority' => 0.8],
        ['url' => 'contact', 'priority' => 0.8],
        ['url' => 'products', 'priority' => 0.9],
        ['url' => 'blog', 'priority' => 0.7]
    ];
    
    foreach ($pages as $page) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars(SITE_URL . $page['url']) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
        $xml .= '    <changefreq>monthly</changefreq>' . "\n";
        $xml .= '    <priority>' . $page['priority'] . '</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        $url_count++;
    }
    
    // Add products
    $stmt = $db->query("SELECT id, updated_at FROM products WHERE stock > 0");
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars(SITE_URL . 'product/' . $product['id']) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d', strtotime($product['updated_at'])) . '</lastmod>' . "\n";
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>0.6</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        $url_count++;
    }
    
    // Add categories
    $stmt = $db->query("SELECT slug, updated_at FROM categories WHERE is_active = 1");
    $categories = $stmt->fetchAll();
    
    foreach ($categories as $category) {
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars(SITE_URL . 'category/' . $category['slug']) . '</loc>' . "\n";
        $xml .= '    <lastmod>' . date('Y-m-d', strtotime($category['updated_at'])) . '</lastmod>' . "\n";
        $xml .= '    <changefreq>weekly</changefreq>' . "\n";
        $xml .= '    <priority>0.5</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        $url_count++;
    }
    
    $xml .= '</urlset>';
    
    // Save sitemap file
    $filename = '../sitemap.xml';
    file_put_contents($filename, $xml);
    
    // Update robots.txt
    $robots = "User-agent: *\n";
    $robots .= "Disallow: /admin/\n";
    $robots .= "Disallow: /ajax/\n";
    $robots .= "Disallow: /includes/\n";
    $robots .= "Sitemap: " . SITE_URL . "sitemap.xml\n";
    
    file_put_contents('../../robots.txt', $robots);
    
    echo json_encode([
        'success' => true,
        'message' => 'Sitemap generated successfully',
        'url_count' => $url_count,
        'filename' => 'sitemap.xml'
    ]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>