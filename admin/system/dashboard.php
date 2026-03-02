<?php
// admin/system/dashboard.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

// Special check for system administrator
requireSystemAdmin();

$page_title = 'System Administrator Dashboard';
require_once dirname(__DIR__) . '/includes/header.php';

$db = getDB();
$admin_id = $_SESSION['user_id'];

// Get system stats with error handling
$stats = [
    'commissions' => ['total_commissions' => 0, 'total_transactions' => 0],
    'withdrawals' => ['pending_withdrawals' => 0, 'total_pending' => 0],
    'active_accounts' => 0,
    'vendors_with_balance' => 0
];

// Try to get stats, but don't fail if tables don't exist yet
try {
    $result = $db->query("SELECT COALESCE(SUM(commission_amount), 0) as total FROM vendor_commissions")->fetch();
    $stats['commissions']['total_commissions'] = $result['total'];
} catch(Exception $e) {
    // Table might not exist yet
}

try {
    $result = $db->query("SELECT COUNT(*) as count FROM vendor_withdrawal_requests WHERE status = 'pending'")->fetch();
    $stats['withdrawals']['pending_withdrawals'] = $result['count'];
} catch(Exception $e) {
    // Table might not exist yet
}

try {
    $stats['active_accounts'] = $db->query("SELECT COUNT(*) FROM admin_accounts WHERE is_active = 1")->fetchColumn();
} catch(Exception $e) {
    // Table might not exist yet
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

.system-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    color: white;
}

.super-admin-badge {
    background: rgba(255,215,0,0.2);
    color: gold;
    padding: 5px 15px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
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

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.action-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    border: 1px solid transparent;
    display: block;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
    border-color: var(--primary);
    text-decoration: none;
}

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 20px;
}

.action-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.action-desc {
    color: #6c757d;
    font-size: 14px;
    margin-bottom: 15px;
}

.action-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
    background: var(--light);
    color: var(--primary);
}
</style>

<div class="system-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-2">
                    <i class="fas fa-crown me-2"></i>
                    System Administrator Dashboard
                </h1>
                <p class="mb-0 opacity-75">
                    <i class="fas fa-shield-alt me-2"></i>
                    Super Admin Access • Full System Control
                </p>
            </div>
            <div>
                <span class="super-admin-badge">
                    <i class="fas fa-star me-1"></i> SUPER ADMIN
                </span>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-value">$<?php echo number_format($stats['commissions']['total_commissions'] ?? 0, 2); ?></div>
            <div class="stat-label">Total Commissions</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo $stats['withdrawals']['pending_withdrawals'] ?? 0; ?></div>
            <div class="stat-label">Pending Withdrawals</div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="stat-value"><?php echo $stats['active_accounts'] ?? 0; ?></div>
            <div class="stat-label">Active Accounts</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <h4 class="mb-3">
        <i class="fas fa-bolt me-2 text-primary"></i>
        System Actions
    </h4>

    <div class="action-grid">
        <a href="admin-accounts.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <h5 class="action-title">Manage Admin Accounts</h5>
            <p class="action-desc">Add/edit payment accounts (PayPal, Stripe, Easypaisa, etc.)</p>
            <span class="action-badge">
                <i class="fas fa-plus-circle me-1"></i> Configure
            </span>
        </a>

        <a href="../vendors/products.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-box"></i>
            </div>
            <h5 class="action-title">Products</h5>
            <p class="action-desc">Manage all vendor products</p>
            <span class="action-badge">
                <i class="fas fa-eye me-1"></i> View
            </span>
        </a>

        <a href="../vendors/index.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-store"></i>
            </div>
            <h5 class="action-title">Vendors</h5>
            <p class="action-desc">Manage all vendors</p>
            <span class="action-badge">
                <i class="fas fa-users me-1"></i> View
            </span>
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>