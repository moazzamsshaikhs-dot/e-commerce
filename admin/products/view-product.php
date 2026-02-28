<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$product_id = (int)($_GET['id'] ?? 0);
if (!$product_id) {
    $_SESSION['error'] = 'Invalid product ID';
    header('Location: products.php');
    exit();
}

$db = getDB();

try {
    // Get product details with more information - FIXED QUERY
    $stmt = $db->prepare("
        SELECT p.*, 
               u.email, 
               u.full_name, 
               u.username,
               u.profile_pic,
               u.phone,
               c.name as category_name,
               c.slug as category_slug,
               c.commission_rate,
               (SELECT COUNT(*) FROM products WHERE vendor_id = p.vendor_id) as vendor_total_products,
               (SELECT AVG(rating) FROM reviews WHERE product_id = p.id) as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE product_id = p.id) as review_count
        FROM products p 
        JOIN users u ON p.vendor_id = u.id 
        LEFT JOIN categories c ON p.category = c.slug  /* Fixed: Using p.category instead of p.category_id */
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    // Debug - check commission rate
    error_log("Product ID: {$product_id}, Category: {$product['category']}, Commission Rate: {$product['commission_rate']}");
    
    // Get product images
    $images = [];
    if (!empty($product['images'])) {
        $images = json_decode($product['images'], true) ?: [];
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: products.php');
    exit();
}

// Determine stock status
$stock_status = 'in-stock';
$stock_color = 'success';
$stock_icon = 'fa-check-circle';
$stock_text = 'In Stock';

if ($product['stock'] <= 0) {
    $stock_status = 'out-of-stock';
    $stock_color = 'danger';
    $stock_icon = 'fa-times-circle';
    $stock_text = 'Out of Stock';
} elseif ($product['stock'] < 10) {
    $stock_status = 'low-stock';
    $stock_color = 'warning';
    $stock_icon = 'fa-exclamation-triangle';
    $stock_text = 'Low Stock';
}

require_once '../includes/header.php';
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
    --border: #edf2f9;
}

.view-product-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Page Header */
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

/* Product Gallery */
.product-gallery {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    height: 100%;
}

.main-image-container {
    background: var(--light);
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 20px;
    text-align: center;
    border: 2px dashed var(--border);
    position: relative;
}

.main-product-image {
    max-width: 100%;
    max-height: 300px;
    object-fit: contain;
}

.image-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    color: var(--dark);
}

.thumbnail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
    gap: 10px;
}

.thumbnail-item {
    background: var(--light);
    border-radius: 10px;
    padding: 5px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    cursor: pointer;
}

.thumbnail-item:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.thumbnail-item.active {
    border-color: var(--primary);
    background: rgba(67, 97, 238, 0.05);
}

.thumbnail-image {
    width: 100%;
    height: 60px;
    object-fit: cover;
    border-radius: 5px;
}

/* Product Info Card */
.product-info-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    height: 100%;
}

.product-title {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 15px;
}

.product-meta {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #6c757d;
}

.meta-item i {
    color: var(--primary);
}

/* Price Section */
.price-section {
    background: rgba(67, 97, 238, 0.05);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.current-price {
    font-size: 32px;
    font-weight: 700;
    color: var(--primary);
    line-height: 1;
}

.current-price small {
    font-size: 14px;
    font-weight: 400;
    color: #6c757d;
    margin-left: 8px;
}

.old-price {
    font-size: 18px;
    color: #6c757d;
    text-decoration: line-through;
    margin-left: 15px;
}

.discount-badge {
    background: var(--success);
    color: white;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 600;
}

/* Stock Status */
.stock-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 20px;
}

.stock-status.in-stock {
    background: rgba(6, 214, 160, 0.1);
    color: var(--success);
}

.stock-status.low-stock {
    background: rgba(255, 183, 3, 0.1);
    color: var(--warning);
}

.stock-status.out-of-stock {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger);
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.info-item {
    background: var(--light);
    border-radius: 12px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
}

.info-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.info-icon {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
}

.info-label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 4px;
}

.info-value {
    font-weight: 600;
    color: var(--dark);
}

/* Description */
.description-section {
    background: var(--light);
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
}

.description-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.description-title i {
    color: var(--primary);
}

.description-content {
    color: #6c757d;
    line-height: 1.7;
    white-space: pre-line;
}

/* Vendor Card */
.vendor-card {
    background: linear-gradient(135deg, var(--dark) 0%, #1a1e2f 100%);
    border-radius: 15px;
    padding: 20px;
    color: white;
    margin-top: 20px;
    position: relative;
    overflow: hidden;
}

.vendor-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
}

.vendor-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
    position: relative;
    z-index: 1;
}

.vendor-avatar {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.2);
}

.vendor-info h5 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.vendor-info .vendor-meta {
    font-size: 12px;
    opacity: 0.8;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.vendor-stats {
    display: flex;
    gap: 15px;
    margin-top: 15px;
    position: relative;
    z-index: 1;
}

.vendor-stat {
    flex: 1;
    text-align: center;
    padding: 10px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
}

.vendor-stat .value {
    font-size: 18px;
    font-weight: 700;
}

.vendor-stat .label {
    font-size: 11px;
    opacity: 0.8;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 12px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 500;
    font-size: 14px;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: #3651c4;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
    color: white;
}

.btn-success {
    background: var(--success);
    color: white;
}

.btn-success:hover {
    background: #05b585;
    transform: translateY(-2px);
    color: white;
}

.btn-warning {
    background: var(--warning);
    color: white;
}

.btn-warning:hover {
    background: #e6a500;
    transform: translateY(-2px);
    color: white;
}

.btn-danger {
    background: var(--danger);
    color: white;
}

.btn-danger:hover {
    background: #d64161;
    transform: translateY(-2px);
    color: white;
}

.btn-outline {
    background: transparent;
    border: 2px solid var(--border);
    color: var(--dark);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* Badges */
.badge {
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.badge-pending { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.badge-approved { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.badge-rejected { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
.badge-featured { background: rgba(67, 97, 238, 0.1); color: var(--primary); }

/* Modal Styles */
.modal-content {
    border-radius: 20px;
    border: none;
}

.modal-header {
    border-radius: 20px 20px 0 0;
    padding: 20px 25px;
}

.modal-header.bg-success {
    background: var(--success) !important;
}

.modal-header.bg-danger {
    background: var(--danger) !important;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid var(--border);
    padding: 20px 25px;
}

/* Responsive */
@media (max-width: 992px) {
    .view-product-container {
        padding: 20px;
    }
    
    .product-title {
        font-size: 24px;
    }
    
    .current-price {
        font-size: 28px;
    }
}

@media (max-width: 768px) {
    .product-gallery,
    .product-info-card {
        margin-bottom: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="view-product-container">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-box me-2 text-primary"></i>
                        Product Details
                    </h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        View complete information about this product
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="products.php<?php echo isset($_GET['vendor']) ? '?vendor=' . $_GET['vendor'] : ''; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Products
                    </a>
                    <a href="product-action.php?action=edit&id=<?php echo $product_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Edit Product
                    </a>
                </div>
            </div>
        </div>

        <!-- Debug Info (hidden by default) - Remove in production -->
        <?php if (isset($_GET['debug'])): ?>
        <div class="alert alert-info mb-4">
            <strong>Debug Info:</strong><br>
            Product Category Slug: <?php echo $product['category'] ?: 'NULL'; ?><br>
            Category Name: <?php echo $product['category_name'] ?: 'Not found'; ?><br>
            Commission Rate: <?php echo $product['commission_rate'] ?: '0'; ?>%
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Left Column - Product Gallery -->
            <div class="col-lg-5">
                <div class="product-gallery">
                    <div class="main-image-container">
                        <?php 
                        $main_image = !empty($product['image']) ? SITE_URL . 'assets/images/products/' . $product['image'] : '../../../assets/images/no-image.png';
                        ?>
                        <img src="<?php echo $main_image; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                             class="main-product-image"
                             id="mainProductImage"
                             onerror="this.src='<?php echo SITE_URL; ?>assets/images/no-image.png';">
                        
                        <?php if ($product['is_featured']): ?>
                            <span class="image-badge">
                                <i class="fas fa-star text-warning me-1"></i> Featured
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($images)): ?>
                    <div class="thumbnail-grid">
                        <?php foreach ($images as $index => $image): ?>
                        <div class="thumbnail-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                             onclick="changeImage('<?php echo SITE_URL . 'assets/images/products/' . $image; ?>', this)">
                            <img src="<?php echo SITE_URL . 'assets/images/products/' . $image; ?>" 
                                 alt="Thumbnail" 
                                 class="thumbnail-image"
                                 onerror="this.src='<?php echo SITE_URL; ?>assets/images/no-image.png';">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column - Product Info -->
            <div class="col-lg-7">
                <div class="product-info-card">
                    <!-- Title and Status -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <?php if ($product['approved_status'] == 'pending'): ?>
                            <span class="badge badge-pending">Pending</span>
                        <?php elseif ($product['approved_status'] == 'approved'): ?>
                            <span class="badge badge-approved">Approved</span>
                        <?php elseif ($product['approved_status'] == 'rejected'): ?>
                            <span class="badge badge-rejected">Rejected</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-meta">
                        <span class="meta-item">
                            <i class="fas fa-hashtag"></i> ID: #<?php echo str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-calendar-alt"></i> Added: <?php echo date('d M Y', strtotime($product['created_at'])); ?>
                        </span>
                        <span class="meta-item">
                            <i class="fas fa-sync-alt"></i> Updated: <?php echo date('d M Y', strtotime($product['updated_at'])); ?>
                        </span>
                    </div>

                    <!-- Price Section -->
                    <div class="price-section">
                        <div>
                            <span class="current-price">
                                $<?php echo number_format($product['price'], 2); ?>
                                <small>USD</small>
                            </span>
                            <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                                <span class="old-price">$<?php echo number_format($product['old_price'], 2); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                            <?php 
                            $discount = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100);
                            ?>
                            <span class="discount-badge">
                                <i class="fas fa-tag me-1"></i> <?php echo $discount; ?>% OFF
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Stock Status -->
                    <div class="stock-status <?php echo $stock_status; ?>">
                        <i class="fas <?php echo $stock_icon; ?>"></i>
                        <span><?php echo $stock_text; ?> (<?php echo $product['stock']; ?> units available)</span>
                    </div>

                    <!-- Quick Info Grid -->
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Category</div>
                                <div class="info-value"><?php echo htmlspecialchars($product['category_name'] ?? $product['category'] ?? 'Uncategorized'); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-percentage"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Commission</div>
                                <div class="info-value"><?php echo $product['commission_rate'] ?? '0'; ?>%</div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Views</div>
                                <div class="info-value"><?php echo number_format($product['views'] ?? 0); ?></div>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="info-content">
                                <div class="info-label">Sales</div>
                                <div class="info-value"><?php echo number_format($product['sales_count'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($product['description'])): ?>
                    <div class="description-section">
                        <div class="description-title">
                            <i class="fas fa-align-left"></i>
                            Description
                        </div>
                        <div class="description-content">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Vendor Card -->
                    <div class="vendor-card">
                        <div class="vendor-header">
                            <?php 
                            $vendor_avatar = !empty($product['profile_pic']) ? SITE_URL . 'assets/images/profiles/' . $product['profile_pic'] : SITE_URL . 'assets/images/avatars/default.png';
                            ?>
                            <img src="<?php echo $vendor_avatar; ?>" alt="Vendor" class="vendor-avatar"
                                 onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png';">
                            <div class="vendor-info">
                                <h5><?php echo htmlspecialchars($product['full_name']); ?></h5>
                                <div class="vendor-meta">
                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($product['email']); ?></span>
                                    <?php if (!empty($product['phone'])): ?>
                                        <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($product['phone']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="vendor-stats">
                            <div class="vendor-stat">
                                <div class="value"><?php echo $product['vendor_total_products'] ?? 0; ?></div>
                                <div class="label">Total Products</div>
                            </div>
                            <div class="vendor-stat">
                                <div class="value">
                                    <?php if ($product['avg_rating']): ?>
                                        <?php echo number_format($product['avg_rating'], 1); ?>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </div>
                                <div class="label">Avg Rating</div>
                            </div>
                            <div class="vendor-stat">
                                <div class="value"><?php echo $product['review_count'] ?? 0; ?></div>
                                <div class="label">Reviews</div>
                            </div>
                        </div>

                        <div class="mt-3 text-end">
                            <a href="../vendors/view-vendor.php?id=<?php echo $product['vendor_id']; ?>" class="btn btn-sm" style="background: rgba(255,255,255,0.1); color: white; border: none;">
                                <i class="fas fa-store me-2"></i> View Vendor Profile
                            </a>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($product['approved_status'] == 'pending'): ?>
                            <button class="btn-action btn-success" onclick="approveProduct(<?php echo $product['id']; ?>)">
                                <i class="fas fa-check-circle"></i> Approve Product
                            </button>
                            <button class="btn-action btn-danger" onclick="rejectProduct(<?php echo $product['id']; ?>)">
                                <i class="fas fa-times-circle"></i> Reject Product
                            </button>
                        <?php endif; ?>
                        
                        <a href="?toggle_featured=<?php echo $product['id']; ?>" 
                           class="btn-action <?php echo $product['is_featured'] ? 'btn-warning' : 'btn-outline'; ?>"
                           onclick="return confirm('<?php echo $product['is_featured'] ? 'Remove from featured?' : 'Mark as featured?'; ?>')">
                            <i class="fas fa-star"></i> 
                            <?php echo $product['is_featured'] ? 'Remove Featured' : 'Mark Featured'; ?>
                        </a>
                        
                        <a href="delete-product.php?id=<?php echo $product['id']; ?>" 
                           class="btn-action btn-danger"
                           onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">
                            <i class="fas fa-trash"></i> Delete Product
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="action/approve-product.php">
                <input type="hidden" name="product_id" id="approve_product_id">
                <input type="hidden" name="redirect" value="view-product.php?id=<?php echo $product_id; ?>">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Approve Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5>Confirm Approval</h5>
                    <p class="text-muted">Are you sure you want to approve this product?</p>
                    <p class="small text-muted">The product will be visible to customers immediately.</p>
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
            <form method="POST" action="action/reject-product.php">
                <input type="hidden" name="product_id" id="reject_product_id">
                <input type="hidden" name="redirect" value="view-product.php?id=<?php echo $product_id; ?>">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>
                        Reject Product
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-times-circle fa-4x text-danger"></i>
                    </div>
                    <h5 class="text-center mb-3">Provide Rejection Reason</h5>
                    <div class="mb-3">
                        <textarea name="rejection_reason" class="form-control" rows="4" 
                                  placeholder="Please explain why this product is being rejected..." required></textarea>
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

<script>
// Change main image when thumbnail clicked
function changeImage(src, element) {
    document.getElementById('mainProductImage').src = src;
    
    // Remove active class from all thumbnails
    document.querySelectorAll('.thumbnail-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Add active class to clicked thumbnail
    element.classList.add('active');
}

// Approve product
function approveProduct(id) {
    document.getElementById('approve_product_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

// Reject product
function rejectProduct(id) {
    document.getElementById('reject_product_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

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