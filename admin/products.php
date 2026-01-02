<?php
require_once './includes/config.php';
require_once './includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('/dashboard.php');
}

$page_title = 'Manage Products';
require_once './includes/header.php';

// Initialize variables
$products = [];
$total_products = 0;
$total_pages = 1;
$categories = [];
$stats = [];
$error = '';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Determine ORDER BY clause based on sort parameter
$order_by = 'created_at DESC'; // default
switch($sort) {
    case 'oldest':
        $order_by = 'created_at ASC';
        break;
    case 'price_low':
        $order_by = 'price ASC';
        break;
    case 'price_high':
        $order_by = 'price DESC';
        break;
    case 'stock_low':
        $order_by = 'stock ASC';
        break;
}

try {
    $db = getDB();
    
    // ========== BUILD FILTERS ==========
    $where_conditions = [];
    $query_params = [];
    
    // Search filter
    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $search_term = "%{$search}%";
        $query_params[] = $search_term; // name
        $query_params[] = $search_term; // description
        $query_params[] = $search_term; // category
    }
    
    // Category filter
    if (!empty($category)) {
        $where_conditions[] = "category = ?";
        $query_params[] = $category;
    }
    
    // Stock filter
    if (!empty($stock_filter)) {
        if ($stock_filter === 'low') {
            $where_conditions[] = "stock < 10 AND stock > 0";
        } elseif ($stock_filter === 'out') {
            $where_conditions[] = "stock = 0";
        } elseif ($stock_filter === 'in_stock') {
            $where_conditions[] = "stock > 0";
        }
    }
    
    // Build WHERE clause
    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }
    
    // ========== GET TOTAL COUNT ==========
    $count_sql = "SELECT COUNT(*) as total FROM products {$where_clause}";
    $count_stmt = $db->prepare($count_sql);
    
    if (!empty($query_params)) {
        $count_stmt->execute($query_params);
    } else {
        $count_stmt->execute();
    }
    
    $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
    $total_products = $count_result['total'] ?? 0;
    $total_pages = ceil($total_products / $limit);
    
    // ========== GET PRODUCTS WITH PAGINATION ==========
    $products_sql = "SELECT * FROM products {$where_clause} ORDER BY {$order_by} LIMIT :limit OFFSET :offset";
    $products_stmt = $db->prepare($products_sql);
    
    // Bind all parameters
    if (!empty($query_params)) {
        $param_index = 1;
        foreach ($query_params as $param) {
            $products_stmt->bindValue($param_index, $param);
            $param_index++;
        }
    }
    
    $products_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $products_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $products_stmt->execute();
    
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========== GET CATEGORIES FOR FILTER ==========
    $categories_stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // ========== GET STATISTICS ==========
    
    // Total products
    $stats['total_products'] = $total_products;
    
    // Low stock products
    $low_stmt = $db->query("SELECT COUNT(*) as low_stock FROM products WHERE stock < 10 AND stock > 0");
    $stats['low_stock'] = $low_stmt->fetch(PDO::FETCH_ASSOC)['low_stock'] ?? 0;
    
    // Out of stock products
    $out_stmt = $db->query("SELECT COUNT(*) as out_of_stock FROM products WHERE stock = 0");
    $stats['out_of_stock'] = $out_stmt->fetch(PDO::FETCH_ASSOC)['out_of_stock'] ?? 0;
    
    // Featured products
    $featured_stmt = $db->query("SELECT COUNT(*) as featured FROM products WHERE featured = 1");
    $stats['featured'] = $featured_stmt->fetch(PDO::FETCH_ASSOC)['featured'] ?? 0;
    
    // Total stock value
    $value_stmt = $db->query("SELECT COALESCE(SUM(price * stock), 0) as total_value FROM products");
    $value_result = $value_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_value'] = $value_result['total_value'] ?? 0;
    
} catch(PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    error_log("Products page error: " . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include './includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Manage Products</h1>
                <p class="text-muted mb-0">
                    <?php if ($total_products > 0): ?>
                        Showing <?php echo min($offset + 1, $total_products); ?>-<?php echo min($offset + count($products), $total_products); ?> of <?php echo $total_products; ?> products
                    <?php else: ?>
                        No products found
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="product-action.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i> Add Product
                </a>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                    <i class="fas fa-upload me-2"></i> Bulk Upload
                </button>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-database me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Products</h6>
                                <h3 class="mb-0"><?php echo $stats['total_products']; ?></h3>
                            </div>
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-box text-primary fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Low Stock</h6>
                                <h3 class="mb-0 text-warning"><?php echo $stats['low_stock']; ?></h3>
                            </div>
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-exclamation-triangle text-warning fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Out of Stock</h6>
                                <h3 class="mb-0 text-danger"><?php echo $stats['out_of_stock']; ?></h3>
                            </div>
                            <div class="avatar-sm bg-danger bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-times-circle text-danger fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Stock Value</h6>
                                <h3 class="mb-0 text-success">$<?php echo number_format($stats['total_value'], 2); ?></h3>
                            </div>
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-dollar-sign text-success fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0"><i class="fas fa-filter me-2"></i> Filter Products</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3" id="filterForm">
                    <!-- Search Input -->
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search products..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="col-md-3">
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $category === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Stock Filter -->
                    <div class="col-md-2">
                        <select name="stock" class="form-select">
                            <option value="">All Stock</option>
                            <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                        <?php if (!empty($search) || !empty($category) || !empty($stock_filter)): ?>
                        <a href="products.php" class="btn btn-outline-danger">
                            <i class="fas fa-times"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Active Filters Display -->
                <?php if (!empty($search) || !empty($category) || !empty($stock_filter)): ?>
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <small class="text-muted me-2">Active filters:</small>
                        
                        <?php if (!empty($search)): ?>
                        <span class="badge bg-primary">
                            Search: "<?php echo htmlspecialchars($search); ?>"
                            <a href="?<?php 
                                $params = $_GET;
                                unset($params['search']);
                                echo http_build_query($params);
                            ?>" class="text-white ms-1 text-decoration-none">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($category)): ?>
                        <span class="badge bg-info">
                            Category: <?php echo htmlspecialchars($category); ?>
                            <a href="?<?php 
                                $params = $_GET;
                                unset($params['category']);
                                echo http_build_query($params);
                            ?>" class="text-white ms-1 text-decoration-none">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                        <?php endif; ?>
                        
                        <?php if (!empty($stock_filter)): ?>
                        <?php 
                        $stock_labels = [
                            'in_stock' => 'In Stock',
                            'low' => 'Low Stock',
                            'out' => 'Out of Stock'
                        ];
                        ?>
                        <span class="badge bg-<?php echo $stock_filter === 'low' ? 'warning' : ($stock_filter === 'out' ? 'danger' : 'success'); ?>">
                            Stock: <?php echo $stock_labels[$stock_filter]; ?>
                            <a href="?<?php 
                                $params = $_GET;
                                unset($params['stock']);
                                echo http_build_query($params);
                            ?>" class="text-white ms-1 text-decoration-none">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-boxes me-2"></i> Products</h6>
                <div class="d-flex gap-2">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-sort me-1"></i> 
                            <?php 
                            $sort_labels = [
                                'newest' => 'Newest First',
                                'oldest' => 'Oldest First',
                                'price_low' => 'Price: Low to High',
                                'price_high' => 'Price: High to Low',
                                'stock_low' => 'Stock: Low to High'
                            ];
                            echo $sort_labels[$sort] ?? 'Sort By';
                            ?>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest', 'page' => 1])); ?>">Newest First</a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'oldest', 'page' => 1])); ?>">Oldest First</a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_low', 'page' => 1])); ?>">Price: Low to High</a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_high', 'page' => 1])); ?>">Price: High to Low</a></li>
                            <li><a class="dropdown-item" href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'stock_low', 'page' => 1])); ?>">Stock: Low to High</a></li>
                        </ul>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleView()">
                        <i class="fas fa-th-large" id="viewIcon"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <div class="text-muted mb-4">
                        <i class="fas fa-box-open fa-4x opacity-25"></i>
                    </div>
                    <h5>No Products Found</h5>
                    <p class="text-muted mb-4">
                        <?php if (!empty($search) || !empty($category) || !empty($stock_filter)): ?>
                        Try adjusting your filters or 
                        <?php endif; ?>
                        add your first product.
                    </p>
                    <a href="product-action.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Add New Product
                    </a>
                </div>
                <?php else: ?>
                <div class="row" id="productsGrid">
                    <?php foreach($products as $product): 
                        $stock_class = 'success';
                        $stock_text = 'In Stock';
                        
                        if ($product['stock'] == 0) {
                            $stock_class = 'danger';
                            $stock_text = 'Out of Stock';
                        } elseif ($product['stock'] < 10) {
                            $stock_class = 'warning';
                            $stock_text = 'Low Stock';
                        }
                        
                        // Image handling with fallback
                        $image_url = '../assets/images/products/' . htmlspecialchars($product['image'] ?? 'default.jpg');
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                        <div class="card product-card h-100">
                            <div class="position-relative">
                                <img src="<?php echo $image_url; ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     style="height: 200px; object-fit: cover;"
                                     onerror="this.src='../assets/images/products/default.jpg'">
                                
                                <!-- Product Badges -->
                                <div class="position-absolute top-0 start-0 p-2">
                                    <?php if ($product['featured']): ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-star me-1"></i> Featured
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="position-absolute top-0 end-0 p-2">
                                    <span class="badge bg-<?php echo $stock_class; ?>">
                                        <?php echo $stock_text; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <h6 class="card-title mb-2">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </h6>
                                <p class="card-text text-muted small mb-2">
                                    <?php 
                                    $description = $product['description'] ?? '';
                                    if (strlen($description) > 60) {
                                        echo htmlspecialchars(substr($description, 0, 60)) . '...';
                                    } else {
                                        echo htmlspecialchars($description);
                                    }
                                    ?>
                                </p>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <span class="text-primary fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                        <?php if (!empty($product['old_price']) && $product['old_price'] > 0): ?>
                                        <small class="text-muted text-decoration-line-through ms-2">
                                            $<?php echo number_format($product['old_price'], 2); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo !empty($product['category']) ? htmlspecialchars($product['category']) : 'Uncategorized'; ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-layer-group me-1"></i>
                                            Stock: <?php echo $product['stock']; ?>
                                        </small>
                                    </div>
                                    <small class="text-muted">
                                        <i class="far fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-white border-0 pt-0">
                                <div class="d-flex gap-2">
                                    <a href="product-action.php?action=view&id=<?php echo $product['id']; ?>" 
                                       class="btn btn-sm btn-outline-info flex-grow-1">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="product-action.php?action=edit&id=<?php echo $product['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary flex-grow-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger flex-grow-1"
                                            onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Previous Page -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php 
                                       $params = $_GET;
                                       $params['page'] = $page - 1;
                                       echo http_build_query($params);
                                   ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <!-- Page Numbers -->
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php 
                                    $params = $_GET;
                                    $params['page'] = 1;
                                    echo http_build_query($params);
                                ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php 
                                       $params = $_GET;
                                       $params['page'] = $i;
                                       echo http_build_query($params);
                                   ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php 
                                    $params = $_GET;
                                    $params['page'] = $total_pages;
                                    echo http_build_query($params);
                                ?>"><?php echo $total_pages; ?></a>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Next Page -->
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php 
                                       $params = $_GET;
                                       $params['page'] = $page + 1;
                                       echo http_build_query($params);
                                   ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Bulk Upload Modal -->
        <div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form id="bulkUploadForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="bulkUploadModalLabel">Bulk Upload Products</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="csvFile" class="form-label">Upload CSV File</label>
                                <input class="form-control" type="file" id="csvFile" name="csv_file" accept=".csv">
                                <div class="form-text">
                                    Download <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="text-primary">sample CSV template</a>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Import Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="update_existing" id="updateExisting">
                                    <label class="form-check-label" for="updateExisting">
                                        Update existing products (based on Name)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="skip_duplicates" id="skipDuplicates" checked>
                                    <label class="form-check-label" for="skipDuplicates">
                                        Skip duplicate products
                                    </label>
                                </div>
                            </div>
                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    CSV should contain columns: name, description, price, old_price, category, stock, featured (0/1)
                                </small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i> Upload
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<style>
.dashboard-container {
    display: flex;
    min-height: calc(100vh - 70px);
}

.sidebar {
    width: 250px;
    background: #1a1a2e;
    transition: all 0.3s;
    position: fixed;
    height: calc(100vh - 70px);
    overflow-y: auto;
    z-index: 1000;
}

.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 20px;
    background: #f8f9fa;
    /* color: #f8f9fa; */
    min-height: calc(100vh - 70px);
}

.sidebar-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1001;
    display: none;
}

@media (max-width: 992px) {
    .sidebar {
        margin-left: -250px;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .sidebar.active {
        margin-left: 0;
    }
    
    .sidebar-toggle {
        display: block;
    }
}
.product-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}

.product-card .card-img-top {
    border-radius: 0;
}

.product-card .card-footer {
    border-top: 1px solid #f0f0f0;
    background: transparent;
}

.badge {
    font-weight: 500;
    padding: 5px 10px;
    border-radius: 20px;
}

.card-text {
    min-height: 40px;
}

.pagination .page-link {
    border-radius: 6px;
    margin: 0 3px;
    border: 1px solid #dee2e6;
}

.page-item.active .page-link {
    background-color: #4361ee;
    border-color: #4361ee;
    color: white;
}

.avatar-sm {
    width: 40px;
    height: 40px;
}

.table-view {
    display: none;
}

.list-group-item:hover {
    background-color: #f8f9fa;
}

/* For product image fallback */
.product-card img {
    background-color: #f8f9fa;
}
</style>

<script>
let isGridView = true;

function toggleView() {
    const grid = document.getElementById('productsGrid');
    const icon = document.getElementById('viewIcon');
    
    if (isGridView) {
        // Switch to table view
        grid.classList.remove('row');
        grid.classList.add('list-group', 'table-view');
        icon.classList.remove('fa-th-large');
        icon.classList.add('fa-list');
        
        // Convert cards to list items
        const cards = grid.querySelectorAll('.product-card');
        cards.forEach(card => {
            card.classList.remove('card');
            card.classList.add('list-group-item', 'd-flex', 'align-items-center');
            card.style.border = 'none';
            card.style.padding = '15px 0';
            card.style.borderBottom = '1px solid #eee';
        });
    } else {
        // Switch back to grid view
        grid.classList.remove('list-group', 'table-view');
        grid.classList.add('row');
        icon.classList.remove('fa-list');
        icon.classList.add('fa-th-large');
        
        // Convert list items back to cards
        const items = grid.querySelectorAll('.list-group-item');
        items.forEach(item => {
            item.classList.remove('list-group-item', 'd-flex', 'align-items-center');
            item.classList.add('product-card', 'card');
            item.style.padding = '';
            item.style.borderBottom = '';
        });
    }
    
    isGridView = !isGridView;
}

function confirmDelete(productId, productName) {
    if (confirm(`Are you sure you want to delete product "${productName}"?\n\nThis action cannot be undone and will permanently remove the product.`)) {
        window.location.href = `product-action.php?action=delete&id=${productId}`;
    }
}

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    const filterSelects = filterForm.querySelectorAll('select[name="category"], select[name="stock"]');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            // Remove page parameter when filter changes
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            window.history.replaceState({}, '', 'products.php?' + params.toString());
            
            // Submit form
            filterForm.submit();
        });
    });
    
    // Quick search on Enter key
    const searchInput = filterForm.querySelector('input[name="search"]');
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            filterForm.submit();
        }
    });
    
    // Handle sort dropdown
    document.querySelectorAll('.dropdown-menu .dropdown-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = this.getAttribute('href');
        });
    });
});
</script>

<?php require_once './includes/footer.php'; ?>