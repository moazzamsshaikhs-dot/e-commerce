<?php
// admin/products/products.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Manage Products';
require_once '../includes/header.php';

// Initialize variables
$products = [];
$total_products = 0;
$total_pages = 1;
$categories = [];
$stats = [
    'total_products' => 0,
    'featured' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'total_value' => 0,
    'avg_price' => 0,
    'pending_approval' => 0,
    'approved' => 0,
    'rejected' => 0
];
$error = '';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
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
    case 'name_asc':
        $order_by = 'name ASC';
        break;
    case 'name_desc':
        $order_by = 'name DESC';
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
    
    // Status filter (Approval)
    if (!empty($status_filter) && $status_filter !== 'all') {
        $where_conditions[] = "approved_status = ?";
        $query_params[] = $status_filter;
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
    $total_products = $count_result ? (int)$count_result['total'] : 0;
    $total_pages = ceil($total_products / $limit);
    
    // ========== GET PRODUCTS WITH PAGINATION ==========
    $products_sql = "SELECT * FROM products {$where_clause} ORDER BY 
                     CASE approved_status 
                         WHEN 'pending' THEN 1
                         WHEN 'approved' THEN 2
                         WHEN 'rejected' THEN 3
                     END, {$order_by} LIMIT {$limit} OFFSET {$offset}";
    $products_stmt = $db->prepare($products_sql);
    
    if (!empty($query_params)) {
        $products_stmt->execute($query_params);
    } else {
        $products_stmt->execute();
    }
    
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========== GET CATEGORIES FOR FILTER ==========
    $categories_stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // ========== GET STATISTICS ==========
    
    // Total products
    $stats['total_products'] = $total_products;
    
    // Featured products
    $featured_stmt = $db->query("SELECT COUNT(*) as featured FROM products WHERE featured = 1");
    $featured_result = $featured_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['featured'] = $featured_result ? (int)$featured_result['featured'] : 0;
    
    // Low stock products
    $low_stmt = $db->query("SELECT COUNT(*) as low_stock FROM products WHERE stock < 10 AND stock > 0");
    $low_result = $low_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['low_stock'] = $low_result ? (int)$low_result['low_stock'] : 0;
    
    // Out of stock products
    $out_stmt = $db->query("SELECT COUNT(*) as out_of_stock FROM products WHERE stock = 0");
    $out_result = $out_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['out_of_stock'] = $out_result ? (int)$out_result['out_of_stock'] : 0;
    
    // Total stock value
    $value_stmt = $db->query("SELECT COALESCE(SUM(price * stock), 0) as total_value FROM products");
    $value_result = $value_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_value'] = $value_result ? (float)$value_result['total_value'] : 0;
    
    // Average price
    $avg_stmt = $db->query("SELECT COALESCE(AVG(price), 0) as avg_price FROM products");
    $avg_result = $avg_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['avg_price'] = $avg_result ? (float)$avg_result['avg_price'] : 0;
    
    // Approval stats
    $pending_stmt = $db->query("SELECT COUNT(*) as pending FROM products WHERE approved_status = 'pending'");
    $pending_result = $pending_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['pending_approval'] = $pending_result ? (int)$pending_result['pending'] : 0;
    
    $approved_stmt = $db->query("SELECT COUNT(*) as approved FROM products WHERE approved_status = 'approved'");
    $approved_result = $approved_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['approved'] = $approved_result ? (int)$approved_result['approved'] : 0;
    
    $rejected_stmt = $db->query("SELECT COUNT(*) as rejected FROM products WHERE approved_status = 'rejected'");
    $rejected_result = $rejected_stmt->fetch(PDO::FETCH_ASSOC);
    $stats['rejected'] = $rejected_result ? (int)$rejected_result['rejected'] : 0;
    
} catch(PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
    error_log("Products page error: " . $e->getMessage());
}

// Ensure all stats are properly typed
$stats['total_products'] = (int)$stats['total_products'];
$stats['featured'] = (int)$stats['featured'];
$stats['low_stock'] = (int)$stats['low_stock'];
$stats['out_of_stock'] = (int)$stats['out_of_stock'];
$stats['total_value'] = (float)$stats['total_value'];
$stats['avg_price'] = (float)$stats['avg_price'];
$stats['pending_approval'] = (int)$stats['pending_approval'];
$stats['approved'] = (int)$stats['approved'];
$stats['rejected'] = (int)$stats['rejected'];
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

/* Header */
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
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
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

.stat-card.primary { border-left-color: var(--primary); }
.stat-card.success { border-left-color: var(--success); }
.stat-card.warning { border-left-color: var(--warning); }
.stat-card.danger { border-left-color: var(--danger); }
.stat-card.info { border-left-color: var(--info); }
.stat-card.pending { border-left-color: #ffb703; }
.stat-card.approved { border-left-color: #06d6a0; }
.stat-card.rejected { border-left-color: #ef476f; }

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
    font-size: 24px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 13px;
}

.stat-sub {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.filter-card .form-control,
.filter-card .form-select {
    border-radius: 12px;
    border: 2px solid #edf2f9;
    padding: 10px 15px;
    transition: all 0.3s ease;
}

.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.filter-card .btn-filter {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
}

.filter-card .btn-filter:hover {
    background: #3651c4;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.filter-card .btn-reset {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    width: 100%;
}

.filter-card .btn-reset:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* Products Card */
.products-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.card-header {
    padding: 20px 25px;
    border-bottom: 1px solid #edf2f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.card-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

/* Product Card */
.product-card {
    transition: all 0.3s ease;
    border: 1px solid #edf2f9;
    border-radius: 15px;
    overflow: hidden;
    height: 100%;
    background: white;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(67, 97, 238, 0.15) !important;
}

.product-image {
    position: relative;
    height: 180px;
    overflow: hidden;
    background: #f8f9fa;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.product-badges {
    position: absolute;
    top: 10px;
    left: 10px;
    right: 10px;
    display: flex;
    justify-content: space-between;
    pointer-events: none;
    z-index: 2;
}

.badge-custom {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-featured { background: rgba(255, 193, 7, 0.9); color: #000; }
.badge-new { background: rgba(67, 97, 238, 0.9); color: white; }
.badge-low { background: rgba(255, 183, 3, 0.9); color: #000; }
.badge-out { background: rgba(239, 71, 111, 0.9); color: white; }
.badge-in { background: rgba(6, 214, 160, 0.9); color: white; }
.badge-pending { background: rgba(255, 183, 3, 0.9); color: #000; }
.badge-approved { background: rgba(6, 214, 160, 0.9); color: white; }
.badge-rejected { background: rgba(239, 71, 111, 0.9); color: white; }

.product-body {
    padding: 15px;
}

.product-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 42px;
}

.product-category {
    font-size: 11px;
    color: #6c757d;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.product-price {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.current-price {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
}

.old-price {
    font-size: 12px;
    color: #6c757d;
    text-decoration: line-through;
}

.product-stock {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
}

.stock-bar {
    flex: 1;
    height: 5px;
    background: #edf2f9;
    border-radius: 3px;
    overflow: hidden;
}

.stock-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.stock-fill.high { background: var(--success); }
.stock-fill.medium { background: var(--warning); }
.stock-fill.low { background: var(--danger); }

.stock-text {
    font-size: 11px;
    font-weight: 500;
}

.product-footer {
    padding: 12px 15px;
    border-top: 1px solid #edf2f9;
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.btn-action {
    flex: 1;
    min-width: 35px;
    padding: 6px 8px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    text-decoration: none;
}

.btn-view { background: var(--info); color: white; }
.btn-edit { background: var(--primary); color: white; }
.btn-delete { background: var(--danger); color: white; }
.btn-approve { background: var(--success); color: white; }
.btn-reject { background: var(--danger); color: white; }

.btn-action:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
    color: white;
}

/* Low Stock Alert Card */
.alert-card {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border-left: 4px solid var(--warning);
    border-radius: 15px;
    padding: 15px 20px;
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 183, 3, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(255, 183, 3, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 183, 3, 0); }
}

.alert-card .alert-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: rgba(255, 183, 3, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--warning);
}

.alert-card .alert-content {
    flex: 1;
}

.alert-card .alert-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.alert-card .alert-text {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 0;
}

.alert-card .alert-action {
    background: var(--warning);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
}

.alert-card .alert-action:hover {
    background: #e6a800;
    transform: translateY(-2px);
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 60px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h5 {
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 20px;
}

/* Pagination */
.pagination {
    gap: 5px;
    margin: 0;
}

.page-link {
    border: none;
    border-radius: 10px !important;
    padding: 8px 14px;
    color: var(--dark);
    font-weight: 500;
    transition: all 0.3s ease;
    background: white;
    border: 1px solid #edf2f9;
}

.page-link:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    border-color: var(--primary);
}

.page-item.active .page-link {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.page-item.disabled .page-link {
    background: #f8f9fa;
    color: #6c757d;
    border-color: #edf2f9;
}

/* Sort Dropdown */
.sort-dropdown .dropdown-toggle {
    background: white;
    border: 2px solid #edf2f9;
    border-radius: 12px;
    padding: 8px 16px;
    color: var(--dark);
    font-weight: 500;
}

.sort-dropdown .dropdown-toggle:hover {
    border-color: var(--primary);
}

.sort-dropdown .dropdown-menu {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
}

.sort-dropdown .dropdown-item {
    border-radius: 10px;
    padding: 8px 15px;
    transition: all 0.3s ease;
}

.sort-dropdown .dropdown-item:hover {
    background: var(--primary);
    color: white;
}

.sort-dropdown .dropdown-item.active {
    background: var(--primary);
    color: white;
}

/* View Toggle */
.view-toggle {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: white;
    border: 2px solid #edf2f9;
    color: var(--dark);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.view-toggle:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Error Alert */
.error-alert {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    border-left: 4px solid var(--danger);
}

/* Active Filters */
.active-filters {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #edf2f9;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filter-badge {
    background: #f8f9fa;
    border-radius: 30px;
    padding: 5px 12px;
    font-size: 13px;
    color: var(--dark);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.filter-badge .remove-filter {
    color: #6c757d;
    transition: all 0.3s ease;
}

.filter-badge .remove-filter:hover {
    color: var(--danger);
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-in {
    animation: slideIn 0.5s ease forwards;
}

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }

/* Responsive */
@media (max-width: 768px) {
    .products-container {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .product-footer {
        flex-direction: column;
    }
    
    .alert-card {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<div class="products-container">
    <!-- Page Header -->
    <div class="page-header animate-slide-in">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h2 fw-bold mb-1">
                    <i class="fas fa-boxes me-2 text-primary"></i>
                    Product Management
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-box me-2"></i>
                    Total <strong><?php echo number_format($stats['total_products']); ?></strong> products in catalog
                    <?php if ($total_products > 0): ?>
                        • Showing page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="../dashboard.php" class="btn btn-outline-primary btn-lg">
                    <i class="fa fa-home"></i>
                    Back
                </a>
                <a href="product-bulk-upload.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-upload me-2"></i>
                    Bulk Upload
                </a>
                <a href="product-action.php?action=add" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add Product
                </a>
            </div>
        </div>
    </div>

    <!-- Low Stock Alert Card -->
    <?php if ($stats['low_stock'] > 0 || $stats['out_of_stock'] > 0): ?>
    <div class="alert-card animate-slide-in delay-1">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="alert-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">
                    <i class="fas fa-box-open me-2"></i>
                    Stock Alert!
                </div>
                <div class="alert-text">
                    <strong><?php echo $stats['low_stock']; ?></strong> products are running low on stock 
                    (less than 10 units) and <strong><?php echo $stats['out_of_stock']; ?></strong> products are out of stock.
                    Please review and restock these items.
                </div>
            </div>
            <a href="?stock=low" class="alert-action">
                <i class="fas fa-eye me-2"></i> View Low Stock
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
        <div class="error-alert animate-slide-in delay-1">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-15 mb-4 animate-slide-in delay-1" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fa-2x me-3"></i>
                <div>
                    <?php echo $_SESSION['success']; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid animate-slide-in delay-1">
        <div class="stat-card primary">
            <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                <i class="fas fa-box text-primary"></i>
            </div>
            <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
            <div class="stat-label">Total Products</div>
            <div class="stat-sub">
                <i class="fas fa-star text-warning me-1"></i>
                <?php echo $stats['featured']; ?> Featured
            </div>
        </div>
        
        <div class="stat-card pending">
            <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                <i class="fas fa-clock text-warning"></i>
            </div>
            <div class="stat-value"><?php echo $stats['pending_approval']; ?></div>
            <div class="stat-label">Pending Approval</div>
            <div class="stat-sub">Awaiting review</div>
        </div>
        
        <div class="stat-card approved">
            <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                <i class="fas fa-check-circle text-success"></i>
            </div>
            <div class="stat-value"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Approved</div>
            <div class="stat-sub">Visible to customers</div>
        </div>
        
        <div class="stat-card rejected">
            <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                <i class="fas fa-times-circle text-danger"></i>
            </div>
            <div class="stat-value"><?php echo $stats['rejected']; ?></div>
            <div class="stat-label">Rejected</div>
            <div class="stat-sub">Not visible</div>
        </div>
        
        <div class="stat-card warning">
            <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                <i class="fas fa-exclamation-triangle text-warning"></i>
            </div>
            <div class="stat-value"><?php echo $stats['low_stock']; ?></div>
            <div class="stat-label">Low Stock</div>
            <div class="stat-sub">Less than 10 units</div>
        </div>
        
        <div class="stat-card danger">
            <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                <i class="fas fa-times-circle text-danger"></i>
            </div>
            <div class="stat-value"><?php echo $stats['out_of_stock']; ?></div>
            <div class="stat-label">Out of Stock</div>
            <div class="stat-sub">Need restocking</div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="filter-card animate-slide-in delay-2">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search by name, description or category..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="col-md-2">
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
            
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="all">All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <select name="stock" class="form-select">
                    <option value="">All Stock</option>
                    <option value="in_stock" <?php echo $stock_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock (&lt;10)</option>
                    <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <?php if (!empty($search) || !empty($category) || !empty($stock_filter) || !empty($status_filter)): ?>
                    <a href="products.php" class="btn-reset">
                        <i class="fas fa-redo me-2"></i> Reset
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        
        <!-- Active Filters -->
        <?php if (!empty($search) || !empty($category) || !empty($stock_filter) || !empty($status_filter)): ?>
        <div class="active-filters">
            <span class="text-muted me-2">Active filters:</span>
            
            <?php if (!empty($search)): ?>
            <span class="filter-badge">
                <i class="fas fa-search me-1"></i> "<?php echo htmlspecialchars($search); ?>"
                <a href="?<?php 
                    $params = $_GET;
                    unset($params['search']);
                    echo http_build_query($params);
                ?>" class="remove-filter">
                    <i class="fas fa-times"></i>
                </a>
            </span>
            <?php endif; ?>
            
            <?php if (!empty($category)): ?>
            <span class="filter-badge">
                <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($category); ?>
                <a href="?<?php 
                    $params = $_GET;
                    unset($params['category']);
                    echo http_build_query($params);
                ?>" class="remove-filter">
                    <i class="fas fa-times"></i>
                </a>
            </span>
            <?php endif; ?>
            
            <?php if (!empty($status_filter) && $status_filter != 'all'): ?>
            <span class="filter-badge" style="background: rgba(<?php 
                echo $status_filter === 'pending' ? '255, 183, 3' : ($status_filter === 'approved' ? '6, 214, 160' : '239, 71, 111'); 
            ?>, 0.1);">
                <i class="fas fa-<?php 
                    echo $status_filter === 'pending' ? 'clock' : ($status_filter === 'approved' ? 'check-circle' : 'times-circle'); 
                ?> me-1"></i>
                <?php echo ucfirst($status_filter); ?>
                <a href="?<?php 
                    $params = $_GET;
                    unset($params['status']);
                    echo http_build_query($params);
                ?>" class="remove-filter">
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
            <span class="filter-badge" style="background: rgba(<?php 
                echo $stock_filter === 'in_stock' ? '6, 214, 160' : ($stock_filter === 'low' ? '255, 183, 3' : '239, 71, 111'); 
            ?>, 0.1);">
                <i class="fas fa-<?php echo $stock_filter === 'in_stock' ? 'check' : ($stock_filter === 'low' ? 'exclamation' : 'times'); ?> me-1"></i>
                <?php echo $stock_labels[$stock_filter]; ?>
                <a href="?<?php 
                    $params = $_GET;
                    unset($params['stock']);
                    echo http_build_query($params);
                ?>" class="remove-filter">
                    <i class="fas fa-times"></i>
                </a>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Products Card -->
    <div class="products-card animate-slide-in delay-3">
        <div class="card-header">
            <h5>
                <i class="fas fa-cubes me-2 text-primary"></i>
                Product Catalog
                <span class="badge bg-primary ms-2"><?php echo $stats['total_products']; ?> Items</span>
                <span class="badge bg-warning ms-2"><?php echo $stats['pending_approval']; ?> Pending</span>
            </h5>
            
            <div class="d-flex gap-2">
                <!-- Sort Dropdown -->
                <div class="dropdown sort-dropdown">
                    <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-sort me-1"></i>
                        <?php 
                        $sort_labels = [
                            'newest' => 'Newest First',
                            'oldest' => 'Oldest First',
                            'name_asc' => 'Name A-Z',
                            'name_desc' => 'Name Z-A',
                            'price_low' => 'Price: Low to High',
                            'price_high' => 'Price: High to Low',
                            'stock_low' => 'Stock: Low to High'
                        ];
                        echo $sort_labels[$sort] ?? 'Sort By';
                        ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item <?php echo $sort === 'newest' ? 'active' : ''; ?>" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'newest', 'page' => 1])); ?>">
                            <i class="fas fa-clock me-2"></i> Newest First
                        </a></li>
                        <li><a class="dropdown-item <?php echo $sort === 'oldest' ? 'active' : ''; ?>" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'oldest', 'page' => 1])); ?>">
                            <i class="fas fa-history me-2"></i> Oldest First
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?php echo $sort === 'name_asc' ? 'active' : ''; ?>" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name_asc', 'page' => 1])); ?>">
                            <i class="fas fa-sort-alpha-down me-2"></i> Name A-Z
                        </a></li>
                        <li><a class="dropdown-item <?php echo $sort === 'name_desc' ? 'active' : ''; ?>" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'name_desc', 'page' => 1])); ?>">
                            <i class="fas fa-sort-alpha-up me-2"></i> Name Z-A
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?php echo $sort === 'price_low' ? 'active' : ''; ?>" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_low', 'page' => 1])); ?>">
                            <i class="fas fa-arrow-up me-2"></i> Price: Low to High
                        </a></li>
                        <li><a class="dropdown-item <?php echo $sort === 'price_high' ? 'active' : ''; ?>" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'price_high', 'page' => 1])); ?>">
                            <i class="fas fa-arrow-down me-2"></i> Price: High to Low
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item <?php echo $sort === 'stock_low' ? 'active' : ''; ?>" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'stock_low', 'page' => 1])); ?>">
                            <i class="fas fa-layer-group me-2"></i> Stock: Low to High
                        </a></li>
                    </ul>
                </div>
                
                <!-- View Toggle -->
                <button class="view-toggle" onclick="toggleView()" id="viewToggle">
                    <i class="fas fa-th-large" id="viewIcon"></i>
                </button>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h5>No Products Found</h5>
                    <p class="text-muted">
                        <?php if (!empty($search) || !empty($category) || !empty($stock_filter) || !empty($status_filter)): ?>
                            No products match your filters. Try adjusting your search criteria.
                        <?php else: ?>
                            Your product catalog is empty. Start by adding your first product.
                        <?php endif; ?>
                    </p>
                    <a href="product-action.php?action=add" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus-circle me-2"></i> Add Your First Product
                    </a>
                </div>
            <?php else: ?>
                <div class="row g-4" id="productsGrid">
                    <?php foreach($products as $product): 
                        $stock_percentage = $product['stock'] > 100 ? 100 : ($product['stock'] / 100) * 100;
                        $stock_class = $product['stock'] == 0 ? 'low' : ($product['stock'] < 10 ? 'medium' : 'high');
                        $is_new = strtotime($product['created_at']) > strtotime('-7 days');
                        $approval_status = $product['approved_status'] ?? 'pending';
                    ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo !empty($product['image']) ? '../../assets/images/products/' . $product['image'] : '../../assets/images/products/default.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.src='../../assets/images/products/default.jpg'">
                                
                                <div class="product-badges">
                                    <div>
                                        <?php if ($product['featured']): ?>
                                        <span class="badge-custom badge-featured">
                                            <i class="fas fa-star"></i> Featured
                                        </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($is_new): ?>
                                        <span class="badge-custom badge-new ms-1">
                                            <i class="fas fa-bolt"></i> New
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div>
                                        <?php if ($product['stock'] == 0): ?>
                                        <span class="badge-custom badge-out">
                                            <i class="fas fa-times-circle"></i> Out
                                        </span>
                                        <?php elseif ($product['stock'] < 10): ?>
                                        <span class="badge-custom badge-low">
                                            <i class="fas fa-exclamation-triangle"></i> Low
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Approval Status Badge -->
                                <div class="position-absolute bottom-0 start-0 p-2" style="z-index: 2;">
                                    <?php if ($approval_status == 'pending'): ?>
                                    <span class="badge-custom badge-pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                    <?php elseif ($approval_status == 'approved'): ?>
                                    <span class="badge-custom badge-approved">
                                        <i class="fas fa-check-circle"></i> Approved
                                    </span>
                                    <?php elseif ($approval_status == 'rejected'): ?>
                                    <span class="badge-custom badge-rejected">
                                        <i class="fas fa-times-circle"></i> Rejected
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="product-body">
                                <div class="product-category">
                                    <i class="fas fa-tag"></i>
                                    <?php echo !empty($product['category']) ? htmlspecialchars($product['category']) : 'Uncategorized'; ?>
                                </div>
                                
                                <div class="product-title">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </div>
                                
                                <div class="product-price">
                                    <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                                    <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                                    <span class="old-price">$<?php echo number_format($product['old_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="product-stock">
                                    <div class="stock-bar">
                                        <div class="stock-fill <?php echo $stock_class; ?>" 
                                             style="width: <?php echo $stock_percentage; ?>%"></div>
                                    </div>
                                    <span class="stock-text <?php echo $stock_class; ?>">
                                        <?php echo $product['stock']; ?> units
                                    </span>
                                </div>
                            </div>
                            
                            <div class="product-footer">
                                <?php if ($approval_status == 'pending'): ?>
                                    <button class="btn-action btn-approve" onclick="approveProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-action btn-reject" onclick="rejectProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <a href="product-action.php?action=view&id=<?php echo $product['id']; ?>" 
                                   class="btn-action btn-view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="product-action.php?action=edit&id=<?php echo $product['id']; ?>" 
                                   class="btn-action btn-edit" title="Edit Product">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" 
                                        class="btn-action btn-delete" 
                                        title="Delete Product"
                                        onclick="confirmDelete(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer border-0 bg-white py-4">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Showing page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </div>
                <nav aria-label="Product pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor;
                        
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="product-approval.php">
                <input type="hidden" name="product_id" id="approve_product_id">
                <input type="hidden" name="action" value="approve">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Approve Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this product?</p>
                    <p class="text-muted small">Approved products will be visible to customers on the website.</p>
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
            <form method="POST" action="product-approval.php">
                <input type="hidden" name="product_id" id="reject_product_id">
                <input type="hidden" name="action" value="reject">
                
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
                        <label for="rejection_reason" class="form-label">Reason for rejection</label>
                        <textarea name="rejection_reason" class="form-control" id="rejection_reason" rows="3" required></textarea>
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

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete product <strong id="deleteProductName"></strong>?</p>
                <p class="text-danger small">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    This action cannot be undone. The product will be permanently removed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="product-action.php?action=edit&id=<?php echo $product['id']; ?>" id="deleteConfirmBtn" class="btn btn-danger">Delete Product</a>
            </div>
        </div>
    </div>
</div>

<script>
let isGridView = true;

function toggleView() {
    const grid = document.getElementById('productsGrid');
    const icon = document.getElementById('viewIcon');
    const toggleBtn = document.getElementById('viewToggle');
    
    if (isGridView) {
        // Switch to list view
        grid.classList.remove('row', 'g-4');
        grid.classList.add('list-view');
        icon.classList.remove('fa-th-large');
        icon.classList.add('fa-list');
        toggleBtn.innerHTML = '<i class="fas fa-list"></i>';
        
        // Style for list view
        document.querySelectorAll('.col-xl-3').forEach(col => {
            col.classList.remove('col-xl-3', 'col-lg-4', 'col-md-6');
            col.classList.add('col-12');
        });
        
        document.querySelectorAll('.product-card').forEach(card => {
            card.classList.add('list-view-card');
        });
        
    } else {
        // Switch back to grid view
        grid.classList.remove('list-view');
        grid.classList.add('row', 'g-4');
        icon.classList.remove('fa-list');
        icon.classList.add('fa-th-large');
        toggleBtn.innerHTML = '<i class="fas fa-th-large"></i>';
        
        document.querySelectorAll('.col-12').forEach(col => {
            col.classList.add('col-xl-3', 'col-lg-4', 'col-md-6');
            col.classList.remove('col-12');
        });
        
        document.querySelectorAll('.product-card').forEach(card => {
            card.classList.remove('list-view-card');
        });
    }
    
    isGridView = !isGridView;
}

function approveProduct(id) {
    document.getElementById('approve_product_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectProduct(id) {
    document.getElementById('reject_product_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function confirmDelete(productId, productName) {
    document.getElementById('deleteProductName').textContent = productName;
    document.getElementById('deleteConfirmBtn').href = 'product-action.php?action=delete&id=' + productId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.querySelector('.filter-card form');
    const categorySelect = document.querySelector('select[name="category"]');
    const statusSelect = document.querySelector('select[name="status"]');
    const stockSelect = document.querySelector('select[name="stock"]');
    
    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            filterForm.submit();
        });
    }
    
    if (statusSelect) {
        statusSelect.addEventListener('change', function() {
            filterForm.submit();
        });
    }
    
    if (stockSelect) {
        stockSelect.addEventListener('change', function() {
            filterForm.submit();
        });
    }
    
    // Search on Enter
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filterForm.submit();
            }
        });
    }
    
    // Initialize tooltips
    document.querySelectorAll('[title]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
});

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