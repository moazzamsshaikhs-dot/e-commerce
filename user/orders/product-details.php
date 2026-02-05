<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Product not found';
    redirect('shop.php');
}

$page_title = 'Product Details';
require_once '../../includes/header.php';

$db = getDB();
$product_id = (int)$_GET['id'];

// Get product details with vendor info including payment methods
$stmt = $db->prepare("
    SELECT p.*, 
           c.name as category_name,
           u.username as vendor_username,
           u.full_name as vendor_name,
           u.id as vendor_id,
           u.vendor_rating,
           u.vendor_since,
           vs.store_name,
           vs.store_description,
           vs.store_logo,
           vs.payment_methods
    FROM products p
    LEFT JOIN categories c ON p.category = c.slug
    LEFT JOIN users u ON p.vendor_id = u.id
    LEFT JOIN vendor_settings vs ON p.vendor_id = vs.vendor_id
    WHERE p.id = ? AND p.approved_status = 'approved'
");

$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['error'] = 'Product not found or not available';
    redirect('shop.php');
}

// Get similar products (same category)
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category = c.slug 
    WHERE p.category = ? 
    AND p.id != ? 
    AND p.approved_status = 'approved'
    AND p.stock > 0
    ORDER BY RAND() 
    LIMIT 8
");
$stmt->execute([$product['category'], $product_id]);
$similar_products = $stmt->fetchAll();

// Get product reviews with user info
$stmt = $db->prepare("
    SELECT r.*, u.username, u.full_name, u.profile_pic 
    FROM reviews r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? 
    AND r.is_approved = 1 
    ORDER BY r.created_at DESC 
    LIMIT 10
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

// Calculate rating distribution
$rating_distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$total_reviews = 0;
foreach ($reviews as $review) {
    $rating_distribution[$review['rating']]++;
    $total_reviews++;
}

// Get vendor info with payment methods
$vendor_id = $product['vendor_id'];
$stmt = $db->prepare("
    SELECT u.*, vs.*,
           (SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'approved') as total_products,
           (SELECT COUNT(*) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.vendor_id = ?) as total_reviews
    FROM users u
    LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id
    WHERE u.id = ? AND u.user_type = 'vendor'
");
$stmt->execute([$vendor_id, $vendor_id, $vendor_id]);
$vendor = $stmt->fetch();

// Parse vendor payment methods
$vendor_payment_methods = [];
if (!empty($vendor['payment_methods'])) {
    $vendor_payment_methods = json_decode($vendor['payment_methods'], true);
}

// If vendor has no specific payment methods, show all available methods
if (empty($vendor_payment_methods)) {
    $vendor_payment_methods = ['credit_card', 'debit_card', 'paypal', 'bank_transfer', 'cod'];
}

// Map payment methods to icons and labels
$payment_method_icons = [
    'credit_card' => 'fas fa-credit-card',
    'debit_card' => 'fas fa-credit-card',
    'paypal' => 'fab fa-paypal',
    'bank_transfer' => 'fas fa-university',
    'stripe' => 'fab fa-stripe',
    'cod' => 'fas fa-money-bill-wave',
    'apple_pay' => 'fab fa-apple-pay',
    'google_pay' => 'fab fa-google-pay'
];

$payment_method_labels = [
    'credit_card' => 'Credit Card',
    'debit_card' => 'Debit Card',
    'paypal' => 'PayPal',
    'bank_transfer' => 'Bank Transfer',
    'stripe' => 'Stripe',
    'cod' => 'Cash on Delivery',
    'apple_pay' => 'Apple Pay',
    'google_pay' => 'Google Pay'
];

// Get cart items for current user
$cart_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Get wishlist status
$in_wishlist = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    $in_wishlist = $stmt->fetch() ? true : false;
}

// Get product images (assuming multiple images)
$product_images = [];
if ($product['image']) {
    $product_images = [
        $product['image'],
        // Add more images if available in your database structure
        // 'product-image-2.jpg',
        // 'product-image-3.jpg'
    ];
}

// Increment product views
$stmt = $db->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$stmt->execute([$product_id]);

// Log activity
logUserActivity($_SESSION['user_id'], 'product_view', 'Viewed product: ' . $product['name']);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);
    
    // Validate
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Please select a valid rating';
    } elseif (empty($review_text)) {
        $_SESSION['error'] = 'Please write your review';
    } else {
        try {
            // Check if user already reviewed this product
            $stmt = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'You have already reviewed this product';
            } else {
                // Insert review
                $stmt = $db->prepare("
                    INSERT INTO reviews (user_id, product_id, rating, review_text, is_approved, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $product_id, $rating, $review_text]);
                
                // Update product rating stats
                $stmt = $db->prepare("
                    UPDATE products 
                    SET average_rating = (
                        SELECT AVG(rating) FROM reviews WHERE product_id = ?
                    ),
                    review_count = (
                        SELECT COUNT(*) FROM reviews WHERE product_id = ?
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$product_id, $product_id, $product_id]);
                
                $_SESSION['success'] = 'Thank you for your review!';
                redirect('product-details.php?id=' . $product_id);
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error submitting review: ' . $e->getMessage();
        }
    }
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity < 1) {
        $_SESSION['error'] = 'Please enter a valid quantity';
    } elseif ($quantity > $product['stock']) {
        $_SESSION['error'] = 'Requested quantity exceeds available stock';
    } else {
        // Add to cart (you need to implement this function)
        addToCart($_SESSION['user_id'], $product_id, $quantity);
        $_SESSION['success'] = 'Product added to cart successfully!';
        redirect('product-details.php?id=' . $product_id);
    }
}
?>

<div class="product-details-page">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>user/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
            <li class="breadcrumb-item"><a href="?category=<?php echo urlencode($product['category']); ?>"><?php echo $product['category_name']; ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo substr($product['name'], 0, 30); ?>...</li>
        </ol>
    </nav>

    <div class="row">
        <!-- Left Column - Product Images -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <!-- Main Image -->
                    <div class="main-image mb-3 text-center" style="height: 400px;">
                        <?php if (!empty($product_images)): ?>
                            <img id="mainProductImage" 
                                 src="<?php echo SITE_URL . 'assets/images/products/' . $product_images[0]; ?>" 
                                 class="img-fluid rounded h-100 object-fit-contain"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 style="cursor: zoom-in;">
                        <?php else: ?>
                            <div class="bg-light rounded h-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-box fa-4x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Image Gallery -->
                    <?php if (count($product_images) > 1): ?>
                        <div class="image-thumbnails d-flex flex-wrap gap-2 justify-content-center">
                            <?php foreach ($product_images as $index => $image): ?>
                                <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     style="width: 80px; height: 80px; cursor: pointer; border: 2px solid transparent;"
                                     data-image="<?php echo SITE_URL . 'uploads/products/' . $image; ?>">
                                    <img src="<?php echo SITE_URL . 'uploads/products/' . $image; ?>" 
                                         class="img-fluid rounded h-100 object-fit-cover"
                                         alt="Thumbnail <?php echo $index + 1; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Actions -->
                    <div class="d-flex gap-2 mt-4">
                        <!-- Wishlist Button -->
                        <button class="btn btn-outline-danger wishlist-toggle flex-grow-1" 
                                id="wishlistBtn"
                                data-product-id="<?php echo $product_id; ?>"
                                data-in-wishlist="<?php echo $in_wishlist ? 'true' : 'false'; ?>">
                            <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart me-2"></i>
                            <?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                        </button>
                        
                        <!-- Share Button -->
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#shareModal">
                            <i class="fas fa-share-alt me-2"></i> Share
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Vendor Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sold by</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <!-- Vendor Logo/Image -->
                        <div class="me-3">
                            <?php if ($vendor['store_logo']): ?>
                                <img src="<?php echo SITE_URL . 'uploads/vendors/' . $vendor['store_logo']; ?>" 
                                     class="rounded-circle" 
                                     style="width: 60px; height: 60px; object-fit: cover;"
                                     alt="<?php echo htmlspecialchars($vendor['store_name'] ?? $vendor['full_name']); ?>">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="fas fa-store fa-2x text-primary"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Vendor Info -->
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <a href="vendor.php?id=<?php echo $vendor_id; ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($vendor['store_name'] ?? $vendor['full_name']); ?>
                                </a>
                            </h5>
                            
                            <!-- Rating -->
                            <div class="d-flex align-items-center mb-2">
                                <div class="text-warning me-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= floor($vendor['vendor_rating'] ?? 0) ? 'text-warning' : 'far text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-muted small">(<?php echo $vendor['vendor_rating'] ?? 0; ?>)</span>
                            </div>
                            
                            <!-- Stats -->
                            <div class="row small text-muted">
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo $vendor['total_products'] ?? 0; ?></div>
                                    <div>Products</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo $vendor['total_reviews'] ?? 0; ?></div>
                                    <div>Reviews</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold">
                                        <?php echo $vendor['vendor_since'] ? date('Y', strtotime($vendor['vendor_since'])) : 'N/A'; ?>
                                    </div>
                                    <div>Member Since</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vendor Payment Methods -->
                    <?php if (!empty($vendor_payment_methods)): ?>
                        <div class="mt-3">
                            <h6 class="border-bottom pb-2 mb-2">
                                <i class="fas fa-credit-card me-2 text-primary"></i>
                                Payment Methods Accepted
                            </h6>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($vendor_payment_methods as $method): ?>
                                    <?php if (isset($payment_method_icons[$method])): ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="<?php echo $payment_method_icons[$method]; ?> me-1"></i>
                                            <?php echo $payment_method_labels[$method] ?? ucfirst(str_replace('_', ' ', $method)); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Vendor Actions -->
                    <div class="mt-3 d-flex gap-2">
                        <a href="vendor.php?id=<?php echo $vendor_id; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-store me-1"></i> Visit Store
                        </a>
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#contactVendorModal">
                            <i class="fas fa-envelope me-1"></i> Contact
                        </button>
                        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#paymentMethodsModal">
                            <i class="fas fa-credit-card me-1"></i> Payment Info
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Product Info -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <!-- Product Title and Badges -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h1 class="h3 mb-0"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <div>
                            <?php if ($product['featured']): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-star me-1"></i> Featured
                                </span>
                            <?php endif; ?>
                            <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                <span class="badge bg-danger ms-1">
                                    -<?php echo round((($product['old_price'] - $product['price']) / $product['old_price']) * 100); ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Category and SKU -->
                    <div class="mb-3">
                        <span class="badge bg-light text-dark me-2">
                            <i class="fas fa-tag me-1"></i> <?php echo $product['category_name']; ?>
                        </span>
                        <!-- <span class="text-muted small">
                            <i class="fas fa-hashtag me-1"></i> Product ID: <?php echo $product['id']; ?>
                        </span> -->
                    </div>
                    
                    <!-- Rating -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php
                            $rating = $product['average_rating'] ?? 0;
                            for ($i = 1; $i <= 5; $i++):
                                $starClass = $i <= floor($rating) ? 'fas fa-star' : 
                                           ($i <= ceil($rating) ? 'fas fa-star-half-alt' : 'far fa-star');
                            ?>
                                <i class="<?php echo $starClass; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="text-muted me-3">(<?php echo $product['review_count'] ?? 0; ?> reviews)</span>
                        <span class="text-success">
                            <i class="fas fa-eye me-1"></i> <?php echo $product['views']; ?> views
                        </span>
                    </div>
                    
                    <!-- Price -->
                    <div class="mb-4">
                        <h2 class="text-primary mb-2">
                            $<?php echo number_format($product['price'], 2); ?>
                            <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                <small class="text-muted text-decoration-line-through fs-5 ms-2">
                                    $<?php echo number_format($product['old_price'], 2); ?>
                                </small>
                            <?php endif; ?>
                        </h2>
                        <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                            <span class="text-success">
                                <i class="fas fa-save me-1"></i> 
                                Save $<?php echo number_format($product['old_price'] - $product['price'], 2); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Stock Status -->
                    <div class="mb-4">
                        <?php if ($product['stock'] == 0): ?>
                            <span class="badge bg-secondary fs-6 p-2">
                                <i class="fas fa-times-circle me-2"></i> Out of Stock
                            </span>
                        <?php elseif ($product['stock'] < 10): ?>
                            <span class="badge bg-warning fs-6 p-2">
                                <i class="fas fa-exclamation-triangle me-2"></i> Only <?php echo $product['stock']; ?> left in stock
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success fs-6 p-2">
                                <i class="fas fa-check-circle me-2"></i> In Stock
                            </span>
                        <?php endif; ?>
                        <span class="text-muted ms-2">
                            <?php echo $product['sales_count']; ?> sold
                        </span>
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <form method="POST" class="mb-4">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Quantity</label>
                                <div class="input-group">
                                    <button class="btn btn-outline-secondary" type="button" id="decreaseQty">-</button>
                                    <input type="number" 
                                           class="form-control text-center" 
                                           name="quantity" 
                                           id="quantity" 
                                           value="1" 
                                           min="1" 
                                           max="<?php echo $product['stock']; ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="increaseQty">+</button>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <button type="submit" 
                                        name="add_to_cart" 
                                        class="btn btn-primary btn-lg w-100"
                                        <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus me-2"></i> 
                                    <?php echo $product['stock'] == 0 ? 'Out of Stock' : 'Add to Cart'; ?>
                                </button>
                                <?php 
                                // Determine max purchase quantity for Buy Now button
                                $cart_quantity = isset($cart_items[$product_id]) ? (int)$cart_items[$product_id] : 0;
                                $max_purchase_quantity = $product['stock'] - $cart_quantity;
                                if ($product['stock'] > 0 && $max_purchase_quantity <= 0) {
                                    $max_purchase_quantity = 0;
                                }
                            if ($product['stock'] == 0) {
                                $max_purchase_quantity = 0; 
                            }
                                    ?>
                                    
                                    <!-- Buy Now Button with Payment Methods Preview -->
                                    <div class="position-relative">
                                        <a href="payment.php?id=<?php echo $product['id']; ?>&quantity=<?php echo $max_purchase_quantity > 0 ? $max_purchase_quantity : 1; ?>" 
                                        class="text-decoration-none w-100 btn btn-outline-primary mt-2"
                                        id="buyNowBtn">
                                        <i class="fas fa-bolt me-2"></i> Buy Now
                                    </a>
                                    <!-- Payment Methods Preview -->
                                    <?php if (!empty($vendor_payment_methods)): ?>
                                        <div class="payment-methods-preview mt-2">
                                            <small class="text-muted d-block mb-1">
                                                <i class="fas fa-lock me-1 text-success"></i>
                                                Secure payment via:
                                            </small>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php 
                                                // Show only first 3 payment methods for preview
                                                $preview_methods = array_slice($vendor_payment_methods, 0, 3);
                                                foreach ($preview_methods as $method): 
                                                    if (isset($payment_method_icons[$method])): 
                                                ?>
                                                    <span class="badge bg-light text-muted border">
                                                        <i class="<?php echo $payment_method_icons[$method]; ?>"></i>
                                                    </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                
                                                // If more than 3 methods, show count
                                                if (count($vendor_payment_methods) > 3): 
                                                ?>
                                                    <span class="badge bg-light text-muted border">
                                                        +<?php echo count($vendor_payment_methods) - 3; ?> more
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Product Description -->
                    <div class="mb-4">
                        <h5 class="mb-3">Description</h5>
                        <div class="product-description">
                            <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?>
                        </div>
                    </div>
                    
                    <!-- Product Specifications -->
                    <div class="mb-4">
                        <h5 class="mb-3">Specifications</h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <th style="width: 30%;">Category</th>
                                        <td><?php echo $product['category_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Stock</th>
                                        <td><?php echo $product['stock']; ?> units</td>
                                    </tr>
                                    <tr>
                                        <th>Condition</th>
                                        <td>New</td>
                                    </tr>
                                    <tr>
                                        <th>Weight</th>
                                        <td>Approx. 1kg</td>
                                    </tr>
                                    <tr>
                                        <th>Dimensions</th>
                                        <td>10 × 10 × 10 cm</td>
                                    </tr>
                                    <!-- Payment Methods Row -->
                                    <tr>
                                        <th>Payment Options</th>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($vendor_payment_methods as $method): ?>
                                                    <?php if (isset($payment_method_icons[$method])): ?>
                                                        <span class="badge bg-light text-dark border small">
                                                            <i class="<?php echo $payment_method_icons[$method]; ?> me-1"></i>
                                                            <?php echo $payment_method_labels[$method] ?? ucfirst(str_replace('_', ' ', $method)); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reviews Section -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Customer Reviews</h5>
                </div>
                <div class="card-body">
                    <!-- Rating Summary -->
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <div class="display-4 fw-bold text-primary"><?php echo number_format($product['average_rating'] ?? 0, 1); ?></div>
                            <div class="text-warning mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= floor($rating) ? 'text-warning' : 'far text-muted'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="text-muted small"><?php echo $total_reviews; ?> reviews</div>
                        </div>
                        <div class="col-md-8">
                            <?php for ($i = 5; $i >= 1; $i--): 
                                $percentage = $total_reviews > 0 ? ($rating_distribution[$i] / $total_reviews) * 100 : 0;
                            ?>
                                <div class="row align-items-center mb-2">
                                    <div class="col-2">
                                        <span class="text-muted"><?php echo $i; ?> <i class="fas fa-star text-warning"></i></span>
                                    </div>
                                    <div class="col-8">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="col-2 text-end">
                                        <span class="text-muted small"><?php echo $rating_distribution[$i]; ?></span>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Write Review Button -->
                    <div class="text-center mb-4">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                            <i class="fas fa-edit me-2"></i> Write a Review
                        </button>
                    </div>
                    
                    <!-- Reviews List -->
                    <div class="reviews-list">
                        <?php if (empty($reviews)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No reviews yet. Be the first to review!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item border-top pt-3 mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <?php if ($review['profile_pic'] && $review['profile_pic'] !== 'default.png'): ?>
                                                    <img src="<?php echo SITE_URL . 'uploads/profiles/' . $review['profile_pic']; ?>" 
                                                         class="rounded-circle me-2" 
                                                         style="width: 40px; height: 40px; object-fit: cover;"
                                                         alt="<?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?>">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user text-primary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?></strong>
                                                    <div class="text-warning small">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'far text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                        </div>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    
                                    <!-- Review Actions -->
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-outline-secondary me-2">
                                            <i class="fas fa-thumbs-up me-1"></i> Helpful (0)
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-flag me-1"></i> Report
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if ($total_reviews > 10): ?>
                                <div class="text-center mt-4">
                                    <a href="reviews.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline-primary">
                                        View All Reviews <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Similar Products -->
    <?php if (!empty($similar_products)): ?>
        <div class="mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Similar Products</h3>
                <a href="?category=<?php echo urlencode($product['category']); ?>" class="btn btn-outline-primary btn-sm">
                    View All <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <div class="row g-4">
                <?php foreach ($similar_products as $similar): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100 hover-shadow">
                            <!-- Product Image -->
                            <div class="position-relative" style="height: 200px;">
                                <a href="product-details.php?id=<?php echo $similar['id']; ?>">
                                    <?php if ($similar['image']): ?>
                                        <img src="<?php echo SITE_URL . 'assets/images/products/' . $similar['image']; ?>" 
                                             class="card-img-top h-100 object-fit-cover" 
                                             alt="<?php echo htmlspecialchars($similar['name']); ?>">
                                    <?php else: ?>
                                        <div class="card-img-top bg-light h-100 d-flex align-items-center justify-content-center">
                                            <i class="fas fa-box fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            </div>
                            
                            <!-- Product Info -->
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title">
                                    <a href="product-details.php?id=<?php echo $similar['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars(substr($similar['name'], 0, 40)); ?>
                                        <?php echo strlen($similar['name']) > 40 ? '...' : ''; ?>
                                    </a>
                                </h6>
                                
                                <!-- Rating -->
                                <div class="mb-2 small">
                                    <div class="d-flex align-items-center">
                                        <?php
                                        $similar_rating = $similar['average_rating'] ?? 0;
                                        for ($i = 1; $i <= 5; $i++):
                                            $starClass = $i <= floor($similar_rating) ? 'fas fa-star text-warning' : 
                                                       ($i <= ceil($similar_rating) ? 'fas fa-star-half-alt text-warning' : 'far fa-star text-light');
                                        ?>
                                            <i class="<?php echo $starClass; ?>"></i>
                                        <?php endfor; ?>
                                        <span class="text-muted ms-2">(<?php echo $similar['review_count'] ?? 0; ?>)</span>
                                    </div>
                                </div>
                                
                                <!-- Price -->
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="text-primary mb-0">
                                            $<?php echo number_format($similar['price'], 2); ?>
                                        </h5>
                                        <button class="btn btn-sm btn-outline-primary add-to-cart-quick" 
                                                data-product-id="<?php echo $similar['id']; ?>">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modals -->

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share this product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 text-center">
                    <div class="col-3">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . 'user/orders/product-details.php?id=' . $product_id); ?>" 
                           target="_blank" 
                           class="btn btn-primary w-100">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Check out this product: ' . $product['name']); ?>&url=<?php echo urlencode(SITE_URL . 'user/orders/product-details.php?id=' . $product_id); ?>" 
                           target="_blank" 
                           class="btn btn-info w-100 text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="https://wa.me/?text=<?php echo urlencode('Check out this product: ' . $product['name'] . ' ' . SITE_URL . 'user/orders/product-details.php?id=' . $product_id); ?>" 
                           target="_blank" 
                           class="btn btn-success w-100">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="mailto:?subject=<?php echo urlencode('Check out this product: ' . $product['name']); ?>&body=<?php echo urlencode(SITE_URL . 'user/orders/product-details.php?id=' . $product_id); ?>" 
                           class="btn btn-danger w-100">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Copy Link -->
                <div class="mt-4">
                    <label class="form-label">Product Link</label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               id="productLink" 
                               value="<?php echo SITE_URL . 'user/orders/product-details.php?id=' . $product_id; ?>" 
                               readonly>
                        <button class="btn btn-outline-secondary" type="button" id="copyLinkBtn">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Write a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-4 text-center">
                        <h6>How would you rate this product?</h6>
                        <div class="rating-stars mb-3" style="font-size: 2rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star rating-star" data-rating="<?php echo $i; ?>" style="cursor: pointer; color: #ddd;"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="selectedRating" required>
                        <div class="text-muted small" id="ratingText">Tap to rate</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Review</label>
                        <textarea class="form-control" 
                                  name="review_text" 
                                  rows="4" 
                                  placeholder="Share your experience with this product..."
                                  required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contact Vendor Modal -->
<div class="modal fade" id="contactVendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="contactVendorForm">
                <div class="modal-header">
                    <h5 class="modal-title">Contact Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="4" required></textarea>
                    </div>
                    <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payment Methods Modal -->
<div class="modal fade" id="paymentMethodsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card me-2 text-primary"></i>
                    Available Payment Methods
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong><?php echo htmlspecialchars($vendor['store_name'] ?? $vendor['full_name']); ?></strong> 
                            accepts the following payment methods for this product:
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <?php foreach ($vendor_payment_methods as $method): ?>
                        <?php if (isset($payment_method_icons[$method])): ?>
                            <div class="col-md-6">
                                <div class="card border h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="payment-icon-circle bg-light rounded-circle p-3 me-3">
                                                <i class="<?php echo $payment_method_icons[$method]; ?> fa-2x text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php echo $payment_method_labels[$method] ?? ucfirst(str_replace('_', ' ', $method)); ?>
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                <?php 
                                                $descriptions = [
                                                    'credit_card' => 'Visa, MasterCard, American Express',
                                                    'debit_card' => 'All major debit cards',
                                                    'paypal' => 'Secure PayPal payments',
                                                    'bank_transfer' => 'Direct bank transfer',
                                                    'stripe' => 'Secure Stripe payments',
                                                    'cod' => 'Pay when you receive',
                                                    'apple_pay' => 'Apple Pay digital wallet',
                                                    'google_pay' => 'Google Pay digital wallet'
                                                ];
                                                echo $descriptions[$method] ?? 'Secure online payment';
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="alert alert-warning">
                            <i class="fas fa-shield-alt me-2"></i>
                            <strong>Security Note:</strong> All payments are encrypted and secure. 
                            Your payment information is never stored on our servers.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="payment.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                    <i class="fas fa-bolt me-2"></i> Proceed to Payment
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Image Zoom Modal -->
<div class="modal fade" id="imageZoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="zoomedImage" src="" class="img-fluid" alt="Zoomed Product Image">
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Image thumbnail click
    $('.thumbnail').click(function() {
        const imageUrl = $(this).data('image');
        $('#mainProductImage').attr('src', imageUrl);
        $('.thumbnail').removeClass('active');
        $(this).addClass('active');
        $(this).css('border-color', '#007bff');
    });
    
    // Image zoom
    $('#mainProductImage').click(function() {
        const imageUrl = $(this).attr('src');
        $('#zoomedImage').attr('src', imageUrl);
        $('#imageZoomModal').modal('show');
    });
    
    // Quantity controls
    $('#decreaseQty').click(function() {
        const input = $('#quantity');
        let value = parseInt(input.val());
        if (value > 1) {
            input.val(value - 1);
            updateBuyNowLink();
        }
    });
    
    $('#increaseQty').click(function() {
        const input = $('#quantity');
        const max = parseInt(input.attr('max'));
        let value = parseInt(input.val());
        if (value < max) {
            input.val(value + 1);
            updateBuyNowLink();
        } else {
            showToast('Maximum quantity reached', 'warning');
        }
    });
    
    // Quantity input change
    $('#quantity').on('input', function() {
        updateBuyNowLink();
    });
    
    function updateBuyNowLink() {
        const quantity = $('#quantity').val();
        const buyNowBtn = $('#buyNowBtn');
        const currentHref = buyNowBtn.attr('href').split('?')[0];
        buyNowBtn.attr('href', currentHref + '?id=<?php echo $product_id; ?>&quantity=' + quantity);
    }
    
    // Initialize buy now link
    updateBuyNowLink();
    
    // Rating stars
    $('.rating-star').hover(function() {
        const rating = $(this).data('rating');
        highlightStars(rating);
    });
    
    $('.rating-star').click(function() {
        const rating = $(this).data('rating');
        $('#selectedRating').val(rating);
        highlightStars(rating);
        
        // Update rating text
        const ratingTexts = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
        $('#ratingText').text(ratingTexts[rating - 1]);
    });
    
    $('.rating-stars').mouseleave(function() {
        const currentRating = $('#selectedRating').val();
        if (currentRating) {
            highlightStars(currentRating);
        } else {
            $('.rating-star').css('color', '#ddd');
        }
    });
    
    function highlightStars(rating) {
        $('.rating-star').css('color', '#ddd');
        $('.rating-star').each(function(index) {
            if (index < rating) {
                $(this).css('color', '#ffc107');
            }
        });
    }
    
    // Wishlist toggle
    $('#wishlistBtn').click(function() {
        const button = $(this);
        const productId = button.data('product-id');
        const isInWishlist = button.data('in-wishlist') === 'true';
        
        $.ajax({
            url: '../ajax/toggle-wishlist.php',
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
                        icon.removeClass('far').addClass('fas');
                        button.data('in-wishlist', 'true');
                        button.html('<i class="fas fa-heart me-2"></i> Remove from Wishlist');
                        showToast('Added to wishlist!', 'success');
                    } else {
                        icon.removeClass('fas').addClass('far');
                        button.data('in-wishlist', 'false');
                        button.html('<i class="far fa-heart me-2"></i> Add to Wishlist');
                        showToast('Removed from wishlist', 'info');
                    }
                } else {
                    showToast(response.message || 'Error updating wishlist', 'error');
                }
            }
        });
    });
    
    // Copy link
    $('#copyLinkBtn').click(function() {
        const linkInput = $('#productLink');
        linkInput.select();
        document.execCommand('copy');
        showToast('Link copied to clipboard!', 'success');
    });
    
    // Contact vendor form
    $('#contactVendorForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '../ajax/contact-vendor.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Message sent to vendor successfully!', 'success');
                    $('#contactVendorModal').modal('hide');
                    $('#contactVendorForm')[0].reset();
                } else {
                    showToast(response.message || 'Error sending message', 'error');
                }
            }
        });
    });
    
    // Quick add to cart
    $('.add-to-cart-quick').click(function() {
        const productId = $(this).data('product-id');
        addToCart(productId, 1);
    });
});

// Toast notification
function showToast(message, type = 'info') {
    const toast = $(`
        <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0 position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    
    $('body').append(toast);
    const bsToast = new bootstrap.Toast(toast[0]);
    bsToast.show();
    
    toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

// Add to cart function
function addToCart(productId, quantity) {
    $.ajax({
        url: '../ajax/add-to-cart.php',
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
                showToast('Product added to cart!', 'success');
            } else {
                showToast(response.message || 'Error adding to cart', 'error');
            }
        }
    });
}
</script>

<!-- CSS Styles -->
<style>
.product-details-page .thumbnail.active {
    border-color: #007bff !important;
}

.product-details-page .thumbnail:hover {
    border-color: #6c757d !important;
}

.product-details-page .object-fit-contain {
    object-fit: contain;
}

.product-details-page .object-fit-cover {
    object-fit: cover;
}

.product-details-page .hover-shadow {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.product-details-page .hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.product-details-page .product-description {
    line-height: 1.8;
    color: #555;
}

.product-details-page .progress {
    background-color: #e9ecef;
}

.product-details-page .review-item {
    transition: background-color 0.3s ease;
}

.product-details-page .review-item:hover {
    background-color: #f8f9fa;
}

#imageZoomModal .modal-body img {
    max-height: 70vh;
}

/* Quantity input spinner */
.product-details-page input[type="number"]::-webkit-inner-spin-button,
.product-details-page input[type="number"]::-webkit-outer-spin-button {
    opacity: 1;
    height: 40px;
}

/* Vendor store logo */
.product-details-page .vendor-logo {
    border: 2px solid #dee2e6;
}

/* Share buttons */
.product-details-page .share-btn {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Payment methods preview */
.payment-methods-preview .badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}

.payment-icon-circle {
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Vendor card enhancements */
.product-details-page .vendor-card .payment-badges {
    max-height: 80px;
    overflow-y: auto;
}

/* Buy now button enhancements */
#buyNowBtn {
    position: relative;
    transition: all 0.3s ease;
}

#buyNowBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
}

/* Payment methods in table */
.table .badge {
    font-size: 0.75rem;
    font-weight: normal;
}

/* Modal payment icons */
.modal .payment-icon-circle {
    transition: transform 0.3s ease;
}

.modal .card:hover .payment-icon-circle {
    transform: scale(1.1);
}
</style>

<?php require_once '../../includes/footer.php'; ?>