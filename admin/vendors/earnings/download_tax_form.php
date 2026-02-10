<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    header('HTTP/1.0 403 Forbidden');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate the vendor is eligible for this tax year
try {
    $db = getDB();
    
    // Check if vendor has earnings for this year
    $stmt = $db->prepare("
        SELECT SUM(vendor_amount) as total_earnings
        FROM vendor_earnings 
        WHERE vendor_id = ? AND YEAR(created_at) = ? AND status = 'paid'
    ");
    $stmt->execute([$vendor_id, $year]);
    $earnings = $stmt->fetchColumn();
    
    // Check tax threshold
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tax_threshold'");
    $stmt->execute();
    $tax_threshold = $stmt->fetchColumn() ?: 600;
    
    if ($earnings < $tax_threshold) {
        $_SESSION['error'] = 'You are not eligible for a 1099-K form for this tax year.';
        header('Location: tax.php');
        exit();
    }
    
    // Generate PDF (simplified - in production, use a proper PDF library like TCPDF or Dompdf)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="1099-K_' . $year . '.pdf"');
    
    // Simplified PDF content - in production, generate proper PDF
    echo "FORM 1099-K\n";
    echo "Payment Card and Third Party Network Transactions\n";
    echo "Tax Year: $year\n";
    echo "Vendor ID: $vendor_id\n";
    echo "Total Amount: $" . number_format($earnings, 2) . "\n";
    echo "This is a sample tax form. In production, generate proper PDF.\n";
    
    logActivity($vendor_id, 'download_tax_form', 'Downloaded tax form for year: ' . $year);
    exit();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error generating tax form: ' . $e->getMessage();
    header('Location: tax.php');
    exit();
}
?>