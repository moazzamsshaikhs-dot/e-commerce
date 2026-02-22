<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirectToDashboard();
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();

    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        redirect('../vendor/dashboard.php');
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect('../vendor/dashboard.php');
}

$page_title = 'Earnings';
require_once '../../includes/header.php';

// Get vendor earnings
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];

    // Get total earnings summary
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(vendor_amount), 0) as total_earnings,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN vendor_amount ELSE 0 END), 0) as paid_earnings,
            COALESCE(SUM(CASE WHEN status IN ('pending', 'processing') THEN vendor_amount ELSE 0 END), 0) as pending_earnings,
            COUNT(*) as total_transactions
        FROM vendor_earnings 
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $earnings_summary = $stmt->fetch();

    // Get monthly earnings
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(vendor_amount) as monthly_earnings,
            COUNT(*) as transactions
        FROM vendor_earnings 
        WHERE vendor_id = ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $stmt->execute([$vendor_id]);
    $monthly_earnings = $stmt->fetchAll();

    // Get recent earnings
    $stmt = $db->prepare("
        SELECT ve.*, o.order_number, p.name as product_name, u.username as customer_name
        FROM vendor_earnings ve
        JOIN orders o ON ve.order_id = o.id
        JOIN products p ON ve.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE ve.vendor_id = ?
        ORDER BY ve.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $recent_earnings = $stmt->fetchAll();

    // Get withdrawal history
    $stmt = $db->prepare("
        SELECT * FROM vendor_withdrawals 
        WHERE vendor_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$vendor_id]);
    $withdrawals = $stmt->fetchAll();

    // ===== FIXED: Get vendor's category and commission rate with better error handling =====

    // First, check what tables exist
    $tables = $db->query("SHOW TABLES");
    $tables_list = $tables->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('vendor_categories', $tables_list)) {
        // Create the table if it doesn't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS `vendor_categories` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(100) NOT NULL,
                `slug` varchar(100) NOT NULL,
                `description` text DEFAULT NULL,
                `commission_rate` decimal(5,2) DEFAULT 10.00,
                `is_active` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `slug` (`slug`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        // Insert default categories
        $db->exec("
            INSERT INTO `vendor_categories` (`name`, `slug`, `commission_rate`, `is_active`) VALUES
            ('Electronics', 'electronics', 10.00, 1),
            ('Fashion', 'fashion', 8.00, 1),
            ('Home & Living', 'home-living', 7.00, 1),
            ('Books', 'books', 5.00, 1),
            ('Sports', 'sports', 6.00, 1),
            ('Beauty', 'beauty', 9.00, 1),
            ('Food', 'food', 4.00, 1)
        ");
    }

    // Get vendor's category and commission rate with COLLATE fix
// Get vendor's category and commission rate with BINARY collation
$stmt = $db->prepare("
    SELECT u.vendor_category, 
           vc.name as category_name, 
           vc.commission_rate 
    FROM users u
    LEFT JOIN vendor_categories vc ON u.vendor_category COLLATE utf8mb4_unicode_ci = vc.slug COLLATE utf8mb4_unicode_ci
    WHERE u.id = ?
");
$stmt->execute([$vendor_id]);
$vendor_data = $stmt->fetch();

    // If no data found, set defaults
    if (!$vendor_data) {
        $vendor_data = [
            'vendor_category' => 'general',
            'category_name' => 'General',
            'commission_rate' => 10.00
        ];
    }

    // Ensure commission_rate is a float
    $commission_rate = floatval($vendor_data['commission_rate']);
    $vendor_category = $vendor_data['category_name'];
    $vendor_category_slug = $vendor_data['vendor_category'] ?? 'general';
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading earnings data: ' . $e->getMessage();
    $earnings_summary = ['total_earnings' => 0, 'paid_earnings' => 0, 'pending_earnings' => 0, 'total_transactions' => 0];
    $monthly_earnings = [];
    $recent_earnings = [];
    $withdrawals = [];
    $commission_rate = 10.00;
    $vendor_category = 'General';
    $vendor_category_slug = 'general';
}
?>

<!-- Rest of your HTML remains the same, but update the Commission Info section -->

<div class="dashboard-container">
    <?php include '../../includes/vendor-sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">My Earnings</h1>
                    <p class="text-muted mb-0">Track your earnings and withdrawals</p>
                </div>
                <div class="d-flex gap-3">
                    <?php if ($earnings_summary['pending_earnings'] > 0): ?>
                        <a href="withdraw.php" class="btn btn-warning">
                            <i class="fas fa-wallet me-2"></i> Withdraw Now
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>admin/vendors/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Earnings Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Earnings</h6>
                                <h2 class="mb-0">$<?php echo number_format($earnings_summary['total_earnings'], 2); ?></h2>
                            </div>
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-money-bill-wave text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Paid Earnings</h6>
                                <h2 class="mb-0">$<?php echo number_format($earnings_summary['paid_earnings'], 2); ?></h2>
                            </div>
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-check-circle text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Pending Earnings</h6>
                                <h2 class="mb-0">$<?php echo number_format($earnings_summary['pending_earnings'], 2); ?></h2>
                            </div>
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-clock text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Transactions</h6>
                                <h2 class="mb-0"><?php echo $earnings_summary['total_transactions']; ?></h2>
                            </div>
                            <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-exchange-alt text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Earnings and Withdrawals -->
        <div class="row g-4">
            <!-- Recent Earnings -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Earnings</h5>
                        <a href="history.php" class="btn btn-sm btn-outline-primary">
                            View All <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_earnings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No earnings yet</p>
                                <p class="text-muted small">Earnings will appear here when customers purchase your products</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Order #</th>
                                            <th>Product</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_earnings as $earning): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($earning['created_at'])); ?></td>
                                                <td>
                                                    <a href="../orders/view.php?id=<?php echo $earning['order_id']; ?>" class="text-decoration-none">
                                                        #<?php echo $earning['order_number']; ?>
                                                    </a>
                                                </td>
                                                <td><?php echo substr($earning['product_name'], 0, 30); ?>...</td>
                                                <td>@<?php echo $earning['customer_name']; ?></td>
                                                <td class="fw-bold">$<?php echo number_format($earning['vendor_amount'], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $status_color = 'secondary';
                                                    if ($earning['status'] == 'paid') $status_color = 'success';
                                                    if ($earning['status'] == 'pending') $status_color = 'warning';
                                                    if ($earning['status'] == 'processing') $status_color = 'info';
                                                    if ($earning['status'] == 'cancelled') $status_color = 'danger';
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_color; ?>">
                                                        <?php echo ucfirst($earning['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Monthly Earnings Chart -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Monthly Earnings (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($monthly_earnings)): ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No monthly earnings data available</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Month</th>
                                            <th>Earnings</th>
                                            <th>Transactions</th>
                                            <th>Average</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($monthly_earnings as $month): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                                                <td class="text-success fw-bold">$<?php echo number_format($month['monthly_earnings'], 2); ?></td>
                                                <td><?php echo $month['transactions']; ?> transactions</td>
                                                <td>$<?php echo number_format($month['monthly_earnings'] / max($month['transactions'], 1), 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Withdrawals and Info -->
            <div class="col-lg-4">
                <!-- Withdrawal History -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Recent Withdrawals</h5>
                        <a href="withdraw.php" class="btn btn-sm btn-outline-primary">
                            Withdraw
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($withdrawals)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-wallet fa-2x text-muted mb-2"></i>
                                <p class="text-muted">No withdrawals yet</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($withdrawals as $withdrawal): ?>
                                    <div class="list-group-item border-0 px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">$<?php echo number_format($withdrawal['withdrawal_amount'], 2); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($withdrawal['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <?php
                                                $status_color = 'secondary';
                                                if ($withdrawal['status'] == 'completed') $status_color = 'success';
                                                if ($withdrawal['status'] == 'pending') $status_color = 'warning';
                                                if ($withdrawal['status'] == 'processing') $status_color = 'info';
                                                if ($withdrawal['status'] == 'rejected') $status_color = 'danger';
                                                ?>
                                                <span class="badge bg-<?php echo $status_color; ?>">
                                                    <?php echo ucfirst($withdrawal['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-<?php
                                                                echo $withdrawal['withdrawal_method'] == 'bank' ? 'university' : ($withdrawal['withdrawal_method'] == 'paypal' ? 'paypal' : ($withdrawal['withdrawal_method'] == 'stripe' ? 'credit-card' : 'money-bill'));
                                                                ?> me-1"></i>
                                            <?php echo ucfirst($withdrawal['withdrawal_method']); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Commission Info - FIXED with database values -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-percentage me-2 text-primary"></i> Commission Info
                        </h5>

                        <div class="alert alert-info mb-3">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-2x me-3"></i>
                                <div>
                                    <strong>Your Commission Rate</strong>
                                    <h3 class="mb-0 text-primary"><?php echo number_format($commission_rate, 2); ?>%</h3>
                                </div>
                            </div>
                        </div>

                        <!-- Show vendor category -->
                        <div class="mb-3">
                            <div class="row">
                                <div class="col-12">
                                    <small class="text-muted">Your Category:</small>
                                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($vendor_category); ?></p>
                                    <small class="text-muted">(Slug: <?php echo htmlspecialchars($vendor_category_slug); ?>)</small>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted small">
                            For every sale, you receive <strong class="text-success"><?php echo number_format(100 - $commission_rate, 2); ?>%</strong> of the product price.
                            <strong class="text-primary"><?php echo number_format($commission_rate, 2); ?>%</strong> goes to platform commission.
                        </p>

                        <!-- Calculate sample earning -->
                        <?php
                        $sample_price = 100;
                        $your_share = $sample_price * ((100 - $commission_rate) / 100);
                        $platform_share = $sample_price * ($commission_rate / 100);
                        ?>
                        <div class="bg-light p-3 rounded mt-3">
                            <h6 class="mb-2">Example Calculation:</h6>
                            <div class="row text-center">
                                <div class="col-6">
                                    <small class="text-muted">Product Price</small>
                                    <h5 class="mb-0">$<?php echo $sample_price; ?></h5>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">You Earn</small>
                                    <h5 class="text-success mb-0">$<?php echo number_format($your_share, 2); ?></h5>
                                </div>
                            </div>
                            <div class="progress mt-2 p-2" style="height: 30px;">
                                <div class="progress-bar bg-success p-2" style="width: <?php echo (100 - $commission_rate); ?>%">
                                    <?php echo number_format(100 - $commission_rate, 2); ?>%
                                </div>
                                <div class="progress-bar bg-primary p-2" style="width: <?php echo $commission_rate; ?>%">
                                    <?php echo number_format($commission_rate, 2); ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-success">Your Share: $<?php echo number_format($your_share, 2); ?></small>
                                <small class="text-primary">Commission: $<?php echo number_format($platform_share, 2); ?></small>
                            </div>
                        </div>

                        <!-- Debug info (remove after fixing) -->
                        <?php if (isset($_GET['debug'])): ?>
                            <div class="mt-3 p-2 bg-dark text-white small">
                                <strong>Debug Info:</strong><br>
                                Vendor Category Slug: <?php echo $vendor_category_slug; ?><br>
                                Category Name: <?php echo $vendor_category; ?><br>
                                Commission Rate: <?php echo $commission_rate; ?>%
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Withdrawal Eligibility -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-gem me-2 text-warning"></i> Withdrawal Rules
                        </h5>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Minimum withdrawal: $50
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Processing time: 3-5 business days
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Available payment methods: Bank, PayPal
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                30-day holding period for new orders
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-4 mt-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-check fa-2x text-primary mb-3"></i>
                        <h5>Next Payout</h5>
                        <p class="text-muted small mb-3">15th of every month</p>
                        <a href="schedule.php" class="btn btn-outline-primary btn-sm">View Schedule</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-file-invoice-dollar fa-2x text-success mb-3"></i>
                        <h5>Tax Documents</h5>
                        <p class="text-muted small mb-3">Download your tax forms</p>
                        <a href="tax.php" class="btn btn-outline-success btn-sm">View Documents</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-question-circle fa-2x text-info mb-3"></i>
                        <h5>Need Help?</h5>
                        <p class="text-muted small mb-3">Earnings related questions</p>
                        <a href="../help/support.php" class="btn btn-outline-info btn-sm">Get Help</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Earnings Alert -->
        <?php if ($earnings_summary['pending_earnings'] >= 50): ?>
            <div class="card border-0 shadow-sm mt-4 border-start border-5 border-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1">
                                <i class="fas fa-money-check-alt me-2 text-warning"></i>
                                Ready to Withdraw!
                            </h5>
                            <p class="text-muted mb-0">
                                You have $<?php echo number_format($earnings_summary['pending_earnings'], 2); ?> available for withdrawal.
                            </p>
                        </div>
                        <a href="withdraw.php" class="btn btn-warning px-4">
                            <i class="fas fa-wallet me-2"></i> Withdraw Now
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif ($earnings_summary['pending_earnings'] > 0 && $earnings_summary['pending_earnings'] < 50): ?>
            <div class="card border-0 shadow-sm mt-4 border-start border-5 border-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold mb-1">
                                <i class="fas fa-clock me-2 text-info"></i>
                                Almost There!
                            </h5>
                            <p class="text-muted mb-0">
                                You have $<?php echo number_format($earnings_summary['pending_earnings'], 2); ?> pending.
                                Need $<?php echo number_format(50 - $earnings_summary['pending_earnings'], 2); ?> more to withdraw.
                            </p>
                        </div>
                        <a href="../../vendor/dashboard.php" class="btn btn-outline-info">
                            <i class="fas fa-chart-line me-2"></i> Boost Sales
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<style>
    .card.border-start {
        border-left-width: 5px !important;
    }

    .avatar-sm {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .table th {
        background: #f8f9fa;
        font-weight: 600;
    }

    .list-group-item {
        border-left: none;
        border-right: none;
    }

    .list-group-item:first-child {
        border-top: none;
    }
</style>

<script>
    // Auto-close alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Animate numbers on scroll
    function animateNumbers() {
        const counters = document.querySelectorAll('.counter');
        counters.forEach(counter => {
            const target = parseInt(counter.getAttribute('data-target'));
            const increment = target / 100;
            let current = 0;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    counter.textContent = target;
                    clearInterval(timer);
                } else {
                    counter.textContent = Math.floor(current);
                }
            }, 20);
        });
    }

    // Trigger animation when in view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateNumbers();
                observer.unobserve(entry.target);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const counterElements = document.querySelectorAll('.counter');
        counterElements.forEach(el => observer.observe(el));
    });
</script>

<?php require_once '../../includes/footer.php'; ?>