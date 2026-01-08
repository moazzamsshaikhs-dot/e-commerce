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

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Create New Order</h1>
                <p class="text-muted mb-0">Manually create an order for a customer</p>
            </div>
            <div>
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Orders
                </a>
            </div>
        </div>

        <form id="createOrderForm" method="POST" action="<?php echo SITE_URL; ?>admin/orders/process-create-order.php">
            <div class="row">
                <!-- Left Column: Customer & Products -->
                <div class="col-lg-8">
                    <!-- Customer Selection -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-user me-2 text-primary"></i>Customer Information
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
                                                data-email="<?php echo htmlspecialchars($customer['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($customer['phone']); ?>"
                                                data-address="<?php echo htmlspecialchars($customer['address']); ?>">
                                            <?php echo htmlspecialchars($customer['full_name']); ?> (<?php echo htmlspecialchars($customer['email']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Or <a href="#" onclick="showNewCustomerForm()">Add New Customer</a></label>
                                    <div id="newCustomerForm" style="display: none;">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <input type="text" class="form-control" name="new_customer_name" placeholder="Full Name">
                                            </div>
                                            <div class="col-6">
                                                <input type="email" class="form-control" name="new_customer_email" placeholder="Email">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customer Details (Auto-filled) -->
                            <div class="row mt-3 g-3" id="customerDetails" style="display: none;">
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" id="customerEmail" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="customerPhone" readonly>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" id="customerAddress" rows="2" readonly></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products Selection -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-box me-2 text-primary"></i>Order Items
                            </h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addProductRow()">
                                <i class="fas fa-plus me-1"></i> Add Product
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table" id="productsTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="40%">Product</th>
                                            <th width="15%">Price</th>
                                            <th width="15%">Quantity</th>
                                            <th width="15%">Subtotal</th>
                                            <th width="15%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="productsBody">
                                        <!-- Rows will be added dynamically -->
                                        <tr id="noProductsRow">
                                            <td colspan="5" class="text-center py-4">
                                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No products added yet</p>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProductRow()">
                                                    <i class="fas fa-plus me-1"></i> Add First Product
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                            <td colspan="2">
                                                <strong id="subtotalDisplay">$0.00</strong>
                                                <input type="hidden" name="subtotal" id="subtotal" value="0">
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end">
                                                <label class="form-label mb-0">Shipping Cost:</label>
                                            </td>
                                            <td colspan="2">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" class="form-control" name="shipping_cost" id="shippingCost" value="5.99" step="0.01" min="0">
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="text-end">
                                                <label class="form-label mb-0">Tax Rate (%):</label>
                                            </td>
                                            <td colspan="2">
                                                <div class="input-group input-group-sm">
                                                    <input type="number" class="form-control" name="tax_rate" id="taxRate" value="10" step="0.1" min="0" max="100">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="table-light">
                                            <td colspan="3" class="text-end"><h5 class="mb-0">Total Amount:</h5></td>
                                            <td colspan="2">
                                                <h5 class="mb-0 text-primary" id="totalAmountDisplay">$0.00</h5>
                                                <input type="hidden" name="total_amount" id="totalAmount" value="0">
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Notes -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-sticky-note me-2 text-primary"></i>Order Notes
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Customer Notes</label>
                                <textarea class="form-control" name="customer_notes" rows="3" placeholder="Any notes from the customer..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Internal Notes</label>
                                <textarea class="form-control" name="internal_notes" rows="3" placeholder="Internal notes for this order..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Shipping & Payment -->
                <div class="col-lg-4">
                    <!-- Shipping Information -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-truck me-2 text-primary"></i>Shipping Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Shipping Address</label>
                                <textarea class="form-control" name="shipping_address" id="shippingAddress" rows="3" required placeholder="Enter shipping address..."></textarea>
                                <div class="form-text">
                                    <a href="#" onclick="copyBillingToShipping()">Copy from customer address</a>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Billing Address</label>
                                <textarea class="form-control" name="billing_address" id="billingAddress" rows="3" placeholder="Enter billing address (optional)..."></textarea>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="sameAsShipping" onclick="toggleBillingAddress()">
                                    <label class="form-check-label" for="sameAsShipping">
                                        Same as shipping address
                                    </label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Shipping Method</label>
                                    <select class="form-select" name="shipping_method" required>
                                        <option value="standard">Standard Shipping</option>
                                        <option value="express">Express Shipping</option>
                                        <option value="overnight">Overnight Delivery</option>
                                        <option value="pickup">Store Pickup</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Shipping Carrier</label>
                                    <select class="form-select" name="shipping_carrier_id">
                                        <option value="">Select Carrier</option>
                                        <?php foreach($shipping_carriers as $carrier): ?>
                                        <option value="<?php echo $carrier['id']; ?>"><?php echo htmlspecialchars($carrier['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Tracking Number</label>
                                <input type="text" class="form-control" name="tracking_number" placeholder="Tracking number (if available)">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2 text-primary"></i>Payment Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method" required>
                                    <option value="cod">Cash on Delivery</option>
                                    <option value="card">Credit/Debit Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="paypal">PayPal</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="completed">Completed</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Order Status <span class="text-danger">*</span></label>
                                <select class="form-select" name="order_status" required>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Order Priority</label>
                                <select class="form-select" name="order_priority">
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_gift" id="isGift">
                                <label class="form-check-label" for="isGift">
                                    This is a gift order
                                </label>
                            </div>
                            <div id="giftMessageSection" style="display: none;">
                                <label class="form-label">Gift Message</label>
                                <textarea class="form-control" name="gift_message" rows="2" placeholder="Gift message..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Summary & Actions -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="mb-3">Order Summary</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span id="summarySubtotal">$0.00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Shipping:</span>
                                <span id="summaryShipping">$5.99</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tax (<span id="summaryTaxRate">10</span>%):</span>
                                <span id="summaryTax">$0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3">
                                <strong>Total:</strong>
                                <strong class="text-primary" id="summaryTotal">$0.00</strong>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check me-2"></i> Create Order
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="previewOrder()">
                                    <i class="fas fa-eye me-2"></i> Preview Order
                                </button>
                            </div>
                            
                            <div class="alert alert-info mt-3 small">
                                <i class="fas fa-info-circle me-2"></i>
                                Order will be created immediately with the selected details.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
</div>

<!-- Product Row Template (Hidden) -->
<template id="productRowTemplate">
    <tr class="product-row">
        <td>
            <select class="form-select product-select" name="products[]" onchange="updateProductDetails(this)" required>
                <option value="">Select Product</option>
                <?php foreach($products as $product): ?>
                <option value="<?php echo $product['id']; ?>"
                        data-price="<?php echo $product['price']; ?>"
                        data-stock="<?php echo $product['stock']; ?>"
                        data-name="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php echo htmlspecialchars($product['name']); ?> - $<?php echo number_format($product['price'], 2); ?> (Stock: <?php echo $product['stock']; ?>)
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
            <small class="text-muted stock-info"></small>
        </td>
        <td>
            <input type="text" class="form-control product-subtotal" name="subtotals[]" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeProductRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</template>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    
    // Add to table
    const productsBody = document.getElementById('productsBody');
    const noProductsRow = document.getElementById('noProductsRow');
    
    if (noProductsRow) {
        noProductsRow.remove();
    }
    
    productsBody.appendChild(row);
    
    // Update calculations
    updateOrderSummary();
}

// Remove product row
function removeProductRow(button) {
    const row = button.closest('.product-row');
    row.remove();
    
    // Show no products message if empty
    const productsBody = document.getElementById('productsBody');
    if (productsBody.children.length === 0) {
        productsBody.innerHTML = `
            <tr id="noProductsRow">
                <td colspan="5" class="text-center py-4">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No products added yet</p>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProductRow()">
                        <i class="fas fa-plus me-1"></i> Add First Product
                    </button>
                </td>
            </tr>
        `;
    }
    
    updateOrderSummary();
}

// Update product details when selected
function updateProductDetails(select) {
    const row = select.closest('.product-row');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        const price = selectedOption.getAttribute('data-price');
        const stock = selectedOption.getAttribute('data-stock');
        const name = selectedOption.getAttribute('data-name');
        
        // Update price
        row.querySelector('.product-price').value = '$' + parseFloat(price).toFixed(2);
        
        // Update stock info
        const stockInfo = row.querySelector('.stock-info');
        stockInfo.textContent = 'Stock: ' + stock;
        if (stock < 5) {
            stockInfo.className = 'text-danger small';
        } else {
            stockInfo.className = 'text-muted small';
        }
        
        // Set max quantity
        const quantityInput = row.querySelector('.product-quantity');
        quantityInput.max = stock;
        
        // Update subtotal
        updateSubtotal(quantityInput);
    }
}

// Update subtotal for a product
function updateSubtotal(input) {
    const row = input.closest('.product-row');
    const priceInput = row.querySelector('.product-price');
    const subtotalInput = row.querySelector('.product-subtotal');
    
    const price = parseFloat(priceInput.value.replace('$', '')) || 0;
    const quantity = parseInt(input.value) || 0;
    const subtotal = price * quantity;
    
    subtotalInput.value = '$' + subtotal.toFixed(2);
    
    updateOrderSummary();
}

// Update order summary
function updateOrderSummary() {
    let subtotal = 0;
    
    // Calculate subtotal from all products
    document.querySelectorAll('.product-subtotal').forEach(input => {
        const value = parseFloat(input.value.replace('$', '')) || 0;
        subtotal += value;
    });
    
    // Get shipping and tax
    const shippingCost = parseFloat(document.getElementById('shippingCost').value) || 0;
    const taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
    
    // Calculate tax and total
    const taxAmount = (subtotal * taxRate) / 100;
    const totalAmount = subtotal + shippingCost + taxAmount;
    
    // Update hidden fields
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    document.getElementById('totalAmount').value = totalAmount.toFixed(2);
    
    // Update display
    document.getElementById('subtotalDisplay').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('totalAmountDisplay').textContent = '$' + totalAmount.toFixed(2);
    
    // Update summary card
    document.getElementById('summarySubtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('summaryShipping').textContent = '$' + shippingCost.toFixed(2);
    document.getElementById('summaryTaxRate').textContent = taxRate;
    document.getElementById('summaryTax').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('summaryTotal').textContent = '$' + totalAmount.toFixed(2);
}

// Customer selection
document.getElementById('customerSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const customerDetails = document.getElementById('customerDetails');
    
    if (selectedOption.value) {
        const email = selectedOption.getAttribute('data-email');
        const phone = selectedOption.getAttribute('data-phone');
        const address = selectedOption.getAttribute('data-address');
        
        document.getElementById('customerEmail').value = email;
        document.getElementById('customerPhone').value = phone;
        document.getElementById('customerAddress').value = address;
        document.getElementById('shippingAddress').value = address;
        
        customerDetails.style.display = 'block';
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
    } else {
        billingAddress.readOnly = false;
    }
}

// Copy billing to shipping
function copyBillingToShipping() {
    const shippingAddress = document.getElementById('shippingAddress');
    const customerAddress = document.getElementById('customerAddress').value;
    
    if (customerAddress) {
        shippingAddress.value = customerAddress;
    }
}

// Show new customer form
function showNewCustomerForm() {
    const newCustomerForm = document.getElementById('newCustomerForm');
    const customerSelect = document.getElementById('customerSelect');
    
    newCustomerForm.style.display = 'block';
    customerSelect.value = '';
    document.getElementById('customerDetails').style.display = 'none';
}

// Gift checkbox
document.getElementById('isGift').addEventListener('change', function() {
    const giftMessageSection = document.getElementById('giftMessageSection');
    giftMessageSection.style.display = this.checked ? 'block' : 'none';
});

// Preview order
function previewOrder() {
    // Validate form
    const form = document.getElementById('createOrderForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    // Collect data
    const formData = new FormData(form);
    const data = {};
    for (let [key, value] of formData.entries()) {
        if (key.includes('[]')) {
            const baseKey = key.replace('[]', '');
            if (!data[baseKey]) data[baseKey] = [];
            data[baseKey].push(value);
        } else {
            data[key] = value;
        }
    }
    
    // Show preview
    Swal.fire({
        title: 'Order Preview',
        html: `
            <div class="text-start">
                <p><strong>Customer:</strong> ${document.getElementById('customerSelect').selectedOptions[0]?.text || 'New Customer'}</p>
                <p><strong>Items:</strong> ${document.querySelectorAll('.product-row').length} product(s)</p>
                <p><strong>Subtotal:</strong> ${document.getElementById('summarySubtotal').textContent}</p>
                <p><strong>Shipping:</strong> ${document.getElementById('summaryShipping').textContent}</p>
                <p><strong>Tax:</strong> ${document.getElementById('summaryTax').textContent}</p>
                <p><strong>Total:</strong> ${document.getElementById('summaryTotal').textContent}</p>
                <hr>
                <p><strong>Status:</strong> ${data.order_status}</p>
                <p><strong>Payment:</strong> ${data.payment_method} (${data.payment_status})</p>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Create Order',
        cancelButtonText: 'Edit Order'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}

// Form submission
document.getElementById('createOrderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate at least one product
    const productRows = document.querySelectorAll('.product-row');
    if (productRows.length === 0) {
        Swal.fire('Error!', 'Please add at least one product to the order.', 'error');
        return;
    }
    
    // Validate product selection
    let hasEmptyProducts = false;
    document.querySelectorAll('.product-select').forEach(select => {
        if (!select.value) hasEmptyProducts = true;
    });
    
    if (hasEmptyProducts) {
        Swal.fire('Error!', 'Please select a product for all rows.', 'error');
        return;
    }
    
    // Submit via AJAX
    const formData = new FormData(this);
    
    fetch(this.action, {
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
                confirmButtonText: 'View Order'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'order-details.php?id=' + data.order_id;
                } else {
                    window.location.href = 'orders.php';
                }
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred while creating the order.', 'error');
    });
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Add first product row
    addProductRow();
    
    // Watch for changes to update summary
    document.getElementById('shippingCost').addEventListener('input', updateOrderSummary);
    document.getElementById('taxRate').addEventListener('input', updateOrderSummary);
});
</script>

<?php require_once '../includes/footer.php'; ?>