<?php
// admin/vendors/view-vendor.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'View Vendor Details';
require_once '../includes/header.php';

$db = getDB();
$vendor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$vendor_id) {
    $_SESSION['error'] = 'Invalid vendor ID';
    header('Location: vendors.php');
    exit();
}

// Get vendor details
try {
    $stmt = $db->prepare("
        SELECT u.*, 
               COUNT(DISTINCT p.id) as total_products,
               COUNT(DISTINCT oi.id) as total_sales,
               COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_revenue,
               (SELECT COUNT(*) FROM vendor_withdrawals WHERE vendor_id = u.id AND status = 'pending') as pending_withdrawals,
               (SELECT COUNT(*) FROM vendor_withdrawals WHERE vendor_id = u.id AND status = 'completed') as completed_withdrawals,
               (SELECT COALESCE(SUM(withdrawal_amount), 0) FROM vendor_withdrawals WHERE vendor_id = u.id AND status = 'completed') as total_withdrawn
        FROM users u
        LEFT JOIN products p ON u.id = p.vendor_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed'
        WHERE u.id = ? AND u.user_type = 'vendor'
        GROUP BY u.id
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        $_SESSION['error'] = 'Vendor not found';
        header('Location: vendors.php');
        exit();
    }

    // Get vendor's products
    $stmt = $db->prepare("
        SELECT * FROM products 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get vendor's payment methods
    $payment_methods = [];

    // Bank accounts
    $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $payment_methods['bank'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mobile accounts
    $stmt = $db->prepare("SELECT * FROM vendor_mobile_accounts WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $payment_methods['mobile'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // PayPal accounts
    $stmt = $db->prepare("SELECT * FROM vendor_paypal_accounts WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $payment_methods['paypal'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Stripe accounts
    $stmt = $db->prepare("SELECT * FROM vendor_stripe_accounts WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $payment_methods['stripe'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cards
    $stmt = $db->prepare("SELECT * FROM vendor_cards WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $payment_methods['cards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get withdrawal history
    $stmt = $db->prepare("
        SELECT * FROM vendor_withdrawals 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent orders
    $stmt = $db->prepare("
        SELECT o.*, oi.product_id, p.name as product_name
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading vendor: ' . $e->getMessage();
    header('Location: vendors.php');
    exit();
}
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
}

.view-vendor-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Profile Header */
.profile-header {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.profile-cover {
    height: 150px;
    background: linear-gradient(135deg, var(--primary), var(--info));
    position: relative;
}

.profile-info {
    padding: 0 30px 30px 30px;
    position: relative;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--info));
    border: 5px solid white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: 600;
    color: white;
    margin-top: -60px;
    margin-bottom: 20px;
    box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
}

.profile-name {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.profile-username {
    color: #6c757d;
    font-size: 16px;
    margin-bottom: 15px;
}

.profile-badges {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.badge {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
}

.badge-verified { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.badge-pending { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.badge-suspended { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
.badge-approved { background: rgba(67, 97, 238, 0.1); color: var(--primary); }

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 14px;
}

/* Info Cards */
.info-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.info-card h5 {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #edf2f9;
}

.info-row {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px dashed #edf2f9;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    width: 150px;
    color: #6c757d;
    font-weight: 500;
}

.info-value {
    flex: 1;
    color: var(--dark);
    font-weight: 500;
}

/* Payment Method Cards */
.payment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.payment-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 20px;
    border-left: 4px solid transparent;
}

.payment-card.bank { border-left-color: var(--primary); }
.payment-card.mobile { border-left-color: var(--success); }
.payment-card.paypal { border-left-color: var(--info); }
.payment-card.stripe { border-left-color: var(--warning); }
.payment-card.card { border-left-color: var(--danger); }

.payment-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.payment-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.payment-title {
    font-weight: 600;
    color: var(--dark);
}

.payment-detail {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 5px;
}

.payment-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    margin-top: 10px;
}

/* Action Buttons */
.action-buttons {
    position: absolute;
    top: 30px;
    right: 30px;
    display: flex;
    gap: 10px;
}

.btn-action {
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-approve {
    background: var(--success);
    color: white;
}

.btn-reject {
    background: var(--danger);
    color: white;
}

.btn-suspend {
    background: var(--warning);
    color: white;
}

.btn-verify {
    background: var(--info);
    color: white;
}

.btn-edit {
    background: var(--primary);
    color: white;
}

.btn-action:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

/* Responsive */
@media (max-width: 768px) {
    .view-vendor-container {
        padding: 20px;
    }
    
    .profile-info {
        padding: 0 20px 20px 20px;
    }
    
    .action-buttons {
        position: static;
        margin-top: 20px;
        justify-content: flex-start;
    }
    
    .info-row {
        flex-direction: column;
    }
    
    .info-label {
        width: 100%;
        margin-bottom: 5px;
    }
}
</style>

<div class="view-vendor-container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-cover"></div>
        <div class="profile-info">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($vendor['full_name'] ?? $vendor['username'], 0, 1)); ?>
            </div>
            
            <div class="action-buttons">
                <?php if ($vendor['vendor_status'] == 'pending'): ?>
                    <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                        <i class="fas fa-check me-2"></i> Approve
                    </button>
                    <button class="btn-action btn-reject" onclick="rejectVendor(<?php echo $vendor['id']; ?>)">
                        <i class="fas fa-times me-2"></i> Reject
                    </button>
                <?php elseif ($vendor['vendor_status'] == 'approved'): ?>
                    <?php if (!$vendor['vendor_verified']): ?>
                        <button class="btn-action btn-verify" onclick="verifyVendor(<?php echo $vendor['id']; ?>)">
                            <i class="fas fa-shield-alt me-2"></i> Verify
                        </button>
                    <?php endif; ?>
                    <button class="btn-action btn-suspend" onclick="suspendVendor(<?php echo $vendor['id']; ?>)">
                        <i class="fas fa-ban me-2"></i> Suspend
                    </button>
                <?php elseif ($vendor['vendor_status'] == 'suspended'): ?>
                    <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                        <i class="fas fa-check me-2"></i> Reactivate
                    </button>
                <?php endif; ?>
                <a href="edit-vendor.php?id=<?php echo $vendor['id']; ?>" class="btn-action btn-edit">
                    <i class="fas fa-edit me-2"></i> Edit
                </a>
            </div>
            
            <div class="profile-name"><?php echo htmlspecialchars($vendor['full_name'] ?? $vendor['username']); ?></div>
            <div class="profile-username">@<?php echo htmlspecialchars($vendor['username']); ?></div>
            
            <div class="profile-badges">
                <?php if ($vendor['vendor_status'] == 'approved'): ?>
                    <span class="badge badge-approved">
                        <i class="fas fa-check-circle me-1"></i> Approved
                    </span>
                <?php elseif ($vendor['vendor_status'] == 'pending'): ?>
                    <span class="badge badge-pending">
                        <i class="fas fa-clock me-1"></i> Pending Approval
                    </span>
                <?php elseif ($vendor['vendor_status'] == 'suspended'): ?>
                    <span class="badge badge-suspended">
                        <i class="fas fa-ban me-1"></i> Suspended
                    </span>
                <?php endif; ?>
                
                <?php if ($vendor['vendor_verified']): ?>
                    <span class="badge badge-verified">
                        <i class="fas fa-shield-alt me-1"></i> Verified
                    </span>
                <?php endif; ?>
                
                <span class="badge" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);">
                    <i class="fas fa-calendar me-1"></i> Member since <?php echo date('M Y', strtotime($vendor['created_at'])); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                <i class="fas fa-box text-primary"></i>
            </div>
            <div class="stat-value"><?php echo $vendor['total_products']; ?></div>
            <div class="stat-label">Total Products</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                <i class="fas fa-shopping-cart text-success"></i>
            </div>
            <div class="stat-value"><?php echo $vendor['total_sales']; ?></div>
            <div class="stat-label">Total Sales</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                <i class="fas fa-dollar-sign text-warning"></i>
            </div>
            <div class="stat-value">$<?php echo number_format($vendor['total_revenue'], 2); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                <i class="fas fa-money-bill-wave text-info"></i>
            </div>
            <div class="stat-value">$<?php echo number_format($vendor['total_withdrawn'], 2); ?></div>
            <div class="stat-label">Withdrawn</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                <i class="fas fa-clock text-danger"></i>
            </div>
            <div class="stat-value"><?php echo $vendor['pending_withdrawals']; ?></div>
            <div class="stat-label">Pending Withdrawals</div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-6">
            <!-- Contact Information -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-address-card me-2 text-primary"></i>
                    Contact Information
                </h5>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">
                        <a href="mailto:<?php echo $vendor['email']; ?>"><?php echo $vendor['email']; ?></a>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?php echo $vendor['phone'] ?? 'Not provided'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?php echo $vendor['address'] ?? 'Not provided'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Country</span>
                    <span class="info-value"><?php echo $vendor['country'] ?? 'Not provided'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tax ID</span>
                    <span class="info-value"><?php echo $vendor['tax_id'] ?? 'Not provided'; ?></span>
                </div>
            </div>

            <!-- Bank Accounts -->
            <?php if (!empty($payment_methods['bank'])): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-university me-2 text-primary"></i>
                    Bank Accounts
                </h5>
                <?php foreach ($payment_methods['bank'] as $bank): ?>
                <div class="payment-card bank mb-3">
                    <div class="payment-header">
                        <div class="payment-icon" style="background: rgba(67, 97, 238, 0.1);">
                            <i class="fas fa-university text-primary"></i>
                        </div>
                        <div class="payment-title"><?php echo htmlspecialchars($bank['bank_name']); ?></div>
                    </div>
                    <div class="payment-detail">Account Holder: <?php echo htmlspecialchars($bank['account_holder_name']); ?></div>
                    <div class="payment-detail">Account: ****<?php echo substr($bank['account_number'], -4); ?></div>
                    <?php if (!empty($bank['ifsc_code'])): ?>
                    <div class="payment-detail">IFSC: <?php echo $bank['ifsc_code']; ?></div>
                    <?php endif; ?>
                    <div>
                        <?php if ($bank['is_verified']): ?>
                            <span class="payment-status" style="background: rgba(6, 214, 160, 0.1); color: var(--success);">
                                <i class="fas fa-check-circle me-1"></i> Verified
                            </span>
                        <?php else: ?>
                            <span class="payment-status" style="background: rgba(255, 183, 3, 0.1); color: var(--warning);">
                                <i class="fas fa-clock me-1"></i> Pending
                            </span>
                        <?php endif; ?>
                        <?php if ($bank['is_default']): ?>
                            <span class="payment-status" style="background: rgba(67, 97, 238, 0.1); color: var(--primary);">
                                <i class="fas fa-star me-1"></i> Default
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Mobile Accounts -->
            <?php if (!empty($payment_methods['mobile'])): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-mobile-alt me-2 text-success"></i>
                    Mobile Accounts
                </h5>
                <?php foreach ($payment_methods['mobile'] as $mobile): ?>
                <div class="payment-card mobile mb-3">
                    <div class="payment-header">
                        <div class="payment-icon" style="background: rgba(6, 214, 160, 0.1);">
                            <i class="fas fa-mobile-alt text-success"></i>
                        </div>
                        <div class="payment-title"><?php echo ucfirst($mobile['account_type']); ?></div>
                    </div>
                    <div class="payment-detail">Holder: <?php echo htmlspecialchars($mobile['account_holder_name']); ?></div>
                    <div class="payment-detail">Mobile: ****<?php echo substr($mobile['mobile_number'], -4); ?></div>
                    <?php if (!empty($mobile['cnic_number'])): ?>
                    <div class="payment-detail">CNIC: <?php echo $mobile['cnic_number']; ?></div>
                    <?php endif; ?>
                    <div>
                        <?php if ($mobile['is_verified']): ?>
                            <span class="payment-status" style="background: rgba(6, 214, 160, 0.1); color: var(--success);">
                                <i class="fas fa-check-circle me-1"></i> Verified
                            </span>
                        <?php else: ?>
                            <span class="payment-status" style="background: rgba(255, 183, 3, 0.1); color: var(--warning);">
                                <i class="fas fa-clock me-1"></i> Pending
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column -->
        <div class="col-lg-6">
            <!-- Products -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-box me-2 text-warning"></i>
                    Recent Products
                </h5>
                <?php if (empty($products)): ?>
                    <p class="text-muted text-center py-3">No products added yet</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(substr($product['name'], 0, 30)) . '...'; ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td>
                                        <?php if ($product['stock'] <= 0): ?>
                                            <span class="badge bg-danger">Out</span>
                                        <?php elseif ($product['stock'] < 10): ?>
                                            <span class="badge bg-warning"><?php echo $product['stock']; ?></span>
                                        <?php else: ?>
                                            <?php echo $product['stock']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['approved_status'] == 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($product['approved_status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="products.php?vendor=<?php echo $vendor_id; ?>" class="btn btn-link">View All Products →</a>
                <?php endif; ?>
            </div>

            <!-- Withdrawal History -->
            <div class="info-card">
                <h5>
                    <i class="fas fa-history me-2 text-info"></i>
                    Recent Withdrawals
                </h5>
                <?php if (empty($withdrawals)): ?>
                    <p class="text-muted text-center py-3">No withdrawal history</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($withdrawals as $w): ?>
                                <tr>
                                    <td><?php echo date('d M', strtotime($w['created_at'])); ?></td>
                                    <td>$<?php echo number_format($w['withdrawal_amount'], 2); ?></td>
                                    <td><?php echo ucfirst($w['withdrawal_method']); ?></td>
                                    <td>
                                        <?php if ($w['status'] == 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($w['status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($w['status'] == 'rejected'): ?>
                                            <span class="badge bg-danger">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Processing</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="withdrawals.php?vendor=<?php echo $vendor_id; ?>" class="btn btn-link">View All →</a>
                <?php endif; ?>
            </div>

            <!-- PayPal/Stripe/Cards -->
            <?php if (!empty($payment_methods['paypal']) || !empty($payment_methods['stripe']) || !empty($payment_methods['cards'])): ?>
            <div class="info-card">
                <h5>
                    <i class="fas fa-credit-card me-2 text-danger"></i>
                    Other Payment Methods
                </h5>
                
                <!-- PayPal -->
                <?php if (!empty($payment_methods['paypal'])): ?>
                    <?php foreach ($payment_methods['paypal'] as $paypal): ?>
                    <div class="payment-card paypal mb-3">
                        <div class="payment-header">
                            <div class="payment-icon" style="background: rgba(76, 201, 240, 0.1);">
                                <i class="fab fa-paypal text-info"></i>
                            </div>
                            <div class="payment-title">PayPal</div>
                        </div>
                        <div class="payment-detail">Email: <?php echo htmlspecialchars($paypal['paypal_email']); ?></div>
                        <div class="payment-detail">Holder: <?php echo htmlspecialchars($paypal['account_holder_name']); ?></div>
                        <div>
                            <?php if ($paypal['is_verified']): ?>
                                <span class="payment-status" style="background: rgba(6, 214, 160, 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle me-1"></i> Verified
                                </span>
                            <?php else: ?>
                                <span class="payment-status" style="background: rgba(255, 183, 3, 0.1); color: var(--warning);">
                                    <i class="fas fa-clock me-1"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Stripe -->
                <?php if (!empty($payment_methods['stripe'])): ?>
                    <?php foreach ($payment_methods['stripe'] as $stripe): ?>
                    <div class="payment-card stripe mb-3">
                        <div class="payment-header">
                            <div class="payment-icon" style="background: rgba(255, 183, 3, 0.1);">
                                <i class="fab fa-stripe text-warning"></i>
                            </div>
                            <div class="payment-title">Stripe</div>
                        </div>
                        <div class="payment-detail">Account: <?php echo substr($stripe['stripe_account_id'], 0, 8); ?>...</div>
                        <div class="payment-detail">Email: <?php echo htmlspecialchars($stripe['account_email']); ?></div>
                        <div class="payment-detail">Holder: <?php echo htmlspecialchars($stripe['account_holder_name']); ?></div>
                        <div>
                            <?php if ($stripe['is_verified']): ?>
                                <span class="payment-status" style="background: rgba(6, 214, 160, 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle me-1"></i> Verified
                                </span>
                            <?php else: ?>
                                <span class="payment-status" style="background: rgba(255, 183, 3, 0.1); color: var(--warning);">
                                    <i class="fas fa-clock me-1"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Cards -->
                <?php if (!empty($payment_methods['cards'])): ?>
                    <?php foreach ($payment_methods['cards'] as $card): ?>
                    <div class="payment-card card mb-3">
                        <div class="payment-header">
                            <div class="payment-icon" style="background: rgba(239, 71, 111, 0.1);">
                                <i class="fas fa-credit-card text-danger"></i>
                            </div>
                            <div class="payment-title"><?php echo ucfirst($card['card_type']); ?> Card</div>
                        </div>
                        <div class="payment-detail">Card: **** **** **** <?php echo $card['card_last_four']; ?></div>
                        <div class="payment-detail">Holder: <?php echo htmlspecialchars($card['card_holder_name']); ?></div>
                        <div class="payment-detail">Expires: <?php echo $card['expiry_month']; ?>/<?php echo $card['expiry_year']; ?></div>
                        <div>
                            <?php if ($card['is_verified']): ?>
                                <span class="payment-status" style="background: rgba(6, 214, 160, 0.1); color: var(--success);">
                                    <i class="fas fa-check-circle me-1"></i> Verified
                                </span>
                            <?php else: ?>
                                <span class="payment-status" style="background: rgba(255, 183, 3, 0.1); color: var(--warning);">
                                    <i class="fas fa-clock me-1"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals for actions -->
<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="action/approve-vendor.php">
                <input type="hidden" name="vendor_id" id="approve_vendor_id">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Approve Vendor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this vendor?</p>
                    <p class="text-muted small">The vendor will be able to start selling products immediately.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="action/reject-vendor.php">
                <input type="hidden" name="vendor_id" id="reject_vendor_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>
                        Reject Vendor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject this vendor?</p>
                    <div class="mb-3">
                        <label class="form-label">Reason for rejection</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Verify Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="action/verify-vendor.php">
                <input type="hidden" name="vendor_id" id="verify_vendor_id">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-shield-alt me-2"></i>
                        Verify Vendor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to verify this vendor?</p>
                    <p class="text-muted small">Verified vendors get a trust badge and may have higher withdrawal limits.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Verify Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div class="modal fade" id="suspendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="action/suspend-vendor.php">
                <input type="hidden" name="vendor_id" id="suspend_vendor_id">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Suspend Vendor
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to suspend this vendor?</p>
                    <div class="mb-3">
                        <label class="form-label">Reason for suspension</label>
                        <textarea name="suspension_reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <p class="text-danger small">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        Suspended vendors cannot sell products or withdraw funds.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Suspend Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveVendor(id) {
    document.getElementById('approve_vendor_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectVendor(id) {
    document.getElementById('reject_vendor_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function verifyVendor(id) {
    document.getElementById('verify_vendor_id').value = id;
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
}

function suspendVendor(id) {
    document.getElementById('suspend_vendor_id').value = id;
    new bootstrap.Modal(document.getElementById('suspendModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>