<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

if (!isset($_GET['payment_id']) || empty($_GET['payment_id'])) {
    $_SESSION['error'] = 'Payment ID is required.';
    redirect('index.php');
}

$payment_id = (int)$_GET['payment_id'];

try {
    $db = getDB();
    
    // Get payment details
    $stmt = $db->prepare("SELECT p.*, 
                                 u.full_name as customer_name,
                                 u.email as customer_email,
                                 o.order_number,
                                 o.total_amount as order_total
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
    
    // Check if payment is refundable
    if ($payment['status'] !== 'completed') {
        $_SESSION['error'] = 'Only completed payments can be refunded.';
        redirect('payment-details.php?id=' . $payment_id);
    }
    
    // Get existing refunds
    $stmt = $db->prepare("SELECT SUM(refund_amount) as total_refunded 
                          FROM refunds 
                          WHERE payment_id = ? AND status = 'completed'");
    $stmt->execute([$payment_id]);
    $total_refunded = $stmt->fetch()['total_refunded'] ?? 0;
    
    $refundable_amount = $payment['amount'] - $total_refunded;
    
    if ($refundable_amount <= 0) {
        $_SESSION['error'] = 'Payment has already been fully refunded.';
        redirect('payment-details.php?id=' . $payment_id);
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading refund details: ' . $e->getMessage();
    redirect('index.php');
}

$page_title = 'Process Refund';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Process Refund</h1>
                <p class="text-muted mb-0">Payment ID: #<?php echo $payment['id']; ?></p>
            </div>
            <div>
                <a href="payment-details.php?id=<?php echo $payment_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Payment
                </a>
            </div>
        </div>
        
        <!-- Payment Summary -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Payment Summary</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td width="40%">Payment ID:</td>
                                <td>#<?php echo $payment['id']; ?></td>
                            </tr>
                            <tr>
                                <td>Customer:</td>
                                <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                            </tr>
                            <tr>
                                <td>Order:</td>
                                <td>
                                    <?php if ($payment['order_number']): ?>
                                    <?php echo $payment['order_number']; ?>
                                    <?php else: ?>
                                    <span class="text-muted">Manual Payment</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Payment Method:</td>
                                <td><?php echo strtoupper($payment['payment_method']); ?></td>
                            </tr>
                            <tr>
                                <td>Original Amount:</td>
                                <td class="fw-bold">$<?php echo number_format($payment['amount'], 2); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Refund Summary</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td width="50%">Original Amount:</td>
                                <td class="text-end">$<?php echo number_format($payment['amount'], 2); ?></td>
                            </tr>
                            <tr>
                                <td>Already Refunded:</td>
                                <td class="text-end text-danger">-$<?php echo number_format($total_refunded, 2); ?></td>
                            </tr>
                            <tr class="table-active">
                                <td><strong>Refundable Amount:</strong></td>
                                <td class="text-end">
                                    <strong class="text-primary">$<?php echo number_format($refundable_amount, 2); ?></strong>
                                </td>
                            </tr>
                            <tr>
                                <td>Refund Percentage:</td>
                                <td class="text-end">
                                    <?php echo round(($refundable_amount / $payment['amount']) * 100, 2); ?>%
                                </td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-info py-2 small">
                            <i class="fas fa-info-circle me-2"></i>
                            Maximum refundable amount: $<?php echo number_format($refundable_amount, 2); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Refund Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Refund Details</h5>
            </div>
            <div class="card-body">
                <form id="refundForm" method="POST" action="../../ajax/process-refund.php">
                    <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Refund Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" name="refund_amount" 
                                       id="refundAmount" step="0.01" min="0.01" 
                                       max="<?php echo $refundable_amount; ?>"
                                       value="<?php echo number_format($refundable_amount, 2); ?>" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="setFullAmount()">
                                    Full Amount
                                </button>
                            </div>
                            <small class="text-muted">
                                Maximum: $<?php echo number_format($refundable_amount, 2); ?>
                                (<span id="refundPercentage"><?php echo round(($refundable_amount / $payment['amount']) * 100, 2); ?>%</span>)
                            </small>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Refund Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="refund_type" required>
                                <option value="full">Full Refund</option>
                                <option value="partial">Partial Refund</option>
                                <option value="cancellation">Order Cancellation</option>
                                <option value="return">Product Return</option>
                                <option value="adjustment">Price Adjustment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Reason for Refund <span class="text-danger">*</span></label>
                            <select class="form-select" name="reason" required onchange="toggleCustomReason()">
                                <option value="">Select Reason</option>
                                <option value="customer_requested">Customer Requested</option>
                                <option value="order_cancelled">Order Cancelled</option>
                                <option value="product_returned">Product Returned</option>
                                <option value="price_adjustment">Price Adjustment</option>
                                <option value="duplicate_payment">Duplicate Payment</option>
                                <option value="fraudulent_transaction">Fraudulent Transaction</option>
                                <option value="technical_issue">Technical Issue</option>
                                <option value="custom">Other (Specify)</option>
                            </select>
                        </div>
                        
                        <div class="col-12" id="customReasonDiv" style="display: none;">
                            <label class="form-label">Custom Reason</label>
                            <input type="text" class="form-control" name="custom_reason" 
                                   placeholder="Enter custom reason...">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Refund Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="refund_method" required>
                                <option value="original">Original Payment Method</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="check">Check</option>
                                <option value="cash">Cash</option>
                                <option value="store_credit">Store Credit</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="3" 
                                      placeholder="Add any additional notes about this refund..."></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_customer" id="notifyCustomer" checked>
                                <label class="form-check-label" for="notifyCustomer">
                                    Notify customer via email
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="update_order_status" id="updateOrderStatus" checked>
                                <label class="form-check-label" for="updateOrderStatus">
                                    Update order status to "Refunded"
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="alert alert-warning py-2">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Important:</strong> Refunds may take 5-10 business days to appear in customer's account.
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">Refund Total:</h5>
                                    <small class="text-muted">Will be deducted from payment</small>
                                </div>
                                <div>
                                    <h3 class="text-danger mb-0" id="refundTotalDisplay">
                                        $<?php echo number_format($refundable_amount, 2); ?>
                                    </h3>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning btn-lg">
                                    <i class="fas fa-undo me-2"></i> Process Refund
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewRefund()">
                                    <i class="fas fa-eye me-2"></i> Preview Refund
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Refund History -->
        <?php if ($total_refunded > 0): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Previous Refunds</h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->prepare("SELECT r.*, u.full_name as processed_by_name 
                                      FROM refunds r
                                      LEFT JOIN users u ON r.processed_by = u.id
                                      WHERE r.payment_id = ? 
                                      ORDER BY r.created_at DESC");
                $stmt->execute([$payment_id]);
                $previous_refunds = $stmt->fetchAll();
                ?>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Processed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($previous_refunds as $refund): 
                                $status_color = 'warning';
                                if ($refund['status'] == 'completed') $status_color = 'success';
                                if ($refund['status'] == 'failed') $status_color = 'danger';
                            ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($refund['created_at'])); ?></td>
                                <td class="text-danger">-$<?php echo number_format($refund['refund_amount'], 2); ?></td>
                                <td><?php echo $refund['reason']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $status_color; ?>">
                                        <?php echo ucfirst($refund['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($refund['processed_by_name']) {
                                        echo htmlspecialchars($refund['processed_by_name']);
                                    } else {
                                        echo 'System';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Set full refund amount
function setFullAmount() {
    const maxAmount = <?php echo $refundable_amount; ?>;
    document.getElementById('refundAmount').value = maxAmount.toFixed(2);
    updateRefundDisplay();
}

// Toggle custom reason
function toggleCustomReason() {
    const reasonSelect = document.querySelector('select[name="reason"]');
    const customReasonDiv = document.getElementById('customReasonDiv');
    
    if (reasonSelect.value === 'custom') {
        customReasonDiv.style.display = 'block';
        document.querySelector('input[name="custom_reason"]').required = true;
    } else {
        customReasonDiv.style.display = 'none';
        document.querySelector('input[name="custom_reason"]').required = false;
    }
}

// Update refund display
function updateRefundDisplay() {
    const refundAmount = parseFloat(document.getElementById('refundAmount').value) || 0;
    const originalAmount = <?php echo $payment['amount']; ?>;
    const totalRefunded = <?php echo $total_refunded; ?>;
    const remaining = originalAmount - totalRefunded - refundAmount;
    
    // Update display
    document.getElementById('refundTotalDisplay').textContent = '$' + refundAmount.toFixed(2);
    
    // Update percentage
    const percentage = (refundAmount / originalAmount) * 100;
    document.getElementById('refundPercentage').textContent = percentage.toFixed(2) + '%';
    
    // Validate amount
    if (refundAmount > <?php echo $refundable_amount; ?>) {
        document.getElementById('refundAmount').classList.add('is-invalid');
    } else {
        document.getElementById('refundAmount').classList.remove('is-invalid');
    }
}

// Preview refund
function previewRefund() {
    // Validate form
    const form = document.getElementById('refundForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const refundAmount = parseFloat(document.getElementById('refundAmount').value);
    if (refundAmount > <?php echo $refundable_amount; ?>) {
        Swal.fire('Error!', 'Refund amount cannot exceed refundable amount.', 'error');
        return;
    }
    
    // Collect data
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Show preview
    Swal.fire({
        title: 'Refund Preview',
        html: `
            <div class="text-start">
                <p><strong>Payment ID:</strong> #<?php echo $payment['id']; ?></p>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($payment['customer_name']); ?></p>
                <p><strong>Refund Amount:</strong> <span class="text-danger">$${refundAmount.toFixed(2)}</span></p>
                <p><strong>Refund Type:</strong> ${data.refund_type.replace('_', ' ')}</p>
                <p><strong>Reason:</strong> ${data.reason === 'custom' ? data.custom_reason : data.reason}</p>
                <p><strong>Refund Method:</strong> ${data.refund_method.replace('_', ' ')}</p>
                <hr>
                <p><strong>Original Amount:</strong> $<?php echo number_format($payment['amount'], 2); ?></p>
                <p><strong>Already Refunded:</strong> $<?php echo number_format($total_refunded, 2); ?></p>
                <p><strong>This Refund:</strong> $${refundAmount.toFixed(2)}</p>
                <p><strong>Remaining Balance:</strong> $${(<?php echo $refundable_amount; ?> - refundAmount).toFixed(2)}</p>
                <hr>
                <p><strong>Actions:</strong></p>
                <p>✓ Create refund record</p>
                ${data.notify_customer ? '<p>✓ Notify customer</p>' : ''}
                ${data.update_order_status && <?php echo $payment['order_id'] ? 'true' : 'false'; ?> ? '<p>✓ Update order status</p>' : ''}
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Process Refund',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            submitRefund();
        }
    });
}

// Submit refund via AJAX
function submitRefund() {
    const form = document.getElementById('refundForm');
    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    // Show loading
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    submitButton.disabled = true;
    
    fetch(form.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: data.message,
                icon: 'success',
                confirmButtonText: 'View Payment'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'payment-details.php?id=<?php echo $payment_id; ?>';
                } else {
                    window.location.href = 'index.php';
                }
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred while processing refund.', 'error');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// Form submission
document.getElementById('refundForm').addEventListener('submit', function(e) {
    e.preventDefault();
    previewRefund();
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Update display on amount change
    document.getElementById('refundAmount').addEventListener('input', updateRefundDisplay);
    
    // Initialize display
    updateRefundDisplay();
});
</script>

<?php require_once '../includes/footer.php'; ?>