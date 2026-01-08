<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . '../index.php');
}

$page_title = 'Payment Reports';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get date range
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Get payment statistics
    $stats_sql = "SELECT 
                    COUNT(*) as total_payments,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    MIN(amount) as min_amount,
                    MAX(amount) as max_amount,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded_count
                  FROM payments
                  WHERE DATE(created_at) BETWEEN ? AND ?";
    
    $stmt = $db->prepare($stats_sql);
    $stmt->execute([$start_date, $end_date]);
    $stats = $stmt->fetch();
    
    // Get daily payments for chart
    $daily_sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as payment_count,
                    SUM(amount) as total_amount
                  FROM payments
                  WHERE DATE(created_at) BETWEEN ? AND ?
                  GROUP BY DATE(created_at)
                  ORDER BY date";
    
    $stmt = $db->prepare($daily_sql);
    $stmt->execute([$start_date, $end_date]);
    $daily_payments = $stmt->fetchAll();
    
    // Get payment methods distribution
    $methods_sql = "SELECT 
                      payment_method,
                      COUNT(*) as count,
                      SUM(amount) as total_amount
                    FROM payments
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY payment_method
                    ORDER BY total_amount DESC";
    
    $stmt = $db->prepare($methods_sql);
    $stmt->execute([$start_date, $end_date]);
    $methods_distribution = $stmt->fetchAll();
    
    // Get status distribution
    $status_sql = "SELECT 
                     status,
                     COUNT(*) as count,
                     SUM(amount) as total_amount
                   FROM payments
                   WHERE DATE(created_at) BETWEEN ? AND ?
                   GROUP BY status
                   ORDER BY count DESC";
    
    $stmt = $db->prepare($status_sql);
    $stmt->execute([$start_date, $end_date]);
    $status_distribution = $stmt->fetchAll();
    
    // Get top customers
    $top_customers_sql = "SELECT 
                            u.full_name,
                            u.email,
                            COUNT(p.id) as payment_count,
                            SUM(p.amount) as total_spent
                          FROM payments p
                          LEFT JOIN users u ON p.user_id = u.id
                          WHERE DATE(p.created_at) BETWEEN ? AND ?
                          GROUP BY p.user_id
                          ORDER BY total_spent DESC
                          LIMIT 10";
    
    $stmt = $db->prepare($top_customers_sql);
    $stmt->execute([$start_date, $end_date]);
    $top_customers = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading reports: ' . $e->getMessage();
    $stats = [];
    $daily_payments = [];
    $methods_distribution = [];
    $status_distribution = [];
    $top_customers = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Payment Reports</h1>
                <p class="text-muted mb-0">Analytics and insights for payments</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Payments
                </a>
            </div>
        </div>
        
        <!-- Date Range Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" 
                               name="start_date" 
                               class="form-control" 
                               value="<?php echo $start_date; ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" 
                               name="end_date" 
                               class="form-control" 
                               value="<?php echo $end_date; ?>"
                               required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <a href="payment-reports.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-2"></i> Reset
                        </a>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <a href="export-payments.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                           class="btn btn-success w-100">
                            <i class="fas fa-file-export me-2"></i> Export
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Payments
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo number_format($stats['total_payments'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-credit-card fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Amount
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?php echo number_format($stats['total_amount'] ?? 0, 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Avg. Payment
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?php echo number_format($stats['avg_amount'] ?? 0, 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Completed Amount
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    $<?php echo number_format($stats['completed_amount'] ?? 0, 2); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Daily Payments Chart -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Daily Payments Trend</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area">
                            <canvas id="dailyPaymentsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Methods Distribution -->
            <div class="col-xl-4 col-lg-5">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Payment Methods</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4">
                            <canvas id="paymentMethodsChart"></canvas>
                        </div>
                        <div class="mt-4 text-center small">
                            <?php foreach($methods_distribution as $method): ?>
                            <span class="mr-2">
                                <i class="fas fa-circle" style="color: #<?php echo substr(md5($method['payment_method']), 0, 6); ?>"></i>
                                <?php echo ucfirst($method['payment_method']); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status Distribution -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Payment Status Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Count</th>
                                        <th>Amount</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($status_distribution as $status): 
                                        $percentage = ($status['count'] / ($stats['total_payments'] ?: 1)) * 100;
                                        $status_color = match($status['status']) {
                                            'completed' => 'success',
                                            'pending' => 'warning',
                                            'failed' => 'danger',
                                            'refunded' => 'info',
                                            default => 'secondary'
                                        };
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $status_color; ?>">
                                                <?php echo ucfirst($status['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo number_format($status['count']); ?></td>
                                        <td>$<?php echo number_format($status['total_amount'], 2); ?></td>
                                        <td>
                                            <div class="progress">
                                                <div class="progress-bar bg-<?php echo $status_color; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $percentage; ?>%"
                                                     aria-valuenow="<?php echo $percentage; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?php echo round($percentage, 1); ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Customers -->
            <div class="col-lg-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Top Customers</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Payments</th>
                                        <th>Total Spent</th>
                                        <th>Avg. Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($top_customers as $customer): 
                                        $avg_spent = $customer['total_spent'] / ($customer['payment_count'] ?: 1);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($customer['full_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                        </td>
                                        <td class="text-center"><?php echo number_format($customer['payment_count']); ?></td>
                                        <td class="text-success">$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                        <td>$<?php echo number_format($avg_spent, 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Daily Payments Chart
const dailyLabels = <?php echo json_encode(array_column($daily_payments, 'date')); ?>;
const dailyAmounts = <?php echo json_encode(array_column($daily_payments, 'total_amount')); ?>;
const dailyCounts = <?php echo json_encode(array_column($daily_payments, 'payment_count')); ?>;

const dailyCtx = document.getElementById('dailyPaymentsChart').getContext('2d');
const dailyPaymentsChart = new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Amount ($)',
            data: dailyAmounts,
            backgroundColor: 'rgba(78, 115, 223, 0.05)',
            borderColor: 'rgba(78, 115, 223, 1)',
            pointRadius: 3,
            pointBackgroundColor: 'rgba(78, 115, 223, 1)',
            pointBorderColor: 'rgba(78, 115, 223, 1)',
            pointHoverRadius: 5,
            pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
            pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
            pointHitRadius: 10,
            pointBorderWidth: 2,
            tension: 0.3
        }, {
            label: 'Count',
            data: dailyCounts,
            backgroundColor: 'rgba(28, 200, 138, 0.05)',
            borderColor: 'rgba(28, 200, 138, 1)',
            pointRadius: 3,
            pointBackgroundColor: 'rgba(28, 200, 138, 1)',
            pointBorderColor: 'rgba(28, 200, 138, 1)',
            pointHoverRadius: 5,
            pointHoverBackgroundColor: 'rgba(28, 200, 138, 1)',
            pointHoverBorderColor: 'rgba(28, 200, 138, 1)',
            pointHitRadius: 10,
            pointBorderWidth: 2,
            tension: 0.3
        }]
    },
    options: {
        maintainAspectRatio: false,
        responsive: true,
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: true,
                position: 'top'
            },
            tooltip: {
                mode: 'index',
                intersect: false
            }
        }
    }
});

// Payment Methods Chart
const methodLabels = <?php echo json_encode(array_column($methods_distribution, 'payment_method')); ?>;
const methodData = <?php echo json_encode(array_column($methods_distribution, 'total_amount')); ?>;
const methodColors = methodLabels.map(label => '#' + label.substring(0, 6).padEnd(6, '0'));

const methodsCtx = document.getElementById('paymentMethodsChart').getContext('2d');
const paymentMethodsChart = new Chart(methodsCtx, {
    type: 'doughnut',
    data: {
        labels: methodLabels.map(label => ucfirst(label)),
        datasets: [{
            data: methodData,
            backgroundColor: methodColors,
            hoverBackgroundColor: methodColors.map(color => color + 'CC'),
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }]
    },
    options: {
        maintainAspectRatio: false,
        responsive: true,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: $${value.toFixed(2)} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Helper function to capitalize first letter
function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
</script>

<?php require_once '../includes/footer.php'; ?>