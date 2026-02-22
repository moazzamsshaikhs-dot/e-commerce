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
    
    // Get detailed earnings for the year
    $stmt = $db->prepare("
        SELECT 
            ve.*,
            o.order_number,
            o.order_date,
            p.name as product_name
        FROM vendor_earnings ve
        JOIN orders o ON ve.order_id = o.id
        JOIN products p ON ve.product_id = p.id
        WHERE ve.vendor_id = ? AND YEAR(ve.created_at) = ?
        ORDER BY ve.created_at DESC
    ");
    $stmt->execute([$vendor_id, $year]);
    $earnings = $stmt->fetchAll();
    
    // Get summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(vendor_amount) as total_earnings,
            SUM(commission_amount) as total_commission,
            AVG(vendor_amount) as average_earning,
            MAX(vendor_amount) as max_earning,
            MIN(vendor_amount) as min_earning
        FROM vendor_earnings 
        WHERE vendor_id = ? AND YEAR(created_at) = ?
    ");
    $stmt->execute([$vendor_id, $year]);
    $summary = $stmt->fetch();
    
    // Get vendor info
    $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Generate HTML report
    header('Content-Type: text/html');
    echo "<html><head><title>Earnings Report {$year}</title>";
    echo "<style>
        body { font-family: Arial; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #4CAF50; color: white; padding: 10px; text-align: left; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        tr:hover { background-color: #f5f5f5; }
        .summary { background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .summary-item { margin: 10px 0; }
        .label { font-weight: bold; display: inline-block; width: 200px; }
        .total { font-size: 18px; color: #4CAF50; }
    </style>";
    echo "</head><body>";
    
    echo "<div class='header'>";
    echo "<h1>Earnings Report - {$year}</h1>";
    echo "<p>Generated for: " . htmlspecialchars($vendor['full_name']) . " (" . htmlspecialchars($vendor['email']) . ")</p>";
    echo "<p>Generated on: " . date('F d, Y H:i:s') . "</p>";
    echo "</div>";
    
    // Summary section
    echo "<div class='summary'>";
    echo "<h2>Summary</h2>";
    echo "<div class='summary-item'><span class='label'>Total Transactions:</span> " . $summary['total_transactions'] . "</div>";
    echo "<div class='summary-item'><span class='label'>Total Earnings:</span> $" . number_format($summary['total_earnings'], 2) . "</div>";
    echo "<div class='summary-item'><span class='label'>Total Commission:</span> $" . number_format($summary['total_commission'], 2) . "</div>";
    echo "<div class='summary-item'><span class='label'>Average per Transaction:</span> $" . number_format($summary['average_earning'], 2) . "</div>";
    echo "<div class='summary-item'><span class='label'>Highest Earning:</span> $" . number_format($summary['max_earning'], 2) . "</div>";
    echo "<div class='summary-item'><span class='label'>Lowest Earning:</span> $" . number_format($summary['min_earning'], 2) . "</div>";
    echo "</div>";
    
    // Transactions table
    if (!empty($earnings)) {
        echo "<h2>Transaction Details</h2>";
        echo "<table>";
        echo "<tr>
                <th>Date</th>
                <th>Order #</th>
                <th>Product</th>
                <th>Amount</th>
                <th>Commission</th>
                <th>Net Earnings</th>
                <th>Status</th>
              </tr>";
        
        foreach($earnings as $earning) {
            $status_color = '';
            if ($earning['status'] == 'paid') $status_color = 'green';
            elseif ($earning['status'] == 'pending') $status_color = 'orange';
            else $status_color = 'gray';
            
            echo "<tr>";
            echo "<td>" . date('M d, Y', strtotime($earning['created_at'])) . "</td>";
            echo "<td>" . htmlspecialchars($earning['order_number']) . "</td>";
            echo "<td>" . htmlspecialchars($earning['product_name']) . "</td>";
            echo "<td>$" . number_format($earning['product_price'], 2) . "</td>";
            echo "<td>$" . number_format($earning['commission_amount'], 2) . "</td>";
            echo "<td>$" . number_format($earning['vendor_amount'], 2) . "</td>";
            echo "<td style='color: {$status_color}'>" . ucfirst($earning['status']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No transactions found for {$year}.</p>";
    }
    
    echo "</body></html>";
    
} catch(PDOException $e) {
    error_log("Earnings report error: " . $e->getMessage());
    die('Error generating report');
}
?>