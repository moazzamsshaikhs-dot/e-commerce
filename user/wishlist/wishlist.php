<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'vendor') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'My Wishlist';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get wishlist items
$stmt = $db->prepare("
    SELECT w.*, p.*, c.name as category_name 
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN categories c ON p.category = c.id 
    WHERE w.user_id = ?
    ORDER BY w.added_at DESC
");
$stmt->execute([$user_id]);
$wishlist_items = $stmt->fetchAll();

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM wishlist WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_items = $stmt->fetch()['total'];

// Log activity
logUserActivity($user_id, 'wishlist_view', 'Viewed wishlist');
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">My Wishlist</h1>
                    <p class="text-muted mb-0"><?php echo $total_items; ?> items saved</p>
                </div>
                <div>
                    <a href="<?php echo SITE_URL; ?>user/orders/shop.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>

        <!-- Wishlist Items -->
        <?php if (empty($wishlist_items)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-heart fa-4x text-muted mb-4"></i>
                    <h4>Your wishlist is empty</h4>
                    <p class="text-muted mb-4">Save your favorite products here for easy access</p>
                    <a href="<?php echo SITE_URL; ?>/user/orders/shop.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="card product-card border-0 shadow-sm h-100">
                            <?php if ($item['image']): ?>
                                <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $item['image']; ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-light card-img-top d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <i class="fas fa-box fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($item['old_price'] && $item['old_price'] > $item['price']): ?>
                                <span class="badge bg-danger position-absolute" style="top: 10px; left: 10px;">
                                    Save <?php echo round((($item['old_price'] - $item['price']) / $item['old_price']) * 100); ?>%
                                </span>
                            <?php endif; ?>
                            
                            <button class="btn btn-danger btn-sm position-absolute remove-wishlist-btn" 
                                    style="top: 10px; right: 10px;"
                                    data-product-id="<?php echo $item['id']; ?>">
                                <i class="fas fa-heart"></i>
                            </button>
                            
                            <div class="card-body d-flex flex-column">
                                <h6 class="card-title mb-2">
                                    <a href="<?php echo SITE_URL; ?>user/orders/product-details.php?id=<?php echo $item['id']; ?>" 
                                       class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </h6>
                                
                                <?php if ($item['category_name']): ?>
                                    <small class="text-muted d-block mb-2">
                                        <i class="fas fa-tag me-1"></i><?php echo $item['category_name']; ?>
                                    </small>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <span class="h5 text-primary">$<?php echo number_format($item['price'], 2); ?></span>
                                    <?php if ($item['old_price'] && $item['old_price'] > $item['price']): ?>
                                        <span class="text-muted text-decoration-line-through ms-2">
                                            $<?php echo number_format($item['old_price'], 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-auto">
                                    <?php if ($item['stock'] > 0): ?>
                                        <span class="badge bg-success mb-2">
                                            <i class="fas fa-check me-1"></i> In Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger mb-2">
                                            <i class="fas fa-times me-1"></i> Out of Stock
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo SITE_URL; ?>user/orders/product-details.php?id=<?php echo $item['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                        <?php if ($item['stock'] > 0): ?>
                                            <button class="btn btn-primary btn-sm add-to-cart-btn" 
                                                    data-product-id="<?php echo $item['id']; ?>">
                                                <i class="fas fa-cart-plus me-1"></i> Add to Cart
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>
                                                <i class="fas fa-bell me-1"></i> Notify When Available
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Clear Wishlist Button -->
            <div class="text-center mt-4">
                <button class="btn btn-outline-danger" id="clearWishlistBtn">
                    <i class="fas fa-trash me-2"></i> Clear All Wishlist Items
                </button>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove from wishlist
    document.querySelectorAll('.remove-wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const productCard = this.closest('.product-card');
            
            fetch('wishlist-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    productCard.remove();
                    updateWishlistCount();
                    showToast('Removed from wishlist', 'success');
                    
                    // If no items left, show empty message
                    if (document.querySelectorAll('.product-card').length === 0) {
                        location.reload();
                    }
                } else {
                    showToast(data.message || 'Error removing item', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error', 'error');
            });
        });
    });
    
    // Add to cart
    document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            
            fetch('cart-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Added to cart!', 'success');
                } else {
                    showToast(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error', 'error');
            });
        });
    });
    
    // Clear all wishlist items
    const clearWishlistBtn = document.getElementById('clearWishlistBtn');
    if (clearWishlistBtn) {
        clearWishlistBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to clear all items from your wishlist?')) {
                fetch('wishlist-ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'clear_all'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Wishlist cleared', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        showToast(data.message || 'Error clearing wishlist', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error', 'error');
                });
            }
        });
    }
});

function updateWishlistCount() {
    const wishlistCount = document.querySelector('.wishlist-count');
    if (wishlistCount) {
        fetch('wishlist-ajax.php?action=get_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    wishlistCount.textContent = data.count;
                }
            });
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<style>
.product-card {
    transition: transform 0.3s, box-shadow 0.3s;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
</style>

<?php require_once '../../includes/footer.php'; ?>