<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$vendor_id = $_SESSION['user_id'];

if (!$year || $year < 2000 || $year > date('Y')) {
    die('Invalid year');
}

try {
    $db = getDB();
    
    // Get vendor earnings for the year
    $stmt = $db->prepare("
        SELECT 
            YEAR(created_at) as tax_year,
            COUNT(*) as total_transactions,
            SUM(vendor_amount) as total_earnings,
            SUM(commission_amount) as total_commission,
            MIN(created_at) as first_payment,
            MAX(created_at) as last_payment
        FROM vendor_earnings 
        WHERE vendor_id = ? AND status = 'paid' AND YEAR(created_at) = ?
        GROUP BY YEAR(created_at)
    ");
    $stmt->execute([$vendor_id, $year]);
    $data = $stmt->fetch();
    
    if (!$data) {
        die('No earnings data found for this year');
    }
    
    // Get vendor info
    $stmt = $db->prepare("SELECT full_name, email, tax_id, address, city, country FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Generate PDF (simplified - in production use a PDF library like TCPDF or FPDF)
    header('Content-Type: text/html');
    echo "<html><head><title>Tax Form {$year}</title>";
    echo "<style>
        body { font-family: Arial; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; }
        .form-title { font-size: 24px; font-weight: bold; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ccc; }
        .row { margin: 10px 0; }
        .label { font-weight: bold; display: inline-block; width: 200px; }
    </style>";
    echo "</head><body>";
    
    echo "<div class='header'>";
    echo "<div class='form-title'>1099-K Tax Form</div>";
    echo "<div>Tax Year: {$year}</div>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>Vendor Information</h3>";
    echo "<div class='row'><span class='label'>Name:</span> " . htmlspecialchars($vendor['full_name']) . "</div>";
    echo "<div class='row'><span class='label'>Email:</span> " . htmlspecialchars($vendor['email']) . "</div>";
    echo "<div class='row'><span class='label'>Tax ID:</span> " . htmlspecialchars(maskTaxID($vendor['tax_id'] ?? 'Not provided')) . "</div>";
    echo "<div class='row'><span class='label'>Address:</span> " . htmlspecialchars($vendor['address'] ?? '') . "</div>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>Earnings Summary</h3>";
    echo "<div class='row'><span class='label'>Total Transactions:</span> " . $data['total_transactions'] . "</div>";
    echo "<div class='row'><span class='label'>Total Earnings:</span> $" . number_format($data['total_earnings'], 2) . "</div>";
    echo "<div class='row'><span class='label'>Total Commission:</span> $" . number_format($data['total_commission'], 2) . "</div>";
    echo "<div class='row'><span class='label'>First Payment:</span> " . date('M d, Y', strtotime($data['first_payment'])) . "</div>";
    echo "<div class='row'><span class='label'>Last Payment:</span> " . date('M d, Y', strtotime($data['last_payment'])) . "</div>";
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h3>Important Information</h3>";
    echo "<p>This form is for informational purposes only. Please consult with your tax advisor.</p>";
    echo "<p>You are required to report all income on your tax return regardless of whether you receive a form.</p>";
    echo "</div>";
    
    echo "</body></html>";
    
} catch(PDOException $e) {
    error_log("Tax form download error: " . $e->getMessage());
    die('Error generating tax form');
}

function maskTaxID($taxId) {
    if (!$taxId) return 'Not provided';
    if (strlen($taxId) <= 4) return '••••';
    return '••••' . substr($taxId, -4);
}
?>