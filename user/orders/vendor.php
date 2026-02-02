<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Vendor not found';
    redirect('shop.php');
}

$page_title = 'Vendor Store';
require_once '../../includes/header.php';

$db = getDB();
$vendor_id = (int)$_GET['id'];

// Get vendor details
$stmt = $db->prepare("
    SELECT u.*, vs.*,
           (SELECT COUNT(*) FROM products WHERE vendor_id = u.id AND approved_status = 'approved') as total_products,
           (SELECT COUNT(*) FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.vendor_id = u.id) as total_reviews,
           (SELECT COUNT(*) FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            JOIN products p ON oi.product_id = p.id 
            WHERE p.vendor_id = u.id AND o.status = 'delivered') as total_sales
    FROM users u
    LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id
    WHERE u.id = ? AND u.user_type = 'vendor' AND u.vendor_status = 'approved'
");

$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    $_SESSION['error'] = 'Vendor not found or not approved';
    redirect('shop.php');
}

// Get vendor's products
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category = c.slug 
    WHERE p.vendor_id = ? 
    AND p.approved_status = 'approved'
    ORDER BY p.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $vendor_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Get total products count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM products WHERE vendor_id = ? AND approved_status = 'approved'");
$stmt->execute([$vendor_id]);
$total_products = $stmt->fetch()['total'];
$total_pages = ceil($total_products / $limit);

// Get vendor's reviews
$stmt = $db->prepare("
    SELECT r.*, u.username, u.full_name, u.profile_pic, p.name as product_name
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    WHERE p.vendor_id = ? 
    AND r.is_approved = 1
    ORDER BY r.created_at DESC 
    LIMIT 5
");
$stmt->execute([$vendor_id]);
$vendor_reviews = $stmt->fetchAll();

// Get vendor categories
$stmt = $db->prepare("
    SELECT DISTINCT c.id, c.name, c.slug, COUNT(p.id) as product_count
    FROM products p
    JOIN categories c ON p.category = c.slug
    WHERE p.vendor_id = ? 
    AND p.approved_status = 'approved'
    GROUP BY c.id, c.name, c.slug
    ORDER BY product_count DESC
");
$stmt->execute([$vendor_id]);
$vendor_categories = $stmt->fetchAll();

// Get cart items
$cart_items = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $db->prepare("SELECT product_id FROM cart_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Log activity
logUserActivity($_SESSION['user_id'], 'vendor_view', 'Viewed vendor store: ' . $vendor['store_name']);

// Parse vendor settings
$payment_methods = json_decode($vendor['payment_methods'] ?? '[]', true);
$business_hours = json_decode($vendor['business_hours'] ?? '[]', true);
?>

<div class="vendor-page">
    <!-- Vendor Header -->
    <div class="vendor-header bg-gradient py-5 mb-4" 
         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3 text-center mb-4 mb-md-0">
                    <?php if ($vendor['store_logo']): ?>
                        <img src="<?php echo SITE_URL . 'uploads/vendors/' . $vendor['store_logo']; ?>" 
                             class="rounded-circle border border-4 border-white shadow" 
                             style="width: 150px; height: 150px; object-fit: cover;"
                             alt="<?php echo htmlspecialchars($vendor['store_name']); ?>">
                    <?php else: ?>
                        <div class="rounded-circle bg-white d-inline-flex align-items-center justify-content-center" 
                             style="width: 150px; height: 150px;">
                            <i class="fas fa-store fa-3x text-primary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h1 class="text-white mb-2"><?php echo htmlspecialchars($vendor['store_name'] ?? $vendor['full_name']); ?></h1>
                    <?php if ($vendor['store_description']): ?>
                        <p class="text-white-75 mb-3"><?php echo htmlspecialchars($vendor['store_description']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Vendor Stats -->
                    <div class="row text-white">
                        <div class="col-4 text-center">
                            <div class="h4 mb-0"><?php echo $vendor['total_products']; ?></div>
                            <small>Products</small>
                        </div>
                        <div class="col-4 text-center">
                            <div class="h4 mb-0"><?php echo $vendor['total_reviews']; ?></div>
                            <small>Reviews</small>
                        </div>
                        <div class="col-4 text-center">
                            <div class="h4 mb-0"><?php echo $vendor['total_sales']; ?></div>
                            <small>Sales</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 text-md-end">
                    <div class="vendor-rating bg-white bg-opacity-10 rounded-3 p-3 text-white">
                        <div class="text-center">
                            <div class="display-4 fw-bold mb-2"><?php echo number_format($vendor['vendor_rating'] ?? 0, 1); ?></div>
                            <div class="text-warning mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= floor($vendor['vendor_rating'] ?? 0) ? 'text-warning' : 'far text-white-50'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <small><?php echo $vendor['total_reviews']; ?> reviews</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Vendor Info Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <ul class="nav nav-tabs" id="vendorTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
                            <i class="fas fa-box me-2"></i> Products
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="about-tab" data-bs-toggle="tab" data-bs-target="#about" type="button">
                            <i class="fas fa-info-circle me-2"></i> About
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button">
                            <i class="fas fa-star me-2"></i> Reviews
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button">
                            <i class="fas fa-credit-card me-2"></i> Payment Methods
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="policies-tab" data-bs-toggle="tab" data-bs-target="#policies" type="button">
                            <i class="fas fa-file-contract me-2"></i> Policies
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-4" id="vendorTabContent">
                    <!-- Products Tab -->
                    <div class="tab-pane fade show active" id="products" role="tabpanel">
                        <!-- Categories Filter -->
                        <?php if (!empty($vendor_categories)): ?>
                            <div class="mb-4">
                                <h5 class="mb-3">Categories</h5>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="?id=<?php echo $vendor_id; ?>" 
                                       class="btn btn-outline-primary <?php echo !isset($_GET['category']) ? 'active' : ''; ?>">
                                        All Products
                                    </a>
                                    <?php foreach ($vendor_categories as $cat): ?>
                                        <a href="?id=<?php echo $vendor_id; ?>&category=<?php echo $cat['slug']; ?>" 
                                           class="btn btn-outline-primary <?php echo (isset($_GET['category']) && $_GET['category'] == $cat['slug']) ? 'active' : ''; ?>">
                                            <?php echo $cat['name']; ?> 
                                            <span class="badge bg-primary ms-1"><?php echo $cat['product_count']; ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Products Grid -->
                        <div class="row g-4">
                            <?php if (empty($products)): ?>
                                <div class="col-12 text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <h4>No products found</h4>
                                    <p class="text-muted">This vendor hasn't added any products yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <div class="col-xl-3 col-lg-4 col-md-6">
                                        <div class="card border-0 shadow-sm h-100 hover-shadow">
                                            <div class="position-relative" style="height: 200px;">
                                                <a href="product-details.php?id=<?php echo $product['id']; ?>">
                                                    <?php if ($product['image']): ?>
                                                        <img src="<?php echo SITE_URL . 'assets/images/products/' . $product['image']; ?>" 
                                                             class="card-img-top h-100 object-fit-cover" 
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                    <?php else: ?>
                                                        <div class="card-img-top bg-light h-100 d-flex align-items-center justify-content-center">
                                                            <i class="fas fa-box fa-3x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </a>
                                            </div>
                                            <div class="card-body d-flex flex-column">
                                                <h6 class="card-title">
                                                    <a href="product-details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars(substr($product['name'], 0, 40)); ?>
                                                        <?php echo strlen($product['name']) > 40 ? '...' : ''; ?>
                                                    </a>
                                                </h6>
                                                <div class="mt-auto">
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
                                            <a class="page-link" href="?id=<?php echo $vendor_id; ?>&page=<?php echo $page-1; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?id=<?php echo $vendor_id; ?>&page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?id=<?php echo $vendor_id; ?>&page=<?php echo $page+1; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                    
                    <!-- About Tab -->
                    <div class="tab-pane fade" id="about" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <?php if ($vendor['vendor_bio']): ?>
                                    <div class="mb-4">
                                        <h5 class="mb-3">About the Vendor</h5>
                                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($vendor['vendor_bio'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Store Information -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-calendar-alt text-primary me-2"></i> Member Since</h6>
                                        <p class="text-muted"><?php echo date('F Y', strtotime($vendor['vendor_since'])); ?></p>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <h6><i class="fas fa-tag text-primary me-2"></i> Category</h6>
                                        <p class="text-muted"><?php echo $vendor['vendor_category'] ?? 'Not specified'; ?></p>
                                    </div>
                                    <?php if ($vendor['store_address']): ?>
                                        <div class="col-md-6 mb-3">
                                            <h6><i class="fas fa-map-marker-alt text-primary me-2"></i> Store Address</h6>
                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($vendor['store_address'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($vendor['store_phone']): ?>
                                        <div class="col-md-6 mb-3">
                                            <h6><i class="fas fa-phone text-primary me-2"></i> Contact Phone</h6>
                                            <p class="text-muted"><?php echo $vendor['store_phone']; ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($vendor['store_email']): ?>
                                        <div class="col-md-6 mb-3">
                                            <h6><i class="fas fa-envelope text-primary me-2"></i> Email</h6>
                                            <p class="text-muted"><?php echo $vendor['store_email']; ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($vendor['store_website']): ?>
                                        <div class="col-md-6 mb-3">
                                            <h6><i class="fas fa-globe text-primary me-2"></i> Website</h6>
                                            <p class="text-muted">
                                                <a href="<?php echo $vendor['store_website']; ?>" target="_blank">
                                                    <?php echo $vendor['store_website']; ?>
                                                </a>
                                            </p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Social Media -->
                                <?php 
                                $social_links = [
                                    'store_social_facebook' => ['icon' => 'fab fa-facebook', 'color' => 'primary', 'name' => 'Facebook'],
                                    'store_social_instagram' => ['icon' => 'fab fa-instagram', 'color' => 'danger', 'name' => 'Instagram'],
                                    'store_social_twitter' => ['icon' => 'fab fa-twitter', 'color' => 'info', 'name' => 'Twitter'],
                                    'store_social_linkedin' => ['icon' => 'fab fa-linkedin', 'color' => 'primary', 'name' => 'LinkedIn'],
                                    'store_social_youtube' => ['icon' => 'fab fa-youtube', 'color' => 'danger', 'name' => 'YouTube'],
                                    'store_social_pinterest' => ['icon' => 'fab fa-pinterest', 'color' => 'danger', 'name' => 'Pinterest']
                                ];
                                
                                $has_social = false;
                                foreach ($social_links as $field => $social) {
                                    if (!empty($vendor[$field])) {
                                        $has_social = true;
                                        break;
                                    }
                                }
                                
                                if ($has_social):
                                ?>
                                    <div class="mt-4">
                                        <h5 class="mb-3">Follow Us</h5>
                                        <div class="d-flex gap-2">
                                            <?php foreach ($social_links as $field => $social): ?>
                                                <?php if (!empty($vendor[$field])): ?>
                                                    <a href="<?php echo $vendor[$field]; ?>" 
                                                       target="_blank" 
                                                       class="btn btn-outline-<?php echo $social['color']; ?> btn-sm">
                                                        <i class="<?php echo $social['icon']; ?> me-1"></i> <?php echo $social['name']; ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Business Hours -->
                            <?php if (!empty($business_hours) && is_array($business_hours)): ?>
                                <div class="col-lg-4">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-header bg-white">
                                            <h6 class="mb-0"><i class="fas fa-clock me-2"></i> Business Hours</h6>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-sm">
                                                <tbody>
                                                    <?php 
                                                    $days = [
                                                        'monday' => 'Monday',
                                                        'tuesday' => 'Tuesday',
                                                        'wednesday' => 'Wednesday',
                                                        'thursday' => 'Thursday',
                                                        'friday' => 'Friday',
                                                        'saturday' => 'Saturday',
                                                        'sunday' => 'Sunday'
                                                    ];
                                                    
                                                    foreach ($days as $day_key => $day_name): 
                                                        $hours = $business_hours[$day_key] ?? ['open' => '', 'close' => '', 'closed' => false];
                                                    ?>
                                                        <tr>
                                                            <td><?php echo $day_name; ?></td>
                                                            <td class="text-end">
                                                                <?php if ($hours['closed'] ?? false): ?>
                                                                    <span class="text-danger">Closed</span>
                                                                <?php elseif (!empty($hours['open']) && !empty($hours['close'])): ?>
                                                                    <?php echo date('g:i A', strtotime($hours['open'])); ?> - 
                                                                    <?php echo date('g:i A', strtotime($hours['close'])); ?>
                                                                <?php else: ?>
                                                                    <span class="text-muted">Not specified</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Reviews Tab -->
                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-4">
                                <!-- Rating Summary -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-body text-center">
                                        <div class="display-2 fw-bold text-primary mb-2"><?php echo number_format($vendor['vendor_rating'] ?? 0, 1); ?></div>
                                        <div class="text-warning mb-3" style="font-size: 1.5rem;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= floor($vendor['vendor_rating'] ?? 0) ? 'text-warning' : 'far text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="text-muted">Based on <?php echo $vendor['total_reviews']; ?> reviews</p>
                                    </div>
                                </div>
                                
                                <!-- Write Review Button -->
                                <div class="text-center">
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorReviewModal">
                                        <i class="fas fa-edit me-2"></i> Write a Review
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Reviews List -->
                            <div class="col-lg-8">
                                <?php if (empty($vendor_reviews)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                        <h4>No reviews yet</h4>
                                        <p class="text-muted">Be the first to review this vendor!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($vendor_reviews as $review): ?>
                                        <div class="card border-0 shadow-sm mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($review['profile_pic'] && $review['profile_pic'] !== 'default.png'): ?>
                                                            <img src="<?php echo SITE_URL . 'uploads/profiles/' . $review['profile_pic']; ?>" 
                                                                 class="rounded-circle me-3" 
                                                                 style="width: 50px; height: 50px; object-fit: cover;"
                                                                 alt="<?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?>">
                                                        <?php else: ?>
                                                            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center me-3" 
                                                                 style="width: 50px; height: 50px;">
                                                                <i class="fas fa-user text-primary"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?></h6>
                                                            <small class="text-muted">Reviewed on <?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                                        </div>
                                                    </div>
                                                    <div class="text-warning">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'far text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                </div>
                                                
                                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                                
                                                <?php if (!empty($review['product_name'])): ?>
                                                    <div class="border-top pt-3">
                                                        <small class="text-muted">Product reviewed:</small>
                                                        <a href="product-details.php?id=<?php echo $review['product_id']; ?>" class="d-block text-decoration-none">
                                                            <i class="fas fa-box me-2"></i> <?php echo htmlspecialchars($review['product_name']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($vendor['total_reviews'] > 5): ?>
                                        <div class="text-center mt-4">
                                            <a href="vendor-reviews.php?id=<?php echo $vendor_id; ?>" class="btn btn-outline-primary">
                                                View All Reviews <i class="fas fa-arrow-right ms-2"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Methods Tab -->
                    <div class="tab-pane fade" id="payments" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-8">
                                <!-- Payment Methods -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0">Accepted Payment Methods</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($payment_methods) && is_array($payment_methods)): ?>
                                            <div class="row g-3">
                                                <?php 
                                                $payment_icons = [
                                                    'credit_card' => ['icon' => 'fas fa-credit-card', 'color' => 'primary', 'name' => 'Credit/Debit Cards'],
                                                    'paypal' => ['icon' => 'fab fa-paypal', 'color' => 'primary', 'name' => 'PayPal'],
                                                    'bank_transfer' => ['icon' => 'fas fa-university', 'color' => 'success', 'name' => 'Bank Transfer'],
                                                    'cash_on_delivery' => ['icon' => 'fas fa-money-bill-wave', 'color' => 'success', 'name' => 'Cash on Delivery'],
                                                    'stripe' => ['icon' => 'fab fa-stripe', 'color' => 'purple', 'name' => 'Stripe'],
                                                    'razorpay' => ['icon' => 'fas fa-rupee-sign', 'color' => 'blue', 'name' => 'Razorpay'],
                                                    'ssl_commerz' => ['icon' => 'fas fa-shield-alt', 'color' => 'green', 'name' => 'SSL Commerz']
                                                ];
                                                
                                                foreach ($payment_methods as $method_key => $method):
                                                    if (isset($payment_icons[$method_key])):
                                                        $icon = $payment_icons[$method_key];
                                                ?>
                                                    <div class="col-md-6">
                                                        <div class="card border h-100">
                                                            <div class="card-body d-flex align-items-center">
                                                                <div class="avatar-md bg-<?php echo $icon['color']; ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                    <i class="<?php echo $icon['icon']; ?> fa-2x text-<?php echo $icon['color']; ?>"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-1"><?php echo $icon['name']; ?></h6>
                                                                    <small class="text-muted">Accepted for all orders</small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No payment methods specified by vendor.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Payment Security -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0">Payment Security</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-shield-alt fa-2x text-success me-3 mt-1"></i>
                                                    <div>
                                                        <h6>Secure Payments</h6>
                                                        <p class="text-muted small mb-0">All transactions are encrypted and secure</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-lock fa-2x text-primary me-3 mt-1"></i>
                                                    <div>
                                                        <h6>Privacy Protected</h6>
                                                        <p class="text-muted small mb-0">Your payment details are never shared</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-sync-alt fa-2x text-info me-3 mt-1"></i>
                                                    <div>
                                                        <h6>Easy Refunds</h6>
                                                        <p class="text-muted small mb-0">Quick refund process for eligible orders</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-headset fa-2x text-warning me-3 mt-1"></i>
                                                    <div>
                                                        <h6>24/7 Support</h6>
                                                        <p class="text-muted small mb-0">Payment support available round the clock</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-4">
                                <!-- Payment FAQ -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0">Payment FAQ</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="accordion" id="paymentFAQ">
                                            <div class="accordion-item">
                                                <h6 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                                        When will I be charged?
                                                    </button>
                                                </h6>
                                                <div id="faq1" class="accordion-collapse collapse">
                                                    <div class="accordion-body small">
                                                        Payment is processed immediately when you place an order. For pre-orders, payment is charged when the item ships.
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="accordion-item">
                                                <h6 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                                        Is it safe to pay online?
                                                    </button>
                                                </h6>
                                                <div id="faq2" class="accordion-collapse collapse">
                                                    <div class="accordion-body small">
                                                        Yes, all payments are processed through secure payment gateways with 256-bit SSL encryption.
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="accordion-item">
                                                <h6 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                                        What if my payment fails?
                                                    </button>
                                                </h6>
                                                <div id="faq3" class="accordion-collapse collapse">
                                                    <div class="accordion-body small">
                                                        If payment fails, please check your payment details and try again. Contact support if the issue persists.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact for Payment Issues -->
                                <div class="card border-0 shadow-sm mt-4">
                                    <div class="card-body text-center">
                                        <i class="fas fa-question-circle fa-3x text-primary mb-3"></i>
                                        <h6>Need Help with Payment?</h6>
                                        <p class="small text-muted mb-3">Contact our payment support team</p>
                                        <a href="contact.php?vendor_id=<?php echo $vendor_id; ?>" class="btn btn-outline-primary btn-sm w-100">
                                            <i class="fas fa-envelope me-2"></i> Contact Support
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Policies Tab -->
                    <div class="tab-pane fade" id="policies" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <!-- Store Policy -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0"><i class="fas fa-store me-2"></i> Store Policy</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($vendor['store_policy']): ?>
                                            <?php echo nl2br(htmlspecialchars($vendor['store_policy'])); ?>
                                        <?php else: ?>
                                            <p class="text-muted">No store policy specified.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <!-- Return Policy -->
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0"><i class="fas fa-undo me-2"></i> Return Policy</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($vendor['return_policy']): ?>
                                            <?php echo nl2br(htmlspecialchars($vendor['return_policy'])); ?>
                                        <?php else: ?>
                                            <p class="text-muted">No return policy specified.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <!-- Shipping Policy -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-white">
                                        <h5 class="mb-0"><i class="fas fa-shipping-fast me-2"></i> Shipping Policy</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($vendor['shipping_policy']): ?>
                                            <?php echo nl2br(htmlspecialchars($vendor['shipping_policy'])); ?>
                                        <?php else: ?>
                                            <p class="text-muted">No shipping policy specified.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-6">
                                <!-- Quality Guarantee -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body text-center">
                                        <i class="fas fa-award fa-3x text-warning mb-3"></i>
                                        <h5>Quality Guarantee</h5>
                                        <p class="text-muted small">
                                            All products sold by this vendor come with a quality guarantee. 
                                            If you're not satisfied, contact the vendor for resolution.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vendor Review Modal -->
<div class="modal fade" id="vendorReviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="vendorReviewForm">
                <div class="modal-header">
                    <h5 class="modal-title">Review Vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <h6>How would you rate this vendor?</h6>
                        <div class="rating-stars mb-3" style="font-size: 2rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star vendor-rating-star" data-rating="<?php echo $i; ?>" style="cursor: pointer; color: #ddd;"></i>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="vendor_rating" id="vendorSelectedRating" required>
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                        <div class="text-muted small" id="vendorRatingText">Tap to rate</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Review</label>
                        <textarea class="form-control" 
                                  name="vendor_review_text" 
                                  rows="4" 
                                  placeholder="Share your experience with this vendor..."
                                  required></textarea>
                    </div>
                    
                    <!-- Product Selection (optional) -->
                    <div class="mb-3">
                        <label class="form-label">Which product did you purchase? (Optional)</label>
                        <select class="form-select" name="product_id">
                            <option value="">Select a product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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

<!-- JavaScript -->
<script>
$(document).ready(function() {
    // Tab activation
    $('.nav-tabs button').click(function() {
        $('.nav-tabs button').removeClass('active');
        $(this).addClass('active');
    });
    
    // Rating stars for vendor review
    $('.vendor-rating-star').hover(function() {
        const rating = $(this).data('rating');
        highlightVendorStars(rating);
    });
    
    $('.vendor-rating-star').click(function() {
        const rating = $(this).data('rating');
        $('#vendorSelectedRating').val(rating);
        highlightVendorStars(rating);
        
        // Update rating text
        const ratingTexts = ['Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
        $('#vendorRatingText').text(ratingTexts[rating - 1]);
    });
    
    $('.rating-stars').mouseleave(function() {
        const currentRating = $('#vendorSelectedRating').val();
        if (currentRating) {
            highlightVendorStars(currentRating);
        } else {
            $('.vendor-rating-star').css('color', '#ddd');
        }
    });
    
    function highlightVendorStars(rating) {
        $('.vendor-rating-star').css('color', '#ddd');
        $('.vendor-rating-star').each(function(index) {
            if (index < rating) {
                $(this).css('color', '#ffc107');
            }
        });
    }
    
    // Vendor review submission
    $('#vendorReviewForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: '../ajax/submit-vendor-review.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Thank you for your review!', 'success');
                    $('#vendorReviewModal').modal('hide');
                    $('#vendorReviewForm')[0].reset();
                    
                    // Reload reviews tab after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showToast(response.message || 'Error submitting review', 'error');
                }
            }
        });
    });
    
    // Add to cart from vendor page
    $('.add-to-cart').click(function() {
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
.vendor-page .vendor-header {
    position: relative;
    overflow: hidden;
}

.vendor-page .vendor-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
}

.vendor-page .nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
    padding: 0.75rem 1rem;
}

.vendor-page .nav-tabs .nav-link.active {
    color: #007bff;
    border-bottom-color: #007bff;
    background: none;
}

.vendor-page .nav-tabs .nav-link:hover {
    border-bottom-color: #dee2e6;
}

.vendor-page .tab-content {
    min-height: 400px;
}

.vendor-page .hover-shadow {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.vendor-page .hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.vendor-page .object-fit-cover {
    object-fit: cover;
}

.vendor-page .accordion-button {
    font-size: 0.9rem;
    padding: 0.75rem 1rem;
}

.vendor-page .accordion-button:not(.collapsed) {
    background-color: #e7f1ff;
    color: #0c63e4;
}

.vendor-page .avatar-md {
    width: 50px;
    height: 50px;
}

.vendor-page .payment-method-card {
    transition: all 0.3s ease;
}

.vendor-page .payment-method-card:hover {
    background-color: #f8f9fa;
}

/* Business hours table */
.vendor-page .table-sm th,
.vendor-page .table-sm td {
    padding: 0.5rem;
}

/* Social media buttons */
.vendor-page .social-btn {
    min-width: 120px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>