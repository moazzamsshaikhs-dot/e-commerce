<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
} else if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Please log in to access the vendor dashboard.';
    redirect(SITE_URL . 'login.php');
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect(SITE_URL . 'admin/vendors/dashboard.php');
}

$vendor_id = $_SESSION['user_id'];

// Get low stock and out of stock products
try {
    // Get low stock products (1-9)
    $stmt = $db->prepare("
        SELECT 
            p.*,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.id) as review_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ? 
        AND p.stock > 0 
        AND p.stock < 10
        GROUP BY p.id
        ORDER BY p.stock ASC, p.name
    ");
    $stmt->execute([$vendor_id]);
    $low_stock = $stmt->fetchAll();
    
    // Get out of stock products
    $stmt = $db->prepare("
        SELECT 
            p.*,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.id) as review_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN reviews r ON p.id = r.product_id
        WHERE p.vendor_id = ? 
        AND p.stock = 0
        GROUP BY p.id
        ORDER BY p.name
    ");
    $stmt->execute([$vendor_id]);
    $out_of_stock = $stmt->fetchAll();
    
    // Get inventory summary
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count,
            SUM(CASE WHEN stock > 0 AND stock < 10 THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN stock >= 10 THEN 1 ELSE 0 END) as in_stock_count
        FROM products
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $summary = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading low stock data: ' . $e->getMessage();
    $low_stock = [];
    $out_of_stock = [];
    $summary = [
        'total_products' => 0,
        'out_of_stock_count' => 0,
        'low_stock_count' => 0,
        'in_stock_count' => 0
    ];
}

// Log activity
logUserActivity($_SESSION['user_id'], 'low_stock_view', 'Viewed low stock products');

$page_title = 'Low Stock Products - Vendor Dashboard';
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Low Stock Products</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-exclamation-triangle me-1 text-warning"></i>
                        Monitor and manage products with low inventory
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="inventory.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Inventory
                    </a>
                    <a href="../products/add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add Product
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-warning mb-2">
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo $summary['low_stock_count']; ?></h3>
                        <p class="text-muted mb-0">Low Stock Products</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-danger mb-2">
                            <i class="fas fa-times-circle fa-2x"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo $summary['out_of_stock_count']; ?></h3>
                        <p class="text-muted mb-0">Out of Stock</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-success mb-2">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo $summary['in_stock_count']; ?></h3>
                        <p class="text-muted mb-0">In Stock</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="text-primary mb-2">
                            <i class="fas fa-boxes fa-2x"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo $summary['total_products']; ?></h3>
                        <p class="text-muted mb-0">Total Products</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Low Stock Products -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    Low Stock Products (1-9 units)
                    <span class="badge bg-warning ms-2"><?php echo count($low_stock); ?> items</span>
                </h5>
                <button class="btn btn-sm btn-outline-warning" onclick="restockAllLow()">
                    <i class="fas fa-sync-alt me-1"></i> Restock All
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($low_stock)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-muted">No Low Stock Products</h5>
                        <p class="text-muted">All products have sufficient stock levels</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Current Stock</th>
                                    <th>Price</th>
                                    <th>Sales</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($low_stock as $product): 
                                    $stock_percentage = ($product['stock'] / 10) * 100;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-3">
                                                <?php if (!empty($product['image'])): ?>
                                                    <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $product['image']; ?>" 
                                                         class="rounded" width="40" height="40">
                                                <?php else: ?>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-box text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <small class="text-muted">ID: #<?php echo $product['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-3" style="height: 8px; width: 80px;">
                                                <div class="progress-bar bg-warning" style="width: <?php echo $stock_percentage; ?>%"></div>
                                            </div>
                                            <div class="fw-bold"><?php echo $product['stock']; ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold">$<?php echo number_format($product['price'], 2); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo $product['total_sold']; ?></div>
                                        <?php if ($product['avg_rating'] > 0): ?>
                                            <small class="text-warning">
                                                <i class="fas fa-star"></i> <?php echo number_format($product['avg_rating'], 1); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">Low Stock</span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../products/edit.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-warning" 
                                                    onclick="quickRestock(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['stock']; ?>)"
                                                    title="Restock">
                                                <i class="fas fa-box"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Out of Stock Products -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-times-circle me-2 text-danger"></i>
                    Out of Stock Products
                    <span class="badge bg-danger ms-2"><?php echo count($out_of_stock); ?> items</span>
                </h5>
                <button class="btn btn-sm btn-outline-danger" onclick="restockAllOut()">
                    <i class="fas fa-sync-alt me-1"></i> Restock All
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($out_of_stock)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <h5 class="text-muted">No Out of Stock Products</h5>
                        <p class="text-muted">Great! All products are in stock</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Last Sold</th>
                                    <th>Price</th>
                                    <th>Total Sales</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($out_of_stock as $product): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-3">
                                                <?php if (!empty($product['image'])): ?>
                                                    <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $product['image']; ?>" 
                                                         class="rounded" width="40" height="40">
                                                <?php else: ?>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-box text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <small class="text-muted">ID: #<?php echo $product['id']; ?></small>
                                                <?php if ($product['featured']): ?>
                                                    <span class="badge bg-info ms-2">Featured</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted">-</span>
                                    </td>
                                    <td>
                                        <div class="fw-bold">$<?php echo number_format($product['price'], 2); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo $product['total_sold']; ?> sold</div>
                                        <?php if ($product['avg_rating'] > 0): ?>
                                            <small class="text-warning">
                                                <i class="fas fa-star"></i> <?php echo number_format($product['avg_rating'], 1); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../products/edit.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="quickRestock(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', 0)"
                                                    title="Restock">
                                                <i class="fas fa-box"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" 
                                                    onclick="toggleProductStatus(<?php echo $product['id']; ?>, 'hide')"
                                                    title="Hide from store">
                                                <i class="fas fa-eye-slash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Restock Modal -->
        <div class="modal fade" id="restockModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Restock Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="restockForm">
                        <div class="modal-body">
                            <input type="hidden" id="restockProductId">
                            <div class="mb-3">
                                <label class="form-label">Product</label>
                                <input type="text" id="restockProductName" class="form-control" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Current Stock</label>
                                <input type="number" id="restockCurrentStock" class="form-control" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Stock Quantity</label>
                                <input type="number" id="restockNewStock" class="form-control" min="1" value="20" required>
                                <small class="text-muted">Recommended: 20-50 units for popular products</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Restock Notes (Optional)</label>
                                <textarea id="restockNotes" class="form-control" rows="2" 
                                          placeholder="e.g., New shipment arrived, Seasonal restock, etc."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Update Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
function quickRestock(productId, productName, currentStock) {
    document.getElementById('restockProductId').value = productId;
    document.getElementById('restockProductName').value = productName;
    document.getElementById('restockCurrentStock').value = currentStock;
    document.getElementById('restockNewStock').value = Math.max(20, currentStock * 2);
    
    const modal = new bootstrap.Modal(document.getElementById('restockModal'));
    modal.show();
}

function restockAllLow() {
    if (confirm('Restock all low stock products to 20 units?')) {
        const lowStockIds = <?php echo json_encode(array_column($low_stock, 'id')); ?>;
        if (lowStockIds.length > 0) {
            bulkRestock(lowStockIds, 20);
        }
    }
}

function restockAllOut() {
    if (confirm('Restock all out of stock products to 30 units?')) {
        const outStockIds = <?php echo json_encode(array_column($out_of_stock, 'id')); ?>;
        if (outStockIds.length > 0) {
            bulkRestock(outStockIds, 30);
        }
    }
}

function bulkRestock(productIds, stockQuantity) {
    fetch('bulk-restock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            product_ids: JSON.stringify(productIds),
            stock_quantity: stockQuantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating stock');
    });
}

function toggleProductStatus(productId, action) {
    if (confirm(action === 'hide' ? 'Hide this product from the store?' : 'Show this product?')) {
        fetch('toggle-product-status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                product_id: productId,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

// Handle restock form submission
document.getElementById('restockForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const productId = document.getElementById('restockProductId').value;
    const newStock = document.getElementById('restockNewStock').value;
    const notes = document.getElementById('restockNotes').value;
    
    fetch('update-stock.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            product_id: productId,
            new_stock: newStock,
            reason: 'Restock: ' + notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Stock updated successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating stock');
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>