<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$robots_content = $_POST['robots_content'] ?? '';

if (empty($robots_content)) {
    echo json_encode(['valid' => true]);
    exit;
}

$issues = [];

// Check for common issues
if (preg_match('/disallow:\s*\/\s*/i', $robots_content)) {
    $issues[] = 'Warning: Disallow all rule (/) may block all crawlers';
}

if (preg_match('/allow:\s*\/\s*/i', $robots_content)) {
    $issues[] = 'Note: Allow all rule (/) found';
}

if (strpos($robots_content, 'Sitemap:') === false) {
    $issues[] = 'Suggestion: Add Sitemap directive';
}

// Check syntax
$lines = explode("\n", $robots_content);
foreach ($lines as $line_num => $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) continue;
    
    if (!preg_match('/^(user-agent|disallow|allow|sitemap|crawl-delay|host):\s*/i', $line)) {
        $issues[] = 'Warning: Line ' . ($line_num + 1) . ' has invalid syntax: ' . htmlspecialchars($line);
    }
}

echo json_encode([
    'valid' => count($issues) === 0,
    'issues' => $issues
]);