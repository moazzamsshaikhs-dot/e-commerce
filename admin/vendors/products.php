<?php
// admin/vendors/products.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Vendor Products';
require_once '../includes/header.php';

$db = getDB();
$vendor_id = isset($_GET['vendor']) ? (int)$_GET['vendor'] : 0;

// Get vendor details
$vendor = null;
if ($vendor_id) {
    try {
        $stmt = $db->prepare("SELECT id, username, full_name, email FROM users WHERE id = ? AND user_type = 'vendor'");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        // Ignore
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT p.*, 
           c.name as category_name,
           u.full_name as vendor_name,
           u.username as vendor_username
    FROM products p
    LEFT JOIN categories c ON p.category = c.slug
    LEFT JOIN users u ON p.vendor_id = u.id
    WHERE 1=1
";

$params = [];

if ($vendor_id) {
    $query .= " AND p.vendor_id = ?";
    $params[] = $vendor_id;
}

if ($filter === 'pending') {
    $query .= " AND p.approved_status = 'pending'";
} elseif ($filter === 'approved') {
    $query .= " AND p.approved_status = 'approved'";
} elseif ($filter === 'rejected') {
    $query .= " AND p.approved_status = 'rejected'";
} elseif ($filter === 'featured') {
    $query .= " AND p.is_featured = 1";
} elseif ($filter === 'low-stock') {
    $query .= " AND p.stock > 0 AND p.stock < 10";
} elseif ($filter === 'out-of-stock') {
    $query .= " AND p.stock = 0";
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
try {
    if ($vendor_id) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'pending'");
        $stmt->execute([$vendor_id]);
        $stats['pending'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'approved'");
        $stmt->execute([$vendor_id]);
        $stats['approved'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND stock = 0");
        $stmt->execute([$vendor_id]);
        $stats['out_of_stock'] = $stmt->fetchColumn();
    } else {
        $stmt = $db->query("SELECT COUNT(*) FROM products WHERE approved_status = 'pending'");
        $stats['pending'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) FROM products");
        $stats['total'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) FROM products WHERE stock = 0");
        $stats['out_of_stock'] = $stmt->fetchColumn();
    }
} catch(Exception $e) {
    $stats = ['pending' => 0, 'approved' => 0, 'total' => 0, 'out_of_stock' => 0];
}
?>

<style>
:root {
    --primary: #4361ee;
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --dark: #2b2d42;
    --light: #f8f9fa;
}

.products-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-card.pending { border-left-color: var(--warning); }
.stat-card.approved { border-left-color: var(--success); }
.stat-card.total { border-left-color: var(--primary); }
.stat-card.outstock { border-left-color: var(--danger); }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 14px;
}

/* Filter Tabs */
.filter-tabs {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 500;
    color: #6c757d;
    background: var(--light);
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.filter-tab:hover {
    background: var(--primary);
    color: white;
}

.filter-tab.active {
    background: var(--primary);
    color: white;
}

.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    margin-left: 8px;
    font-size: 12px;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

/* Search Box */
.search-box {
    background: white;
    border-radius: 15px;
    padding: 5px;
    display: flex;
    align-items: center;
    border: 1px solid #edf2f9;
    min-width: 300px;
}

.search-box input {
    border: none;
    padding: 10px 15px;
    flex: 1;
    border-radius: 12px;
}

.search-box input:focus {
    outline: none;
}

.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.search-box button:hover {
    background: #3651c4;
}

/* Products Table */
.products-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.table-header {
    padding: 20px 25px;
    border-bottom: 1px solid #edf2f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.table-responsive {
    padding: 0 25px 25px 25px;
}

.table {
    margin-bottom: 0;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--dark);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px 10px;
    border-bottom: 2px solid #edf2f9;
}

.table td {
    padding: 15px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #edf2f9;
}

.table tbody tr:hover {
    background: #f8f9fa;
}

/* Product Image */
.product-img {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
    background: var(--light);
}

/* Status Badges */
.badge {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
}

.badge-pending { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.badge-approved { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.badge-rejected { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
.badge-featured { background: rgba(67, 97, 238, 0.1); color: var(--primary); }

/* Action Buttons */
.btn-action {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-approve {
    background: var(--success);
    color: white;
}

.btn-reject {
    background: var(--danger);
    color: white;
}

.btn-view {
    background: var(--primary);
    color: white;
}

.btn-action:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

/* Stock indicators */
.stock-high { color: var(--success); }
.stock-low { color: var(--warning); font-weight: 600; }
.stock-out { color: var(--danger); font-weight: 600; }

/* Responsive */
@media (max-width: 768px) {
    .products-container {
        padding: 20px;
    }
    
    .table-responsive {
        padding: 0 15px 15px 15px;
    }
    
    .search-box {
        min-width: 100%;
    }
}
</style>

<div class="products-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-box me-2 text-primary"></i>
                    <?php if ($vendor): ?>
                        Products by <?php echo htmlspecialchars($vendor['full_name'] ?? $vendor['username']); ?>
                    <?php else: ?>
                        All Vendor Products
                    <?php endif; ?>
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-boxes me-2"></i>
                    Total <?php echo count($products); ?> products found
                    <?php if ($vendor): ?>
                        <a href="view-vendor.php?id=<?php echo $vendor_id; ?>" class="ms-3 text-primary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Vendor
                        </a>
                    <?php endif; ?>
                </p>
            </div>
            <?php if (!$vendor): ?>
                <a href="../products/add-product.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i> Add New Product
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <?php if ($vendor): ?>
            <div class="stat-card pending">
                <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card approved">
                <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                    <i class="fas fa-check-circle text-success"></i>
                </div>
                <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card outstock">
                <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                    <i class="fas fa-exclamation-circle text-danger"></i>
                </div>
                <div class="stat-value"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        <?php else: ?>
            <div class="stat-card total">
                <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                    <i class="fas fa-box text-primary"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                    <i class="fas fa-clock text-warning"></i>
                </div>
                <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card outstock">
                <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                    <i class="fas fa-exclamation-circle text-danger"></i>
                </div>
                <div class="stat-value"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filters and Search -->
    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
        <div class="filter-tabs">
            <a href="products.php<?php echo $vendor ? '?vendor=' . $vendor_id : ''; ?>" 
               class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list me-1"></i> All
            </a>
            <a href="products.php?filter=pending<?php echo $vendor ? '&vendor=' . $vendor_id : ''; ?>" 
               class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock me-1"></i> Pending
                <span class="count"><?php echo $stats['pending'] ?? 0; ?></span>
            </a>
            <a href="products.php?filter=approved<?php echo $vendor ? '&vendor=' . $vendor_id : ''; ?>" 
               class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle me-1"></i> Approved
            </a>
            <a href="products.php?filter=featured<?php echo $vendor ? '&vendor=' . $vendor_id : ''; ?>" 
               class="filter-tab <?php echo $filter === 'featured' ? 'active' : ''; ?>">
                <i class="fas fa-star me-1"></i> Featured
            </a>
            <a href="products.php?filter=low-stock<?php echo $vendor ? '&vendor=' . $vendor_id : ''; ?>" 
               class="filter-tab <?php echo $filter === 'low-stock' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle me-1"></i> Low Stock
            </a>
            <a href="products.php?filter=out-of-stock<?php echo $vendor ? '&vendor=' . $vendor_id : ''; ?>" 
               class="filter-tab <?php echo $filter === 'out-of-stock' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle me-1"></i> Out of Stock
            </a>
        </div>

        <form method="GET" class="search-box">
            <?php if ($vendor): ?>
                <input type="hidden" name="vendor" value="<?php echo $vendor_id; ?>">
            <?php endif; ?>
            <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">
                <i class="fas fa-search me-1"></i> Search
            </button>
        </form>
    </div>

    <!-- Products Table -->
    <div class="products-card">
        <div class="table-header">
            <h5>
                <i class="fas fa-boxes me-2 text-primary"></i>
                Products List
            </h5>
            <span class="text-muted">Showing <?php echo count($products); ?> products</span>
        </div>
        
        <div class="table-responsive">
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No products found</h5>
                    <p class="text-muted">Try adjusting your filters or search terms</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product</th>
                            <?php if (!$vendor): ?>
                                <th>Vendor</th>
                            <?php endif; ?>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <img src="<?php echo !empty($product['image']) ? '../../assets/images/products/' . $product['image'] : '../../assets/images/no-image.png'; ?>" 
                                     class="product-img" alt="<?php echo $product['name']; ?>">
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars(substr($product['name'], 0, 40)) . (strlen($product['name']) > 40 ? '...' : ''); ?></strong>
                                <br>
                                <small class="text-muted">ID: #<?php echo $product['id']; ?></small>
                            </td>
                            <?php if (!$vendor): ?>
                                <td>
                                    <a href="view-vendor.php?id=<?php echo $product['vendor_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($product['vendor_name'] ?? $product['vendor_username']); ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($product['category_name'] ?? $product['category']); ?></td>
                            <td>
                                <strong>$<?php echo number_format($product['price'], 2); ?></strong>
                                <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                                    <br><small class="text-muted text-decoration-line-through">$<?php echo number_format($product['old_price'], 2); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product['stock'] <= 0): ?>
                                    <span class="stock-out">Out of Stock</span>
                                <?php elseif ($product['stock'] < 10): ?>
                                    <span class="stock-low"><?php echo $product['stock']; ?> left</span>
                                <?php else: ?>
                                    <span class="stock-high"><?php echo $product['stock']; ?> in stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product['approved_status'] == 'pending'): ?>
                                    <span class="badge badge-pending">Pending</span>
                                <?php elseif ($product['approved_status'] == 'approved'): ?>
                                    <span class="badge badge-approved">Approved</span>
                                <?php elseif ($product['approved_status'] == 'rejected'): ?>
                                    <span class="badge badge-rejected">Rejected</span>
                                <?php endif; ?>
                                
                                <?php if ($product['is_featured']): ?>
                                    <span class="badge badge-featured mt-1">Featured</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if ($product['approved_status'] == 'pending'): ?>
                                        <a href="action/approve-product.php?id=<?php echo $product['id']; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn-action btn-approve me-1" onclick="approveProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-check"></i>
                                        </a>
                                        <button class="btn-action btn-reject me-1" onclick="rejectProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="../products/view-product.php?id=<?php echo $product['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="action/approve-product.php">
                <input type="hidden" name="product_id" id="approve_product_id">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Approve Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this product?</p>
                    <p class="text-muted small">The product will be visible to customers immediately.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="action/reject-product.php">
                <input type="hidden" name="product_id" id="reject_product_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>
                        Reject Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject this product?</p>
                    <div class="mb-3">
                        <label class="form-label">Reason for rejection</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveProduct(id) {
    document.getElementById('approve_product_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectProduct(id) {
    document.getElementById('reject_product_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>