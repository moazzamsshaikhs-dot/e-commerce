<?php
// admin/vendors/inventory/download-template.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    die('Access denied');
}

// Generate CSV template
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventory-template.csv"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Headers
fputcsv($output, ['name', 'description', 'price', 'stock', 'category']);

// Sample data
fputcsv($output, ['Sample Product 1', 'Description for sample product', '99.99', '10', 'Electronics']);
fputcsv($output, ['Sample Product 2', 'Another sample product description', '149.99', '5', 'Clothing']);
fputcsv($output, ['', '', '', '', '']); // Empty row
fputcsv($output, ['Instructions:', 'Price must be numeric', 'Stock must be integer', 'Category optional', '']);

fclose($output);
exit();
?>