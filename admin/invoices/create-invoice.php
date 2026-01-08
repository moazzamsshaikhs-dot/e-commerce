<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: ../index.php');
    exit();
}

// Generate invoice number
function generateInvoiceNumber() {
    $db = getDB();
    
    // Get last invoice number
    $stmt = $db->query("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
    $last_invoice = $stmt->fetch();
    
    if ($last_invoice && preg_match('/INV-(\d+)-(\d+)/', $last_invoice['invoice_number'], $matches)) {
        $number = intval($matches[2]) + 1;
        return 'INV-' . date('Y') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
    
    return 'INV-' . date('Y') . '-0001';
}

// Get customers and products
try {
    $db = getDB();
    
    // Get customers
    $stmt = $db->query("SELECT id, full_name, email, phone, address FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();
    
    // Get products
    $stmt = $db->query("SELECT id, name, price, stock FROM products WHERE stock > 0 ORDER BY name");
    $products = $stmt->fetchAll();
    
    // Get company settings
    $stmt = $db->query("SELECT * FROM settings WHERE setting_key IN ('site_name', 'site_email', 'site_phone', 'site_address', 'tax_rate')");
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
        
        // Validate required fields
        $required_fields = ['user_id', 'invoice_date', 'due_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill all required fields.");
            }
        }
        
        $user_id = (int)$_POST['user_id'];
        $invoice_date = $_POST['invoice_date'];
        $due_date = $_POST['due_date'];
        $notes = $_POST['notes'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $payment_status = $_POST['payment_status'] ?? 'unpaid';
        
        // Validate items
        if (empty($_POST['item_description']) || !is_array($_POST['item_description'])) {
            throw new Exception("Please add at least one item to the invoice.");
        }
        
        // Calculate totals
        $subtotal = 0;
        $items = [];
        
        foreach ($_POST['item_description'] as $index => $description) {
            if (!empty(trim($description))) {
                $quantity = floatval($_POST['item_quantity'][$index] ?? 1);
                $unit_price = floatval($_POST['item_price'][$index] ?? 0);
                
                if ($quantity <= 0 || $unit_price <= 0) {
                    throw new Exception("Quantity and price must be greater than 0.");
                }
                
                $item_subtotal = $quantity * $unit_price;
                $subtotal += $item_subtotal;
                
                $items[] = [
                    'description' => trim($description),
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'subtotal' => $item_subtotal,
                    'product_id' => !empty($_POST['item_product_id'][$index]) ? (int)$_POST['item_product_id'][$index] : null
                ];
            }
        }
        
        if ($subtotal <= 0) {
            throw new Exception('Invoice must have at least one item with positive amount.');
        }
        
        // Calculate tax
        $tax_rate = floatval($_POST['tax_rate'] ?? ($settings['tax_rate'] ?? 10));
        $tax_amount = ($subtotal * $tax_rate) / 100;
        $total_amount = $subtotal + $tax_amount;
        $amount_paid = $payment_status == 'paid' ? $total_amount : 0;
        $balance_due = $total_amount - $amount_paid;
        
        // Generate invoice number
        $invoice_number = generateInvoiceNumber();
        
        // Insert invoice
        $stmt = $db->prepare("
            INSERT INTO invoices (
                invoice_number, user_id, invoice_date, due_date,
                subtotal, tax_rate, tax_amount, total_amount,
                amount_paid, balance_due, payment_status, status,
                notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $invoice_number,
            $user_id,
            $invoice_date,
            $due_date,
            $subtotal,
            $tax_rate,
            $tax_amount,
            $total_amount,
            $amount_paid,
            $balance_due,
            $payment_status,
            $status,
            $notes
        ]);
        
        $invoice_id = $db->lastInsertId();
        
        // Insert invoice items
        $stmt = $db->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal, product_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $invoice_id,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['subtotal'],
                $item['product_id']
            ]);
        }
        
        // If paid, record payment
        if ($payment_status == 'paid') {
            $stmt = $db->prepare("
                INSERT INTO invoice_payments (invoice_id, user_id, amount, payment_method, payment_date, status)
                VALUES (?, ?, ?, 'manual', CURDATE(), 'completed')
            ");
            $stmt->execute([$invoice_id, $user_id, $total_amount]);
        }
        
        $db->commit();
        
        $_SESSION['success'] = "Invoice created successfully! Invoice #: $invoice_number";
        
        if (isset($_POST['save_and_send']) && $_POST['save_and_send'] == '1') {
            header("Location: send-invoice.php?id=$invoice_id");
            exit();
        } else {
            header("Location: view-invoice.php?id=$invoice_id");
            exit();
        }
        
    } catch(Exception $e) {
        if ($db) $db->rollBack();
        $_SESSION['error'] = 'Error creating invoice: ' . $e->getMessage();
        header('Location: create-invoice.php');
        exit();
    }
}

$page_title = 'Create New Invoice';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Create New Invoice</h1>
                <p class="text-muted mb-0">Create and send invoices to customers</p>
            </div>
            <div>
                <a href="invoices.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Invoices
                </a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="invoiceForm">
            <div class="row">
                <!-- Left Column - Invoice Details -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Invoice Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                                    <select class="form-select" name="user_id" id="customerSelect" required>
                                        <option value="">Select Customer</option>
                                        <?php foreach($customers as $customer): ?>
                                        <option value="<?php echo $customer['id']; ?>">
                                            <?php echo htmlspecialchars($customer['full_name'] . ' - ' . $customer['email']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Customer information will be auto-filled</small>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="invoice_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="due_date" 
                                           value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="draft">Draft</option>
                                        <option value="sent">Sent</option>
                                        <option value="approved">Approved</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Payment Status</label>
                                    <select class="form-select" name="payment_status" id="paymentStatus">
                                        <option value="unpaid">Unpaid</option>
                                        <option value="paid">Paid</option>
                                        <option value="partial">Partial</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" name="tax_rate" 
                                           value="<?php echo $settings['tax_rate'] ?? 10; ?>" 
                                           step="0.01" min="0" max="100">
                                </div>
                                
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Currency</label>
                                    <select class="form-select" disabled>
                                        <option>USD ($)</option>
                                    </select>
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
                                            <th width="45%">Description <span class="text-danger">*</span></th>
                                            <th width="15%">Quantity <span class="text-danger">*</span></th>
                                            <th width="20%">Unit Price <span class="text-danger">*</span></th>
                                            <th width="15%">Amount</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsBody">
                                        <!-- Items will be added here -->
                                        <tr class="item-row">
                                            <td>1</td>
                                            <td>
                                                <input type="text" class="form-control item-description" 
                                                       name="item_description[]" placeholder="Enter description" required>
                                                <input type="hidden" name="item_product_id[]" class="item-product-id">
                                                <small class="text-muted">Select from products or enter custom description</small>
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
                                                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(this)" disabled>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
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
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Additional notes for the customer"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Terms & Conditions</label>
                                <textarea class="form-control" name="terms" rows="3" 
                                          placeholder="Payment terms and conditions">Payment due within 30 days. Late payments subject to 1.5% monthly interest.</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Summary & Actions -->
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
                                    <span id="subtotal">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Tax (<span id="taxRate"><?php echo $settings['tax_rate'] ?? 10; ?></span>%):</span>
                                    <span id="taxAmount">$0.00</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-1 fw-bold">
                                    <span>Total:</span>
                                    <span id="totalAmount">$0.00</span>
                                </div>
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Amount Paid:</span>
                                    <span id="amountPaid">$0.00</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between fw-bold fs-5">
                                    <span>Balance Due:</span>
                                    <span id="balanceDue" class="text-danger">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Info Card -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <div id="customerInfo" class="text-muted">
                                Select a customer to view details
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
                                <button type="submit" name="save_draft" value="1" class="btn btn-outline-primary">
                                    <i class="fas fa-save me-2"></i> Save as Draft
                                </button>
                                
                                <button type="submit" name="save_and_send" value="1" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Save & Send Invoice
                                </button>
                                
                                <button type="submit" name="save_only" value="1" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i> Save Invoice
                                </button>
                                
                                <a href="invoices.php" class="btn btn-outline-danger">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                            
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" id="sendEmail" name="send_email" value="1" checked>
                                <label class="form-check-label" for="sendEmail">
                                    Send email notification to customer
                                </label>
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
let itemCount = 1;

// Add new item row
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
            <small class="text-muted">Select from products or enter custom description</small>
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
    
    // Add event listeners to new inputs
    const newInputs = newRow.querySelectorAll('.item-quantity, .item-price');
    newInputs.forEach(input => {
        input.addEventListener('input', calculateRow);
        input.addEventListener('change', calculateRow);
    });
    
    calculateTotals();
}

// Remove item row
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

// Update row numbers
function updateRowNumbers() {
    const rows = document.querySelectorAll('.item-row');
    rows.forEach((row, index) => {
        row.querySelector('td:first-child').textContent = index + 1;
    });
    itemCount = rows.length;
}

// Calculate row total
function calculateRow() {
    const row = this.closest('.item-row');
    const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const amount = quantity * price;
    
    row.querySelector('.item-amount').value = amount.toFixed(2);
    calculateTotals();
}

// Calculate all totals
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
    
    const paymentStatus = document.getElementById('paymentStatus').value;
    let amountPaid = 0;
    if (paymentStatus === 'paid') {
        amountPaid = totalAmount;
    } else if (paymentStatus === 'partial') {
        amountPaid = totalAmount * 0.5; // Default 50% for partial
    }
    
    const balanceDue = totalAmount - amountPaid;
    
    // Update display
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('taxRate').textContent = taxRate;
    document.getElementById('taxAmount').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('totalAmount').textContent = '$' + totalAmount.toFixed(2);
    document.getElementById('amountPaid').textContent = '$' + amountPaid.toFixed(2);
    document.getElementById('balanceDue').textContent = '$' + balanceDue.toFixed(2);
}

// Add product from select
function addProduct(productId) {
    if (!productId) return;
    
    const select = document.getElementById('productSelect');
    const selectedOption = select.options[select.selectedIndex];
    const productName = selectedOption.getAttribute('data-name');
    const productPrice = selectedOption.getAttribute('data-price');
    
    // Find first empty description or add new row
    let emptyRow = null;
    const rows = document.querySelectorAll('.item-row');
    
    rows.forEach(row => {
        const descInput = row.querySelector('.item-description');
        if (!descInput.value.trim() && !emptyRow) {
            emptyRow = row;
        }
    });
    
    if (emptyRow) {
        emptyRow.querySelector('.item-description').value = productName;
        emptyRow.querySelector('.item-product-id').value = productId;
        emptyRow.querySelector('.item-price').value = productPrice;
    } else {
        addItem();
        const newRow = document.querySelector('.item-row:last-child');
        newRow.querySelector('.item-description').value = productName;
        newRow.querySelector('.item-product-id').value = productId;
        newRow.querySelector('.item-price').value = productPrice;
    }
    
    calculateTotals();
    select.value = '';
}

// Load customer info
document.getElementById('customerSelect').addEventListener('change', function() {
    const customerId = this.value;
    if (!customerId) {
        document.getElementById('customerInfo').innerHTML = 'Select a customer to view details';
        return;
    }
    
    // You can add AJAX call here to get customer details
    const customers = <?php echo json_encode($customers); ?>;
    const customer = customers.find(c => c.id == customerId);
    
    if (customer) {
        let html = `
            <p class="mb-1"><strong>${customer.full_name}</strong></p>
            <p class="mb-1">Email: ${customer.email}</p>
            <p class="mb-1">Phone: ${customer.phone || 'N/A'}</p>
            <p class="mb-0">Address: ${customer.address || 'N/A'}</p>
        `;
        document.getElementById('customerInfo').innerHTML = html;
    }
});

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add listeners to existing inputs
    document.querySelectorAll('.item-quantity, .item-price').forEach(input => {
        input.addEventListener('input', calculateRow);
        input.addEventListener('change', calculateRow);
    });
    
    document.querySelector('input[name="tax_rate"]').addEventListener('input', calculateTotals);
    document.querySelector('input[name="tax_rate"]').addEventListener('change', calculateTotals);
    
    document.getElementById('paymentStatus').addEventListener('change', calculateTotals);
    
    // Initial calculation
    calculateTotals();
    
    // Form validation
    document.getElementById('invoiceForm').addEventListener('submit', function(e) {
        let valid = true;
        const descriptions = document.querySelectorAll('.item-description');
        
        descriptions.forEach(input => {
            if (!input.value.trim()) {
                valid = false;
                input.classList.add('is-invalid');
            } else {
                input.classList.remove('is-invalid');
            }
        });
        
        if (!valid) {
            e.preventDefault();
            Swal.fire('Error', 'Please fill all item descriptions.', 'error');
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>