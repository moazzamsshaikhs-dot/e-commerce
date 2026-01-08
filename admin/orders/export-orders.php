    <?php
    require_once '../includes/config.php';
    require_once '../includes/auth-check.php';

    // Check if user is admin
    if ($_SESSION['user_type'] !== 'admin') {
        $_SESSION['error'] = 'Access denied. Admin only.';
        redirect(SITE_URL . 'index.php');
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=orders_' . date('Y-m-d_H-i') . '.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, [
        'Order ID',
        'Order Number',
        'Customer Name',
        'Customer Email',
        'Customer Phone',
        'Order Date',
        'Status',
        'Payment Status',
        'Payment Method',
        'Total Amount',
        'Items Count',
        'Shipping Method',
        'Tracking Number',
        'Shipping Address',
        'Billing Address',
        // 'Created At'
    ]);

    try {
        $db = getDB();
        
        // Check if specific IDs are requested
        $where = ["1=1"];
        $params = [];
        
        if (isset($_GET['ids']) && !empty($_GET['ids'])) {
            $ids = explode(',', $_GET['ids']);
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $where[] = "o.id IN ($placeholders)";
            $params = array_merge($params, $ids);
        }
        
        // Apply filters if any
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $where[] = "o.status = ?";
            $params[] = $_GET['status'];
        }
        
        if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
            $where[] = "DATE(o.order_date) >= ?";
            $params[] = $_GET['start_date'];
        }
        
        if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
            $where[] = "DATE(o.order_date) <= ?";
            $params[] = $_GET['end_date'];
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Get orders for export
        $orders_sql = "SELECT o.*, 
                            u.full_name,
                            u.email,
                            u.phone,
                            COUNT(oi.id) as items_count
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    LEFT JOIN order_items oi ON o.id = oi.order_id
                    WHERE $where_sql
                    GROUP BY o.id
                    ORDER BY o.order_date DESC";
        
        $stmt = $db->prepare($orders_sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        // Add data rows
        foreach ($orders as $order) {
            fputcsv($output, [
                $order['id'],
                $order['order_number'],
                $order['full_name'],
                $order['email'],
                $order['phone'],
                $order['order_date'],
                $order['status'],
                $order['payment_status'],
                $order['payment_method'],
                $order['total_amount'],
                $order['items_count'],
                $order['shipping_method'],
                $order['tracking_number'],
                $order['shipping_address'],
                $order['billing_address'],
                // $order['created_at']
            ]);
        }
        
    } catch(PDOException $e) {
        // If error, output as plain text
        header('Content-Type: text/plain');
        echo 'Error exporting orders: ' . $e->getMessage();
    }

    fclose($output);
    exit;