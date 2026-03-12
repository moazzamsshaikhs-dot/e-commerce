<?php
ob_start();
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'user') {
    redirect('../../login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get all orders
$stmt = $db->prepare("
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           SUM(oi.quantity) as total_items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.order_date DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Orders';
require_once '../../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-shopping-bag me-2"></i>My Orders</h4>
        <a href="../dashboard.php" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>

    <?php if (empty($orders)): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
            <h5>No orders yet</h5>
            <p class="text-muted">Start shopping to place your first order</p>
            <a href="<?php echo SITE_URL; ?>category.php" class="btn btn-primary">
                <i class="fas fa-shopping-cart me-2"></i>Shop Now
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($orders as $order): ?>
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">Order #<?php echo $order['order_number']; ?></span>
                                <span class="badge bg-<?php 
                                    echo $order['status'] == 'delivered' ? 'success' : 
                                        ($order['status'] == 'cancelled' ? 'danger' : 
                                        ($order['status'] == 'pending' ? 'warning' : 'info')); 
                                ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Order Date</small>
                                    <strong><?php echo date('d M Y', strtotime($order['order_date'])); ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Total Amount</small>
                                    <strong class="text-primary">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted d-block">Items</small>
                                    <strong><?php echo $order['total_items']; ?> items</strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Payment</small>
                                    <span class="badge bg-<?php echo $order['payment_status'] == 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between">
                                <a href="order-confirmation.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                
                                <?php if ($order['status'] == 'delivered'): ?>
                                    <a href="rate-order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-star me-1"></i>Rate
                                    </a>
                                <?php elseif ($order['status'] == 'pending'): ?>
                                    <a href="track-order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-truck me-1"></i>Track
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($order['payment_method'] == 'bank' && $order['payment_status'] == 'pending'): ?>
                                    <a href="upload-payment-slip.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-upload me-1"></i>Upload Slip
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>