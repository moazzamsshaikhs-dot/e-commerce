<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('Location: ../../index.php');
    exit;
}

try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get all tax rates
    $stmt = $db->prepare("
        SELECT 
            tc.class_name,
            tr.country,
            tr.state,
            tr.city,
            tr.postcode,
            tr.rate,
            tr.rate_name,
            tr.priority,
            tr.compound,
            tr.shipping
        FROM vendor_tax_rates tr
        LEFT JOIN vendor_tax_classes tc ON tr.tax_class_id = tc.id
        WHERE tr.vendor_id = ?
        ORDER BY tr.country, tr.state, tr.city, tr.postcode
    ");
    $stmt->execute([$vendor_id]);
    $tax_rates = $stmt->fetchAll();
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tax-rates-' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // Add headers
    fputcsv($output, [
        'Tax Class',
        'Country',
        'State/Region',
        'City',
        'Postcode',
        'Rate (%)',
        'Rate Name',
        'Priority',
        'Compound',
        'Apply to Shipping'
    ]);
    
    // Add data rows
    foreach ($tax_rates as $rate) {
        fputcsv($output, [
            $rate['class_name'],
            $rate['country'],
            $rate['state'] ?? '',
            $rate['city'] ?? '',
            $rate['postcode'] ?? '',
            $rate['rate'],
            $rate['rate_name'],
            $rate['priority'],
            $rate['compound'] ? 'Yes' : 'No',
            $rate['shipping'] ? 'Yes' : 'No'
        ]);
    }
    
    fclose($output);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error exporting tax rates: ' . $e->getMessage();
    header('Location: ../tax.php');
}
?>