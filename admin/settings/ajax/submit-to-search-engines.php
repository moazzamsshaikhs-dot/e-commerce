<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$engine = $_POST['engine'] ?? 'google';

try {
    $sitemap_url = SITE_URL . 'sitemap.xml';
    
    // Google Search Console submission URL
    $google_submit_url = 'https://www.google.com/ping?sitemap=' . urlencode($sitemap_url);
    
    // Bing Webmaster Tools submission URL
    $bing_submit_url = 'https://www.bing.com/ping?sitemap=' . urlencode($sitemap_url);
    
    $results = [];
    
    if ($engine === 'google' || $engine === 'both') {
        $ch = curl_init($google_submit_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SEO Tool; +' . SITE_URL . ')');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $results['google'] = [
            'success' => $httpCode === 200,
            'message' => $httpCode === 200 ? 'Sitemap submitted to Google' : 'Google submission failed (HTTP ' . $httpCode . ')'
        ];
    }
    
    if ($engine === 'bing' || $engine === 'both') {
        $ch = curl_init($bing_submit_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SEO Tool; +' . SITE_URL . ')');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $results['bing'] = [
            'success' => $httpCode === 200,
            'message' => $httpCode === 200 ? 'Sitemap submitted to Bing' : 'Bing submission failed (HTTP ' . $httpCode . ')'
        ];
    }
    
    $success = true;
    $message = '';
    
    if ($engine === 'both') {
        if ($results['google']['success'] && $results['bing']['success']) {
            $message = 'Sitemap submitted to both Google and Bing successfully';
        } else {
            $success = false;
            $message = 'Google: ' . $results['google']['message'] . ' | Bing: ' . $results['bing']['message'];
        }
    } else {
        $success = $results[$engine]['success'];
        $message = $results[$engine]['message'];
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'details' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}