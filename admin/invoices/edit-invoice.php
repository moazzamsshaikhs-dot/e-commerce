<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: ../index.php');
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Invoice ID is required.';
    header('Location: invoices.php');
    exit();
}

$invoice_id = (int)$_GET['id'];

// Get invoice details
try {
    $db = getDB();
    
    // Get invoice
    $stmt = $db->prepare("
        SELECT i.*, u.full_name, u.email, u.phone, u.address as customer_address 
        FROM invoices i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        $_SESSION['error'] = 'Invoice not found.';
        header('Location: invoices.php');
        exit();
    }
    
    // Get invoice items
    $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll();
    
    // Get customers
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();
    
    // Get products
    $stmt = $db->query("SELECT id, name, price FROM products ORDER BY name");
    $products = $stmt->fetchAll();
    
    // Get settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'tax_rate')");
    $settings_result = $stmt->fetchAll();
    $settings = [];
    foreach ($settings_result as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
} catch(PDOException $e) {
    die('Error: ' . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        $db->beginTransaction();
        
        // Validate
        $user_id = (int)$_POST['user_id'];
        $invoice_date = $_POST['invoice_date'];
        $due_date = $_POST['due_date'];
        $notes = $_POST['notes'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $payment_status = $_POST['payment_status'] ?? 'unpaid';
        
        // Calculate totals
        $subtotal = 0;
        $items_data = [];
        
        if (isset($_POST['item_description']) && is_array($_POST['item_description'])) {
            foreach ($_POST['item_description'] as $index => $description) {
                if (!empty(trim($description))) {
                    $quantity = floatval($_POST['item_quantity'][$index] ?? 1);
                    $unit_price = floatval($_POST['item_price'][$index] ?? 0);
                    $product_id = !empty($_POST['item_product_id'][$index]) ? (int)$_POST['item_product_id'][$index] : null;
                    
                    $item_subtotal = $quantity * $unit_price;
                    $subtotal += $item_subtotal;
                    
                    $items_data[] = [
                        'description' => trim($description),
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'subtotal' => $item_subtotal,
                        'product_id' => $product_id
                    ];
                }
            }
        }
        
        if ($subtotal <= 0) {
            throw new Exception('Invoice must have at least one item with positive amount.');
        }
        
        // Calculate tax
        $tax_rate = floatval($_POST['tax_rate'] ?? 10);
        $tax_amount = ($subtotal * $tax_rate) / 100;
        $total_amount = $subtotal + $tax_amount;
        
        // Calculate amount paid from existing payments
        $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM invoice_payments WHERE invoice_id = ? AND status = 'completed'");
        $stmt->execute([$invoice_id]);
        $total_paid = $stmt->fetch()['total_paid'] ?? 0;
        
        $balance_due = $total_amount - $total_paid;
        
        // Update invoice
        $stmt = $db->prepare("
            UPDATE invoices SET
                user_id = ?,
                invoice_date = ?,
                due_date = ?,
                subtotal = ?,
                tax_rate = ?,
                tax_amount = ?,
                total_amount = ?,
                balance_due = ?,
                payment_status = ?,
                status = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $user_id,
            $invoice_date,
            $due_date,
            $subtotal,
            $tax_rate,
            $tax_amount,
            $total_amount,
            $balance_due,
            $payment_status,
            $status,
            $notes,
            $invoice_id
        ]);
        
        // Delete old items
        $stmt = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        
        // Insert new items
        $stmt = $db->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal, product_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items_data as $item) {
            $stmt->execute([
                $invoice_id,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['subtotal'],
                $item['product_id']
            ]);
        }
        
        $db->commit();
        
        $_SESSION['success'] = "Invoice updated successfully!";
        header("Location: view-invoice.php?id=$invoice_id");
        exit();
        
    } catch(Exception $e) {
        if ($db) $db->rollBack();
        $_SESSION['error'] = 'Error updating invoice: ' . $e->getMessage();
        header("Location: edit-invoice.php?id=$invoice_id");
        exit();
    }
}

$page_title = 'Edit Invoice';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Edit Invoice #<?php echo $invoice['invoice_number']; ?></h1>
                <p class="text-muted mb-0">Last updated: <?php echo date('F d, Y h:i A', strtotime($invoice['updated_at'])); ?></p>
            </div>
            <div>
                <a href="view-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-eye me-2"></i> View
                </a>
                <a href="invoices.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to List
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="invoiceForm">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Invoice Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                                    <select class="form-select" name="user_id" required>
                                        <?php foreach($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>" 
                                            <?php echo $customer['id'] == $invoice['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($customer['full_name'] . ' - ' . $customer['email']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="invoice_date" 
                                           value="<?php echo $invoice['invoice_date']; ?>" required>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="due_date" 
                                           value="<?php echo $invoice['due_date']; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="draft" <?php echo $invoice['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="sent" <?php echo $invoice['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                        <option value="approved" <?php echo $invoice['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="cancelled" <?php echo $invoice['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Payment Status</label>
                                    <select class="form-select" name="payment_status" id="paymentStatus">
                                        <option value="unpaid" <?php echo $invoice['payment_status'] == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                        <option value="partial" <?php echo $invoice['payment_status'] == 'partial' ? 'selected' : ''; ?>>Partial</option>
                                        <option value="paid" <?php echo $invoice['payment_status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="overdue" <?php echo $invoice['payment_status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                        <option value="refunded" <?php echo $invoice['payment_status'] == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" name="tax_rate" 
                                           value="<?php echo $invoice['tax_rate']; ?>" step="0.01" min="0" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Items -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Invoice Items</h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addItem()">
                                <i class="fas fa-plus me-1"></i> Add Item
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="45%">Description</th>
                                            <th width="15%">Quantity</th>
                                            <th width="20%">Unit Price</th>
                                            <th width="15%">Amount</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsBody">
                                        <?php $counter = 1; ?>
                                        <?php foreach($items as $item): ?>
                                        <tr class="item-row">
                                            <td><?php echo $counter++; ?></td>
                                            <td>
                                                <input type="text" class="form-control item-description" 
                                                       name="item_description[]" value="<?php echo htmlspecialchars($item['description']); ?>" required>
                                                <input type="hidden" name="item_product_id[]" value="<?php echo $item['product_id']; ?>" class="item-product-id">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control item-quantity" 
                                                       name="item_quantity[]" value="<?php echo $item['quantity']; ?>" min="0.01" step="0.01" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control item-price" 
                                                       name="item_price[]" value="<?php echo $item['unit_price']; ?>" min="0" step="0.01" required>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control item-amount" 
                                                       value="<?php echo number_format($item['subtotal'], 2); ?>" readonly>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)" 
                                                    <?php echo count($items) <= 1 ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Products Quick Add -->
                            <div class="mt-3">
                                <label class="form-label">Quick Add Products:</label>
                                <select class="form-select" id="productSelect" onchange="addProduct(this.value)">
                                    <option value="">Select product to add</option>
                                    <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                            data-price="<?php echo $product['price']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?> - $<?php echo number_format($product['price'], 2); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Notes & Terms</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Notes to Customer</label>
                                <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($invoice['notes']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Summary Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Invoice Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Subtotal:</span>
                                    <span id="subtotal">$<?php echo number_format($invoice['subtotal'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Tax (<span id="taxRate"><?php echo $invoice['tax_rate']; ?></span>%):</span>
                                    <span id="taxAmount">$<?php echo number_format($invoice['tax_amount'], 2); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-1 fw-bold">
                                    <span>Total:</span>
                                    <span id="totalAmount">$<?php echo number_format($invoice['total_amount'], 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Amount Paid:</span>
                                    <span id="amountPaid">$<?php echo number_format($invoice['amount_paid'], 2); ?></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold fs-5">
                                    <span>Balance Due:</span>
                                    <span id="balanceDue" class="text-danger">$<?php echo number_format($invoice['balance_due'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions Card -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" name="update" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update Invoice
                                </button>
                                
                                <a href="view-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                                
                                <?php if ($invoice['status'] != 'cancelled'): ?>
                                <a href="send-invoice.php?id=<?php echo $invoice_id; ?>" class="btn btn-success">
                                    <i class="fas fa-paper-plane me-2"></i> Send to Customer
                                </a>
                                <?php endif; ?>
                                
                                <a href="print-invoice.php?id=<?php echo $invoice_id; ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-print me-2"></i> Print Preview
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// JavaScript functions from create-invoice.php (same functionality)
// Add them here or include from external file
let itemCount = <?php echo count($items); ?>;

function addItem() {
    itemCount++;
    const tbody = document.getElementById('itemsBody');
    const newRow = document.createElement('tr');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <td>${itemCount}</td>
        <td>
            <input type="text" class="form-control item-description" 
                   name="item_description[]" placeholder="Enter description" required>
            <input type="hidden" name="item_product_id[]" class="item-product-id">
        </td>
        <td>
            <input type="number" class="form-control item-quantity" 
                   name="item_quantity[]" value="1" min="0.01" step="0.01" required>
        </td>
        <td>
            <input type="number" class="form-control item-price" 
                   name="item_price[]" min="0" step="0.01" required>
        </td>
        <td>
            <input type="text" class="form-control item-amount" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    
    // Add event listeners
    const newInputs = newRow.querySelectorAll('.item-quantity, .item-price');
    newInputs.forEach(input => {
        input.addEventListener('input', calculateRow);
        input.addEventListener('change', calculateRow);
    });
    
    calculateTotals();
}

function removeItem(button) {
    const row = button.closest('.item-row');
    if (document.querySelectorAll('.item-row').length > 1) {
        row.remove();
        updateRowNumbers();
        calculateTotals();
    } else {
        Swal.fire('Warning', 'Invoice must have at least one item.', 'warning');
    }
}

function updateRowNumbers() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row, index) => {
        row.querySelector('td:first-child').textContent = index + 1;
    });
    itemCount = rows.length;
}

function calculateRow() {
    const row = this.closest('.item-row');
    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const amount = quantity * price;
    
    row.querySelector('.item-amount').value = amount.toFixed(2);
    calculateTotals();
}

function calculateTotals() {
    let subtotal = 0;
    const rows = document.querySelectorAll('.item-row');
    
    rows.forEach(row => {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        subtotal += quantity * price;
    });
    
    const taxRate = parseFloat(document.querySelector('input[name="tax_rate"]').value) || 0;
    const taxAmount = (subtotal * taxRate) / 100;
    const totalAmount = subtotal + taxAmount;
    
    // For edit, we keep existing payments, but update display
    const existingPaid = <?php echo $invoice['amount_paid']; ?>;
    const balanceDue = totalAmount - existingPaid;
    
    // Update display
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('taxRate').textContent = taxRate;
    document.getElementById('taxAmount').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('totalAmount').textContent = '$' + totalAmount.toFixed(2);
    document.getElementById('amountPaid').textContent = '$' + existingPaid.toFixed(2);
    document.getElementById('balanceDue').textContent = '$' + balanceDue.toFixed(2);
    
    // Update payment status based on calculations
    const paymentSelect = document.getElementById('paymentStatus');
    if (balanceDue <= 0) {
        paymentSelect.value = 'paid';
    } else if (existingPaid > 0) {
        paymentSelect.value = 'partial';
    }
}

// Add product from select
function addProduct(productId) {
    if (!productId) return;
    
    const select = document.getElementById('productSelect');
    const selectedOption = select.options[select.selectedIndex];
    const productName = selectedOption.getAttribute('data-name');
    const productPrice = selectedOption.getAttribute('data-price');
    
    // Add new row
    addItem();
    const newRow = document.querySelector('.item-row:last-child');
    newRow.querySelector('.item-description').value = productName;
    newRow.querySelector('.item-product-id').value = productId;
    newRow.querySelector('.item-price').value = productPrice;
    newRow.querySelector('.item-quantity').value = 1;
    
    calculateRow.call(newRow.querySelector('.item-quantity'));
    select.value = '';
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-quantity, .item-price').forEach(input => {
        input.addEventListener('input', calculateRow);
        input.addEventListener('change', calculateRow);
    });
    
    document.querySelector('input[name="tax_rate"]').addEventListener('input', calculateTotals);
    document.querySelector('input[name="tax_rate"]').addEventListener('change', calculateTotals);
    
    document.getElementById('paymentStatus').addEventListener('change', function() {
        calculateTotals();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>