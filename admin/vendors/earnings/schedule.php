<?php
// schedule.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
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

// Get payout schedule and upcoming payouts
try {
    $db = getDB();
    
    // Get pending earnings for next payout
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(vendor_amount), 0) as pending_earnings 
        FROM vendor_earnings 
        WHERE vendor_id = ? AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$vendor_id]);
    $pending_earnings = $stmt->fetch()['pending_earnings'];
    
    // Calculate next payout date (15th of current/next month)
    $today = new DateTime();
    $current_month = $today->format('Y-m');
    $next_month = $today->modify('first day of next month')->format('Y-m');
    
    $next_payout_date = new DateTime($next_month . '-15');
    if ($today->format('d') <= 15) {
        $next_payout_date = new DateTime($current_month . '-15');
        if ($today->format('d') > 15) {
            $next_payout_date->modify('first day of next month')->setDate(
                $next_payout_date->format('Y'),
                $next_payout_date->format('m'),
                15
            );
        }
    }
    
    // Get recent payouts
    $stmt = $db->prepare("
        SELECT * FROM vendor_withdrawals 
        WHERE vendor_id = ? AND status = 'completed'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$vendor_id]);
    $recent_payouts = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading payout data: ' . $e->getMessage();
    $pending_earnings = 0;
    $recent_payouts = [];
    $next_payout_date = new DateTime('first day of next month');
    $next_payout_date->setDate($next_payout_date->format('Y'), $next_payout_date->format('m'), 15);
}

$page_title = 'Payout Schedule';
include_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <?php 
    $sidebar_path = '../../includes/vendor-sidebar.php';
    if (file_exists($sidebar_path)) {
        include_once $sidebar_path;
    }
    ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Payout Schedule</h1>
                    <p class="text-muted mb-0">View your payout dates and schedule</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="earnings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Earnings
                    </a>
                    <?php if ($pending_earnings >= 50): ?>
                        <a href="withdraw.php" class="btn btn-warning">
                            <i class="fas fa-wallet me-2"></i> Withdraw Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Next Payout Countdown -->
        <div class="card border-0 shadow-sm schedule-card mb-4 border-start border-5 border-primary">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="fw-bold text-primary mb-2">
                            <i class="fas fa-calendar-check me-2"></i>
                            Next Payout Date
                        </h4>
                        <h2 class="display-6 fw-bold mb-3"><?php echo $next_payout_date->format('F d, Y'); ?></h2>
                        <div class="countdown-timer text-primary mb-3" id="countdownTimer">
                            <!-- Countdown will be filled by JavaScript -->
                        </div>
                        <p class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Payouts are processed on the 15th of every month for earnings accumulated until the last day of previous month.
                        </p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px;">
                            <i class="fas fa-money-check-alt fa-4x text-primary"></i>
                        </div>
                        <div class="mt-3">
                            <h5 class="fw-bold">Pending for Payout</h5>
                            <h3 class="text-warning fw-bold">$<?php echo number_format($pending_earnings, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Payout Schedule -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm schedule-card">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                            2024 Payout Schedule
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php
                            // Generate schedule for next 6 months
                            $schedule_months = 6;
                            $current_date = new DateTime();
                            
                            for ($i = -1; $i < $schedule_months; $i++):
                                $month_date = new DateTime();
                                if ($i >= 0) {
                                    $month_date->modify("+$i months");
                                }
                                $payout_date = new DateTime($month_date->format('Y-m-15'));
                                $month_name = $payout_date->format('F Y');
                                
                                // Determine status
                                $status = 'future';
                                $status_class = 'future';
                                $status_text = 'Upcoming';
                                
                                if ($i == -1) {
                                    $status = 'past';
                                    $status_class = 'past';
                                    $status_text = 'Last Month';
                                } elseif ($i == 0) {
                                    $status = 'current';
                                    $status_class = 'current';
                                    $status_text = 'Current Month';
                                } elseif ($i == 1) {
                                    $status = 'upcoming';
                                    $status_class = 'upcoming';
                                    $status_text = 'Next Month';
                                }
                            ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?php echo $status_class; ?>"></div>
                                <div class="timeline-content card p-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo $month_name; ?> Payout</h6>
                                            <p class="text-muted mb-0">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo $payout_date->format('l, F d, Y'); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <span class="badge bg-<?php 
                                                echo $status == 'past' ? 'secondary' : 
                                                     ($status == 'current' ? 'info' : 
                                                     ($status == 'upcoming' ? 'success' : 'warning'));
                                            ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Recent Payouts -->
            <div class="col-lg-4"></div>
                <div class="card border-0 shadow-sm schedule-card">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2 text-primary"></i>
                            Recent Payouts
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_payouts) > 0): ?>
                            <ul class="list-group">
                                <?php foreach ($recent_payouts as $payout): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1">Payout of $<?php echo number_format($payout['amount'], 2); ?></h6>
                                            <p class="text-muted mb-0">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo (new DateTime($payout['created_at']))->format('F d, Y'); ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-success">Completed</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted text-center mb-0">No payouts completed yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
    </main>
</div>  
<script>
    // Countdown Timer
    function updateCountdown() {
        const countdownElement = document.getElementById('countdownTimer');
        const nextPayoutDate = new Date("<?php echo $next_payout_date->format('Y-m-d'); ?>T00:00:00");
        const now = new Date();
        const timeDifference = nextPayoutDate - now;
        
        if (timeDifference > 0) {
            const days = Math.floor(timeDifference / (1000 * 60 * 60 * 24));
            const hours = Math.floor((timeDifference / (1000 * 60 * 60)) % 24);
            const minutes = Math.floor((timeDifference / (1000 * 60)) % 60);
            const seconds = Math.floor((timeDifference / 1000) % 60);
            
            countdownElement.textContent = `${days}d ${hours}h ${minutes}m ${seconds}s`;
        } else {
            countdownElement.textContent = "Payout is being processed!";
        }
    }
    
    setInterval(updateCountdown, 1000);
    updateCountdown();
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
        background: #e9ecef;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 20px;
    }
    
    .timeline-marker {
        position: absolute;
        left: -30px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 0 0 3px #dee2e6;
    }
    
    .timeline-marker.past { background-color: #6c757d; }
    .timeline-marker.current { background-color: #0dcaf0; }
    .timeline-marker.upcoming { background-color: #198754; }
    .timeline-marker.future { background-color: #ffc107; }
    
    .schedule-card {
        border-radius: 10px;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .schedule-card:hover {
        transform: translateY(-5px);
    }
    
    .countdown-timer {
        font-size: 1.5rem;
        font-weight: bold;
    }
    </style>
<?php include_once '../../includes/footer.php'; ?>