<?php
// admin/vendors/vendors.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Manage Vendors';
require_once '../includes/header.php';

$db = getDB();

// Get filter from URL
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query based on filter - FIXED: removed store_name reference
$query = "
    SELECT u.*, 
           COUNT(DISTINCT p.id) as total_products,
           COUNT(DISTINCT oi.id) as total_sales,
           SUM(oi.quantity * oi.unit_price) as total_revenue,
           (SELECT COUNT(*) FROM vendor_withdrawals WHERE vendor_id = u.id AND status = 'pending') as pending_withdrawals
    FROM users u
    LEFT JOIN products p ON u.id = p.vendor_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.payment_status = 'completed'
    WHERE u.user_type = 'vendor'
";

$params = [];

if ($filter === 'pending') {
    $query .= " AND u.vendor_status = 'pending'";
} elseif ($filter === 'approved') {
    $query .= " AND u.vendor_status = 'approved'";
} elseif ($filter === 'suspended') {
    $query .= " AND u.vendor_status = 'suspended'";
} elseif ($filter === 'verified') {
    $query .= " AND u.vendor_verified = 1";
} elseif ($filter === 'unverified') {
    $query .= " AND u.vendor_verified = 0";
}

// FIXED: removed store_name from search
if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
try {
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor' AND vendor_status = 'pending'");
    $stats['pending'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor' AND vendor_status = 'approved'");
    $stats['approved'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor' AND vendor_status = 'suspended'");
    $stats['suspended'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor' AND vendor_verified = 1");
    $stats['verified'] = $stmt->fetchColumn();
    
} catch(Exception $e) {
    $stats = ['pending' => 0, 'approved' => 0, 'suspended' => 0, 'verified' => 0];
}
?>

<!-- Rest of your HTML remains the same as before -->
<!-- ... -->

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

.vendors-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Header */
.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success), var(--warning), var(--danger));
}

.page-header h1 {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

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
    border: 1px solid rgba(0,0,0,0.03);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-card.pending { border-left: 4px solid var(--warning); }
.stat-card.approved { border-left: 4px solid var(--success); }
.stat-card.suspended { border-left: 4px solid var(--danger); }
.stat-card.verified { border-left: 4px solid var(--info); }

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

/* Filter Tabs */
.filter-tabs {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 500;
    color: #6c757d;
    background: var(--light);
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
}

.filter-tab:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.filter-tab.active {
    background: var(--primary);
    color: white;
}

.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    margin-left: 8px;
    font-size: 12px;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

/* Search Box */
.search-box {
    background: white;
    border-radius: 15px;
    padding: 5px;
    display: flex;
    align-items: center;
    border: 1px solid #edf2f9;
}

.search-box input {
    border: none;
    padding: 10px 15px;
    flex: 1;
    border-radius: 12px;
}

.search-box input:focus {
    outline: none;
}

.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.search-box button:hover {
    background: #3651c4;
}

/* Vendor Card */
.vendor-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
}

.vendor-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.03);
    position: relative;
}

.vendor-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(67, 97, 238, 0.1);
}

.vendor-card.pending { border-top: 4px solid var(--warning); }
.vendor-card.approved { border-top: 4px solid var(--success); }
.vendor-card.suspended { border-top: 4px solid var(--danger); }

.vendor-header {
    padding: 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 1px solid #edf2f9;
    position: relative;
}

.vendor-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--info));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 600;
    color: white;
    margin: 0 auto 15px;
    border: 4px solid white;
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.vendor-name {
    text-align: center;
    margin-bottom: 10px;
}

.vendor-name h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.vendor-name p {
    color: #6c757d;
    font-size: 13px;
    margin-bottom: 0;
}

.vendor-badges {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}

.badge {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
}

.badge-verified { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.badge-pending { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.badge-suspended { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
.badge-approved { background: rgba(67, 97, 238, 0.1); color: var(--primary); }

.vendor-body {
    padding: 20px;
}

.info-row {
    display: flex;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px dashed #edf2f9;
}

.info-row:last-child {
    border-bottom: none;
}

.info-icon {
    width: 30px;
    color: var(--primary);
    font-size: 14px;
}

.info-label {
    width: 100px;
    color: #6c757d;
    font-size: 13px;
}

.info-value {
    flex: 1;
    font-weight: 500;
    color: var(--dark);
}

.vendor-footer {
    padding: 20px;
    background: #f8f9fa;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    border-top: 1px solid #edf2f9;
}

.btn-action {
    padding: 8px 16px;
    border-radius: 10px;
    font-size: 13px;
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

.btn-view {
    background: var(--primary);
    color: white;
}

.btn-verify {
    background: var(--info);
    color: white;
}

.btn-action:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

/* Modal Styles */
.modal-content {
    border-radius: 20px;
    border: none;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary), var(--info));
    color: white;
    border-radius: 20px 20px 0 0;
    padding: 20px 25px;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid #edf2f9;
    padding: 20px 25px;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.vendor-card {
    animation: slideIn 0.5s ease forwards;
}

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }

/* Responsive */
@media (max-width: 768px) {
    .vendors-container {
        padding: 20px;
    }
    
    .vendor-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<div class="vendors-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1>
                    <i class="fas fa-store me-2 text-primary"></i>
                    Manage Vendors
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-users me-2"></i>
                    Total <?php echo count($vendors); ?> vendors found
                </p>
            </div>
            <a href="add-vendor.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>
                Add New Vendor
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                <i class="fas fa-clock text-warning"></i>
            </div>
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                <i class="fas fa-check-circle text-success"></i>
            </div>
            <div class="stat-value"><?php echo $stats['approved']; ?></div>
            <div class="stat-label">Approved Vendors</div>
        </div>
        <div class="stat-card suspended">
            <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                <i class="fas fa-ban text-danger"></i>
            </div>
            <div class="stat-value"><?php echo $stats['suspended']; ?></div>
            <div class="stat-label">Suspended</div>
        </div>
        <div class="stat-card verified">
            <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                <i class="fas fa-shield-alt text-info"></i>
            </div>
            <div class="stat-value"><?php echo $stats['verified']; ?></div>
            <div class="stat-label">Verified Vendors</div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
        <div class="filter-tabs">
            <a href="vendors.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list me-1"></i> All
            </a>
            <a href="vendors.php?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock me-1"></i> Pending
                <span class="count"><?php echo $stats['pending']; ?></span>
            </a>
            <a href="vendors.php?filter=approved" class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle me-1"></i> Approved
                <span class="count"><?php echo $stats['approved']; ?></span>
            </a>
            <a href="vendors.php?filter=suspended" class="filter-tab <?php echo $filter === 'suspended' ? 'active' : ''; ?>">
                <i class="fas fa-ban me-1"></i> Suspended
                <span class="count"><?php echo $stats['suspended']; ?></span>
            </a>
            <a href="vendors.php?filter=verified" class="filter-tab <?php echo $filter === 'verified' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt me-1"></i> Verified
                <span class="count"><?php echo $stats['verified']; ?></span>
            </a>
        </div>

        <form method="GET" class="search-box" style="min-width: 300px;">
            <input type="text" name="search" placeholder="Search vendors..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">
                <i class="fas fa-search me-1"></i> Search
            </button>
        </form>
    </div>

    <!-- Vendors Grid -->
    <?php if (empty($vendors)): ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-store fa-5x text-muted opacity-25"></i>
            </div>
            <h4 class="text-muted mb-3">No vendors found</h4>
            <?php if ($filter === 'pending'): ?>
                <p class="text-muted">There are no pending vendor requests at the moment.</p>
            <?php else: ?>
                <p class="text-muted">Get started by adding your first vendor.</p>
                <a href="add-vendor.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add New Vendor
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="vendor-grid">
            <?php foreach ($vendors as $index => $vendor): ?>
                <div class="vendor-card <?php echo $vendor['vendor_status']; ?> animate-slide-in delay-<?php echo ($index % 3) + 1; ?>">
                    <div class="vendor-header">
                        <div class="vendor-avatar">
                            <?php echo strtoupper(substr($vendor['full_name'] ?? $vendor['username'], 0, 1)); ?>
                        </div>
                        <div class="vendor-name">
                            <h4><?php echo htmlspecialchars($vendor['full_name'] ?? $vendor['username']); ?></h4>
                            <p>
                                <i class="fas fa-envelope me-1"></i>
                                <?php echo htmlspecialchars($vendor['email']); ?>
                            </p>
                        </div>
                        <div class="vendor-badges">
                            <?php if ($vendor['vendor_status'] == 'pending'): ?>
                                <span class="badge badge-pending">
                                    <i class="fas fa-clock me-1"></i> Pending
                                </span>
                            <?php elseif ($vendor['vendor_status'] == 'approved'): ?>
                                <span class="badge badge-approved">
                                    <i class="fas fa-check-circle me-1"></i> Approved
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
                            <?php else: ?>
                                <span class="badge badge-pending">
                                    <i class="fas fa-clock me-1"></i> Unverified
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="vendor-body">
                        <div class="info-row">
                            <span class="info-icon"><i class="fas fa-user"></i></span>
                            <span class="info-label">Username</span>
                            <span class="info-value">@<?php echo htmlspecialchars($vendor['username']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-icon"><i class="fas fa-phone"></i></span>
                            <span class="info-label">Phone</span>
                            <span class="info-value"><?php echo htmlspecialchars($vendor['phone'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-icon"><i class="fas fa-box"></i></span>
                            <span class="info-label">Products</span>
                            <span class="info-value"><?php echo $vendor['total_products']; ?> items</span>
                        </div>
                        <div class="info-row">
                            <span class="info-icon"><i class="fas fa-shopping-cart"></i></span>
                            <span class="info-label">Sales</span>
                            <span class="info-value"><?php echo $vendor['total_sales']; ?> orders</span>
                        </div>
                        <div class="info-row">
                            <span class="info-icon"><i class="fas fa-dollar-sign"></i></span>
                            <span class="info-label">Revenue</span>
                            <span class="info-value text-success">$<?php echo number_format($vendor['total_revenue'] ?? 0, 2); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-icon"><i class="fas fa-calendar"></i></span>
                            <span class="info-label">Joined</span>
                            <span class="info-value"><?php echo date('M d, Y', strtotime($vendor['created_at'])); ?></span>
                        </div>
                        <?php if ($vendor['pending_withdrawals'] > 0): ?>
                            <div class="info-row">
                                <span class="info-icon"><i class="fas fa-money-bill-wave text-warning"></i></span>
                                <span class="info-label">Withdrawals</span>
                                <span class="info-value">
                                    <span class="badge bg-warning">
                                        <?php echo $vendor['pending_withdrawals']; ?> pending
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="vendor-footer">
                        <?php if ($vendor['vendor_status'] == 'pending'): ?>
                            <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                            <button class="btn-action btn-reject" onclick="rejectVendor(<?php echo $vendor['id']; ?>)">
                                <i class="fas fa-times me-1"></i> Reject
                            </button>
                        <?php elseif ($vendor['vendor_status'] == 'approved'): ?>
                            <?php if (!$vendor['vendor_verified']): ?>
                                <button class="btn-action btn-verify" onclick="verifyVendor(<?php echo $vendor['id']; ?>)">
                                    <i class="fas fa-shield-alt me-1"></i> Verify
                                </button>
                            <?php endif; ?>
                            <button class="btn-action btn-suspend" onclick="suspendVendor(<?php echo $vendor['id']; ?>)">
                                <i class="fas fa-ban me-1"></i> Suspend
                            </button>
                        <?php elseif ($vendor['vendor_status'] == 'suspended'): ?>
                            <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                <i class="fas fa-check me-1"></i> Reactivate
                            </button>
                        <?php endif; ?>
                        <a href="view-vendor.php?id=<?php echo $vendor['id']; ?>" class="btn-action btn-view">
                            <i class="fas fa-eye me-1"></i> View
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>
                    Approve Vendor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this vendor?</p>
                <p class="text-muted small">The vendor will be able to start selling products immediately.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="action/approve-vendor.php" id="approveForm">
                    <input type="hidden" name="vendor_id" id="approve_vendor_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Vendor</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
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
                    <textarea name="rejection_reason" class="form-control" rows="3" form="rejectForm" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST" action="action/reject-vendor.php" id="rejectForm">
                    <input type="hidden" name="vendor_id" id="reject_vendor_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Vendor</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Verify Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
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
                <form method="POST" action="action/verify-vendor.php" id="verifyForm">
                    <input type="hidden" name="vendor_id" id="verify_vendor_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Verify Vendor</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div class="modal fade" id="suspendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
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
                    <textarea name="suspension_reason" class="form-control" rows="3" form="suspendForm" required></textarea>
                </div>
                <p class="text-danger small">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    Suspended vendors cannot sell products or withdraw funds.
                </p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="action/suspend-vendor.php" id="suspendForm">
                    <input type="hidden" name="vendor_id" id="suspend_vendor_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Suspend Vendor</button>
                </form>
            </div>
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

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>