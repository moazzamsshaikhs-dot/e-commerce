<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
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
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid order ID.';
    header('Location: ' . SITE_URL . 'admin/vendors/orders/orders.php');
    exit();
}

$order_id = (int)$_GET['id'];

// Fetch order details - check if vendor has products in this order
try {
    $db = getDB();
    
    $query = "SELECT 
                o.*,
                u.full_name as customer_name,
                u.email as customer_email,
                u.phone as customer_phone,
                u.address as customer_address,
                (SELECT COUNT(*) FROM order_items oi 
                 JOIN products p ON oi.product_id = p.id 
                 WHERE oi.order_id = o.id AND p.vendor_id = ?) as vendor_product_count
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.id 
              WHERE o.id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$vendor_id, $order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        header('Location: ' . SITE_URL . 'admin/vendors/orders/orders.php');
        exit();
    }
    
    // Check if this vendor has products in this order
    if ($order['vendor_product_count'] == 0) {
        $_SESSION['error'] = 'Access denied. This order does not contain your products.';
        header('Location: ' . SITE_URL . 'admin/vendors/orders/orders.php');
        exit();
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading order: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'admin/vendors/orders/orders.php');
    exit();
}

// Get order items for this vendor
try {
    $db = getDB();
    
    $items_query = "SELECT 
                      oi.*,
                      p.name as product_name,
                      p.image as product_image,
                      p.category as product_category,
                      p.vendor_id,
                      u.username as vendor_username
                    FROM order_items oi 
                    JOIN products p ON oi.product_id = p.id 
                    LEFT JOIN users u ON p.vendor_id = u.id 
                    WHERE oi.order_id = ? AND p.vendor_id = ?";
    
    $stmt = $db->prepare($items_query);
    $stmt->execute([$order_id, $vendor_id]);
    $order_items = $stmt->fetchAll();
    
    // Calculate vendor's subtotal
    $vendor_subtotal = 0;
    foreach($order_items as $item) {
        $vendor_subtotal += $item['subtotal'];
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading order items: ' . $e->getMessage();
    $order_items = [];
    $vendor_subtotal = 0;
}

// Get order status history
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT 
                            osh.*,
                            u.username as changed_by_name,
                            DATE_FORMAT(osh.created_at, '%b %d, %Y %h:%i %p') as formatted_date
                          FROM order_status_history osh 
                          LEFT JOIN users u ON osh.changed_by = u.id 
                          WHERE osh.order_id = ? 
                          ORDER BY osh.created_at DESC");
    $stmt->execute([$order_id]);
    $status_history = $stmt->fetchAll();
} catch(PDOException $e) {
    $status_history = [];
}

// Get order notes (only vendor's notes)
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT 
                            onotes.*,
                            u.username as user_name,
                            u.profile_pic,
                            DATE_FORMAT(onotes.created_at, '%b %d, %Y %h:%i %p') as formatted_date
                          FROM order_notes onotes 
                          LEFT JOIN users u ON onotes.user_id = u.id 
                          WHERE onotes.order_id = ? 
                          AND (onotes.note_type = 'internal' OR u.id = ?)
                          ORDER BY onotes.created_at DESC");
    $stmt->execute([$order_id, $vendor_id]);
    $order_notes = $stmt->fetchAll();
} catch(PDOException $e) {
    $order_notes = [];
}

// Get vendor earnings for this order
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT 
                            ve.*,
                            p.name as product_name,
                            DATE_FORMAT(ve.created_at, '%b %d, %Y') as earning_date
                          FROM vendor_earnings ve 
                          JOIN products p ON ve.product_id = p.id 
                          WHERE ve.order_id = ? AND ve.vendor_id = ?");
    $stmt->execute([$order_id, $vendor_id]);
    $vendor_earnings = $stmt->fetchAll();
    
    // Calculate total earnings for this order
    $total_earnings = 0;
    $paid_earnings = 0;
    foreach($vendor_earnings as $earning) {
        $total_earnings += $earning['vendor_amount'];
        if ($earning['status'] == 'paid') {
            $paid_earnings += $earning['vendor_amount'];
        }
    }
    
} catch(PDOException $e) {
    $vendor_earnings = [];
    $total_earnings = 0;
    $paid_earnings = 0;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = trim($_POST['status'] ?? '');
    $status_notes = trim($_POST['status_notes'] ?? '');
    
    // Allowed status transitions for vendor
    $allowed_statuses = ['pending', 'processing', 'shipped'];
    $current_status = $order['status'];
    
    // Validate status
    if (!in_array($new_status, $allowed_statuses)) {
        $_SESSION['error'] = 'Invalid status selected.';
    } elseif ($new_status == $current_status) {
        $_SESSION['error'] = 'Status is already set to ' . $new_status . '.';
    } else {
        try {
            $db = getDB();
            
            // Start transaction
            $db->beginTransaction();
            
            // Update order status
            $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            // Add to status history
            $notes = 'Vendor updated status from ' . $current_status . ' to ' . $new_status;
            if (!empty($status_notes)) {
                $notes .= ' - ' . $status_notes;
            }
            
            $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, changed_by, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $new_status, $vendor_id, $notes]);
            
            // Update vendor earnings status if shipped
            if ($new_status == 'shipped') {
                $stmt = $db->prepare("UPDATE vendor_earnings SET status = 'processing' WHERE order_id = ? AND vendor_id = ? AND status = 'pending'");
                $stmt->execute([$order_id, $vendor_id]);
            }
            
            // Add internal note
            $note_text = "Order status changed to " . ucfirst($new_status);
            if (!empty($status_notes)) {
                $note_text .= ": " . $status_notes;
            }
            
            $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) VALUES (?, ?, 'internal', ?)");
            $stmt->execute([$order_id, $vendor_id, $note_text]);
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['success'] = 'Order status updated successfully!';
            
            // Log activity
            if (function_exists('logUserActivity')) {
                logUserActivity($vendor_id, 'order_status_update', "Updated order #$order_id status to $new_status");
            }
            
            // Refresh page
            header('Location: view.php?id=' . $order_id);
            exit();
            
        } catch(PDOException $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error'] = 'Error updating status: ' . $e->getMessage();
        }
    }
}

// Handle add note
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_text = trim($_POST['note_text'] ?? '');
    $note_type = trim($_POST['note_type'] ?? 'internal');
    
    if (empty($note_text)) {
        $_SESSION['error'] = 'Note cannot be empty.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $vendor_id, $note_type, $note_text]);
            
            $_SESSION['success'] = 'Note added successfully!';
            
            // Refresh page
            header('Location: view.php?id=' . $order_id);
            exit();
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Error adding note: ' . $e->getMessage();
        }
    }
}

$page_title = 'Order #' . $order['order_number'];
require_once '../../includes/header.php';
?>

</head>
<body>
<div class="dashboard-container">
    <?php 
    // Check if vendor sidebar exists
    require_once '../../includes/vendor-sidebar.php';
    
    ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">
                        Order #<?php echo htmlspecialchars($order['order_number']); ?>
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>admin/vendor/dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="orders.php">Orders</a></li>
                            <li class="breadcrumb-item active" aria-current="page">View</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Orders
                    </a>
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i> Print
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- Left Column - Order Details -->
            <div class="col-lg-8">
                <!-- Order Summary Card -->
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Order Information</h5>
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <th width="40%">Order Number:</th>
                                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Order Date:</th>
                                            <td><?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Order Status:</th>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['status'] == 'delivered' ? 'success' : 
                                                         ($order['status'] == 'shipped' ? 'info' : 
                                                         ($order['status'] == 'processing' ? 'primary' : 
                                                         ($order['status'] == 'cancelled' ? 'danger' : 'warning'))); 
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Payment Status:</th>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $order['payment_status'] == 'completed' ? 'success' : 
                                                         ($order['payment_status'] == 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Payment Method:</th>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Shipping Method:</th>
                                            <td><?php echo htmlspecialchars($order['shipping_method'] ?: 'Standard Shipping'); ?></td>
                                        </tr>
                                        <?php if ($order['tracking_number']): ?>
                                        <tr>
                                            <th>Tracking Number:</th>
                                            <td>
                                                <code><?php echo htmlspecialchars($order['tracking_number']); ?></code>
                                                <?php if ($order['shipping_carrier_id']): ?>
                                                <?php 
                                                try {
                                                    $db = getDB();
                                                    $stmt = $db->prepare("SELECT name, tracking_url FROM shipping_carriers WHERE id = ?");
                                                    $stmt->execute([$order['shipping_carrier_id']]);
                                                    $carrier = $stmt->fetch();
                                                    if ($carrier && $carrier['tracking_url']):
                                                ?>
                                                <a href="<?php echo str_replace('{{tracking}}', $order['tracking_number'], $carrier['tracking_url']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary ms-2">
                                                    <i class="fas fa-external-link-alt"></i> Track
                                                </a>
                                                <?php endif; } catch(PDOException $e) { } ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($order['estimated_delivery']): ?>
                                        <tr>
                                            <th>Est. Delivery:</th>
                                            <td><?php echo date('F d, Y', strtotime($order['estimated_delivery'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-3">Customer Information</h5>
                                <table class="table table-sm">
                                    <tbody>
                                        <tr>
                                            <th width="40%">Customer:</th>
                                            <td><?php echo htmlspecialchars($order['customer_name'] ?: 'Guest'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td>
                                                <?php if ($order['customer_email']): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>">
                                                    <?php echo htmlspecialchars($order['customer_email']); ?>
                                                </a>
                                                <?php else: ?>
                                                Not provided
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Phone:</th>
                                            <td><?php echo htmlspecialchars($order['customer_phone'] ?: 'Not provided'); ?></td>
                                        </tr>
                                        <?php if ($order['customer_address']): ?>
                                        <tr>
                                            <th>Address:</th>
                                            <td><?php echo nl2br(htmlspecialchars($order['customer_address'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Customer Actions -->
                                <div class="mt-3">
                                    <?php if ($order['customer_email']): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>?subject=Regarding your order <?php echo htmlspecialchars($order['order_number']); ?>" 
                                       class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-envelope me-1"></i> Email Customer
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items Card -->
                <div class="card">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">Order Items (Your Products)</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($order_items)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No items found for this vendor.</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($order_items as $item): 
                                        $product_image = SITE_URL . 'assets/images/products/' . ($item['product_image'] ?: 'default.png');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="product-image me-3">
                                                    <img src="<?php echo $product_image; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                         class="rounded"
                                                         onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'">
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                    <small class="text-muted">
                                                        SKU: <?php echo $item['product_id']; ?>
                                                        <?php if ($item['product_category']): ?>
                                                        • Category: <?php echo htmlspecialchars($item['product_category']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $order['status'] == 'delivered' ? 'success' : 
                                                     ($order['status'] == 'shipped' ? 'info' : 
                                                     ($order['status'] == 'processing' ? 'primary' : 
                                                     ($order['status'] == 'cancelled' ? 'danger' : 'warning'))); 
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Vendor Subtotal:</td>
                                        <td colspan="2" class="fw-bold">$<?php echo number_format($vendor_subtotal, 2); ?></td>
                                    </tr>
                                    <?php 
                                    // Calculate commission - FIXED SECTION
                                    $commission_rate = 12; // Default commission rate
                                    $commission_amount = $vendor_subtotal * ($commission_rate / 100);
                                    $vendor_earnings_amount = $vendor_subtotal - $commission_amount;
                                    ?>
                                    <tr>
                                        <td colspan="3" class="text-end">Commission (<?php echo $commission_rate; ?>%):</td>
                                        <td colspan="2" class="text-danger">-$<?php echo number_format($commission_amount, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Your Earnings:</td>
                                        <td colspan="2" class="fw-bold text-success">$<?php echo number_format($vendor_earnings_amount, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Order Notes -->
                <div class="card">
                    <div class="card-header bg-white border-bottom-0 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Order Notes</h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <i class="fas fa-plus me-1"></i> Add Note
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($order_notes)): ?>
                        <p class="text-muted text-center py-3">No notes yet.</p>
                        <?php else: ?>
                        <div class="notes-list">
                            <?php foreach($order_notes as $note): ?>
                            <div class="note-item">
                                <div class="d-flex justify-content-between mb-2">
                                    <div class="d-flex align-items-center">
                                        <?php if ($note['user_id'] == $vendor_id): ?>
                                        <span class="badge bg-info me-2">You</span>
                                        <?php endif; ?>
                                        <strong><?php echo htmlspecialchars($note['user_name'] ?: 'System'); ?></strong>
                                        <small class="text-muted ms-2"><?php echo $note['formatted_date']; ?></small>
                                    </div>
                                    <span class="badge bg-<?php echo $note['note_type'] == 'internal' ? 'secondary' : 'primary'; ?>">
                                        <?php echo ucfirst($note['note_type']); ?>
                                    </span>
                                </div>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Status History -->
                <div class="card">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">Status History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($status_history)): ?>
                        <p class="text-muted text-center py-3">No status history available.</p>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach($status_history as $history): ?>
                            <div class="timeline-item mb-4">
                                <div class="timeline-marker bg-<?php 
                                    echo $history['status'] == 'delivered' ? 'success' : 
                                         ($history['status'] == 'shipped' ? 'info' : 
                                         ($history['status'] == 'processing' ? 'primary' : 
                                         ($history['status'] == 'cancelled' ? 'danger' : 'warning'))); 
                                ?>"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between mb-1">
                                        <h6 class="mb-0">Status changed to <strong><?php echo ucfirst($history['status']); ?></strong></h6>
                                        <small class="text-muted"><?php echo $history['formatted_date']; ?></small>
                                    </div>
                                    <p class="text-muted mb-0">
                                        By: <?php echo htmlspecialchars($history['changed_by_name'] ?: 'System'); ?>
                                        <?php if (!empty($history['notes'])): ?>
                                        <br>Note: <?php echo htmlspecialchars($history['notes']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Actions & Stats -->
            <div class="col-lg-4">
                <!-- Order Actions -->
                <div class="card mb-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">Order Actions</h5>
                    </div>
                    <div class="card-body">
                        <!-- Status Update Form -->
                        <form method="POST" action="" class="mb-4">
                            <div class="mb-3">
                                <label for="status" class="form-label fw-bold">Update Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                </select>
                                <small class="text-muted">Current: <?php echo ucfirst($order['status']); ?></small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status_notes" class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" id="status_notes" name="status_notes" rows="2" 
                                          placeholder="Add notes about this status change..."></textarea>
                            </div>
                            
                            <button type="submit" name="update_status" class="btn btn-primary w-100">
                                <i class="fas fa-sync me-2"></i> Update Status
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <!-- Quick Actions -->
                        <div class="quick-actions">
                            <h6 class="mb-3">Quick Actions</h6>
                            <div class="d-grid gap-2">
                                <?php if ($order['status'] == 'shipped' && empty($order['tracking_number'])): ?>
                                <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#trackingModal">
                                    <i class="fas fa-truck me-2"></i> Add Tracking
                                </button>
                                <?php endif; ?>
                                
                                <?php if (file_exists('invoice.php')): ?>
                                <a href="invoice.php?order_id=<?php echo $order_id; ?>" 
                                   class="btn btn-outline-success" target="_blank">
                                    <i class="fas fa-file-invoice me-2"></i> Generate Invoice
                                </a>
                                <?php endif; ?>
                                
                                <?php if (file_exists('packing-slip.php')): ?>
                                <a href="packing-slip.php?order_id=<?php echo $order_id; ?>" 
                                   class="btn btn-outline-warning" target="_blank">
                                    <i class="fas fa-box me-2"></i> Packing Slip
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Earnings Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">Earnings Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="earnings-stats">
                            <div class="stat-item text-center p-3 border rounded mb-3">
                                <h3 class="text-primary mb-1">$<?php echo number_format($vendor_earnings_amount, 2); ?></h3>
                                <p class="text-muted mb-0">Your Earnings</p>
                            </div>
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="stat-item text-center p-2 border rounded">
                                        <h5 class="text-success mb-1">$<?php echo number_format($paid_earnings, 2); ?></h5>
                                        <small class="text-muted">Paid</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item text-center p-2 border rounded">
                                        <h5 class="text-warning mb-1">$<?php echo number_format($total_earnings - $paid_earnings, 2); ?></h5>
                                        <small class="text-muted">Pending</small>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($vendor_earnings)): ?>
                            <div class="mt-3">
                                <h6 class="mb-2">Earnings Breakdown:</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Status</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($vendor_earnings as $earning): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(substr($earning['product_name'], 0, 15)); ?>...</td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $earning['status'] == 'paid' ? 'success' : 
                                                             ($earning['status'] == 'processing' ? 'warning' : 'secondary');
                                                    ?>">
                                                        <?php echo ucfirst($earning['status']); ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($earning['vendor_amount'], 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Order Statistics -->
                <div class="card">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0">Order Statistics</h5>
                    </div>
                    <div class="card-body">
                        <div class="order-stats">
                            <div class="stat-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Items in Order:</span>
                                    <strong><?php echo count($order_items); ?></strong>
                                </div>
                            </div>
                            
                            <div class="stat-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Total Quantity:</span>
                                    <strong>
                                        <?php 
                                        $total_qty = 0;
                                        foreach($order_items as $item) {
                                            $total_qty += $item['quantity'];
                                        }
                                        echo $total_qty;
                                        ?>
                                    </strong>
                                </div>
                            </div>
                            
                            <div class="stat-item mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Commission Rate:</span>
                                    <strong><?php echo $commission_rate; ?>%</strong>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Days Since Order:</span>
                                    <strong>
                                        <?php 
                                        $order_date = new DateTime($order['order_date']);
                                        $today = new DateTime();
                                        $interval = $today->diff($order_date);
                                        echo $interval->days;
                                        ?> days
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Order Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="note_type" class="form-label">Note Type</label>
                        <select class="form-select" id="note_type" name="note_type">
                            <option value="internal">Internal Note</option>
                            <option value="customer">Customer Note</option>
                        </select>
                        <small class="text-muted">Internal notes are only visible to vendors and admins.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="note_text" class="form-label">Note</label>
                        <textarea class="form-control" id="note_text" name="note_text" rows="4" 
                                  placeholder="Enter your note here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_note" class="btn btn-primary">Add Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tracking Modal -->
<div class="modal fade" id="trackingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Tracking Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="update_tracking.php">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tracking_number" class="form-label">Tracking Number</label>
                        <input type="text" class="form-control" id="tracking_number" name="tracking_number" 
                               placeholder="Enter tracking number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="shipping_carrier" class="form-label">Shipping Carrier</label>
                        <select class="form-select" id="shipping_carrier" name="shipping_carrier">
                            <option value="">Select Carrier</option>
                            <?php
                            try {
                                $db = getDB();
                                $stmt = $db->prepare("SELECT * FROM shipping_carriers WHERE is_active = 1 ORDER BY name");
                                $stmt->execute();
                                $carriers = $stmt->fetchAll();
                                
                                foreach($carriers as $carrier):
                            ?>
                            <option value="<?php echo $carrier['id']; ?>"><?php echo htmlspecialchars($carrier['name']); ?></option>
                            <?php endforeach; } catch(PDOException $e) { } ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tracking_notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="tracking_notes" name="tracking_notes" rows="2" 
                                  placeholder="Add any notes about shipping..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Tracking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status update form validation
    const statusForm = document.querySelector('form[action=""]');
    const statusSelect = document.getElementById('status');
    
    if (statusForm && statusSelect) {
        statusForm.addEventListener('submit', function(e) {
            if (!statusSelect.value) {
                e.preventDefault();
                alert('Please select a status.');
                statusSelect.focus();
                return false;
            }
            
            // Show loading
            const submitBtn = this.querySelector('button[name="update_status"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
                submitBtn.disabled = true;
            }
        });
    }
    
    // Add note form validation
    const noteForm = document.querySelector('form[action=""][name="add_note"]');
    if (noteForm) {
        noteForm.addEventListener('submit', function(e) {
            const noteText = document.getElementById('note_text');
            if (!noteText.value.trim()) {
                e.preventDefault();
                alert('Please enter a note.');
                noteText.focus();
                return false;
            }
        });
    }
    
    // Tracking modal validation
    const trackingForm = document.querySelector('form[action="update_tracking.php"]');
    if (trackingForm) {
        trackingForm.addEventListener('submit', function(e) {
            const trackingNumber = document.getElementById('tracking_number');
            if (!trackingNumber.value.trim()) {
                e.preventDefault();
                alert('Please enter a tracking number.');
                trackingNumber.focus();
                return false;
            }
        });
    }
    
    // Auto-close alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
<style>
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background: #f8f9fa;
    }
    
    .main-content {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
    }
    
    .dashboard-header {
        background: white;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .table th {
        font-weight: 600;
        background: #f8f9fa;
        border-top: none;
    }
    
    .product-image {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
    }
    
    .product-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
    }
    
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 11px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }
    
    .timeline-item {
        position: relative;
    }
    
    .timeline-marker {
        position: absolute;
        left: -30px;
        top: 5px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 0 0 3px #dee2e6;
    }
    
    .timeline-content {
        background: white;
        padding-left: 15px;
    }
    
    .note-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
    }
    
    .badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: 500;
    }
    
    .earnings-stats .stat-item {
        transition: transform 0.2s ease;
    }
    
    .earnings-stats .stat-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .order-stats .stat-item {
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    
    .order-stats .stat-item:last-child {
        border-bottom: none;
    }
    
    .quick-actions .btn {
        border-radius: 8px;
        padding: 10px;
    }
    
    .modal-content {
        border-radius: 12px;
    }
    
    .form-select:focus, .form-control:focus {
        border-color: #4361ee;
        box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
    }
    
    @media print {
        .dashboard-header, .card-header, .btn, .modal, .timeline::before {
            display: none !important;
        }
        
        .card {
            border: 1px solid #000 !important;
            box-shadow: none !important;
        }
        
        .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-container {
            flex-direction: column;
        }
        
        .main-content {
            padding: 15px;
        }
        
        .table-responsive {
            font-size: 14px;
        }
    }
</style>
<?php 
// Check if footer exists
include_once '../../includes/footer.php';

?>