<?php
// ajax/get-countries.php - AJAX se countries fetch karne ke liye
require_once '../includes/config.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    $type = $_GET['type'] ?? 'all';
    
    if ($type === 'popular') {
        // 🔴 YAHAN QUERY LAGAO - Popular countries first
        $query = "
            SELECT code, name FROM countries 
            WHERE is_active = 1 
            ORDER BY CASE 
                WHEN code IN ('US', 'GB', 'CA', 'AU', 'PK', 'IN', 'AE') THEN 0 
                ELSE 1 
            END, name
        ";
    } else {
        // 🔴 YAHAN QUERY LAGAO - All active countries
        $query = "SELECT code, name FROM countries WHERE is_active = 1 ORDER BY name";
    }
    
    $stmt = $db->query($query);
    $countries = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $countries]);
    
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}