<?php
// admin/users/user-view.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/super-admin-check.php';

// Require super admin access
requireSuperAdmin();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];

try {
    $db = getDB();
    
    // First, check what date columns exist in orders table
    $columns = $db->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    
    // Determine which date column to use
    $date_column = 'created_at'; // default
    if (in_array('order_date', $columns)) {
        $date_column = 'order_date';
    } elseif (in_array('created', $columns)) {
        $date_column = 'created';
    } elseif (in_array('created_at', $columns)) {
        $date_column = 'created_at';
    }
    
    // Fetch user details with additional stats
    $stmt = $db->prepare("
        SELECT 
            u.*,
            (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as total_products,
            (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
            (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id) as total_documents,
            (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id AND verified = 1) as verified_documents,
            (SELECT SUM(total_amount) FROM orders WHERE user_id = u.id AND payment_status = 'completed') as total_spent,
            (SELECT SUM(vendor_amount) FROM vendor_earnings WHERE vendor_id = u.id AND status = 'paid') as total_earned
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: users.php');
        exit;
    }
    
    // Get user activities
    $stmt = $db->prepare("
        SELECT * FROM user_activities 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $activities = $stmt->fetchAll();
    
    // Get user documents if vendor
    $documents = [];
    if ($user['user_type'] === 'vendor') {
        $stmt = $db->prepare("
            SELECT * FROM vendor_documents 
            WHERE vendor_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user_id]);
        $documents = $stmt->fetchAll();
    }
    
    // Get recent orders - FIXED: Use dynamic column name
    $stmt = $db->prepare("
        SELECT * FROM orders 
        WHERE user_id = ? 
        ORDER BY {$date_column} DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: users.php');
    exit;
}

$page_title = 'View User: ' . ($user['full_name'] ?? $user['username']);
require_once '../includes/header.php';
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3651c4;
    --primary-light: rgba(67, 97, 238, 0.1);
    --success: #06d6a0;
    --success-dark: #05b585;
    --success-light: rgba(6, 214, 160, 0.1);
    --warning: #ffb703;
    --warning-dark: #e6a500;
    --warning-light: rgba(255, 183, 3, 0.1);
    --danger: #ef476f;
    --danger-dark: #d64161;
    --danger-light: rgba(239, 71, 111, 0.1);
    --info: #4cc9f0;
    --info-dark: #3aa9d9;
    --info-light: rgba(76, 201, 240, 0.1);
    --dark: #2b2d42;
    --dark-light: rgba(43, 45, 66, 0.1);
    --light: #f8f9fa;
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

.view-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
    border-radius: 50%;
    z-index: 0;
}

.page-header > div {
    position: relative;
    z-index: 1;
}

/* Profile Card */
.profile-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    box-shadow: var(--shadow);
    height: 100%;
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    margin: 0 auto 20px;
    display: block;
}

.profile-name {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark);
    text-align: center;
    margin-bottom: 5px;
}

.profile-username {
    text-align: center;
    color: var(--dark);
    opacity: 0.7;
    margin-bottom: 15px;
}

.profile-badges {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.badge-type {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-type.admin { background: var(--danger-light); color: var(--danger-dark); border: 1px solid var(--danger); }
.badge-type.vendor { background: var(--warning-light); color: var(--warning-dark); border: 1px solid var(--warning); }
.badge-type.user { background: var(--success-light); color: var(--success-dark); border: 1px solid var(--success); }

.badge-status {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-status.active { background: var(--success-light); color: var(--success-dark); border: 1px solid var(--success); }
.badge-status.suspended { background: var(--danger-light); color: var(--danger-dark); border: 1px solid var(--danger); }

.profile-info {
    margin-top: 20px;
}

.info-item {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
}

.info-item:last-child {
    border-bottom: none;
}

.info-icon {
    width: 35px;
    height: 35px;
    border-radius: var(--radius);
    background: var(--primary-light);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 11px;
    color: var(--dark);
    opacity: 0.6;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-weight: 500;
    color: var(--dark);
}

/* Stats Cards */
.stats-grid-small {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card-small {
    background: white;
    border-radius: var(--radius-lg);
    padding: 15px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    text-align: center;
}

.stat-value-small {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
}

.stat-label-small {
    font-size: 12px;
    color: var(--dark);
    opacity: 0.7;
}

/* Section Card */
.section-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    margin-bottom: 25px;
}

.section-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: var(--primary);
}

/* Activity Timeline */
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--border);
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: var(--primary);
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.timeline-time {
    font-size: 11px;
    color: var(--dark);
    opacity: 0.6;
    margin-bottom: 5px;
}

.timeline-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
}

.timeline-desc {
    font-size: 12px;
    color: var(--dark);
    opacity: 0.7;
}

/* Document Cards */
.document-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.document-card {
    background: var(--light);
    border-radius: var(--radius);
    padding: 15px;
    border: 1px solid var(--border);
    transition: var(--transition);
}

.document-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    background: white;
}

.document-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    margin-bottom: 10px;
}

.document-icon.id_proof { background: var(--primary-light); color: var(--primary); }
.document-icon.address_proof { background: var(--info-light); color: var(--info); }
.document-icon.business_registration { background: var(--success-light); color: var(--success); }
.document-icon.tax_certificate { background: var(--warning-light); color: var(--warning); }

.document-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
    font-size: 14px;
}

.document-meta {
    font-size: 11px;
    color: var(--dark);
    opacity: 0.6;
    margin-bottom: 10px;
}

.document-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.document-status.verified { background: var(--success-light); color: var(--success-dark); border: 1px solid var(--success); }
.document-status.pending { background: var(--warning-light); color: var(--warning-dark); border: 1px solid var(--warning); }

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.btn-action-large {
    padding: 10px 20px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-edit {
    background: var(--primary);
    color: white;
}

.btn-edit:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    color: white;
}

.btn-back {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
}

.btn-back:hover {
    background: var(--border);
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .view-container {
        padding: 20px;
    }
    
    .stats-grid-small {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>


<div class="view-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-user-circle me-2" style="color: var(--primary);"></i>
                    User Profile
                </h1>
                <p class="text-muted mb-0">
                    View complete user information and activity
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Users
                </a>
                <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i> Edit User
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column - Profile -->
        <div class="col-lg-4">
            <div class="profile-card">
                <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $user['profile_pic'] ?? 'default.png'; ?>" 
                     alt="Profile" class="profile-avatar" onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png';">
                
                <h2 class="profile-name"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></h2>
                <div class="profile-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                
                <div class="profile-badges">
                    <span class="badge-type <?php echo $user['user_type']; ?>">
                        <?php echo ucfirst($user['user_type']); ?>
                    </span>
                    <span class="badge-status <?php echo $user['account_status']; ?>">
                        <?php echo ucfirst($user['account_status']); ?>
                    </span>
                </div>
                
                <div class="profile-info">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Email Address</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Phone Number</div>
                            <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Location</div>
                            <div class="info-value">
                                <?php 
                                $location = [];
                                if (!empty($user['city'])) $location[] = $user['city'];
                                if (!empty($user['country'])) $location[] = $user['country'];
                                echo !empty($location) ? implode(', ', $location) : 'Not provided';
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Member Since</div>
                            <div class="info-value"><?php echo date('d M Y', strtotime($user['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Last Login</div>
                            <div class="info-value"><?php echo $user['last_login'] ? date('d M Y H:i', strtotime($user['last_login'])) : 'Never'; ?></div>
                        </div>
                    </div>
                    
                    <?php if ($user['user_type'] === 'vendor'): ?>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Vendor Status</div>
                            <div class="info-value">
                                <?php if ($user['vendor_verified']): ?>
                                    <span class="badge bg-success">Verified</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending Verification</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="user-edit.php?id=<?php echo $user['id']; ?>" class="btn-action-large btn-edit">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Stats and Activity -->
        <div class="col-lg-8">
            <!-- Quick Stats -->
            <div class="stats-grid-small">
                <div class="stat-card-small">
                    <div class="stat-value-small"><?php echo $user['login_count'] ?? 0; ?></div>
                    <div class="stat-label-small">Logins</div>
                </div>
                
                <?php if ($user['user_type'] === 'vendor'): ?>
                <div class="stat-card-small">
                    <div class="stat-value-small"><?php echo $user['total_products'] ?? 0; ?></div>
                    <div class="stat-label-small">Products</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-value-small">$<?php echo number_format($user['total_earned'] ?? 0, 2); ?></div>
                    <div class="stat-label-small">Earnings</div>
                </div>
                <?php else: ?>
                <div class="stat-card-small">
                    <div class="stat-value-small"><?php echo $user['total_orders'] ?? 0; ?></div>
                    <div class="stat-label-small">Orders</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-value-small">$<?php echo number_format($user['total_spent'] ?? 0, 2); ?></div>
                    <div class="stat-label-small">Total Spent</div>
                </div>
                <?php endif; ?>
                
                <div class="stat-card-small">
                    <div class="stat-value-small"><?php echo count($activities); ?></div>
                    <div class="stat-label-small">Activities</div>
                </div>
            </div>
            
            <!-- Documents Section (for vendors) -->
            <?php if ($user['user_type'] === 'vendor' && !empty($documents)): ?>
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-file-alt"></i>
                    Documents
                    <span class="badge bg-primary ms-2"><?php echo count($documents); ?></span>
                </div>
                
                <div class="document-grid">
                    <?php foreach ($documents as $doc): ?>
                    <div class="document-card">
                        <div class="document-icon <?php echo $doc['document_type']; ?>">
                            <i class="fas fa-<?php echo $doc['document_type'] == 'id_proof' ? 'id-card' : 'file'; ?>"></i>
                        </div>
                        <div class="document-name"><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></div>
                        <div class="document-meta">#<?php echo htmlspecialchars($doc['document_number'] ?? 'N/A'); ?></div>
                        <div>
                            <span class="document-status <?php echo $doc['verified'] ? 'verified' : 'pending'; ?>">
                                <?php echo $doc['verified'] ? 'Verified' : 'Pending'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Activity Timeline -->
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-history"></i>
                    Recent Activity
                </div>
                
                <?php if (empty($activities)): ?>
                    <p class="text-muted text-center py-4">No recent activity</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($activities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-time">
                                <?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?>
                            </div>
                            <div class="timeline-title">
                                <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                            </div>
                            <div class="timeline-desc">
                                <?php echo htmlspecialchars($activity['description']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Orders - FIXED: Display orders with correct date column -->
            <?php if (!empty($orders)): ?>
            <div class="section-card">
                <div class="section-title">
                    <i class="fas fa-shopping-cart"></i>
                    Recent Orders
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): 
                                // Determine which date column to use for display
                                $order_date = $order['order_date'] ?? $order['created_at'] ?? $order['created'] ?? null;
                            ?>
                            <tr>
                                <td><?php echo $order['order_number'] ?? '#' . $order['id']; ?></td>
                                <td>$<?php echo number_format($order['total_amount'] ?? 0, 2); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo ($order['status'] ?? 'pending') == 'delivered' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($order['status'] ?? 'pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($order_date) {
                                        echo date('d M Y', strtotime($order_date));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
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

<?php require_once '../includes/footer.php'; ?>