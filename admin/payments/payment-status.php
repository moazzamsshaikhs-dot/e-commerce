<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

$page_title = 'Payment Status Management';
require_once '../includes/header.php';

// Get payment ID from URL
$payment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $db = getDB();
    
    if ($payment_id > 0) {
        // Get specific payment details
        $stmt = $db->prepare("SELECT p.*, 
                                     u.full_name as customer_name,
                                     u.email as customer_email,
                                     o.order_number,
                                     o.status as order_status
                              FROM payments p
                              LEFT JOIN users u ON p.user_id = u.id
                              LEFT JOIN orders o ON p.order_id = o.id
                              WHERE p.id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            $_SESSION['error'] = 'Payment not found.';
            redirect('index.php');
        }
    }
    
    // Get all payment statuses
    $stmt = $db->query("SELECT DISTINCT status FROM payments WHERE status != '' ORDER BY status");
    $all_statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get status statistics
    $stats_sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    MIN(created_at) as first_payment,
                    MAX(created_at) as last_payment
                  FROM payments
                  GROUP BY status
                  ORDER BY FIELD(status, 'pending', 'processing', 'completed', 'failed', 'refunded')";
    
    $stmt = $db->query($stats_sql);
    $status_stats = $stmt->fetchAll();
    
    // Get recent status changes
    $recent_sql = "SELECT pa.*, 
                          u.full_name as changed_by_name,
                          p.transaction_id,
                          p.amount
                   FROM payment_audit pa
                   LEFT JOIN users u ON pa.performed_by = u.id
                   LEFT JOIN payments p ON pa.payment_id = p.id
                   WHERE pa.action = 'status_update'
                   ORDER BY pa.created_at DESC
                   LIMIT 10";
    
    $stmt = $db->query($recent_sql);
    $recent_changes = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading payment status data: ' . $e->getMessage();
    redirect('index.php');
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Payment Status Management</h1>
                <p class="text-muted mb-0">Update and monitor payment statuses</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Payments
                </a>
            </div>
        </div>
        
        <?php if ($payment_id > 0): ?>
        <!-- Update Specific Payment Status -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Update Payment Status</h5>
                    </div>
                    <div class="card-body">
                        <!-- Payment Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6>Payment Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td width="40%">Payment ID:</td>
                                        <td>#<?php echo $payment['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Transaction ID:</td>
                                        <td class="font-monospace"><?php echo $payment['transaction_id'] ?: 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <td>Customer:</td>
                                        <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Order:</td>
                                        <td>
                                            <?php if ($payment['order_number']): ?>
                                            <?php echo $payment['order_number']; ?>
                                            <?php else: ?>
                                            <span class="text-muted">Manual Payment</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Amount:</td>
                                        <td class="fw-bold">$<?php echo number_format($payment['amount'], 2); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Current Status</h6>
                                <div class="text-center py-4">
                                    <?php
                                    $status_color = 'secondary';
                                    $status_icon = 'question-circle';
                                    switch($payment['status']) {
                                        case 'pending':
                                            $status_color = 'warning';
                                            $status_icon = 'clock';
                                            break;
                                        case 'completed':
                                            $status_color = 'success';
                                            $status_icon = 'check-circle';
                                            break;
                                        case 'failed':
                                            $status_color = 'danger';
                                            $status_icon = 'times-circle';
                                            break;
                                        case 'refunded':
                                            $status_color = 'info';
                                            $status_icon = 'undo';
                                            break;
                                    }
                                    ?>
                                    <div class="mb-3">
                                        <span class="badge bg-<?php echo $status_color; ?> fs-5 p-3">
                                            <i class="fas fa-<?php echo $status_icon; ?> fa-2x me-2"></i>
                                            <?php echo ucfirst($payment['status']); ?>
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        Last updated: <?php echo date('d M Y, h:i A', strtotime($payment['updated_at'] ?? $payment['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Update Form -->
                        <form id="statusUpdateForm">
                            <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">New Status <span class="text-danger">*</span></label>
                                    <div class="row g-2">
                                        <?php foreach($all_statuses as $status): 
                                            if ($status == $payment['status']) continue;
                                            
                                            $status_config = [
                                                'pending' => ['color' => 'warning', 'icon' => 'clock', 'title' => 'Payment is awaiting processing'],
                                                'completed' => ['color' => 'success', 'icon' => 'check-circle', 'title' => 'Payment successfully received'],
                                                'failed' => ['color' => 'danger', 'icon' => 'times-circle', 'title' => 'Payment failed or declined'],
                                                'refunded' => ['color' => 'info', 'icon' => 'undo', 'title' => 'Payment has been refunded'],
                                            ];
                                            $config = $status_config[$status] ?? ['color' => 'secondary', 'icon' => 'question-circle', 'title' => ''];
                                        ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" 
                                                       type="radio" 
                                                       name="new_status" 
                                                       id="status_<?php echo $status; ?>" 
                                                       value="<?php echo $status; ?>"
                                                       required>
                                                <label class="form-check-label w-100" for="status_<?php echo $status; ?>">
                                                    <div class="card border">
                                                        <div class="card-body text-center py-3">
                                                            <i class="fas fa-<?php echo $config['icon']; ?> fa-2x text-<?php echo $config['color']; ?> mb-2"></i>
                                                            <h6 class="mb-1"><?php echo ucfirst($status); ?></h6>
                                                            <small class="text-muted"><?php echo $config['title']; ?></small>
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body">
                                            <h6>Quick Actions</h6>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-success" onclick="setStatus('completed')">
                                                    <i class="fas fa-check-circle me-2"></i> Mark as Completed
                                                </button>
                                                <button type="button" class="btn btn-danger" onclick="setStatus('failed')">
                                                    <i class="fas fa-times-circle me-2"></i> Mark as Failed
                                                </button>
                                                <button type="button" class="btn btn-info" onclick="setStatus('refunded')">
                                                    <i class="fas fa-undo me-2"></i> Mark as Refunded
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Update Reason/Notes</label>
                                    <textarea class="form-control" name="notes" rows="3" 
                                              placeholder="Enter reason for status change (optional)..."></textarea>
                                    <small class="text-muted">This note will be recorded in the audit trail.</small>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="notify_customer" id="notifyCustomer" checked>
                                        <label class="form-check-label" for="notifyCustomer">
                                            Notify customer via email
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="update_order" id="updateOrder" 
                                            <?php echo $payment['order_number'] ? 'checked' : 'disabled'; ?>>
                                        <label class="form-check-label" for="updateOrder">
                                            Update order status
                                            <?php if (!$payment['order_number']): ?>
                                            <small class="text-muted">(No linked order)</small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <hr>
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save me-2"></i> Update Payment Status
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Status Guidelines -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0">Status Guidelines</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item px-0 border-0">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-warning me-2">Pending</span>
                                    <small>Awaiting payment confirmation</small>
                                </div>
                                <p class="small text-muted mb-0">Use for payments that are still being processed.</p>
                            </div>
                            
                            <div class="list-group-item px-0 border-0">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-success me-2">Completed</span>
                                    <small>Payment successfully received</small>
                                </div>
                                <p class="small text-muted mb-0">Use when payment is confirmed and funds are available.</p>
                            </div>
                            
                            <div class="list-group-item px-0 border-0">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-danger me-2">Failed</span>
                                    <small>Payment declined or error</small>
                                </div>
                                <p class="small text-muted mb-0">Use for declined cards, insufficient funds, or technical errors.</p>
                            </div>
                            
                            <div class="list-group-item px-0 border-0">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="badge bg-info me-2">Refunded</span>
                                    <small>Payment refunded to customer</small>
                                </div>
                                <p class="small text-muted mb-0">Use after processing a full or partial refund.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Status Changes -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0">Recent Status Changes</h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach($recent_changes as $change): 
                                $old_color = match($change['old_status']) {
                                    'pending' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'refunded' => 'info',
                                    default => 'secondary'
                                };
                                $new_color = match($change['new_status']) {
                                    'pending' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    'refunded' => 'info',
                                    default => 'secondary'
                                };
                            ?>
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <div class="timeline-marker">
                                        <i class="fas fa-exchange-alt"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <span class="badge bg-<?php echo $old_color; ?>"><?php echo $change['old_status']; ?></span>
                                                <i class="fas fa-arrow-right mx-1 text-muted"></i>
                                                <span class="badge bg-<?php echo $new_color; ?>"><?php echo $change['new_status']; ?></span>
                                            </div>
                                            <small class="text-muted"><?php echo date('h:i A', strtotime($change['created_at'])); ?></small>
                                        </div>
                                        <small class="text-muted d-block">
                                            <?php if ($change['changed_by_name']): ?>
                                            By: <?php echo $change['changed_by_name']; ?>
                                            <?php else: ?>
                                            System
                                            <?php endif; ?>
                                        </small>
                                        <?php if ($change['transaction_id']): ?>
                                        <small class="text-muted d-block">
                                            TXN: <?php echo substr($change['transaction_id'], 0, 10) . '...'; ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Status Statistics Dashboard -->
        <div class="row mb-4">
            <?php foreach($status_stats as $stat): 
                $status_color = match($stat['status']) {
                    'pending' => 'warning',
                    'completed' => 'success',
                    'failed' => 'danger',
                    'refunded' => 'info',
                    default => 'secondary'
                };
                $status_icon = match($stat['status']) {
                    'pending' => 'clock',
                    'completed' => 'check-circle',
                    'failed' => 'times-circle',
                    'refunded' => 'undo',
                    default => 'question-circle'
                };
            ?>
            <div class="col-xl-2 col-md-4 mb-4">
                <div class="card border-left-<?php echo $status_color; ?> shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-<?php echo $status_color; ?> text-uppercase mb-1">
                                    <?php echo ucfirst($stat['status']); ?>
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stat['count']); ?>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">Total: $<?php echo number_format($stat['total_amount'], 2); ?></small><br>
                                    <small class="text-muted">Avg: $<?php echo number_format($stat['avg_amount'], 2); ?></small>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-<?php echo $status_icon; ?> fa-2x text-gray-300"></i>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="index.php?status=<?php echo $stat['status']; ?>" class="btn btn-sm btn-outline-<?php echo $status_color; ?> w-100">
                                <i class="fas fa-eye me-1"></i> View All
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Bulk Status Update -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Bulk Status Update</h5>
            </div>
            <div class="card-body">
                <form id="bulkStatusForm" method="POST" action="../../ajax/bulk-update-status.php">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Payments</label>
                            <select class="form-select" name="payment_ids[]" multiple size="6" required>
                                <option value="">-- Select Payments --</option>
                                <?php
                                $stmt = $db->query("SELECT p.id, p.transaction_id, p.amount, u.full_name 
                                                    FROM payments p 
                                                    LEFT JOIN users u ON p.user_id = u.id 
                                                    ORDER BY p.created_at DESC 
                                                    LIMIT 50");
                                $recent_payments = $stmt->fetchAll();
                                foreach($recent_payments as $pay):
                                ?>
                                <option value="<?php echo $pay['id']; ?>">
                                    #<?php echo $pay['id']; ?> - 
                                    <?php echo $pay['transaction_id'] ? substr($pay['transaction_id'], 0, 15) . '...' : 'Manual'; ?> - 
                                    $<?php echo number_format($pay['amount'], 2); ?> - 
                                    <?php echo htmlspecialchars($pay['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Hold Ctrl/Cmd to select multiple payments</small>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" name="new_status" required>
                                <option value="">Select Status</option>
                                <?php foreach($all_statuses as $status): ?>
                                <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-5">
                            <label class="form-label">Update Reason</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="notes" 
                                       placeholder="Reason for bulk update...">
                                <button type="button" class="btn btn-primary" onclick="previewBulkUpdate()">
                                    <i class="fas fa-eye me-1"></i> Preview
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="notify_customers" id="notifyCustomers">
                                <label class="form-check-label" for="notifyCustomers">
                                    Notify all customers via email
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <hr>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sync-alt me-2"></i> Update Selected Payments
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Status Transition Matrix -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Status Transition Rules</h5>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#rulesModal">
                    <i class="fas fa-cog me-1"></i> Configure Rules
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>From \ To</th>
                                <?php foreach($all_statuses as $status): ?>
                                <th class="text-center"><?php echo ucfirst($status); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($all_statuses as $from_status): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo match($from_status) {
                                        'pending' => 'warning',
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        'refunded' => 'info',
                                        default => 'secondary'
                                    }; ?>">
                                        <?php echo ucfirst($from_status); ?>
                                    </span>
                                </td>
                                <?php foreach($all_statuses as $to_status): 
                                    $allowed = true;
                                    $description = '';
                                    
                                    // Define transition rules
                                    if ($from_status === $to_status) {
                                        $allowed = false;
                                        $description = 'Same status';
                                    } elseif ($from_status === 'refunded' && $to_status !== 'completed') {
                                        $allowed = false;
                                        $description = 'Refunded payments can only go back to completed';
                                    } elseif ($from_status === 'completed' && $to_status === 'pending') {
                                        $allowed = false;
                                        $description = 'Cannot revert completed to pending';
                                    }
                                    
                                    $icon = $allowed ? 'check text-success' : 'times text-danger';
                                    $title = $allowed ? 'Allowed transition' : 'Not allowed: ' . $description;
                                ?>
                                <td class="text-center" title="<?php echo $title; ?>">
                                    <i class="fas fa-<?php echo $icon; ?>"></i>
                                    <?php if ($allowed && $from_status !== $to_status): ?>
                                    <div class="small text-muted">
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> Some status transitions may trigger additional actions like order updates, notifications, or audit logs.
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Status History -->
        <?php if ($payment_id > 0): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Status History</h5>
            </div>
            <div class="card-body">
                <?php
                $stmt = $db->prepare("SELECT pa.*, u.full_name as changed_by_name
                                      FROM payment_audit pa
                                      LEFT JOIN users u ON pa.performed_by = u.id
                                      WHERE pa.payment_id = ? AND pa.action = 'status_update'
                                      ORDER BY pa.created_at DESC");
                $stmt->execute([$payment_id]);
                $status_history = $stmt->fetchAll();
                ?>
                
                <?php if (empty($status_history)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No status history available</p>
                </div>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach($status_history as $history): 
                        $old_color = match($history['old_status']) {
                            'pending' => 'warning',
                            'completed' => 'success',
                            'failed' => 'danger',
                            'refunded' => 'info',
                            default => 'secondary'
                        };
                        $new_color = match($history['new_status']) {
                            'pending' => 'warning',
                            'completed' => 'success',
                            'failed' => 'danger',
                            'refunded' => 'info',
                            default => 'secondary'
                        };
                    ?>
                    <div class="timeline-item mb-4">
                        <div class="d-flex">
                            <div class="timeline-marker">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <div>
                                        <span class="badge bg-<?php echo $old_color; ?>"><?php echo $history['old_status']; ?></span>
                                        <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                        <span class="badge bg-<?php echo $new_color; ?>"><?php echo $history['new_status']; ?></span>
                                    </div>
                                    <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($history['created_at'])); ?></small>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <?php if ($history['changed_by_name']): ?>
                                        Changed by: <strong><?php echo $history['changed_by_name']; ?></strong>
                                        <?php else: ?>
                                        Changed by: <strong>System</strong>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <?php if ($history['notes']): ?>
                                <div class="alert alert-light py-2 small">
                                    <i class="fas fa-sticky-note me-2 text-muted"></i>
                                    <?php echo htmlspecialchars($history['notes']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Rules Configuration Modal -->
<div class="modal fade" id="rulesModal" tabindex="-1" aria-labelledby="rulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rulesModalLabel">Status Transition Rules</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="rulesForm">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>From Status</th>
                                    <th>To Status</th>
                                    <th>Allowed</th>
                                    <th>Actions</th>
                                    <th>Notifications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Define default rules
                                $default_rules = [
                                    ['from' => 'pending', 'to' => 'completed', 'allowed' => true, 'actions' => ['update_order'], 'notifications' => true],
                                    ['from' => 'pending', 'to' => 'failed', 'allowed' => true, 'actions' => [], 'notifications' => true],
                                    ['from' => 'completed', 'to' => 'refunded', 'allowed' => true, 'actions' => ['update_order'], 'notifications' => true],
                                    ['from' => 'refunded', 'to' => 'completed', 'allowed' => true, 'actions' => ['update_order'], 'notifications' => true],
                                ];
                                
                                foreach($default_rules as $rule):
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo match($rule['from']) {
                                            'pending' => 'warning',
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'info',
                                            default => 'secondary'
                                        }; ?>">
                                            <?php echo ucfirst($rule['from']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo match($rule['to']) {
                                            'pending' => 'warning',
                                            'completed' => 'success',
                                            'failed' => 'danger',
                                            'refunded' => 'info',
                                            default => 'secondary'
                                        }; ?>">
                                            <?php echo ucfirst($rule['to']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="allowed[<?php echo $rule['from']; ?>][<?php echo $rule['to']; ?>]" 
                                                   <?php echo $rule['allowed'] ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="actions[<?php echo $rule['from']; ?>][<?php echo $rule['to']; ?>][]" 
                                                   value="update_order"
                                                   <?php echo in_array('update_order', $rule['actions']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small">Update Order</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="actions[<?php echo $rule['from']; ?>][<?php echo $rule['to']; ?>][]" 
                                                   value="create_invoice"
                                                   <?php echo in_array('create_invoice', $rule['actions']) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small">Create Invoice</label>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="notifications[<?php echo $rule['from']; ?>][<?php echo $rule['to']; ?>]" 
                                                   <?php echo $rule['notifications'] ? 'checked' : ''; ?>>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> Changing these rules may affect automated workflows and notifications.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveRules()">Save Rules</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Set status via quick action buttons
function setStatus(status) {
    document.querySelector(`input[value="${status}"]`).checked = true;
}

// Update payment status
document.getElementById('statusUpdateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    if (!data.new_status) {
        Swal.fire('Error!', 'Please select a new status.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Update Payment Status',
        html: `
            <div class="text-start">
                <p>Are you sure you want to update this payment status?</p>
                <p><strong>New Status:</strong> ${data.new_status}</p>
                ${data.notes ? `<p><strong>Reason:</strong> ${data.notes}</p>` : ''}
                <hr>
                <p><strong>Actions:</strong></p>
                <p>✓ Update payment status</p>
                ${data.notify_customer ? '<p>✓ Notify customer via email</p>' : ''}
                ${data.update_order ? '<p>✓ Update order status</p>' : ''}
                <p>✓ Record in audit trail</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update status!'
    }).then((result) => {
        if (result.isConfirmed) {
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Updating...';
            submitButton.disabled = true;
            
            fetch('../ajax/update-payment-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    payment_id: data.payment_id,
                    status: data.new_status,
                    notes: data.notes,
                    notify_customer: data.notify_customer === 'on',
                    update_order: data.update_order === 'on'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                    submitButton.innerHTML = originalText;
                    submitButton.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        }
    });
});

// Preview bulk update
function previewBulkUpdate() {
    const form = document.getElementById('bulkStatusForm');
    const paymentSelect = form.querySelector('select[name="payment_ids[]"]');
    const statusSelect = form.querySelector('select[name="new_status"]');
    const notesInput = form.querySelector('input[name="notes"]');
    
    const selectedPayments = Array.from(paymentSelect.selectedOptions).map(opt => opt.value);
    
    if (selectedPayments.length === 0) {
        Swal.fire('Error!', 'Please select at least one payment.', 'error');
        return;
    }
    
    if (!statusSelect.value) {
        Swal.fire('Error!', 'Please select a new status.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Bulk Status Update Preview',
        html: `
            <div class="text-start">
                <p><strong>Selected Payments:</strong> ${selectedPayments.length} payment(s)</p>
                <p><strong>New Status:</strong> ${statusSelect.options[statusSelect.selectedIndex].text}</p>
                ${notesInput.value ? `<p><strong>Reason:</strong> ${notesInput.value}</p>` : ''}
                <hr>
                <p><strong>Will perform the following actions:</strong></p>
                <p>✓ Update ${selectedPayments.length} payment status(es)</p>
                ${form.notify_customers.checked ? '<p>✓ Send notifications to all customers</p>' : ''}
                <p>✓ Record all changes in audit trail</p>
                <p>✓ Update related orders (if applicable)</p>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Proceed',
        cancelButtonText: 'Cancel'
    });
}

// Bulk status update form submission
document.getElementById('bulkStatusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    previewBulkUpdate();
});

// Save rules
function saveRules() {
    Swal.fire({
        title: 'Save Rules?',
        text: 'Save the status transition rules configuration?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Save Rules'
    }).then((result) => {
        if (result.isConfirmed) {
            // In production, you would save these rules to database
            Swal.fire('Success!', 'Rules saved successfully.', 'success');
            $('#rulesModal').modal('hide');
        }
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

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
    background-color: #e9ecef;
}
.timeline-item {
    position: relative;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #fff;
    border: 2px solid #0d6efd;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0d6efd;
}
.font-monospace {
    font-family: 'Courier New', monospace;
    background-color: #f8f9fa;
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 0.9em;
}
</style>

<?php require_once '../includes/footer.php'; ?>