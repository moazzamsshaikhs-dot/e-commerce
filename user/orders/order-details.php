<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Order ID is required';
    redirect('orders.php');
}

$page_title = 'Order Details';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];
$order_id = intval($_GET['id']);

// Get order details
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = 'Order not found or access denied';
    redirect('orders.php');
}

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.name as product_name, p.image, p.id as product_id
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Get order notes
$stmt = $db->prepare("
    SELECT onotes.*, u.full_name as user_name 
    FROM order_notes onotes 
    LEFT JOIN users u ON onotes.user_id = u.id 
    WHERE onotes.order_id = ? 
    ORDER BY onotes.created_at DESC
");
$stmt->execute([$order_id]);
$order_notes = $stmt->fetchAll();

// Get order status history
$stmt = $db->prepare("
    SELECT osh.*, u.full_name as changed_by_name 
    FROM order_status_history osh 
    LEFT JOIN users u ON osh.changed_by = u.id 
    WHERE osh.order_id = ? 
    ORDER BY osh.created_at ASC
");
$stmt->execute([$order_id]);
$status_history = $stmt->fetchAll();

// Log activity
logUserActivity($user_id, 'order_details', 'Viewed order details: ' . $order['order_number']);
?>

<div class="dashboard-container">
    
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Order Details</h1>
                    <p class="text-muted mb-0">#<?php echo $order['order_number']; ?></p>
                </div>
                <div>
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                    <?php if ($order['status'] === 'pending'): ?>
                        <button class="btn btn-danger ms-2" id="cancelOrderBtn">
                            <i class="fas fa-times me-2"></i> Cancel Order
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left Column - Order Items -->
            <div class="col-lg-8">
                <!-- Order Items Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Order Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if ($item['image']): ?>
                                                        <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $item['image']; ?>" 
                                                             alt="<?php echo htmlspecialchars($item['product_name'] ?? $item['description']); ?>" 
                                                             class="rounded me-3" width="60" height="60" style="object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                             style="width: 60px; height: 60px;">
                                                            <i class="fas fa-box text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name'] ?? $item['description']); ?></h6>
                                                        <?php if ($item['product_id']): ?>
                                                            <small>
                                                                <a href="<?php echo SITE_URL; ?>user/orders/product-details.php?id=<?php echo $item['product_id']; ?>" 
                                                                   class="text-decoration-none">
                                                                    View Product
                                                                </a>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                        <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                        <td><strong>$<?php echo number_format($order['shipping_cost'] ?? 0, 2); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                        <td><strong>$<?php echo number_format($order['total_amount'] + ($order['shipping_cost'] ?? 0), 2); ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Order Notes -->
                <?php if (!empty($order_notes)): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-0">Order Notes</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($order_notes as $note): ?>
                                <div class="mb-3 p-3 border rounded <?php echo $note['note_type'] === 'customer' ? 'bg-light' : 'bg-white'; ?>">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="badge bg-<?php echo $note['note_type'] === 'customer' ? 'info' : 'secondary'; ?>">
                                            <?php echo ucfirst($note['note_type']); ?> Note
                                        </span>
                                        <small class="text-muted"><?php echo date('d M Y h:i A', strtotime($note['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                    <?php if ($note['user_name']): ?>
                                        <small class="text-muted mt-2 d-block">
                                            <i class="fas fa-user me-1"></i><?php echo $note['user_name']; ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right Column - Order Info -->
            <div class="col-lg-4">
                <!-- Order Status Timeline -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Order Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php 
                            $status_timeline = [
                                'pending' => ['icon' => 'clock', 'color' => 'warning', 'text' => 'Order Placed'],
                                'processing' => ['icon' => 'cog', 'color' => 'info', 'text' => 'Processing'],
                                'shipped' => ['icon' => 'shipping-fast', 'color' => 'primary', 'text' => 'Shipped'],
                                'delivered' => ['icon' => 'check-circle', 'color' => 'success', 'text' => 'Delivered'],
                                'cancelled' => ['icon' => 'times-circle', 'color' => 'danger', 'text' => 'Cancelled']
                            ];
                            
                            $current_status = $order['status'];
                            
                            foreach ($status_timeline as $status_key => $status_info):
                                $is_completed = array_search($status_key, array_keys($status_timeline)) <= array_search($current_status, array_keys($status_timeline));
                                $is_current = $status_key === $current_status;
                            ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker bg-<?php echo $is_completed ? $status_info['color'] : 'secondary'; ?>">
                                        <i class="fas fa-<?php echo $status_info['icon']; ?> text-white"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="<?php echo $is_completed ? 'text-dark' : 'text-muted'; ?>">
                                            <?php echo $status_info['text']; ?>
                                            <?php if ($is_current): ?>
                                                <span class="badge bg-<?php echo $status_info['color']; ?> ms-2">Current</span>
                                            <?php endif; ?>
                                        </h6>
                                        <?php 
                                        // Show date if status was reached
                                        foreach ($status_history as $history) {
                                            if ($history['status'] === $status_key) {
                                                echo '<small class="text-muted">' . date('d M Y, h:i A', strtotime($history['created_at'])) . '</small>';
                                                break;
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Order Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>Order Date:</th>
                                <td><?php echo date('d M Y h:i A', strtotime($order['order_date'])); ?></td>
                            </tr>
                            <tr>
                                <th>Payment Method:</th>
                                <td><?php echo strtoupper($order['payment_method']); ?></td>
                            </tr>
                            <tr>
                                <th>Payment Status:</th>
                                <td>
                                    <span class="badge bg-<?php echo $order['payment_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if ($order['estimated_delivery']): ?>
                                <tr>
                                    <th>Est. Delivery:</th>
                                    <td><?php echo date('d M Y', strtotime($order['estimated_delivery'])); ?></td>
                                </tr>
                            <?php endif; ?>
                            <?php if ($order['tracking_number']): ?>
                                <tr>
                                    <th>Tracking #:</th>
                                    <td>
                                        <?php echo $order['tracking_number']; ?>
                                        <?php if ($order['shipping_carrier_id']): 
                                            // You can fetch carrier tracking URL here if needed
                                        ?>
                                            <a href="#" class="ms-2 small">Track</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Shipping Address</h5>
                    </div>
                    <div class="card-body">
                        <address class="mb-0">
                            <?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'Not provided')); ?>
                        </address>
                        
                        <?php if ($order['customer_notes']): ?>
                            <hr class="my-3">
                            <h6>Customer Notes:</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Cancel Order Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel order #<?php echo $order['order_number']; ?>?</p>
                <div class="mb-3">
                    <label for="cancel_reason" class="form-label">Reason for cancellation</label>
                    <textarea class="form-control" id="cancel_reason" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Confirm Cancellation</button>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 3px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.timeline-content {
    padding-bottom: 10px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const cancelBtn = document.getElementById('cancelOrderBtn');
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            cancelModal.show();
        });
    }
    
    confirmCancelBtn.addEventListener('click', function() {
        const reason = document.getElementById('cancel_reason').value;
        
        if (!reason.trim()) {
            alert('Please provide a reason for cancellation');
            return;
        }
        
        // Send AJAX request
        fetch('cancel-order-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: <?php echo $order_id; ?>,
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cancelModal.hide();
                showToast('Order cancelled successfully!', 'success');
                // Reload page after 2 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                showToast(data.message || 'Error cancelling order', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error', 'error');
        });
    });
});

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

<?php require_once '../../includes/footer.php'; ?>