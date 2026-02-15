<?php
// admin/vendors/products/low-stock.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Define SITE_URL if not defined


// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    
    // Get vendor status from users table
    $stmt = $db->prepare("SELECT vendor_status, full_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        $_SESSION['error'] = 'Vendor account not found.';
        header('Location: ' . SITE_URL . 'logout.php');
        exit();
    }
    
    if ($vendor['vendor_status'] !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
        exit();
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    error_log("Vendor status check error: " . $e->getMessage());
    header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
    exit();
}

$page_title = 'Low Stock Products';
require_once '../../includes/header.php';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'stock_asc';
$threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 10;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Initialize variables
$products = [];
$categories = [];
$total_products = 0;
$total_pages = 1;
$total_vendor_products = 0;
$stats = [
    'total_low_stock' => 0,
    'out_of_stock' => 0,
    'critical_stock' => 0,
    'low_stock' => 0,
    'total_inventory' => 0,
    'inventory_value' => 0,
    'avg_stock' => 0
];

try {
    $db = getDB();
    
    // ============================================
    // FIX 1: Update low_stock flags for existing products
    // ============================================
    $update_stmt = $db->prepare("
        UPDATE products 
        SET low_stock = CASE 
            WHEN stock > 0 AND stock < 10 THEN 1
            ELSE 0
        END,
        out_of_stock = CASE 
            WHEN stock = 0 THEN 1
            ELSE 0
        END
        WHERE vendor_id = ?
    ");
    $update_stmt->execute([$vendor_id]);
    
    // Get total vendor products for display
    $total_stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
    $total_stmt->execute([$vendor_id]);
    $total_vendor_products = $total_stmt->fetchColumn();
    
    // Get categories for filter
    $cat_stmt = $db->prepare("SELECT DISTINCT category FROM products WHERE vendor_id = ? AND category IS NOT NULL AND category != '' ORDER BY category");
    $cat_stmt->execute([$vendor_id]);
    $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // ============================================
    // FIX 2: Build query with correct column names
    // ============================================
    
    // Base conditions
    $conditions = ["p.vendor_id = ?"];
    $params = [$vendor_id];
    
    // Add low stock condition (stock < threshold OR out of stock)
    $conditions[] = "(p.stock < ? OR p.stock = 0)";
    $params[] = $threshold;
    
    // Apply status filter
    if ($status_filter === 'out_of_stock') {
        $conditions[] = "p.stock = 0";
    } elseif ($status_filter === 'critical') {
        $conditions[] = "p.stock > 0 AND p.stock < 5";
    } elseif ($status_filter === 'low') {
        $conditions[] = "p.stock >= 5 AND p.stock < ?";
        $params[] = $threshold;
    }
    
    // Apply category filter
    if (!empty($category_filter)) {
        $conditions[] = "p.category = ?";
        $params[] = $category_filter;
    }
    
    // Apply search
    if (!empty($search_term)) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
        $search_param = "%$search_term%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Build WHERE clause
    $where_clause = implode(" AND ", $conditions);
    
    // ============================================
    // FIX 3: Main query with CORRECT column names (order_date instead of created_at)
    // ============================================
    $query = "
        SELECT 
            p.*,
            CASE 
                WHEN p.stock = 0 THEN 'out_of_stock'
                WHEN p.stock < 5 THEN 'critical'
                WHEN p.stock < {$threshold} THEN 'low'
                ELSE 'normal'
            END as stock_status,
            COALESCE((
                SELECT SUM(oi.quantity) 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.product_id = p.id 
                AND o.status != 'cancelled'
                AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ), 0) as sales_last_30_days,
            COALESCE((
                SELECT COUNT(*) 
                FROM order_items oi 
                WHERE oi.product_id = p.id
            ), 0) as total_orders,
            DATEDIFF(NOW(), p.updated_at) as days_since_update
        FROM products p
        WHERE {$where_clause}
    ";
    
    // Count query
    $count_query = "
        SELECT COUNT(*) 
        FROM products p 
        WHERE {$where_clause}
    ";
    
    // Apply sorting
    switch($sort_by) {
        case 'stock_desc':
            $query .= " ORDER BY p.stock DESC";
            break;
        case 'name_asc':
            $query .= " ORDER BY p.name ASC";
            break;
        case 'name_desc':
            $query .= " ORDER BY p.name DESC";
            break;
        case 'price_asc':
            $query .= " ORDER BY p.price ASC";
            break;
        case 'price_desc':
            $query .= " ORDER BY p.price DESC";
            break;
        case 'sales_desc':
            $query .= " ORDER BY sales_last_30_days DESC";
            break;
        case 'oldest':
            $query .= " ORDER BY p.updated_at ASC";
            break;
        case 'stock_asc':
        default:
            $query .= " ORDER BY p.stock ASC";
            break;
    }
    
    // Get total count for pagination
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);
    
    // Adjust page if out of range
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }
    
    // Add pagination
    $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    // Execute main query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // ============================================
    // FIX 4: Get summary statistics
    // ============================================
    $stats_query = "
        SELECT 
            COUNT(*) as total_low_stock,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock > 0 AND stock < 5 THEN 1 ELSE 0 END) as critical_stock,
            SUM(CASE WHEN stock >= 5 AND stock < ? THEN 1 ELSE 0 END) as low_stock,
            SUM(stock) as total_inventory,
            SUM(CASE WHEN stock > 0 THEN price * stock ELSE 0 END) as inventory_value,
            AVG(CASE WHEN stock > 0 THEN stock END) as avg_stock
        FROM products
        WHERE vendor_id = ? AND (stock < ? OR stock = 0)
    ";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([$threshold, $vendor_id, $threshold]);
    $stats_result = $stats_stmt->fetch();
    
    if ($stats_result) {
        $stats = $stats_result;
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading products: ' . $e->getMessage();
    error_log("Low stock query error: " . $e->getMessage());
    error_log("Query: " . ($query ?? 'N/A'));
    error_log("Params: " . print_r($params ?? [], true));
}

// Function to get stock status badge
function getStockStatusBadge($status, $stock) {
    switch($status) {
        case 'out_of_stock':
            return '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i> Out of Stock</span>';
        case 'critical':
            return '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i> Critical (' . $stock . ' left)</span>';
        case 'low':
            return '<span class="badge bg-info"><i class="fas fa-exclamation-circle me-1"></i> Low Stock (' . $stock . ' left)</span>';
        default:
            return '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> In Stock (' . $stock . ')</span>';
    }
}
?>

<!-- Debug Info (only visible when debug=1) -->
<?php if (isset($_GET['debug']) && $_GET['debug'] == 1): ?>
<div class="container-fluid mt-3">
    <div class="alert alert-info">
        <h5>Debug Information:</h5>
        <pre>
Vendor ID: <?php echo $vendor_id; ?>
Threshold: <?php echo $threshold; ?>
Total Products in DB: <?php echo $total_vendor_products; ?>
Products Meeting Low Stock Criteria: <?php echo $stats['total_low_stock']; ?>
Products Found in Query: <?php echo count($products); ?>
Status Filter: <?php echo $status_filter; ?>
Category Filter: <?php echo $category_filter ?: 'None'; ?>
Search Term: <?php echo $search_term ?: 'None'; ?>
Page: <?php echo $page; ?>
Total Pages: <?php echo $total_pages; ?>

Product List (All Products):
<?php 
// Show all products for debugging
$debug_all = $db->prepare("SELECT id, name, stock, 
    CASE 
        WHEN stock = 0 THEN 'out_of_stock'
        WHEN stock < 5 THEN 'critical' 
        WHEN stock < $threshold THEN 'low' 
        ELSE 'normal' 
    END as status 
    FROM products WHERE vendor_id = ?");
$debug_all->execute([$vendor_id]);
$all_products_list = $debug_all->fetchAll();
foreach($all_products_list as $p) {
    echo "- ID: {$p['id']}, {$p['name']}, Stock: {$p['stock']}, Status: {$p['status']}\n";
}
?>

Low Stock Products (Should Show):
<?php 
foreach($products as $p) {
    echo "- ID: {$p['id']}, {$p['name']}, Stock: {$p['stock']}, Status: {$p['stock_status']}\n";
}
?>
        </pre>
        <a href="low-stock.php" class="btn btn-sm btn-secondary">Hide Debug</a>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-container">
    <?php include '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Low Stock Products
                    </h1>
                    <p class="text-muted mb-0">
                        Products with stock below <?php echo $threshold; ?> units. 
                        <span class="badge bg-danger ms-2"><?php echo $stats['total_low_stock']; ?> items need attention</span>
                    </p>
                </div>
                <div class="d-flex gap-3">
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Products
                    </a>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i> Add New Product
                    </a>
                    <!-- <a href="?debug=1" class="btn btn-warning btn-sm">
                        <i class="fas fa-bug me-2"></i> Debug
                    </a> -->
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="row g-3 mt-3">
                <div class="col-md-3">
                    <div class="stat-card bg-danger bg-opacity-10 p-3 rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-danger text-uppercase">Out of Stock</small>
                                <h3 class="mb-0 text-danger"><?php echo $stats['out_of_stock']; ?></h3>
                            </div>
                            <i class="fas fa-times-circle fa-2x text-danger opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-warning bg-opacity-10 p-3 rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-warning text-uppercase">Critical (&lt;5)</small>
                                <h3 class="mb-0 text-warning"><?php echo $stats['critical_stock']; ?></h3>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-2x text-warning opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-info bg-opacity-10 p-3 rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-info text-uppercase">Low Stock</small>
                                <h3 class="mb-0 text-info"><?php echo $stats['low_stock']; ?></h3>
                            </div>
                            <i class="fas fa-exclamation-circle fa-2x text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card bg-success bg-opacity-10 p-3 rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-success text-uppercase">Inventory Value</small>
                                <h3 class="mb-0 text-success">$<?php echo number_format($stats['inventory_value'], 2); ?></h3>
                            </div>
                            <i class="fas fa-dollar-sign fa-2x text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Low Stock</option>
                            <option value="out_of_stock" <?php echo $status_filter == 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                            <option value="critical" <?php echo $status_filter == 'critical' ? 'selected' : ''; ?>>Critical (&lt;5)</option>
                            <option value="low" <?php echo $status_filter == 'low' ? 'selected' : ''; ?>>Low (5-<?php echo $threshold-1; ?>)</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php if (!empty($categories)): ?>
                                <?php foreach($categories as $cat): ?>
                                    <?php if (!empty($cat)): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo $category_filter == $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($cat)); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="stock_asc" <?php echo $sort_by == 'stock_asc' ? 'selected' : ''; ?>>Stock (Low to High)</option>
                            <option value="stock_desc" <?php echo $sort_by == 'stock_desc' ? 'selected' : ''; ?>>Stock (High to Low)</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                            <option value="price_asc" <?php echo $sort_by == 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)</option>
                            <option value="price_desc" <?php echo $sort_by == 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)</option>
                            <option value="sales_desc" <?php echo $sort_by == 'sales_desc' ? 'selected' : ''; ?>>Most Sold (30 days)</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest Updated</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Threshold</label>
                        <select name="threshold" class="form-select">
                            <option value="5" <?php echo $threshold == 5 ? 'selected' : ''; ?>>5 units</option>
                            <option value="10" <?php echo $threshold == 10 ? 'selected' : ''; ?>>10 units</option>
                            <option value="15" <?php echo $threshold == 15 ? 'selected' : ''; ?>>15 units</option>
                            <option value="20" <?php echo $threshold == 20 ? 'selected' : ''; ?>>20 units</option>
                            <option value="25" <?php echo $threshold == 25 ? 'selected' : ''; ?>>25 units</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search_term); ?>" 
                                   placeholder="Product name...">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search_term) || $status_filter != 'all' || !empty($category_filter)): ?>
                                <a href="low-stock.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Products Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($products)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                        <h5>No low stock products found</h5>
                        <p class="text-muted">
                            <?php if (!empty($search_term) || $status_filter != 'all' || !empty($category_filter)): ?>
                                Try adjusting your filters
                            <?php else: ?>
                                <?php 
                                if ($total_vendor_products > 0) {
                                    echo "You have $total_vendor_products total products. ";
                                    if ($stats['total_low_stock'] == 0) {
                                        echo "All products have stock above $threshold units.";
                                    } else {
                                        echo "But none match the current filters.";
                                    }
                                } else {
                                    echo "You haven't added any products yet.";
                                }
                                ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search_term) || $status_filter != 'all' || !empty($category_filter)): ?>
                            <a href="low-stock.php" class="btn btn-primary">Clear Filters</a>
                        <?php elseif ($total_vendor_products == 0): ?>
                            <a href="add.php" class="btn btn-primary">Add Your First Product</a>
                        <?php else: ?>
                            <a href="products.php" class="btn btn-primary">View All Products</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="width: 80px;">Image</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock Status</th>
                                    <th>Sales (30d)</th>
                                    <th>Last Updated</th>
                                    <th style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($products as $index => $product): 
                                    $row_number = $offset + $index + 1;
                                    $stock_status = $product['stock_status'] ?? 'normal';
                                ?>
                                <tr class="<?php 
                                    echo $stock_status == 'out_of_stock' ? 'table-danger' : 
                                        ($stock_status == 'critical' ? 'table-warning' : ''); 
                                ?>">
                                    <td><?php echo $row_number; ?></td>
                                    <td>
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light d-flex align-items-center justify-content-center rounded" 
                                                 style="width: 60px; height: 60px;">
                                                <i class="fas fa-image text-muted fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="edit.php?id=<?php echo $product['id']; ?>" class="text-decoration-none fw-bold">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                        <?php if (($product['approved_status'] ?? '') == 'pending'): ?>
                                            <span class="badge bg-warning ms-2">Pending</span>
                                        <?php endif; ?>
                                        <div class="small text-muted mt-1">
                                            ID: #<?php echo $product['id']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($product['price'] ?? 0, 2); ?></strong>
                                        <?php if (!empty($product['old_price'])): ?>
                                            <br>
                                            <small class="text-muted text-decoration-line-through">
                                                $<?php echo number_format($product['old_price'], 2); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo getStockStatusBadge($stock_status, $product['stock'] ?? 0); ?>
                                        
                                        <!-- Restock Recommendation -->
                                        <?php if (($product['sales_last_30_days'] ?? 0) > 0 && ($product['stock'] ?? 0) < ($product['sales_last_30_days'] * 2)): ?>
                                            <div class="mt-1">
                                                <small class="text-danger">
                                                    <i class="fas fa-fire me-1"></i>
                                                    Selling fast! (<?php echo $product['sales_last_30_days']; ?>/month)
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo $product['sales_last_30_days'] ?? 0; ?></strong>
                                        <small class="text-muted d-block">
                                            (<?php echo $product['total_orders'] ?? 0; ?> total)
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo !empty($product['updated_at']) ? date('M d, Y', strtotime($product['updated_at'])) : 'N/A'; ?>
                                            <br>
                                            <span class="text-muted">
                                                <?php echo $product['days_since_update'] ?? 0; ?> days ago
                                            </span>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit.php?id=<?php echo $product['id']; ?>&ref=low-stock" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="restock.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-outline-success" 
                                               title="Restock Product">
                                                <i class="fas fa-plus-circle"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-info" 
                                                    title="View Details"
                                                    onclick="showProductDetails(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-eye"></i>
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
                        <div class="d-flex justify-content-between align-items-center p-3 border-top">
                            <div>
                                Showing <?php echo $offset + 1; ?> to 
                                <?php echo min($offset + $limit, $total_products); ?> 
                                of <?php echo $total_products; ?> products
                            </div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search_term); ?>&sort=<?php echo urlencode($sort_by); ?>&threshold=<?php echo $threshold; ?>">
                                            Previous
                                        </a>
                                    </li>
                                    
                                    <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    for($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search_term); ?>&sort=<?php echo urlencode($sort_by); ?>&threshold=<?php echo $threshold; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&search=<?php echo urlencode($search_term); ?>&sort=<?php echo urlencode($sort_by); ?>&threshold=<?php echo $threshold; ?>">
                                            Next
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Restock Suggestions -->
        <?php if (!empty($products) && ($stats['out_of_stock'] > 0 || $stats['critical_stock'] > 0)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0 py-3">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-lightbulb me-2 text-warning"></i>
                    Smart Restock Suggestions
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <?php 
                    $suggestions = array_filter($products, function($p) {
                        return ($p['sales_last_30_days'] ?? 0) > 0;
                    });
                    usort($suggestions, function($a, $b) {
                        return ($b['sales_last_30_days'] ?? 0) - ($a['sales_last_30_days'] ?? 0);
                    });
                    $suggestions = array_slice($suggestions, 0, 3);
                    ?>
                    
                    <?php if (!empty($suggestions)): ?>
                        <?php foreach($suggestions as $product): ?>
                            <div class="col-md-4">
                                <div class="card h-100 border-0 bg-light">
                                    <div class="card-body">
                                        <h6 class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <div class="mb-2">
                                            <span class="badge bg-<?php echo ($product['stock'] ?? 0) == 0 ? 'danger' : 'warning'; ?>">
                                                Current: <?php echo $product['stock'] ?? 0; ?> units
                                            </span>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Sold last 30 days:</small>
                                            <strong class="d-block"><?php echo $product['sales_last_30_days'] ?? 0; ?> units</strong>
                                        </div>
                                        <div class="mb-3">
                                            <small class="text-muted">Recommended restock:</small>
                                            <strong class="d-block text-success">
                                                <?php echo max(($product['sales_last_30_days'] ?? 0) * 2, 10); ?> units
                                            </strong>
                                        </div>
                                        <a href="restock.php?id=<?php echo $product['id']; ?>&quantity=<?php echo max(($product['sales_last_30_days'] ?? 0) * 2, 10); ?>" 
                                           class="btn btn-sm btn-success w-100">
                                            <i class="fas fa-truck me-2"></i> Restock Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Export Options -->
        <!-- Export buttons for low stock page -->
<div class="mt-4 d-flex justify-content-end gap-2">
    <a href="export.php?type=low-stock&format=csv" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-file-csv me-2"></i> Export CSV
    </a>
    <a href="export.php?type=low-stock&format=pdf" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-file-pdf me-2"></i> Export PDF
    </a>
</div>
    </main>
</div>


<!-- Product Details Modal -->
<div class="modal fade" id="productDetailsModal" tabindex="-1" aria-labelledby="productDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="productDetailsModalLabel">
                    <i class="fas fa-box me-2"></i> Product Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="productDetailsContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted">Loading product details...</p>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Close
                </button>
                <a href="edit.php?id=<?php echo $product['id']; ?>" id="modalEditBtn" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i> Edit Product
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.table th {
    font-weight: 600;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    vertical-align: middle;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
}

.badge {
    font-weight: 500;
    padding: 0.5em 0.75em;
}

.img-thumbnail {
    border-radius: 8px;
    border: 2px solid #f8f9fa;
}

.pagination .page-link {
    border: none;
    margin: 0 2px;
    border-radius: 8px;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>

<script>
// Enhanced showProductDetails function with better error handling
function showProductDetails(productId) {
    const modal = new bootstrap.Modal(document.getElementById('productDetailsModal'));
    const content = document.getElementById('productDetailsContent');
    const modalTitle = document.getElementById('productDetailsModalLabel');
    const editBtn = document.getElementById('modalEditBtn');
    
    // Show loading with animation
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="text-muted">Loading product details...</p>
            <p class="text-muted small">Product ID: #${productId}</p>
        </div>
    `;
    
    // Update modal title
    modalTitle.innerHTML = `<i class="fas fa-box me-2"></i> Product Details #${productId}`;
    
    // Set edit button href
    editBtn.href = `edit.php?id=${productId}&ref=low-stock`;
    
    // Show modal immediately with loading
    modal.show();
    
    // Fetch product details with timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout
    
    // Get the correct URL for AJAX
    const ajaxUrl = 'ajax/get_product_details.php?id=' + productId;
    console.log('Fetching from:', ajaxUrl); // Debug log
    
    fetch(ajaxUrl, { signal: controller.signal })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Fade in new content
                content.style.opacity = '0';
                setTimeout(() => {
                    content.innerHTML = data.html;
                    content.style.opacity = '1';
                    content.style.transition = 'opacity 0.3s ease';
                }, 200);
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger m-4">
                        <i class="fas fa-exclamation-circle fa-2x mb-3 d-block text-center"></i>
                        <h5 class="alert-heading text-center">Error</h5>
                        <p class="mb-0 text-center">${data.message || 'Failed to load product details'}</p>
                        <hr>
                        <p class="mb-0 small text-center text-muted">Product ID: #${productId}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.error('Fetch Error:', error);
            
            let errorMessage = 'Error loading product details.';
            if (error.name === 'AbortError') {
                errorMessage = 'Request timed out. Please try again.';
            } else if (error.message.includes('404')) {
                errorMessage = 'AJAX handler not found. Please check the file path.';
            } else if (error.message.includes('Failed to fetch')) {
                errorMessage = 'Network error. Please check your connection.';
            }
            
            content.innerHTML = `
                <div class="alert alert-danger m-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block text-center"></i>
                    <h5 class="alert-heading text-center">Connection Error</h5>
                    <p class="mb-0 text-center">${errorMessage}</p>
                    <hr>
                    <p class="mb-0 small text-center text-muted">Technical: ${error.message}</p>
                    <button class="btn btn-sm btn-outline-danger mt-3 d-block mx-auto" onclick="showProductDetails(${productId})">
                        <i class="fas fa-redo me-2"></i>Retry
                    </button>
                </div>
            `;
        });
}

// Test the AJAX connection on page load
document.addEventListener('DOMContentLoaded', function() {
    // Test if AJAX file exists
    fetch('ajax/get_product_details.php?test=1')
        .then(response => {
            if (response.ok) {
                console.log(' AJAX handler is accessible');
            } else {
                console.error(' AJAX handler not found. Check file path.');
            }
        })
        .catch(error => {
            console.error(' Cannot connect to AJAX handler:', error);
        });
});

// Add keyboard shortcut (Ctrl+D) to show details of first product
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        const firstProductId = document.querySelector('[onclick*="showProductDetails"]');
        if (firstProductId) {
            const match = firstProductId.getAttribute('onclick').match(/\d+/);
            if (match) showProductDetails(parseInt(match[0]));
        }
    }
});

// Add click outside to close
document.addEventListener('click', function(e) {
    if (e.target.closest('.modal-backdrop')) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('productDetailsModal'));
        if (modal) modal.hide();
    }
});


// admin/vendors/products/low-stock.php - Add inside the <script> tag

// Quick stock update function
function quickStockUpdate(productId, newStock) {
    // Show loading indicator on the button
    const button = event?.target;
    if (button) {
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;
    }
    
    fetch('ajax/update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_stock&product_id=' + productId + '&stock=' + newStock
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message
            showToast('success', data.message);
            
            // Update UI elements
            updateStockDisplay(productId, data.new_stock, data.status_color);
            
            // Update the stock status badge if exists
            const stockBadge = document.querySelector(`#stock-badge-${productId}`);
            if (stockBadge) {
                stockBadge.className = `badge bg-${data.status_color}`;
                stockBadge.innerHTML = data.status_text + ' (' + data.new_stock + ' left)';
            }
            
            // Update the stock value in the table
            const stockCell = document.querySelector(`#stock-cell-${productId}`);
            if (stockCell) {
                stockCell.textContent = data.new_stock + ' units';
            }
        } else {
            showToast('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('danger', 'Failed to update stock. Please try again.');
    })
    .finally(() => {
        // Restore button
        if (button) {
            button.innerHTML = originalHtml;
            button.disabled = false;
        }
    });
}

// Helper function to update stock display
function updateStockDisplay(productId, newStock, statusColor) {
    // Update the row class based on stock level
    const row = document.querySelector(`#product-row-${productId}`);
    if (row) {
        row.className = '';
        if (newStock == 0) {
            row.classList.add('table-danger');
        } else if (newStock < 5) {
            row.classList.add('table-warning');
        }
    }
}

// Add quick action buttons to the table
function addQuickStockButtons() {
    document.querySelectorAll('.quick-stock-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.productId;
            const newStock = parseInt(this.dataset.stock);
            quickStockUpdate(productId, newStock);
        });
    });
}

// Call this when page loads
document.addEventListener('DOMContentLoaded', function() {
    addQuickStockButtons();
});
</script>
<style>
    /* Modal Animations */
.modal.fade .modal-dialog {
    transform: scale(0.8);
    transition: transform 0.3s ease;
}

.modal.show .modal-dialog {
    transform: scale(1);
}

.modal-content {
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.modal-header {
    border-bottom: none;
    padding: 1.5rem;
}

.modal-footer {
    border-top: none;
    padding: 1.5rem;
}

/* Product Image Container */
.product-image-container {
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.no-image-placeholder {
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

/* Progress Bars */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.6s ease;
}

/* Rating Stars */
.text-warning .fa-star,
.text-warning .fa-star-o {
    font-size: 1.1rem;
}

/* Card Hover Effects */
.card.border-0 {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card.border-0:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15) !important;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .product-image-container {
        margin-bottom: 1rem;
    }
    
    .col-md-3.col-6 {
        margin-bottom: 0.5rem;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>