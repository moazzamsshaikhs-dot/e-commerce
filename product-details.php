<?php
require_once 'includes/config.php';

// Check if product ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Fetch product details
try {
    $db = getDB();
    
    // Increment product views
    $stmt = $db->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
    $stmt->execute([$product_id]);
    
    // Get product details with vendor info
    $query = "SELECT 
                p.*,
                u.username as vendor_username,
                u.full_name as vendor_name,
                u.vendor_rating,
                u.vendor_status,
                u.vendor_since,
                u.profile_pic as vendor_image,
                c.name as category_name,
                c.parent_id,
                (SELECT name FROM categories WHERE id = c.parent_id) as parent_category,
                (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) as total_reviews,
                (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id AND r.is_approved = 1) as avg_rating,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as total_sold
              FROM products p 
              LEFT JOIN users u ON p.vendor_id = u.id 
              LEFT JOIN categories c ON p.category = c.slug 
              WHERE p.id = ? AND p.approved_status = 'approved' 
              AND u.vendor_status = 'approved' 
              AND p.stock > 0";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error'] = 'Product not found or unavailable.';
        header('Location: index.php');
        exit();
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading product details.';
    header('Location: index.php');
    exit();
}

// Get product images (if multiple images stored)
$product_images = [];
if (!empty($product['images'])) {
    $product_images = json_decode($product['images'], true);
    if (!is_array($product_images)) {
        $product_images = [];
    }
}

// Add main image to images array
if (!empty($product['image'])) {
    array_unshift($product_images, $product['image']);
}

// Get related products (same category, same vendor, or similar price range)
try {
    $db = getDB();
    
    $related_query = "SELECT p.*, u.username as vendor_username 
                      FROM products p 
                      LEFT JOIN users u ON p.vendor_id = u.id 
                      WHERE p.id != ? 
                      AND p.approved_status = 'approved' 
                      AND p.stock > 0 
                      AND u.vendor_status = 'approved'
                      AND (p.category = ? OR p.vendor_id = ? OR p.price BETWEEN ? AND ?)
                      ORDER BY RAND() 
                      LIMIT 8";
    
    $min_price = $product['price'] * 0.5;
    $max_price = $product['price'] * 1.5;
    
    $stmt = $db->prepare($related_query);
    $stmt->execute([
        $product_id,
        $product['category'],
        $product['vendor_id'],
        $min_price,
        $max_price
    ]);
    $related_products = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $related_products = [];
}

// Get product reviews with user info
try {
    $db = getDB();
    
    $reviews_query = "SELECT r.*, u.username, u.full_name, u.profile_pic, 
                             DATE_FORMAT(r.created_at, '%M %d, %Y') as review_date
                      FROM reviews r 
                      LEFT JOIN users u ON r.user_id = u.id 
                      WHERE r.product_id = ? AND r.is_approved = 1 
                      ORDER BY r.created_at DESC 
                      LIMIT 10";
    
    $stmt = $db->prepare($reviews_query);
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll();
    
    // Get review statistics
    $stats_query = "SELECT 
                      COUNT(*) as total,
                      AVG(rating) as average,
                      SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                      SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                      SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                      SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                      SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
                    FROM reviews 
                    WHERE product_id = ? AND is_approved = 1";
    
    $stmt = $db->prepare($stats_query);
    $stmt->execute([$product_id]);
    $review_stats = $stmt->fetch();
    
} catch(PDOException $e) {
    $reviews = [];
    $review_stats = null;
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please login to add items to cart.';
        $_SESSION['redirect_url'] = 'product-details.php?id=' . $product_id;
        header('Location: login.php');
        exit();
    }
    
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if ($quantity < 1) {
        $_SESSION['error'] = 'Quantity must be at least 1.';
    } elseif ($quantity > $product['stock']) {
        $_SESSION['error'] = 'Only ' . $product['stock'] . ' items available in stock.';
    } else {
        try {
            $db = getDB();
            $user_id = $_SESSION['user_id'];
            
            // Check if product already in cart
            $stmt = $db->prepare("SELECT * FROM cart_items WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $existing_item = $stmt->fetch();
            
            if ($existing_item) {
                // Update quantity
                $new_quantity = $existing_item['quantity'] + $quantity;
                if ($new_quantity <= $product['stock']) {
                    $stmt = $db->prepare("UPDATE cart_items SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_quantity, $existing_item['id']]);
                    $_SESSION['success'] = 'Product quantity updated in cart!';
                } else {
                    $_SESSION['error'] = 'Cannot add more than available stock.';
                }
            } else {
                // Add new item to cart
                $stmt = $db->prepare("INSERT INTO cart_items (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $product_id, $quantity]);
                $_SESSION['success'] = 'Product added to cart successfully!';
            }
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Error adding product to cart. Please try again.';
        }
    }
    
    // Redirect to refresh page
    header('Location: product-details.php?id=' . $product_id);
    exit();
}

// Handle add to wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_wishlist'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please login to add items to wishlist.';
        $_SESSION['redirect_url'] = 'product-details.php?id=' . $product_id;
        header('Location: login.php');
        exit();
    }
    
    try {
        $db = getDB();
        $user_id = $_SESSION['user_id'];
        
        // Check if already in wishlist
        $stmt = $db->prepare("SELECT * FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['warning'] = 'Product is already in your wishlist.';
        } else {
            $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $product_id]);
            $_SESSION['success'] = 'Product added to wishlist successfully!';
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error adding product to wishlist.';
    }
    
    header('Location: product-details.php?id=' . $product_id);
    exit();
}

$page_title = htmlspecialchars($product['name']) . ' - ' . SITE_NAME;
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="container py-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
        <li class="breadcrumb-item"><a href="category.php?slug=<?php echo $product['category']; ?>"><?php echo htmlspecialchars($product['category_name'] ?? $product['category']); ?></a></li>
        <?php if (!empty($product['parent_category'])): ?>
        <li class="breadcrumb-item"><a href="category.php?slug=<?php echo $product['parent_id']; ?>"><?php echo htmlspecialchars($product['parent_category']); ?></a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
    </ol>
</nav>

<div class="container py-4">
    <!-- Messages -->
    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Left Column - Product Images -->
        <div class="col-lg-6">
            <div class="product-images">
                <!-- Main Image -->
                <div class="main-image mb-3">
                    <img id="mainProductImage" 
                         src="<?php echo SITE_URL . 'assets/images/products/' . ($product_images[0] ?? 'default.png'); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="img-fluid rounded shadow-sm"
                         data-bs-toggle="modal" 
                         data-bs-target="#imageModal"
                         style="cursor: zoom-in;">
                </div>
                
                <!-- Thumbnail Images -->
                <?php if (count($product_images) > 1): ?>
                <div class="thumbnail-images">
                    <div class="row g-2">
                        <?php foreach($product_images as $index => $image): ?>
                        <div class="col-3">
                            <img src="<?php echo SITE_URL . 'assets/images/products/' . $image; ?>" 
                                 alt="Product image <?php echo $index + 1; ?>"
                                 class="img-fluid rounded border thumbnail"
                                 data-index="<?php echo $index; ?>"
                                 style="cursor: pointer; height: 80px; object-fit: cover;"
                                 onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Share Product -->
            <div class="share-product mt-4">
                <h6 class="mb-3">Share this product:</h6>
                <div class="social-share d-flex gap-2">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . 'product-details.php?id=' . $product_id); ?>" 
                       target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(SITE_URL . 'product-details.php?id=' . $product_id); ?>&text=<?php echo urlencode($product['name']); ?>" 
                       target="_blank" class="btn btn-outline-info btn-sm">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($product['name'] . ' - ' . SITE_URL . 'product-details.php?id=' . $product_id); ?>" 
                       target="_blank" class="btn btn-outline-success btn-sm">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="mailto:?subject=Check out this product&body=<?php echo urlencode($product['name'] . ' - ' . SITE_URL . 'product-details.php?id=' . $product_id); ?>" 
                       class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Product Details -->
        <div class="col-lg-6">
            <div class="product-details">
                <!-- Product Title -->
                <h1 class="product-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <!-- Vendor Info -->
                <div class="vendor-info mb-3">
                    <small class="text-muted">Sold by:</small>
                    <a href="vendor.php?id=<?php echo $product['vendor_id']; ?>" class="text-decoration-none">
                        <span class="badge bg-light text-dark border">
                            <i class="fas fa-store me-1"></i>
                            <?php echo htmlspecialchars($product['vendor_name'] ?? $product['vendor_username']); ?>
                        </span>
                    </a>
                    <?php if ($product['vendor_rating'] > 0): ?>
                    <span class="ms-2">
                        <?php for($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= floor($product['vendor_rating'])): ?>
                                <i class="fas fa-star text-warning" style="font-size: 0.8rem;"></i>
                            <?php elseif ($i == ceil($product['vendor_rating']) && fmod($product['vendor_rating'], 1) >= 0.5): ?>
                                <i class="fas fa-star-half-alt text-warning" style="font-size: 0.8rem;"></i>
                            <?php else: ?>
                                <i class="far fa-star text-warning" style="font-size: 0.8rem;"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <small class="text-muted">(<?php echo number_format($product['vendor_rating'], 1); ?>)</small>
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- Rating and Reviews -->
                <div class="rating-reviews mb-3">
                    <div class="d-flex align-items-center">
                        <div class="product-rating me-3">
                            <div class="stars">
                                <?php
                                $avg_rating = (float)$product['avg_rating'];
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
                            <span class="ms-2">
                                <strong><?php echo number_format($avg_rating, 1); ?></strong>
                                <small class="text-muted">(<?php echo $product['total_reviews']; ?> reviews)</small>
                            </span>
                        </div>
                        <div class="sold-badge">
                            <span class="badge bg-success">
                                <i class="fas fa-shopping-cart me-1"></i>
                                <?php echo number_format($product['total_sold']); ?> sold
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Price -->
                <div class="price-section mb-3">
                    <div class="d-flex align-items-center">
                        <h2 class="text-primary mb-0">$<?php echo number_format($product['price'], 2); ?></h2>
                        
                        <?php if ($product['old_price'] && $product['old_price'] > $product['price']): 
                            $discount_percent = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100);
                        ?>
                        <div class="ms-3">
                            <span class="text-muted text-decoration-line-through">$<?php echo number_format($product['old_price'], 2); ?></span>
                            <span class="badge bg-danger ms-2">-<?php echo $discount_percent; ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($product['stock'] > 0): ?>
                    <div class="stock-status text-success">
                        <i class="fas fa-check-circle me-1"></i>
                        In Stock (<?php echo $product['stock']; ?> available)
                    </div>
                    <?php else: ?>
                    <div class="stock-status text-danger">
                        <i class="fas fa-times-circle me-1"></i>
                        Out of Stock
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Product Highlights -->
                <div class="product-highlights mb-4">
                    <h6>Product Highlights:</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i> Free shipping on orders over $50</li>
                        <li><i class="fas fa-check text-success me-2"></i> 30-day return policy</li>
                        <li><i class="fas fa-check text-success me-2"></i> Secure payment options</li>
                        <li><i class="fas fa-check text-success me-2"></i> 24/7 customer support</li>
                    </ul>
                </div>
                
                <!-- Quantity and Cart Actions -->
                <?php if ($product['stock'] > 0): ?>
                <div class="cart-actions mb-4">
                    <form method="POST" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="quantity" class="form-label">Quantity:</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary" onclick="decreaseQuantity()">-</button>
                                <input type="number" 
                                       class="form-control text-center p-2" 
                                       id="quantity" 
                                       name="quantity" 
                                       value="1" 
                                       min="1" 
                                       max="<?php echo $product['stock']; ?>">
                                <button type="button" class="btn btn-outline-secondary" onclick="increaseQuantity()">+</button>
                            </div>
                        </div>
                        
                        <div class="col-md-8 d-grid gap-2">
                            <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg">
                                <i class="fas fa-shopping-cart me-2"></i> Add to Cart
                            </button>
                            
                            <button type="submit" name="add_to_wishlist" class="btn btn-outline-danger">
                                <i class="fas fa-heart me-2"></i> Add to Wishlist
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This product is currently out of stock. Check back later!
                </div>
                <?php endif; ?>
                
                <!-- Product Meta -->
                <div class="product-meta mt-4 pt-4 border-top">
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Category:</small>
                            <a href="category.php?slug=<?php echo $product['category']; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($product['category_name'] ?? $product['category']); ?>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Product ID:</small>
                            <span class="text-muted">#<?php echo $product['id']; ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Views:</small>
                            <span class="text-muted"><?php echo number_format($product['views']); ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Added:</small>
                            <span class="text-muted"><?php echo date('M d, Y', strtotime($product['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Product Description and Details Tabs -->
    <div class="row mt-5">
        <div class="col-12">
            <ul class="nav nav-tabs" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab">
                        <i class="fas fa-file-alt me-2"></i> Description
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                        <i class="fas fa-star me-2"></i> Reviews (<?php echo $product['total_reviews']; ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="vendor-tab" data-bs-toggle="tab" data-bs-target="#vendor" type="button" role="tab">
                        <i class="fas fa-store me-2"></i> Vendor Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="shipping-tab" data-bs-toggle="tab" data-bs-target="#shipping" type="button" role="tab">
                        <i class="fas fa-shipping-fast me-2"></i> Shipping & Returns
                    </button>
                </li>
            </ul>
            
            <div class="tab-content p-4 border border-top-0 rounded-bottom" id="productTabsContent">
                <!-- Description Tab -->
                <div class="tab-pane fade show active" id="description" role="tabpanel">
                    <div class="product-description">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>
                    
                    <?php if (!empty($product['additional_info'])): ?>
                    <div class="additional-info mt-4">
                        <h5>Additional Information</h5>
                        <?php echo nl2br(htmlspecialchars($product['additional_info'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviews" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4">
                            <!-- Rating Summary -->
                            <div class="rating-summary p-4 border rounded bg-light mb-4">
                                <h4 class="text-center mb-3"><?php echo number_format($avg_rating, 1); ?> out of 5</h4>
                                
                                <div class="text-center mb-3">
                                    <div class="stars mb-2">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= floor($avg_rating)): ?>
                                                <i class="fas fa-star text-warning fa-lg"></i>
                                            <?php elseif ($i == ceil($avg_rating) && fmod($avg_rating, 1) >= 0.5): ?>
                                                <i class="fas fa-star-half-alt text-warning fa-lg"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-warning fa-lg"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="text-muted">Based on <?php echo $product['total_reviews']; ?> reviews</p>
                                </div>
                                
                                <?php if ($review_stats): ?>
                                <div class="rating-bars">
                                    <?php for($i = 5; $i >= 1; $i--): 
                                        $count = $review_stats[$i . '_star'] ?? 0;
                                        $percentage = $review_stats['total'] > 0 ? ($count / $review_stats['total']) * 100 : 0;
                                    ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <small class="text-muted me-2" style="width: 30px;"><?php echo $i; ?>★</small>
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted" style="width: 40px;"><?php echo $count; ?></small>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Add Review Button -->
                            <?php if (isset($_SESSION['user_id'])): ?>
                            <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                <i class="fas fa-edit me-2"></i> Write a Review
                            </button>
                            <?php else: ?>
                            <a href="login.php?redirect=product-details.php?id=<?php echo $product_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i> Login to Review
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-8">
                            <!-- Reviews List -->
                            <div class="reviews-list">
                                <?php if (count($reviews) > 0): ?>
                                    <?php foreach($reviews as $review): ?>
                                    <div class="review-item border-bottom pb-4 mb-4">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">
                                                    <?php
                                                    $avatar = $review['profile_pic'] ?? 'default.png';
                                                    $avatar_path = SITE_URL . 'assets/images/avatars/' . $avatar;
                                                    ?>
                                                    <img src="<?php echo $avatar_path; ?>" 
                                                         alt="<?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?>"
                                                         class="rounded-circle"
                                                         style="width: 48px; height: 48px; object-fit: cover;">
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?></h6>
                                                    <small class="text-muted"><?php echo $review['review_date']; ?></small>
                                                </div>
                                            </div>
                                            <div class="rating-stars">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $review['rating']): ?>
                                                        <i class="fas fa-star text-warning"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star text-warning"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($product['total_reviews'] > 10): ?>
                                    <div class="text-center mt-4">
                                        <a href="reviews.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-list me-2"></i> View All Reviews
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Reviews Yet</h5>
                                        <p class="text-muted">Be the first to review this product!</p>
                                        
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                            <i class="fas fa-edit me-2"></i> Write First Review
                                        </button>
                                        <?php else: ?>
                                        <a href="login.php?redirect=product-details.php?id=<?php echo $product_id; ?>" class="btn btn-outline-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i> Login to Review
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Vendor Tab -->
                <div class="tab-pane fade" id="vendor" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="vendor-card text-center p-4 border rounded">
                                <?php
                                $vendor_image = SITE_URL . 'assets/images/avatars/' . ($product['vendor_image'] ?? 'default.png');
                                ?>
                                <img src="<?php echo $vendor_image; ?>" 
                                     alt="<?php echo htmlspecialchars($product['vendor_name'] ?? $product['vendor_username']); ?>"
                                     class="rounded-circle mb-3"
                                     style="width: 100px; height: 100px; object-fit: cover;">
                                
                                <h5><?php echo htmlspecialchars($product['vendor_name'] ?? $product['vendor_username']); ?></h5>
                                
                                <?php if ($product['vendor_rating'] > 0): ?>
                                <div class="vendor-rating mb-2">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= floor($product['vendor_rating'])): ?>
                                            <i class="fas fa-star text-warning"></i>
                                        <?php elseif ($i == ceil($product['vendor_rating']) && fmod($product['vendor_rating'], 1) >= 0.5): ?>
                                            <i class="fas fa-star-half-alt text-warning"></i>
                                        <?php else: ?>
                                            <i class="far fa-star text-warning"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    <span class="ms-1">(<?php echo number_format($product['vendor_rating'], 1); ?>)</span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($product['vendor_since'])): ?>
                                <p class="text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Vendor since <?php echo date('F Y', strtotime($product['vendor_since'])); ?>
                                </p>
                                <?php endif; ?>
                                
                                <a href="vendor.php?id=<?php echo $product['vendor_id']; ?>" class="btn btn-outline-primary mt-2">
                                    <i class="fas fa-store me-2"></i> Visit Store
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="vendor-details">
                                <h5 class="mb-3">About This Seller</h5>
                                
                                <!-- You can add more vendor details here from vendor_settings table -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="vendor-stat p-3 border rounded text-center">
                                            <h4 class="text-primary"><?php 
                                                // Get vendor's total products
                                                try {
                                                    $db = getDB();
                                                    $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'approved'");
                                                    $stmt->execute([$product['vendor_id']]);
                                                    echo $stmt->fetchColumn();
                                                } catch(PDOException $e) {
                                                    echo '0';
                                                }
                                            ?></h4>
                                            <p class="text-muted mb-0">Products</p>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <div class="vendor-stat p-3 border rounded text-center">
                                            <h4 class="text-success"><?php echo number_format($product['total_sold']); ?></h4>
                                            <p class="text-muted mb-0">Items Sold</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="vendor-policies mt-4">
                                    <h6 class="mb-3">Seller Policies:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success me-2"></i> Quality guaranteed products</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Fast shipping</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Responsive customer service</li>
                                        <li><i class="fas fa-check text-success me-2"></i> Secure transactions</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping Tab -->
                <div class="tab-pane fade" id="shipping" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="shipping-info">
                                <h5 class="mb-3"><i class="fas fa-shipping-fast me-2"></i> Shipping Information</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-3">
                                        <strong><i class="fas fa-truck me-2 text-primary"></i> Standard Shipping:</strong>
                                        <p class="mb-0 ms-4">3-5 business days • Free on orders over $50</p>
                                    </li>
                                    <li class="mb-3">
                                        <strong><i class="fas fa-rocket me-2 text-success"></i> Express Shipping:</strong>
                                        <p class="mb-0 ms-4">1-2 business days • $9.99 flat rate</p>
                                    </li>
                                    <li class="mb-3">
                                        <strong><i class="fas fa-globe me-2 text-info"></i> International Shipping:</strong>
                                        <p class="mb-0 ms-4">7-14 business days • Rates vary by destination</p>
                                    </li>
                                    <li>
                                        <strong><i class="fas fa-box me-2 text-warning"></i> Processing Time:</strong>
                                        <p class="mb-0 ms-4">Orders are processed within 24-48 hours</p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="return-info">
                                <h5 class="mb-3"><i class="fas fa-undo me-2"></i> Return Policy</h5>
                                <ul class="list-unstyled">
                                    <li class="mb-3">
                                        <strong><i class="fas fa-calendar-check me-2 text-success"></i> Return Period:</strong>
                                        <p class="mb-0 ms-4">30 days from delivery date</p>
                                    </li>
                                    <li class="mb-3">
                                        <strong><i class="fas fa-check-circle me-2 text-primary"></i> Condition:</strong>
                                        <p class="mb-0 ms-4">Items must be unused and in original packaging</p>
                                    </li>
                                    <li class="mb-3">
                                        <strong><i class="fas fa-dollar-sign me-2 text-warning"></i> Refunds:</strong>
                                        <p class="mb-0 ms-4">Full refund upon receipt and inspection</p>
                                    </li>
                                    <li>
                                        <strong><i class="fas fa-truck-loading me-2 text-danger"></i> Return Shipping:</strong>
                                        <p class="mb-0 ms-4">Free returns for defective items</p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6><i class="fas fa-headset me-2"></i> Need Help?</h6>
                        <p class="mb-0">If you have any questions about shipping or returns, please contact our customer support team at <a href="mailto:support@<?php echo str_replace(['http://', 'https://', 'www.'], '', SITE_URL); ?>">support@<?php echo str_replace(['http://', 'https://', 'www.'], '', SITE_URL); ?></a> or call +1 (555) 123-4567.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (count($related_products) > 0): ?>
    <div class="row mt-5">
        <div class="col-12">
            <h3 class="mb-4">Related Products</h3>
            <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach($related_products as $related): 
                    $related_image = SITE_URL . 'assets/images/products/' . ($related['image'] ?: 'default.png');
                ?>
                <div class="col">
                    <div class="card product-card h-100 border-0 shadow-sm">
                        <a href="product-details.php?id=<?php echo $related['id']; ?>">
                            <img src="<?php echo $related_image; ?>" 
                                 class="card-img-top" 
                                 alt="<?php echo htmlspecialchars($related['name']); ?>"
                                 style="height: 200px; object-fit: contain; padding: 10px;">
                        </a>
                        <div class="card-body">
                            <h6 class="card-title">
                                <a href="product-details.php?id=<?php echo $related['id']; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars(substr($related['name'], 0, 50)); ?>
                                    <?php if (strlen($related['name']) > 50): ?>...<?php endif; ?>
                                </a>
                            </h6>
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="text-primary mb-0">$<?php echo number_format($related['price'], 2); ?></h5>
                                <small class="text-muted">
                                    <i class="fas fa-store me-1"></i>
                                    <?php echo htmlspecialchars($related['vendor_username']); ?>
                                </small>
                            </div>
                        </div>
                        <div class="card-footer bg-white border-top-0">
                            <a href="product-details.php?id=<?php echo $related['id']; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-eye me-2"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalImage" src="" class="img-fluid" style="max-height: 70vh;">
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Write a Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="submit_review.php">
                <div class="modal-body">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Your Rating:</label>
                        <div class="rating-input">
                            <div class="stars">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="far fa-star fa-2x star-rating" data-rating="<?php echo $i; ?>" style="cursor: pointer; color: #ffc107;"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="selectedRating" value="5" required>
                            <div class="rating-labels mt-2">
                                <small id="ratingText" class="text-muted">Excellent</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reviewTitle" class="form-label">Review Title:</label>
                        <input type="text" class="form-control" id="reviewTitle" name="review_title" placeholder="Summarize your experience..." required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reviewText" class="form-label">Your Review:</label>
                        <textarea class="form-control" id="reviewText" name="review_text" rows="4" 
                                  placeholder="Share your experience with this product..." required></textarea>
                        <div class="form-text">Minimum 20 characters</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.product-images .main-image img {
    max-height: 500px;
    width: 100%;
    object-fit: contain;
}

.thumbnail:hover {
    border-color: #4361ee !important;
    transform: scale(1.05);
    transition: all 0.2s ease;
}

.product-title {
    font-size: 2rem;
    font-weight: 700;
    color: #333;
}

.price-section h2 {
    font-size: 2.5rem;
    font-weight: 700;
}

.stock-status {
    font-size: 0.9rem;
}

.cart-actions .input-group {
    max-width: 150px;
}

.nav-tabs .nav-link {
    color: #6c757d;
    font-weight: 500;
    padding: 12px 24px;
}

.nav-tabs .nav-link.active {
    color: #4361ee;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

.tab-content {
    background: #fff;
}

.product-description {
    line-height: 1.8;
    font-size: 1.1rem;
}

.review-item {
    background: #fff;
}

.vendor-stat {
    transition: transform 0.2s ease;
}

.vendor-stat:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.product-card {
    transition: transform 0.2s ease;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.product-card img {
    transition: transform 0.3s ease;
}

.product-card:hover img {
    transform: scale(1.05);
}

.star-rating:hover,
.star-rating:hover ~ .star-rating {
    color: #ffc107 !important;
}

.rating-input .stars {
    direction: rtl;
}

.rating-input .stars i:hover,
.rating-input .stars i:hover ~ i,
.rating-input .stars i.active,
.rating-input .stars i.active ~ i {
    color: #ffc107 !important;
}
</style>

<script>
// Quantity controls
function decreaseQuantity() {
    const input = document.getElementById('quantity');
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
    }
}

function increaseQuantity() {
    const input = document.getElementById('quantity');
    const max = parseInt(input.max);
    if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
    }
}

// Image gallery
document.addEventListener('DOMContentLoaded', function() {
    // Thumbnail click handler
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.getElementById('mainProductImage');
    const modalImage = document.getElementById('modalImage');
    
    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            mainImage.src = this.src;
            
            // Update modal image if open
            if (modalImage) {
                modalImage.src = this.src;
            }
            
            // Add active class
            thumbnails.forEach(t => t.classList.remove('border-primary'));
            this.classList.add('border-primary');
            this.style.borderWidth = '2px';
        });
    });
    
    // Update modal image when main image changes
    if (mainImage && modalImage) {
        mainImage.addEventListener('click', function() {
            modalImage.src = this.src;
        });
    }
    
    // Star rating for review
    const stars = document.querySelectorAll('.star-rating');
    const selectedRating = document.getElementById('selectedRating');
    const ratingText = document.getElementById('ratingText');
    
    const ratingLabels = {
        1: 'Poor',
        2: 'Fair',
        3: 'Good',
        4: 'Very Good',
        5: 'Excellent'
    };
    
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            selectedRating.value = rating;
            ratingText.textContent = ratingLabels[rating];
            
            // Update star display
            stars.forEach(s => {
                const sRating = parseInt(s.getAttribute('data-rating'));
                if (sRating <= rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
        
        star.addEventListener('mouseover', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            
            stars.forEach(s => {
                const sRating = parseInt(s.getAttribute('data-rating'));
                if (sRating <= rating) {
                    s.style.color = '#ffc107';
                } else {
                    s.style.color = '#e4e5e9';
                }
            });
        });
        
        star.addEventListener('mouseout', function() {
            const currentRating = parseInt(selectedRating.value);
            
            stars.forEach(s => {
                const sRating = parseInt(s.getAttribute('data-rating'));
                if (sRating <= currentRating) {
                    s.style.color = '#ffc107';
                } else {
                    s.style.color = '#e4e5e9';
                }
            });
        });
    });
    
    // Tab persistence
    const productTabs = document.querySelector('#productTabs');
    if (productTabs) {
        const tab = new bootstrap.Tab(productTabs);
        
        // Save active tab to localStorage
        productTabs.addEventListener('shown.bs.tab', function (e) {
            localStorage.setItem('activeProductTab', e.target.id);
        });
        
        // Load active tab from localStorage
        const activeTab = localStorage.getItem('activeProductTab');
        if (activeTab) {
            const tabElement = document.querySelector(`#${activeTab}`);
            if (tabElement) {
                tab.show(tabElement);
            }
        }
    }
    
    // Scroll to reviews if URL has #reviews anchor
    if (window.location.hash === '#reviews') {
        const reviewsTab = document.querySelector('#reviews-tab');
        if (reviewsTab) {
            const tab = new bootstrap.Tab(reviewsTab);
            tab.show();
            
            // Scroll to reviews section
            setTimeout(() => {
                document.getElementById('reviews').scrollIntoView({ behavior: 'smooth' });
            }, 100);
        }
    }
    
    // Add to cart quantity validation
    const quantityInput = document.getElementById('quantity');
    if (quantityInput) {
        quantityInput.addEventListener('change', function() {
            let value = parseInt(this.value);
            const max = parseInt(this.max);
            const min = parseInt(this.min);
            
            if (isNaN(value)) value = min;
            if (value < min) value = min;
            if (value > max) value = max;
            
            this.value = value;
        });
    }
});

// Share product
function shareProduct(platform) {
    const url = window.location.href;
    const title = document.querySelector('.product-title').textContent;
    const text = encodeURIComponent(`Check out ${title} on ${SITE_NAME}`);
    
    let shareUrl = '';
    
    switch(platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${text}`;
            break;
        case 'whatsapp':
            shareUrl = `https://api.whatsapp.com/send?text=${text} ${encodeURIComponent(url)}`;
            break;
        case 'email':
            shareUrl = `mailto:?subject=${encodeURIComponent(title)}&body=${text} ${encodeURIComponent(url)}`;
            break;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
