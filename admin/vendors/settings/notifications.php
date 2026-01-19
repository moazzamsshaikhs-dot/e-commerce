<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Notification Settings';
require_once '../../includes/header.php';

// Get vendor details and notifications
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get unread notifications count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$vendor_id]);
    $unread_count = $stmt->fetch()['count'];
    
    // Get recent notifications
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $notifications = $stmt->fetchAll();
    
    // Get notification preferences from settings
    $stmt = $db->prepare("
        SELECT setting_value FROM settings 
        WHERE setting_key = 'vendor_notification_prefs' 
        AND user_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $prefs_result = $stmt->fetch();
    
    $notification_prefs = $prefs_result ? json_decode($prefs_result['setting_value'], true) : [
        'email' => [
            'orders' => true,
            'reviews' => true,
            'earnings' => true,
            'products' => true,
            'marketing' => false
        ],
        'push' => [
            'orders' => true,
            'reviews' => true,
            'earnings' => true,
            'products' => true
        ],
        'sms' => [
            'orders' => false,
            'earnings' => false
        ]
    ];
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $unread_count = 0;
    $notifications = [];
    $notification_prefs = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_preferences') {
            $email_prefs = [
                'orders' => isset($_POST['email_orders']) ? 1 : 0,
                'reviews' => isset($_POST['email_reviews']) ? 1 : 0,
                'earnings' => isset($_POST['email_earnings']) ? 1 : 0,
                'products' => isset($_POST['email_products']) ? 1 : 0,
                'marketing' => isset($_POST['email_marketing']) ? 1 : 0
            ];
            
            $push_prefs = [
                'orders' => isset($_POST['push_orders']) ? 1 : 0,
                'reviews' => isset($_POST['push_reviews']) ? 1 : 0,
                'earnings' => isset($_POST['push_earnings']) ? 1 : 0,
                'products' => isset($_POST['push_products']) ? 1 : 0
            ];
            
            $sms_prefs = [
                'orders' => isset($_POST['sms_orders']) ? 1 : 0,
                'earnings' => isset($_POST['sms_earnings']) ? 1 : 0
            ];
            
            $prefs = [
                'email' => $email_prefs,
                'push' => $push_prefs,
                'sms' => $sms_prefs
            ];
            
            // Save to settings table
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, user_id, created_at)
                VALUES ('vendor_notification_prefs', ?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
            ");
            $stmt->execute([json_encode($prefs), $vendor_id, json_encode($prefs)]);
            
            $_SESSION['success'] = 'Notification preferences updated successfully!';
            redirect('notifications.php');
            
        } elseif ($action === 'mark_all_read') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$vendor_id]);
            
            $_SESSION['success'] = 'All notifications marked as read!';
            redirect('notifications.php');
            
        } elseif ($action === 'clear_all') {
            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$vendor_id]);
            
            $_SESSION['success'] = 'All notifications cleared!';
            redirect('notifications.php');
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Notification Settings</h1>
                <p class="text-muted mb-0">Manage your notification preferences</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
                <?php if ($unread_count > 0): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-double me-2"></i> Mark All Read
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Notification Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Unread</h6>
                                <h3 class="fw-bold text-primary"><?php echo $unread_count; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-bell text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Today</h6>
                                <h3 class="fw-bold text-success">
                                    <?php
                                    $today_count = 0;
                                    foreach($notifications as $note) {
                                        if (date('Y-m-d', strtotime($note['created_at'])) == date('Y-m-d')) {
                                            $today_count++;
                                        }
                                    }
                                    echo $today_count;
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-calendar-day text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">This Week</h6>
                                <h3 class="fw-bold text-warning">
                                    <?php
                                    $week_count = 0;
                                    $week_start = date('Y-m-d', strtotime('-7 days'));
                                    foreach($notifications as $note) {
                                        if (date('Y-m-d', strtotime($note['created_at'])) >= $week_start) {
                                            $week_count++;
                                        }
                                    }
                                    echo $week_count;
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-calendar-week text-warning fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total</h6>
                                <h3 class="fw-bold text-info"><?php echo count($notifications); ?></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="fas fa-inbox text-info fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notifications Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="notificationTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="preferences-tab" data-bs-toggle="tab" 
                                data-bs-target="#preferences" type="button">
                            <i class="fas fa-cog me-2"></i> Preferences
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="inbox-tab" data-bs-toggle="tab" 
                                data-bs-target="#inbox" type="button">
                            <i class="fas fa-inbox me-2"></i> Inbox
                            <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" 
                                data-bs-target="#email" type="button">
                            <i class="fas fa-envelope me-2"></i> Email Settings
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Notifications Content -->
        <div class="tab-content" id="notificationTabContent">
            <!-- Preferences Tab -->
            <div class="tab-pane fade show active" id="preferences" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-cog me-2"></i> Notification Preferences
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="preferencesForm">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <!-- Email Notifications -->
                            <div class="mb-5">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-envelope text-primary me-2"></i> Email Notifications
                                </h6>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Notification Type</th>
                                                <th>Description</th>
                                                <th>Enable</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <strong>New Orders</strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">When customer places an order</small>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="email_orders" id="emailOrders"
                                                               <?php echo ($notification_prefs['email']['orders'] ?? true) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong>New Reviews</strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">When customer leaves a review</small>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="email_reviews" id="emailReviews"
                                                               <?php echo ($notification_prefs['email']['reviews'] ?? true) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong>Earnings & Payments</strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">When earnings are processed or paid</small>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="email_earnings" id="emailEarnings"
                                                               <?php echo ($notification_prefs['email']['earnings'] ?? true) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong>Product Updates</strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">When product status changes (approved/out of stock)</small>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="email_products" id="emailProducts"
                                                               <?php echo ($notification_prefs['email']['products'] ?? true) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <strong>Marketing & Updates</strong>
                                                </td>
                                                <td>
                                                    <small class="text-muted">News, tips, and promotional offers</small>
                                                </td>
                                                <td>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="email_marketing" id="emailMarketing"
                                                               <?php echo ($notification_prefs['email']['marketing'] ?? false) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Push Notifications -->
                            <div class="mb-5">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-bell text-warning me-2"></i> Push Notifications
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="push_orders" id="pushOrders"
                                                   <?php echo ($notification_prefs['push']['orders'] ?? true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="pushOrders">
                                                New Orders
                                            </label>
                                            <small class="text-muted d-block">Real-time order notifications</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="push_reviews" id="pushReviews"
                                                   <?php echo ($notification_prefs['push']['reviews'] ?? true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="pushReviews">
                                                New Reviews
                                            </label>
                                            <small class="text-muted d-block">Customer review alerts</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="push_earnings" id="pushEarnings"
                                                   <?php echo ($notification_prefs['push']['earnings'] ?? true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="pushEarnings">
                                                Earnings Updates
                                            </label>
                                            <small class="text-muted d-block">Payment and withdrawal alerts</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="push_products" id="pushProducts"
                                                   <?php echo ($notification_prefs['push']['products'] ?? true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="pushProducts">
                                                Product Alerts
                                            </label>
                                            <small class="text-muted d-block">Low stock and status changes</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SMS Notifications -->
                            <div class="mb-5">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-sms text-success me-2"></i> SMS Notifications
                                </h6>
                                <div class="alert alert-info">
                                    <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> SMS Credits Required</h6>
                                    <p class="mb-2">SMS notifications require credits. Standard rates apply.</p>
                                    <a href="#" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-credit-card me-2"></i> Purchase SMS Credits
                                    </a>
                                </div>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="sms_orders" id="smsOrders"
                                                   <?php echo ($notification_prefs['sms']['orders'] ?? false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="smsOrders">
                                                Urgent Order Alerts
                                            </label>
                                            <small class="text-muted d-block">High-value or time-sensitive orders</small>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="sms_earnings" id="smsEarnings"
                                                   <?php echo ($notification_prefs['sms']['earnings'] ?? false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="smsEarnings">
                                                Large Payments
                                            </label>
                                            <small class="text-muted d-block">For payments above $500</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notification Frequency -->
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-clock text-info me-2"></i> Notification Frequency
                                </h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Email Digest</label>
                                            <select class="form-select" name="email_frequency">
                                                <option value="realtime" selected>Real-time</option>
                                                <option value="hourly">Hourly Summary</option>
                                                <option value="daily">Daily Digest</option>
                                                <option value="weekly">Weekly Report</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Quiet Hours</label>
                                            <div class="input-group">
                                                <input type="time" class="form-control" name="quiet_start" value="22:00">
                                                <span class="input-group-text">to</span>
                                                <input type="time" class="form-control" name="quiet_end" value="08:00">
                                            </div>
                                            <small class="text-muted">No push notifications during these hours</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="resetPreferences()">
                                    <i class="fas fa-undo me-2"></i> Reset to Defaults
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Inbox Tab -->
            <div class="tab-pane fade" id="inbox" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-inbox me-2"></i> Notification Inbox
                        </h5>
                        <div class="btn-group">
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="mark_all_read">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check-double me-2"></i> Mark All Read
                                </button>
                            </form>
                            <form method="POST" class="d-inline ms-2">
                                <input type="hidden" name="action" value="clear_all">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Clear all notifications?')">
                                    <i class="fas fa-trash me-2"></i> Clear All
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($notifications)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Notifications</h5>
                            <p class="text-muted">You're all caught up!</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach($notifications as $notification): 
                                $type_colors = [
                                    'info' => 'primary',
                                    'success' => 'success',
                                    'warning' => 'warning',
                                    'error' => 'danger'
                                ];
                                $type_icons = [
                                    'info' => 'info-circle',
                                    'success' => 'check-circle',
                                    'warning' => 'exclamation-triangle',
                                    'error' => 'times-circle'
                                ];
                            ?>
                            <div class="list-group-item list-group-item-action border-0 py-3 px-4 
                                        <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                <div class="d-flex align-items-start">
                                    <!-- Notification Icon -->
                                    <div class="me-3">
                                        <div class="bg-<?php echo $type_colors[$notification['type']] ?? 'primary'; ?> 
                                                     bg-opacity-10 p-2 rounded">
                                            <i class="fas fa-<?php echo $type_icons[$notification['type']] ?? 'bell'; ?> 
                                                       text-<?php echo $type_colors[$notification['type']] ?? 'primary'; ?> 
                                                       fa-lg"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Notification Content -->
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="mb-1 fw-bold">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php if (!$notification['is_read']): ?>
                                                <span class="badge bg-danger ms-2">New</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?php 
                                                $time_ago = time() - strtotime($notification['created_at']);
                                                if ($time_ago < 60) {
                                                    echo 'Just now';
                                                } elseif ($time_ago < 3600) {
                                                    echo floor($time_ago / 60) . ' min ago';
                                                } elseif ($time_ago < 86400) {
                                                    echo floor($time_ago / 3600) . ' hour ago';
                                                } else {
                                                    echo date('d M, h:i A', strtotime($notification['created_at']));
                                                }
                                                ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        
                                        <!-- Actions -->
                                        <div class="mt-2">
                                            <?php if (!$notification['is_read']): ?>
                                            <button class="btn btn-sm btn-outline-success" 
                                                    onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-check me-1"></i> Mark as Read
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger ms-2" 
                                                    onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-trash me-1"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- View More -->
                        <div class="text-center py-3 border-top">
                            <a href="notifications-all.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-2"></i> View All Notifications
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notification Categories -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Notification Categories</h6>
                                <div class="row g-2">
                                    <?php
                                    $categories = [
                                        'orders' => ['Orders', 'primary', 'shopping-cart'],
                                        'reviews' => ['Reviews', 'success', 'star'],
                                        'earnings' => ['Earnings', 'warning', 'money-bill-wave'],
                                        'products' => ['Products', 'info', 'box'],
                                        'system' => ['System', 'secondary', 'cog']
                                    ];
                                    
                                    foreach($categories as $key => $cat):
                                        $count = 0;
                                        foreach($notifications as $note) {
                                            if (stripos($note['title'], $cat[0]) !== false) {
                                                $count++;
                                            }
                                        }
                                    ?>
                                    <div class="col-6">
                                        <div class="border rounded p-3 mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-<?php echo $cat[1]; ?> bg-opacity-10 p-2 rounded me-3">
                                                    <i class="fas fa-<?php echo $cat[2]; ?> text-<?php echo $cat[1]; ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold"><?php echo $cat[0]; ?></h6>
                                                    <small class="text-muted"><?php echo $count; ?> notifications</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">Notification Statistics</h6>
                                <canvas id="notificationChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Email Settings Tab -->
            <div class="tab-pane fade" id="email" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-envelope me-2"></i> Email Notification Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="emailSettingsForm">
                            <input type="hidden" name="action" value="update_email_settings">
                            
                            <div class="row g-4">
                                <!-- Email Address -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Primary Email Address *</label>
                                        <input type="email" name="primary_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required>
                                        <small class="text-muted">All notifications will be sent to this address</small>
                                    </div>
                                </div>
                                
                                <!-- Secondary Email -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Secondary Email (Optional)</label>
                                        <input type="email" name="secondary_email" class="form-control">
                                        <small class="text-muted">Backup email for important notifications</small>
                                    </div>
                                </div>
                                
                                <!-- Email Format -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Email Format</label>
                                        <select name="email_format" class="form-select">
                                            <option value="html" selected>HTML (Rich Text)</option>
                                            <option value="text">Plain Text</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Language -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Email Language</label>
                                        <select name="email_language" class="form-select">
                                            <option value="en" selected>English</option>
                                            <option value="hi">Hindi</option>
                                            <option value="es">Spanish</option>
                                            <option value="fr">French</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Custom Footer -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Custom Email Footer (Optional)</label>
                                        <textarea name="email_footer" class="form-control" rows="3"
                                                  placeholder="Add a custom message to all emails..."></textarea>
                                        <small class="text-muted">This will be added to the bottom of all notification emails</small>
                                    </div>
                                </div>
                                
                                <!-- Email Templates -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-3">Email Templates</h6>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Template</th>
                                                    <th>Description</th>
                                                    <th>Status</th>
                                                    <th>Preview</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>
                                                        <strong>Order Confirmation</strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">Sent when order is placed</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Enabled</span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i> Preview
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        <strong>Payment Receipt</strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">Sent when payment is received</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Enabled</span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i> Preview
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        <strong>Review Reminder</strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">Sent after order delivery</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-warning">Disabled</span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i> Preview
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>
                                                        <strong>Weekly Report</strong>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">Weekly sales and analytics</small>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Enabled</span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye me-1"></i> Preview
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Email Test -->
                                <div class="col-12">
                                    <div class="border rounded p-4 bg-light">
                                        <h6 class="fw-bold mb-3">Test Email Notifications</h6>
                                        <p class="text-muted mb-3">Send a test email to verify your settings</p>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <select class="form-select" id="testEmailType">
                                                    <option value="order">Order Notification</option>
                                                    <option value="review">Review Notification</option>
                                                    <option value="payment">Payment Notification</option>
                                                    <option value="report">Weekly Report</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="button" class="btn btn-outline-primary w-100" 
                                                        onclick="sendTestEmail()">
                                                    <i class="fas fa-paper-plane me-2"></i> Send Test Email
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Email Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Notifications JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const triggerTabList = [].slice.call(document.querySelectorAll('#notificationTab button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Initialize chart
    initNotificationChart();
    
    // Form submissions
    const forms = ['preferencesForm', 'emailSettingsForm'];
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this);
            });
        }
    });
});

function initNotificationChart() {
    const ctx = document.getElementById('notificationChart');
    if (!ctx) return;
    
    // Sample data - in production, fetch from API
    const data = {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [
            {
                label: 'Orders',
                data: [12, 19, 8, 15, 22, 18, 10],
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.3
            },
            {
                label: 'Reviews',
                data: [8, 12, 6, 10, 15, 12, 8],
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.3
            },
            {
                label: 'System',
                data: [3, 5, 2, 4, 6, 3, 2],
                borderColor: '#6c757d',
                backgroundColor: 'rgba(108, 117, 125, 0.1)',
                tension: 0.3
            }
        ]
    };
    
    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 5
                    }
                }
            }
        }
    };
    
    new Chart(ctx, config);
}

function markAsRead(notificationId) {
    fetch('action/mark-read.php', {
        method: 'POST',
        body: new FormData(document.getElementById('readForm_' + notificationId))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    });
}

function deleteNotification(notificationId) {
    if (confirm('Delete this notification?')) {
        fetch('action/delete-notification.php', {
            method: 'POST',
            body: new FormData(document.getElementById('deleteForm_' + notificationId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function resetPreferences() {
    if (confirm('Reset all notification preferences to defaults?')) {
        // Reset all checkboxes
        document.querySelectorAll('#preferencesForm input[type="checkbox"]').forEach(checkbox => {
            if (checkbox.name.includes('email_') && !checkbox.name.includes('marketing')) {
                checkbox.checked = true;
            } else if (checkbox.name.includes('push_')) {
                checkbox.checked = true;
            } else {
                checkbox.checked = false;
            }
        });
        
        // Reset selects
        document.querySelector('select[name="email_frequency"]').value = 'realtime';
        document.querySelector('input[name="quiet_start"]').value = '22:00';
        document.querySelector('input[name="quiet_end"]').value = '08:00';
    }
}

function sendTestEmail() {
    const type = document.getElementById('testEmailType').value;
    
    fetch('action/send-test-email.php', {
        method: 'POST',
        body: new FormData(document.getElementById('testEmailForm'))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test email sent successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function submitForm(form) {
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}
</script>

<style>
/* Notification styles */
.list-group-item:hover {
    background-color: #f8f9fa !important;
}

.bg-opacity-10 {
    opacity: 0.1;
}

/* Switch styles */
.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
}

.form-switch .form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>

<?php require_once '../../includes/footer.php'; ?>