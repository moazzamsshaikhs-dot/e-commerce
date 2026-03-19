<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => 'Valid URL required']);
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        throw new Exception('Failed to fetch URL. HTTP Code: ' . $httpCode);
    }
    
    // Parse HTML
    $doc = new DOMDocument();
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    
    // USING ALL HELPER FUNCTIONS FROM CONFIG.PHP
    
    // Get basic meta information
    $title = $doc->getElementsByTagName('title')->length > 0 
        ? trim($doc->getElementsByTagName('title')->item(0)->textContent) 
        : '';
    
    $description = getMetaTag($doc, 'description');
    $keywords = getMetaTag($doc, 'keywords');
    $author = getMetaTag($doc, 'author');
    $viewport = getMetaTag($doc, 'viewport');
    $robots = getMetaTag($doc, 'robots');
    
    // Get heading structure
    $headings = getHeadings($doc);
    
    // Get images with alt text
    $images = getImages($doc);
    
    // Get links with classification
    $links = getLinks($doc, $url);
    
    // Get clean text content
    $textContent = getTextContent($doc);
    $wordCount = str_word_count($textContent);
    
    // Calculate scores
    $score = 100;
    $recommendations = [];
    
    // Title analysis
    $title_length = strlen($title);
    if (empty($title)) {
        $score -= 20;
        $recommendations[] = ['status' => 'danger', 'message' => 'Missing page title'];
    } elseif ($title_length < 30) {
        $score -= 10;
        $recommendations[] = ['status' => 'warning', 'message' => 'Title too short (' . $title_length . ' chars)'];
    } elseif ($title_length > 60) {
        $score -= 5;
        $recommendations[] = ['status' => 'warning', 'message' => 'Title too long (' . $title_length . ' chars)'];
    } else {
        $recommendations[] = ['status' => 'good', 'message' => 'Title length optimal (' . $title_length . ' chars)'];
    }
    
    // Description analysis
    $desc_length = strlen($description ?? '');
    if (empty($description)) {
        $score -= 20;
        $recommendations[] = ['status' => 'danger', 'message' => 'Missing meta description'];
    } elseif ($desc_length < 120) {
        $score -= 10;
        $recommendations[] = ['status' => 'warning', 'message' => 'Description too short (' . $desc_length . ' chars)'];
    } elseif ($desc_length > 160) {
        $score -= 5;
        $recommendations[] = ['status' => 'warning', 'message' => 'Description too long (' . $desc_length . ' chars)'];
    } else {
        $recommendations[] = ['status' => 'good', 'message' => 'Description length optimal (' . $desc_length . ' chars)'];
    }
    
    // Keywords analysis
    if (empty($keywords)) {
        $score -= 5;
        $recommendations[] = ['status' => 'warning', 'message' => 'Missing meta keywords'];
    }
    
    // Heading analysis
    if ($headings['h1']['count'] === 0) {
        $score -= 10;
        $recommendations[] = ['status' => 'warning', 'message' => 'No H1 heading found'];
    } elseif ($headings['h1']['count'] > 1) {
        $score -= 5;
        $recommendations[] = ['status' => 'warning', 'message' => 'Multiple H1 headings (' . $headings['h1']['count'] . ')'];
    }
    
    // Image analysis
    if ($images['without_alt'] > 0) {
        $score -= min(20, $images['without_alt'] * 2);
        $recommendations[] = ['status' => 'warning', 'message' => $images['without_alt'] . ' images missing alt text'];
    }
    
    // Link analysis
    if (count($links['internal']) === 0) {
        $score -= 10;
        $recommendations[] = ['status' => 'warning', 'message' => 'No internal links found'];
    }
    
    // Word count analysis
    if ($wordCount < 300) {
        $score -= 10;
        $recommendations[] = ['status' => 'warning', 'message' => 'Low word count (' . $wordCount . ' words)'];
    }
    
    // Robots meta analysis
    if (!empty($robots) && strpos($robots, 'noindex') !== false) {
        $score -= 30;
        $recommendations[] = ['status' => 'danger', 'message' => 'Page has noindex directive'];
    }
    
    // Ensure score is within range
    $score = max(0, min(100, $score));
    
    // Prepare response
    echo json_encode([
        'success' => true,
        'url' => $url,
        'basic' => [
            'title' => $title,
            'title_length' => $title_length,
            'description' => $description,
            'description_length' => $desc_length,
            'keywords' => $keywords,
            'author' => $author,
            'viewport' => $viewport,
            'robots' => $robots
        ],
        'headings' => [
            'h1_count' => $headings['h1']['count'],
            'h2_count' => $headings['h2']['count'],
            'h1_samples' => array_slice($headings['h1']['content'], 0, 3)
        ],
        'images' => [
            'total' => count($images['all']),
            'with_alt' => $images['with_alt_count'],
            'without_alt' => $images['without_alt_count']
        ],
        'links' => [
            'total' => count($links['all']),
            'internal' => count($links['internal']),
            'external' => count($links['external']),
            'nofollow' => count(array_filter($links['all'], function($l) { return $l['nofollow']; }))
        ],
        'content' => [
            'word_count' => $wordCount
        ],
        'score' => $score,
        'recommendations' => $recommendations
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}