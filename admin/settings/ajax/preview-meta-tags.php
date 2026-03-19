<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$url = $_POST['url'] ?? SITE_URL;

// Truncate if too long
if (strlen($title) > 60) {
    $title = substr($title, 0, 57) . '...';
}

if (strlen($description) > 160) {
    $description = substr($description, 0, 157) . '...';
}

// Format URL for display
$display_url = preg_replace('#^https?://#', '', rtrim($url, '/'));

$html = '
<div class="google-preview">
    <div class="google-preview-header">
        <span class="google-icon"></span>
        <span class="google-url">' . htmlspecialchars($display_url) . '</span>
    </div>
    <div class="google-preview-title">
        <a href="#">' . htmlspecialchars($title ?: 'Your Website Title') . '</a>
    </div>
    <div class="google-preview-description">
        ' . htmlspecialchars($description ?: 'Your website description will appear here in search results.') . '
    </div>
</div>

<style>
.google-preview {
    font-family: arial, sans-serif;
    max-width: 600px;
    padding: 15px;
    background: white;
    border: 1px solid #dadce0;
    border-radius: 8px;
}
.google-preview-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 5px;
}
.google-icon {
    width: 20px;
    height: 20px;
    background: #4285f4;
    border-radius: 50%;
    display: inline-block;
}
.google-url {
    color: #202124;
    font-size: 14px;
}
.google-preview-title a {
    color: #1a0dab;
    font-size: 20px;
    text-decoration: none;
    line-height: 1.3;
}
.google-preview-title a:hover {
    text-decoration: underline;
}
.google-preview-description {
    color: #4d5156;
    font-size: 14px;
    line-height: 1.58;
    margin-top: 5px;
}
</style>
';

echo json_encode([
    'success' => true,
    'preview' => $html
]);