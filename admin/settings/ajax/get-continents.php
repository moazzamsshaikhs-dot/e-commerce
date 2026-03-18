<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$continents = [
    ['id' => 'Asia', 'name' => 'Asia'],
    ['id' => 'Europe', 'name' => 'Europe'],
    ['id' => 'North America', 'name' => 'North America'],
    ['id' => 'South America', 'name' => 'South America'],
    ['id' => 'Africa', 'name' => 'Africa'],
    ['id' => 'Oceania', 'name' => 'Oceania'],
    ['id' => 'Antarctica', 'name' => 'Antarctica']
];

echo json_encode([
    'success' => true,
    'continents' => $continents
]);