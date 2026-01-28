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
            tr.*,
            tc.class_name,
            c.name as country_name
        FROM vendor_tax_rates tr
        LEFT JOIN vendor_tax_classes tc ON tr.tax_class_id = tc.id
        LEFT JOIN countries c ON tr.country = c.code
        WHERE tr.vendor_id = ?
        ORDER BY tr.country, tr.state, tr.rate DESC
    ");
    $stmt->execute([$vendor_id]);
    $tax_rates = $stmt->fetchAll();
    
    // Create CSV content
    $csv = "Tax Class,Country,State,City,Postcode,Rate (%),Rate Name,Priority,Compound,Apply to Shipping,Created\n";
    
    foreach ($tax_rates as $rate) {
        $csv .= sprintf(
            '"%s","%s","%s","%s","%s",%.2f,"%s",%d,"%s","%s","%s"' . "\n",
            $rate['class_name'] ?? 'Standard',
            $rate['country_name'] ?? $rate['country'],
            $rate['state'] ?? '',
            $rate['city'] ?? '',
            $rate['postcode'] ?? '',
            $rate['rate'],
            $rate['rate_name'],
            $rate['priority'],
            $rate['compound'] ? 'Yes' : 'No',
            $rate['shipping'] ? 'Yes' : 'No',
            $rate['created_at']
        );
    }
    
    // Set headers for download
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="tax-rates-export-' . date('Y-m-d-H-i-s') . '.csv"');
    
    // Output CSV
    echo $csv;
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error exporting tax rates: ' . $e->getMessage();
    header('Location: ../tax.php');
}
?>