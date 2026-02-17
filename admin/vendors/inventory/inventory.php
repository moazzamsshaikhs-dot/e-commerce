<?php
// admin/vendors/inventory/inventory.php
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
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect(SITE_URL . 'admin/vendors/dashboard.php');
}

// Get filter parameters
$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_stock = $_GET['stock'] ?? '';
$filter_search = $_GET['search'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'name';
$sort_order = $_GET['sort_order'] ?? 'asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Bulk actions
$bulk_action = $_POST['bulk_action'] ?? '';
$selected_ids = $_POST['selected_ids'] ?? [];

if ($bulk_action && !empty($selected_ids)) {
    try {
        $db = getDB();

        if ($bulk_action === 'delete') {
            // Delete selected products
            $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
            $stmt = $db->prepare("DELETE FROM products WHERE id IN ($placeholders) AND vendor_id = ?");
            $stmt->execute(array_merge($selected_ids, [$_SESSION['user_id']]));

            $_SESSION['success'] = count($selected_ids) . ' product(s) deleted successfully.';
        } elseif ($bulk_action === 'update_stock') {
            $new_stock = $_POST['new_stock'] ?? 0;
            if (is_numeric($new_stock) && $new_stock >= 0) {
                $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
                $stmt = $db->prepare("UPDATE products SET stock = ? WHERE id IN ($placeholders) AND vendor_id = ?");
                $stmt->execute(array_merge([$new_stock], $selected_ids, [$_SESSION['user_id']]));

                $_SESSION['success'] = count($selected_ids) . ' product(s) stock updated to ' . $new_stock;
            }
        } elseif ($bulk_action === 'toggle_featured') {
            $placeholders = str_repeat('?,', count($selected_ids) - 1) . '?';
            $stmt = $db->prepare("UPDATE products SET featured = NOT featured WHERE id IN ($placeholders) AND vendor_id = ?");
            $stmt->execute(array_merge($selected_ids, [$_SESSION['user_id']]));

            $_SESSION['success'] = count($selected_ids) . ' product(s) featured status toggled';
        } elseif ($bulk_action === 'export') {
            // Export functionality would go here
            $_SESSION['info'] = 'Export functionality coming soon';
        }

        redirect('inventory.php?' . http_build_query($_GET));
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Bulk action failed: ' . $e->getMessage();
    }
}

// Get vendor inventory data
try {
    $vendor_id = $_SESSION['user_id'];

    // Build query conditions
    $conditions = ["p.vendor_id = ?"];
    $params = [$vendor_id];

    if (!empty($filter_category)) {
        $conditions[] = "p.category = ?";
        $params[] = $filter_category;
    }

    if (!empty($filter_status)) {
        if ($filter_status === 'out_of_stock') {
            $conditions[] = "p.stock = 0";
        } elseif ($filter_status === 'low_stock') {
            $conditions[] = "p.stock > 0 AND p.stock < 10";
        } elseif ($filter_status === 'in_stock') {
            $conditions[] = "p.stock >= 10";
        }
    }

    if (!empty($filter_stock)) {
        if ($filter_stock === 'zero') {
            $conditions[] = "p.stock = 0";
        } elseif ($filter_stock === 'low') {
            $conditions[] = "p.stock BETWEEN 1 AND 9";
        } elseif ($filter_stock === 'medium') {
            $conditions[] = "p.stock BETWEEN 10 AND 49";
        } elseif ($filter_stock === 'high') {
            $conditions[] = "p.stock >= 50";
        }
    }

    if (!empty($filter_search)) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $search_term = "%{$filter_search}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    $where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

    // Get sort order
    $valid_sort_columns = ['name', 'price', 'stock', 'created_at', 'sales_count', 'views'];
    $sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'name';
    $sort_order = strtolower($sort_order) === 'desc' ? 'DESC' : 'ASC';

    // Get total count for pagination
    $count_stmt = $db->prepare("
        SELECT COUNT(*) as total 
        FROM products p
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_products / $per_page);

    // ============================================
    // FIXED: Use direct integer values for LIMIT and OFFSET
    // ============================================
    $inventory_query = "
        SELECT 
            p.*,
            COALESCE(SUM(oi.quantity), 0) as total_sold,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.id) as review_count,
            (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.product_id = p.id AND oi2.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as recent_sales
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN reviews r ON p.id = r.product_id
        $where_clause
        GROUP BY p.id
        ORDER BY $sort_by $sort_order
        LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;

    $inventory_stmt = $db->prepare($inventory_query);
    $inventory_stmt->execute($params);
    $inventory_items = $inventory_stmt->fetchAll();

    // Get inventory summary stats
    $summary_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock > 0 AND stock < 10 THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock >= 10 THEN 1 ELSE 0 END) as in_stock,
            SUM(stock) as total_stock,
            SUM(price * stock) as inventory_value,
            AVG(price) as avg_price,
            SUM(views) as total_views,
            SUM(sales_count) as total_sales
        FROM products
        WHERE vendor_id = ?
    ");
    $summary_stmt->execute([$vendor_id]);
    $inventory_summary = $summary_stmt->fetch();

    // Get categories for filter dropdown
    $category_stmt = $db->prepare("
        SELECT DISTINCT category 
        FROM products 
        WHERE vendor_id = ? AND category IS NOT NULL AND category != ''
        ORDER BY category
    ");
    $category_stmt->execute([$vendor_id]);
    $categories = $category_stmt->fetchAll();

    // Get inventory alerts
    $alerts_stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.stock,
            p.image,
            CASE 
                WHEN p.stock = 0 THEN 'Out of Stock'
                WHEN p.stock < 5 THEN 'Very Low Stock'
                WHEN p.stock < 10 THEN 'Low Stock'
            END as alert_type
        FROM products p
        WHERE p.vendor_id = ? 
        AND (p.stock = 0 OR p.stock < 10)
        ORDER BY p.stock ASC, p.name
        LIMIT 10
    ");
    $alerts_stmt->execute([$vendor_id]);
    $inventory_alerts = $alerts_stmt->fetchAll();

    // Get top selling products
    $top_selling_stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.image,
            p.stock,
            SUM(oi.quantity) as total_sold,
            SUM(oi.subtotal) as revenue,
            COUNT(DISTINCT oi.order_id) as order_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.vendor_id = ?
        GROUP BY p.id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $top_selling_stmt->execute([$vendor_id]);
    $top_selling = $top_selling_stmt->fetchAll();

    // Get stock movement (recently updated)
    $movement_stmt = $db->prepare("
        SELECT 
            p.id,
            p.name,
            p.stock,
            p.image,
            p.updated_at,
            (SELECT quantity FROM order_items WHERE product_id = p.id ORDER BY created_at DESC LIMIT 1) as last_sale_qty,
            (SELECT created_at FROM order_items WHERE product_id = p.id ORDER BY created_at DESC LIMIT 1) as last_sale_date
        FROM products p
        WHERE p.vendor_id = ?
        ORDER BY p.updated_at DESC
        LIMIT 5
    ");
    $movement_stmt->execute([$vendor_id]);
    $stock_movement = $movement_stmt->fetchAll();

    // Calculate inventory metrics
    $inventory_turnover = $inventory_summary['total_sales'] > 0 ?
        $inventory_summary['total_sales'] / max(1, $inventory_summary['avg_price']) : 0;

    $stockout_rate = $inventory_summary['total_products'] > 0 ?
        ($inventory_summary['out_of_stock'] / $inventory_summary['total_products']) * 100 : 0;

    $avg_stock_level = $inventory_summary['total_products'] > 0 ?
        $inventory_summary['total_stock'] / $inventory_summary['total_products'] : 0;
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading inventory data: ' . $e->getMessage();
    error_log("Inventory Error: " . $e->getMessage());

    // Set default empty values
    $inventory_items = [];
    $inventory_summary = [
        'total_products' => 0,
        'out_of_stock' => 0,
        'low_stock' => 0,
        'in_stock' => 0,
        'total_stock' => 0,
        'inventory_value' => 0,
        'avg_price' => 0,
        'total_views' => 0,
        'total_sales' => 0
    ];
    $categories = [];
    $inventory_alerts = [];
    $top_selling = [];
    $stock_movement = [];
    $total_products = 0;
    $total_pages = 1;
    $inventory_turnover = 0;
    $stockout_rate = 0;
    $avg_stock_level = 0;
}

// Log activity
logUserActivity($_SESSION['user_id'], 'inventory_view', 'Viewed inventory management');

$page_title = 'Inventory Management - Vendor Dashboard';
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
                    <h1 class="h3 mb-1 fw-bold text-primary">Inventory Management</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-warehouse me-1 text-success"></i>
                        Manage your product inventory and stock levels
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="../products/add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add Product
                    </a>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-file-import me-2"></i> Import
                    </button>
                    <!-- Add this button next to import button -->
                    <a href="download-template.php" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-download me-1"></i> Download Template
                    </a>
                </div>
            </div>
        </div>

        <!-- Inventory Summary Cards -->
        <div class="row g-4 mb-4">
            <!-- Total Inventory Value -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Inventory Value</h6>
                                <h2 class="mb-0">$<?php echo number_format($inventory_summary['inventory_value'], 2); ?></h2>
                                <small class="text-muted">
                                    <?php echo $inventory_summary['total_stock']; ?> units in stock
                                </small>
                            </div>
                            <div class="stats-icon primary">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Products -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Products</h6>
                                <h2 class="mb-0"><?php echo $inventory_summary['total_products']; ?></h2>
                                <small class="text-success">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <?php echo $inventory_summary['in_stock']; ?> in stock
                                </small>
                            </div>
                            <div class="stats-icon success">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Alerts -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Stock Alerts</h6>
                                <h2 class="mb-0"><?php echo $inventory_summary['low_stock'] + $inventory_summary['out_of_stock']; ?></h2>
                                <small class="text-warning">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?php echo $inventory_summary['low_stock']; ?> low, <?php echo $inventory_summary['out_of_stock']; ?> out
                                </small>
                            </div>
                            <div class="stats-icon warning">
                                <i class="fas fa-bell"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Turnover -->
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Turnover Rate</h6>
                                <h2 class="mb-0"><?php echo number_format($inventory_turnover, 1); ?>x</h2>
                                <small class="text-info">
                                    <i class="fas fa-chart-line me-1"></i>
                                    <?php echo $inventory_summary['total_sales']; ?> total sales
                                </small>
                            </div>
                            <div class="stats-icon info">
                                <i class="fas fa-retweet"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats & Alerts -->
        <div class="row g-4 mb-4">
            <!-- Stock Alerts -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                            Stock Alerts
                        </h5>
                        <span class="badge bg-warning"><?php echo count($inventory_alerts); ?> alerts</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($inventory_alerts)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p class="text-muted mb-0">All products have sufficient stock</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($inventory_alerts as $alert): ?>
                                    <div class="list-group-item border-0 px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3">
                                                    <?php if (!empty($alert['image'])): ?>
                                                        <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $alert['image']; ?>"
                                                            class="rounded" width="40" height="40">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                            style="width: 40px; height: 40px;">
                                                            <i class="fas fa-box text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($alert['name']); ?></h6>
                                                    <small class="text-muted">ID: #<?php echo $alert['id']; ?></small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-<?php
                                                                        echo $alert['stock'] == 0 ? 'danger' : 'warning';
                                                                        ?>">
                                                    <?php echo $alert['alert_type']; ?>
                                                </span>
                                                <div class="fw-bold mt-1"><?php echo $alert['stock']; ?> left</div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <a href="../products/edit.php?id=<?php echo $alert['id']; ?>" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-edit me-1"></i> Restock
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="low-stock.php" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-list me-1"></i> View All Alerts
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Selling Products -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-chart-line me-2 text-success"></i>
                            Top Selling Products
                        </h5>
                        <a href="../reports/sales.php" class="btn btn-sm btn-outline-primary">
                            View Reports
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_selling)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No sales data available</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($top_selling as $product): ?>
                                    <div class="list-group-item border-0 px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-center">
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
                                                    <small class="text-muted">Stock: <?php echo $product['stock']; ?></small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold"><?php echo $product['total_sold'] ?? 0; ?> sold</div>
                                                <small class="text-success">$<?php echo number_format($product['revenue'] ?? 0, 2); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-filter me-2 text-primary"></i>
                    Filter Inventory
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['category']); ?>"
                                    <?php echo $filter_category == $category['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Stock Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="in_stock" <?php echo $filter_status == 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low_stock" <?php echo $filter_status == 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out_of_stock" <?php echo $filter_status == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Stock Level</label>
                        <select name="stock" class="form-select">
                            <option value="">All Levels</option>
                            <option value="zero" <?php echo $filter_stock == 'zero' ? 'selected' : ''; ?>>Zero Stock</option>
                            <option value="low" <?php echo $filter_stock == 'low' ? 'selected' : ''; ?>>Low (1-9)</option>
                            <option value="medium" <?php echo $filter_stock == 'medium' ? 'selected' : ''; ?>>Medium (10-49)</option>
                            <option value="high" <?php echo $filter_stock == 'high' ? 'selected' : ''; ?>>High (50+)</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Sort By</label>
                        <div class="input-group">
                            <select name="sort_by" class="form-select">
                                <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name</option>
                                <option value="stock" <?php echo $sort_by == 'stock' ? 'selected' : ''; ?>>Stock</option>
                                <option value="price" <?php echo $sort_by == 'price' ? 'selected' : ''; ?>>Price</option>
                                <option value="sales_count" <?php echo $sort_by == 'sales_count' ? 'selected' : ''; ?>>Sales</option>
                                <option value="views" <?php echo $sort_by == 'views' ? 'selected' : ''; ?>>Views</option>
                                <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                            </select>
                            <select name="sort_order" class="form-select" style="width: auto;">
                                <option value="asc" <?php echo $sort_order == 'asc' ? 'selected' : ''; ?>>ASC</option>
                                <option value="desc" <?php echo $sort_order == 'desc' ? 'selected' : ''; ?>>DESC</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Search Products</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by name or description..."
                            value="<?php echo htmlspecialchars($filter_search); ?>">
                    </div>

                    <div class="col-md-4 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-grow-1">
                                <i class="fas fa-search me-2"></i> Apply Filters
                            </button>
                            <a href="inventory.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="POST" id="bulkForm">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <select name="bulk_action" class="form-select" onchange="toggleBulkOptions(this.value)">
                                <option value="">Bulk Actions</option>
                                <option value="update_stock">Update Stock</option>
                                <option value="toggle_featured">Toggle Featured</option>
                                <option value="export">Export Selected</option>
                                <option value="delete" class="text-danger">Delete Selected</option>
                            </select>
                        </div>

                        <div class="col-md-4" id="stockInputContainer" style="display: none;">
                            <div class="input-group">
                                <input type="number" name="new_stock" class="form-control" placeholder="Enter new stock quantity" min="0">
                                <button type="submit" class="btn btn-primary" name="apply_stock">
                                    Apply
                                </button>
                            </div>
                        </div>

                        <div class="col-md-4 text-end">
                            <button type="button" class="btn btn-outline-primary" onclick="selectAllItems()">
                                <i class="fas fa-check-square me-2"></i> Select All
                            </button>
                            <button type="submit" class="btn btn-danger" onclick="return confirmBulkAction()">
                                <i class="fas fa-play me-2"></i> Apply
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    Inventory Items (<?php echo $total_products; ?>)
                    <small class="text-muted ms-2">
                        Showing <?php echo count($inventory_items); ?> of <?php echo $total_products; ?>
                    </small>
                </h5>
                <div>
                    <a href="print-inventory.php?<?php echo http_build_query($_GET); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                        <i class="fas fa-print me-1"></i> Print
                    </a>
                </div>
            </div>

            <div class="card-body">
                <?php if (empty($inventory_items)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No products found</h5>
                        <p class="text-muted mb-4">
                            <?php if (!empty($filter_search) || !empty($filter_category) || !empty($filter_status)): ?>
                                Try adjusting your filters
                            <?php else: ?>
                                Start by adding your first product
                            <?php endif; ?>
                        </p>
                        <a href="../products/add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Product
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                                    </th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Sales</th>
                                    <th>Rating</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_items as $item):
                                    $stock_percentage = $item['stock'] > 0 ? min(100, ($item['stock'] / 100) * 100) : 0;
                                    $stock_color = $item['stock'] == 0 ? 'danger' : ($item['stock'] < 10 ? 'warning' : 'success');
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_ids[]" value="<?php echo $item['id']; ?>"
                                                class="item-checkbox" form="bulkForm">
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm me-3">
                                                    <?php if (!empty($item['image'])): ?>
                                                        <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $item['image']; ?>"
                                                            alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                            class="rounded" width="40" height="40">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                            style="width: 40px; height: 40px;">
                                                            <i class="fas fa-box text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                    <small class="text-muted">ID: #<?php echo $item['id']; ?></small>
                                                    <?php if ($item['featured']): ?>
                                                        <span class="badge bg-info ms-2">Featured</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($item['category']): ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold">$<?php echo number_format($item['price'], 2); ?></div>
                                            <?php if ($item['old_price']): ?>
                                                <small class="text-muted text-decoration-line-through">
                                                    $<?php echo number_format($item['old_price'], 2); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-3" style="height: 8px; width: 60px;">
                                                    <div class="progress-bar bg-<?php echo $stock_color; ?>"
                                                        style="width: <?php echo $stock_percentage; ?>%"></div>
                                                </div>
                                                <div class="fw-bold"><?php echo $item['stock']; ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $stock_color; ?>">
                                                <?php
                                                if ($item['stock'] == 0) echo 'Out of Stock';
                                                elseif ($item['stock'] < 10) echo 'Low Stock';
                                                else echo 'In Stock';
                                                ?>
                                            </span>
                                            <?php if ($item['low_stock']): ?>
                                                <i class="fas fa-exclamation-triangle text-warning ms-1"
                                                    title="Low Stock Alert"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo $item['total_sold'] ?? 0; ?></div>
                                            <small class="text-muted"><?php echo $item['recent_sales'] ?? 0; ?> recent</small>
                                        </td>
                                        <td>
                                            <?php if ($item['avg_rating'] > 0): ?>
                                                <div class="d-flex align-items-center">
                                                    <div class="text-warning me-1">
                                                        <i class="fas fa-star"></i>
                                                    </div>
                                                    <span><?php echo number_format($item['avg_rating'], 1); ?></span>
                                                    <small class="text-muted ms-1">(<?php echo $item['review_count']; ?>)</small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../products/edit.php?id=<?php echo $item['id']; ?>"
                                                    class="btn btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="../products/view.php?id=<?php echo $item['id']; ?>"
                                                    class="btn btn-outline-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-warning"
                                                    onclick="quickUpdateStock(<?php echo $item['id']; ?>, <?php echo $item['stock']; ?>)"
                                                    title="Update Stock">
                                                    <i class="fas fa-sync"></i>
                                                </button>
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
                                    <a class="page-link" href="?<?php
                                                                $prev = $page - 1;
                                                                $query = $_GET;
                                                                $query['page'] = $prev;
                                                                echo http_build_query($query);
                                                                ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>

                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);

                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php
                                                                    $query = $_GET;
                                                                    $query['page'] = $i;
                                                                    echo http_build_query($query);
                                                                    ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php
                                                                $next = $page + 1;
                                                                $query = $_GET;
                                                                $query['page'] = $next;
                                                                echo http_build_query($query);
                                                                ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="card-footer bg-white border-0">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Inventory Value: $<?php echo number_format($inventory_summary['inventory_value'], 2); ?> |
                            Stockout Rate: <?php echo number_format($stockout_rate, 1); ?>% |
                            Avg Stock: <?php echo number_format($avg_stock_level, 1); ?>
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">
                            Last updated: <?php echo date('M d, Y h:i A'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Import Modal -->
<!-- In your inventory.php, add this import button -->
<button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#importModal">
    <i class="fas fa-file-import me-2"></i> Import
</button>

<!-- Import Modal - Updated with better options -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-file-import me-2"></i>
                    Import Products from CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="import.php" enctype="multipart/form-data" id="importForm">
                <div class="modal-body">
                    <!-- File Upload -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-file-csv me-2 text-primary"></i>
                            Select CSV File *
                        </label>
                        <input type="file" name="inventory_file" class="form-control" 
                               accept=".csv,.xlsx,.xls" required>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Supported formats: CSV, Excel (.xlsx, .xls). Max file size: 5MB
                        </div>
                    </div>
                    
                    <!-- Header Row Selection -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-table me-2 text-primary"></i>
                                Header Row
                            </label>
                            <select name="header_row" class="form-select">
                                <option value="0">Auto-detect headers</option>
                                <option value="1" selected>Row 1 is header</option>
                                <option value="2">Row 2 is header</option>
                                <option value="3">Row 3 is header</option>
                                <option value="4">Row 4 is header</option>
                                <option value="5">Row 5 is header</option>
                            </select>
                            <div class="form-text">Select which row contains column names</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                <i class="fas fa-cog me-2 text-primary"></i>
                                Import Action
                            </label>
                            <select name="import_action" class="form-select">
                                <option value="add_new">Add new products only</option>
                                <option value="update_existing">Update existing products only</option>
                                <option value="add_update" selected>Add new and update existing</option>
                            </select>
                            <div class="form-text">Choose how to handle existing products</div>
                        </div>
                    </div>
                    
                    <!-- Update Options -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-sliders-h me-2 text-primary"></i>
                                Update Options
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="update_stock" id="updateStock" checked>
                                        <label class="form-check-label" for="updateStock">
                                            <i class="fas fa-boxes me-1 text-success"></i>
                                            Update stock quantities
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="update_prices" id="updatePrices">
                                        <label class="form-check-label" for="updatePrices">
                                            <i class="fas fa-dollar-sign me-1 text-info"></i>
                                            Update prices
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Format Info -->
                    <div class="alert alert-info">
                        <div class="d-flex">
                            <i class="fas fa-file-csv fa-2x me-3"></i>
                            <div>
                                <h6 class="alert-heading mb-1">CSV File Format</h6>
                                <p class="small mb-2">Your CSV file should have these columns (order doesn't matter):</p>
                                <code class="bg-white p-2 d-block rounded">
                                    name, description, price, stock, category
                                </code>
                                <hr class="my-2">
                                <div class="row small">
                                    <div class="col-md-6">
                                        <i class="fas fa-check-circle text-success me-1"></i> name: Product name (required)
                                        <br>
                                        <i class="fas fa-check-circle text-success me-1"></i> description: Product description
                                    </div>
                                    <div class="col-md-6">
                                        <i class="fas fa-check-circle text-success me-1"></i> price: Selling price (required)
                                        <br>
                                        <i class="fas fa-check-circle text-success me-1"></i> stock: Quantity in stock
                                    </div>
                                </div>
                                <a href="download-template.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download me-1"></i> Download Sample CSV
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar (hidden initially) -->
                    <div id="importProgress" style="display: none;">
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 style="width: 0%" id="importProgressBar">0%</div>
                        </div>
                        <p class="text-center small text-muted" id="importStatus">Processing...</p>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="importSubmitBtn">
                        <i class="fas fa-upload me-2"></i> Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success/Error Messages Section - Add this after header -->
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

<?php if (isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Import completed with <?php echo count($_SESSION['import_errors']); ?> warnings:</strong>
        <ul class="mb-0 mt-2">
            <?php 
            foreach(array_slice($_SESSION['import_errors'], 0, 5) as $error):
                echo "<li>" . htmlspecialchars($error) . "</li>";
            endforeach;
            if (count($_SESSION['import_errors']) > 5):
                echo "<li>... and " . (count($_SESSION['import_errors']) - 5) . " more errors</li>";
            endif;
            ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['import_errors']); ?>
<?php endif; ?>

<!-- Quick Update Stock Modal -->
<div class="modal fade" id="quickUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="quickUpdateForm">
                <div class="modal-body">
                    <input type="hidden" id="updateProductId">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" id="updateProductName" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="number" id="currentStock" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Update Type</label>
                        <select id="updateType" class="form-select" onchange="toggleUpdateFields()">
                            <option value="set">Set to specific value</option>
                            <option value="add">Add quantity</option>
                            <option value="subtract">Subtract quantity</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="updateLabel">New Stock Quantity</label>
                        <input type="number" id="updateValue" class="form-control" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason (Optional)</label>
                        <textarea id="updateReason" class="form-control" rows="2" placeholder="e.g., Restocked, Sold out, etc."></textarea>
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

<style>
    .stats-card {
        transition: transform 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .stats-icon.primary {
        background: rgba(67, 97, 238, 0.1);
        color: #4361ee;
    }

    .stats-icon.success {
        background: rgba(34, 197, 94, 0.1);
        color: #22c55e;
    }

    .stats-icon.warning {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .stats-icon.info {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .avatar-sm {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .progress {
        border-radius: 10px;
    }

    .table th {
        font-weight: 600;
        border-top: none;
        color: #495057;
        background: #f8f9fa;
    }

    .table td {
        vertical-align: middle;
    }

    .item-checkbox:checked {
        background-color: #4361ee;
        border-color: #4361ee;
    }

    #bulkForm .form-select option.text-danger {
        color: #dc3545 !important;
    }
</style>

<script>
    // Bulk Actions Functions
    function toggleBulkOptions(action) {
        const stockInput = document.getElementById('stockInputContainer');
        if (action === 'update_stock') {
            stockInput.style.display = 'block';
        } else {
            stockInput.style.display = 'none';
        }
    }

    function selectAllItems() {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const selectAll = document.getElementById('selectAll');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);

        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
        });
        selectAll.checked = !allChecked;
    }

    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
    }

    function confirmBulkAction() {
        const checkboxes = document.querySelectorAll('.item-checkbox:checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one item.');
            return false;
        }

        const action = document.querySelector('[name="bulk_action"]').value;
        if (!action) {
            alert('Please select a bulk action.');
            return false;
        }

        if (action === 'delete') {
            return confirm(`Are you sure you want to delete ${checkboxes.length} product(s)? This action cannot be undone.`);
        }

        return true;
    }

    // Quick Stock Update Functions
    function quickUpdateStock(productId, currentStock) {
        const row = document.querySelector(`input[value="${productId}"]`).closest('tr');
        const productName = row.querySelector('h6').textContent;

        document.getElementById('updateProductId').value = productId;
        document.getElementById('updateProductName').value = productName;
        document.getElementById('currentStock').value = currentStock;
        document.getElementById('updateValue').value = currentStock;

        const modal = new bootstrap.Modal(document.getElementById('quickUpdateModal'));
        modal.show();
    }

    function toggleUpdateFields() {
        const updateType = document.getElementById('updateType').value;
        const updateLabel = document.getElementById('updateLabel');
        const updateValue = document.getElementById('updateValue');
        const currentStock = parseInt(document.getElementById('currentStock').value);

        switch (updateType) {
            case 'set':
                updateLabel.textContent = 'New Stock Quantity';
                updateValue.value = currentStock;
                break;
            case 'add':
                updateLabel.textContent = 'Quantity to Add';
                updateValue.value = '';
                break;
            case 'subtract':
                updateLabel.textContent = 'Quantity to Subtract';
                updateValue.value = '';
                break;
        }
    }

    document.getElementById('quickUpdateForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const productId = document.getElementById('updateProductId').value;
        const updateType = document.getElementById('updateType').value;
        const updateValue = parseInt(document.getElementById('updateValue').value);
        const currentStock = parseInt(document.getElementById('currentStock').value);
        const reason = document.getElementById('updateReason').value;

        let newStock = currentStock;

        switch (updateType) {
            case 'set':
                newStock = updateValue;
                break;
            case 'add':
                newStock = currentStock + updateValue;
                break;
            case 'subtract':
                newStock = Math.max(0, currentStock - updateValue);
                break;
        }

        // Send AJAX request to update stock
        fetch('update-stock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    product_id: productId,
                    new_stock: newStock,
                    reason: reason
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Stock updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating stock: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating stock.');
            });
    });

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Check if any checkboxes are checked on page load
        const checkboxes = document.querySelectorAll('.item-checkbox');
        const selectAll = document.getElementById('selectAll');

        function updateSelectAll() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const someChecked = Array.from(checkboxes).some(cb => cb.checked);
            selectAll.checked = allChecked;
            selectAll.indeterminate = someChecked && !allChecked;
        }

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectAll);
        });

        updateSelectAll();
    });

    // Auto-refresh inventory every 5 minutes
    setTimeout(function() {
        if (confirm('Refresh inventory data?')) {
            window.location.reload();
        }
    }, 300000);


// Import form submission handling
document.getElementById('importForm').addEventListener('submit', function(e) {
    const fileInput = this.querySelector('input[type="file"]');
    const submitBtn = document.getElementById('importSubmitBtn');
    const progressDiv = document.getElementById('importProgress');
    const progressBar = document.getElementById('importProgressBar');
    const statusText = document.getElementById('importStatus');
    
    if (!fileInput.files || fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please select a file to upload');
        return false;
    }
    
    const file = fileInput.files[0];
    const fileSize = file.size / 1024 / 1024; // in MB
    
    if (fileSize > 5) {
        e.preventDefault();
        alert('File size must be less than 5MB');
        return false;
    }
    
    if (file.type && !file.type.includes('csv') && !file.name.endsWith('.csv')) {
        if (!confirm('File may not be a CSV. Continue anyway?')) {
            e.preventDefault();
            return false;
        }
    }
    
    // Show progress
    progressDiv.style.display = 'block';
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Importing...';
    
    let progress = 0;
    const interval = setInterval(function() {
        progress += 5;
        if (progress <= 90) {
            progressBar.style.width = progress + '%';
            progressBar.textContent = progress + '%';
            statusText.textContent = 'Processing row ' + Math.floor(progress / 5) + '...';
        }
    }, 200);
    
    // Store interval to clear later
    window.importInterval = interval;
});

// Clear interval when modal is hidden
document.getElementById('importModal').addEventListener('hidden.bs.modal', function() {
    if (window.importInterval) {
        clearInterval(window.importInterval);
    }
    document.getElementById('importProgress').style.display = 'none';
    document.getElementById('importSubmitBtn').disabled = false;
    document.getElementById('importSubmitBtn').innerHTML = '<i class="fas fa-upload me-2"></i> Start Import';
});

// Auto-dismiss alerts after 8 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 8000);

</script>

<?php require_once '../../includes/footer.php'; ?>