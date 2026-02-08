<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['warning'] = 'Your vendor account needs to be approved to manage products.';
        redirect(SITE_URL . 'vendor/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    redirect(SITE_URL . 'vendor/dashboard.php');
}

$page_title = 'Pending Products';
require_once '../../includes/header.php';

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'created_at_desc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Initialize variables
$pending_products = [];
$approved_products_count = 0;
$rejected_products_count = 0;
$pending_products_count = 0; // New variable for pending count only
$total_all_products = 0;
$total_pages = 1;
$categories = [];
$vendor_id = $_SESSION['user_id'];

// Get all product counts for statistics
try {
    $db = getDB();
    
    // Get counts for different statuses
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'approved'");
    $stmt->execute([$vendor_id]);
    $approved_products_count = (int)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'rejected'");
    $stmt->execute([$vendor_id]);
    $rejected_products_count = (int)$stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'pending'");
    $stmt->execute([$vendor_id]);
    $pending_products_count = (int)$stmt->fetchColumn(); // Pending count only
    
    $total_all_products = $approved_products_count + $rejected_products_count + $pending_products_count;
    
    // Debug: Show counts
    // echo "<!-- Debug: Approved: {$approved_products_count}, Rejected: {$rejected_products_count}, Pending: {$pending_products_count}, Total: {$total_all_products} -->";
    
    // Get categories for filter dropdown
    $stmt = $db->prepare("
        SELECT DISTINCT p.category, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category = c.slug 
        WHERE p.vendor_id = ? 
        ORDER BY c.name ASC
    ");
    $stmt->execute([$vendor_id]);
    $categories = $stmt->fetchAll();
    
    // Build base query for pending products
    $query = "FROM products p WHERE p.vendor_id = ? AND p.approved_status = 'pending'";
    $params = [$vendor_id];
    
    // Apply search filter
    if (!empty($search)) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Apply category filter
    if (!empty($category)) {
        $query .= " AND p.category = ?";
        $params[] = $category;
    }
    
    // Get total pending count for pagination
    $count_query = "SELECT COUNT(*) " . $query;
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_products_for_pagination = $stmt->fetchColumn(); // This is for pagination only
    
    // Calculate pagination
    $total_pages = ceil($total_products_for_pagination / $limit);
    $total_pages = max(1, $total_pages);
    $page = min($page, $total_pages);
    
    // Apply sorting
    $order_by = "ORDER BY ";
    switch ($sort) {
        case 'name_asc':
            $order_by .= "p.name ASC";
            break;
        case 'name_desc':
            $order_by .= "p.name DESC";
            break;
        case 'price_asc':
            $order_by .= "p.price ASC";
            break;
        case 'price_desc':
            $order_by .= "p.price DESC";
            break;
        case 'created_at_asc':
            $order_by .= "p.created_at ASC";
            break;
        case 'created_at_desc':
        default:
            $order_by .= "p.created_at DESC";
            break;
    }
    
    // Get pending products with pagination
    $products_query = "
        SELECT p.*, 
               c.name as category_name,
               TIMESTAMPDIFF(HOUR, p.created_at, NOW()) as hours_pending
        FROM products p 
        LEFT JOIN categories c ON p.category = c.slug 
        WHERE p.vendor_id = ? AND p.approved_status = 'pending' 
    ";
    
    // Add filters to products query
    if (!empty($search)) {
        $products_query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    }
    if (!empty($category)) {
        $products_query .= " AND p.category = ?";
    }
    
    $products_query .= " {$order_by} LIMIT {$limit} OFFSET {$offset}";
    
    $stmt = $db->prepare($products_query);
    
    // Bind parameters
    $stmt->bindValue(1, $vendor_id, PDO::PARAM_INT);
    $param_index = 2;
    if (!empty($search)) {
        $stmt->bindValue($param_index++, "%{$search}%", PDO::PARAM_STR);
        $stmt->bindValue($param_index++, "%{$search}%", PDO::PARAM_STR);
    }
    if (!empty($category)) {
        $stmt->bindValue($param_index, $category, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $pending_products = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading pending products: ' . $e->getMessage();
    error_log("Pending Products Error: " . $e->getMessage());
}

// Log activity
logUserActivity($_SESSION['user_id'], 'view_pending_products', 'Viewed pending products list');
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php    
        include_once '../../includes/vendor-sidebar.php';    
    ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Pending Products</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-clock me-1 text-warning"></i>
                        Products awaiting admin approval
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-outline-primary">
                        <i class="fas fa-boxes me-2"></i> All Products
                    </a>
                    <a href="add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add New
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Pending Products</h6>
                                <h3 class="mb-0 text-warning"><?php echo $pending_products_count; ?></h3>
                            </div>
                            <div class="stats-icon warning">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <small class="text-muted">Awaiting admin approval</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Approved Products</h6>
                                <h3 class="mb-0 text-success"><?php echo $approved_products_count; ?></h3>
                            </div>
                            <div class="stats-icon success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <small class="text-muted">Live on marketplace</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Rejected Products</h6>
                                <h3 class="mb-0 text-danger"><?php echo $rejected_products_count; ?></h3>
                            </div>
                            <div class="stats-icon danger">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                        <small class="text-muted">Needs revision</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Products</h6>
                                <h3 class="mb-0 text-primary"><?php echo $total_all_products; ?></h3>
                            </div>
                            <div class="stats-icon primary">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                        <small class="text-muted">All products</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters & Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label small fw-bold">Search Products</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="search" 
                                   name="search" 
                                   placeholder="Search by product name or description..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="category" class="form-label small fw-bold">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['category']; ?>" 
                                    <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['category_name'] ?? ucfirst($cat['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label for="sort" class="form-label small fw-bold">Sort By</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="created_at_desc" <?php echo $sort == 'created_at_desc' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="created_at_asc" <?php echo $sort == 'created_at_asc' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                            <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter me-2"></i> Filter
                            </button>
                            <a href="pending.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
                
                <!-- Results Summary -->
                <?php if (!empty($search) || !empty($category) || (!empty($sort) && $sort != 'created_at_desc')): ?>
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                Filter Results: 
                                <?php if (!empty($search)): ?>
                                    <span class="badge bg-info">Search: "<?php echo htmlspecialchars($search); ?>"</span>
                                <?php endif; ?>
                                <?php if (!empty($category)): ?>
                                    <span class="badge bg-info ms-1">Category: <?php echo htmlspecialchars($category); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($sort) && $sort != 'created_at_desc'): ?>
                                    <span class="badge bg-info ms-1">Sorted: <?php 
                                        $sort_labels = [
                                            'created_at_asc' => 'Oldest First',
                                            'name_asc' => 'Name A-Z',
                                            'name_desc' => 'Name Z-A',
                                            'price_asc' => 'Price: Low to High',
                                            'price_desc' => 'Price: High to Low'
                                        ];
                                        echo $sort_labels[$sort] ?? ucfirst(str_replace('_', ' ', $sort));
                                    ?></span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <div>
                            <small class="text-muted">
                                <?php if (count($pending_products) > 0): ?>
                                    Showing <strong><?php echo min($offset + 1, $total_products_for_pagination); ?></strong> to 
                                    <strong><?php echo min($offset + $limit, $total_products_for_pagination); ?></strong> of 
                                    <strong><?php echo $total_products_for_pagination; ?></strong> pending products
                                <?php else: ?>
                                    <strong>0 pending products</strong> found
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Pending Products Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Pending Products List</h5>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="refreshPendingList()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                    <span class="badge bg-warning">
                        <i class="fas fa-clock me-1"></i> <?php echo $pending_products_count; ?> Pending
                    </span>
                </div>
            </div>
            
            <div class="card-body">
                <?php if ($pending_products_count == 0): ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-check-circle fa-4x text-success"></i>
                        </div>
                        <h4 class="mb-3 text-success">No Pending Products!</h4>
                        <p class="text-muted mb-4">
                            Great! All your products have been reviewed and approved.
                        </p>
                        <?php if ($approved_products_count > 0): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-info-circle me-2"></i>
                                You have <strong><?php echo $approved_products_count; ?> approved products</strong> live on the marketplace.
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-center gap-2 mt-4">
                            <a href="add.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Add New Product
                            </a>
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="fas fa-boxes me-2"></i> View All Products
                            </a>
                        </div>
                    </div>
                <?php elseif (empty($pending_products)): ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-filter fa-4x text-warning opacity-25"></i>
                        </div>
                        <h4 class="mb-3 text-muted">No Products Match Filters</h4>
                        <p class="text-muted mb-4">
                            Try changing your search or filter criteria.
                        </p>
                        <a href="pending.php" class="btn btn-outline-primary">
                            <i class="fas fa-redo me-2"></i> Clear Filters
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
                                    <th>Submitted</th>
                                    <th>Time Pending</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="pendingProductsTable">
                                <?php foreach($pending_products as $product): ?>
                                <tr id="product-<?php echo $product['id']; ?>" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    class="pending-product-item">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($product['image'])): ?>
                                                <img src="<?php echo SITE_URL . 'assets/images/products/' . $product['image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                     class="rounded me-3" 
                                                     style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="fas fa-box text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo substr(strip_tags($product['description']), 0, 50) . '...'; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $product['category_name'] ?? ucfirst($product['category']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <strong class="text-primary">$<?php echo number_format($product['price'], 2); ?></strong>
                                            <?php if ($product['old_price']): ?>
                                                <small class="text-muted text-decoration-line-through">
                                                    $<?php echo number_format($product['old_price'], 2); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($product['stock'] > 10): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check me-1"></i> <?php echo $product['stock']; ?>
                                            </span>
                                        <?php elseif ($product['stock'] > 0): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-exclamation-triangle me-1"></i> <?php echo $product['stock']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times me-1"></i> Out of Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <small class="text-muted"><?php echo date('d M Y', strtotime($product['created_at'])); ?></small>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($product['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $hours_pending = $product['hours_pending'] ?? 0;
                                        $time_text = '';
                                        $time_class = 'bg-warning';
                                        
                                        if ($hours_pending < 24) {
                                            $time_text = $hours_pending . ' hours';
                                        } elseif ($hours_pending < 168) { // 7 days
                                            $days = floor($hours_pending / 24);
                                            $time_text = $days . ' day' . ($days > 1 ? 's' : '');
                                            $time_class = 'bg-warning';
                                        } else {
                                            $weeks = floor($hours_pending / 168);
                                            $time_text = $weeks . ' week' . ($weeks > 1 ? 's' : '');
                                            $time_class = 'bg-danger';
                                        }
                                        ?>
                                        <span class="badge <?php echo $time_class; ?>">
                                            <i class="fas fa-clock me-1"></i> <?php echo $time_text; ?>
                                        </span>
                                        <small class="d-block text-muted mt-1">Pending review</small>
                                    </td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-primary btn-sm dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="view.php?id=<?php echo $product['id']; ?>">
                                                        <i class="fas fa-eye me-2"></i> View Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="edit.php?id=<?php echo $product['id']; ?>">
                                                        <i class="fas fa-edit me-2"></i> Edit
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="checkProductStatus(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')">
                                                        <i class="fas fa-sync me-2"></i> Check Status
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="javascript:void(0)" 
                                                       onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')">
                                                        <i class="fas fa-trash me-2"></i> Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Approvals/Rejections Section -->
        <?php 
        try {
            $db = getDB();
            
            // Get recently approved products (last 24 hours)
            $stmt = $db->prepare("
                SELECT p.*, c.name as category_name, 
                       p.updated_at as status_changed_at
                FROM products p 
                LEFT JOIN categories c ON p.category = c.slug 
                WHERE p.vendor_id = ? 
                AND p.approved_status IN ('approved', 'rejected') 
                AND p.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY p.updated_at DESC 
                LIMIT 5
            ");
            $stmt->execute([$vendor_id]);
            $recent_status_changes = $stmt->fetchAll();
            
            if (!empty($recent_status_changes)):
        ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Recent Status Updates</h5>
                <small class="text-muted">Last 24 hours</small>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach($recent_status_changes as $change): ?>
                    <div class="list-group-item border-0 px-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <?php if (!empty($change['image'])): ?>
                                    <img src="<?php echo SITE_URL . 'assets/images/products/' . $change['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($change['name']); ?>" 
                                         class="rounded me-3" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center me-3" 
                                         style="width: 40px; height: 40px;">
                                        <i class="fas fa-box text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($change['name']); ?></h6>
                                    <small class="text-muted">
                                        <?php echo $change['category_name'] ?? ucfirst($change['category']); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="text-end">
                                <span class="badge <?php echo $change['approved_status'] == 'approved' ? 'bg-success' : 'bg-danger'; ?>">
                                    <i class="fas fa-<?php echo $change['approved_status'] == 'approved' ? 'check' : 'times'; ?> me-1"></i>
                                    <?php echo ucfirst($change['approved_status']); ?>
                                </span>
                                <small class="d-block text-muted mt-1">
                                    <?php echo date('h:i A', strtotime($change['status_changed_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php 
            endif;
        } catch(PDOException $e) {
            // Silently continue if there's an error
        }
        ?>
        
        <!-- Help Card -->
        <?php if ($pending_products_count > 0): ?>
        <div class="card border-0 shadow-sm mt-4 border-start border-5 border-info">
            <div class="card-body">
                <div class="d-flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-question-circle fa-2x text-info"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h5 class="fw-bold mb-2">Need Help with Product Approval?</h5>
                        <p class="text-muted mb-2">
                            Products typically take 24-48 hours to be reviewed by our admin team. 
                            Make sure your products follow our guidelines for faster approval.
                        </p>
                        <div class="d-flex gap-2">
                            <a href="<?php echo SITE_URL; ?>vendor/help/guidelines.php" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-book me-2"></i> View Guidelines
                            </a>
                            <a href="<?php echo SITE_URL; ?>vendor/help/support.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-headset me-2"></i> Contact Support
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
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

.stats-icon.warning {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.stats-icon.success {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
}

.stats-icon.danger {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.stats-icon.primary {
    background: rgba(67, 97, 238, 0.1);
    color: #4361ee;
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

.dropdown-menu {
    min-width: 180px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border: none;
}

.dropdown-item {
    padding: 8px 15px;
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

.pending-product-item.removing {
    animation: fadeOut 0.5s ease forwards;
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; height: 0; padding: 0; margin: 0; overflow: hidden; }
}

.status-update-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1050;
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<script>
function confirmDelete(productId, productName) {
    if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
        window.location.href = `delete.php?id=${productId}`;
    }
}

function refreshPendingList() {
    const refreshBtn = event.target.closest('button');
    const originalHTML = refreshBtn.innerHTML;
    
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Reload the page after a short delay
    setTimeout(() => {
        location.reload();
    }, 1000);
}

function checkProductStatus(productId, productName) {
    const btn = event.target.closest('a');
    const originalHTML = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Checking...';
    btn.disabled = true;
    
    // Make AJAX request to check product status
    fetch('check-status.php?id=' + productId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.status !== 'pending') {
                    // Remove product from list with animation
                    const productRow = document.getElementById('product-' + productId);
                    if (productRow) {
                        productRow.classList.add('removing');
                        
                        // Show status update alert
                        showStatusAlert(productName, data.status);
                        
                        // Remove row after animation
                        setTimeout(() => {
                            productRow.remove();
                            
                            // Update counters
                            updateProductCounters(data.counts);
                            
                            // If no more products, reload page
                            const remainingProducts = document.querySelectorAll('.pending-product-item').length;
                            if (remainingProducts === 0) {
                                setTimeout(() => location.reload(), 1000);
                            }
                        }, 500);
                    }
                    
                    // Show status message
                    alert(`Product "${productName}" has been ${data.status} by admin.`);
                } else {
                    alert(`Product "${productName}" is still pending review.`);
                }
            } else {
                alert('Error checking product status: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error checking product status. Please try again.');
        })
        .finally(() => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        });
}

function showStatusAlert(productName, status) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${status === 'approved' ? 'success' : 'danger'} status-update-alert`;
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${status === 'approved' ? 'check-circle' : 'times-circle'} me-2 fa-lg"></i>
            <div>
                <strong>${status === 'approved' ? 'Approved!' : 'Rejected'}</strong>
                <div class="small">"${productName}" has been ${status} by admin.</div>
            </div>
        </div>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Remove alert after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

function updateProductCounters(counts) {
    // Update pending count badge
    const pendingBadge = document.querySelector('.badge.bg-warning');
    if (pendingBadge && counts.pending !== undefined) {
        pendingBadge.innerHTML = `<i class="fas fa-clock me-1"></i> ${counts.pending} Pending`;
    }
    
    // Update stats cards
    if (counts.pending !== undefined) {
        const pendingCard = document.querySelector('.card:first-child h3.text-warning');
        if (pendingCard) pendingCard.textContent = counts.pending;
    }
    
    if (counts.approved !== undefined) {
        const approvedCard = document.querySelector('.card:nth-child(2) h3.text-success');
        if (approvedCard) approvedCard.textContent = counts.approved;
    }
    
    if (counts.rejected !== undefined) {
        const rejectedCard = document.querySelector('.card:nth-child(3) h3.text-danger');
        if (rejectedCard) rejectedCard.textContent = counts.rejected;
    }
    
    if (counts.total !== undefined) {
        const totalCard = document.querySelector('.card:last-child h3.text-primary');
        if (totalCard) totalCard.textContent = counts.total;
    }
}

// Auto-refresh every 30 seconds to check for status updates
setInterval(() => {
    // Only refresh if page is visible and not being interacted with
    if (!document.hidden) {
        fetch('check-status.php?vendor=<?php echo $vendor_id; ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.has_changes) {
                    // Show notification
                    const notification = document.createElement('div');
                    notification.className = 'alert alert-info status-update-alert';
                    notification.innerHTML = `
                        <div class="d-flex align-items-center">
                            <i class="fas fa-bell me-2"></i>
                            <div>
                                <strong>Status Update</strong>
                                <div class="small">Some product statuses have been updated.</div>
                            </div>
                            <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                        </div>
                    `;
                    
                    document.body.appendChild(notification);
                    
                    // Auto-remove after 10 seconds
                    setTimeout(() => notification.remove(), 10000);
                }
            })
            .catch(error => console.error('Auto-refresh error:', error));
    }
}, 30000); // 30 seconds

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert:not(.status-update-alert)');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(tooltip) {
        return new bootstrap.Tooltip(tooltip);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>