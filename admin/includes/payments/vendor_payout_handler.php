<?php
// admin/includes/payments/vendor_payout_handler.php
require_once dirname(__DIR__, 3) . '/includes/config.php';
require_once dirname(__DIR__, 3) . '/includes/auth-check.php';
require_once 'admin_payment_processor.php';

class VendorPayoutHandler {
    
    private $db;
    private $processor;
    
    public function __construct($db) {
        $this->db = $db;
        $this->processor = new AdminPaymentProcessor($db);
    }
    
    /**
     * Auto-process all pending payouts for delivered orders
     */
    public function processDeliveredOrders() {
        // Get all delivered orders that haven't been paid
        $stmt = $this->db->prepare("
            SELECT DISTINCT 
                o.id as order_id,
                oi.vendor_id,
                SUM(oi.subtotal) as vendor_total
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN vendor_earnings ve ON o.id = ve.order_id
            WHERE o.status = 'delivered' 
                AND ve.id IS NULL
                AND o.payment_status = 'completed'
            GROUP BY o.id, oi.vendor_id
        ");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($orders as $order) {
            // Get commission for this vendor/order
            $commissionStmt = $this->db->prepare("
                SELECT SUM(commission_amount) as total_commission
                FROM vendor_commissions
                WHERE order_id = ? AND vendor_id = ? AND status = 'pending'
            ");
            $commissionStmt->execute([$order['order_id'], $order['vendor_id']]);
            $commission = $commissionStmt->fetchColumn();
            
            if ($commission > 0) {
                $result = $this->processor->processVendorPayment(
                    $order['vendor_id'],
                    $order['order_id'],
                    $commission
                );
                
                $results[] = [
                    'order_id' => $order['order_id'],
                    'vendor_id' => $order['vendor_id'],
                    'amount' => $commission,
                    'result' => $result
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Process monthly payouts for all vendors
     */
    public function processMonthlyPayouts() {
        // Get all vendors with pending earnings
        $stmt = $this->db->prepare("
            SELECT 
                vendor_id,
                SUM(amount) as total_pending
            FROM vendor_earnings
            WHERE status = 'pending'
            GROUP BY vendor_id
            HAVING total_pending > 0
        ");
        $stmt->execute();
        $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        foreach ($vendors as $vendor) {
            // Create withdrawal request
            $insertStmt = $this->db->prepare("
                INSERT INTO vendor_withdrawal_requests 
                (vendor_id, request_amount, available_balance, withdrawal_method, status, created_at)
                VALUES (?, ?, ?, 'auto', 'pending', NOW())
            ");
            $insertStmt->execute([
                $vendor['vendor_id'],
                $vendor['total_pending'],
                $vendor['total_pending']
            ]);
            
            $requestId = $this->db->lastInsertId();
            
            // Auto-approve and process
            $result = $this->processor->processWithdrawalRequest(
                $requestId,
                'approve',
                1, // Admin ID 1 (system)
                'Auto monthly payout'
            );
            
            $results[] = [
                'vendor_id' => $vendor['vendor_id'],
                'amount' => $vendor['total_pending'],
                'result' => $result
            ];
        }
        
        return $results;
    }
    
    /**
     * Generate payout report
     */
    public function generatePayoutReport($startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(ve.paid_date) as payout_date,
                u.id as vendor_id,
                u.full_name as vendor_name,
                u.email as vendor_email,
                COUNT(DISTINCT ve.order_id) as orders_count,
                SUM(ve.amount) as total_amount,
                GROUP_CONCAT(DISTINCT ve.transaction_id) as transaction_ids
            FROM vendor_earnings ve
            JOIN users u ON ve.vendor_id = u.id
            WHERE ve.status = 'paid'
                AND ve.paid_date BETWEEN ? AND ?
            GROUP BY DATE(ve.paid_date), u.id, u.full_name, u.email
            ORDER BY payout_date DESC, vendor_name
        ");
        $stmt->execute([$startDate, $endDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Export payouts to CSV
     */
    public function exportPayoutsToCSV($startDate, $endDate) {
        $data = $this->generatePayoutReport($startDate, $endDate);
        
        $filename = 'payouts_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Vendor ID', 'Vendor Name', 'Email', 'Orders', 'Amount', 'Transaction IDs']);
        
        foreach ($data as $row) {
            fputcsv($output, [
                $row['payout_date'],
                $row['vendor_id'],
                $row['vendor_name'],
                $row['vendor_email'],
                $row['orders_count'],
                number_format($row['total_amount'], 2),
                $row['transaction_ids']
            ]);
        }
        
        fclose($output);
        exit;
    }
}

// Handle auto-processing if called directly
if (basename($_SERVER['PHP_SELF']) == 'vendor_payout_handler.php') {
    $db = getDB();
    $handler = new VendorPayoutHandler($db);
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'process_delivered':
                $results = $handler->processDeliveredOrders();
                echo json_encode(['success' => true, 'results' => $results]);
                break;
                
            case 'monthly':
                $results = $handler->processMonthlyPayouts();
                echo json_encode(['success' => true, 'results' => $results]);
                break;
                
            case 'export':
                $startDate = $_GET['start_date'] ?? date('Y-m-01');
                $endDate = $_GET['end_date'] ?? date('Y-m-t');
                $handler->exportPayoutsToCSV($startDate, $endDate);
                break;
        }
    }
}