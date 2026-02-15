<?php
// admin/vendors/products/ajax/get_product_details.php
// FIXED: Correct path to config
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Define SITE_URL if not defined (FIXED)
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/e-commerce/');
}

// Set header for JSON response
header('Content-Type: application/json');

// Check if user is vendor
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'vendor') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Vendor only.']);
    exit();
}

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$vendor_id = (int)$_SESSION['user_id'];

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

try {
    $db = getDB();
    
    // Get product details with all related information
    $stmt = $db->prepare("
        SELECT 
            p.*,
            COUNT(DISTINCT o.id) as total_orders,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.id) as review_count,
            COALESCE((
                SELECT SUM(oi.quantity) 
                FROM order_items oi 
                JOIN orders o ON oi.order_id = o.id 
                WHERE oi.product_id = p.id 
                AND o.status != 'cancelled'
                AND o.order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ), 0) as sales_last_30_days,
            COALESCE((
                SELECT SUM(oi.quantity) 
                FROM order_items oi 
                WHERE oi.product_id = p.id
            ), 0) as total_sold,
            (
                SELECT COUNT(*) 
                FROM wishlist w 
                WHERE w.product_id = p.id
            ) as wishlist_count,
            DATEDIFF(NOW(), p.created_at) as days_since_created,
            COALESCE((
                SELECT five_star_count FROM products WHERE id = p.id
            ), 0) as five_star_count,
            COALESCE((
                SELECT four_star_count FROM products WHERE id = p.id
            ), 0) as four_star_count,
            COALESCE((
                SELECT three_star_count FROM products WHERE id = p.id
            ), 0) as three_star_count,
            COALESCE((
                SELECT two_star_count FROM products WHERE id = p.id
            ), 0) as two_star_count,
            COALESCE((
                SELECT one_star_count FROM products WHERE id = p.id
            ), 0) as one_star_count
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status != 'cancelled'
        LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
        WHERE p.id = ? AND p.vendor_id = ?
        GROUP BY p.id
    ");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
        exit();
    }
    
    // Get recent orders for this product
    $orders_stmt = $db->prepare("
        SELECT 
            o.id,
            o.order_number,
            o.order_date,
            o.status,
            o.total_amount,
            oi.quantity,
            oi.unit_price,
            u.username as customer_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN users u ON o.user_id = u.id
        WHERE oi.product_id = ? AND o.status != 'cancelled'
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $orders_stmt->execute([$product_id]);
    $recent_orders = $orders_stmt->fetchAll();
    
    // Get recent reviews
    $reviews_stmt = $db->prepare("
        SELECT 
            r.*,
            u.username,
            u.profile_pic,
            DATEDIFF(NOW(), r.created_at) as days_ago
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.product_id = ? AND r.is_approved = 1
        ORDER BY r.created_at DESC
        LIMIT 3
    ");
    $reviews_stmt->execute([$product_id]);
    $recent_reviews = $reviews_stmt->fetchAll();
    
    // Generate HTML for modal
    ob_start();
    ?>
    
    <div class="container-fluid p-0">
        <!-- Product Header with Image and Basic Info -->
        <div class="row g-0 mb-4">
            <div class="col-md-4">
                <div class="product-image-container text-center p-3 bg-light rounded">
                    <?php if (!empty($product['image'])): ?>
                        <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo htmlspecialchars($product['image']); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="img-fluid rounded shadow-sm" 
                             style="max-height: 200px; object-fit: contain;"
                             onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/no-image.png';">
                    <?php else: ?>
                        <div class="no-image-placeholder p-4">
                            <i class="fas fa-image fa-4x text-muted mb-3"></i>
                            <p class="text-muted">No image available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-8">
                <div class="p-3">
                    <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($product['name']); ?></h4>
                    
                    <!-- Status Badges -->
                    <div class="mb-3">
                        <?php
                        $stock_status = '';
                        $status_color = '';
                        if ($product['stock'] == 0) {
                            $stock_status = 'Out of Stock';
                            $status_color = 'danger';
                        } elseif ($product['stock'] < 5) {
                            $stock_status = 'Critical Stock';
                            $status_color = 'warning';
                        } elseif ($product['stock'] < 10) {
                            $stock_status = 'Low Stock';
                            $status_color = 'info';
                        } else {
                            $stock_status = 'In Stock';
                            $status_color = 'success';
                        }
                        ?>
                        <span class="badge bg-<?php echo $status_color; ?> me-2 p-2">
                            <i class="fas fa-<?php echo $status_color == 'success' ? 'check' : 'exclamation'; ?>-circle me-1"></i>
                            <?php echo $stock_status; ?>
                        </span>
                        
                        <span class="badge bg-<?php echo $product['approved_status'] == 'approved' ? 'success' : 'warning'; ?> p-2">
                            <i class="fas fa-<?php echo $product['approved_status'] == 'approved' ? 'check' : 'clock'; ?>-circle me-1"></i>
                            <?php echo ucfirst($product['approved_status']); ?>
                        </span>
                        
                        <?php if ($product['featured']): ?>
                            <span class="badge bg-warning text-dark p-2">
                                <i class="fas fa-star me-1"></i> Featured
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="bg-light p-2 rounded text-center">
                                <small class="text-muted d-block">Price</small>
                                <strong class="h5 text-primary">$<?php echo number_format($product['price'], 2); ?></strong>
                                <?php if (!empty($product['old_price']) && $product['old_price'] > 0): ?>
                                    <br><small class="text-muted text-decoration-line-through">$<?php echo number_format($product['old_price'], 2); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light p-2 rounded text-center">
                                <small class="text-muted d-block">Current Stock</small>
                                <strong class="h5 <?php echo $product['stock'] < 5 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $product['stock']; ?> units
                                </strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category and Date -->
                    <div class="mb-2">
                        <i class="fas fa-folder text-primary me-2"></i>
                        <strong>Category:</strong> 
                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($product['category'] ?? 'Uncategorized'); ?></span>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-calendar text-primary me-2"></i>
                        <strong>Created:</strong> <?php echo date('F j, Y', strtotime($product['created_at'])); ?>
                        <small class="text-muted">(<?php echo $product['days_since_created']; ?> days ago)</small>
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-sync-alt text-primary me-2"></i>
                        <strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($product['updated_at'])); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Description Section -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="fas fa-align-left me-2 text-primary"></i> Description</h6>
            </div>
            <div class="card-body">
                <p class="mb-0"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 bg-primary bg-opacity-10 text-center p-3">
                    <i class="fas fa-shopping-cart fa-2x text-primary mb-2"></i>
                    <h5 class="mb-0"><?php echo $product['total_orders']; ?></h5>
                    <small class="text-muted">Total Orders</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 bg-success bg-opacity-10 text-center p-3">
                    <i class="fas fa-chart-line fa-2x text-success mb-2"></i>
                    <h5 class="mb-0"><?php echo $product['total_sold']; ?></h5>
                    <small class="text-muted">Units Sold</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 bg-warning bg-opacity-10 text-center p-3">
                    <i class="fas fa-fire fa-2x text-warning mb-2"></i>
                    <h5 class="mb-0"><?php echo $product['sales_last_30_days']; ?></h5>
                    <small class="text-muted">Last 30 Days</small>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 bg-info bg-opacity-10 text-center p-3">
                    <i class="fas fa-heart fa-2x text-info mb-2"></i>
                    <h5 class="mb-0"><?php echo $product['wishlist_count']; ?></h5>
                    <small class="text-muted">In Wishlists</small>
                </div>
            </div>
        </div>
        
        <!-- Rating Summary -->
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="fas fa-star me-2 text-warning"></i> Rating Summary</h6>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4 text-center">
                        <h1 class="display-4 text-warning mb-0"><?php echo number_format($product['avg_rating'], 1); ?></h1>
                        <div class="text-warning mb-2">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($product['avg_rating']) ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted"><?php echo $product['review_count']; ?> reviews</p>
                    </div>
                    <div class="col-md-8">
                        <?php
                        $rating_breakdown = [
                            5 => $product['five_star_count'] ?? 0,
                            4 => $product['four_star_count'] ?? 0,
                            3 => $product['three_star_count'] ?? 0,
                            2 => $product['two_star_count'] ?? 0,
                            1 => $product['one_star_count'] ?? 0
                        ];
                        $max_count = max($rating_breakdown) ?: 1;
                        
                        foreach($rating_breakdown as $stars => $count):
                            $percentage = ($count / $max_count) * 100;
                        ?>
                        <div class="row align-items-center g-2 mb-1">
                            <div class="col-2 text-end"><?php echo $stars; ?> <i class="fas fa-star text-warning small"></i></div>
                            <div class="col-8">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                            <div class="col-2"><small><?php echo $count; ?></small></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Reviews -->
        <?php if (!empty($recent_reviews)): ?>
        <div class="card mb-4 border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-comment me-2 text-primary"></i> Recent Reviews</h6>
                <a href="../reviews.php?product_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php foreach($recent_reviews as $review): ?>
                <div class="d-flex mb-3 pb-3 border-bottom">
                    <div class="flex-shrink-0">
                        <?php if (!empty($review['profile_pic']) && $review['profile_pic'] != 'default.png'): ?>
                            <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo htmlspecialchars($review['profile_pic']); ?>" 
                                 class="rounded-circle" width="50" height="50" style="object-fit: cover;"
                                 onerror="this.onerror=null; this.src='<?php echo SITE_URL; ?>assets/images/default-avatar.png';">
                        <?php else: ?>
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-user text-muted fa-2x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="d-flex justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($review['username']); ?></h6>
                            <small class="text-muted"><?php echo $review['days_ago']; ?> days ago</small>
                        </div>
                        <div class="text-warning mb-2">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Orders -->
        <?php if (!empty($recent_orders)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fas fa-truck me-2 text-primary"></i> Recent Orders</h6>
                <a href="../../../orders.php?product_id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_orders as $order): ?>
                            <tr>
                                <td>
                                    <a href="../../../orders/view.php?id=<?php echo $order['id']; ?>" class="text-decoration-none" target="_blank">
                                        #<?php echo $order['order_number']; ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'shipped' => 'primary',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger'
                                    ];
                                    $color = $status_colors[$order['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
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
    
    <?php
    $html = ob_get_clean();
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch(PDOException $e) {
    error_log("Product details error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
} catch(Exception $e) {
    error_log("Product details general error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>