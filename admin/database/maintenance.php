<?php
// admin/database/maintenance.php - Database check karne ke liye

// 🔴 YAHAN SARI QUERIES DAL SAKTE HO
$queries = [
    "SELECT COUNT(*) as total_countries FROM countries",
    "SELECT COUNT(*) as active_countries FROM countries WHERE is_active = 1",
    "SELECT code, name FROM countries ORDER BY name LIMIT 10",
    "SELECT code, name FROM countries WHERE is_active = 1 ORDER BY name LIMIT 10",
    "SELECT code, name FROM countries WHERE is_active = 1 ORDER BY CASE WHEN code IN ('US', 'GB', 'CA', 'AU', 'PK', 'IN', 'AE') THEN 0 ELSE 1 END, name LIMIT 10"
];

foreach($queries as $sql) {
    $stmt = $db->query($sql);
    $result = $stmt->fetchAll();
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}