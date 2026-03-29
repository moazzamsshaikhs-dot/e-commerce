<?php
// admin/create-invoice.php
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
    
    $stmt = $db->query("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
    $last_invoice = $stmt->fetch();
    
    if ($last_invoice && preg_match('/INV-(\d+)-(\d+)/', $last_invoice['invoice_number'], $matches)) {
        $number = intval($matches[2]) + 1;
        return 'INV-' . date('Y') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
    
    return 'INV-' . date('Y') . '-0001';
}
/**
 * Get setting value from database (Updated version)
 */
function getSetting($key, $default = null) {
    static $settings_cache = null;
    
    // Load all settings into cache once
    if ($settings_cache === null) {
        try {
            $db = getDB();
            $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
            $settings_cache = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings_cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch(Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
            return $default;
        }
    }
    
    return isset($settings_cache[$key]) ? $settings_cache[$key] : $default;
}
// Get customers and products
try {
    $db = getDB();
    
    $stmt = $db->query("SELECT id, full_name, email, phone, address FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();
    
    $stmt = $db->query("SELECT id, name, price, stock FROM products WHERE stock > 0 ORDER BY name");
    $products = $stmt->fetchAll();
    
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
        
        if (empty($_POST['item_description']) || !is_array($_POST['item_description'])) {
            throw new Exception("Please add at least one item to the invoice.");
        }
        
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
        
        $tax_rate = floatval($_POST['tax_rate'] ?? ($settings['tax_rate'] ?? 10));
        $tax_amount = ($subtotal * $tax_rate) / 100;
        $total_amount = $subtotal + $tax_amount;
        $amount_paid = $payment_status == 'paid' ? $total_amount : 0;
        $balance_due = $total_amount - $amount_paid;
        
        $invoice_number = generateInvoiceNumber();
        
        $stmt = $db->prepare("
            INSERT INTO invoices (
                invoice_number, user_id, invoice_date, due_date,
                subtotal, tax_rate, tax_amount, total_amount,
                amount_paid, balance_due, payment_status, status,
                notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $invoice_number, $user_id, $invoice_date, $due_date,
            $subtotal, $tax_rate, $tax_amount, $total_amount,
            $amount_paid, $balance_due, $payment_status, $status,
            $notes
        ]);
        
        $invoice_id = $db->lastInsertId();
        
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

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --primary-light: #4895ef;
    --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
    --success: #06d6a0;
    --success-dark: #0ca678;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --dark: #2b2d42;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
    --border-radius-full: 9999px;
}

.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--gray-100);
}

.main-content {
    flex: 1;
    padding: 2rem;
    background: var(--gray-100);
    transition: var(--transition);
    width: 100%;
}

@media (max-width: 992px) {
    .main-content {
        padding: 1rem;
    }
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 1.5rem 2rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success), var(--warning), var(--danger));
}

.page-header h1 {
    font-size: 1.5rem;
    font-weight: 700;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

/* Form Cards */
.form-card {
    background: white;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    margin-bottom: 1.5rem;
}

.form-card .card-header {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.form-card .card-header h5 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.form-card .card-header h5 i {
    color: var(--primary);
}

.form-card .card-body {
    padding: 1.25rem;
}

/* Form Controls */
.form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-control, .form-select {
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-300);
    padding: 0.6rem 0.75rem;
    font-size: 0.85rem;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    outline: none;
}

/* Summary Card */
.summary-card {
    background: white;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    margin-bottom: 1.5rem;
    position: sticky;
    top: 1rem;
}

.summary-card .card-header {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
}

.summary-card .card-header h5 {
    font-weight: 600;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.summary-card .summary-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px dashed var(--gray-200);
}

.summary-card .summary-item:last-child {
    border-bottom: none;
}

.summary-card .summary-label {
    color: var(--gray-600);
    font-size: 0.85rem;
}

.summary-card .summary-value {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.85rem;
}

.summary-card .summary-total {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary);
}

.summary-card .balance-due {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--danger);
}

/* Table Styles */
.items-table {
    margin-bottom: 0;
}

.items-table th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 0.75rem;
    border-bottom: 2px solid var(--gray-300);
}

.items-table td {
    padding: 0.75rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--gray-200);
}

/* Button Styles */
.btn {
    padding: 0.6rem 1rem;
    font-size: 0.85rem;
    border-radius: var(--border-radius-md);
    transition: var(--transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-primary {
    background: var(--primary-gradient);
    border: none;
    color: white;
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
}

.btn-outline-primary {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.btn-outline-danger {
    background: transparent;
    border: 1px solid var(--danger);
    color: var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger);
    color: white;
}

.btn-sm {
    padding: 0.3rem 0.6rem;
    font-size: 0.75rem;
}

/* Alert Styles */
.alert {
    border: none;
    border-radius: var(--border-radius-lg);
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: var(--shadow-md);
}

.alert-success {
    background: rgba(6, 214, 160, 0.1);
    color: var(--success-dark);
    border-left: 4px solid var(--success);
}

.alert-danger {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger-dark);
    border-left: 4px solid var(--danger);
}

/* Customer Info Card */
.customer-info-card {
    background: white;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    margin-bottom: 1.5rem;
}

.customer-info-card .card-header {
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
}

.customer-info-card .customer-details {
    padding: 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
}

/* Responsive */
@media (max-width: 992px) {
    .items-table {
        min-width: 650px;
    }
    
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .summary-card {
        position: static;
    }
}

@media (max-width: 768px) {
    .form-card .card-header {
        flex-direction: column;
        text-align: center;
    }
    
    .btn-group {
        flex-wrap: wrap;
    }
    
    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius-full);
}
</style>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1>
                        <i class="fas fa-file-invoice"></i>
                        Create New Invoice
                    </h1>
                    <p class="text-muted mb-0">Create and send professional invoices to customers</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="invoices.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Invoices
                    </a>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="invoiceForm">
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <!-- Invoice Details Card -->
                    <div class="form-card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-info-circle"></i>
                                Invoice Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Customer <span class="text-danger">*</span>
                                    </label>
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
                                
                                <div class="col-md-3">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-alt"></i> Invoice Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" name="invoice_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="col-md-3">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-check"></i> Due Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" name="due_date" 
                                           value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-tag"></i> Status
                                    </label>
                                    <select class="form-select" name="status">
                                        <option value="draft"> Draft</option>
                                        <option value="sent"> Sent</option>
                                        <option value="approved"> Approved</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-credit-card"></i> Payment Status
                                    </label>
                                    <select class="form-select" name="payment_status" id="paymentStatus">
                                        <option value="unpaid"> Unpaid</option>
                                        <option value="paid"> Paid</option>
                                        <option value="partial"> Partial</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">
                                        <i class="fas fa-percent"></i> Tax Rate (%)
                                    </label>
                                    <input type="number" class="form-control" name="tax_rate" 
                                           value="<?php echo $settings['tax_rate'] ?? 10; ?>" 
                                           step="0.01" min="0" max="100">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Items Card -->
                    <div class="form-card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-list"></i>
                                Invoice Items
                            </h5>
                            <button type="button" class="btn btn-primary btn-sm" onclick="addItem()">
                                <i class="fas fa-plus me-1"></i> Add Item
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table items-table" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th width="5%">#</th>
                                            <th width="45%">Description</th>
                                            <th width="15%">Quantity</th>
                                            <th width="15%">Unit Price</th>
                                            <th width="15%">Amount</th>
                                            <th width="5%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsBody">
                                        <tr class="item-row">
                                            <td>1</td>
                                            <td>
                                                <input type="text" class="form-control item-description" 
                                                       name="item_description[]" placeholder="Enter item description" required>
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
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)" disabled>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="5" class="text-end">
                                                <div class="mt-3">
                                                    <label class="form-label">Quick Add Products:</label>
                                                    <select class="form-select" id="productSelect" onchange="addProduct(this.value)">
                                                        <option value="">-- Select product to add --</option>
                                                        <?php foreach($products as $product): ?>
                                                        <option value="<?php echo $product['id']; ?>" 
                                                                data-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                                data-price="<?php echo $product['price']; ?>">
                                                            <?php echo htmlspecialchars($product['name']); ?> - $<?php echo number_format($product['price'], 2); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes Card -->
                    <div class="form-card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-sticky-note"></i>
                                Notes & Terms
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Notes to Customer</label>
                                <textarea class="form-control" name="notes" rows="3" 
                                          placeholder="Additional notes for the customer..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Terms & Conditions</label>
                                <textarea class="form-control" name="terms" rows="3" 
                                          placeholder="Payment terms and conditions...">Payment due within 30 days. Late payments subject to 1.5% monthly interest.</textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Summary Card -->
                    <div class="summary-card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-calculator"></i>
                                Invoice Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="summary-item">
                                <span class="summary-label">Subtotal:</span>
                                <span class="summary-value" id="subtotal">$0.00</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Tax (<span id="taxRate"><?php echo $settings['tax_rate'] ?? 10; ?></span>%):</span>
                                <span class="summary-value" id="taxAmount">$0.00</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Total:</span>
                                <span class="summary-value summary-total" id="totalAmount">$0.00</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Amount Paid:</span>
                                <span class="summary-value" id="amountPaid">$0.00</span>
                            </div>
                            <div class="summary-item pt-2 mt-2" style="border-top: 2px solid var(--gray-300);">
                                <span class="summary-label fw-bold">Balance Due:</span>
                                <span class="summary-value balance-due" id="balanceDue">$0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Info Card -->
                    <div class="customer-info-card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-user-circle"></i>
                                Customer Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="customerInfo" class="text-muted text-center py-4">
                                <i class="fas fa-user fa-2x mb-2 d-block"></i>
                                Select a customer to view details
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions Card -->
                    <div class="summary-card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-bolt"></i>
                                Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" name="save_draft" value="1" class="btn btn-outline-primary">
                                    <i class="fas fa-save me-2"></i> Save as Draft
                                </button>
                                <button type="submit" name="save_and_send" value="1" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Save & Send
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
                                    <i class="fas fa-envelope me-1"></i> Send email notification
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

function addItem() {
    itemCount++;
    const tbody = document.getElementById('itemsBody');
    const newRow = document.createElement('tr');
    newRow.className = 'item-row';
    newRow.innerHTML = `
        <td>${itemCount}</td>
        <td>
            <input type="text" class="form-control item-description" 
                   name="item_description[]" placeholder="Enter item description" required>
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
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(newRow);
    
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
    
    const paymentStatus = document.getElementById('paymentStatus').value;
    let amountPaid = 0;
    if (paymentStatus === 'paid') {
        amountPaid = totalAmount;
    } else if (paymentStatus === 'partial') {
        amountPaid = totalAmount * 0.5;
    }
    
    const balanceDue = totalAmount - amountPaid;
    
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('taxRate').textContent = taxRate;
    document.getElementById('taxAmount').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('totalAmount').textContent = '$' + totalAmount.toFixed(2);
    document.getElementById('amountPaid').textContent = '$' + amountPaid.toFixed(2);
    document.getElementById('balanceDue').textContent = '$' + balanceDue.toFixed(2);
}

function addProduct(productId) {
    if (!productId) return;
    
    const select = document.getElementById('productSelect');
    const selectedOption = select.options[select.selectedIndex];
    const productName = selectedOption.getAttribute('data-name');
    const productPrice = selectedOption.getAttribute('data-price');
    
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

document.getElementById('customerSelect').addEventListener('change', function() {
    const customerId = this.value;
    if (!customerId) {
        document.getElementById('customerInfo').innerHTML = '<i class="fas fa-user fa-2x mb-2 d-block"></i>Select a customer to view details';
        return;
    }
    
    const customers = <?php echo json_encode($customers); ?>;
    const customer = customers.find(c => c.id == customerId);
    
    if (customer) {
        let html = `
            <div class="customer-details">
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar-sm bg-primary text-white rounded-circle me-3" style="width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user fa-1x"></i>
                    </div>
                    <div>
                        <h6 class="mb-0">${customer.full_name}</h6>
                        <small class="text-muted">Customer since</small>
                    </div>
                </div>
                <p class="mb-1"><i class="fas fa-envelope me-2 text-primary"></i> ${customer.email}</p>
                <p class="mb-1"><i class="fas fa-phone me-2 text-primary"></i> ${customer.phone || 'N/A'}</p>
                <p class="mb-0"><i class="fas fa-map-marker-alt me-2 text-primary"></i> ${customer.address || 'N/A'}</p>
            </div>
        `;
        document.getElementById('customerInfo').innerHTML = html;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.item-quantity, .item-price').forEach(input => {
        input.addEventListener('input', calculateRow);
        input.addEventListener('change', calculateRow);
    });
    
    document.querySelector('input[name="tax_rate"]').addEventListener('input', calculateTotals);
    document.getElementById('paymentStatus').addEventListener('change', calculateTotals);
    
    calculateTotals();
    
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