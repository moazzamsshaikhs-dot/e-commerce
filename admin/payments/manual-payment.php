<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

$page_title = 'Add Manual Payment';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get customers for dropdown
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();
    
    // Get orders for dropdown (pending payments)
    $stmt = $db->query("SELECT o.id, o.order_number, o.total_amount, u.full_name 
                        FROM orders o 
                        LEFT JOIN users u ON o.user_id = u.id 
                        WHERE o.payment_status = 'pending' 
                        ORDER BY o.order_date DESC");
    $pending_orders = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading data: ' . $e->getMessage();
    redirect('index.php');
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Add Manual Payment</h1>
                <p class="text-muted mb-0">Record offline or manual payments</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Payments
                </a>
            </div>
        </div>

        <form id="manualPaymentForm" method="POST" action="../../ajax/process-manual-payment.php">
            <div class="row">
                <!-- Left Column: Payment Details -->
                <div class="col-lg-8">
                    <!-- Customer & Order Selection -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2 text-primary"></i>Customer & Order Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Select Customer <span class="text-danger">*</span></label>
                                    <select class="form-select" id="customerSelect" name="customer_id" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>"
                                                data-email="<?php echo htmlspecialchars($customer['email']); ?>">
                                            <?php echo htmlspecialchars($customer['full_name']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Link to Order (Optional)</label>
                                    <select class="form-select" id="orderSelect" name="order_id">
                                        <option value="">Select Order (or leave blank)</option>
                                        <?php foreach($pending_orders as $order): ?>
                                        <option value="<?php echo $order['id']; ?>"
                                                data-amount="<?php echo $order['total_amount']; ?>"
                                                data-customer="<?php echo htmlspecialchars($order['full_name']); ?>">
                                            <?php echo $order['order_number']; ?> - $<?php echo number_format($order['total_amount'], 2); ?> - <?php echo htmlspecialchars($order['full_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="alert alert-info py-2">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <small>Selecting an order will automatically update order payment status</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div id="orderInfo" style="display: none;">
                                        <small class="text-muted d-block">Order Details</small>
                                        <div>
                                            <strong id="orderNumber"></strong><br>
                                            <span id="orderCustomer"></span><br>
                                            <span id="orderAmount"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Details -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2 text-primary"></i>Payment Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="check">Check</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="paypal">PayPal</option>
                                        <option value="upi">UPI</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Transaction ID</label>
                                    <input type="text" class="form-control" name="transaction_id" 
                                           placeholder="Bank reference, check number, etc.">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Currency <span class="text-danger">*</span></label>
                                    <select class="form-select" name="currency" required>
                                        <option value="USD">USD ($)</option>
                                        <option value="EUR">EUR (€)</option>
                                        <option value="GBP">GBP (£)</option>
                                        <option value="INR">INR (₹)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Amount <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="currencySymbol">$</span>
                                        <input type="number" class="form-control" name="amount" 
                                               id="amount" step="0.01" min="0.01" required
                                               placeholder="Enter payment amount">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Payment Status <span class="text-danger">*</span></label>
                                    <select class="form-select" name="status" required>
                                        <option value="completed">Completed</option>
                                        <option value="pending">Pending</option>
                                        <option value="failed">Failed</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" name="payment_date" 
                                           value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Payment Details (Optional)</label>
                                    <textarea class="form-control" name="payment_details" rows="3" 
                                              placeholder="Bank details, payment notes, etc."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Summary & Actions -->
                <div class="col-lg-4">
                    <!-- Payment Summary -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="mb-3">Payment Summary</h6>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Customer</small>
                                <span id="summaryCustomer">Not selected</span>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Linked Order</small>
                                <span id="summaryOrder">None</span>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Payment Method</small>
                                <span id="summaryMethod">Cash</span>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Amount</small>
                                <h4 class="text-primary" id="summaryAmount">$0.00</h4>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted d-block">Status</small>
                                <span class="badge bg-success" id="summaryStatus">Completed</span>
                            </div>
                            
                            <hr>
                            
                            <div class="alert alert-warning py-2 small">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                This will create a payment record and update linked order status if applicable.
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">Actions</h6>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="send_receipt" id="sendReceipt" checked>
                                <label class="form-check-label" for="sendReceipt">
                                    Send receipt to customer
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="add_internal_note" id="addInternalNote" checked>
                                <label class="form-check-label" for="addInternalNote">
                                    Add internal note
                                </label>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check me-2"></i> Record Payment
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewPayment()">
                                    <i class="fas fa-eye me-2"></i> Preview
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Currency symbols
const currencySymbols = {
    'USD': '$',
    'EUR': '€',
    'GBP': '£',
    'INR': '₹'
};

// Update summary on form changes
document.addEventListener('DOMContentLoaded', function() {
    // Initial update
    updateSummary();
    
    // Listen for changes
    document.getElementById('customerSelect').addEventListener('change', updateSummary);
    document.getElementById('orderSelect').addEventListener('change', updateSummary);
    document.querySelector('select[name="payment_method"]').addEventListener('change', updateSummary);
    document.querySelector('select[name="currency"]').addEventListener('change', updateSummary);
    document.getElementById('amount').addEventListener('input', updateSummary);
    document.querySelector('select[name="status"]').addEventListener('change', updateSummary);
});

// Update currency symbol
document.querySelector('select[name="currency"]').addEventListener('change', function() {
    const symbol = currencySymbols[this.value] || '$';
    document.getElementById('currencySymbol').textContent = symbol;
    updateSummary();
});

// Update order info when order selected
document.getElementById('orderSelect').addEventListener('change', function() {
    const orderInfo = document.getElementById('orderInfo');
    const amountInput = document.getElementById('amount');
    
    if (this.value) {
        const selectedOption = this.options[this.selectedIndex];
        const orderAmount = selectedOption.getAttribute('data-amount');
        const orderCustomer = selectedOption.getAttribute('data-customer');
        const orderNumber = selectedOption.text.split(' - ')[0];
        
        // Show order info
        document.getElementById('orderNumber').textContent = orderNumber;
        document.getElementById('orderCustomer').textContent = orderCustomer;
        document.getElementById('orderAmount').textContent = '$' + parseFloat(orderAmount).toFixed(2);
        orderInfo.style.display = 'block';
        
        // Auto-fill amount
        amountInput.value = orderAmount;
    } else {
        orderInfo.style.display = 'none';
        amountInput.value = '';
    }
    
    updateSummary();
});

// Update summary
function updateSummary() {
    // Customer
    const customerSelect = document.getElementById('customerSelect');
    const customerText = customerSelect.options[customerSelect.selectedIndex]?.text || 'Not selected';
    document.getElementById('summaryCustomer').textContent = customerText.split(' (')[0];
    
    // Order
    const orderSelect = document.getElementById('orderSelect');
    const orderText = orderSelect.options[orderSelect.selectedIndex]?.text || 'None';
    document.getElementById('summaryOrder').textContent = orderText.split(' - ')[0] || 'None';
    
    // Payment method
    const methodSelect = document.querySelector('select[name="payment_method"]');
    document.getElementById('summaryMethod').textContent = methodSelect.options[methodSelect.selectedIndex].text;
    
    // Currency and amount
    const currency = document.querySelector('select[name="currency"]').value;
    const symbol = currencySymbols[currency] || '$';
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    document.getElementById('summaryAmount').textContent = symbol + amount.toFixed(2);
    
    // Status
    const status = document.querySelector('select[name="status"]').value;
    const statusBadge = document.getElementById('summaryStatus');
    statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
    
    // Update status badge color
    if (status === 'completed') {
        statusBadge.className = 'badge bg-success';
    } else if (status === 'pending') {
        statusBadge.className = 'badge bg-warning';
    } else {
        statusBadge.className = 'badge bg-danger';
    }
}

// Preview payment
function previewPayment() {
    // Validate form
    const form = document.getElementById('manualPaymentForm');
    if (!form.checkValidity()) {
        form.reportValidity();
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
        title: 'Payment Preview',
        html: `
            <div class="text-start">
                <p><strong>Customer:</strong> ${document.getElementById('summaryCustomer').textContent}</p>
                <p><strong>Linked Order:</strong> ${document.getElementById('summaryOrder').textContent}</p>
                <p><strong>Payment Method:</strong> ${document.getElementById('summaryMethod').textContent}</p>
                <p><strong>Amount:</strong> ${document.getElementById('summaryAmount').textContent}</p>
                <p><strong>Status:</strong> ${document.getElementById('summaryStatus').outerHTML}</p>
                <p><strong>Payment Date:</strong> ${data.payment_date}</p>
                <hr>
                <p><strong>Actions:</strong></p>
                <p>✓ Create payment record</p>
                ${data.send_receipt ? '<p>✓ Send receipt to customer</p>' : ''}
                ${data.add_internal_note ? '<p>✓ Add internal note</p>' : ''}
                ${data.order_id ? '<p>✓ Update order payment status</p>' : ''}
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Record Payment',
        cancelButtonText: 'Edit Details'
    }).then((result) => {
        if (result.isConfirmed) {
            submitPayment();
        }
    });
}

// Submit payment via AJAX
function submitPayment() {
    const form = document.getElementById('manualPaymentForm');
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
                    window.location.href = 'payment-details.php?id=' + data.payment_id;
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
        Swal.fire('Error!', 'An error occurred while processing payment.', 'error');
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    });
}

// Form submission
document.getElementById('manualPaymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    previewPayment();
});
</script>

<?php require_once '../includes/footer.php'; ?>