<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ../../index.php');
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();

    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        header('Location: ../../vendor/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ../../vendor/dashboard.php');
    exit();
}

$page_title = 'My Products';
require_once '../../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Initialize variables
$products = [];
$total_products = 0;
$total_pages = 1;
$categories = [];
$status_counts = [
    'approved' => 0,
    'pending' => 0,
    'rejected' => 0
];

try {
    $db = getDB();

    // Get status counts
    $stmt = $db->prepare("SELECT approved_status, COUNT(*) as count FROM products WHERE vendor_id = ? GROUP BY approved_status");
    $stmt->execute([$vendor_id]);
    $status_data = $stmt->fetchAll();

    foreach ($status_data as $row) {
        $status_counts[$row['approved_status']] = (int)$row['count'];
    }

    // Get total products count for stats
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $total_products_all = (int)$stmt->fetchColumn();

    // Get available categories for this vendor
    $stmt = $db->prepare("SELECT DISTINCT p.category, c.name as category_name 
                         FROM products p 
                         LEFT JOIN categories c ON p.category = c.slug 
                         WHERE p.vendor_id = ? 
                         ORDER BY c.name");
    $stmt->execute([$vendor_id]);
    $categories = $stmt->fetchAll();

    // Build WHERE conditions for filtered products
    $where_conditions = ["p.vendor_id = :vendor_id"];
    $params = [':vendor_id' => $vendor_id];

    if (!empty($search)) {
        $where_conditions[] = "(p.name LIKE :search OR p.description LIKE :search_desc)";
        $params[':search'] = "%$search%";
        $params[':search_desc'] = "%$search%";
    }

    if (!empty($status) && in_array($status, ['approved', 'pending', 'rejected'])) {
        $where_conditions[] = "p.approved_status = :status";
        $params[':status'] = $status;
    }

    if (!empty($category)) {
        $where_conditions[] = "p.category = :category";
        $params[':category'] = $category;
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM products p WHERE $where_clause";
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_products = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);

    // Adjust page if out of bounds
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }

    // Get products with pagination - FIXED LIMIT/OFFSET SYNTAX
    $products_query = "SELECT p.*, c.name as category_name 
                      FROM products p 
                      LEFT JOIN categories c ON p.category = c.slug 
                      WHERE $where_clause 
                      ORDER BY p.created_at DESC 
                      LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($products_query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading products: ' . $e->getMessage();
    error_log("Products Page Error - Vendor ID $vendor_id: " . $e->getMessage() . " | Query: " . ($products_query ?? 'N/A'));
    $products = [];
    $total_products = 0;
    $total_pages = 1;
}

// Debug information (remove in production)
if (isset($_GET['debug'])) {
    echo "<!-- Debug Info: Vendor ID: $vendor_id, Total Products: $total_products -->";
    echo "<!-- SQL Query: " . htmlspecialchars($products_query ?? 'N/A') . " -->";
    echo "<!-- SQL Params: " . print_r($params ?? [], true) . " -->";
}

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $db = getDB();

        // Verify product belongs to vendor
        $stmt = $db->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$product_id, $vendor_id]);

        if ($stmt->rowCount() > 0) {
            // Get product name for logging
            $stmt = $db->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product_name = $stmt->fetchColumn();

            // Delete product
            $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);

            $_SESSION['success'] = "Product '$product_name' deleted successfully!";

            // Log activity
            logUserActivity($vendor_id, 'product_delete', "Deleted product: $product_name (ID: $product_id)");

            // Redirect to avoid resubmission
            header("Location: products.php");
            exit();
        } else {
            $_SESSION['error'] = 'Product not found or access denied.';
        }
    } catch (PDOException $e) {
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
                    <a href="<?php echo SITE_URL ?>admin/vendors/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center py-4">
                        <h2 class="text-primary mb-2"><?php echo $total_products_all; ?></h2>
                        <p class="text-muted mb-0">Total Products</p>
                        <small class="text-muted">All statuses combined</small>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 border-start border-5 border-success">
                    <div class="card-body text-center py-4">
                        <h2 class="text-success mb-2"><?php echo $status_counts['approved']; ?></h2>
                        <p class="text-muted mb-0">Approved</p>
                        <small class="text-success">
                            <i class="fas fa-check-circle me-1"></i> Live on store
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 border-start border-5 border-warning">
                    <div class="card-body text-center py-4">
                        <h2 class="text-warning mb-2"><?php echo $status_counts['pending']; ?></h2>
                        <p class="text-muted mb-0">Pending</p>
                        <small class="text-warning">
                            <i class="fas fa-clock me-1"></i> Awaiting approval
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100 border-start border-5 border-danger">
                    <div class="card-body text-center py-4">
                        <h2 class="text-danger mb-2"><?php echo $status_counts['rejected']; ?></h2>
                        <p class="text-muted mb-0">Rejected</p>
                        <small class="text-danger">
                            <i class="fas fa-times-circle me-1"></i> Needs revision
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" class="form-control"
                                placeholder="Search products by name or description..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>
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
                            <?php foreach ($categories as $cat):
                                $cat_slug = $cat['category'] ?? '';
                                $cat_name = $cat['category_name'] ?? ucfirst($cat['category'] ?? '');
                                if (!empty($cat_slug)): ?>
                                    <option value="<?php echo htmlspecialchars($cat_slug); ?>"
                                        <?php echo $category == $cat_slug ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat_name); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Active Filters -->
                <?php if (!empty($search) || !empty($status) || !empty($category)): ?>
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">Active Filters:</small>
                                <?php if (!empty($search)): ?>
                                    <span class="badge bg-info ms-2">
                                        <i class="fas fa-search me-1"></i> "<?php echo htmlspecialchars($search); ?>"
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($status)): ?>
                                    <span class="badge bg-<?php
                                                            echo $status == 'approved' ? 'success' : ($status == 'pending' ? 'warning' : 'danger');
                                                            ?> ms-2">
                                        <i class="fas fa-<?php
                                                            echo $status == 'approved' ? 'check' : ($status == 'pending' ? 'clock' : 'times');
                                                            ?> me-1"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($category)):
                                    $selected_cat_name = '';
                                    foreach ($categories as $cat) {
                                        if (($cat['category'] ?? '') == $category) {
                                            $selected_cat_name = $cat['category_name'] ?? ucfirst($cat['category'] ?? '');
                                            break;
                                        }
                                    }
                                    if (!empty($selected_cat_name)):
                                ?>
                                        <span class="badge bg-secondary ms-2">
                                            <i class="fas fa-folder me-1"></i> <?php echo htmlspecialchars($selected_cat_name); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div>
                                <a href="products.php" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-times me-1"></i> Clear Filters
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 fw-bold">Products List</h5>
                    <div>
                        <small class="text-muted me-3">
                            <?php if ($total_products > 0): ?>
                                Showing <?php echo count($products); ?> of <?php echo $total_products; ?> product(s)
                            <?php else: ?>
                                No products found
                            <?php endif; ?>
                        </small>
                        <?php if ($total_products > 0): ?>
                            <!-- Export button for all products page -->
                            <a href="export.php?type=all&format=csv" class="btn btn-outline-primary">
                                <i class="fas fa-download me-2"></i> Export
                            </a>

                            <!-- Or with dropdown for multiple formats -->
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-download me-2"></i> Export
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="export.php?type=all&format=csv">
                                            <i class="fas fa-file-csv me-2"></i> Export as CSV
                                        </a></li>
                                    <li><a class="dropdown-item" href="export.php?type=all&format=pdf">
                                            <i class="fas fa-file-pdf me-2"></i> Export as PDF
                                        </a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="export.php?type=low-stock&format=csv">
                                            <i class="fas fa-exclamation-triangle me-2"></i> Low Stock (CSV)
                                        </a></li>
                                    <li><a class="dropdown-item" href="export.php?type=featured&format=csv">
                                            <i class="fas fa-star me-2"></i> Featured (CSV)
                                        </a></li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <?php if (!empty($search) || !empty($status) || !empty($category)): ?>
                            <div class="mb-4">
                                <i class="fas fa-search fa-4x text-muted opacity-25"></i>
                            </div>
                            <h4 class="mb-3 text-muted">No Products Found</h4>
                            <p class="text-muted mb-4">
                                No products match your current filters.
                            </p>
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="fas fa-redo me-2"></i> Clear Filters
                            </a>
                        <?php else: ?>
                            <div class="mb-4">
                                <i class="fas fa-box-open fa-4x text-muted"></i>
                            </div>
                            <h4 class="mb-3 text-muted">No Products Yet</h4>
                            <p class="text-muted mb-4">
                                Start by adding your first product to the store.
                            </p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Add Your First Product
                            </a>
                        <?php endif; ?>
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
                                <?php foreach ($products as $product):
                                    $image_path = SITE_URL . 'assets/images/products/' . ($product['image'] ?: 'default.png');
                                    $product_link = "view.php?id=" . $product['id'];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="product-image-wrapper me-3">
                                                    <img src="<?php echo $image_path; ?>"
                                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                        class="rounded"
                                                        onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'"
                                                        style="width: 50px; height: 50px; object-fit: cover;">
                                                </div>
                                                <div>
                                                    <a href="<?php echo $product_link; ?>" class="text-decoration-none">
                                                        <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                    </a>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                                        <?php if ($product['featured']): ?>
                                                            <span class="badge bg-info ms-2">Featured</span>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?php echo htmlspecialchars($product['category_name'] ?? ucfirst($product['category'] ?? 'N/A')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <strong class="text-primary">$<?php echo number_format($product['price'], 2); ?></strong>
                                                <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                                    <small class="text-muted text-decoration-line-through">
                                                        $<?php echo number_format($product['old_price'], 2); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($product['stock'] == 0): ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times me-1"></i> Out of Stock
                                                </span>
                                            <?php elseif ($product['stock'] < 10): ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i> Low (<?php echo $product['stock']; ?>)
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i> <?php echo $product['stock']; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_config = [
                                                'approved' => ['color' => 'success', 'icon' => 'check'],
                                                'pending' => ['color' => 'warning', 'icon' => 'clock'],
                                                'rejected' => ['color' => 'danger', 'icon' => 'times']
                                            ];
                                            $current_status = $product['approved_status'];
                                            $config = $status_config[$current_status] ?? ['color' => 'secondary', 'icon' => 'question'];
                                            ?>
                                            <span class="badge bg-<?php echo $config['color']; ?>">
                                                <i class="fas fa-<?php echo $config['icon']; ?> me-1"></i>
                                                <?php echo ucfirst($current_status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?php echo number_format($product['views'] ?? 0); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-muted"><?php echo number_format($product['sales_count'] ?? 0); ?></span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="view.php?id=<?php echo $product['id']; ?>"
                                                    class="btn btn-outline-primary"
                                                    title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $product['id']; ?>"
                                                    class="btn btn-outline-warning"
                                                    title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $product['id']; ?>"
                                                    class="btn btn-outline-danger text-decoration-none"
                                                    title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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
                                    <a class="page-link"
                                        href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>

                                <?php
                                // Show limited pagination links
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link"
                                            href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link"
                                        href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                            <p class="text-center text-muted small mt-2">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?> •
                                <?php echo $total_products; ?> total products
                            </p>
                        </nav>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
    .product-image-wrapper {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        overflow: hidden;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .product-image-wrapper img {
        max-width: 100%;
        max-height: 100%;
    }

    .table th {
        font-weight: 600;
        background: #f8f9fa;
        border-top: none;
        color: #495057;
    }

    .table td {
        vertical-align: middle;
        border-color: #eee;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .pagination .page-item.active .page-link {
        background-color: #4361ee;
        border-color: #4361ee;
    }

    .pagination .page-link {
        color: #4361ee;
        border: none;
        margin: 0 2px;
        border-radius: 6px;
    }

    .pagination .page-link:hover {
        background-color: rgba(67, 97, 238, 0.1);
    }

    .card {
        transition: transform 0.2s ease;
    }

    .card:hover {
        transform: translateY(-2px);
    }
</style>

<script>
    // Auto-close alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.classList.add('fade');
            setTimeout(() => {
                alert.remove();
            }, 300);
        });
    }, 5000);

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Confirm before deleting - FIXED NULL ERROR
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('button[name="delete_product"]');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
    });

    // Fix for JavaScript errors - check if elements exist before adding event listeners
    document.addEventListener('DOMContentLoaded', function() {
        const descriptionField = document.getElementById('description');
        const charCounter = document.getElementById('charCounter');

        if (descriptionField && charCounter) {
            descriptionField.addEventListener('input', function() {
                const charCount = this.value.length;
                charCounter.textContent = `${charCount} characters`;

                if (charCount < 10) {
                    charCounter.className = 'text-danger';
                } else if (charCount < 50) {
                    charCounter.className = 'text-warning';
                } else {
                    charCounter.className = 'text-success';
                }
            });
        }

        const productForm = document.getElementById('productForm');
        if (productForm) {
            productForm.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                    submitBtn.disabled = true;
                }
            });
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>