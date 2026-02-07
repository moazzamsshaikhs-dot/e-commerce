<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'Shop Now';
require_once '../../includes/header.php';

$db = getDB();

// Get products with categories and filters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build query
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category = c.slug 
          WHERE p.approved_status = 'approved' 
          AND p.stock > 0";

$params = [];

if (!empty($category)) {
    $query .= " AND p.category = :category";
    $params[':category'] = $category;
}

if (!empty($search)) {
    $query .= " AND (p.name LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($min_price) && is_numeric($min_price)) {
    $query .= " AND p.price >= :min_price";
    $params[':min_price'] = $min_price;
}

if (!empty($max_price) && is_numeric($max_price)) {
    $query .= " AND p.price <= :max_price";
    $params[':max_price'] = $max_price;
}

// Sorting
switch ($sort) {
    case 'price_low':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'popular':
        $query .= " ORDER BY p.views DESC, p.sales_count DESC";
        break;
    case 'rating':
        $query .= " ORDER BY p.average_rating DESC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Get total count
$count_query = preg_replace('/SELECT p\.\*, c\.name as category_name/', 'SELECT COUNT(*) as total', $query);
$count_query = preg_replace('/ORDER BY.*/', '', $count_query);

$stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_products = $stmt->fetch()['total'];
$total_pages = ceil($total_products / $limit);

// Add pagination to main query
$query .= " LIMIT :limit OFFSET :offset";

// Get products
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Get all categories for filter
$stmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
$categories = $stmt->fetchAll();

// Get cart items for current user
$cart_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Get wishlist items for current user
$wishlist_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Log activity
logUserActivity($_SESSION['user_id'], 'shop_access', 'Accessed shop page');
?>

<div class="shop-page">
    <!-- Hero Section -->
    <div class="bg-gradient py-5 mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-black-70 mb-3">Shop with Confidence</h1>
                    <p class="text-white-75 mb-4">
                        Discover amazing products with guaranteed quality. Free shipping on orders over $50.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="#featured" class="btn btn-light">
                            <i class="fas fa-star me-2"></i> Featured Products
                        </a>
                        <a href="#categories" class="btn btn-outline-light">
                            <i class="fas fa-list me-2"></i> Browse Categories
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="stats-card bg-white bg-opacity-10 rounded-3 p-3 text-black-60">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <h3 class="mb-0"><?php echo $total_products; ?></h3>
                                <small class="text-muted">Products</small>
                            </div>
                            <div class="col-4">
                                <h3 class="mb-0">24/7</h3>
                                <small class="text-muted">Support</small>
                            </div>
                            <div class="col-4">
                                <h3 class="mb-0">30</h3>
                                <small class="text-muted">Days Return</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Filters Bar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" id="shopFilters" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['slug']; ?>" <?php echo ($category === $cat['slug']) ? 'selected' : ''; ?>>
                                    <?php echo $cat['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Price Range</label>
                        <div class="input-group">
                            <input type="number" name="min_price" class="form-control" placeholder="Min" value="<?php echo $min_price; ?>" min="0">
                            <span class="input-group-text">to</span>
                            <input type="number" name="max_price" class="form-control" placeholder="Max" value="<?php echo $max_price; ?>" min="0">
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="price_low" <?php echo ($sort === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo ($sort === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="popular" <?php echo ($sort === 'popular') ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="rating" <?php echo ($sort === 'rating') ? 'selected' : ''; ?>>Highest Rated</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Categories Section -->
        <div class="mb-5" id="categories">
            <h3 class="mb-4">Browse Categories</h3>
            <div class="row g-3">
                <?php foreach ($categories as $cat): ?>
                    <div class="col-6 col-md-3 col-lg-2">
                        <a href="?category=<?php echo $cat['slug']; ?>" 
                           class="category-card text-decoration-none text-center">
                            <div class="card border-0 shadow-sm h-100 hover-lift">
                                <div class="card-body p-3">
                                    <div class="avatar-lg bg-light rounded-circle d-inline-flex align-items-center justify-content-center mb-2">
                                        <i class="fas fa-<?php echo getCategoryIcon($cat['name']); ?> text-primary"></i>
                                    </div>
                                    <h6 class="mb-0"><?php echo $cat['name']; ?></h6>
                                    <small class="text-muted">
                                        <?php 
                                        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category = ? AND approved_status = 'approved'");
                                        $stmt->execute([$cat['slug']]);
                                        echo $stmt->fetchColumn();
                                        ?> products
                                    </small>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>All Products</h3>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" id="gridViewBtn" title="Grid View">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="btn btn-outline-secondary" id="listViewBtn" title="List View">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
            
            <div class="row" id="productsGrid">
                <?php if (empty($products)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>No products found</h4>
                        <p class="text-muted mb-4">Try adjusting your search or filter criteria</p>
                        <a href="?" class="btn btn-primary">
                            <i class="fas fa-undo me-2"></i> Clear Filters
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-xl-3 col-lg-4 col-md-6 mb-4 product-card">
                            <div class="card h-100 border-0 shadow-sm hover-shadow">
                                <!-- Product Image -->
                                <div class="position-relative overflow-hidden" style="height: 200px;">
                                    <?php if ($product['image']): ?>
                                        <img src="<?php echo SITE_URL . 'assets/images/products/' . $product['image']; ?>" 
                                             class="card-img-top h-100 object-fit-cover" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             style="transition: transform 0.5s ease;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light h-100 d-flex align-items-center justify-content-center">
                                            <i class="fas fa-box fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Product Badges -->
                                    <div class="position-absolute top-0 start-0 m-2">
                                        <?php if ($product['featured']): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-star me-1"></i> Featured
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                            <span class="badge bg-danger">
                                                -<?php echo round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Quick Actions -->
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <button class="btn btn-sm btn-light wishlist-toggle" 
                                                data-product-id="<?php echo $product['id']; ?>"
                                                data-in-wishlist="<?php echo in_array($product['id'], $wishlist_items) ? 'true' : 'false'; ?>"
                                                title="<?php echo in_array($product['id'], $wishlist_items) ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                            <i class="<?php echo in_array($product['id'], $wishlist_items) ? 'fas' : 'far'; ?> fa-heart <?php echo in_array($product['id'], $wishlist_items) ? 'text-danger' : ''; ?>"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Stock Status -->
                                    <div class="position-absolute bottom-0 start-0 m-2">
                                        <?php if ($product['stock'] == 0): ?>
                                            <span class="badge bg-secondary">Out of Stock</span>
                                        <?php elseif ($product['stock'] < 10): ?>
                                            <span class="badge bg-warning">Only <?php echo $product['stock']; ?> left</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">In Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Product Info -->
                                <div class="card-body d-flex flex-column">
                                    <!-- Category -->
                                    <div class="mb-2">
                                        <span class="badge bg-light text-dark">
                                            <?php echo $product['category_name'] ?? $product['category']; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Product Name -->
                                    <h5 class="card-title product-name">
                                        <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                                           class="text-decoration-none text-dark">
                                           <?php echo htmlspecialchars(substr($product['name'], 0, 50)); ?>
                                           <?php echo strlen($product['name']) > 50 ? '...' : ''; ?>
                                        </a>
                                    </h5>
                                    
                                    <!-- Rating -->
                                    <div class="mb-2">
                                        <div class="d-flex align-items-center">
                                            <?php
                                            $rating = $product['average_rating'] ?? 0;
                                            for ($i = 1; $i <= 5; $i++):
                                                $starClass = $i <= floor($rating) ? 'fas fa-star text-warning' : 
                                                           ($i <= ceil($rating) ? 'fas fa-star-half-alt text-warning' : 'far fa-star text-light');
                                            ?>
                                                <i class="<?php echo $starClass; ?>"></i>
                                            <?php endfor; ?>
                                            <small class="text-muted ms-2">(<?php echo $product['review_count'] ?? 0; ?>)</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Price -->
                                    <div class="mt-auto">
                                        <div class="d-flex align-items-center mb-3">
                                            <h4 class="text-primary mb-0">
                                                $<?php echo number_format($product['price'], 2); ?>
                                            </h4>
                                            <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                                <small class="text-muted text-decoration-line-through ms-2">
                                                    $<?php echo number_format($product['old_price'], 2); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Add to Cart -->
                                        <?php if ($product['stock'] > 0): ?>
                                            <?php if (isset($cart_items[$product['id']])): ?>
                                                <div class="btn-group w-100">
                                                    <button class="btn btn-outline-primary decrease-cart" 
                                                            data-product-id="<?php echo $product['id']; ?>">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <button class="btn btn-outline-primary disabled" style="min-width: 50px;">
                                                        <?php echo $cart_items[$product['id']]; ?>
                                                    </button>
                                                    <button class="btn btn-outline-primary increase-cart" 
                                                            data-product-id="<?php echo $product['id']; ?>"
                                                            data-max-stock="<?php echo $product['stock']; ?>">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                                <?php else: ?>
                                                <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                                           class="text-decoration-none  w-100 btn btn-outline-primary mb-2">
                                           Details
                                        </a>
                                                <button class="btn btn-primary w-100 add-to-cart" 
                                                        data-product-id="<?php echo $product['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                    <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                                </button>

                                                
                                        
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-secondary w-100" disabled>
                                                <i class="fas fa-times me-2"></i> Out of Stock
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo buildQueryString(['page' => $page-1]); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo buildQueryString(['page' => $i]); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo buildQueryString(['page' => $page+1]); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Featured Products -->
        <?php
        $stmt = $db->prepare("SELECT * FROM products WHERE featured = 1 AND approved_status = 'approved' AND stock > 0 LIMIT 8");
        $stmt->execute();
        $featured_products = $stmt->fetchAll();
        
        if (!empty($featured_products)):
        ?>
            <div class="mb-5" id="featured">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3>Featured Products</h3>
                    <a href="?featured=1" class="btn btn-outline-primary btn-sm">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                <div class="row g-3">
                    <?php foreach ($featured_products as $product): ?>
                        <div class="col-xl-3 col-lg-4 col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="position-relative" style="height: 150px;">
                                    <?php if ($product['image']): ?>
                                        <img src="<?php echo SITE_URL . 'assets/images/products/' . $product['image']; ?>" 
                                             class="card-img-top h-100 object-fit-cover" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light h-100 d-flex align-items-center justify-content-center">
                                            <i class="fas fa-box fa-2x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="position-absolute top-0 start-0 m-2">
                                        <span class="badge bg-warning">
                                            <i class="fas fa-star me-1"></i> Featured
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title mb-2">
                                        <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?>
                                            <?php echo strlen($product['name']) > 40 ? '...' : ''; ?>
                                        </a>
                                    </h6>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="text-primary mb-0">
                                            $<?php echo number_format($product['price'], 2); ?>
                                        </h5>
                                        <button class="btn btn-sm btn-outline-primary add-to-cart" 
                                                data-product-id="<?php echo $product['id']; ?>">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Helpers -->
<?php
function getCategoryIcon($categoryName) {
    $icons = [
        'Electronics' => 'tv',
        'Fashion' => 'tshirt',
        'Home & Living' => 'home',
        'Books' => 'book',
        'Sports' => 'futbol',
        'Beauty' => 'spa',
        'Mobiles' => 'mobile-alt',
        'Laptops' => 'laptop',
        'Men Fashion' => 'male',
        'Women Fashion' => 'female'
    ];
    
    return $icons[$categoryName] ?? 'shopping-bag';
}

function buildQueryString($new_params = []) {
    $params = $_GET;
    foreach ($new_params as $key => $value) {
        $params[$key] = $value;
    }
    return http_build_query($params);
}
?>

<!-- Shop Page CSS -->
<style>
.shop-page .card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.shop-page .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.hover-shadow {
    transition: box-shadow 0.3s ease;
}

.hover-shadow:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
}

.hover-lift {
    transition: transform 0.3s ease;
}

.hover-lift:hover {
    transform: translateY(-3px);
}

.category-card .avatar-lg {
    width: 60px;
    height: 60px;
}

.object-fit-cover {
    object-fit: cover;
}

.product-card .product-name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 48px;
}

#productsGrid.list-view .col-md-6 {
    flex: 0 0 100%;
    max-width: 100%;
}

#productsGrid.list-view .product-card {
    display: flex;
    flex-direction: row;
    height: auto;
}

#productsGrid.list-view .product-card .card {
    flex-direction: row;
}

#productsGrid.list-view .product-card .card > div:first-child {
    flex: 0 0 200px;
    max-width: 200px;
}

#productsGrid.list-view .product-card .card-body {
    flex: 1;
}
</style>

<!-- //// JavaScript section (shop.php ke end me) -->

<script>
$(document).ready(function() {
    // AJAX calls ko correct URL par point karo
    const baseUrl = '<?php echo SITE_URL; ?>';
    
    // Add to cart function ko update karo
    function addToCart(productId, quantity, productName = '') {
        $.ajax({
            url: '<?php echo SITE_URL; ?>user/ajax/add-to-cart.php', // Correct path
            type: 'POST',
            data: {
                product_id: productId,
                quantity: quantity
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update cart count in header
                    $('.cart-count').text(response.cart_count);
                    
                    // Update UI for this product
                    const addButton = $(`.add-to-cart[data-product-id="${productId}"]`);
                    if (addButton.length) {
                        const productCard = addButton.closest('.product-card');
                        const stock = addButton.closest('.card-body').find('.increase-cart').data('max-stock') || 10;
                        
                        const qtyControls = `
                            <div class="btn-group w-100">
                                <button class="btn btn-outline-primary decrease-cart" data-product-id="${productId}">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <button class="btn btn-outline-primary disabled" style="min-width: 50px;">1</button>
                                <button class="btn btn-outline-primary increase-cart" data-product-id="${productId}" data-max-stock="${stock}">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        `;
                        
                        addButton.replaceWith(qtyControls);
                        
                        // Re-bind events
                        productCard.find('.increase-cart').click(function() {
                            const pid = $(this).data('product-id');
                            const maxStock = $(this).data('max-stock');
                            const currentQty = parseInt($(this).siblings('button:eq(1)').text());
                            
                            updateCartQuantity(pid, currentQty + 1);
                        });
                        
                        productCard.find('.decrease-cart').click(function() {
                            const pid = $(this).data('product-id');
                            const currentQty = parseInt($(this).siblings('button:eq(1)').text());
                            
                            if (currentQty > 1) {
                                updateCartQuantity(pid, currentQty - 1);
                            } else {
                                removeFromCart(pid);
                            }
                        });
                    }
                    
                    // Show success message
                    if (productName) {
                        showToast(`Added <strong>${productName}</strong> to cart!`, 'success');
                    } else {
                        showToast('Product added to cart!', 'success');
                    }
                } else {
                    showToast(response.message || 'Error adding to cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Network error: ' + error, 'error');
            }
        });
    }
    
    function updateCartQuantity(productId, quantity) {
        $.ajax({
            url: '<?php echo SITE_URL; ?>user/ajax/update-cart.php',
            type: 'POST',
            data: {
                product_id: productId,
                quantity: quantity
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const qtyButton = $(`.increase-cart[data-product-id="${productId}"]`).siblings('button:eq(1)');
                    qtyButton.text(quantity);
                    
                    // Update cart count in header
                    $('.cart-count').text(response.cart_count);
                    showToast('Cart updated', 'success');
                } else {
                    showToast(response.message || 'Error updating cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Network error: ' + error, 'error');
            }
        });
    }
    
    function removeFromCart(productId) {
        $.ajax({
            url: '<?php echo SITE_URL; ?>user/ajax/remove-from-cart.php',
            type: 'POST',
            data: { product_id: productId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update cart count
                    $('.cart-count').text(response.cart_count);
                    
                    // Replace with add button
                    const productCard = $(`.decrease-cart[data-product-id="${productId}"]`).closest('.product-card');
                    const productName = productCard.find('.product-name a').text().trim();
                    
                    const addButton = `
                        <button class="btn btn-primary w-100 add-to-cart" 
                                data-product-id="${productId}"
                                data-product-name="${productName}">
                            <i class="fas fa-cart-plus me-2"></i> Add to Cart
                        </button>
                    `;
                    
                    productCard.find('.btn-group').replaceWith(addButton);
                    
                    // Re-bind add to cart event
                    productCard.find('.add-to-cart').click(function() {
                        const pid = $(this).data('product-id');
                        const pname = $(this).data('product-name');
                        addToCart(pid, 1, pname);
                    });
                    
                    showToast('Removed from cart', 'info');
                }
            },
            error: function(xhr, status, error) {
                showToast('Network error: ' + error, 'error');
            }
        });
    }
    
    // Wishlist function update
    $('.wishlist-toggle').click(function() {
        const button = $(this);
        const productId = button.data('product-id');
        const isInWishlist = button.data('in-wishlist') === 'true';
        
        $.ajax({
            url: '<?php echo SITE_URL; ?>user/ajax/toggle-wishlist.php',
            type: 'POST',
            data: {
                product_id: productId,
                action: isInWishlist ? 'remove' : 'add'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const icon = button.find('i');
                    if (response.action === 'added') {
                        icon.removeClass('far').addClass('fas text-danger');
                        button.data('in-wishlist', 'true');
                        button.attr('title', 'Remove from Wishlist');
                        showToast('Added to wishlist!', 'success');
                    } else {
                        icon.removeClass('fas text-danger').addClass('far');
                        button.data('in-wishlist', 'false');
                        button.attr('title', 'Add to Wishlist');
                        showToast('Removed from wishlist', 'info');
                    }
                } else {
                    showToast(response.message || 'Error updating wishlist', 'error');
                }
            },
            error: function(xhr, status, error) {
                showToast('Network error: ' + error, 'error');
            }
        });
    });
    
    // Bind add to cart buttons
    $('.add-to-cart').click(function() {
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        addToCart(productId, 1, productName);
    });
    
    // Bind increase cart buttons
    $('.increase-cart').click(function() {
        const productId = $(this).data('product-id');
        const maxStock = $(this).data('max-stock');
        const currentQty = parseInt($(this).siblings('button:eq(1)').text());
        
        if (currentQty < maxStock) {
            updateCartQuantity(productId, currentQty + 1);
        } else {
            showToast('Cannot add more than available stock', 'warning');
        }
    });
    
    // Bind decrease cart buttons
    $('.decrease-cart').click(function() {
        const productId = $(this).data('product-id');
        const currentQty = parseInt($(this).siblings('button:eq(1)').text());
        
        if (currentQty > 1) {
            updateCartQuantity(productId, currentQty - 1);
        } else {
            removeFromCart(productId);
        }
    });
    
    // Rest of your shop page JavaScript...
});
</script>

<?php require_once '../../includes/footer.php'; ?>