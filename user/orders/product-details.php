<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
} else if ($_SESSION['user_type'] === 'vendor') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/vendors/dashboard.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Product not found';
    redirect('shop.php');
}

$page_title = 'Product Details';
require_once '../../includes/header.php';

$db = getDB();
$product_id = (int)$_GET['id'];

// Safe string function to handle null values
function safe_html($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Safe number function
function safe_float($num) {
    return floatval($num ?? 0);
}

// Safe int function
function safe_int($num) {
    return intval($num ?? 0);
}

// =============== CREATE REVIEWS TABLE IF NOT EXISTS (SIMPLIFIED) ===============
try {
    $check_table = $db->query("SHOW TABLES LIKE 'reviews'");
    if ($check_table->rowCount() == 0) {
        // Create simple reviews table without foreign key constraints first
        $db->exec("
            CREATE TABLE IF NOT EXISTS `reviews` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `product_id` int(11) NOT NULL,
                `rating` int(11) NOT NULL,
                `review_text` text DEFAULT NULL,
                `is_approved` tinyint(1) DEFAULT 1,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `product_id` (`product_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        error_log("✓ Reviews table created successfully");
    }
} catch (Exception $e) {
    error_log("Error creating reviews table: " . $e->getMessage());
}

// =============== SIMPLIFIED REVIEW HANDLING ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    // Log the submission
    error_log("========== REVIEW SUBMISSION ==========");
    error_log("POST data: " . print_r($_POST, true));
    
    // Check login
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please login to submit a review';
        header('Location: login.php?redirect=product-details.php?id=' . $product_id);
        exit();
    }
    
    // Get form data
    $user_id = $_SESSION['user_id'];
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
    
    error_log("User ID: $user_id, Product ID: $product_id, Rating: $rating");
    error_log("Review text length: " . strlen($review_text));
    
    // Validate
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Please select a valid rating (1-5)';
        header('Location: product-details.php?id=' . $product_id);
        exit();
    }
    
    if (empty($review_text)) {
        $_SESSION['error'] = 'Please write your review';
        header('Location: product-details.php?id=' . $product_id);
        exit();
    }
    
    if (strlen($review_text) < 10) {
        $_SESSION['error'] = 'Review must be at least 10 characters long';
        header('Location: product-details.php?id=' . $product_id);
        exit();
    }
    
    try {
        $db = getDB();
        
        // Check if user already reviewed this product
        $check_sql = "SELECT id FROM reviews WHERE user_id = ? AND product_id = ?";
        $check_stmt = $db->prepare($check_sql);
        $check_stmt->execute([$user_id, $product_id]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $_SESSION['error'] = 'You have already reviewed this product';
            header('Location: product-details.php?id=' . $product_id);
            exit();
        }
        
        // Insert review - SIMPLE INSERT without transaction
        $insert_sql = "INSERT INTO reviews (user_id, product_id, rating, review_text, is_approved, created_at) 
                       VALUES (?, ?, ?, ?, 1, NOW())";
        $insert_stmt = $db->prepare($insert_sql);
        $result = $insert_stmt->execute([$user_id, $product_id, $rating, $review_text]);
        
        if ($result) {
            $review_id = $db->lastInsertId();
            error_log("✓ Review inserted successfully with ID: $review_id");
            
            // Update product average rating
            $update_sql = "UPDATE products SET 
                           average_rating = (SELECT AVG(rating) FROM reviews WHERE product_id = ?),
                           review_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ?)
                           WHERE id = ?";
            $update_stmt = $db->prepare($update_sql);
            $update_stmt->execute([$product_id, $product_id, $product_id]);
            
            // Update star counts
            $star_sql = "UPDATE products SET 
                        five_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 5),
                        four_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 4),
                        three_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 3),
                        two_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 2),
                        one_star_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND rating = 1)
                        WHERE id = ?";
            $star_stmt = $db->prepare($star_sql);
            $star_stmt->execute([$product_id, $product_id, $product_id, $product_id, $product_id, $product_id]);
            
            $_SESSION['success'] = 'Thank you for your review! It has been posted successfully.';
            error_log("✓ Product stats updated");
        } else {
            $error = $insert_stmt->errorInfo();
            error_log("✗ Insert failed: " . print_r($error, true));
            $_SESSION['error'] = 'Database error: ' . $error[2];
        }
        
    } catch (PDOException $e) {
        error_log("✗ Exception: " . $e->getMessage());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    header('Location: product-details.php?id=' . $product_id);
    exit();
}

// =============== GET PRODUCT DETAILS ===============
// Get product details with vendor info including payment methods
$stmt = $db->prepare("
    SELECT p.*, 
           CASE 
               WHEN c.id IS NOT NULL THEN c.name 
               ELSE p.category 
           END as category_name,
           c.id as category_id,
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
    LEFT JOIN categories c ON 
        (p.category_id IS NOT NULL AND p.category_id = c.id) OR 
        (p.category IS NOT NULL AND p.category = c.slug)
    LEFT JOIN users u ON p.vendor_id = u.id
    LEFT JOIN vendor_settings vs ON p.vendor_id = vs.vendor_id
    WHERE p.id = ? AND p.approved_status = 'approved'
");

$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $_SESSION['error'] = 'Product not found or not available';
    redirect('shop.php');
}

// If category_name is still empty, try to get it directly from products.category
if (empty($product['category_name']) && !empty($product['category'])) {
    // Try to find category by slug
    $stmt2 = $db->prepare("SELECT name, id FROM categories WHERE slug = ?");
    $stmt2->execute([$product['category']]);
    $cat = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($cat) {
        $product['category_name'] = $cat['name'];
        $product['category_id'] = $cat['id'];
    } else {
        // If no category found, use the category slug as name
        $product['category_name'] = ucfirst(str_replace('-', ' ', $product['category']));
    }
}

// =============== GET SIMILAR PRODUCTS ===============
$category_id_for_similar = $product['category_id'] ?? null;
$category_slug_for_similar = $product['category'] ?? null;

if ($category_id_for_similar) {
    // If we have category_id, use it
    $similar_sql = "
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.category_id = ? 
        AND p.id != ? 
        AND p.approved_status = 'approved'
        AND p.stock > 0
        ORDER BY RAND() 
        LIMIT 8
    ";
    $similar_params = [$category_id_for_similar, $product_id];
} elseif ($category_slug_for_similar) {
    // If we have category slug, use it
    $similar_sql = "
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category = c.slug 
        WHERE p.category = ? 
        AND p.id != ? 
        AND p.approved_status = 'approved'
        AND p.stock > 0
        ORDER BY RAND() 
        LIMIT 8
    ";
    $similar_params = [$category_slug_for_similar, $product_id];
} else {
    $similar_sql = "";
    $similar_params = [];
}

if (!empty($similar_sql)) {
    $stmt = $db->prepare($similar_sql);
    $stmt->execute($similar_params);
    $similar_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $similar_products = [];
}

// =============== GET PRODUCT REVIEWS ===============
$reviews_sql = "SELECT r.*, 
                       u.username, 
                       u.full_name, 
                       u.profile_pic,
                       u.id as user_id
                FROM reviews r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.product_id = ? 
                AND r.is_approved = 1 
                ORDER BY r.created_at DESC";
$reviews_stmt = $db->prepare($reviews_sql);
$reviews_stmt->execute([$product_id]);
$reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Check if reviews are being fetched
error_log("Number of reviews fetched for product {$product_id}: " . count($reviews));

// Calculate rating distribution
$rating_distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$total_reviews = 0;
foreach ($reviews as $review) {
    if (isset($review['rating'])) {
        $rating_distribution[$review['rating']]++;
        $total_reviews++;
    }
}

// Calculate average rating from reviews
if ($total_reviews > 0) {
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
    }
    $product_rating = $total_rating / $total_reviews;
    $product_review_count = $total_reviews;
} else {
    $product_rating = safe_float($product['average_rating'] ?? 0);
    $product_review_count = safe_int($product['review_count'] ?? 0);
}

// =============== GET VENDOR INFO ===============
$vendor_id = $product['vendor_id'];
$vendor_sql = "SELECT u.*, vs.*,
                      (SELECT COUNT(*) FROM products WHERE vendor_id = ? AND approved_status = 'approved') as total_products,
                      (SELECT COUNT(*) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.vendor_id = ?) as total_reviews
               FROM users u
               LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id
               WHERE u.id = ? AND u.user_type = 'vendor'";
$vendor_stmt = $db->prepare($vendor_sql);
$vendor_stmt->execute([$vendor_id, $vendor_id, $vendor_id]);
$vendor = $vendor_stmt->fetch(PDO::FETCH_ASSOC);

// =============== PAYMENT METHODS - SUPPORTED TYPES ===============
// Complete list of all supported payment methods
$all_payment_methods = [
    'bank' => ['name' => 'Bank Transfer', 'icon' => 'fas fa-university', 'description' => 'Direct bank transfer to vendor account'],
    'paypal' => ['name' => 'PayPal', 'icon' => 'fab fa-paypal', 'description' => 'Secure PayPal payments'],
    'stripe' => ['name' => 'Stripe', 'icon' => 'fab fa-stripe', 'description' => 'Secure Stripe payments'],
    'easypaisa' => ['name' => 'Easypaisa', 'icon' => 'fas fa-mobile-alt', 'description' => 'Pakistan\'s leading mobile wallet'],
    'jazzcash' => ['name' => 'JazzCash', 'icon' => 'fas fa-mobile-alt', 'description' => 'Fast and secure mobile payments'],
    'cod' => ['name' => 'Cash on Delivery', 'icon' => 'fas fa-money-bill-wave', 'description' => 'Pay when you receive'],
    'visa' => ['name' => 'Visa Card', 'icon' => 'fab fa-cc-visa', 'description' => 'Visa credit/debit cards'],
    'mastercard' => ['name' => 'Mastercard', 'icon' => 'fab fa-cc-mastercard', 'description' => 'Mastercard credit/debit cards'],
    'amex' => ['name' => 'American Express', 'icon' => 'fab fa-cc-amex', 'description' => 'American Express cards']
];

// Parse vendor payment methods
$vendor_payment_methods = [];
if (!empty($vendor['payment_methods'])) {
    $vendor_payment_methods = json_decode($vendor['payment_methods'], true);
}

// If vendor has no specific payment methods, show ALL supported methods
if (empty($vendor_payment_methods) || !is_array($vendor_payment_methods)) {
    $vendor_payment_methods = array_keys($all_payment_methods);
}

// Get cart items for current user
$cart_items = [];
if (isset($_SESSION['user_id'])) {
    $cart_stmt = $db->prepare("SELECT product_id, quantity FROM cart_items WHERE user_id = ?");
    $cart_stmt->execute([$_SESSION['user_id']]);
    $cart_items = $cart_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Get wishlist status
$in_wishlist = false;
if (isset($_SESSION['user_id'])) {
    $wish_stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wish_stmt->execute([$_SESSION['user_id'], $product_id]);
    $in_wishlist = $wish_stmt->fetch() ? true : false;
}

// Get product images
$product_images = [];
if (!empty($product['image'])) {
    $product_images = [$product['image']];
    // If you have an images field with multiple images
    if (!empty($product['images'])) {
        $images = json_decode($product['images'], true);
        if (is_array($images)) {
            $product_images = array_merge($product_images, $images);
        }
    }
}

// Increment product views
$view_stmt = $db->prepare("UPDATE products SET views = views + 1 WHERE id = ?");
$view_stmt->execute([$product_id]);

// Log activity
if (isset($_SESSION['user_id']) && function_exists('logUserActivity')) {
    logUserActivity($_SESSION['user_id'], 'product_view', 'Viewed product: ' . ($product['name'] ?? 'Unknown'));
}

// =============== HANDLE CONTACT VENDOR FORM ===============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_vendor'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please login to contact vendor']);
        exit();
    }
    
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    if (empty($subject) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Subject and message are required']);
        exit();
    }
    
    try {
        // Check if vendor_messages table exists
        $msg_check = $db->query("SHOW TABLES LIKE 'vendor_messages'");
        if ($msg_check->rowCount() == 0) {
            // Create vendor_messages table if it doesn't exist
            $db->exec("
                CREATE TABLE IF NOT EXISTS `vendor_messages` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `vendor_id` int(11) NOT NULL,
                    `product_id` int(11) DEFAULT NULL,
                    `subject` varchar(255) NOT NULL,
                    `message` text NOT NULL,
                    `status` enum('unread','read','replied','archived') DEFAULT 'unread',
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`),
                    KEY `vendor_id` (`vendor_id`),
                    KEY `product_id` (`product_id`),
                    KEY `status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ");
        }
        
        // Insert into vendor_messages table
        $msg_sql = "INSERT INTO vendor_messages (user_id, vendor_id, product_id, subject, message, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'unread', NOW())";
        $msg_stmt = $db->prepare($msg_sql);
        $msg_stmt->execute([$_SESSION['user_id'], $vendor_id, $product_id, $subject, $message]);
        
        // Log activity
        if (function_exists('logUserActivity')) {
            logUserActivity($_SESSION['user_id'], 'contact_vendor', 'Contacted vendor about product ID: ' . $product_id);
        }
        
        echo json_encode(['success' => true, 'message' => 'Message sent to vendor successfully!']);
    } catch (PDOException $e) {
        error_log("Contact vendor error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error sending message. Please try again.']);
    }
    exit();
}

// Get cart quantity for this product
$cart_quantity = isset($cart_items[$product_id]) ? (int)$cart_items[$product_id] : 0;
$max_purchase_quantity = $product['stock'] - $cart_quantity;
if ($max_purchase_quantity < 0) $max_purchase_quantity = 0;
?>

<!-- Display Session Messages -->
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

<div class="product-details-page">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>user/dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="shop.php">Shop</a></li>
            <?php if (!empty($product['category_name'])): ?>
                <li class="breadcrumb-item">
                    <a href="shop.php?category=<?php echo urlencode($product['category'] ?? $product['category_id']); ?>">
                        <?php echo safe_html($product['category_name']); ?>
                    </a>
                </li>
            <?php endif; ?>
            <li class="breadcrumb-item active" aria-current="page"><?php echo safe_html(substr($product['name'] ?? '', 0, 30)); ?>...</li>
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
                                 alt="<?php echo safe_html($product['name'] ?? 'Product Image'); ?>"
                                 style="cursor: zoom-in;"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/no-image.png';">
                        <?php else: ?>
                            <div class="bg-light rounded h-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-box fa-4x text-muted"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Image Gallery -->
                    <?php if (count($product_images) > 1): ?>
                        <div class="image-thumbnails d-flex flex-wrap gap-2 justify-content-center mt-3">
                            <?php foreach ($product_images as $index => $image): ?>
                                <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     style="width: 80px; height: 80px; cursor: pointer; border: 2px solid transparent; border-radius: 5px; overflow: hidden;"
                                     data-image="<?php echo SITE_URL . 'assets/images/products/' . $image; ?>">
                                    <img src="<?php echo SITE_URL . 'assets/images/products/' . $image; ?>" 
                                         class="img-fluid h-100 w-100 object-fit-cover"
                                         alt="Thumbnail <?php echo $index + 1; ?>"
                                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/no-image.png';">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Actions -->
                    <div class="d-flex gap-2 mt-4">
                        <!-- Wishlist Button -->
                        <button class="btn btn-outline-danger flex-grow-1 wishlist-toggle" 
                                id="wishlistBtn"
                                data-product-id="<?php echo $product_id; ?>"
                                data-in-wishlist="<?php echo $in_wishlist ? 'true' : 'false'; ?>">
                            <i class="<?php echo $in_wishlist ? 'fas' : 'far'; ?> fa-heart me-2"></i>
                            <span class="wishlist-text"><?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?></span>
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
                            <?php if (!empty($vendor['store_logo'])): ?>
                                <img src="<?php echo SITE_URL . 'uploads/vendors/' . $vendor['store_logo']; ?>" 
                                     class="rounded-circle" 
                                     style="width: 60px; height: 60px; object-fit: cover;"
                                     alt="<?php echo safe_html($vendor['store_name'] ?? $vendor['full_name'] ?? 'Vendor'); ?>"
                                     onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/default-store.png';">
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
                                    <?php echo safe_html($vendor['store_name'] ?? $vendor['full_name'] ?? 'Vendor'); ?>
                                </a>
                            </h5>
                            
                            <!-- Rating -->
                            <div class="d-flex align-items-center mb-2">
                                <div class="text-warning me-2">
                                    <?php 
                                    $vendor_rating = safe_float($vendor['vendor_rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= floor($vendor_rating)) {
                                            echo '<i class="fas fa-star text-warning"></i>';
                                        } elseif ($i <= ceil($vendor_rating) && $vendor_rating - floor($vendor_rating) > 0) {
                                            echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-muted"></i>';
                                        }
                                    endfor; 
                                    ?>
                                </div>
                                <span class="text-muted small">(<?php echo number_format($vendor_rating, 1); ?>)</span>
                            </div>
                            
                            <!-- Stats -->
                            <div class="row small text-muted">
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo safe_int($vendor['total_products'] ?? 0); ?></div>
                                    <div>Products</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold"><?php echo safe_int($vendor['total_reviews'] ?? 0); ?></div>
                                    <div>Reviews</div>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold">
                                        <?php echo !empty($vendor['vendor_since']) ? date('Y', strtotime($vendor['vendor_since'])) : 'N/A'; ?>
                                    </div>
                                    <div>Member Since</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Vendor Payment Methods - COMPREHENSIVE LIST -->
                    <div class="mt-3">
                        <h6 class="border-bottom pb-2 mb-2">
                            <i class="fas fa-credit-card me-2 text-primary"></i>
                            Payment Methods Accepted
                        </h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php 
                            // Display all payment methods
                            foreach ($vendor_payment_methods as $method): 
                                if (isset($all_payment_methods[$method])): 
                            ?>
                                <span class="badge bg-light text-dark border p-2" 
                                      title="<?php echo $all_payment_methods[$method]['description']; ?>"
                                      data-bs-toggle="tooltip" data-bs-placement="top">
                                    <i class="<?php echo $all_payment_methods[$method]['icon']; ?> me-1 text-primary"></i>
                                    <?php echo $all_payment_methods[$method]['name']; ?>
                                </span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                    
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
                        <h1 class="h3 mb-0"><?php echo safe_html($product['name'] ?? 'Product Name'); ?></h1>
                        <div>
                            <?php if (!empty($product['is_featured']) || !empty($product['featured'])): ?>
                                <span class="badge bg-warning">
                                    <i class="fas fa-star me-1"></i> Featured
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($product['old_price']) && safe_float($product['old_price']) > safe_float($product['price'])): ?>
                                <span class="badge bg-danger ms-1">
                                    -<?php echo round(((safe_float($product['old_price']) - safe_float($product['price'])) / safe_float($product['old_price'])) * 100); ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Category and ID -->
                    <div class="mb-3">
                        <span class="badge bg-light text-dark me-2">
                            <i class="fas fa-tag me-1"></i> 
                            <?php 
                            if (!empty($product['category_name'])) {
                                echo safe_html($product['category_name']);
                            } elseif (!empty($product['category'])) {
                                echo safe_html(ucfirst(str_replace('-', ' ', $product['category'])));
                            } else {
                                echo 'Uncategorized';
                            }
                            ?>
                        </span>
                        <span class="text-muted small">
                            <i class="fas fa-hashtag me-1"></i> Product ID: <?php echo $product['id']; ?>
                        </span>
                    </div>
                    
                    <!-- Rating -->
                    <div class="d-flex align-items-center mb-3">
                        <div class="text-warning me-2">
                            <?php
                            $rating = $product_rating;
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= floor($rating)) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($i <= ceil($rating) && $rating - floor($rating) > 0) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star"></i>';
                                }
                            endfor;
                            ?>
                        </div>
                        <span class="text-muted me-3">(<?php echo $product_review_count; ?> reviews)</span>
                        <span class="text-success">
                            <i class="fas fa-eye me-1"></i> <?php echo safe_int($product['views'] ?? 0); ?> views
                        </span>
                        <span class="text-info ms-2">
                            <i class="fas fa-shopping-cart me-1"></i> <?php echo safe_int($product['sales_count'] ?? 0); ?> sold
                        </span>
                    </div>
                    
                    <!-- Price -->
                    <div class="mb-4">
                        <h2 class="text-primary mb-2">
                            $<?php echo number_format(safe_float($product['price']), 2); ?>
                            <?php if (!empty($product['old_price']) && safe_float($product['old_price']) > safe_float($product['price'])): ?>
                                <small class="text-muted text-decoration-line-through fs-5 ms-2">
                                    $<?php echo number_format(safe_float($product['old_price']), 2); ?>
                                </small>
                            <?php endif; ?>
                        </h2>
                        <?php if (!empty($product['old_price']) && safe_float($product['old_price']) > safe_float($product['price'])): ?>
                            <span class="text-success">
                                <i class="fas fa-save me-1"></i> 
                                Save $<?php echo number_format(safe_float($product['old_price']) - safe_float($product['price']), 2); ?>
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
                    </div>
                    
                    <!-- Add to Cart Form -->
                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Quantity</label>
                            <div class="input-group">
                                <button class="btn btn-outline-secondary" type="button" id="decreaseQty">-</button>
                                <input type="number" 
                                       class="form-control text-center" 
                                       id="quantity" 
                                       value="1" 
                                       min="1" 
                                       max="<?php echo $product['stock']; ?>"
                                       data-stock="<?php echo $product['stock']; ?>">
                                <button class="btn btn-outline-secondary" type="button" id="increaseQty">+</button>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <?php if ($product['stock'] > 0): ?>
                                <?php if (isset($cart_items[$product_id])): ?>
                                    <div class="btn-group w-100">
                                        <button class="btn btn-outline-primary decrease-cart" type="button" data-product-id="<?php echo $product_id; ?>">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <button class="btn btn-outline-primary disabled" type="button" style="min-width: 50px;" disabled>
                                            <?php echo $cart_items[$product_id]; ?>
                                        </button>
                                        <button class="btn btn-outline-primary increase-cart" type="button" data-product-id="<?php echo $product_id; ?>" data-max-stock="<?php echo $product['stock']; ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary btn-lg w-100 add-to-cart" data-product-id="<?php echo $product_id; ?>" data-product-name="<?php echo safe_html($product['name'] ?? 'Product'); ?>">
                                        <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-lg w-100" disabled>
                                    <i class="fas fa-times me-2"></i> Out of Stock
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Buy Now Button with Payment Methods Preview -->
                    <?php if ($product['stock'] > 0 && $max_purchase_quantity > 0): ?>
                        <div class="position-relative mb-4">
                            <a href="multi-item-checkout.php?product_id=<?php echo $product_id; ?>&quantity=<?php echo min(1, $max_purchase_quantity); ?>" 
                               class="btn btn-outline-primary w-100 btn-lg"
                               id="buyNowBtn">
                                <i class="fas fa-bolt me-2"></i> Buy Now
                            </a>
                            <!-- Payment Methods Preview -->
                            <div class="payment-methods-preview mt-2">
                                <small class="text-muted d-block mb-1">
                                    <i class="fas fa-lock me-1 text-success"></i>
                                    Secure payment via:
                                </small>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php 
                                    // Show payment method icons (first 5)
                                    $preview_methods = array_slice($vendor_payment_methods, 0, 5);
                                    foreach ($preview_methods as $method): 
                                        if (isset($all_payment_methods[$method])): 
                                    ?>
                                        <span class="badge bg-light text-muted border" 
                                              title="<?php echo $all_payment_methods[$method]['name']; ?>">
                                            <i class="<?php echo $all_payment_methods[$method]['icon']; ?>"></i>
                                        </span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    
                                    // If more than 5 methods, show count
                                    if (count($vendor_payment_methods) > 5): 
                                    ?>
                                        <span class="badge bg-light text-muted border">
                                            +<?php echo count($vendor_payment_methods) - 5; ?> more
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Description -->
                    <div class="mb-4">
                        <h5 class="mb-3">Description</h5>
                        <div class="product-description">
                            <?php echo nl2br(safe_html($product['description'] ?? 'No description available.')); ?>
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
                                        <td><?php echo safe_html($product['category_name'] ?? 'Uncategorized'); ?></td>
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
                                        <th>Payment Options</th>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($vendor_payment_methods as $method): ?>
                                                    <?php if (isset($all_payment_methods[$method])): ?>
                                                        <span class="badge bg-light text-dark border small" 
                                                              title="<?php echo $all_payment_methods[$method]['description']; ?>">
                                                            <i class="<?php echo $all_payment_methods[$method]['icon']; ?> me-1 text-primary"></i>
                                                            <?php echo $all_payment_methods[$method]['name']; ?>
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
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Customer Reviews</h5>
                    <span class="badge bg-primary"><?php echo $total_reviews; ?> Reviews</span>
                </div>
                <div class="card-body">
                    <!-- Rating Summary -->
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <div class="display-4 fw-bold text-primary"><?php echo number_format($product_rating, 1); ?></div>
                            <div class="text-warning mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= floor($product_rating)): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif ($i <= ceil($product_rating) && $product_rating - floor($product_rating) > 0): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                            <div class="text-muted small">Based on <?php echo $product_review_count; ?> reviews</div>
                        </div>
                        <div class="col-md-8">
                            <?php for ($i = 5; $i >= 1; $i--): 
                                $percentage = $product_review_count > 0 ? ($rating_distribution[$i] / $product_review_count) * 100 : 0;
                            ?>
                                <div class="row align-items-center mb-2">
                                    <div class="col-2">
                                        <span class="text-muted"><?php echo $i; ?> <i class="fas fa-star text-warning"></i></span>
                                    </div>
                                    <div class="col-8">
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%"
                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100"></div>
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="text-center mb-4">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                <i class="fas fa-edit me-2"></i> Write a Review
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="text-center mb-4">
                            <a href="login.php?redirect=product-details.php?id=<?php echo $product_id; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-sign-in-alt me-2"></i> Login to Write Review
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reviews List -->
                    <div class="reviews-list">
                        <?php if (empty($reviews)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No reviews yet. Be the first to review this product!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <div class="review-item border-top pt-3 mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($review['profile_pic']) && $review['profile_pic'] !== 'default.png'): ?>
                                                    <img src="<?php echo SITE_URL . 'uploads/profiles/' . $review['profile_pic']; ?>" 
                                                         class="rounded-circle me-2" 
                                                         style="width: 40px; height: 40px; object-fit: cover;"
                                                         alt="<?php echo safe_html($review['full_name'] ?? $review['username'] ?? 'User'); ?>"
                                                         onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/default-avatar.png';">
                                                <?php else: ?>
                                                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-2" 
                                                         style="width: 40px; height: 40px;">
                                                        <i class="fas fa-user text-primary"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo safe_html($review['full_name'] ?? $review['username'] ?? 'Anonymous'); ?></strong>
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
                                    <p class="mb-0"><?php echo nl2br(safe_html($review['review_text'] ?? '')); ?></p>
                                </div>
                            <?php endforeach; ?>
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
                <a href="shop.php?category=<?php echo urlencode($product['category'] ?? $product['category_id']); ?>" class="btn btn-outline-primary btn-sm">
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
                                    <?php if (!empty($similar['image'])): ?>
                                        <img src="<?php echo SITE_URL . 'assets/images/products/' . $similar['image']; ?>" 
                                             class="card-img-top h-100 object-fit-cover" 
                                             alt="<?php echo safe_html($similar['name'] ?? 'Product'); ?>"
                                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/no-image.png';">
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
                                        <?php echo safe_html(substr($similar['name'] ?? '', 0, 40)); ?>
                                        <?php echo strlen($similar['name'] ?? '') > 40 ? '...' : ''; ?>
                                    </a>
                                </h6>
                                
                                <!-- Rating -->
                                <div class="mb-2 small">
                                    <div class="d-flex align-items-center">
                                        <?php
                                        $similar_rating = safe_float($similar['average_rating'] ?? 0);
                                        for ($i = 1; $i <= 5; $i++):
                                            if ($i <= floor($similar_rating)) {
                                                echo '<i class="fas fa-star text-warning"></i>';
                                            } elseif ($i <= ceil($similar_rating) && $similar_rating - floor($similar_rating) > 0) {
                                                echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                            } else {
                                                echo '<i class="far fa-star text-light"></i>';
                                            }
                                        endfor;
                                        ?>
                                        <span class="text-muted ms-2">(<?php echo safe_int($similar['review_count'] ?? 0); ?>)</span>
                                    </div>
                                </div>
                                
                                <!-- Price -->
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="text-primary mb-0">
                                            $<?php echo number_format(safe_float($similar['price']), 2); ?>
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

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share this product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
                        <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Check out this product: ' . ($product['name'] ?? 'Product')); ?>&url=<?php echo urlencode(SITE_URL . 'user/orders/product-details.php?id=' . $product_id); ?>" 
                           target="_blank" 
                           class="btn btn-info w-100 text-white">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="https://wa.me/?text=<?php echo urlencode('Check out this product: ' . ($product['name'] ?? 'Product') . ' - ' . SITE_URL . 'user/orders/product-details.php?id=' . $product_id); ?>" 
                           target="_blank" 
                           class="btn btn-success w-100">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                    </div>
                    <div class="col-3">
                        <a href="mailto:?subject=<?php echo urlencode('Check out this product: ' . ($product['name'] ?? 'Product')); ?>&body=<?php echo urlencode('I thought you might like this product: ' . ($product['name'] ?? 'Product') . "\n\n" . SITE_URL . 'user/orders/product-details.php?id=' . $product_id); ?>" 
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

<!-- Review Modal - SIMPLIFIED -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?php echo $_SERVER['PHP_SELF'] . '?id=' . $product_id; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Write a Review</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="alert alert-warning">
                            Please <a href="login.php?redirect=<?php echo urlencode('product-details.php?id=' . $product_id); ?>">login</a> to write a review.
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <select name="rating" class="form-select" required>
                                <option value="">Select rating</option>
                                <option value="5">5 Stars - Excellent</option>
                                <option value="4">4 Stars - Very Good</option>
                                <option value="3">3 Stars - Good</option>
                                <option value="2">2 Stars - Fair</option>
                                <option value="1">1 Star - Poor</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Your Review</label>
                            <textarea class="form-control" name="review_text" rows="4" required minlength="10"></textarea>
                            <small class="text-muted">Minimum 10 characters</small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Contact Vendor Modal -->
<div class="modal fade" id="contactVendorModal" tabindex="-1" aria-hidden="true">
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
                    <input type="hidden" name="contact_vendor" value="1">
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
<div class="modal fade" id="paymentMethodsModal" tabindex="-1" aria-hidden="true">
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
                            <strong><?php echo safe_html($vendor['store_name'] ?? $vendor['full_name'] ?? 'Vendor'); ?></strong> 
                            accepts the following payment methods:
                        </div>
                    </div>
                </div>
                
                <div class="row g-4">
                    <?php foreach ($vendor_payment_methods as $method): ?>
                        <?php if (isset($all_payment_methods[$method])): ?>
                            <div class="col-md-6">
                                <div class="card border h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="payment-icon-circle bg-light rounded-circle p-3 me-3">
                                                <i class="<?php echo $all_payment_methods[$method]['icon']; ?> fa-2x text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <?php echo $all_payment_methods[$method]['name']; ?>
                                            </h6>
                                            <p class="text-muted small mb-0">
                                                <?php echo $all_payment_methods[$method]['description']; ?>
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
                        <div class="alert alert-success">
                            <i class="fas fa-shield-alt me-2"></i>
                            <strong>Security Note:</strong> All payments are encrypted and secure. 
                            Your payment information is never stored on our servers.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <?php if ($product['stock'] > 0 && $max_purchase_quantity > 0): ?>
                    <a href="checkout.php?product_id=<?php echo $product_id; ?>&quantity=1" class="btn btn-primary">
                        <i class="fas fa-bolt me-2"></i> Proceed to Payment
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Image Zoom Modal -->
<div class="modal fade" id="imageZoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="zoomedImage" src="" class="img-fluid" alt="Zoomed Product Image">
            </div>
        </div>
    </div>
</div>

<!-- Toast Container for Notifications -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<!-- JavaScript -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    const siteUrl = '<?php echo SITE_URL; ?>';
    const productId = <?php echo $product_id; ?>;
    
    // Enable tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
    
    // Function to show toast messages
    function showToast(message, type = 'info') {
        const bgColor = type === 'success' ? 'bg-success' : 
                        type === 'error' ? 'bg-danger' : 
                        type === 'warning' ? 'bg-warning' : 'bg-info';
        const textColor = type === 'warning' ? 'text-dark' : 'text-white';
        
        const toastId = 'toast-' + Date.now();
        const toast = `
            <div id="${toastId}" class="toast ${bgColor} ${textColor} mb-2" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        $('.toast-container').append(toast);
        const bsToast = new bootstrap.Toast(document.getElementById(toastId));
        bsToast.show();
        
        setTimeout(() => {
            $(`#${toastId}`).remove();
        }, 3500);
    }
    
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
    
    // Quantity input validation
    $('#quantity').on('input', function() {
        const input = $(this);
        let value = parseInt(input.val());
        const min = parseInt(input.attr('min'));
        const max = parseInt(input.attr('max'));
        
        if (isNaN(value) || value < min) {
            input.val(min);
        } else if (value > max) {
            input.val(max);
            showToast('Maximum quantity reached', 'warning');
        }
        updateBuyNowLink();
    });
    
    function updateBuyNowLink() {
        const quantity = $('#quantity').val();
        const buyNowBtn = $('#buyNowBtn');
        if (buyNowBtn.length) {
            const baseUrl = buyNowBtn.attr('href').split('?')[0];
            buyNowBtn.attr('href', baseUrl + '?product_id=<?php echo $product_id; ?>&quantity=' + quantity);
        }
    }
    
    // Initialize buy now link
    updateBuyNowLink();
    
    // Wishlist toggle
    $('#wishlistBtn').click(function() {
        const button = $(this);
        const productId = button.data('product-id');
        const isInWishlist = button.data('in-wishlist') === 'true';
        
        $.ajax({
            url: siteUrl + 'user/ajax/toggle-wishlist.php',
            type: 'POST',
            data: {
                product_id: productId,
                action: isInWishlist ? 'remove' : 'add'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const icon = button.find('i');
                    const textSpan = button.find('.wishlist-text');
                    if (response.action === 'added') {
                        icon.removeClass('far').addClass('fas');
                        button.data('in-wishlist', 'true');
                        textSpan.text('Remove from Wishlist');
                        showToast('Added to wishlist!', 'success');
                    } else if (response.action === 'removed') {
                        icon.removeClass('fas').addClass('far');
                        button.data('in-wishlist', 'false');
                        textSpan.text('Add to Wishlist');
                        showToast('Removed from wishlist', 'info');
                    }
                } else {
                    showToast(response.message || 'Error updating wishlist', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Wishlist error:', error);
                showToast('Network error: ' + error, 'error');
            }
        });
    });
    
    // Add to cart
    $(document).on('click', '.add-to-cart', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const productName = button.data('product-name') || 'Product';
        const quantity = $('#quantity').val() || 1;
        
        $.ajax({
            url: siteUrl + 'user/ajax/add-to-cart.php',
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
                    
                    // Replace button with quantity controls
                    const qtyControls = `
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-outline-primary decrease-cart" data-product-id="${productId}">
                                <i class="fas fa-minus"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary disabled" style="min-width: 50px;" disabled>${quantity}</button>
                            <button type="button" class="btn btn-outline-primary increase-cart" data-product-id="${productId}" data-max-stock="<?php echo $product['stock']; ?>">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    `;
                    
                    button.closest('.col-md-8').html(qtyControls);
                    showToast(`Added <strong>${productName}</strong> to cart!`, 'success');
                } else {
                    showToast(response.message || 'Error adding to cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Add to cart error:', error);
                showToast('Network error: ' + error, 'error');
            }
        });
    });
    
    // Increase cart
    $(document).on('click', '.increase-cart', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const maxStock = button.data('max-stock');
        const currentQty = parseInt(button.siblings('button:eq(1)').text());
        
        if (currentQty < maxStock) {
            updateCartQuantity(productId, currentQty + 1);
        } else {
            showToast('Cannot add more than available stock', 'warning');
        }
    });
    
    // Decrease cart
    $(document).on('click', '.decrease-cart', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const currentQty = parseInt(button.siblings('button:eq(1)').text());
        
        if (currentQty > 1) {
            updateCartQuantity(productId, currentQty - 1);
        } else {
            removeFromCart(productId);
        }
    });
    
    function updateCartQuantity(productId, quantity) {
        $.ajax({
            url: siteUrl + 'user/ajax/update-cart.php',
            type: 'POST',
            data: {
                product_id: productId,
                quantity: quantity
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const qtyDisplay = $(`.increase-cart[data-product-id="${productId}"]`).siblings('button:eq(1)');
                    qtyDisplay.text(quantity);
                    
                    // Update cart count in header
                    $('.cart-count').text(response.cart_count);
                    showToast('Cart updated', 'success');
                } else {
                    showToast(response.message || 'Error updating cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Update cart error:', error);
                showToast('Network error: ' + error, 'error');
            }
        });
    }
    
    function removeFromCart(productId) {
        $.ajax({
            url: siteUrl + 'user/ajax/remove-from-cart.php',
            type: 'POST',
            data: { product_id: productId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Update cart count
                    $('.cart-count').text(response.cart_count);
                    
                    // Replace with add to cart button
                    const addButton = `
                        <button type="button" class="btn btn-primary btn-lg w-100 add-to-cart" data-product-id="${productId}" data-product-name="<?php echo safe_html($product['name'] ?? 'Product'); ?>">
                            <i class="fas fa-cart-plus me-2"></i> Add to Cart
                        </button>
                    `;
                    
                    $(`.decrease-cart[data-product-id="${productId}"]`).closest('.col-md-8').html(addButton);
                    showToast('Removed from cart', 'info');
                } else {
                    showToast(response.message || 'Error removing from cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Remove from cart error:', error);
                showToast('Network error: ' + error, 'error');
            }
        });
    }
    
    // Quick add to cart from similar products
    $(document).on('click', '.add-to-cart-quick', function() {
        const button = $(this);
        const productId = button.data('product-id');
        
        $.ajax({
            url: siteUrl + 'user/ajax/add-to-cart.php',
            type: 'POST',
            data: {
                product_id: productId,
                quantity: 1
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('.cart-count').text(response.cart_count);
                    showToast('Product added to cart!', 'success');
                } else {
                    showToast(response.message || 'Error adding to cart', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Quick add error:', error);
                showToast('Network error: ' + error, 'error');
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
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Message sent to vendor successfully!', 'success');
                    $('#contactVendorModal').modal('hide');
                    $('#contactVendorForm')[0].reset();
                } else {
                    showToast(response.message || 'Error sending message', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Contact vendor error:', error);
                showToast('Network error: ' + error, 'error');
            }
        });
    });
});
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

/* Toast container */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}

.toast {
    min-width: 300px;
}

/* Responsive fixes */
@media (max-width: 768px) {
    .product-details-page .main-image {
        height: 250px !important;
    }
    
    .product-details-page .thumbnail {
        width: 60px !important;
        height: 60px !important;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>