<?php
// update_status.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
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
        $_SESSION['error'] = 'Your vendor account is not approved.';
        header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'admin/vendor/dashboard.php');
    exit();
}

// Get order ID
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    $_SESSION['error'] = 'Invalid order ID.';
    header('Location: orders.php');
    exit();
}

$order_id = (int)$_GET['order_id'];

// Fetch order details
try {
    $db = getDB();
    
    $query = "SELECT o.*, u.full_name as customer_name
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              WHERE o.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        header('Location: orders.php');
        exit();
    }
    
    // Check if vendor has products in this order
    $stmt = $db->prepare("SELECT COUNT(*) FROM order_items oi 
                         JOIN products p ON oi.product_id = p.id 
                         WHERE oi.order_id = ? AND p.vendor_id = ?");
    $stmt->execute([$order_id, $vendor_id]);
    $vendor_product_count = $stmt->fetchColumn();
    
    if ($vendor_product_count == 0) {
        $_SESSION['error'] = 'Access denied. This order does not contain your products.';
        header('Location: orders.php');
        exit();
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading order: ' . $e->getMessage();
    header('Location: orders.php');
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = trim($_POST['status'] ?? '');
    $status_notes = trim($_POST['status_notes'] ?? '');
    
    // Allowed status transitions for vendor
    $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered'];
    $current_status = $order['status'];
    
    // Validate status
    if (empty($new_status)) {
        $_SESSION['error'] = 'Please select a status.';
    } elseif (!in_array($new_status, $allowed_statuses)) {
        $_SESSION['error'] = 'Invalid status selected.';
    } elseif ($new_status == $current_status) {
        $_SESSION['error'] = 'Status is already set to ' . $new_status . '.';
    } else {
        try {
            $db = getDB();
            
            // Start transaction
            $db->beginTransaction();
            
            // Debug: Log the update
            error_log("Updating order #$order_id from $current_status to $new_status");
            
            // Update order status
            $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("No rows affected - order may not exist");
            }
            
            // Add to status history
            $notes = 'Vendor updated status from ' . $current_status . ' to ' . $new_status;
            if (!empty($status_notes)) {
                $notes .= ' - ' . $status_notes;
            }
            
            $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $new_status, $vendor_id, $notes]);
            
            // Add internal note
            $note_text = "Order status changed to " . ucfirst($new_status);
            if (!empty($status_notes)) {
                $note_text .= ": " . $status_notes;
            }
            
            $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) VALUES (?, ?, 'internal', ?)");
            $stmt->execute([$order_id, $vendor_id, $note_text]);
            
            // Update vendor earnings status if shipped
            if ($new_status == 'shipped') {
                $stmt = $db->prepare("UPDATE vendor_earnings SET status = 'processing' WHERE order_id = ? AND vendor_id = ? AND status = 'pending'");
                $stmt->execute([$order_id, $vendor_id]);
            }
            
            // Commit transaction
            $db->commit();
            
            // Set success message
            $_SESSION['success'] = "Order #{$order['order_number']} status updated from " . ucfirst($current_status) . " to " . ucfirst($new_status) . " successfully!";
            
            // Debug: Log success
            error_log("Status update successful for order #$order_id");
            
            // Redirect back to orders with success message
            header('Location: orders.php?success=1');
            exit();
            
        } catch(PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
            error_log("Database error in update_status.php: " . $e->getMessage());
        } catch(Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
            error_log("General error in update_status.php: " . $e->getMessage());
        }
    }
    
    // If we get here, there was an error - stay on the page to show error
}

$page_title = 'Update Order Status - #' . $order['order_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .btn-loading {
            position: relative;
            color: transparent !important;
        }
        .btn-loading:after {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<?php 
// Include header
$header_path = '../../includes/header.php';
if (file_exists($header_path)) {
    include_once $header_path;
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-edit me-2"></i> Update Order Status</h4>
                </div>
                <div class="card-body">
                    <!-- Display Messages -->
                    <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php 
                        echo htmlspecialchars($_SESSION['success']); 
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Order Info -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading fw-bold">Order Information</h6>
                        <hr>
                        <div class="row">
                            <div class="col-6">
                                <strong>Order #:</strong><br>
                                <code><?php echo htmlspecialchars($order['order_number']); ?></code>
                            </div>
                            <div class="col-6">
                                <strong>Customer:</strong><br>
                                <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?>
                            </div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-12">
                                <strong>Current Status:</strong><br>
                                <span class="status-badge badge bg-<?php 
                                    echo $order['status'] == 'delivered' ? 'success' : 
                                         ($order['status'] == 'shipped' ? 'info' : 
                                         ($order['status'] == 'processing' ? 'primary' : 
                                         ($order['status'] == 'cancelled' ? 'danger' : 'warning'))); 
                                ?>">
                                    <i class="fas fa-<?php 
                                        echo $order['status'] == 'pending' ? 'clock' : 
                                             ($order['status'] == 'processing' ? 'cog' : 
                                             ($order['status'] == 'shipped' ? 'truck' : 
                                             ($order['status'] == 'delivered' ? 'check' : 'times'))); 
                                    ?> me-1"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Update Form -->
                    <form method="POST" action="" id="statusForm">
                        <div class="mb-3">
                            <label for="status" class="form-label fw-bold">New Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Select New Status</option>
                                <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected disabled' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected disabled' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected disabled' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected disabled' : ''; ?>>Delivered</option>
                            </select>
                            <small class="text-muted">Select the new status for this order</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status_notes" class="form-label">Notes (Optional)</label>
                            <textarea class="form-control" id="status_notes" name="status_notes" rows="3" 
                                      placeholder="Add notes about this status change... (e.g., Tracking number, delays, etc.)"><?php echo isset($_POST['status_notes']) ? htmlspecialchars($_POST['status_notes']) : ''; ?></textarea>
                            <small class="text-muted">These notes will be added to the order history</small>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="orders.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Back to Orders
                            </a>
                            <button type="submit" name="update_status" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save me-2"></i> Update Status
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-muted">
                    <small><i class="fas fa-info-circle me-1"></i> Only orders containing your products can be updated</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('statusForm');
    const submitBtn = document.getElementById('submitBtn');
    const statusSelect = document.getElementById('status');
    
    if (form && submitBtn && statusSelect) {
        // Initialize form with current status selected
        const currentStatus = '<?php echo $order["status"]; ?>';
        if (currentStatus) {
            const options = statusSelect.querySelectorAll('option');
            options.forEach(option => {
                if (option.value === currentStatus) {
                    option.selected = true;
                    option.disabled = true;
                }
            });
        }
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form
            if (!statusSelect.value) {
                alert('Please select a new status.');
                statusSelect.focus();
                return false;
            }
            
            if (statusSelect.value === currentStatus) {
                alert('Please select a different status from the current one.');
                statusSelect.focus();
                return false;
            }
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
            submitBtn.disabled = true;
            submitBtn.classList.add('btn-loading');
            
            // Add a small delay to show loading state
            setTimeout(() => {
                // Submit the form
                this.submit();
            }, 300);
        });
    }
    
    // Auto-close alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(function() {
            alerts.forEach(function(alert) {
                try {
                    if (alert && bootstrap && bootstrap.Alert) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                } catch (e) {
                    console.log('Could not close alert:', e);
                }
            });
        }, 5000);
    }
    
    // Check if there's a success parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        // You could show a success message here if needed
    }
});
</script>

<?php 
// Include footer
$footer_path = '../../includes/footer.php';
if (file_exists($footer_path)) {
    include_once $footer_path;
}
?>
</body>
</html>