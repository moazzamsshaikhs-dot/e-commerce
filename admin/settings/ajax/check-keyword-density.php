<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$url = $_POST['url'] ?? '';
$keyword = $_POST['keyword'] ?? '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Valid URL required']);
    exit;
}

if (empty($keyword)) {
    echo json_encode(['success' => false, 'message' => 'Keyword required']);
    exit;
}

try {
    // Fetch page content
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; SEO Analyzer; +' . SITE_URL . ')');
    
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        throw new Exception('Failed to fetch URL');
    }
    
    // Remove scripts, styles, and tags
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    // Count words
    $words = str_word_count(strtolower($text), 1);
    $total_words = count($words);
    
    // Count keyword occurrences
    $keyword_lower = strtolower($keyword);
    $keyword_count = substr_count(strtolower($text), $keyword_lower);
    
    // Calculate density
    $density = $total_words > 0 ? round(($keyword_count / $total_words) * 100, 2) : 0;
    
    // Determine status
    $status = 'good';
    $message = '';
    
    if ($keyword_count === 0) {
        $status = 'danger';
        $message = 'Keyword not found on the page';
    } elseif ($density < 0.5) {
        $status = 'warning';
        $message = 'Keyword density is low (' . $density . '%). Recommended: 0.5-2.5%';
    } elseif ($density > 3) {
        $status = 'warning';
        $message = 'Keyword density is high (' . $density . '%). Possible keyword stuffing';
    } else {
        $message = 'Keyword density is optimal (' . $density . '%)';
    }
    
    // Check keyword in title
    $title = '';
    if (preg_match('/<title>(.*?)<\/title>/is', $html, $matches)) {
        $title = $matches[1];
    }
    $keyword_in_title = stripos($title, $keyword) !== false;
    
    // Check keyword in headings
    $keyword_in_h1 = false;
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
        $h1 = $matches[1];
        $keyword_in_h1 = stripos($h1, $keyword) !== false;
    }
    
    echo json_encode([
        'success' => true,
        'url' => $url,
        'keyword' => $keyword,
        'total_words' => $total_words,
        'keyword_count' => $keyword_count,
        'density' => $density,
        'status' => $status,
        'message' => $message,
        'keyword_in_title' => $keyword_in_title,
        'keyword_in_h1' => $keyword_in_h1
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}