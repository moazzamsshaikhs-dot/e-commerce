<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirectToDashboard();
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        redirect('../../vendor/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect('../../vendor/dashboard.php');
}

$page_title = 'My Products';
require_once '../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';

// Get vendor products
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Build query
    $query = "SELECT p.*, c.name as category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category = c.slug 
              WHERE p.vendor_id = ?";
    $params = [$vendor_id];
    
    if ($search) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status && in_array($status, ['approved', 'pending', 'rejected'])) {
        $query .= " AND p.approved_status = ?";
        $params[] = $status;
    }
    
    if ($category) {
        $query .= " AND p.category = ?";
        $params[] = $category;
    }
    
    $query .= " ORDER BY p.created_at DESC";
    
    // Get total count
    $stmt = $db->prepare(str_replace('p.*, c.name as category_name', 'COUNT(*) as total', $query));
    $stmt->execute($params);
    $total_products = $stmt->fetch()['total'];
    $total_pages = ceil($total_products / $limit);
    
    // Get products with pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = $db->query("SELECT DISTINCT category FROM products WHERE vendor_id = $vendor_id");
    $categories = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading products: ' . $e->getMessage();
    $products = [];
    $total_products = 0;
    $total_pages = 1;
}

// Delete product
if (isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        
        // Verify product belongs to vendor
        $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$product_id, $vendor_id]);
        
        if ($stmt->rowCount() > 0) {
            // Delete product
            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            
            $_SESSION['success'] = 'Product deleted successfully!';
            
            // Log activity
            logUserActivity($vendor_id, 'product_delete', "Deleted product ID: $product_id");
            
            // Redirect to avoid resubmission
            redirect('products.php');
        } else {
            $_SESSION['error'] = 'Product not found or access denied.';
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error deleting product: ' . $e->getMessage();
    }
}
?>

<div class="dashboard-container">
    <?php include '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">My Products</h1>
                    <p class="text-muted mb-0">Manage your products and inventory</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add New Product
                    </a>
                    <a href="../../vendor/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h2 class="text-primary"><?php echo $total_products; ?></h2>
                        <p class="text-muted mb-0">Total Products</p>
                    </div>
                </div>
            </div>
            <?php 
            // Get status counts
            try {
                $status_counts = [];
                $stmt = $db->prepare("SELECT approved_status, COUNT(*) as count FROM products WHERE vendor_id = ? GROUP BY approved_status");
                $stmt->execute([$vendor_id]);
                $status_data = $stmt->fetchAll();
                
                foreach($status_data as $row) {
                    $status_counts[$row['approved_status']] = $row['count'];
                }
            } catch(Exception $e) {
                $status_counts = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
            }
            ?>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-success">
                    <div class="card-body text-center">
                        <h2 class="text-success"><?php echo $status_counts['approved'] ?? 0; ?></h2>
                        <p class="text-muted mb-0">Approved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-warning">
                    <div class="card-body text-center">
                        <h2 class="text-warning"><?php echo $status_counts['pending'] ?? 0; ?></h2>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-danger">
                    <div class="card-body text-center">
                        <h2 class="text-danger"><?php echo $status_counts['rejected'] ?? 0; ?></h2>
                        <p class="text-muted mb-0">Rejected</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['category']; ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Products Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No products found</h4>
                        <p class="text-muted">Add your first product to start selling</p>
                        <a href="add.php" class="btn btn-primary mt-3">
                            <i class="fas fa-plus me-2"></i> Add Your First Product
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Views</th>
                                    <th>Sales</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $product['image'] ?: 'default.png'; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 class="rounded me-3" width="50" height="50"
                                                 onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo ucfirst($product['category_name'] ?? $product['category']); ?></td>
                                    <td class="fw-bold">$<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php if ($product['stock'] == 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif ($product['stock'] < 10): ?>
                                            <span class="badge bg-warning">Low (<?php echo $product['stock']; ?>)</span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?php echo $product['stock']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_color = 'secondary';
                                        if ($product['approved_status'] == 'approved') $status_color = 'success';
                                        if ($product['approved_status'] == 'pending') $status_color = 'warning';
                                        if ($product['approved_status'] == 'rejected') $status_color = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $status_color; ?>">
                                            <?php echo ucfirst($product['approved_status']); ?>
                                        </span>
                                        <?php if ($product['featured']): ?>
                                            <span class="badge bg-info ms-1">Featured</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $product['views'] ?? 0; ?></td>
                                    <td><?php echo $product['sales_count'] ?? 0; ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $product['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="deleteModal<?php echo $product['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($product['name']); ?></strong>?</p>
                                                        <p class="text-danger">This action cannot be undone.</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <button type="submit" name="delete_product" class="btn btn-danger">
                                                                <i class="fas fa-trash me-2"></i> Delete
                                                            </button>
                                                        </form>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row g-4 mt-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-bullhorn me-2 text-primary"></i> Product Tips
                        </h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Use clear, high-quality product images
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Write detailed descriptions with keywords
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Keep inventory updated regularly
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Set competitive prices based on market
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Respond to customer reviews promptly
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="fas fa-chart-line me-2 text-success"></i> Product Performance
                        </h5>
                        <div class="text-center py-4">
                            <p class="text-muted mb-3">Track your product performance</p>
                            <a href="../reports/sales.php" class="btn btn-outline-success me-2">
                                <i class="fas fa-chart-bar me-1"></i> Sales Report
                            </a>
                            <a href="../reviews/reviews.php" class="btn btn-outline-warning">
                                <i class="fas fa-star me-1"></i> View Reviews
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
/* Custom styles for products page */
.rounded {
    border-radius: 8px !important;
}

.table img {
    object-fit: cover;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.pagination .page-item.active .page-link {
    background-color: #4361ee;
    border-color: #4361ee;
}

.modal-content {
    border-radius: 10px;
    border: none;
}
</style>

<script>
// Auto-close alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>