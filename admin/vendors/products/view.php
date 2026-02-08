<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header(SITE_URL . 'index.php');
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        header(SITE_URL . 'vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header(SITE_URL . 'admin/vendors/dashboard.php');
    exit();
}

// Get product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid product ID.';
    header(SITE_URL . 'admin/vendors/products.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Fetch product details
try {
    $db = getDB();
    
    $query = "SELECT p.*, c.name as category_name, u.username as vendor_username, 
                     u.full_name as vendor_name, u.vendor_rating,
                     (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) as total_reviews,
                     (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id) as avg_rating,
                     (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as total_sold
              FROM products p 
              LEFT JOIN categories c ON p.category = c.slug 
              LEFT JOIN users u ON p.vendor_id = u.id 
              WHERE p.id = ? AND p.vendor_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $_SESSION['error'] = 'Product not found or access denied.';
        header(SITE_URL . 'admin/vendors/products.php');
        exit();
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading product: ' . $e->getMessage();
    header(SITE_URL . 'admin/vendors/products.php');
    exit();
}

// Get related products (same vendor, same category)
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, price, image, stock, approved_status 
                          FROM products 
                          WHERE vendor_id = ? AND category = ? AND id != ? 
                          AND approved_status = 'approved' 
                          ORDER BY created_at DESC 
                          LIMIT 4");
    $stmt->execute([$vendor_id, $product['category'], $product_id]);
    $related_products = $stmt->fetchAll();
} catch(PDOException $e) {
    $related_products = [];
}

// Get product reviews
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT r.*, u.username, u.profile_pic, u.full_name 
                          FROM reviews r 
                          LEFT JOIN users u ON r.user_id = u.id 
                          WHERE r.product_id = ? 
                          ORDER BY r.created_at DESC 
                          LIMIT 10");
    $stmt->execute([$product_id]);
    $reviews = $stmt->fetchAll();
    
    // Get review counts by rating
    $stmt = $db->prepare("SELECT rating, COUNT(*) as count FROM reviews WHERE product_id = ? GROUP BY rating ORDER BY rating DESC");
    $stmt->execute([$product_id]);
    $rating_counts = $stmt->fetchAll();
    
    $rating_summary = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    foreach($rating_counts as $row) {
        $rating_summary[$row['rating']] = (int)$row['count'];
    }
    
} catch(PDOException $e) {
    $reviews = [];
    $rating_summary = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
}

// Get order history for this product
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT o.*, oi.quantity, oi.unit_price, 
                                 DATE_FORMAT(o.order_date, '%Y-%m-%d') as order_date_formatted
                          FROM orders o 
                          JOIN order_items oi ON o.id = oi.order_id 
                          WHERE oi.product_id = ? 
                          ORDER BY o.order_date DESC 
                          LIMIT 10");
    $stmt->execute([$product_id]);
    $order_history = $stmt->fetchAll();
} catch(PDOException $e) {
    $order_history = [];
}

$page_title = 'View Product: ' . htmlspecialchars($product['name']);
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="../../../vendor/dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                            <li class="breadcrumb-item active" aria-current="page">View</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-warning">
                        <i class="fas fa-edit me-2"></i> Edit Product
                    </a>
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Left Column - Product Details -->
            <div class="col-lg-8">
                <!-- Product Info Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row">
                            <!-- Product Image -->
                            <div class="col-md-5">
                                <div class="product-image-container mb-3">
                                    <?php
                                    $image_path = SITE_URL . 'assets/images/products/' . ($product['image'] ?: 'default.png');
                                    $image_alt = htmlspecialchars($product['name']);
                                    ?>
                                    <img src="<?php echo $image_path; ?>" 
                                         alt="<?php echo $image_alt; ?>" 
                                         class="img-fluid rounded"
                                         onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'"
                                         style="max-height: 300px; object-fit: contain;">
                                </div>
                                
                                <!-- Status Badges -->
                                <div class="d-flex flex-wrap gap-2 mb-3">
                                    <span class="badge bg-<?php 
                                        echo $product['approved_status'] == 'approved' ? 'success' : 
                                             ($product['approved_status'] == 'pending' ? 'warning' : 'danger');
                                    ?>">
                                        <i class="fas fa-<?php 
                                            echo $product['approved_status'] == 'approved' ? 'check' : 
                                                 ($product['approved_status'] == 'pending' ? 'clock' : 'times');
                                        ?> me-1"></i>
                                        <?php echo ucfirst($product['approved_status']); ?>
                                    </span>
                                    
                                    <?php if ($product['featured']): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-star me-1"></i> Featured
                                        </span>
                                    <?php endif; ?>
                                    
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-eye me-1"></i> <?php echo number_format($product['views']); ?> views
                                    </span>
                                    
                                    <span class="badge bg-success">
                                        <i class="fas fa-shopping-cart me-1"></i> <?php echo number_format($product['total_sold']); ?> sold
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Product Details -->
                            <div class="col-md-7">
                                <div class="product-details">
                                    <h3 class="mb-3"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    
                                    <div class="mb-4">
                                        <div class="d-flex align-items-center mb-2">
                                            <h4 class="text-primary mb-0">
                                                $<?php echo number_format($product['price'], 2); ?>
                                            </h4>
                                            <?php if ($product['old_price'] && $product['old_price'] > $product['price']): ?>
                                                <span class="text-muted text-decoration-line-through ms-3">
                                                    $<?php echo number_format($product['old_price'], 2); ?>
                                                </span>
                                                <span class="badge bg-danger ms-2">
                                                    <?php 
                                                    $discount = (($product['old_price'] - $product['price']) / $product['old_price']) * 100;
                                                    echo '-' . number_format($discount, 0) . '%';
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Rating -->
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="rating-stars me-2">
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
                                            <span class="text-muted">
                                                <?php echo number_format($avg_rating, 1); ?> 
                                                (<?php echo $product['total_reviews']; ?> reviews)
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Product Info Table -->
                                    <div class="table-responsive mb-4">
                                        <table class="table table-sm table-bordered">
                                            <tbody>
                                                <tr>
                                                    <th width="30%">Product ID</th>
                                                    <td><?php echo $product['id']; ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Category</th>
                                                    <td>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo htmlspecialchars($product['category_name'] ?? $product['category']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Stock Status</th>
                                                    <td>
                                                        <?php if ($product['stock'] == 0): ?>
                                                            <span class="badge bg-danger">
                                                                <i class="fas fa-times me-1"></i> Out of Stock
                                                            </span>
                                                        <?php elseif ($product['stock'] < 10): ?>
                                                            <span class="badge bg-warning">
                                                                <i class="fas fa-exclamation-triangle me-1"></i> 
                                                                Low Stock (<?php echo $product['stock']; ?> left)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">
                                                                <i class="fas fa-check me-1"></i> 
                                                                In Stock (<?php echo $product['stock']; ?> available)
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>Created Date</th>
                                                    <td><?php echo date('F d, Y', strtotime($product['created_at'])); ?></td>
                                                </tr>
                                                <tr>
                                                    <th>Last Updated</th>
                                                    <td><?php echo date('F d, Y H:i', strtotime($product['updated_at'])); ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Description -->
                                    <div class="mb-4">
                                        <h5 class="mb-2">Description</h5>
                                        <div class="product-description border rounded p-3 bg-light">
                                            <?php 
                                            echo nl2br(htmlspecialchars($product['description'] ?: 'No description provided.'));
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="d-flex gap-2">
                                        <a href="edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                                            <i class="fas fa-edit me-2"></i> Edit Product
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>product-details.php?id=<?php echo $product_id; ?>" 
                                           target="_blank" class="btn btn-outline-success">
                                            <i class="fas fa-external-link-alt me-2"></i> View on Store
                                        </a>
                                        <a href="delete.php?id=<?php echo $product_id; ?>" 
                                           class="btn btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this product?');">
                                            <i class="fas fa-trash me-2"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reviews Section -->
                <?php if ($product['total_reviews'] > 0): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-star me-2 text-warning"></i>
                            Customer Reviews (<?php echo $product['total_reviews']; ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Rating Summary -->
                            <div class="col-md-4 mb-4 mb-md-0">
                                <div class="rating-summary p-3 border rounded">
                                    <h6 class="mb-3">Rating Distribution</h6>
                                    <?php for($i = 5; $i >= 1; $i--): 
                                        $count = $rating_summary[$i];
                                        $percentage = $product['total_reviews'] > 0 ? ($count / $product['total_reviews']) * 100 : 0;
                                    ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <small class="text-muted me-2" style="width: 20px;"><?php echo $i; ?>★</small>
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar bg-warning" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <small class="text-muted" style="width: 40px;">
                                            <?php echo $count; ?> (<?php echo number_format($percentage, 1); ?>%)
                                        </small>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <!-- Reviews List -->
                            <div class="col-md-8">
                                <?php if (count($reviews) > 0): ?>
                                    <div class="reviews-list" style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach($reviews as $review): ?>
                                        <div class="review-item border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2">
                                                        <?php
                                                        $avatar = $review['profile_pic'] ?? 'default.png';
                                                        $avatar_path = SITE_URL . 'assets/images/avatars/' . $avatar;
                                                        ?>
                                                        <img src="<?php echo $avatar_path; ?>" 
                                                             alt="<?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?>"
                                                             class="rounded-circle"
                                                             style="width: 32px; height: 32px; object-fit: cover;">
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($review['full_name'] ?? $review['username']); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                                        </small>
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
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-4">No reviews yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Right Column - Stats & Related Products -->
            <div class="col-lg-4">
                <!-- Sales Stats -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-chart-line me-2 text-primary"></i>
                            Sales Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-item text-center p-3 border rounded mb-3">
                                <h3 class="text-primary mb-1"><?php echo number_format($product['total_sold']); ?></h3>
                                <p class="text-muted mb-0">Total Sold</p>
                            </div>
                            
                            <div class="stat-item text-center p-3 border rounded mb-3">
                                <h3 class="text-success mb-1">
                                    $<?php echo number_format($product['total_sold'] * $product['price'], 2); ?>
                                </h3>
                                <p class="text-muted mb-0">Revenue</p>
                            </div>
                            
                            <div class="stat-item text-center p-3 border rounded mb-3">
                                <h3 class="text-warning mb-1"><?php echo number_format($product['views']); ?></h3>
                                <p class="text-muted mb-0">Views</p>
                            </div>
                            
                            <?php if ($product['total_sold'] > 0 && $product['views'] > 0): 
                                $conversion_rate = ($product['total_sold'] / $product['views']) * 100;
                            ?>
                            <div class="stat-item text-center p-3 border rounded">
                                <h3 class="text-info mb-1"><?php echo number_format($conversion_rate, 2); ?>%</h3>
                                <p class="text-muted mb-0">Conversion Rate</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <?php if (count($order_history) > 0): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-shopping-bag me-2 text-success"></i>
                            Recent Orders
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach($order_history as $order): ?>
                            <div class="list-group-item border-0 px-3 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Order #<?php echo $order['order_number']; ?></h6>
                                        <small class="text-muted"><?php echo $order['order_date_formatted']; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php 
                                            echo $order['status'] == 'delivered' ? 'success' : 
                                                 ($order['status'] == 'shipped' ? 'info' : 
                                                 ($order['status'] == 'processing' ? 'primary' : 'warning'));
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                        <br>
                                        <small class="text-primary fw-bold">
                                            <?php echo $order['quantity']; ?> × $<?php echo number_format($order['unit_price'], 2); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Related Products -->
                <?php if (count($related_products) > 0): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-boxes me-2 text-info"></i>
                            Related Products
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach($related_products as $related): 
                                $related_image = SITE_URL . 'assets/images/products/' . ($related['image'] ?: 'default.png');
                            ?>
                            <a href="view.php?id=<?php echo $related['id']; ?>" 
                               class="list-group-item list-group-item-action border-0 px-3 py-2">
                                <div class="d-flex align-items-center">
                                    <div class="product-thumb me-3">
                                        <img src="<?php echo $related_image; ?>" 
                                             alt="<?php echo htmlspecialchars($related['name']); ?>"
                                             class="rounded"
                                             style="width: 40px; height: 40px; object-fit: cover;">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($related['name']); ?></h6>
                                        <small class="text-muted">
                                            $<?php echo number_format($related['price'], 2); ?>
                                            <?php if ($related['stock'] == 0): ?>
                                                <span class="badge bg-danger ms-2">Out of Stock</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
.product-image-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 300px;
}

.rating-stars {
    color: #ffc107;
}

.product-description {
    line-height: 1.8;
    font-size: 0.95rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.stat-item {
    transition: transform 0.2s ease;
}

.stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.list-group-item {
    transition: all 0.2s ease;
}

.list-group-item:hover {
    background-color: rgba(67, 97, 238, 0.05);
}

.product-thumb img {
    border: 1px solid #dee2e6;
}
</style>

<script>
// Image preview for product image
document.addEventListener('DOMContentLoaded', function() {
    const mainImage = document.querySelector('.product-image-container img');
    
    // Add click to enlarge functionality
    if (mainImage) {
        mainImage.addEventListener('click', function() {
            const src = this.src;
            const modal = `
                <div class="modal fade" id="imageModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-0">
                            <div class="modal-header border-0">
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center p-0">
                                <img src="${src}" class="img-fluid" style="max-height: 70vh;">
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modal);
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
            
            // Remove modal after close
            document.getElementById('imageModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        });
    }
    
    // Auto-refresh reviews section every 30 seconds
    setInterval(function() {
        const reviewsSection = document.querySelector('.reviews-list');
        if (reviewsSection) {
            fetch('?partial=reviews&id=<?php echo $product_id; ?>')
                .then(response => response.text())
                .then(html => {
                    reviewsSection.innerHTML = html;
                })
                .catch(error => console.error('Error refreshing reviews:', error));
        }
    }, 30000);
});
</script>

<?php require_once '../../includes/footer.php'; ?>