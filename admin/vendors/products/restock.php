<?php
// admin/vendors/products/restock.php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Define SITE_URL if not defined


// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$suggested_qty = isset($_GET['quantity']) ? intval($_GET['quantity']) : 10;

// Validate product ID
if ($product_id <= 0) {
    $_SESSION['error'] = 'Invalid product ID';
    header('Location: low-stock.php');
    exit();
}

// Get product details first
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error'] = 'Product not found or access denied';
        header('Location: low-stock.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    error_log("Restock product fetch error: " . $e->getMessage());
    header('Location: low-stock.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    
    // Validate quantity
    if ($quantity <= 0) {
        $_SESSION['error'] = 'Please enter a valid quantity greater than 0';
    } elseif ($quantity > 10000) {
        $_SESSION['error'] = 'Maximum restock quantity is 10,000 units';
    } else {
        try {
            $db = getDB();
            
            // Begin transaction
            $db->beginTransaction();
            
            // Update stock
            $stmt = $db->prepare("
                UPDATE products 
                SET stock = stock + ?, 
                    low_stock = CASE 
                        WHEN (stock + ?) > 0 AND (stock + ?) < 10 THEN 1 
                        ELSE 0 
                    END,
                    out_of_stock = CASE 
                        WHEN (stock + ?) = 0 THEN 1 
                        ELSE 0 
                    END,
                    updated_at = NOW() 
                WHERE id = ? AND vendor_id = ?
            ");
            $stmt->execute([$quantity, $quantity, $quantity, $quantity, $product_id, $vendor_id]);
            
            // Check if update was successful
            if ($stmt->rowCount() == 0) {
                throw new Exception('No product was updated. Product may not exist or access denied.');
            }
            
            // Log activity - check if vendor_activities table exists
            try {
                $stmt = $db->prepare("
                    INSERT INTO vendor_activities (vendor_id, activity_type, description, created_at)
                    VALUES (?, 'restock', ?, NOW())
                ");
                $description = "Restocked product ID: $product_id with $quantity units. New stock: " . ($product['stock'] + $quantity);
                $stmt->execute([$vendor_id, $description]);
            } catch(PDOException $e) {
                // If vendor_activities doesn't exist, just log to error log
                error_log("Vendor activity logging failed: " . $e->getMessage());
            }
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['success'] = "Successfully added $quantity units to inventory. New stock: " . ($product['stock'] + $quantity) . " units";
            
            // Redirect based on referrer
            if (isset($_POST['return_to']) && $_POST['return_to'] === 'product') {
                header('Location: edit.php?id=' . $product_id);
            } else {
                header('Location: low-stock.php');
            }
            exit();
            
        } catch(Exception $e) {
            // Rollback transaction on error
            if (isset($db)) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Error updating stock: ' . $e->getMessage();
            error_log("Restock error: " . $e->getMessage());
        }
    }
}

$page_title = 'Restock Product';
require_once '../../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Main Card -->
            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3">
                            <i class="fas fa-truck text-success fa-2x"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold">Restock Product</h5>
                            <p class="text-muted mb-0 small">Add inventory to your product</p>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Product Info -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <?php if (!empty($product['image'])): ?>
                                <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                     class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     style="max-height: 150px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                     style="height: 150px;">
                                    <i class="fas fa-image fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h6 class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></h6>
                            <p class="text-muted small mb-2">
                                <?php echo substr(htmlspecialchars($product['description']), 0, 100); ?>...
                            </p>
                            
                            <div class="row g-2 mt-3">
                                <div class="col-6">
                                    <div class="bg-light p-2 rounded text-center">
                                        <small class="text-muted d-block">Current Stock</small>
                                        <h4 class="mb-0 <?php echo $product['stock'] < 5 ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo $product['stock']; ?>
                                        </h4>
                                        <small>units</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-light p-2 rounded text-center">
                                        <small class="text-muted d-block">Price</small>
                                        <h4 class="mb-0 text-primary">$<?php echo number_format($product['price'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock Status Alert -->
                    <?php if ($product['stock'] == 0): ?>
                        <div class="alert alert-danger mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Out of Stock!</strong> This product is currently out of stock and needs immediate restock.
                        </div>
                    <?php elseif ($product['stock'] < 5): ?>
                        <div class="alert alert-warning mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Critical Stock Level!</strong> Only <?php echo $product['stock']; ?> units left. Restock soon.
                        </div>
                    <?php elseif ($product['stock'] < 10): ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Low Stock Warning.</strong> <?php echo $product['stock']; ?> units remaining.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Restock Form -->
                    <form method="POST" id="restockForm">
                        <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                        <input type="hidden" name="return_to" value="<?php echo isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : 'low-stock'; ?>">
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-plus-circle me-2 text-success"></i>
                                Quantity to Add
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light">Units</span>
                                <input type="number" 
                                       class="form-control form-control-lg" 
                                       name="quantity" 
                                       id="quantity"
                                       value="<?php echo $suggested_qty; ?>" 
                                       min="1" 
                                       max="10000" 
                                       step="1"
                                       required>
                                <button class="btn btn-outline-secondary" type="button" id="decrementBtn">-</button>
                                <button class="btn btn-outline-secondary" type="button" id="incrementBtn">+</button>
                            </div>
                            <div class="form-text d-flex justify-content-between mt-2">
                                <span><i class="fas fa-info-circle me-1"></i> Min: 1, Max: 10,000 units</span>
                                <span id="newStockPreview" class="text-primary">
                                    New stock: <strong><?php echo $product['stock'] + $suggested_qty; ?></strong> units
                                </span>
                            </div>
                        </div>
                        
                        <!-- Quick Select Buttons -->
                        <div class="mb-4">
                            <label class="form-label small text-muted">Quick Select:</label>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary quick-select" data-qty="5">+5</button>
                                <button type="button" class="btn btn-sm btn-outline-primary quick-select" data-qty="10">+10</button>
                                <button type="button" class="btn btn-sm btn-outline-primary quick-select" data-qty="25">+25</button>
                                <button type="button" class="btn btn-sm btn-outline-primary quick-select" data-qty="50">+50</button>
                                <button type="button" class="btn btn-sm btn-outline-primary quick-select" data-qty="100">+100</button>
                                <?php if ($product['stock'] == 0): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger quick-select" data-qty="<?php echo max(10, $suggested_qty); ?>">Restock Now</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Restock Summary -->
                        <div class="card bg-light border-0 mb-4">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Restock Summary</h6>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Current Stock</small>
                                        <strong id="currentStock"><?php echo $product['stock']; ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Adding</small>
                                        <strong id="addingStock"><?php echo $suggested_qty; ?></strong>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <hr class="my-2">
                                        <div class="d-flex justify-content-between">
                                            <span class="fw-bold">New Stock Total:</span>
                                            <span class="fw-bold text-success" id="newStockTotal">
                                                <?php echo $product['stock'] + $suggested_qty; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?php echo isset($_GET['ref']) && $_GET['ref'] === 'product' ? 'edit.php?id=' . $product_id : 'low-stock.php'; ?>" 
                               class="btn btn-outline-secondary px-4">
                                <i class="fas fa-times me-2"></i>
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-success px-5" id="submitBtn">
                                <i class="fas fa-check-circle me-2"></i>
                                Confirm Restock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Additional Info Card -->
            <div class="card shadow border-0 mt-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-info-circle me-2 text-info"></i>
                        Restock Information
                    </h6>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Bulk Discounts:</strong> Contact admin for orders over 1000 units
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Processing Time:</strong> Standard restock updates instantly
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>Low Stock Alert:</strong> Products with &lt;10 units trigger warnings
                        </li>
                        <li>
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong>History:</strong> All restocks are logged for inventory tracking
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 15px;
    overflow: hidden;
}

.btn-group .btn {
    padding: 0.375rem 0.75rem;
}

#decrementBtn, #incrementBtn {
    font-weight: bold;
    font-size: 1.2rem;
    padding: 0.375rem 1rem;
}

.quick-select {
    min-width: 60px;
}

.quick-select:hover {
    transform: translateY(-2px);
    transition: transform 0.2s;
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quantityInput = document.getElementById('quantity');
    const currentStock = <?php echo $product['stock']; ?>;
    const newStockPreview = document.getElementById('newStockPreview');
    const currentStockSpan = document.getElementById('currentStock');
    const addingStockSpan = document.getElementById('addingStock');
    const newStockTotalSpan = document.getElementById('newStockTotal');
    
    // Update preview function
    function updatePreview() {
        let qty = parseInt(quantityInput.value) || 0;
        
        // Validate range
        if (qty < 1) qty = 1;
        if (qty > 10000) qty = 10000;
        quantityInput.value = qty;
        
        // Update previews
        newStockPreview.innerHTML = `New stock: <strong>${currentStock + qty}</strong> units`;
        if (addingStockSpan) addingStockSpan.textContent = qty;
        if (newStockTotalSpan) newStockTotalSpan.textContent = currentStock + qty;
        
        // Update color based on new stock level
        const newTotal = currentStock + qty;
        if (newTotal < 5) {
            newStockTotalSpan.className = 'fw-bold text-danger';
        } else if (newTotal < 10) {
            newStockTotalSpan.className = 'fw-bold text-warning';
        } else {
            newStockTotalSpan.className = 'fw-bold text-success';
        }
    }
    
    // Increment/Decrement buttons
    document.getElementById('incrementBtn')?.addEventListener('click', function() {
        quantityInput.value = Math.min(10000, (parseInt(quantityInput.value) || 0) + 1);
        updatePreview();
    });
    
    document.getElementById('decrementBtn')?.addEventListener('click', function() {
        quantityInput.value = Math.max(1, (parseInt(quantityInput.value) || 0) - 1);
        updatePreview();
    });
    
    // Quick select buttons
    document.querySelectorAll('.quick-select').forEach(btn => {
        btn.addEventListener('click', function() {
            const qty = parseInt(this.dataset.qty);
            quantityInput.value = qty;
            updatePreview();
        });
    });
    
    // Input validation
    quantityInput.addEventListener('input', updatePreview);
    quantityInput.addEventListener('blur', updatePreview);
    
    // Form submission
    document.getElementById('restockForm')?.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('submitBtn');
        const qty = parseInt(quantityInput.value);
        
        if (qty < 1) {
            e.preventDefault();
            alert('Please enter a valid quantity (minimum 1)');
            return false;
        }
        
        if (qty > 10000) {
            e.preventDefault();
            alert('Maximum restock quantity is 10,000 units');
            return false;
        }
        
        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
        submitBtn.disabled = true;
        
        return true;
    });
    
    // Initial preview
    updatePreview();
});
</script>

<?php require_once '../../../includes/footer.php'; ?>