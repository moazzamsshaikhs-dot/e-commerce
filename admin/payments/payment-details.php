<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Payment ID is required.';
    redirect('index.php');
}

$payment_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get payment details
    $stmt = $db->prepare("SELECT p.*, 
                                 u.full_name as customer_name,
                                 u.email as customer_email,
                                 u.phone as customer_phone,
                                 o.order_number,
                                 o.total_amount as order_total,
                                 o.status as order_status,
                                 o.payment_method as order_payment_method
                          FROM payments p
                          LEFT JOIN users u ON p.user_id = u.id
                          LEFT JOIN orders o ON p.order_id = o.id
                          WHERE p.id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $_SESSION['error'] = 'Payment not found.';
        redirect('index.php');
    }
    
    // Get refunds for this payment
    $stmt = $db->prepare("SELECT r.*, u.full_name as processed_by_name
                          FROM refunds r
                          LEFT JOIN users u ON r.processed_by = u.id
                          WHERE r.payment_id = ?
                          ORDER BY r.created_at DESC");
    $stmt->execute([$payment_id]);
    $refunds = $stmt->fetchAll();
    
    // Calculate total refunded
    $total_refunded = 0;
    foreach ($refunds as $refund) {
        if ($refund['status'] == 'completed') {
            $total_refunded += $refund['refund_amount'];
        }
    }
    
    $net_amount = $payment['amount'] - $total_refunded;
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading payment details: ' . $e->getMessage();
    redirect('index.php');
}

$page_title = 'Payment Details #' . ($payment['transaction_id'] ?? $payment['id']);
require_once '../includes/header.php';

// Status badge color
$status_color = 'secondary';
$status_icon = 'question-circle';
switch($payment['status']) {
    case 'pending': 
        $status_color = 'warning'; 
        $status_icon = 'clock'; 
        break;
    case 'completed': 
        $status_color = 'success'; 
        $status_icon = 'check-circle'; 
        break;
    case 'failed': 
        $status_color = 'danger'; 
        $status_icon = 'times-circle'; 
        break;
    case 'refunded': 
        $status_color = 'info'; 
        $status_icon = 'undo'; 
        break;
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Payment Details</h1>
                <p class="text-muted mb-0">
                    <?php if ($payment['transaction_id']): ?>
                    Transaction: <?php echo $payment['transaction_id']; ?>
                    <?php else: ?>
                    Payment ID: #<?php echo $payment['id']; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i> Back to Payments
                </a>
                <a href="receipt.php?id=<?php echo $payment_id; ?>" target="_blank" class="btn btn-primary">
                    <i class="fas fa-print me-2"></i> Print Receipt
                </a>
            </div>
        </div>
        
        <!-- Payment Status & Actions -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title mb-3">Payment Status</h5>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-<?php echo $status_color; ?> fs-6 p-2 me-3">
                                        <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                    
                                    <!-- Status Actions -->
                                    <div class="btn-group">
                                        <?php if ($payment['status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                                type="button" data-bs-toggle="dropdown" 
                                                aria-expanded="false">
                                            Change Status
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item text-success" href="#" 
                                                   onclick="updatePaymentStatus('completed')">
                                                    <i class="fas fa-check me-2"></i>Mark as Completed
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger" href="#" 
                                                   onclick="updatePaymentStatus('failed')">
                                                    <i class="fas fa-times me-2"></i>Mark as Failed
                                                </a>
                                            </li>
                                        </ul>
                                        <?php elseif ($payment['status'] == 'completed' && $net_amount > 0): ?>
                                        <a href="refund.php?payment_id=<?php echo $payment_id; ?>" 
                                           class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-undo me-2"></i> Process Refund
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Payment Information -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Transaction Information</label>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Transaction ID</small>
                                            <div class="font-monospace">
                                                <?php echo $payment['transaction_id'] ?: 'N/A'; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Payment Method</small>
                                            <div>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo strtoupper($payment['payment_method']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($payment['payment_details']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block">Payment Details</small>
                                        <pre class="bg-light p-2 small"><?php 
                                            try {
                                                $details = json_decode($payment['payment_details'], true);
                                                echo json_encode($details, JSON_PRETTY_PRINT); 
                                            } catch(Exception $e) {
                                                echo htmlspecialchars($payment['payment_details']);
                                            }
                                        ?></pre>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="card-title mb-3">Payment Summary</h5>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Payment Date</small>
                                        <strong><?php echo date('d M Y, h:i A', strtotime($payment['created_at'])); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Currency</small>
                                        <strong><?php echo $payment['currency']; ?></strong>
                                    </div>
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Original Amount:</small>
                                            <strong>$<?php echo number_format($payment['amount'], 2); ?></strong>
                                        </div>
                                        
                                        <?php if ($total_refunded > 0): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-danger">Total Refunded:</small>
                                            <strong class="text-danger">-$<?php echo number_format($total_refunded, 2); ?></strong>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                            <small class="text-muted">Net Amount:</small>
                                            <strong class="text-primary fs-5">$<?php echo number_format($net_amount, 2); ?></strong>
                                        </div>
                                        <?php else: ?>
                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                            <small class="text-muted">Amount Due:</small>
                                            <strong class="text-primary fs-5">$<?php echo number_format($payment['amount'], 2); ?></strong>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Refund Status -->
                                <?php if ($total_refunded > 0): ?>
                                <div class="alert alert-info mt-3 py-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Refunds Issued</strong>
                                    <div class="mt-1 small">
                                        <?php echo count($refunds); ?> refund(s) totaling $<?php echo number_format($total_refunded, 2); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="sendPaymentReceipt()">
                                <i class="fas fa-envelope me-2"></i> Send Receipt
                            </button>
                            
                            <?php if ($payment['status'] == 'completed' && $net_amount > 0): ?>
                            <a href="refund.php?payment_id=<?php echo $payment_id; ?>" class="btn btn-outline-warning">
                                <i class="fas fa-undo me-2"></i> Process Refund
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($payment['status'] == 'pending'): ?>
                            <button class="btn btn-outline-success" onclick="updatePaymentStatus('completed')">
                                <i class="fas fa-check-circle me-2"></i> Mark as Completed
                            </button>
                            <button class="btn btn-outline-danger" onclick="updatePaymentStatus('failed')">
                                <i class="fas fa-times-circle me-2"></i> Mark as Failed
                            </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-outline-info" onclick="downloadReceipt()">
                                <i class="fas fa-download me-2"></i> Download Receipt
                            </button>
                            
                            <?php if ($payment['transaction_id']): ?>
                            <button class="btn btn-outline-secondary" onclick="copyTransactionId()">
                                <i class="fas fa-copy me-2"></i> Copy TXN ID
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer & Order Information -->
        <div class="row mb-4">
            <!-- Customer Information -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-user me-2 text-primary"></i>Customer Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($payment['user_id']): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Name</small>
                            <strong><?php echo htmlspecialchars($payment['customer_name']); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Email</small>
                            <a href="mailto:<?php echo htmlspecialchars($payment['customer_email']); ?>">
                                <?php echo htmlspecialchars($payment['customer_email']); ?>
                            </a>
                        </div>
                        <?php if ($payment['customer_phone']): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Phone</small>
                            <a href="tel:<?php echo htmlspecialchars($payment['customer_phone']); ?>">
                                <?php echo htmlspecialchars($payment['customer_phone']); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <div class="mt-4">
                            <a href="../customers/customer-details.php?id=<?php echo $payment['user_id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> View Customer Profile
                            </a>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">No customer information available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order Information -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-shopping-cart me-2 text-primary"></i>Order Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($payment['order_id']): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Order Number</small>
                            <strong>
                                <a href="../orders/order-details.php?id=<?php echo $payment['order_id']; ?>">
                                    <?php echo $payment['order_number']; ?>
                                </a>
                            </strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Order Status</small>
                            <span class="badge bg-<?php 
                                echo match($payment['order_status']) {
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'shipped' => 'primary',
                                    'delivered' => 'success',
                                    'cancelled' => 'danger',
                                    default => 'secondary'
                                };
                            ?>">
                                <?php echo ucfirst($payment['order_status']); ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Order Total</small>
                            <strong>$<?php echo number_format($payment['order_total'], 2); ?></strong>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted d-block">Payment Method (Order)</small>
                            <span class="badge bg-light text-dark">
                                <?php echo strtoupper($payment['order_payment_method']); ?>
                            </span>
                        </div>
                        <div class="mt-4">
                            <a href="../orders/order-details.php?id=<?php echo $payment['order_id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> View Order Details
                            </a>
                        </div>
                        <?php else: ?>
                        <p class="text-muted mb-0">This payment is not linked to any order (manual payment)</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Refund History -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">
                            <i class="fas fa-undo me-2 text-primary"></i>Refund History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($refunds)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-undo fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No refunds issued</p>
                        </div>
                        <?php else: ?>
                        <div class="refunds-list">
                            <?php foreach($refunds as $refund): 
                                $refund_status_color = 'warning';
                                if ($refund['status'] == 'completed') $refund_status_color = 'success';
                                if ($refund['status'] == 'failed') $refund_status_color = 'danger';
                            ?>
                            <div class="refund-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between mb-1">
                                    <strong class="text-danger">-$<?php echo number_format($refund['refund_amount'], 2); ?></strong>
                                    <span class="badge bg-<?php echo $refund_status_color; ?>">
                                        <?php echo ucfirst($refund['status']); ?>
                                    </span>
                                </div>
                                <small class="text-muted d-block">Reason: <?php echo $refund['reason']; ?></small>
                                <?php if ($refund['processed_by_name']): ?>
                                <small class="text-muted d-block">By: <?php echo $refund['processed_by_name']; ?></small>
                                <?php endif; ?>
                                <small class="text-muted d-block">
                                    <?php echo date('d M Y, h:i A', strtotime($refund['created_at'])); ?>
                                </small>
                                <?php if ($refund['notes']): ?>
                                <p class="mb-0 small mt-1"><?php echo nl2br(htmlspecialchars($refund['notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="alert alert-warning mt-3 py-2">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong>Total Refunded: </strong>
                            $<?php echo number_format($total_refunded, 2); ?>
                            (<?php echo round(($total_refunded / $payment['amount']) * 100, 2); ?>%)
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Payment Notes -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Payment Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-info py-2">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Payment Details:</strong>
                            <p class="mb-0 small">
                                <?php if ($payment['transaction_id']): ?>
                                Transaction ID: <?php echo $payment['transaction_id']; ?><br>
                                <?php endif; ?>
                                Method: <?php echo strtoupper($payment['payment_method']); ?><br>
                                Currency: <?php echo $payment['currency']; ?><br>
                                Status: <?php echo ucfirst($payment['status']); ?><br>
                                Created: <?php echo date('d M Y, h:i A', strtotime($payment['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Update payment status
function updatePaymentStatus(newStatus) {
    Swal.fire({
        title: 'Update Payment Status',
        text: `Are you sure you want to mark this payment as ${newStatus}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/update-payment-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: <?php echo $payment_id; ?>,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Send payment receipt
function sendPaymentReceipt() {
    Swal.fire({
        title: 'Send Receipt',
        text: 'Send payment receipt to customer email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Send Receipt'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/resend-receipt.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: <?php echo $payment_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Download receipt
function downloadReceipt() {
    window.open('receipt.php?id=<?php echo $payment_id; ?>', '_blank');
}

// Copy transaction ID
function copyTransactionId() {
    const txnId = "<?php echo $payment['transaction_id']; ?>";
    if (!txnId || txnId === 'N/A') {
        Swal.fire('Warning!', 'No transaction ID available', 'warning');
        return;
    }
    
    navigator.clipboard.writeText(txnId).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Transaction ID copied to clipboard',
            timer: 2000,
            showConfirmButton: false
        });
    });
}

// Format JSON display
document.addEventListener('DOMContentLoaded', function() {
    const paymentDetails = document.querySelector('pre');
    if (paymentDetails) {
        try {
            const jsonText = paymentDetails.textContent;
            if (jsonText.trim().startsWith('{') || jsonText.trim().startsWith('[')) {
                const jsonObj = JSON.parse(jsonText);
                paymentDetails.textContent = JSON.stringify(jsonObj, null, 2);
            }
        } catch(e) {
            // Not JSON, leave as is
        }
    }
});
</script>

<style>
.refund-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}
.font-monospace {
    font-family: 'Courier New', monospace;
    background-color: #f8f9fa;
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 0.9em;
}
</style>

<?php require_once '../includes/footer.php'; ?>