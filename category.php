<?php
require_once 'includes/config.php';

// Check if category slug is provided
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    header('Location: index.php');
    exit();
}

$category_slug = trim($_GET['slug']);

// Get category details
try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
    $stmt->execute([$category_slug]);
    $category = $stmt->fetch();
    
    if (!$category) {
        $_SESSION['error'] = 'Category not found.';
        header('Location: index.php');
        exit();
    }
    
    // Get parent category if exists
    if ($category['parent_id']) {
        $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$category['parent_id']]);
        $parent_category = $stmt->fetch();
    } else {
        $parent_category = null;
    }
    
    // Get sub-categories
    $stmt = $db->prepare("SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY name");
    $stmt->execute([$category['id']]);
    $sub_categories = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading category.';
    header('Location: index.php');
    exit();
}
// Temporary debug code - category.php ke start mein add karo
// try {
//     $db = getDB();
//     $stmt = $db->query("SELECT id, name, slug, is_active FROM categories");
//     $all_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
//     echo "<pre>Debug: All Categories in Database\n";
//     print_r($all_categories);
//     echo "</pre>";
    
//     // Check if your slug exists
//     $slug_to_check = isset($_GET['slug']) ? $_GET['slug'] : 'NOT SET';
//     echo "Current slug from URL: " . htmlspecialchars($slug_to_check);
    
// } catch(Exception $e) {
//     echo "DB Error: " . $e->getMessage();
// }
//exit; // Remove this after debugging
// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Filter parameters
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : null;
$vendor_filter = isset($_GET['vendor']) ? (int)$_GET['vendor'] : null;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE conditions
$where_conditions = [
    "p.approved_status = 'approved'",
    "p.stock > 0",
    "u.vendor_status = 'approved'"
];

$params = [];

// Category condition
if ($category['parent_id']) {
    // If sub-category, show only this category
    $where_conditions[] = "p.category = :category_slug";
    $params[':category_slug'] = $category_slug;
} else {
    // If parent category, show all sub-categories too
    $sub_category_slugs = [];
    foreach($sub_categories as $sub) {
        $sub_category_slugs[] = $sub['slug'];
    }
    $sub_category_slugs[] = $category_slug;
    
    $placeholders = implode(',', array_fill(0, count($sub_category_slugs), '?'));
    $where_conditions[] = "p.category IN ($placeholders)";
    $params = array_merge($params, $sub_category_slugs);
}

// Price filters
if ($min_price !== null && $min_price > 0) {
    $where_conditions[] = "p.price >= :min_price";
    $params[':min_price'] = $min_price;
}

if ($max_price !== null && $max_price > 0) {
    $where_conditions[] = "p.price <= :max_price";
    $params[':max_price'] = $max_price;
}

// Rating filter
if ($rating_filter !== null && $rating_filter >= 1 && $rating_filter <= 5) {
    $where_conditions[] = "(SELECT AVG(rating) FROM reviews WHERE product_id = p.id) >= :rating";
    $params[':rating'] = $rating_filter;
}

// Vendor filter
if ($vendor_filter !== null && $vendor_filter > 0) {
    $where_conditions[] = "p.vendor_id = :vendor_id";
    $params[':vendor_id'] = $vendor_filter;
}

// Search term
if (!empty($search_term)) {
    $where_conditions[] = "(p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search_term%";
}

// Build WHERE clause
$where_clause = implode(" AND ", $where_conditions);

// Sort order
$sort_options = [
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name_asc' => 'p.name ASC',
    'name_desc' => 'p.name DESC',
    'popular' => 'p.views DESC',
    'rating' => 'product_rating DESC',
    'featured' => 'p.featured DESC, p.created_at DESC'
];

$order_by = $sort_options[$sort_by] ?? 'p.created_at DESC';

// Get total count for pagination
try {
    $count_query = "SELECT COUNT(DISTINCT p.id) as total 
                   FROM products p 
                   LEFT JOIN users u ON p.vendor_id = u.id 
                   WHERE $where_clause";
    
    $stmt = $db->prepare($count_query);
    $stmt->execute($params);
    $total_products = (int)$stmt->fetchColumn();
    $total_pages = ceil($total_products / $limit);
    
    // Adjust page if out of bounds
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit;
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading products.';
    $total_products = 0;
    $total_pages = 1;
}

// Get products
$products = [];
try {
    $query = "SELECT 
                p.*,
                u.username as vendor_username,
                u.full_name as vendor_name,
                u.vendor_rating,
                c.name as category_name,
                (SELECT AVG(rating) FROM reviews WHERE product_id = p.id) as product_rating,
                (SELECT COUNT(*) FROM reviews WHERE product_id = p.id) as review_count,
                (SELECT COUNT(*) FROM order_items WHERE product_id = p.id) as total_sold
              FROM products p 
              LEFT JOIN users u ON p.vendor_id = u.id 
              LEFT JOIN categories c ON p.category = c.slug 
              WHERE $where_clause 
              GROUP BY p.id 
              ORDER BY $order_by 
              LIMIT $limit OFFSET $offset";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading products: ' . $e->getMessage();
}

// Get vendors in this category for filter
try {
    $vendor_query = "SELECT DISTINCT u.id, u.username, u.full_name 
                     FROM products p 
                     JOIN users u ON p.vendor_id = u.id 
                     WHERE p.category IN (
                         SELECT slug FROM categories 
                         WHERE id = ? OR parent_id = ?
                     ) AND p.approved_status = 'approved' 
                     AND u.vendor_status = 'approved'
                     ORDER BY u.full_name";
    
    $stmt = $db->prepare($vendor_query);
    $stmt->execute([$category['id'], $category['id']]);
    $category_vendors = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $category_vendors = [];
}

// Get price range for filter
try {
    $price_query = "SELECT 
                      MIN(p.price) as min_price,
                      MAX(p.price) as max_price
                    FROM products p 
                    WHERE p.category IN (
                        SELECT slug FROM categories 
                        WHERE id = ? OR parent_id = ?
                    ) AND p.approved_status = 'approved' 
                    AND p.stock > 0";
    
    $stmt = $db->prepare($price_query);
    $stmt->execute([$category['id'], $category['id']]);
    $price_range = $stmt->fetch();
    
} catch(PDOException $e) {
    $price_range = ['min_price' => 0, 'max_price' => 1000];
}

$page_title = htmlspecialchars($category['name']) . ' - ' . SITE_NAME;
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="container py-1">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
        <?php if ($parent_category): ?>
        <li class="breadcrumb-item"><a href="category.php?slug=<?php echo $parent_category['slug']; ?>">
            <?php echo htmlspecialchars($parent_category['name']); ?>
        </a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($category['name']); ?></li>
    </ol>
</nav>

<div class="container py-4">
    <!-- Category Header -->
    <div class="category-header mb-5">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 fw-bold mb-3"><?php echo htmlspecialchars($category['name']); ?></h1>
                
                <?php if (!empty($category['description'])): ?>
                <p class="lead text-muted mb-4"><?php echo htmlspecialchars($category['description']); ?></p>
                <?php endif; ?>
                
                <div class="category-stats d-flex flex-wrap gap-4">
                    <div class="stat-item">
                        <h3 class="text-primary mb-0"><?php echo number_format($total_products); ?></h3>
                        <small class="text-muted">Products</small>
                    </div>
                    
                    <?php if (!empty($sub_categories)): ?>
                    <div class="stat-item">
                        <h3 class="text-success mb-0"><?php echo count($sub_categories); ?></h3>
                        <small class="text-muted">Sub-categories</small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                        <h3 class="text-warning mb-0"><?php echo count($category_vendors); ?></h3>
                        <small class="text-muted">Vendors</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 text-md-end">
                <?php if (!empty($category['image'])): ?>
                <img src="<?php echo SITE_URL . 'assets/images/categories/' . $category['image']; ?>" 
                     alt="<?php echo htmlspecialchars($category['name']); ?>"
                     class="img-fluid rounded shadow"
                     style="max-height: 200px;">
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Messages -->
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <!-- Sub-categories -->
    <?php if (!empty($sub_categories)): ?>
    <div class="sub-categories mb-5">
        <h3 class="mb-4">Sub-categories</h3>
        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-6 g-3">
            <?php foreach($sub_categories as $sub): ?>
            <div class="col">
                <a href="category.php?slug=<?php echo $sub['slug']; ?>" 
                   class="sub-category-card d-block text-decoration-none text-center p-3 border rounded">
                    <?php if (!empty($sub['image'])): ?>
                    <img src="<?php echo SITE_URL . 'assets/images/categories/' . $sub['image']; ?>" 
                         alt="<?php echo htmlspecialchars($sub['name']); ?>"
                         class="img-fluid mb-2 rounded"
                         style="height: 60px; object-fit: cover;">
                    <?php else: ?>
                    <div class="category-icon mb-2">
                        <i class="fas fa-folder fa-2x text-primary"></i>
                    </div>
                    <?php endif; ?>
                    <h6 class="mb-0 text-dark"><?php echo htmlspecialchars($sub['name']); ?></h6>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3">
            <div class="filter-sidebar sticky-top" style="top: 20px;">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" id="filterForm">
                            <input type="hidden" name="slug" value="<?php echo $category_slug; ?>">
                            
                            <!-- Search -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Search</label>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           name="search" 
                                           value="<?php echo htmlspecialchars($search_term); ?>"
                                           placeholder="Search products...">
                                    <button class="btn btn-outline-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Price Range -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Price Range</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="number" 
                                               class="form-control" 
                                               name="min_price" 
                                               placeholder="Min"
                                               value="<?php echo $min_price ? htmlspecialchars($min_price) : ''; ?>"
                                               step="0.01"
                                               min="0">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" 
                                               class="form-control" 
                                               name="max_price" 
                                               placeholder="Max"
                                               value="<?php echo $max_price ? htmlspecialchars($max_price) : ''; ?>"
                                               step="0.01"
                                               min="0">
                                    </div>
                                </div>
                                <small class="text-muted">Current range: $<?php echo number_format($price_range['min_price'] ?? 0, 2); ?> - $<?php echo number_format($price_range['max_price'] ?? 1000, 2); ?></small>
                            </div>
                            
                            <!-- Rating -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Minimum Rating</label>
                                <div class="rating-filter">
                                    <?php for($i = 5; $i >= 1; $i--): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="rating" 
                                               id="rating<?php echo $i; ?>" 
                                               value="<?php echo $i; ?>"
                                               <?php echo $rating_filter == $i ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rating<?php echo $i; ?>">
                                            <?php for($j = 1; $j <= 5; $j++): ?>
                                                <?php if ($j <= $i): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-warning"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            <span class="ms-1"><?php echo $i; ?>+ stars</span>
                                        </label>
                                    </div>
                                    <?php endfor; ?>
                                    
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="radio" 
                                               name="rating" 
                                               id="rating0" 
                                               value=""
                                               <?php echo $rating_filter === null ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="rating0">All Ratings</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Vendors -->
                            <?php if (!empty($category_vendors)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">Vendor</label>
                                <select class="form-select" name="vendor">
                                    <option value="">All Vendors</option>
                                    <?php foreach($category_vendors as $vendor): ?>
                                    <option value="<?php echo $vendor['id']; ?>" 
                                        <?php echo $vendor_filter == $vendor['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vendor['full_name'] ?? $vendor['username']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Filter Actions -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter me-2"></i> Apply Filters
                                </button>
                                <a href="category.php?slug=<?php echo $category_slug; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i> Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Category Info -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Category Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="category-meta">
                            <p class="mb-2">
                                <i class="fas fa-box me-2 text-primary"></i>
                                <strong>Products:</strong> <?php echo number_format($total_products); ?>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-store me-2 text-success"></i>
                                <strong>Vendors:</strong> <?php echo count($category_vendors); ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-calendar-alt me-2 text-warning"></i>
                                <strong>Last Updated:</strong> <?php echo date('M d, Y', strtotime($category['updated_at'] ?? $category['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Products Header -->
            <div class="products-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
                <div class="mb-3 mb-md-0">
                    <h2 class="h4 mb-1">Products</h2>
                    <p class="text-muted mb-0">
                        Showing <?php echo count($products); ?> of <?php echo number_format($total_products); ?> products
                        <?php if (!empty($search_term)): ?>
                            for "<strong><?php echo htmlspecialchars($search_term); ?></strong>"
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="d-flex flex-wrap gap-2">
                    <!-- Sort Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-sort me-2"></i>
                            <?php 
                            $sort_labels = [
                                'newest' => 'Newest First',
                                'oldest' => 'Oldest First',
                                'price_low' => 'Price: Low to High',
                                'price_high' => 'Price: High to Low',
                                'name_asc' => 'Name: A to Z',
                                'name_desc' => 'Name: Z to A',
                                'popular' => 'Most Popular',
                                'rating' => 'Highest Rated',
                                'featured' => 'Featured First'
                            ];
                            echo $sort_labels[$sort_by] ?? 'Sort By';
                            ?>
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach($sort_labels as $key => $label): ?>
                            <li>
                                <a class="dropdown-item <?php echo $sort_by == $key ? 'active' : ''; ?>" 
                                   href="?slug=<?php echo $category_slug; ?>&sort=<?php echo $key; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?>">
                                    <?php echo $label; ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- View Toggle -->
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary active" id="gridViewBtn">
                            <i class="fas fa-th-large"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="listViewBtn">
                            <i class="fas fa-list"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Active Filters -->
            <?php if (!empty($search_term) || $min_price !== null || $max_price !== null || $rating_filter !== null || $vendor_filter !== null): ?>
            <div class="active-filters mb-4">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <small class="text-muted me-2">Active Filters:</small>
                    
                    <?php if (!empty($search_term)): ?>
                    <span class="badge bg-info">
                        Search: <?php echo htmlspecialchars($search_term); ?>
                        <a href="?slug=<?php echo $category_slug; ?>&sort=<?php echo $sort_by; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>" 
                           class="text-white ms-2" style="text-decoration: none;">×</a>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($min_price !== null): ?>
                    <span class="badge bg-secondary">
                        Min: $<?php echo number_format($min_price, 2); ?>
                        <a href="?slug=<?php echo $category_slug; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>" 
                           class="text-white ms-2" style="text-decoration: none;">×</a>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($max_price !== null): ?>
                    <span class="badge bg-secondary">
                        Max: $<?php echo number_format($max_price, 2); ?>
                        <a href="?slug=<?php echo $category_slug; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>" 
                           class="text-white ms-2" style="text-decoration: none;">×</a>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($rating_filter !== null): ?>
                    <span class="badge bg-warning text-dark">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $rating_filter): ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <a href="?slug=<?php echo $category_slug; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>" 
                           class="text-dark ms-2" style="text-decoration: none;">×</a>
                    </span>
                    <?php endif; ?>
                    
                    <?php if ($vendor_filter !== null): 
                        $vendor_name = '';
                        foreach($category_vendors as $vendor) {
                            if ($vendor['id'] == $vendor_filter) {
                                $vendor_name = $vendor['full_name'] ?? $vendor['username'];
                                break;
                            }
                        }
                        if ($vendor_name):
                    ?>
                    <span class="badge bg-primary">
                        Vendor: <?php echo htmlspecialchars($vendor_name); ?>
                        <a href="?slug=<?php echo $category_slug; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?>" 
                           class="text-white ms-2" style="text-decoration: none;">×</a>
                    </span>
                    <?php endif; endif; ?>
                    
                    <a href="category.php?slug=<?php echo $category_slug; ?>" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-times me-1"></i> Clear All
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Products Grid -->
            <?php if (empty($products)): ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-search fa-3x text-muted opacity-25"></i>
                </div>
                <h4 class="text-muted mb-3">No Products Found</h4>
                <p class="text-muted mb-4">
                    <?php if (!empty($search_term) || $min_price !== null || $max_price !== null || $rating_filter !== null || $vendor_filter !== null): ?>
                        No products match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        No products available in this category at the moment. Check back soon!
                    <?php endif; ?>
                </p>
                <a href="category.php?slug=<?php echo $category_slug; ?>" class="btn btn-primary">
                    <i class="fas fa-redo me-2"></i> Clear Filters
                </a>
            </div>
            <?php else: ?>
            <div class="products-container">
                <!-- Grid View -->
                <div id="gridView" class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
                    <?php foreach($products as $product): 
                        $product_image = SITE_URL . 'assets/images/products/' . ($product['image'] ?: 'default.png');
                        $avg_rating = (float)$product['product_rating'];
                        $review_count = (int)$product['review_count'];
                        $total_sold = (int)$product['total_sold'];
                    ?>
                    <div class="col">
                        <div class="card product-card h-100 border-0 shadow-sm">
                            <!-- Product Image -->
                            <div class="position-relative">
                                <a href="product-details.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo $product_image; ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         style="height: 200px; object-fit: contain; padding: 10px;"
                                         onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'">
                                </a>
                                
                                <!-- Badges -->
                                <div class="position-absolute top-0 start-0 p-2">
                                    <?php if ($product['featured']): ?>
                                    <span class="badge bg-info">Featured</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($product['old_price'] && $product['old_price'] > $product['price']): 
                                        $discount = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100);
                                    ?>
                                    <span class="badge bg-danger">-<?php echo $discount; ?>%</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Quick Actions -->
                                <div class="position-absolute top-0 end-0 p-2">
                                    <button class="btn btn-sm btn-light rounded-circle" 
                                            onclick="addToWishlist(<?php echo $product['id']; ?>)"
                                            title="Add to wishlist">
                                        <i class="far fa-heart"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Product Body -->
                            <div class="card-body">
                                <!-- Category -->
                                <small class="text-muted d-block mb-1">
                                    <a href="category.php?slug=<?php echo $product['category']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($product['category_name'] ?? $product['category']); ?>
                                    </a>
                                </small>
                                
                                <!-- Product Name -->
                                <h6 class="card-title">
                                    <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                                       class="text-decoration-none text-dark" 
                                       title="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?>
                                        <?php if (strlen($product['name']) > 40): ?>...<?php endif; ?>
                                    </a>
                                </h6>
                                
                                <!-- Rating -->
                                <div class="rating mb-2">
                                    <div class="stars">
                                        <?php
                                        $full_stars = floor($avg_rating);
                                        $has_half_star = ($avg_rating - $full_stars) >= 0.5;
                                        
                                        for($i = 1; $i <= 5; $i++):
                                            if ($i <= $full_stars):
                                                echo '<i class="fas fa-star text-warning" style="font-size: 0.8rem;"></i>';
                                            elseif ($i == $full_stars + 1 && $has_half_star):
                                                echo '<i class="fas fa-star-half-alt text-warning" style="font-size: 0.8rem;"></i>';
                                            else:
                                                echo '<i class="far fa-star text-warning" style="font-size: 0.8rem;"></i>';
                                            endif;
                                        endfor;
                                        ?>
                                    </div>
                                    <small class="text-muted ms-1">
                                        (<?php echo $review_count; ?>)
                                    </small>
                                </div>
                                
                                <!-- Vendor -->
                                <small class="text-muted d-block mb-2">
                                    <i class="fas fa-store me-1"></i>
                                    <a href="vendor.php?id=<?php echo $product['vendor_id']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($product['vendor_name'] ?? $product['vendor_username']); ?>
                                    </a>
                                </small>
                                
                                <!-- Price -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="text-primary mb-0">$<?php echo number_format($product['price'], 2); ?></h5>
                                        <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                        <small class="text-muted text-decoration-line-through">
                                            $<?php echo number_format($product['old_price'], 2); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($total_sold > 0): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-shopping-cart me-1"></i>
                                        <?php echo number_format($total_sold); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Card Footer -->
                            <div class="card-footer bg-white border-top-0 pt-0">
                                <form method="POST" action="add-to-cart.php" class="add-to-cart-form">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    
                                    <?php if ($product['stock'] > 0): ?>
                                    <button type="submit" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary w-100" disabled>
                                        <i class="fas fa-times me-2"></i> Out of Stock
                                    </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- List View (Hidden by default) -->
                <div id="listView" class="d-none">
                    <?php foreach($products as $product): 
                        $product_image = SITE_URL . 'assets/images/products/' . ($product['image'] ?: 'default.png');
                        $avg_rating = (float)$product['product_rating'];
                        $review_count = (int)$product['review_count'];
                        $total_sold = (int)$product['total_sold'];
                    ?>
                    <div class="card product-list-card border-0 shadow-sm mb-3">
                        <div class="row g-0">
                            <!-- Product Image -->
                            <div class="col-md-3">
                                <a href="product-details.php?id=<?php echo $product['id']; ?>">
                                    <img src="<?php echo $product_image; ?>" 
                                         class="img-fluid rounded-start" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         style="height: 200px; object-fit: cover; width: 100%;"
                                         onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'">
                                </a>
                            </div>
                            
                            <!-- Product Details -->
                            <div class="col-md-9">
                                <div class="card-body h-100 d-flex flex-column">
                                    <div class="flex-grow-1">
                                        <!-- Badges -->
                                        <div class="mb-2">
                                            <?php if ($product['featured']): ?>
                                            <span class="badge bg-info me-2">Featured</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($product['old_price'] && $product['old_price'] > $product['price']): 
                                                $discount = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100);
                                            ?>
                                            <span class="badge bg-danger me-2">-<?php echo $discount; ?>%</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Product Name -->
                                        <h5 class="card-title">
                                            <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </a>
                                        </h5>
                                        
                                        <!-- Category and Vendor -->
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-folder me-1"></i>
                                                <a href="category.php?slug=<?php echo $product['category']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($product['category_name'] ?? $product['category']); ?>
                                                </a>
                                                •
                                                <i class="fas fa-store ms-2 me-1"></i>
                                                <a href="vendor.php?id=<?php echo $product['vendor_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($product['vendor_name'] ?? $product['vendor_username']); ?>
                                                </a>
                                            </small>
                                        </div>
                                        
                                        <!-- Rating -->
                                        <div class="rating mb-2">
                                            <div class="stars d-inline-block">
                                                <?php
                                                $full_stars = floor($avg_rating);
                                                $has_half_star = ($avg_rating - $full_stars) >= 0.5;
                                                
                                                for($i = 1; $i <= 5; $i++):
                                                    if ($i <= $full_stars):
                                                        echo '<i class="fas fa-star text-warning"></i>';
                                                    elseif ($i == $full_stars + 1 && $has_half_star):
                                                        echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                                    else:
                                                        echo '<i class="far fa-star text-warning"></i>';
                                                    endif;
                                                endfor;
                                                ?>
                                            </div>
                                            <small class="text-muted ms-2">
                                                <?php echo number_format($avg_rating, 1); ?> (<?php echo $review_count; ?> reviews)
                                            </small>
                                            
                                            <?php if ($total_sold > 0): ?>
                                            <small class="text-muted ms-3">
                                                <i class="fas fa-shopping-cart me-1"></i>
                                                <?php echo number_format($total_sold); ?> sold
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Description -->
                                        <p class="card-text text-muted mb-3">
                                            <?php echo htmlspecialchars(substr($product['description'], 0, 200)); ?>
                                            <?php if (strlen($product['description']) > 200): ?>...<?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Price and Actions -->
                                    <div class="d-flex justify-content-between align-items-center mt-auto">
                                        <div>
                                            <h4 class="text-primary mb-0">$<?php echo number_format($product['price'], 2); ?></h4>
                                            <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                            <small class="text-muted text-decoration-line-through">
                                                $<?php echo number_format($product['old_price'], 2); ?>
                                            </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <form method="POST" action="add-to-cart.php" class="add-to-cart-form">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                
                                                <?php if ($product['stock'] > 0): ?>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                                                </button>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-secondary" disabled>
                                                    <i class="fas fa-times me-2"></i> Out of Stock
                                                </button>
                                                <?php endif; ?>
                                            </form>
                                            
                                            <button class="btn btn-outline-secondary" onclick="addToWishlist(<?php echo $product['id']; ?>)" title="Add to wishlist">
                                                <i class="far fa-heart"></i>
                                            </button>
                                            
                                            <a href="product-details.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav class="mt-5">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Page -->
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?slug=<?php echo $category_slug; ?>&page=<?php echo $page - 1; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        // Show first page
                        if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?slug=<?php echo $category_slug; ?>&page=1&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>">
                                1
                            </a>
                        </li>
                        <?php if ($start_page > 2): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; endif; ?>
                        
                        <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?slug=<?php echo $category_slug; ?>&page=<?php echo $i; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <!-- Show last page -->
                        <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" 
                               href="?slug=<?php echo $category_slug; ?>&page=<?php echo $total_pages; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?slug=<?php echo $category_slug; ?>&page=<?php echo $page + 1; ?>&sort=<?php echo $sort_by; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?><?php echo $min_price !== null ? '&min_price=' . $min_price : ''; ?><?php echo $max_price !== null ? '&max_price=' . $max_price : ''; ?><?php echo $rating_filter !== null ? '&rating=' . $rating_filter : ''; ?><?php echo $vendor_filter !== null ? '&vendor=' . $vendor_filter : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                    
                    <p class="text-center text-muted small mt-3">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?> • 
                        <?php echo number_format($total_products); ?> total products
                    </p>
                </nav>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.category-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    padding: 30px;
    border-radius: 12px;
}

.stat-item {
    text-align: center;
    padding: 15px;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    min-width: 100px;
}

.sub-category-card {
    transition: all 0.3s ease;
    background: white;
}

.sub-category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white !important;
}

.sub-category-card:hover h6 {
    color: white !important;
}

.filter-sidebar .card {
    border-radius: 12px;
    overflow: hidden;
}

.product-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.15) !important;
}

.product-card .card-img-top {
    transition: transform 0.5s ease;
}

.product-card:hover .card-img-top {
    transform: scale(1.05);
}

.product-list-card {
    transition: transform 0.3s ease;
}

.product-list-card:hover {
    transform: translateY(-5px);
}

.rating-filter .form-check-label {
    cursor: pointer;
}

.rating-filter .form-check-input:checked + .form-check-label {
    font-weight: bold;
    color: #4361ee;
}

.active-filters .badge {
    font-size: 0.9rem;
    padding: 6px 12px;
}

.active-filters .badge a:hover {
    opacity: 0.8;
}

.pagination .page-item.active .page-link {
    background-color: #4361ee;
    border-color: #4361ee;
}

.pagination .page-link {
    color: #4361ee;
    border-radius: 8px;
    margin: 0 3px;
}

.pagination .page-link:hover {
    background-color: rgba(67, 97, 238, 0.1);
}

.sticky-top {
    position: -webkit-sticky;
    position: sticky;
}
</style>

<script>
// View Toggle
document.addEventListener('DOMContentLoaded', function() {
    const gridViewBtn = document.getElementById('gridViewBtn');
    const listViewBtn = document.getElementById('listViewBtn');
    const gridView = document.getElementById('gridView');
    const listView = document.getElementById('listView');
    
    if (gridViewBtn && listViewBtn && gridView && listView) {
        gridViewBtn.addEventListener('click', function() {
            gridView.classList.remove('d-none');
            listView.classList.add('d-none');
            gridViewBtn.classList.add('active');
            listViewBtn.classList.remove('active');
            localStorage.setItem('productView', 'grid');
        });
        
        listViewBtn.addEventListener('click', function() {
            gridView.classList.add('d-none');
            listView.classList.remove('d-none');
            gridViewBtn.classList.remove('active');
            listViewBtn.classList.add('active');
            localStorage.setItem('productView', 'list');
        });
        
        // Load saved view preference
        const savedView = localStorage.getItem('productView') || 'grid';
        if (savedView === 'list') {
            listViewBtn.click();
        }
    }
    
    // Add to cart form submission
    const addToCartForms = document.querySelectorAll('.add-to-cart-form');
    addToCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('add-to-cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('success', data.message);
                    updateCartCount(data.cart_count);
                } else {
                    showToast('error', data.message);
                    
                    // Redirect to login if not authenticated
                    if (data.redirect) {
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('error', 'An error occurred. Please try again.');
            });
        });
    });
    
    // Price range slider (if using range input)
    const minPriceInput = document.querySelector('input[name="min_price"]');
    const maxPriceInput = document.querySelector('input[name="max_price"]');
    
    if (minPriceInput && maxPriceInput) {
        minPriceInput.addEventListener('change', validatePriceRange);
        maxPriceInput.addEventListener('change', validatePriceRange);
        
        function validatePriceRange() {
            const min = parseFloat(minPriceInput.value) || 0;
            const max = parseFloat(maxPriceInput.value) || 0;
            
            if (max > 0 && min > max) {
                alert('Maximum price must be greater than minimum price.');
                minPriceInput.value = '';
                maxPriceInput.value = '';
            }
        }
    }
});

// Add to wishlist function
function addToWishlist(productId) {
    <?php if (!isset($_SESSION['user_id'])): ?>
    showToast('warning', 'Please login to add items to wishlist.');
    setTimeout(() => {
        window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.href);
    }, 1500);
    return;
    <?php endif; ?>
    
    fetch('add-to-wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'product_id=' + productId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', data.message);
        } else {
            showToast(data.type, data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('error', 'An error occurred. Please try again.');
    });
}

// Update cart count in navbar
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
        element.classList.remove('d-none');
    });
}

// Toast notification
function showToast(type, message) {
    // Remove existing toasts
    const existingToasts = document.querySelectorAll('.custom-toast');
    existingToasts.forEach(toast => toast.remove());
    
    // Create toast
    const toast = document.createElement('div');
    toast.className = `custom-toast toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Show with Bootstrap
    const bsToast = new bootstrap.Toast(toast, {
        animation: true,
        autohide: true,
        delay: 3000
    });
    
    bsToast.show();
    
    // Remove from DOM after hide
    toast.addEventListener('hidden.bs.toast', function () {
        toast.remove();
    });
}

// Filter form submission with loading
const filterForm = document.getElementById('filterForm');
if (filterForm) {
    filterForm.addEventListener('submit', function() {
        // Show loading overlay
        const overlay = document.createElement('div');
        overlay.className = 'position-fixed top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex justify-content-center align-items-center';
        overlay.style.zIndex = '9999';
        overlay.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p>Applying filters...</p>
            </div>
        `;
        document.body.appendChild(overlay);
    });
}

// Price range slider implementation (alternative to number inputs)
function initPriceSlider() {
    const minPrice = <?php echo $price_range['min_price'] ?? 0; ?>;
    const maxPrice = <?php echo $price_range['max_price'] ?? 1000; ?>;
    const currentMin = <?php echo $min_price !== null ? $min_price : $price_range['min_price'] ?? 0; ?>;
    const currentMax = <?php echo $max_price !== null ? $max_price : $price_range['max_price'] ?? 1000; ?>;
    
    // You can implement a range slider here using libraries like noUiSlider
    // For simplicity, we're using number inputs

}

// Initialize on page load
initPriceSlider();
</script>

<?php require_once 'includes/footer.php'; ?>
