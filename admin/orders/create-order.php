<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Create New Order';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get customers for dropdown
    $stmt = $db->query("SELECT id, full_name, email, phone, address FROM users WHERE user_type = 'user' ORDER BY full_name");
    $customers = $stmt->fetchAll();
    
    // Get products for selection
    $stmt = $db->query("SELECT id, name, price, stock, image FROM products WHERE stock > 0 ORDER BY name");
    $products = $stmt->fetchAll();
    
    // Get shipping carriers
    $stmt = $db->query("SELECT * FROM shipping_carriers WHERE is_active = 1 ORDER BY name");
    $shipping_carriers = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading data: ' . $e->getMessage();
    redirect('orders.php');
}
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3651c4;
    --primary-light: rgba(67, 97, 238, 0.1);
    --success: #06d6a0;
    --success-dark: #05b585;
    --success-light: rgba(6, 214, 160, 0.1);
    --warning: #ffb703;
    --warning-dark: #e6a500;
    --warning-light: rgba(255, 183, 3, 0.1);
    --danger: #ef476f;
    --danger-dark: #d64161;
    --danger-light: rgba(239, 71, 111, 0.1);
    --info: #4cc9f0;
    --info-dark: #3aa9d9;
    --info-light: rgba(76, 201, 240, 0.1);
    --dark: #2b2d42;
    --dark-light: rgba(43, 45, 66, 0.1);
    --light: #f8f9fa;
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --shadow-glow: 0 0 20px var(--primary-light);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* Animations */
@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInLeft {
    from {
        transform: translateX(-30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.02); }
    100% { transform: scale(1); }
}

/* Main Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--light);
}

.main-content {
    flex: 1;
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    overflow-y: auto;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.6s ease-out;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
    border-radius: 50%;
    z-index: 0;
}

.page-header > div {
    position: relative;
    z-index: 1;
}

.header-title {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.header-title i {
    color: var(--primary);
    margin-right: 10px;
}

.header-subtitle {
    color: var(--dark);
    opacity: 0.7;
}

/* Cards */
.create-order-card {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    margin-bottom: 25px;
    overflow: hidden;
    animation: slideInUp 0.5s ease-out;
    animation-fill-mode: both;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.create-order-card:hover {
    box-shadow: var(--shadow-hover);
}

.create-order-card:nth-child(1) { animation-delay: 0.1s; }
.create-order-card:nth-child(2) { animation-delay: 0.15s; }
.create-order-card:nth-child(3) { animation-delay: 0.2s; }
.create-order-card:nth-child(4) { animation-delay: 0.25s; }

.card-header-custom {
    padding: 20px 25px;
    background: linear-gradient(135deg, var(--light) 0%, white 100%);
    border-bottom: 2px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.card-header-custom h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-custom h5 i {
    color: var(--primary);
    font-size: 1.25rem;
}

.card-body-custom {
    padding: 25px;
}

/* Form Elements */
.form-label {
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.form-label i {
    color: var(--primary);
    font-size: 14px;
}

.form-control, .form-select {
    border-radius: var(--radius);
    border: 2px solid var(--border);
    padding: 10px 15px;
    transition: var(--transition);
    background: white;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-light);
    outline: none;
}

.form-control[readonly] {
    background: var(--light);
    border-color: var(--border);
    color: var(--dark);
}

.form-text {
    color: var(--dark);
    opacity: 0.6;
    font-size: 12px;
    margin-top: 5px;
}

.form-text a {
    color: var(--primary);
    text-decoration: none;
    transition: var(--transition);
}

.form-text a:hover {
    color: var(--primary-dark);
    text-decoration: underline;
}

/* Customer Section */
.customer-select-wrapper {
    position: relative;
}

.customer-select-wrapper select {
    padding-left: 40px;
}

.customer-icon {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary);
    z-index: 10;
    pointer-events: none;
}

.add-customer-link {
    color: var(--info);
    text-decoration: none;
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.add-customer-link:hover {
    color: var(--info-dark);
    text-decoration: underline;
}

.customer-details-card {
    background: var(--primary-light);
    border-radius: var(--radius);
    padding: 15px;
    margin-top: 15px;
    border: 1px solid var(--primary-light);
    animation: fadeIn 0.3s ease-out;
}

.customer-detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border);
}

.customer-detail-item:last-child {
    border-bottom: none;
}

.customer-detail-icon {
    width: 30px;
    height: 30px;
    border-radius: var(--radius-sm);
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
}

.customer-detail-label {
    font-weight: 500;
    color: var(--dark);
    min-width: 80px;
}

.customer-detail-value {
    color: var(--dark);
    opacity: 0.8;
}

/* New Customer Form */
.new-customer-form {
    background: var(--light);
    border-radius: var(--radius);
    padding: 20px;
    margin-top: 15px;
    border: 1px solid var(--border);
    animation: slideInUp 0.3s ease-out;
}

/* Products Table */
.products-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.products-table th {
    padding: 12px 15px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--dark);
    opacity: 0.7;
    background: var(--light);
    border-radius: var(--radius);
}

.products-table td {
    padding: 15px;
    background: var(--light);
    border-radius: var(--radius);
    transition: var(--transition);
    vertical-align: middle;
}

.product-row {
    animation: slideInLeft 0.3s ease-out;
}

.product-row:hover td {
    background: white;
    box-shadow: var(--shadow);
}

.product-select {
    min-width: 200px;
}

.product-price {
    background: white !important;
    font-weight: 600;
    color: var(--primary) !important;
}

.product-quantity {
    width: 100px;
}

.stock-info {
    font-size: 11px;
    margin-top: 4px;
}

.stock-info.warning {
    color: var(--danger);
}

.product-subtotal {
    background: white !important;
    font-weight: 700;
    color: var(--success) !important;
}

.btn-remove {
    background: var(--danger-light);
    color: var(--danger);
    border: 1px solid var(--danger);
    transition: var(--transition-bounce);
}

.btn-remove:hover {
    background: var(--danger);
    color: white;
    transform: scale(1.1);
}

.btn-add-product {
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid var(--primary);
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-add-product:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: var(--shadow-glow);
}

/* Summary Table */
.summary-table {
    width: 100%;
}

.summary-table td {
    padding: 8px 0;
}

.summary-table .label {
    color: var(--dark);
    opacity: 0.7;
}

.summary-table .value {
    font-weight: 500;
    color: var(--dark);
    text-align: right;
}

.summary-table .total {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--primary);
}

.summary-divider {
    border-top: 2px dashed var(--border);
    margin: 10px 0;
}

/* Summary Card */
.summary-card {
    background: linear-gradient(135deg, var(--primary-light) 0%, white 100%);
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-top: 15px;
}

.summary-card h6 {
    color: var(--dark);
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.summary-card h6 i {
    color: var(--primary);
}

/* Action Buttons */
.btn-create {
    background: var(--success);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: var(--radius);
    font-weight: 600;
    transition: var(--transition-bounce);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
}

.btn-create:hover {
    background: var(--success-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px var(--success-light);
}

.btn-preview {
    background: var(--info);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
}

.btn-preview:hover {
    background: var(--info-dark);
    transform: translateY(-2px);
}

.btn-back {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
    padding: 10px 20px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-back:hover {
    background: var(--border);
    transform: translateY(-2px);
    color: var(--dark);
}

/* No Products Row */
.no-products-row td {
    background: var(--light);
    padding: 40px 20px;
}

.no-products-icon {
    font-size: 48px;
    color: var(--dark);
    opacity: 0.2;
    margin-bottom: 15px;
}

.no-products-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.no-products-text {
    color: var(--dark);
    opacity: 0.6;
    margin-bottom: 15px;
}

/* Form Check */
.form-check-input {
    width: 1.2em;
    height: 1.2em;
    margin-top: 0.15em;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.form-check-label {
    color: var(--dark);
    cursor: pointer;
}

/* Gift Message Section */
.gift-message-section {
    background: var(--warning-light);
    border-radius: var(--radius);
    padding: 15px;
    margin-top: 10px;
    border: 1px solid var(--warning);
    animation: fadeIn 0.3s ease-out;
}

/* Alert Info */
.alert-info {
    background: var(--info-light);
    color: var(--info-dark);
    border: 1px solid var(--info);
    border-radius: var(--radius);
    padding: 12px 15px;
}

/* Responsive */
@media (max-width: 992px) {
    .main-content {
        padding: 20px;
    }
    
    .products-table td {
        padding: 10px;
    }
    
    .product-select {
        min-width: 150px;
    }
}

@media (max-width: 768px) {
    .card-header-custom {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .products-table {
        display: block;
        overflow-x: auto;
    }
    
    .btn-add-product {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .customer-detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .customer-detail-label {
        min-width: auto;
    }
}

/* Loading Animation */
@keyframes spin {
    to { transform: rotate(360deg); }
}

.fa-spinner {
    animation: spin 1s linear infinite;
}
</style>

<div class="dashboard-container">
   
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="header-title">
                        <i class="fas fa-plus-circle"></i>
                        Create New Order
                    </h1>
                    <p class="header-subtitle">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Manually create an order for a customer
                    </p>
                </div>
                <div>
                    <a href="orders.php" class="btn-back">
                        <i class="fas fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>

        <form id="createOrderForm" method="POST" action="process-create-order.php">
            <div class="row g-4">
                <!-- Left Column: Customer & Products -->
                <div class="col-lg-8">
                    <!-- Customer Selection Card -->
                    <div class="create-order-card">
                        <div class="card-header-custom">
                            <h5>
                                <i class="fas fa-user"></i>
                                Customer Information
                            </h5>
                            <span class="badge bg-primary">Step 1</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="customer-select-wrapper">
                                        <i class="fas fa-search customer-icon"></i>
                                        <select class="form-select" id="customerSelect" name="customer_id">
                                            <option value="">Select Existing Customer</option>
                                            <?php foreach($customers as $customer): ?>
                                            <option value="<?php echo $customer['id']; ?>"
                                                    data-email="<?php echo htmlspecialchars($customer['email']); ?>"
                                                    data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                                    data-address="<?php echo htmlspecialchars($customer['address']); ?>">
                                                <?php echo htmlspecialchars($customer['full_name']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="text-muted">Or</span>
                                        <a href="#" class="add-customer-link" onclick="showNewCustomerForm(); return false;">
                                            <i class="fas fa-plus-circle"></i> Add New Customer
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- New Customer Form -->
                            <div id="newCustomerForm" class="new-customer-form" style="display: none;">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-user"></i> Full Name *
                                        </label>
                                        <input type="text" class="form-control" name="new_customer_name" placeholder="Enter full name">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-envelope"></i> Email *
                                        </label>
                                        <input type="email" class="form-control" name="new_customer_email" placeholder="Enter email">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-phone"></i> Phone
                                        </label>
                                        <input type="text" class="form-control" name="new_customer_phone" placeholder="Enter phone">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            <i class="fas fa-map-marker-alt"></i> Address
                                        </label>
                                        <input type="text" class="form-control" name="new_customer_address" placeholder="Enter address">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Details (Auto-filled) -->
                            <div id="customerDetails" class="customer-details-card" style="display: none;">
                                <div class="customer-detail-item">
                                    <span class="customer-detail-icon">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <span class="customer-detail-label">Email:</span>
                                    <span class="customer-detail-value" id="customerEmail"></span>
                                </div>
                                <div class="customer-detail-item">
                                    <span class="customer-detail-icon">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <span class="customer-detail-label">Phone:</span>
                                    <span class="customer-detail-value" id="customerPhone"></span>
                                </div>
                                <div class="customer-detail-item">
                                    <span class="customer-detail-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </span>
                                    <span class="customer-detail-label">Address:</span>
                                    <span class="customer-detail-value" id="customerAddress"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products Selection Card -->
                    <div class="create-order-card">
                        <div class="card-header-custom">
                            <h5>
                                <i class="fas fa-box"></i>
                                Order Items
                            </h5>
                            <div class="d-flex gap-2">
                                <span class="badge bg-info">Step 2</span>
                                <button type="button" class="btn-add-product" onclick="addProductRow()">
                                    <i class="fas fa-plus"></i> Add Product
                                </button>
                            </div>
                        </div>
                        <div class="card-body-custom">
                            <div class="table-responsive">
                                <table class="products-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="productsBody">
                                        <!-- Rows will be added dynamically -->
                                        <tr id="noProductsRow">
                                            <td colspan="5" class="no-products-row">
                                                <div class="text-center">
                                                    <div class="no-products-icon">
                                                        <i class="fas fa-box-open"></i>
                                                    </div>
                                                    <h5 class="no-products-title">No Products Added</h5>
                                                    <p class="no-products-text">Click the button below to add products</p>
                                                    <button type="button" class="btn-add-product" onclick="addProductRow()">
                                                        <i class="fas fa-plus"></i> Add First Product
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Notes Card -->
                    <div class="create-order-card">
                        <div class="card-header-custom">
                            <h5>
                                <i class="fas fa-sticky-note"></i>
                                Order Notes
                            </h5>
                            <span class="badge bg-secondary">Optional</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-comment"></i> Customer Notes
                                    </label>
                                    <textarea class="form-control" name="customer_notes" rows="4" 
                                              placeholder="Any notes from the customer..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-lock"></i> Internal Notes
                                    </label>
                                    <textarea class="form-control" name="internal_notes" rows="4" 
                                              placeholder="Internal notes for staff..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Shipping & Payment -->
                <div class="col-lg-4">
                    <!-- Shipping Information Card -->
                    <div class="create-order-card">
                        <div class="card-header-custom">
                            <h5>
                                <i class="fas fa-truck"></i>
                                Shipping Information
                            </h5>
                            <span class="badge bg-warning">Step 3</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Shipping Address *
                                </label>
                                <textarea class="form-control" name="shipping_address" id="shippingAddress" 
                                          rows="3" required placeholder="Enter shipping address..."></textarea>
                                <div class="form-text">
                                    <a href="#" onclick="copyBillingToShipping(); return false;">
                                        <i class="fas fa-copy"></i> Copy from customer address
                                    </a>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="fas fa-map-marker-alt"></i> Billing Address
                                </label>
                                <textarea class="form-control" name="billing_address" id="billingAddress" 
                                          rows="3" placeholder="Enter billing address..."></textarea>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="sameAsShipping" 
                                           onclick="toggleBillingAddress()">
                                    <label class="form-check-label" for="sameAsShipping">
                                        Same as shipping address
                                    </label>
                                </div>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-shipping-fast"></i> Shipping Method
                                    </label>
                                    <select class="form-select" name="shipping_method" required>
                                        <option value="standard">Standard Shipping (3-5 days)</option>
                                        <option value="express">Express Shipping (1-2 days)</option>
                                        <option value="overnight">Overnight Delivery</option>
                                        <option value="pickup">Store Pickup</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-truck"></i> Shipping Carrier
                                    </label>
                                    <select class="form-select" name="shipping_carrier_id">
                                        <option value="">Select Carrier</option>
                                        <?php foreach($shipping_carriers as $carrier): ?>
                                        <option value="<?php echo $carrier['id']; ?>">
                                            <?php echo htmlspecialchars($carrier['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-hashtag"></i> Tracking Number
                                    </label>
                                    <input type="text" class="form-control" name="tracking_number" 
                                           placeholder="Enter tracking number">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Information Card -->
                    <div class="create-order-card">
                        <div class="card-header-custom">
                            <h5>
                                <i class="fas fa-credit-card"></i>
                                Payment Information
                            </h5>
                            <span class="badge bg-success">Step 4</span>
                        </div>
                        <div class="card-body-custom">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-credit-card"></i> Payment Method *
                                    </label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="cod">Cash on Delivery</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="paypal">PayPal</option>
                                        <option value="cash">Cash</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-check-circle"></i> Payment Status *
                                    </label>
                                    <select class="form-select" name="payment_status" required>
                                        <option value="pending">Pending</option>
                                        <option value="completed">Completed</option>
                                        <option value="failed">Failed</option>
                                        <option value="refunded">Refunded</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-hourglass-half"></i> Order Status *
                                    </label>
                                    <select class="form-select" name="order_status" required>
                                        <option value="pending">Pending</option>
                                        <option value="processing">Processing</option>
                                        <option value="shipped">Shipped</option>
                                        <option value="delivered">Delivered</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">
                                        <i class="fas fa-flag"></i> Order Priority
                                    </label>
                                    <select class="form-select" name="order_priority">
                                        <option value="normal">Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_gift" id="isGift">
                                        <label class="form-check-label" for="isGift">
                                            <i class="fas fa-gift"></i> This is a gift order
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Gift Message Section -->
                            <div id="giftMessageSection" class="gift-message-section" style="display: none;">
                                <label class="form-label">
                                    <i class="fas fa-heart"></i> Gift Message
                                </label>
                                <textarea class="form-control" name="gift_message" rows="3" 
                                          placeholder="Write a gift message..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Summary Card -->
                    <div class="create-order-card">
                        <div class="card-body-custom">
                            <div class="summary-card">
                                <h6>
                                    <i class="fas fa-calculator"></i>
                                    Order Summary
                                </h6>
                                
                                <table class="summary-table">
                                    <tr>
                                        <td class="label">Subtotal:</td>
                                        <td class="value" id="summarySubtotal">$0.00</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Shipping:</td>
                                        <td class="value" id="summaryShipping">$5.99</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Tax (<span id="summaryTaxRate">10</span>%):</td>
                                        <td class="value" id="summaryTax">$0.00</td>
                                    </tr>
                                    <tr>
                                        <td colspan="2" class="summary-divider"></td>
                                    </tr>
                                    <tr>
                                        <td class="label"><strong>Total:</strong></td>
                                        <td class="total" id="summaryTotal">$0.00</td>
                                    </tr>
                                </table>
                                
                                <hr class="my-3">
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn-create" id="submitBtn">
                                        <i class="fas fa-check-circle"></i> Create Order
                                    </button>
                                    <button type="button" class="btn-preview" onclick="previewOrder()">
                                        <i class="fas fa-eye"></i> Preview Order
                                    </button>
                                </div>
                                
                                <div class="alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Order will be created immediately with the selected details.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<!-- Hidden inputs for calculations -->
<input type="hidden" id="shippingCost" name="shipping_cost" value="5.99">
<input type="hidden" id="taxRate" name="tax_rate" value="10">
<input type="hidden" id="subtotal" name="subtotal" value="0">
<input type="hidden" id="totalAmount" name="total_amount" value="0">

<!-- Product Row Template -->
<template id="productRowTemplate">
    <tr class="product-row">
        <td>
            <select class="form-select product-select" onchange="updateProductDetails(this)" required>
                <option value="">Select Product</option>
                <?php foreach($products as $product): ?>
                <option value="<?php echo $product['id']; ?>"
                        data-price="<?php echo $product['price']; ?>"
                        data-stock="<?php echo $product['stock']; ?>"
                        data-name="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php echo htmlspecialchars($product['name']); ?> - $<?php echo number_format($product['price'], 2); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="text" class="form-control product-price" name="prices[]" readonly>
        </td>
        <td>
            <input type="number" class="form-control product-quantity" name="quantities[]" 
                   min="1" value="1" onchange="updateSubtotal(this)" required>
            <div class="stock-info"></div>
        </td>
        <td>
            <input type="text" class="form-control product-subtotal" name="subtotals[]" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-remove" onclick="removeProductRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Product row counter
let productRowCounter = 0;

// Add product row
function addProductRow() {
    const template = document.getElementById('productRowTemplate');
    const clone = template.content.cloneNode(true);
    const row = clone.querySelector('.product-row');
    row.dataset.id = ++productRowCounter;
    
    const productsBody = document.getElementById('productsBody');
    const noProductsRow = document.getElementById('noProductsRow');
    
    if (noProductsRow) {
        noProductsRow.remove();
    }
    
    productsBody.appendChild(row);
    updateOrderSummary();
    
    // Highlight new row
    row.style.animation = 'slideInLeft 0.3s ease-out';
}

// Remove product row
function removeProductRow(button) {
    Swal.fire({
        title: 'Remove Product?',
        text: 'Are you sure you want to remove this product from the order?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: 'var(--danger)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: 'Yes, remove it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const row = button.closest('.product-row');
            row.style.animation = 'slideInLeft 0.3s ease-out reverse';
            setTimeout(() => {
                row.remove();
                
                // Show no products message if empty
                const productsBody = document.getElementById('productsBody');
                if (productsBody.children.length === 0) {
                    productsBody.innerHTML = `
                        <tr id="noProductsRow">
                            <td colspan="5" class="no-products-row">
                                <div class="text-center">
                                    <div class="no-products-icon">
                                        <i class="fas fa-box-open"></i>
                                    </div>
                                    <h5 class="no-products-title">No Products Added</h5>
                                    <p class="no-products-text">Click the button below to add products</p>
                                    <button type="button" class="btn-add-product" onclick="addProductRow()">
                                        <i class="fas fa-plus"></i> Add First Product
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                
                updateOrderSummary();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Removed!',
                    text: 'Product has been removed from order.',
                    timer: 1500,
                    showConfirmButton: false
                });
            }, 150);
        }
    });
}

// Update product details when selected
function updateProductDetails(select) {
    const row = select.closest('.product-row');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const price = selectedOption.getAttribute('data-price');
        const stock = selectedOption.getAttribute('data-stock');
        const name = selectedOption.getAttribute('data-name');
        
        row.querySelector('.product-price').value = '$' + parseFloat(price).toFixed(2);
        
        const stockInfo = row.querySelector('.stock-info');
        stockInfo.textContent = 'Stock: ' + stock;
        stockInfo.className = stock < 5 ? 'stock-info warning' : 'stock-info';
        
        const quantityInput = row.querySelector('.product-quantity');
        quantityInput.max = stock;
        quantityInput.value = 1;
        
        updateSubtotal(quantityInput);
        
        // Show success toast
        Swal.fire({
            icon: 'success',
            title: 'Product Added',
            text: `${name} added to order`,
            timer: 1500,
            showConfirmButton: false,
            position: 'top-end',
            toast: true
        });
    }
}

// Update subtotal for a product
function updateSubtotal(input) {
    const row = input.closest('.product-row');
    const priceInput = row.querySelector('.product-price');
    const subtotalInput = row.querySelector('.product-subtotal');
    
    const price = parseFloat(priceInput.value.replace('$', '')) || 0;
    let quantity = parseInt(input.value) || 0;
    const maxStock = parseInt(input.max) || 0;
    
    if (quantity > maxStock) {
        quantity = maxStock;
        input.value = maxStock;
        Swal.fire({
            icon: 'warning',
            title: 'Stock Limit',
            text: `Only ${maxStock} items available in stock`,
            timer: 2000,
            showConfirmButton: false
        });
    }
    
    const subtotal = price * quantity;
    subtotalInput.value = '$' + subtotal.toFixed(2);
    
    // Animate subtotal change
    subtotalInput.style.backgroundColor = 'var(--success-light)';
    setTimeout(() => {
        subtotalInput.style.backgroundColor = 'white';
    }, 300);
    
    updateOrderSummary();
}

// Update order summary
function updateOrderSummary() {
    let subtotal = 0;
    
    document.querySelectorAll('.product-subtotal').forEach(input => {
        const value = parseFloat(input.value.replace('$', '')) || 0;
        subtotal += value;
    });
    
    const shippingCost = parseFloat(document.getElementById('shippingCost').value) || 0;
    const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
    
    const taxAmount = (subtotal * taxRate) / 100;
    const totalAmount = subtotal + shippingCost + taxAmount;
    
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('totalAmount').value = totalAmount.toFixed(2);
    
    // Update display with animation
    const elements = {
        'summarySubtotal': subtotal,
        'summaryShipping': shippingCost,
        'summaryTax': taxAmount,
        'summaryTotal': totalAmount
    };
    
    Object.entries(elements).forEach(([id, value]) => {
        const element = document.getElementById(id);
        element.style.transform = 'scale(1.1)';
        element.style.color = 'var(--primary)';
        element.textContent = '$' + value.toFixed(2);
        
        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.color = id === 'summaryTotal' ? 'var(--primary)' : 'var(--dark)';
        }, 200);
    });
    
    document.getElementById('summaryTaxRate').textContent = taxRate;
}

// Customer selection
document.getElementById('customerSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const customerDetails = document.getElementById('customerDetails');
    
    if (selectedOption.value) {
        const email = selectedOption.getAttribute('data-email') || '';
        const phone = selectedOption.getAttribute('data-phone') || '';
        const address = selectedOption.getAttribute('data-address') || '';
        
        document.getElementById('customerEmail').textContent = email;
        document.getElementById('customerPhone').textContent = phone;
        document.getElementById('customerAddress').textContent = address;
        document.getElementById('shippingAddress').value = address;
        
        customerDetails.style.display = 'block';
        customerDetails.style.animation = 'fadeIn 0.3s ease-out';
    } else {
        customerDetails.style.display = 'none';
    }
});

// Toggle billing address
function toggleBillingAddress() {
    const checkbox = document.getElementById('sameAsShipping');
    const billingAddress = document.getElementById('billingAddress');
    
    if (checkbox.checked) {
        billingAddress.value = document.getElementById('shippingAddress').value;
        billingAddress.readOnly = true;
        billingAddress.style.backgroundColor = 'var(--light)';
    } else {
        billingAddress.readOnly = false;
        billingAddress.style.backgroundColor = 'white';
    }
}

// Copy billing to shipping
function copyBillingToShipping() {
    const shippingAddress = document.getElementById('shippingAddress');
    const customerAddress = document.getElementById('customerAddress').textContent;
    
    if (customerAddress) {
        shippingAddress.value = customerAddress;
        shippingAddress.style.backgroundColor = 'var(--success-light)';
        setTimeout(() => {
            shippingAddress.style.backgroundColor = 'white';
        }, 500);
    }
}

// Show new customer form
function showNewCustomerForm() {
    const newCustomerForm = document.getElementById('newCustomerForm');
    const customerSelect = document.getElementById('customerSelect');
    
    if (newCustomerForm.style.display === 'none') {
        newCustomerForm.style.display = 'block';
        customerSelect.value = '';
        document.getElementById('customerDetails').style.display = 'none';
    } else {
        newCustomerForm.style.display = 'none';
    }
}

// Gift checkbox
document.getElementById('isGift').addEventListener('change', function() {
    const giftMessageSection = document.getElementById('giftMessageSection');
    if (this.checked) {
        giftMessageSection.style.display = 'block';
        giftMessageSection.style.animation = 'fadeIn 0.3s ease-out';
    } else {
        giftMessageSection.style.display = 'none';
    }
});

// Preview order
function previewOrder() {
    const productRows = document.querySelectorAll('.product-row');
    if (productRows.length === 0) {
        Swal.fire({
            icon: 'error',
            title: 'No Products',
            text: 'Please add at least one product to the order.'
        });
        return;
    }
    
    let hasEmptyProducts = false;
    document.querySelectorAll('.product-select').forEach(select => {
        if (!select.value) hasEmptyProducts = true;
    });
    
    if (hasEmptyProducts) {
        Swal.fire({
            icon: 'error',
            title: 'Incomplete',
            text: 'Please select a product for all rows.'
        });
        return;
    }
    
    const customerName = document.getElementById('customerSelect').selectedOptions[0]?.text.split('(')[0].trim() || 'New Customer';
    const productCount = productRows.length;
    const subtotal = document.getElementById('summarySubtotal').textContent;
    const shipping = document.getElementById('summaryShipping').textContent;
    const tax = document.getElementById('summaryTax').textContent;
    const total = document.getElementById('summaryTotal').textContent;
    
    Swal.fire({
        title: 'Order Preview',
        html: `
            <div class="text-start" style="background: var(--light); padding: 20px; border-radius: var(--radius);">
                <div class="mb-3">
                    <strong style="color: var(--primary);">Customer:</strong>
                    <span class="ms-2">${customerName}</span>
                </div>
                <div class="mb-3">
                    <strong style="color: var(--primary);">Items:</strong>
                    <span class="ms-2">${productCount} product(s)</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <strong>${subtotal}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Shipping:</span>
                    <strong>${shipping}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Tax:</span>
                    <strong>${tax}</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong style="color: var(--primary);">Total:</strong>
                    <strong style="color: var(--primary); font-size: 1.2rem;">${total}</strong>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: 'var(--success)',
        cancelButtonColor: 'var(--primary)',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Create Order',
        cancelButtonText: '<i class="fas fa-edit me-2"></i>Edit Order',
        background: 'white',
        backdrop: `rgba(67,97,238,0.1)`
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('createOrderForm').submit();
        }
    });
}

// Form submission
document.getElementById('createOrderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating Order...';
    submitBtn.disabled = true;
    
    // Validate
    const productRows = document.querySelectorAll('.product-row');
    if (productRows.length === 0) {
        Swal.fire('Error!', 'Please add at least one product to the order.', 'error');
        submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Create Order';
        submitBtn.disabled = false;
        return;
    }
    
    let hasEmptyProducts = false;
    document.querySelectorAll('.product-select').forEach(select => {
        if (!select.value) hasEmptyProducts = true;
    });
    
    if (hasEmptyProducts) {
        Swal.fire('Error!', 'Please select a product for all rows.', 'error');
        submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Create Order';
        submitBtn.disabled = false;
        return;
    }
    
    // Submit via AJAX
    const formData = new FormData(this);
    
    fetch('process-create-order.php', {
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
                confirmButtonColor: 'var(--success)',
                confirmButtonText: 'View Order',
                showCancelButton: true,
                cancelButtonText: 'Back to Orders'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'order-details.php?id=' + data.order_id;
                } else {
                    window.location.href = 'orders.php';
                }
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
            submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Create Order';
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred while creating the order.', 'error');
        submitBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Create Order';
        submitBtn.disabled = false;
    });
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    addProductRow();
    
    document.getElementById('shippingCost').addEventListener('input', updateOrderSummary);
    document.getElementById('taxRate').addEventListener('input', updateOrderSummary);
});
</script>

<?php require_once '../../includes/footer.php'; ?>