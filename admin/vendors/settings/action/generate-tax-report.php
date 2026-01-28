<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $report_type = $data['report_type'] ?? 'sales_tax';
    $period = $data['period'] ?? 'this_month';
    $from_date = $data['from_date'] ?? date('Y-m-01');
    $to_date = $data['to_date'] ?? date('Y-m-d');
    
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        // Calculate date range
        $date_range = calculateDateRange($period, $from_date, $to_date);
        $start_date = $date_range['start'];
        $end_date = $date_range['end'];
        
        if ($report_type === 'sales_tax') {
            // Sales Tax Summary Report
            $report = generateSalesTaxReport($db, $vendor_id, $start_date, $end_date);
            
        } elseif ($report_type === 'tax_by_country') {
            // Tax by Country Report
            $report = generateTaxByCountryReport($db, $vendor_id, $start_date, $end_date);
            
        } elseif ($report_type === 'tax_by_class') {
            // Tax by Tax Class Report
            $report = generateTaxByClassReport($db, $vendor_id, $start_date, $end_date);
            
        } elseif ($report_type === 'exemption_report') {
            // Tax Exemption Report
            $report = generateExemptionReport($db, $vendor_id, $start_date, $end_date);
        }
        
        echo json_encode($report);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function calculateDateRange($period, $from_date, $to_date) {
    $today = date('Y-m-d');
    
    switch ($period) {
        case 'today':
            return ['start' => $today, 'end' => $today];
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return ['start' => $yesterday, 'end' => $yesterday];
        case 'this_week':
            return ['start' => date('Y-m-d', strtotime('monday this week')), 'end' => $today];
        case 'last_week':
            return [
                'start' => date('Y-m-d', strtotime('monday last week')),
                'end' => date('Y-m-d', strtotime('sunday last week'))
            ];
        case 'this_month':
            return ['start' => date('Y-m-01'), 'end' => $today];
        case 'last_month':
            return [
                'start' => date('Y-m-01', strtotime('-1 month')),
                'end' => date('Y-m-t', strtotime('-1 month'))
            ];
        case 'this_quarter':
            $quarter = ceil(date('n') / 3);
            $start_month = ($quarter - 1) * 3 + 1;
            return [
                'start' => date("Y-{$start_month}-01"),
                'end' => $today
            ];
        case 'last_quarter':
            $quarter = ceil(date('n') / 3) - 1;
            if ($quarter < 1) {
                $quarter = 4;
                $year = date('Y') - 1;
            } else {
                $year = date('Y');
            }
            $start_month = ($quarter - 1) * 3 + 1;
            $end_month = $start_month + 2;
            return [
                'start' => date("{$year}-{$start_month}-01"),
                'end' => date("{$year}-{$end_month}-t")
            ];
        case 'this_year':
            return ['start' => date('Y-01-01'), 'end' => $today];
        case 'last_year':
            $last_year = date('Y') - 1;
            return ['start' => "{$last_year}-01-01", 'end' => "{$last_year}-12-31"];
        case 'custom':
        default:
            return ['start' => $from_date, 'end' => $to_date];
    }
}

function generateSalesTaxReport($db, $vendor_id, $start_date, $end_date) {
    // Get orders with tax data
    $stmt = $db->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.order_date,
            o.total_amount,
            o.payment_status,
            SUM(oi.quantity) as total_items,
            SUM(oi.subtotal) as subtotal,
            (o.total_amount - SUM(oi.subtotal)) as tax_amount,
            ((o.total_amount - SUM(oi.subtotal)) / SUM(oi.subtotal) * 100) as tax_rate_percentage
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        AND o.order_date >= ? AND o.order_date <= ?
        AND o.payment_status = 'completed'
        GROUP BY o.id
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$vendor_id, $start_date, $end_date]);
    $orders = $stmt->fetchAll();
    
    $headers = ['Order #', 'Date', 'Items', 'Subtotal', 'Tax Amount', 'Tax Rate', 'Total'];
    $rows = [];
    $total_tax = 0;
    $total_subtotal = 0;
    $total_amount = 0;
    
    foreach ($orders as $order) {
        $rows[] = [
            $order['order_number'],
            date('d M Y', strtotime($order['order_date'])),
            $order['total_items'],
            '$' . number_format($order['subtotal'], 2),
            '$' . number_format($order['tax_amount'], 2),
            number_format($order['tax_rate_percentage'], 2) . '%',
            '$' . number_format($order['total_amount'], 2)
        ];
        
        $total_tax += $order['tax_amount'];
        $total_subtotal += $order['subtotal'];
        $total_amount += $order['total_amount'];
    }
    
    return [
        'success' => true,
        'report_title' => "Sales Tax Summary Report ($start_date to $end_date)",
        'headers' => $headers,
        'rows' => $rows,
        'total_tax' => number_format($total_tax, 2),
        'total_subtotal' => number_format($total_subtotal, 2),
        'total_amount' => number_format($total_amount, 2),
        'average_tax_rate' => $total_subtotal > 0 ? number_format(($total_tax / $total_subtotal * 100), 2) : '0.00'
    ];
}

function generateTaxByCountryReport($db, $vendor_id, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            o.shipping_address,
            COUNT(DISTINCT o.id) as order_count,
            SUM(oi.subtotal) as subtotal,
            (o.total_amount - SUM(oi.subtotal)) as tax_amount
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        AND o.order_date >= ? AND o.order_date <= ?
        AND o.payment_status = 'completed'
        GROUP BY JSON_EXTRACT(o.shipping_address, '$.country')
        ORDER BY tax_amount DESC
    ");
    $stmt->execute([$vendor_id, $start_date, $end_date]);
    $data = $stmt->fetchAll();
    
    $headers = ['Country', 'Orders', 'Taxable Sales', 'Tax Collected', 'Avg Tax Rate'];
    $rows = [];
    $total_tax = 0;
    
    foreach ($data as $row) {
        $address = json_decode($row['shipping_address'], true);
        $country = $address['country'] ?? 'Unknown';
        $tax_rate = $row['subtotal'] > 0 ? ($row['tax_amount'] / $row['subtotal'] * 100) : 0;
        
        $rows[] = [
            $country,
            $row['order_count'],
            '$' . number_format($row['subtotal'], 2),
            '$' . number_format($row['tax_amount'], 2),
            number_format($tax_rate, 2) . '%'
        ];
        
        $total_tax += $row['tax_amount'];
    }
    
    return [
        'success' => true,
        'report_title' => "Tax by Country Report ($start_date to $end_date)",
        'headers' => $headers,
        'rows' => $rows,
        'total_tax' => number_format($total_tax, 2)
    ];
}

function generateTaxByClassReport($db, $vendor_id, $start_date, $end_date) {
    // This is a simplified version - you'd need to join with product tax classes
    return [
        'success' => true,
        'report_title' => "Tax by Class Report ($start_date to $end_date)",
        'headers' => ['Tax Class', 'Orders', 'Taxable Sales', 'Tax Collected'],
        'rows' => [],
        'total_tax' => '0.00'
    ];
}

function generateExemptionReport($db, $vendor_id, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT 
            te.customer_name,
            te.customer_email,
            te.exemption_type,
            COUNT(DISTINCT o.id) as exempt_orders,
            SUM(oi.subtotal) as exempt_amount
        FROM vendor_tax_exemptions te
        LEFT JOIN orders o ON JSON_EXTRACT(o.shipping_address, '$.email') = te.customer_email
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.id AND p.vendor_id = ?
        WHERE te.vendor_id = ?
        AND o.order_date >= ? AND o.order_date <= ?
        AND o.payment_status = 'completed'
        GROUP BY te.id
        ORDER BY exempt_amount DESC
    ");
    $stmt->execute([$vendor_id, $vendor_id, $start_date, $end_date]);
    $data = $stmt->fetchAll();
    
    $headers = ['Customer', 'Email', 'Exemption Type', 'Exempt Orders', 'Exempt Amount'];
    $rows = [];
    $total_exempt = 0;
    
    foreach ($data as $row) {
        $rows[] = [
            $row['customer_name'],
            $row['customer_email'],
            ucfirst($row['exemption_type']),
            $row['exempt_orders'],
            '$' . number_format($row['exempt_amount'], 2)
        ];
        
        $total_exempt += $row['exempt_amount'];
    }
    
    return [
        'success' => true,
        'report_title' => "Tax Exemption Report ($start_date to $end_date)",
        'headers' => $headers,
        'rows' => $rows,
        'total_tax' => number_format($total_exempt, 2)
    ];
}
?>