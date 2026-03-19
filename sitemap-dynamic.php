<?php
header('Content-Type: application/xml; charset=utf-8');

require_once 'includes/config.php';

try {
    $db = getDB();
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Only include the homepage (which works)
    echo '  <url>' . "\n";
    echo '    <loc>https://shopeasepro.com/</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    echo '    <changefreq>daily</changefreq>' . "\n";
    echo '    <priority>1.0</priority>' . "\n";
    echo '  </url>' . "\n";
    
    // Add product pages that work
    $products = $db->query("SELECT id, updated_at FROM products WHERE stock > 0 AND approved_status = 'approved'")->fetchAll();
    foreach ($products as $product) {
        echo '  <url>' . "\n";
        echo '    <loc>https://shopeasepro.com/product-details.php?id=' . $product['id'] . '</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d', strtotime($product['updated_at'])) . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.6</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    
    // Add category pages that work
    $categories = $db->query("SELECT slug, updated_at FROM categories WHERE is_active = 1")->fetchAll();
    foreach ($categories as $cat) {
        echo '  <url>' . "\n";
        echo '    <loc>https://shopeasepro.com/category.php?slug=' . $cat['slug'] . '</loc>' . "\n";
        echo '    <lastmod>' . date('Y-m-d', strtotime($cat['updated_at'])) . '</lastmod>' . "\n";
        echo '    <changefreq>weekly</changefreq>' . "\n";
        echo '    <priority>0.7</priority>' . "\n";
        echo '  </url>' . "\n";
    }
    
    echo '</urlset>';
    
} catch (Exception $e) {
    // Fallback to minimal sitemap
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    echo '  <url>' . "\n";
    echo '    <loc>https://shopeasepro.com/</loc>' . "\n";
    echo '    <lastmod>' . date('Y-m-d') . '</lastmod>' . "\n";
    echo '    <changefreq>daily</changefreq>' . "\n";
    echo '    <priority>1.0</priority>' . "\n";
    echo '  </url>' . "\n";
    echo '</urlset>';
}
?>