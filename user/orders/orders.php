<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'My Orders';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$query = "SELECT * FROM orders WHERE user_id = :user_id";
$params = [':user_id' => $user_id];

if (!empty($status) && $status !== 'all') {
    $query .= " AND status = :status";
    $params[':status'] = $status;
}

if (!empty($search)) {
    $query .= " AND (order_number LIKE :search OR shipping_address LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($date_from)) {
    $query .= " AND DATE(order_date) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(order_date) <= :date_to";
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY order_date DESC";

// Get orders
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$orders = $stmt->fetchAll();

// Get status counts for stats
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count 
    FROM orders 
    WHERE user_id = ? 
    GROUP BY status
");
$stmt->execute([$user_id]);
$status_counts = $stmt->fetchAll();

// Log activity
logUserActivity($user_id, 'orders_view', 'Viewed orders list');
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">My Orders</h1>
                    <p class="text-muted mb-0">Manage and track your orders</p>
                </div>
                <div>
                    <a href="<?php echo SITE_URL; ?>user/orders/shop.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i> Shop Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <?php 
            $status_data = [
                'pending' => ['icon' => 'clock', 'color' => 'warning', 'text' => 'Pending'],
                'processing' => ['icon' => 'cog', 'color' => 'info', 'text' => 'Processing'],
                'shipped' => ['icon' => 'shipping-fast', 'color' => 'primary', 'text' => 'Shipped'],
                'delivered' => ['icon' => 'check-circle', 'color' => 'success', 'text' => 'Delivered'],
                'cancelled' => ['icon' => 'times-circle', 'color' => 'danger', 'text' => 'Cancelled']
            ];
            
            foreach ($status_data as $status_key => $status_info):
                $count = 0;
                foreach ($status_counts as $stat) {
                    if ($stat['status'] == $status_key) {
                        $count = $stat['count'];
                        break;
                    }
                }
            ?>
                <div class="col-6 col-md-4 col-lg">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-3">
                            <div class="avatar-sm bg-<?php echo $status_info['color']; ?> bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                                <i class="fas fa-<?php echo $status_info['icon']; ?> text-<?php echo $status_info['color']; ?>"></i>
                            </div>
                            <h4 class="mb-1"><?php echo $count; ?></h4>
                            <p class="text-muted small mb-0"><?php echo $status_info['text']; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <?php foreach ($status_data as $status_key => $status_info): ?>
                                <option value="<?php echo $status_key; ?>" <?php echo ($status === $status_key) ? 'selected' : ''; ?>>
                                    <?php echo $status_info['text']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Order # or address" value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                        <h4>No orders found</h4>
                        <p class="text-muted">You haven't placed any orders yet.</p>
                        <a href="<?php echo SITE_URL; ?>user/orders/shop.php" class="btn btn-primary">
                            <i class="fas fa-shopping-bag me-2"></i> Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $order['order_number']; ?></strong>
                                            <?php if ($order['is_gift']): ?>
                                                <span class="badge bg-info ms-2"><i class="fas fa-gift"></i> Gift</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_data[$order['status']]['color'] ?? 'secondary'; ?>">
                                                <i class="fas fa-<?php echo $status_data[$order['status']]['icon'] ?? 'question'; ?> me-1"></i>
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $order['payment_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <button class="btn btn-outline-danger cancel-order-btn" 
                                                            data-order-id="<?php echo $order['id']; ?>"
                                                            data-order-number="<?php echo $order['order_number']; ?>"
                                                            title="Cancel Order">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
                <p id="cancel-message"></p>
                <form id="cancelForm">
                    <input type="hidden" name="order_id" id="cancel_order_id">
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Reason for cancellation</label>
                        <textarea class="form-control" id="cancel_reason" name="reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Confirm Cancellation</button>
            </div>
        </div>
    </div>
</div>

<script>
// Cancel Order Functionality
document.addEventListener('DOMContentLoaded', function() {
    const cancelBtns = document.querySelectorAll('.cancel-order-btn');
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    const cancelMessage = document.getElementById('cancel-message');
    const cancelOrderId = document.getElementById('cancel_order_id');
    const confirmCancelBtn = document.getElementById('confirmCancelBtn');
    
    cancelBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const orderNumber = this.getAttribute('data-order-number');
            
            cancelMessage.textContent = `Are you sure you want to cancel order #${orderNumber}?`;
            cancelOrderId.value = orderId;
            
            cancelModal.show();
        });
    });
    
    confirmCancelBtn.addEventListener('click', function() {
        const orderId = cancelOrderId.value;
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
                order_id: orderId,
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