<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin or customer owns invoice
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Invoice ID is required.';
    redirect('invoices.php');
}

$invoice_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // Get invoice details
    $stmt = $db->prepare("SELECT i.*, u.full_name, u.email, u.phone, u.address as customer_address 
                          FROM invoices i
                          LEFT JOIN users u ON i.user_id = u.id
                          WHERE i.id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        die('Invoice not found.');
    }
    
    // Check permission
    if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_id'] != $invoice['user_id']) {
        $_SESSION['error'] = 'You do not have permission to view this invoice.';
        redirect('index.php');
    }
    
    // Get invoice items
    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
    
    // Get invoice payments
    $stmt = $db->prepare("SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY payment_date DESC");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll();
    
    // Get company settings
    $stmt = $db->query("SELECT * FROM invoice_settings LIMIT 1");
    $company = $stmt->fetch();
    
    // Update viewed_at if not admin and not already viewed
    if ($_SESSION['user_type'] !== 'admin' && !$invoice['viewed_at']) {
        $stmt = $db->prepare("UPDATE invoices SET viewed_at = NOW(), status = 'viewed' WHERE id = ?");
        $stmt->execute([$invoice_id]);
    }
    
} catch(PDOException $e) {
    die('Error loading invoice: ' . $e->getMessage());
}

// Set PDF filename
$pdf_filename = "Invoice_{$invoice['invoice_number']}.pdf";

$page_title = "Invoice #{$invoice['invoice_number']}";
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Invoice Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Invoice #<?php echo $invoice['invoice_number']; ?></h1>
                <p class="text-muted mb-0">
                    <?php echo $invoice['full_name']; ?> • 
                    <?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?> • 
                    <?php echo $invoice['payment_status']; ?>
                </p>
            </div>
            <div class="btn-group">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print me-2"></i> Print
                </button>
                <a href="download-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-success">
                    <i class="fas fa-download me-2"></i> Download PDF
                </a>
                <?php if ($_SESSION['user_type'] == 'admin'): ?>
                <a href="edit-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-warning">
                    <i class="fas fa-edit me-2"></i> Edit
                </a>
                <?php endif; ?>
                <button onclick="shareInvoice()" class="btn btn-info">
                    <i class="fas fa-share me-2"></i> Share
                </button>
            </div>
        </div>
        
        <!-- Invoice Preview -->
        <div class="card border-0 shadow-sm mb-4" id="invoicePreview">
            <div class="card-body">
                <!-- Invoice Content -->
                <div class="invoice-container">
                    <!-- Company Header -->
                    <div class="invoice-header border-bottom pb-4 mb-4">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (!empty($company['logo'])): ?>
                                <img src="<?php echo SITE_URL; ?>uploads/<?php echo $company['logo']; ?>" 
                                     alt="Company Logo" class="company-logo mb-3" style="max-height: 60px;">
                                <?php endif; ?>
                                <h2 class="mb-1">INVOICE</h2>
                                <p class="text-muted mb-0">Invoice #: <?php echo $invoice['invoice_number']; ?></p>
                            </div>
                            <div class="col-md-6 text-end">
                                <h4 class="mb-1"><?php echo $company['company_name'] ?? 'Your Company'; ?></h4>
                                <p class="mb-1"><?php echo $company['address'] ?? ''; ?></p>
                                <p class="mb-1"><?php echo $company['city'] ?? ''; ?>, <?php echo $company['state'] ?? ''; ?> <?php echo $company['postal_code'] ?? ''; ?></p>
                                <p class="mb-1">Phone: <?php echo $company['phone'] ?? ''; ?></p>
                                <p class="mb-0">Email: <?php echo $company['email'] ?? ''; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Details -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Bill To:</h5>
                            <p class="mb-1"><strong><?php echo htmlspecialchars($invoice['full_name']); ?></strong></p>
                            <p class="mb-1"><?php echo htmlspecialchars($invoice['email']); ?></p>
                            <p class="mb-1"><?php echo htmlspecialchars($invoice['phone']); ?></p>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td class="text-end"><strong>Invoice Date:</strong></td>
                                    <td class="text-end"><?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-end"><strong>Due Date:</strong></td>
                                    <td class="text-end"><?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-end"><strong>Invoice Status:</strong></td>
                                    <td class="text-end"><?php echo getInvoiceStatusBadge($invoice['status']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-end"><strong>Payment Status:</strong></td>
                                    <td class="text-end"><?php echo
                                    //  getPaymentStatusBadge(
                                        $invoice['payment_status']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Items Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th class="text-end">Quantity</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $counter = 1; ?>
                                <?php foreach($items as $item): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                                    <td class="text-end"><?php echo number_format($item['quantity'], 2); ?></td>
                                    <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <!-- Empty rows for printing -->
                                <?php for($i = count($items); $i < 10; $i++): ?>
                                <tr style="height: 40px;">
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end"><strong>$<?php echo number_format($invoice['subtotal'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Tax (<?php echo $invoice['tax_rate']; ?>%):</strong></td>
                                    <td class="text-end"><strong>$<?php echo number_format($invoice['tax_amount'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end"><strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Amount Paid:</strong></td>
                                    <td class="text-end"><strong>$<?php echo number_format($invoice['amount_paid'], 2); ?></strong></td>
                                </tr>
                                <tr class="table-active">
                                    <td colspan="4" class="text-end"><strong>Balance Due:</strong></td>
                                    <td class="text-end"><strong>$<?php echo number_format($invoice['balance_due'], 2); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Payment History -->
                    <?php if (!empty($payments)): ?>
                    <div class="mb-4">
                        <h5>Payment History</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Method</th>
                                        <th>Transaction ID</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td><?php echo $payment['transaction_id'] ?: 'N/A'; ?></td>
                                        <td class="text-end">$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $payment['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $payment['notes'] ?: '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Notes & Terms -->
                    <div class="row">
                        <?php if (!empty($invoice['notes'])): ?>
                        <div class="col-md-6">
                            <h5>Notes</h5>
                            <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="<?php echo !empty($invoice['notes']) ? 'col-md-6' : 'col-12'; ?>">
                            <h5>Terms & Conditions</h5>
                            <p><?php echo nl2br($company['terms'] ?? 'Payment due within 30 days. Late payments subject to interest.'); ?></p>
                            
                            <?php if (!empty($company['bank_details'])): ?>
                            <h5>Bank Details</h5>
                            <p><?php echo nl2br($company['bank_details']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="mt-5 pt-4 border-top">
                        <div class="row">
                            <div class="col-md-4">
                                <h6>Thank You!</h6>
                                <p class="text-muted small">We appreciate your business.</p>
                            </div>
                            <div class="col-md-4 text-center">
                                <?php if (!empty($company['signature'])): ?>
                                <img src="<?php echo SITE_URL; ?>uploads/<?php echo $company['signature']; ?>" 
                                     alt="Signature" class="signature-img" style="max-height: 50px;">
                                <p class="text-muted small mt-2">Authorized Signature</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 text-end">
                                <p class="text-muted small">
                                    This is a computer-generated invoice.<br>
                                    No signature required.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Invoice Actions -->
        <?php if ($_SESSION['user_type'] == 'admin'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Invoice Actions</h5>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($invoice['status'] != 'sent'): ?>
                    <button class="btn btn-primary" onclick="sendInvoice(<?php echo $invoice_id; ?>)">
                        <i class="fas fa-paper-plane me-2"></i> Send to Customer
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($invoice['payment_status'] != 'paid' && $invoice['status'] != 'cancelled'): ?>
                    <button class="btn btn-success" onclick="recordPayment(<?php echo $invoice_id; ?>)">
                        <i class="fas fa-money-bill-wave me-2"></i> Record Payment
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($invoice['payment_status'] == 'paid'): ?>
                    <button class="btn btn-info" onclick="sendReceipt(<?php echo $invoice_id; ?>)">
                        <i class="fas fa-receipt me-2"></i> Send Receipt
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($invoice['status'] != 'cancelled'): ?>
                    <button class="btn btn-warning" onclick="sendReminder(<?php echo $invoice_id; ?>)">
                        <i class="fas fa-bell me-2"></i> Send Reminder
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($invoice['status'] != 'cancelled'): ?>
                    <button class="btn btn-danger" onclick="cancelInvoice(<?php echo $invoice_id; ?>)">
                        <i class="fas fa-ban me-2"></i> Cancel Invoice
                    </button>
                    <?php endif; ?>
                    
                    <button class="btn btn-outline-danger" onclick="deleteInvoice(<?php echo $invoice_id; ?>)">
                        <i class="fas fa-trash me-2"></i> Delete Invoice
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Invoice Timeline -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Invoice Timeline</h5>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Invoice Created</h6>
                            <p class="text-muted small mb-0">
                                <?php echo date('F d, Y h:i A', strtotime($invoice['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($invoice['sent_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-info"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Invoice Sent to Customer</h6>
                            <p class="text-muted small mb-0">
                                <?php echo date('F d, Y h:i A', strtotime($invoice['sent_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($invoice['viewed_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Invoice Viewed by Customer</h6>
                            <p class="text-muted small mb-0">
                                <?php echo date('F d, Y h:i A', strtotime($invoice['viewed_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($invoice['paid_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Invoice Paid</h6>
                            <p class="text-muted small mb-0">
                                <?php echo date('F d, Y h:i A', strtotime($invoice['paid_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="timeline-item">
                        <div class="timeline-marker bg-secondary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Due Date</h6>
                            <p class="text-muted small mb-0">
                                <?php echo date('F d, Y', strtotime($invoice['due_date'])); ?>
                                <?php if (strtotime($invoice['due_date']) < time() && $invoice['payment_status'] != 'paid'): ?>
                                <span class="badge bg-danger ms-2">Overdue</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
/* Print styles */
@media print {
    .dashboard-container, .main-content, .card, .card-body {
        padding: 0 !important;
        margin: 0 !important;
        border: none !important;
        box-shadow: none !important;
        background: white !important;
    }
    
    .sidebar, .btn-group, .card:not(#invoicePreview), .timeline {
        display: none !important;
    }
    
    #invoicePreview {
        display: block !important;
    }
    
    .invoice-container {
        font-size: 12px;
    }
    
    .table th, .table td {
        padding: 4px !important;
    }
    
    h1, h2, h3, h4, h5, h6 {
        margin-bottom: 8px !important;
    }
}

/* Timeline styles */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    z-index: 1;
}

.timeline-content {
    margin-left: 10px;
}

.signature-img {
    max-height: 80px;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Print invoice
function printInvoice() {
    const printContent = document.getElementById('invoicePreview').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    location.reload();
}

// Send invoice to customer
function sendInvoice(invoiceId) {
    Swal.fire({
        title: 'Send Invoice',
        text: 'Send this invoice to customer via email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Send Invoice',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/send-invoice.php?id=' + invoiceId)
                .then(response => response.json())
                .then(data => {
                    Swal.fire(data.success ? 'Success' : 'Error', data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'An error occurred.', 'error');
                });
        }
    });
}

// Send receipt
function sendReceipt(invoiceId) {
    Swal.fire({
        title: 'Send Receipt',
        text: 'Send payment receipt to customer?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Send Receipt',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/send-receipt.php?id=' + invoiceId)
                .then(response => response.json())
                .then(data => {
                    Swal.fire(data.success ? 'Success' : 'Error', data.message, data.success ? 'success' : 'error');
                })
                .catch(error => {
                    Swal.fire('Error', 'An error occurred.', 'error');
                });
        }
    });
}

// Send reminder
function sendReminder(invoiceId) {
    Swal.fire({
        title: 'Send Reminder',
        text: 'Send payment reminder to customer?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Send Reminder',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/send-reminder.php?id=' + invoiceId)
                .then(response => response.json())
                .then(data => {
                    Swal.fire(data.success ? 'Success' : 'Error', data.message, data.success ? 'success' : 'error');
                })
                .catch(error => {
                    Swal.fire('Error', 'An error occurred.', 'error');
                });
        }
    });
}

// Cancel invoice
function cancelInvoice(invoiceId) {
    Swal.fire({
        title: 'Cancel Invoice',
        text: 'Are you sure you want to cancel this invoice?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Cancel Invoice',
        cancelButtonText: 'Keep Invoice'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/cancel-invoice.php?id=' + invoiceId)
                .then(response => response.json())
                .then(data => {
                    Swal.fire(data.success ? 'Success' : 'Error', data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => location.reload(), 1500);
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'An error occurred.', 'error');
                });
        }
    });
}

// Delete invoice
function deleteInvoice(invoiceId) {
    Swal.fire({
        title: 'Delete Invoice',
        html: 'Are you sure you want to delete this invoice?<br><small class="text-danger">This action cannot be undone!</small>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Delete Invoice',
        cancelButtonText: 'Keep Invoice'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/delete-invoice.php?id=' + invoiceId)
                .then(response => response.json())
                .then(data => {
                    Swal.fire(data.success ? 'Success' : 'Error', data.message, data.success ? 'success' : 'error');
                    if (data.success) {
                        setTimeout(() => {
                            window.location.href = 'invoices.php';
                        }, 1500);
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'An error occurred.', 'error');
                });
        }
    });
}

// Share invoice
function shareInvoice() {
    const invoiceUrl = window.location.href;
    const invoiceNumber = '<?php echo $invoice["invoice_number"]; ?>';
    
    Swal.fire({
        title: 'Share Invoice',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Share Link</label>
                    <div class="input-group">
                        <input type="text" id="shareUrl" class="form-control" value="${invoiceUrl}" readonly>
                        <button class="btn btn-outline-secondary" onclick="copyShareUrl()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Or share via:</label>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary flex-fill" onclick="shareViaEmail()">
                            <i class="fas fa-envelope me-2"></i> Email
                        </button>
                        <button class="btn btn-outline-info flex-fill" onclick="shareViaWhatsApp()">
                            <i class="fab fa-whatsapp me-2"></i> WhatsApp
                        </button>
                    </div>
                </div>
            </div>
        `,
        showConfirmButton: false,
        showCloseButton: true
    });
}

function copyShareUrl() {
    const urlInput = document.getElementById('shareUrl');
    urlInput.select();
    document.execCommand('copy');
    Swal.fire({
        icon: 'success',
        title: 'Copied!',
        text: 'Link copied to clipboard',
        timer: 1500
    });
}

// Record payment (same as in invoices.php)
function recordPayment(invoiceId) {
    // Same function as in invoices.php
    // ... [record payment code] ...
}
</script>

<?php require_once '../includes/footer.php'; ?>