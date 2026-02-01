<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}
if ($_SESSION['user_type'] === 'vendor') {
    $_SESSION['error'] = 'Access denied. Please use vendor dashboard only.';
    redirect(SITE_URL . 'vendor/dashboard.php');
}

$page_title = 'Products - Start Shopping';
require_once '../../includes/header.php';

// Get products with pagination
try {
    $db = getDB();
    
    // Pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    // Get total products count
    $stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE approved_status = 'approved'");
    $total_products = $stmt->fetch()['total'];
    $total_pages = ceil($total_products / $limit);
    
    // Get products with categories
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category = c.slug 
        WHERE p.approved_status = 'approved' 
        AND p.stock > 0
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
    
    // Check wishlist items for current user
    $user_wishlist = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_wishlist = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading products: ' . $e->getMessage();
    $products = [];
    $categories = [];
    $total_pages = 1;
}

// Log activity
logUserActivity($_SESSION['user_id'], 'products_view', 'Viewed products page');
?>

<!-- Products Page -->
<div class="products-page">
    <!-- Header -->
    <div class="container-fluid py-4 bg-white shadow-sm">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-2">Start Shopping</h1>
                    <p class="text-muted mb-0">Browse our wide range of products</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="../cart/cart.php" class="btn btn-outline-primary position-relative">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php 
                            try {
                                $stmt = $db->prepare("SELECT COUNT(*) FROM cart_items WHERE user_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            } catch(Exception $e) {
                                echo '0';
                            }
                            ?>
                        </span>
                    </a>
                    <a href="../wishlist/wishlist.php" class="btn btn-outline-danger">
                        <i class="fas fa-heart"></i> Wishlist
                    </a>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <form method="GET" action="" class="search-form">
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   name="search" 
                                   placeholder="Search products..."
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $category): ?>
                            <option value="<?php echo $category['slug']; ?>"
                                <?php echo (isset($_GET['category']) && $_GET['category'] == $category['slug']) ? 'selected' : ''; ?>>
                                <?php echo $category['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Products Grid -->
    <div class="container py-5">
        <div class="row">
            <?php if(empty($products)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                    <h3 class="text-muted mb-3">No Products Found</h3>
                    <p class="text-muted mb-4">Check back later for new products</p>
                    <a href="../../index.php" class="btn btn-primary">
                        <i class="fas fa-home me-2"></i> Back to Home
                    </a>
                </div>
            <?php else: ?>
                <?php foreach($products as $product): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
                    <div class="card product-card h-100 border-0 shadow-sm hover-shadow">
                        <!-- Product Image -->
                        <div class="position-relative">
                            <?php if($product['image']): ?>
                                <img src="<?php echo SITE_URL . 'assets/images/products/' . $product['image']; ?>" 
                                     class="card-img-top product-image" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <i class="fas fa-box fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Badges -->
                            <div class="position-absolute top-0 start-0 m-2">
                                <?php if($product['featured']): ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-star me-1"></i> Featured
                                    </span>
                                <?php endif; ?>
                                <?php if($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                    <span class="badge bg-danger">
                                        -<?php echo round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Wishlist Button -->
                            <button class="btn btn-sm btn-light position-absolute top-0 end-0 m-2 wishlist-btn" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    <?php echo in_array($product['id'], $user_wishlist) ? 'data-in-wishlist="true"' : ''; ?>>
                                <i class="<?php echo in_array($product['id'], $user_wishlist) ? 'fas' : 'far'; ?> fa-heart 
                                    <?php echo in_array($product['id'], $user_wishlist) ? 'text-danger' : ''; ?>"></i>
                            </button>
                            
                            <!-- Stock Badge -->
                            <div class="position-absolute bottom-0 start-0 m-2">
                                <?php if($product['stock'] == 0): ?>
                                    <span class="badge bg-secondary">Out of Stock</span>
                                <?php elseif($product['stock'] < 10): ?>
                                    <span class="badge bg-warning">Only <?php echo $product['stock']; ?> left</span>
                                <?php else: ?>
                                    <span class="badge bg-success">In Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Card Body -->
                        <div class="card-body d-flex flex-column">
                            <!-- Category -->
                            <div class="mb-2">
                                <span class="badge bg-light text-dark">
                                    <?php echo $product['category_name'] ?? $product['category']; ?>
                                </span>
                            </div>
                            
                            <!-- Product Name -->
                            <h5 class="card-title product-title" title="<?php echo htmlspecialchars($product['name']); ?>">
                                <a href="../product/product-detail.php?id=<?php echo $product['id']; ?>" 
                                   class="text-decoration-none text-dark">
                                   <?php echo strlen($product['name']) > 50 ? substr($product['name'], 0, 50) . '...' : $product['name']; ?>
                                </a>
                            </h5>
                            
                            <!-- Rating -->
                            <div class="mb-2">
                                <?php
                                $rating = $product['average_rating'] ?? 0;
                                $review_count = $product['review_count'] ?? 0;
                                ?>
                                <div class="d-flex align-items-center">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= floor($rating) ? 'text-warning' : 'text-light'; ?>"></i>
                                    <?php endfor; ?>
                                    <small class="text-muted ms-2">(<?php echo $review_count; ?>)</small>
                                </div>
                            </div>
                            
                            <!-- Price -->
                            <div class="mt-auto">
                                <div class="d-flex align-items-center">
                                    <h4 class="text-primary mb-0">
                                        $<?php echo number_format($product['price'], 2); ?>
                                    </h4>
                                    <?php if($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                        <small class="text-muted text-decoration-line-through ms-2">
                                            $<?php echo number_format($product['old_price'], 2); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Add to Cart Button -->
                                <?php if($product['stock'] > 0): ?>
                                    <button class="btn btn-primary w-100 mt-3 add-to-cart" 
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                        <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                    </button>
                                       <a href="../orders/product-details.php?id=<?php echo $product['id']; ?>" 
                                           class="text-decoration-none  w-100 btn btn-outline-primary mt-2">
                                           Details
                                        </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100 mt-3" disabled>
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
        <?php if($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-5">
            <ul class="pagination justify-content-center">
                <?php if($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<!-- Product Card CSS -->
<style>
.product-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #dee2e6;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.product-image {
    transition: transform 0.5s ease;
}

.product-card:hover .product-image {
    transform: scale(1.05);
}

.product-title {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 3em;
}

.wishlist-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.add-to-cart:hover {
    transform: scale(1.02);
}
</style>

<!-- JavaScript for AJAX operations -->
<script>
$(document).ready(function() {
    // Add to Cart
    $('.add-to-cart').click(function() {
        let productId = $(this).data('product-id');
        let productName = $(this).data('product-name');
        
        $.ajax({
            url: '../ajax/add-to-cart.php',
            type: 'POST',
            data: { product_id: productId, quantity: 1 },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    // Update cart count
                    $('.cart-count').text(response.cart_count);
                    
                    // Show success message
                    Swal.fire({
                        icon: 'success',
                        title: 'Added to Cart!',
                        html: `<strong>${productName}</strong> has been added to your cart.`,
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.'
                });
            }
        });
    });
    
    // Wishlist Toggle
    $('.wishlist-btn').click(function() {
        let button = $(this);
        let productId = button.data('product-id');
        let isInWishlist = button.data('in-wishlist') || false;
        
        $.ajax({
            url: '../ajax/toggle-wishlist.php',
            type: 'POST',
            data: { product_id: productId, action: isInWishlist ? 'remove' : 'add' },
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    // Update button
                    let icon = button.find('i');
                    if(response.action === 'added') {
                        icon.removeClass('far').addClass('fas text-danger');
                        button.data('in-wishlist', true);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Added to Wishlist!',
                            showConfirmButton: false,
                            timer: 1000
                        });
                    } else {
                        icon.removeClass('fas text-danger').addClass('far');
                        button.data('in-wishlist', false);
                        
                        Swal.fire({
                            icon: 'info',
                            title: 'Removed from Wishlist',
                            showConfirmButton: false,
                            timer: 1000
                        });
                    }
                }
            }
        });
    });
    
    // Category Filter
    $('#categoryFilter').change(function() {
        let category = $(this).val();
        if(category) {
            window.location.href = `?category=${category}`;
        } else {
            window.location.href = 'products.php';
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>