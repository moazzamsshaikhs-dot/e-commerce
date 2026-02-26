<?php
// admin/withdrawals.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Withdrawals Management';
require_once './includes/header.php';

$db = getDB();

// Process POST requests (Approve, Reject, Verify)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';
    
    try {
        $db->beginTransaction();
        
        // Verify Payment Method
        if ($action_type === 'verify_account') {
            $method_id = (int)($_POST['method_id'] ?? 0);
            $method_type = $_POST['method_type'] ?? '';
            $vendor_id = (int)($_POST['vendor_id'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            if (!$method_id || !$method_type || !$vendor_id) {
                throw new Exception('Missing required fields for verification');
            }
            
            // Update appropriate table based on method type
            if ($method_type == 'bank') {
                $stmt = $db->prepare("
                    UPDATE vendor_bank_accounts 
                    SET is_verified = 1, 
                        verified_by = ?, 
                        verified_at = NOW()
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $method_id, $vendor_id]);
                
            } elseif (in_array($method_type, ['easypaisa', 'jazzcash'])) {
                $stmt = $db->prepare("
                    UPDATE vendor_mobile_accounts 
                    SET is_verified = 1, 
                        verified_by = ?, 
                        verified_at = NOW()
                    WHERE id = ? AND vendor_id = ? AND account_type = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $method_id, $vendor_id, $method_type]);
                
            } elseif ($method_type == 'paypal') {
                $stmt = $db->prepare("
                    UPDATE vendor_paypal_accounts 
                    SET is_verified = 1, 
                        verified_by = ?, 
                        verified_at = NOW()
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $method_id, $vendor_id]);
                
            } elseif ($method_type == 'stripe') {
                $stmt = $db->prepare("
                    UPDATE vendor_stripe_accounts 
                    SET is_verified = 1, 
                        verified_by = ?, 
                        verified_at = NOW()
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $method_id, $vendor_id]);
                
            } elseif (in_array($method_type, ['visa', 'mastercard', 'amex'])) {
                $stmt = $db->prepare("
                    UPDATE vendor_cards 
                    SET is_verified = 1, 
                        verified_by = ?, 
                        verified_at = NOW()
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $method_id, $vendor_id]);
            }
            
            // Check if update was successful
            if ($stmt->rowCount() == 0) {
                throw new Exception('No records were updated. Payment method may not exist.');
            }
            
            // Create notification for vendor
            $message = "Your " . ucfirst($method_type) . " account has been verified. You can now withdraw funds.";
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, 'Payment Method Verified', ?, 'success', NOW())
            ");
            $stmt->execute([$vendor_id, $message]);
            
            $_SESSION['success'] = "Payment method verified successfully";
        }
        
        // Approve Withdrawal
        elseif ($action_type === 'approve') {
            $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
            $transaction_id = trim($_POST['transaction_id'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            // Get withdrawal details
            $stmt = $db->prepare("
                SELECT w.*, u.full_name, u.email, u.id as vendor_id
                FROM vendor_withdrawals w
                JOIN users u ON w.vendor_id = u.id
                WHERE w.id = ?
            ");
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$withdrawal) {
                throw new Exception('Withdrawal not found');
            }
            
            // Update withdrawal status
            $stmt = $db->prepare("
                UPDATE vendor_withdrawals 
                SET status = 'completed', 
                    transaction_id = ?,
                    processed_by = ?,
                    processed_at = NOW(),
                    admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n', ?)
                WHERE id = ?
            ");
            $stmt->execute([$transaction_id, $_SESSION['user_id'], $notes, $withdrawal_id]);
            
            // Mark earnings as paid (approximate calculation)
            $stmt = $db->prepare("
                UPDATE vendor_earnings 
                SET status = 'paid', 
                    paid_date = CURDATE() 
                WHERE vendor_id = ? AND status = 'processing'
                ORDER BY id ASC
                LIMIT ?
            ");
            $limit = ceil($withdrawal['withdrawal_amount'] / 50); // Assuming average $50 per earning
            $stmt->execute([$withdrawal['vendor_id'], $limit]);
            
            // Create notification for vendor
            $message = "Your withdrawal of $" . number_format($withdrawal['withdrawal_amount'], 2) . " has been approved and processed.";
            if ($transaction_id) {
                $message .= " Transaction ID: $transaction_id";
            }
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, 'Withdrawal Approved', ?, 'success', NOW())
            ");
            $stmt->execute([$withdrawal['vendor_id'], $message]);
            
            $_SESSION['success'] = "Withdrawal #$withdrawal_id has been approved";
        }
        
        // Reject Withdrawal
        elseif ($action_type === 'reject') {
            $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
            $notes = trim($_POST['notes'] ?? '');
            
            // Get withdrawal details
            $stmt = $db->prepare("
                SELECT w.*, u.full_name, u.email, u.id as vendor_id
                FROM vendor_withdrawals w
                JOIN users u ON w.vendor_id = u.id
                WHERE w.id = ?
            ");
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update withdrawal status
            $stmt = $db->prepare("
                UPDATE vendor_withdrawals 
                SET status = 'rejected',
                    processed_by = ?,
                    processed_at = NOW(),
                    admin_notes = CONCAT(IFNULL(admin_notes, ''), '\nRejection: ', ?)
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $notes, $withdrawal_id]);
            
            // Revert earnings status
            $stmt = $db->prepare("
                UPDATE vendor_earnings 
                SET status = 'pending' 
                WHERE vendor_id = ? AND status = 'processing'
            ");
            $stmt->execute([$withdrawal['vendor_id']]);
            
            // Create notification for vendor
            $reason = $notes ?: 'No reason provided';
            $message = "Your withdrawal request of $" . number_format($withdrawal['withdrawal_amount'], 2) . " was rejected. Reason: $reason";
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, 'Withdrawal Rejected', ?, 'error', NOW())
            ");
            $stmt->execute([$withdrawal['vendor_id'], $message]);
            
            $_SESSION['success'] = "Withdrawal #$withdrawal_id has been rejected";
        }
        
        $db->commit();
        
    } catch(Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    redirect('withdrawals.php');
    exit();
}

// Get statistics
$stats = [
    'pending_amount' => 0,
    'monthly_processed' => 0,
    'pending_verifications' => 0
];

try {
    // Total pending withdrawals amount
    $stmt = $db->query("
        SELECT COALESCE(SUM(withdrawal_amount), 0) as total_pending
        FROM vendor_withdrawals 
        WHERE status = 'pending'
    ");
    $stats['pending_amount'] = $stmt->fetchColumn();
    
    // Total processed this month
    $stmt = $db->query("
        SELECT COALESCE(SUM(withdrawal_amount), 0) as total_processed
        FROM vendor_withdrawals 
        WHERE status = 'completed' 
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $stats['monthly_processed'] = $stmt->fetchColumn();
    
    // Pending verifications counts from different tables
    $pending_banks = $db->query("SELECT COUNT(*) FROM vendor_bank_accounts WHERE is_verified = 0")->fetchColumn();
    $pending_mobile = $db->query("SELECT COUNT(*) FROM vendor_mobile_accounts WHERE is_verified = 0")->fetchColumn();
    $pending_paypal = $db->query("SELECT COUNT(*) FROM vendor_paypal_accounts WHERE is_verified = 0")->fetchColumn();
    $pending_stripe = $db->query("SELECT COUNT(*) FROM vendor_stripe_accounts WHERE is_verified = 0")->fetchColumn();
    $pending_cards = $db->query("SELECT COUNT(*) FROM vendor_cards WHERE is_verified = 0")->fetchColumn();
    
    $stats['pending_verifications'] = $pending_banks + $pending_mobile + $pending_paypal + $pending_stripe + $pending_cards;
    
} catch(Exception $e) {
    // Tables might not exist yet
}

// Get ALL withdrawals (including completed and rejected for history)
$withdrawals = [];
try {
    $stmt = $db->prepare("
        SELECT 
            w.*,
            u.full_name,
            u.email,
            u.username,
            u.id as vendor_id
        FROM vendor_withdrawals w
        JOIN users u ON w.vendor_id = u.id
        ORDER BY 
            CASE w.status 
                WHEN 'pending' THEN 1
                WHEN 'processing' THEN 2
                WHEN 'completed' THEN 3
                WHEN 'rejected' THEN 4
            END,
            w.created_at DESC
    ");
    $stmt->execute();
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = "Error loading withdrawals: " . $e->getMessage();
}

// Separate withdrawals for different tabs
$pending_withdrawals = array_filter($withdrawals, function($w) {
    return in_array($w['status'], ['pending', 'processing']);
});

$history_withdrawals = array_filter($withdrawals, function($w) {
    return in_array($w['status'], ['completed', 'rejected']);
});

// Get pending verifications from all tables
$pending_verifications = [];

// Bank accounts pending verification
try {
    $stmt = $db->prepare("
        SELECT 
            ba.*,
            u.full_name as vendor_name,
            u.email as vendor_email,
            u.username,
            'bank' as method_type,
            ba.id as method_id,
            ba.account_holder_name,
            ba.bank_name,
            ba.account_number,
            ba.ifsc_code,
            ba.created_at
        FROM vendor_bank_accounts ba
        JOIN users u ON ba.vendor_id = u.id
        WHERE ba.is_verified = 0
        ORDER BY ba.created_at ASC
    ");
    $stmt->execute();
    $bank_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pending_verifications = array_merge($pending_verifications, $bank_verifications);
} catch(Exception $e) {}

// Mobile accounts (Easypaisa/JazzCash) pending verification
try {
    $stmt = $db->prepare("
        SELECT 
            ma.*,
            u.full_name as vendor_name,
            u.email as vendor_email,
            u.username,
            ma.account_type as method_type,
            ma.id as method_id,
            ma.account_holder_name,
            ma.mobile_number,
            ma.cnic_number,
            ma.created_at
        FROM vendor_mobile_accounts ma
        JOIN users u ON ma.vendor_id = u.id
        WHERE ma.is_verified = 0
        ORDER BY ma.created_at ASC
    ");
    $stmt->execute();
    $mobile_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pending_verifications = array_merge($pending_verifications, $mobile_verifications);
} catch(Exception $e) {}

// PayPal accounts pending verification
try {
    $stmt = $db->prepare("
        SELECT 
            pa.*,
            u.full_name as vendor_name,
            u.email as vendor_email,
            u.username,
            'paypal' as method_type,
            pa.id as method_id,
            pa.account_holder_name,
            pa.paypal_email as email,
            pa.created_at
        FROM vendor_paypal_accounts pa
        JOIN users u ON pa.vendor_id = u.id
        WHERE pa.is_verified = 0
        ORDER BY pa.created_at ASC
    ");
    $stmt->execute();
    $paypal_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pending_verifications = array_merge($pending_verifications, $paypal_verifications);
} catch(Exception $e) {}

// Stripe accounts pending verification
try {
    $stmt = $db->prepare("
        SELECT 
            sa.*,
            u.full_name as vendor_name,
            u.email as vendor_email,
            u.username,
            'stripe' as method_type,
            sa.id as method_id,
            sa.account_holder_name,
            sa.stripe_account_id,
            sa.account_email as email,
            sa.created_at
        FROM vendor_stripe_accounts sa
        JOIN users u ON sa.vendor_id = u.id
        WHERE sa.is_verified = 0
        ORDER BY sa.created_at ASC
    ");
    $stmt->execute();
    $stripe_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pending_verifications = array_merge($pending_verifications, $stripe_verifications);
} catch(Exception $e) {}

// Cards pending verification
try {
    $stmt = $db->prepare("
        SELECT 
            c.*,
            u.full_name as vendor_name,
            u.email as vendor_email,
            u.username,
            c.card_type as method_type,
            c.id as method_id,
            c.card_holder_name as account_holder_name,
            c.card_last_four,
            c.expiry_month,
            c.expiry_year,
            c.created_at
        FROM vendor_cards c
        JOIN users u ON c.vendor_id = u.id
        WHERE c.is_verified = 0
        ORDER BY c.created_at ASC
    ");
    $stmt->execute();
    $card_verifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pending_verifications = array_merge($pending_verifications, $card_verifications);
} catch(Exception $e) {}

// Sort pending verifications by created_at
usort($pending_verifications, function($a, $b) {
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});

// Helper function to format account display
function formatAccountDisplay($method) {
    $type = $method['method_type'] ?? '';
    
    if ($type == 'bank') {
        return $method['bank_name'] . ' - ****' . substr($method['account_number'], -4);
    } elseif (in_array($type, ['easypaisa', 'jazzcash'])) {
        return $method['account_holder_name'] . ' (****' . substr($method['mobile_number'], -4) . ')';
    } elseif ($type == 'paypal') {
        return $method['paypal_email'] ?? $method['email'];
    } elseif ($type == 'stripe') {
        return substr($method['stripe_account_id'], 0, 8) . '...';
    } elseif (in_array($type, ['visa', 'mastercard', 'amex'])) {
        return strtoupper($type) . ' - **** **** **** ' . $method['card_last_four'];
    }
    return 'N/A';
}
?>

<style>
:root {
    --primary-color: #4361ee;
    --success-color: #06d6a0;
    --warning-color: #ffb703;
    --danger-color: #ef476f;
    --dark-color: #073b4c;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 5px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary-color);
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
    color: var(--dark-color);
}

.badge-pending { background: #ffb703; color: #000; padding: 8px 12px; border-radius: 20px; }
.badge-processing { background: #17a2b8; color: #fff; padding: 8px 12px; border-radius: 20px; }
.badge-completed { background: #06d6a0; color: #fff; padding: 8px 12px; border-radius: 20px; }
.badge-rejected { background: #ef476f; color: #fff; padding: 8px 12px; border-radius: 20px; }

.method-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.method-bank { background: #e3f2fd; color: #1976d2; }
.method-paypal { background: #e8f5e9; color: #2e7d32; }
.method-stripe { background: #f3e5f5; color: #7b1fa2; }
.method-easypaisa { background: #fff3e0; color: #f57c00; }
.method-jazzcash { background: #fce4ec; color: #c2185b; }
.method-card { background: #e0f2f1; color: #00796b; }

.verification-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid var(--warning-color);
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}

.verification-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 12px 25px;
    border-radius: 12px 12px 0 0;
}

.nav-tabs .nav-link.active {
    color: var(--primary-color);
    background: white;
    border-bottom: 3px solid var(--primary-color);
}

.btn-approve {
    background: linear-gradient(135deg, #06d6a0 0%, #05b585 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
}

.btn-reject {
    background: linear-gradient(135deg, #ef476f 0%, #d64161 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
}

.btn-verify {
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
}

.rounded-15 { border-radius: 15px; }
.rounded-20 { border-radius: 20px; }
</style>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">Withdrawals Management</h1>
            <p class="text-muted mb-0">Manage vendor withdrawal requests and payment verifications</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="window.location.reload()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-15" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-15" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-2">Pending Withdrawals</p>
                        <h3 class="fw-bold mb-0">$<?php echo number_format($stats['pending_amount'], 2); ?></h3>
                        <small class="text-warning">Awaiting approval</small>
                    </div>
                    <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1); color: #ffb703;">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-2">Processed This Month</p>
                        <h3 class="fw-bold mb-0">$<?php echo number_format($stats['monthly_processed'], 2); ?></h3>
                        <small class="text-success">Completed withdrawals</small>
                    </div>
                    <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1); color: #06d6a0;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-2">Pending Verifications</p>
                        <h3 class="fw-bold mb-0"><?php echo $stats['pending_verifications']; ?></h3>
                        <small class="text-info">Payment methods to verify</small>
                    </div>
                    <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" id="withdrawalTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="withdrawals-tab" data-bs-toggle="tab" data-bs-target="#withdrawals" type="button">
                <i class="fas fa-list me-2"></i>Pending Requests
                <span class="badge bg-warning ms-2"><?php echo count($pending_withdrawals); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="verifications-tab" data-bs-toggle="tab" data-bs-target="#verifications" type="button">
                <i class="fas fa-shield-alt me-2"></i>Pending Verifications
                <span class="badge bg-info ms-2"><?php echo $stats['pending_verifications']; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">
                <i class="fas fa-history me-2"></i>History
                <span class="badge bg-secondary ms-2"><?php echo count($history_withdrawals); ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Pending Withdrawals Tab -->
        <div class="tab-pane fade show active" id="withdrawals" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-20">
                <div class="card-body p-0">
                    <?php if (empty($pending_withdrawals)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x text-success opacity-25 mb-3"></i>
                            <h5 class="text-muted">No Pending Withdrawals</h5>
                            <p class="text-muted small">All withdrawal requests have been processed</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Vendor</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Account</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_withdrawals as $w): ?>
                                        <tr>
                                            <td><strong>#<?php echo $w['id']; ?></strong></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle me-2">
                                                        <i class="fas fa-user text-primary"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($w['full_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($w['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <h6 class="fw-bold mb-0 text-primary">$<?php echo number_format($w['withdrawal_amount'], 2); ?></h6>
                                                <small class="text-muted">Fee: $<?php echo number_format($w['fee_amount'] ?? 0, 2); ?></small>
                                            </td>
                                            <td>
                                                <span class="method-badge method-<?php echo $w['withdrawal_method']; ?>">
                                                    <i class="fas fa-<?php 
                                                        echo $w['withdrawal_method'] == 'bank' ? 'university' : 
                                                            ($w['withdrawal_method'] == 'paypal' ? 'paypal' : 
                                                            ($w['withdrawal_method'] == 'stripe' ? 'stripe' : 
                                                            (in_array($w['withdrawal_method'], ['easypaisa', 'jazzcash']) ? 'mobile-alt' : 'credit-card'))); 
                                                    ?> me-1"></i>
                                                    <?php echo ucfirst($w['withdrawal_method']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php 
                                                    if (!empty($w['account_details'])) {
                                                        $details = json_decode($w['account_details'], true);
                                                        if ($details) {
                                                            if ($w['withdrawal_method'] == 'bank') {
                                                                echo ($details['bank_name'] ?? 'Bank') . ' - ****' . ($details['account_number'] ?? '');
                                                            } elseif ($w['withdrawal_method'] == 'paypal') {
                                                                echo $details['paypal_email'] ?? '';
                                                            } elseif (in_array($w['withdrawal_method'], ['easypaisa', 'jazzcash'])) {
                                                                echo '****' . substr($details['mobile_number'] ?? $w['mobile_number'] ?? '', -4);
                                                            }
                                                        }
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?php echo date('d M Y H:i', strtotime($w['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $w['status']; ?> p-2">
                                                    <?php echo ucfirst($w['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($w['status'] == 'pending'): ?>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-approve" 
                                                                onclick="approveWithdrawal(<?php echo $w['id']; ?>, <?php echo $w['withdrawal_amount']; ?>)">
                                                            <i class="fas fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-sm btn-reject" 
                                                                onclick="rejectWithdrawal(<?php echo $w['id']; ?>)">
                                                            <i class="fas fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                <?php elseif ($w['status'] == 'processing'): ?>
                                                    <span class="badge bg-info">Processing</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Verifications Tab -->
        <div class="tab-pane fade" id="verifications" role="tabpanel">
            <div class="row">
                <?php if (empty($pending_verifications)): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm rounded-20">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-check-circle fa-4x text-success opacity-25 mb-3"></i>
                                <h5 class="text-muted">No Pending Verifications</h5>
                                <p class="text-muted small">All payment methods have been verified</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_verifications as $pm): ?>
                        <div class="col-md-6 mb-4">
                            <div class="verification-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-1">
                                            <span class="method-badge method-<?php echo $pm['method_type']; ?>">
                                                <i class="fas fa-<?php 
                                                    echo $pm['method_type'] == 'bank' ? 'university' : 
                                                        ($pm['method_type'] == 'paypal' ? 'paypal' : 
                                                        ($pm['method_type'] == 'stripe' ? 'stripe' : 
                                                        (in_array($pm['method_type'], ['easypaisa', 'jazzcash']) ? 'mobile-alt' : 'credit-card'))); 
                                                ?> me-1"></i>
                                                <?php echo ucfirst($pm['method_type']); ?>
                                            </span>
                                        </h6>
                                        <p class="mb-1">
                                            <strong>Vendor:</strong> <?php echo htmlspecialchars($pm['vendor_name']); ?><br>
                                            <strong>Email:</strong> <?php echo htmlspecialchars($pm['vendor_email']); ?>
                                        </p>
                                    </div>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($pm['created_at'])); ?></small>
                                </div>
                                
                                <div class="bg-light p-3 rounded-15 mb-3">
                                    <?php if ($pm['method_type'] == 'bank'): ?>
                                        <div class="row g-2">
                                            <div class="col-6"><small>Bank: <?php echo htmlspecialchars($pm['bank_name']); ?></small></div>
                                            <div class="col-6"><small>Account: ****<?php echo substr($pm['account_number'], -4); ?></small></div>
                                            <div class="col-6"><small>IFSC: <?php echo htmlspecialchars($pm['ifsc_code']); ?></small></div>
                                            <div class="col-6"><small>Holder: <?php echo htmlspecialchars($pm['account_holder_name']); ?></small></div>
                                        </div>
                                    <?php elseif ($pm['method_type'] == 'paypal'): ?>
                                        <small>Email: <?php echo htmlspecialchars($pm['paypal_email'] ?? $pm['email']); ?></small>
                                        <?php if (!empty($pm['account_holder_name'])): ?>
                                            <br><small>Holder: <?php echo htmlspecialchars($pm['account_holder_name']); ?></small>
                                        <?php endif; ?>
                                    <?php elseif ($pm['method_type'] == 'stripe'): ?>
                                        <small>Account ID: <?php echo htmlspecialchars($pm['stripe_account_id']); ?></small>
                                        <?php if (!empty($pm['account_holder_name'])): ?>
                                            <br><small>Holder: <?php echo htmlspecialchars($pm['account_holder_name']); ?></small>
                                        <?php endif; ?>
                                    <?php elseif (in_array($pm['method_type'], ['easypaisa', 'jazzcash'])): ?>
                                        <div class="row g-2">
                                            <div class="col-6"><small>Mobile: ****<?php echo substr($pm['mobile_number'], -4); ?></small></div>
                                            <div class="col-6"><small>Holder: <?php echo htmlspecialchars($pm['account_holder_name']); ?></small></div>
                                            <?php if (!empty($pm['cnic_number'])): ?>
                                                <div class="col-12"><small>CNIC: <?php echo htmlspecialchars($pm['cnic_number']); ?></small></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif (in_array($pm['method_type'], ['visa', 'mastercard', 'amex'])): ?>
                                        <div class="row g-2">
                                            <div class="col-6"><small>Card: **** **** **** <?php echo $pm['card_last_four']; ?></small></div>
                                            <div class="col-6"><small>Exp: <?php echo $pm['expiry_month']; ?>/<?php echo $pm['expiry_year']; ?></small></div>
                                            <div class="col-6"><small>Holder: <?php echo htmlspecialchars($pm['card_holder_name'] ?? $pm['account_holder_name']); ?></small></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-sm btn-verify flex-grow-1" 
                                            onclick="verifyPaymentMethod(<?php echo $pm['method_id']; ?>, <?php echo $pm['vendor_id']; ?>, '<?php echo $pm['method_type']; ?>')">
                                        <i class="fas fa-check-circle me-1"></i> Verify Account
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" 
                                            onclick="rejectPaymentMethod(<?php echo $pm['method_id']; ?>, <?php echo $pm['vendor_id']; ?>, '<?php echo $pm['method_type']; ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- History Tab (Now Shows Completed & Rejected) -->
        <div class="tab-pane fade" id="history" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-20">
                <div class="card-body">
                    <?php if (empty($history_withdrawals)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-history fa-4x text-muted opacity-25 mb-3"></i>
                            <h5 class="text-muted">No History Yet</h5>
                            <p class="text-muted small">Completed and rejected withdrawals will appear here</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Vendor</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                        <th>Transaction ID</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history_withdrawals as $w): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($w['created_at'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($w['full_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($w['email']); ?></small>
                                        </td>
                                        <td><strong>$<?php echo number_format($w['withdrawal_amount'], 2); ?></strong></td>
                                        <td>
                                            <span class="method-badge method-<?php echo $w['withdrawal_method']; ?>">
                                                <i class="fas fa-<?php 
                                                    echo $w['withdrawal_method'] == 'bank' ? 'university' : 
                                                        ($w['withdrawal_method'] == 'paypal' ? 'paypal' : 
                                                        ($w['withdrawal_method'] == 'stripe' ? 'stripe' : 
                                                        (in_array($w['withdrawal_method'], ['easypaisa', 'jazzcash']) ? 'mobile-alt' : 'credit-card'))); 
                                                ?> me-1"></i>
                                                <?php echo ucfirst($w['withdrawal_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $w['status'] == 'completed' ? 'success' : 'danger'; ?> p-2">
                                                <?php echo ucfirst($w['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($w['transaction_id'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($w['transaction_id']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($w['admin_notes'])): ?>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="alert('<?php echo htmlspecialchars(addslashes($w['admin_notes'])); ?>')">
                                                    <i class="fas fa-comment"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approve Withdrawal Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="withdrawal_id" id="approve_id">
                <input type="hidden" name="action_type" value="approve">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Approve Withdrawal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Amount: $<span id="approve_amount"></span></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Transaction ID (Optional)</label>
                        <input type="text" name="transaction_id" class="form-control" 
                               placeholder="e.g., bank transfer reference">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Any notes about this withdrawal..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Withdrawal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Withdrawal Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="withdrawal_id" id="reject_id">
                <input type="hidden" name="action_type" value="reject">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Reject Withdrawal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection *</label>
                        <textarea name="notes" class="form-control" rows="4" required 
                                  placeholder="Please provide a reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Withdrawal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Verify Payment Method Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="method_id" id="verify_method_id">
                <input type="hidden" name="vendor_id" id="verify_vendor_id">
                <input type="hidden" name="method_type" id="verify_method_type">
                <input type="hidden" name="action_type" value="verify_account">
                
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Verify Payment Method</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to verify this payment method?</p>
                    <p class="text-muted small">Once verified, the vendor can withdraw funds using this method.</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Verification Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Any verification notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Verify Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Payment Method Modal -->
<div class="modal fade" id="rejectMethodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="delete-payment-method.php">
                <input type="hidden" name="method_id" id="reject_method_id">
                <input type="hidden" name="vendor_id" id="reject_vendor_id">
                <input type="hidden" name="method_type" id="reject_method_type">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Payment Method</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to reject this payment method?</p>
                    <p class="text-danger small">This will delete the payment method and the vendor will need to submit again.</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason *</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject & Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveWithdrawal(id, amount) {
    document.getElementById('approve_id').value = id;
    document.getElementById('approve_amount').textContent = amount.toFixed(2);
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectWithdrawal(id) {
    document.getElementById('reject_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function verifyPaymentMethod(id, vendorId, type) {
    document.getElementById('verify_method_id').value = id;
    document.getElementById('verify_vendor_id').value = vendorId;
    document.getElementById('verify_method_type').value = type;
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
}

function rejectPaymentMethod(id, vendorId, type) {
    document.getElementById('reject_method_id').value = id;
    document.getElementById('reject_vendor_id').value = vendorId;
    document.getElementById('reject_method_type').value = type;
    new bootstrap.Modal(document.getElementById('rejectMethodModal')).show();
}

// Auto refresh every 60 seconds
setTimeout(function() {
    window.location.reload();
}, 60000);
</script>

<?php require_once '../includes/footer.php'; ?>