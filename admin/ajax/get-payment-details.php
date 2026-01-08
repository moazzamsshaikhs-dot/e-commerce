<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit;
}

$payment_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT p.*, 
                                 o.order_number,
                                 o.total_amount as order_total,
                                 o.status as order_status,
                                 u.full_name,
                                 u.email,
                                 u.phone,
                                 u.address
                          FROM payments p
                          LEFT JOIN orders o ON p.order_id = o.id
                          LEFT JOIN users u ON p.user_id = u.id
                          WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit;
    }
    
    // Status badge
    switch($payment['status']) {
        case 'completed': 
            $status_badge = '<span class="badge bg-success">Completed</span>';
            break;
        case 'pending': 
            $status_badge = '<span class="badge bg-warning">Pending</span>';
            break;
        case 'failed': 
            $status_badge = '<span class="badge bg-danger">Failed</span>';
            break;
        case 'refunded': 
            $status_badge = '<span class="badge bg-info">Refunded</span>';
            break;
        default: 
            $status_badge = '<span class="badge bg-secondary">Unknown</span>';
    }
    
    // Method badge
    switch($payment['payment_method']) {
        case 'card': 
            $method_badge = '<span class="badge bg-primary"><i class="fas fa-credit-card me-1"></i>Card</span>';
            break;
        case 'paypal': 
            $method_badge = '<span class="badge bg-info"><i class="fab fa-paypal me-1"></i>PayPal</span>';
            break;
        case 'cod': 
            $method_badge = '<span class="badge bg-warning"><i class="fas fa-money-bill-wave me-1"></i>Cash on Delivery</span>';
            break;
        case 'upi': 
            $method_badge = '<span class="badge bg-success"><i class="fas fa-mobile-alt me-1"></i>UPI</span>';
            break;
        default: 
            $method_badge = '<span class="badge bg-secondary">' . ucfirst($payment['payment_method']) . '</span>';
    }
    
    // Format dates
    $created_at = date('d M Y, h:i A', strtotime($payment['created_at']));
    
    // Parse payment details
    $payment_details = 'N/A';
    if (!empty($payment['payment_details'])) {
        $details = json_decode($payment['payment_details'], true);
        if (is_array($details)) {
            $payment_details = '';
            foreach ($details as $key => $value) {
                if (!empty($value)) {
                    $payment_details .= ucfirst($key) . ': ' . $value . '<br>';
                }
            }
        }
    }
    
    // Build HTML
    $html = '
    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label text-muted">Payment ID</label>
                <div class="fw-bold">#' . $payment['id'] . '</div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted">Transaction ID</label>
                <div class="fw-bold">' . ($payment['transaction_id'] ?: 'N/A') . '</div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted">Customer</label>
                <div class="fw-bold">' . htmlspecialchars($payment['full_name']) . '</div>
                <div class="text-muted small">' . htmlspecialchars($payment['email']) . '</div>
                <div class="text-muted small">' . htmlspecialchars($payment['phone']) . '</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label text-muted">Amount</label>
                <div class="fw-bold fs-4 text-primary">₹' . number_format($payment['amount'], 2) . '</div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted">Payment Method</label>
                <div>' . $method_badge . '</div>
            </div>
            <div class="mb-3">
                <label class="form-label text-muted">Status</label>
                <div>' . $status_badge . '</div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <div class="card border">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Order Information</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-2">
                                <span class="text-muted">Order Number:</span>
                                <strong>' . ($payment['order_number'] ?: 'N/A') . '</strong>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted">Order Status:</span>
                                <span class="badge bg-info">' . $payment['order_status'] . '</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-2">
                                <span class="text-muted">Order Total:</span>
                                <strong>₹' . number_format($payment['order_total'], 2) . '</strong>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted">Payment Date:</span>
                                <strong>' . $created_at . '</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <div class="card border">
                <div class="card-header bg-light">
                    <h6 class="mb-0">Payment Details</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Currency:</strong></td>
                                <td>' . ($payment['currency'] ?: 'INR') . '</td>
                            </tr>
                            <tr>
                                <td><strong>Payment Gateway:</strong></td>
                                <td>' . ucfirst($payment['payment_method']) . '</td>
                            </tr>
                            <tr>
                                <td><strong>Payment Details:</strong></td>
                                <td>' . $payment_details . '</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    if (!empty($payment['address'])) {
        $html .= '
        <div class="row mt-3">
            <div class="col-12">
                <div class="card border">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Customer Address</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">' . nl2br(htmlspecialchars($payment['address'])) . '</p>
                    </div>
                </div>
            </div>
        </div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>