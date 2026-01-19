<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    die('Access denied.');
}

$vendor_id = $_SESSION['user_id'];

// Get filter parameters from URL
$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_stock = $_GET['stock'] ?? '';
$filter_search = $_GET['search'] ?? '';

try {
    $db = getDB();
    
    // Build query conditions
    $conditions = ["p.vendor_id = ?"];
    $params = [$vendor_id];
    
    if (!empty($filter_category)) {
        $conditions[] = "p.category = ?";
        $params[] = $filter_category;
    }
    
    if (!empty($filter_status)) {
        if ($filter_status === 'out_of_stock') {
            $conditions[] = "p.stock = 0";
        } elseif ($filter_status === 'low_stock') {
            $conditions[] = "p.stock > 0 AND p.stock < 10";
        } elseif ($filter_status === 'in_stock') {
            $conditions[] = "p.stock >= 10";
        }
    }
    
    if (!empty($filter_search)) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $search_term = "%{$filter_search}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get inventory data
    $stmt = $db->prepare("
        SELECT 
            p.*,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(AVG(r.rating), 0) as avg_rating
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN reviews r ON p.id = r.product_id
        $where_clause
        GROUP BY p.id
        ORDER BY p.name
    ");
    $stmt->execute($params);
    $inventory_items = $stmt->fetchAll();
    
    // Get vendor info
    $stmt = $db->prepare("SELECT full_name, username, vendor_since FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock > 0 AND stock < 10 THEN 1 ELSE 0 END) as low_stock,
            SUM(stock) as total_stock,
            SUM(price * stock) as inventory_value
        FROM products
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $summary = $stmt->fetch();
    
} catch(PDOException $e) {
    die('Error loading inventory data.');
}

// Set headers for download/print
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Report - <?php echo htmlspecialchars($vendor['full_name'] ?? 'Vendor'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .print-header h1 {
            margin: 0;
            color: #4361ee;
        }
        .print-header .subtitle {
            color: #666;
            margin: 5px 0 15px 0;
        }
        .summary-box {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .summary-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #4361ee;
            margin: 5px 0;
        }
        .summary-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #f2f2f2;
            text-align: left;
            padding: 10px;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .stock-indicator {
            display: inline-block;
            width: 60px;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-right: 10px;
            vertical-align: middle;
        }
        .stock-fill {
            height: 100%;
            border-radius: 4px;
        }
        .stock-danger { background-color: #dc3545; }
        .stock-warning { background-color: #ffc107; }
        .stock-success { background-color: #28a745; }
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-out { background: #f8d7da; color: #721c24; }
        .status-low { background: #fff3cd; color: #856404; }
        .status-in { background: #d4edda; color: #155724; }
        .print-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .no-print {
            display: none;
        }
        @media print {
            body { margin: 0; padding: 20px; }
            .no-print { display: none; }
            .print-header { border-bottom: 2px solid #000; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
        @page {
            size: A4 landscape;
            margin: 20mm;
        }
    </style>
</head>
<body>
    <div class="print-header">
        <h1>Inventory Report</h1>
        <div class="subtitle">
            Vendor: <?php echo htmlspecialchars($vendor['full_name'] ?? 'Unknown'); ?>
        </div>
        <div class="subtitle">
            Generated: <?php echo date('F j, Y, g:i a'); ?> | 
            Report Period: All Time
        </div>
        <?php if (!empty($filter_category) || !empty($filter_status) || !empty($filter_search)): ?>
        <div class="subtitle">
            Filters: 
            <?php if (!empty($filter_category)) echo "Category: {$filter_category} | "; ?>
            <?php if (!empty($filter_status)) echo "Status: {$filter_status} | "; ?>
            <?php if (!empty($filter_search)) echo "Search: {$filter_search}"; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="summary-box">
        <div class="summary-item">
            <div class="summary-value"><?php echo $summary['total_products']; ?></div>
            <div class="summary-label">Total Products</div>
        </div>
        <div class="summary-item">
            <div class="summary-value"><?php echo $summary['out_of_stock']; ?></div>
            <div class="summary-label">Out of Stock</div>
        </div>
        <div class="summary-item">
            <div class="summary-value"><?php echo $summary['low_stock']; ?></div>
            <div class="summary-label">Low Stock</div>
        </div>
        <div class="summary-item">
            <div class="summary-value">$<?php echo number_format($summary['inventory_value'], 2); ?></div>
            <div class="summary-label">Inventory Value</div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="5%">ID</th>
                <th width="25%">Product Name</th>
                <th width="10%">Category</th>
                <th width="10%">Price</th>
                <th width="15%">Stock Level</th>
                <th width="10%">Status</th>
                <th width="10%">Total Sold</th>
                <th width="10%">Rating</th>
                <th width="5%">Featured</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($inventory_items as $item): 
                $stock_percentage = $item['stock'] > 0 ? min(100, ($item['stock'] / 100) * 100) : 0;
                $stock_class = $item['stock'] == 0 ? 'stock-danger' : ($item['stock'] < 10 ? 'stock-warning' : 'stock-success');
                $status_class = $item['stock'] == 0 ? 'status-out' : ($item['stock'] < 10 ? 'status-low' : 'status-in');
                $status_text = $item['stock'] == 0 ? 'Out of Stock' : ($item['stock'] < 10 ? 'Low Stock' : 'In Stock');
            ?>
            <tr>
                <td>#<?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                <td>$<?php echo number_format($item['price'], 2); ?></td>
                <td>
                    <div class="stock-indicator">
                        <div class="stock-fill <?php echo $stock_class; ?>" style="width: <?php echo $stock_percentage; ?>%"></div>
                    </div>
                    <?php echo $item['stock']; ?> units
                </td>
                <td>
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo $status_text; ?>
                    </span>
                </td>
                <td><?php echo $item['total_sold']; ?></td>
                <td>
                    <?php if ($item['avg_rating'] > 0): ?>
                        <?php echo number_format($item['avg_rating'], 1); ?>/5
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td><?php echo $item['featured'] ? 'Yes' : 'No'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="print-footer">
        <p>Inventory Management System | <?php echo SITE_NAME; ?> &copy; <?php echo date('Y'); ?></p>
        <p>Total Value: $<?php echo number_format($summary['inventory_value'], 2); ?> | 
           Total Stock: <?php echo $summary['total_stock']; ?> units</p>
    </div>
    
    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #4361ee; color: white; border: none; border-radius: 5px; cursor: pointer;">
            Print Report
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
            Close Window
        </button>
    </div>
    
    <script>
        // Auto-print when page loads
        window.onload = function() {
            window.print();
        };
        
        // Close window after print (optional)
        window.onafterprint = function() {
            // window.close();
        };
    </script>
</body>
</html>