<?php
// admin/system/withdrawal-management.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

// Special check for system administrator
requireSystemAdmin();

$page_title = 'Withdrawal Management';
require_once '../includes/header.php';

$db = getDB();
$message = '';
$message_type = 'success';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = (int)($_POST['request_id'] ?? 0);
    $admin_account_id = (int)($_POST['admin_account_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($request_id && in_array($action, ['approve', 'reject'])) {
        try {
            $db->beginTransaction();
            
            // Get request details
            $stmt = $db->prepare("
                SELECT vwr.*, u.username, u.email, u.full_name 
                FROM vendor_withdrawal_requests vwr
                JOIN users u ON vwr.vendor_id = u.id
                WHERE vwr.id = ?
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                throw new Exception('Withdrawal request not found');
            }
            
            if ($action === 'approve') {
                // Check if enough balance in admin account
                $stmt = $db->prepare("
                    SELECT current_balance, account_type, account_name 
                    FROM admin_accounts 
                    WHERE id = ? AND is_active = 1
                ");
                $stmt->execute([$admin_account_id]);
                $admin_account = $stmt->fetch();
                
                if (!$admin_account) {
                    throw new Exception('Admin account not found or inactive');
                }
                
                if ($admin_account['current_balance'] < $request['request_amount']) {
                    throw new Exception('Insufficient balance in admin account');
                }
                
                // Update request status
                $stmt = $db->prepare("
                    UPDATE vendor_withdrawal_requests 
                    SET status = 'approved', processed_by = ?, processed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $request_id]);
                
                // Create transaction record
                $fee = ($request['request_amount'] * ($request['fee_percentage'] ?? 0) / 100) + ($request['fee_fixed'] ?? 0);
                $net_amount = $request['request_amount'] - $fee;
                
                $stmt = $db->prepare("
                    INSERT INTO withdrawal_transactions 
                    (withdrawal_request_id, vendor_id, admin_account_id, amount, fee, net_amount, transaction_id, processed_by, processed_at, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'completed')
                ");
                
                // Generate transaction ID
                $transaction_id = 'TXN' . time() . rand(1000, 9999);
                
                $stmt->execute([
                    $request_id,
                    $request['vendor_id'],
                    $admin_account_id,
                    $request['request_amount'],
                    $fee,
                    $net_amount,
                    $transaction_id,
                    $_SESSION['user_id']
                ]);
                
                // Send notification to vendor
                $message = "✅ Your withdrawal request of {$request['request_amount']} has been approved and processed. Transaction ID: {$transaction_id}";
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Withdrawal Approved', ?, 'success', NOW())
                ");
                $stmt->execute([$request['vendor_id'], $message]);
                
                // Log activity
                logUserActivity($_SESSION['user_id'], 'withdrawal_approved', 
                    "Approved withdrawal request #{$request_id} for {$request['full_name']} of {$request['request_amount']}");
                
                $_SESSION['success'] = "Withdrawal request approved and processed successfully!";
                
            } else {
                // Reject request
                $stmt = $db->prepare("
                    UPDATE vendor_withdrawal_requests 
                    SET status = 'rejected', processed_by = ?, processed_at = NOW(), rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $rejection_reason, $request_id]);
                
                // Send notification to vendor
                $message = "❌ Your withdrawal request of {$request['request_amount']} has been rejected.<br><strong>Reason:</strong> {$rejection_reason}";
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Withdrawal Rejected', ?, 'error', NOW())
                ");
                $stmt->execute([$request['vendor_id'], $message]);
                
                logUserActivity($_SESSION['user_id'], 'withdrawal_rejected', 
                    "Rejected withdrawal request #{$request_id} for {$request['full_name']}");
                
                $_SESSION['success'] = "Withdrawal request rejected!";
            }
            
            $db->commit();
            
        } catch(Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        redirect('withdrawal-management.php');
        exit();
    }
}

// Get filter
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build query for withdrawal requests
$query = "
    SELECT 
        vwr.*,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        (SELECT SUM(vendor_amount) FROM vendor_earnings WHERE vendor_id = u.id AND status = 'paid') as total_earnings,
        (SELECT SUM(request_amount) FROM vendor_withdrawal_requests WHERE vendor_id = u.id AND status = 'approved') as total_withdrawn
    FROM vendor_withdrawal_requests vwr
    JOIN users u ON vwr.vendor_id = u.id
    WHERE 1=1
";

$params = [];

if ($status !== 'all') {
    $query .= " AND vwr.status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR vwr.transaction_id LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY vwr.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active admin accounts for dropdown
$admin_accounts = $db->query("
    SELECT * FROM admin_accounts 
    WHERE is_active = 1 
    ORDER BY is_default DESC, account_type
")->fetchAll();

// Get account balances and stats
$account_stats = [];
$balance_history = [];
$total_balance = 0;

foreach ($admin_accounts as $account) {
    $account_stats[$account['id']] = $account;
    $total_balance += $account['current_balance'];
}

// Get transaction summary
$transaction_summary = $db->query("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_amount,
        SUM(net_amount) as total_net,
        COUNT(DISTINCT vendor_id) as unique_vendors,
        DATE(created_at) as date
    FROM withdrawal_transactions
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date DESC
")->fetchAll();

// Get stats
$stats = [];
$stats_query = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        COUNT(*) as total
    FROM vendor_withdrawal_requests
");
$stats = $stats_query->fetch(PDO::FETCH_ASSOC);
?>

<style>
/* Your Root Colors */
:root {
    --primary: #4361ee;
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --dark: #2b2d42;
    --light: #f8f9fa;
}

/* Derived Colors */
:root {
    --primary-light: rgba(67, 97, 238, 0.1);
    --primary-dark: #3651c4;
    --success-light: rgba(6, 214, 160, 0.1);
    --success-dark: #05b585;
    --warning-light: rgba(255, 183, 3, 0.1);
    --warning-dark: #e6a500;
    --danger-light: rgba(239, 71, 111, 0.1);
    --danger-dark: #d64161;
    --info-light: rgba(76, 201, 240, 0.1);
    --info-dark: #3aa9d9;
    --dark-light: rgba(43, 45, 66, 0.1);
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --shadow-glow: 0 0 20px rgba(67, 97, 238, 0.3);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* Base Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInLeft {
    from {
        transform: translateX(-30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideInRight {
    from {
        transform: translateX(30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes scaleIn {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes pulse-glow {
    0% { 
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(67, 97, 238, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
    }
}

@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0px); }
}

@keyframes glow {
    0% { box-shadow: 0 0 5px var(--primary-light); }
    50% { box-shadow: 0 0 20px var(--primary); }
    100% { box-shadow: 0 0 5px var(--primary-light); }
}

@keyframes border-pulse {
    0% { border-color: var(--primary); }
    50% { border-color: var(--info); }
    100% { border-color: var(--primary); }
}

@keyframes count-up {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Apply animations to elements */
.page-header {
    animation: slideInUp 0.6s ease-out;
}

.stat-card {
    animation: slideInUp 0.5s ease-out;
    animation-fill-mode: both;
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: translateX(-100%);
    animation: shimmer 2s infinite;
    pointer-events: none;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.15s; }
.stat-card:nth-child(3) { animation-delay: 0.2s; }
.stat-card:nth-child(4) { animation-delay: 0.25s; }
.stat-card:nth-child(5) { animation-delay: 0.3s; }

.stat-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: var(--shadow-hover);
}

.stat-card:hover .stat-icon {
    animation: pulse 0.5s ease;
}

.stat-icon {
    transition: var(--transition);
}

.stat-value {
    animation: count-up 0.8s ease-out;
}

.balance-card {
    animation: slideInLeft 0.5s ease-out;
    animation-fill-mode: both;
    transition: var(--transition-bounce);
}

.balance-card:nth-child(1) { animation-delay: 0.1s; }
.balance-card:nth-child(2) { animation-delay: 0.15s; }
.balance-card:nth-child(3) { animation-delay: 0.2s; }
.balance-card:nth-child(4) { animation-delay: 0.25s; }

.balance-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
}

.balance-card:hover .balance-amount {
    animation: pulse 0.5s ease;
    color: var(--primary-dark);
}

.filter-bar {
    animation: slideInUp 0.5s ease-out 0.3s both;
}

.filter-tab {
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.filter-tab::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.filter-tab:active::after {
    width: 200px;
    height: 200px;
}

.filter-tab:hover {
    transform: translateY(-2px);
}

.filter-tab.active {
    animation: pulse-glow 2s infinite;
}

.search-box {
    transition: var(--transition);
}

.search-box:focus-within {
    transform: scale(1.02);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.search-box button {
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.search-box button::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.search-box button:active::after {
    width: 200px;
    height: 200px;
}

.requests-card {
    animation: slideInUp 0.6s ease-out 0.4s both;
}

.requests-table tbody tr {
    animation: slideInRight 0.4s ease-out;
    animation-fill-mode: both;
    transition: var(--transition);
}

.requests-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
.requests-table tbody tr:nth-child(2) { animation-delay: 0.15s; }
.requests-table tbody tr:nth-child(3) { animation-delay: 0.2s; }
.requests-table tbody tr:nth-child(4) { animation-delay: 0.25s; }
.requests-table tbody tr:nth-child(5) { animation-delay: 0.3s; }
.requests-table tbody tr:nth-child(6) { animation-delay: 0.35s; }
.requests-table tbody tr:nth-child(7) { animation-delay: 0.4s; }
.requests-table tbody tr:nth-child(8) { animation-delay: 0.45s; }
.requests-table tbody tr:nth-child(9) { animation-delay: 0.5s; }
.requests-table tbody tr:nth-child(10) { animation-delay: 0.55s; }

.requests-table tbody tr:hover {
    transform: translateX(5px) scale(1.01);
    background: white;
    box-shadow: var(--shadow);
}

.status-badge {
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.status-badge:hover {
    transform: scale(1.1);
    filter: brightness(1.1);
}

.btn-approve, .btn-reject, .btn-view {
    transition: var(--transition-bounce);
    position: relative;
    overflow: hidden;
}

.btn-approve::after, .btn-reject::after, .btn-view::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-approve:active::after, .btn-reject:active::after, .btn-view:active::after {
    width: 200px;
    height: 200px;
}

.btn-approve:hover, .btn-reject:hover, .btn-view:hover {
    transform: translateY(-3px) scale(1.05);
}

.chart-container {
    animation: slideInUp 0.6s ease-out 0.5s both;
    transition: var(--transition);
}

.chart-container:hover {
    box-shadow: var(--shadow-hover);
}

.chart-wrapper {
    transition: var(--transition);
    border-radius: var(--radius);
}

.chart-wrapper:hover {
    transform: scale(1.01);
}

.account-selector {
    transition: var(--transition);
    cursor: pointer;
}

.account-selector:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
}

.account-selector:focus {
    animation: border-pulse 1s infinite;
}

.modal-content {
    animation: scaleIn 0.3s ease-out;
}

.modal-header {
    animation: slideInUp 0.3s ease-out;
}

.modal-footer .btn {
    transition: var(--transition-bounce);
    position: relative;
    overflow: hidden;
}

.modal-footer .btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.modal-footer .btn:active::after {
    width: 200px;
    height: 200px;
}

.modal-footer .btn:hover {
    transform: translateY(-2px);
}

/* Loading animation for pending items */
.status-pending {
    position: relative;
    animation: pulse-glow 2s infinite;
}

.status-processing {
    position: relative;
}

.status-processing i {
    animation: rotate 2s infinite linear;
}

/* Float animation for icons */
.fa-wallet, .fa-hand-holding-usd, .fa-credit-card {
    animation: float 3s ease-in-out infinite;
}

/* Hover effects for cards */
.balance-stats span {
    transition: var(--transition);
    cursor: default;
}

.balance-stats span:hover {
    transform: scale(1.1);
    color: var(--primary);
}

/* Empty state animation */
.text-center.py-5 i {
    animation: float 3s ease-in-out infinite;
}

.text-center.py-5:hover i {
    animation: pulse 1s ease;
}

/* Alert animations */
.alert {
    animation: slideInLeft 0.4s ease-out;
    position: relative;
    overflow: hidden;
}

.alert::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: translateX(-100%);
    animation: shimmer 2s infinite;
    pointer-events: none;
}

/* Tooltip animations */
[title] {
    position: relative;
    cursor: help;
}

[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    padding: 5px 10px;
    background: var(--dark);
    color: white;
    border-radius: var(--radius-sm);
    font-size: 12px;
    white-space: nowrap;
    animation: slideInUp 0.2s ease-out;
    z-index: 1000;
}

/* Responsive animations */
@media (max-width: 768px) {
    .stat-card, .balance-card, .requests-table tbody tr {
        animation: slideInUp 0.4s ease-out;
    }
    
    .stat-card:hover, .balance-card:hover {
        transform: translateY(-3px) scale(1.01);
    }
}

/* Print styles */
@media print {
    .stat-card, .balance-card, .requests-table tbody tr {
        animation: none;
        transform: none;
    }
    
    .btn-approve, .btn-reject, .btn-view {
        display: none;
    }
}

/* Extra fixes for body/html */
html, body {
    overflow-x: hidden;
    overflow-y: auto;
    height: auto;
    min-height: 100vh;
    margin: 0;
    padding: 0;
    scroll-behavior: smooth;
}

/* Fix for main container */
.withdrawal-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
    height: auto;
    overflow: visible;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}

/* Ensure nothing creates horizontal scroll */
* {
    box-sizing: border-box;
    max-width: 100%;
}

/* Main Layout */
.withdrawal-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
    height: auto;
    overflow: visible;
    position: relative;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
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

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border-left: 4px solid transparent;
    position: relative;
    overflow: hidden;
}

.stat-card.pending { border-left-color: var(--warning); }
.stat-card.approved { border-left-color: var(--success); }
.stat-card.processing { border-left-color: var(--info); }
.stat-card.completed { border-left-color: var(--dark); }
.stat-card.rejected { border-left-color: var(--danger); }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}

.stat-card.pending .stat-icon { background: var(--warning-light); color: var(--warning); }
.stat-card.approved .stat-icon { background: var(--success-light); color: var(--success); }
.stat-card.processing .stat-icon { background: var(--info-light); color: var(--info); }
.stat-card.completed .stat-icon { background: var(--dark-light); color: var(--dark); }
.stat-card.rejected .stat-icon { background: var(--danger-light); color: var(--danger); }

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
    margin-bottom: 5px;
}

.stat-label {
    color: var(--dark);
    opacity: 0.7;
    font-size: 14px;
    font-weight: 500;
}

/* Balance Cards */
.balance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.balance-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.balance-card.paypal { border-top: 4px solid #003087; }
.balance-card.stripe { border-top: 4px solid #635bff; }
.balance-card.easypaisa { border-top: 4px solid #27aae1; }
.balance-card.jazzcash { border-top: 4px solid #ed1c24; }
.balance-card.visa { border-top: 4px solid #1a1f71; }
.balance-card.mastercard { border-top: 4px solid #eb001b; }
.balance-card.bank { border-top: 4px solid var(--success); }

.balance-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}

.balance-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
}

.balance-amount {
    font-size: 24px;
    font-weight: 700;
    color: var(--primary);
    margin: 10px 0;
    transition: var(--transition);
}

.balance-stats {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--dark);
    opacity: 0.7;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow);
}

.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark);
    background: var(--light);
    transition: var(--transition);
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.75rem;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

/* Search Box */
.search-box {
    display: flex;
    align-items: center;
    background: var(--light);
    border-radius: 30px;
    padding: 4px;
    min-width: 300px;
    border: 1px solid var(--border);
}

.search-box input {
    border: none;
    background: transparent;
    padding: 8px 16px;
    flex: 1;
    font-size: 0.875rem;
    outline: none;
    color: var(--dark);
}

.search-box input::placeholder {
    color: var(--dark);
    opacity: 0.5;
}

.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

/* Requests Card */
.requests-card {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    overflow: visible;
    margin-bottom: 30px;
}

.table-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    background: var(--light);
}

.table-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Table Responsive */
.table-responsive {
    padding: 0 25px 25px 25px;
    overflow-x: auto;
    overflow-y: visible;
    max-height: none;
}

.requests-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.requests-table th {
    padding: 12px 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--dark);
    opacity: 0.7;
    border-bottom: 2px solid var(--border);
}

.requests-table td {
    padding: 15px;
    background: var(--light);
    border-radius: var(--radius);
    transition: var(--transition);
    font-size: 0.875rem;
    vertical-align: middle;
}

/* Status Badges */
.status-badge {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.status-pending {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.status-approved {
    background: var(--success-light);
    color: var(--success-dark);
}

.status-processing {
    background: var(--info-light);
    color: var(--info-dark);
}

.status-completed {
    background: var(--dark-light);
    color: var(--dark);
}

.status-rejected {
    background: var(--danger-light);
    color: var(--danger-dark);
}

/* Action Buttons */
.btn-approve, .btn-reject, .btn-view {
    padding: 5px 10px;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    font-weight: 500;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-approve {
    background: var(--success-light);
    color: var(--success-dark);
    border: 1px solid var(--success);
}

.btn-reject {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
}

.btn-view {
    background: var(--primary-light);
    color: var(--primary-dark);
    border: 1px solid var(--primary);
}

/* Account Selector */
.account-selector {
    padding: 8px 12px;
    border-radius: var(--radius);
    border: 1px solid var(--border);
    background: white;
    color: var(--dark);
    font-size: 14px;
    outline: none;
    cursor: pointer;
    max-width: 200px;
}

/* Chart Container */
.chart-container {
    background: white;
    border-radius: var(--radius-xl);
    padding: 20px;
    box-shadow: var(--shadow);
    margin-top: 30px;
    overflow: hidden;
    position: relative;
    width: 100%;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.chart-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.chart-wrapper {
    position: relative;
    width: 100%;
    height: 300px;
    overflow: hidden;
}

#transactionChart {
    display: block;
    width: 100% !important;
    height: 100% !important;
    max-width: 100%;
    max-height: 100%;
}

/* Modal Styles */
.modal-content {
    border-radius: var(--radius-lg);
    border: none;
    overflow: hidden;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: none;
}

.modal-header.bg-success { background: var(--success) !important; }
.modal-header.bg-danger { background: var(--danger) !important; }
.modal-header.bg-info { background: var(--info) !important; }

.modal-body { padding: 25px; }
.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--border);
    background: var(--light);
}

/* Badges */
.badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
}

.badge.bg-info { background: var(--info-light) !important; color: var(--info-dark) !important; }
.badge.bg-success { background: var(--success-light) !important; color: var(--success-dark) !important; }

/* Alerts */
.alert-success { background: var(--success-light); color: var(--success-dark); border: 1px solid var(--success); }
.alert-danger { background: var(--danger-light); color: var(--danger-dark); border: 1px solid var(--danger); }
.alert-info { background: var(--info-light); color: var(--info-dark); border: 1px solid var(--info); }
.alert-warning { background: var(--warning-light); color: var(--warning-dark); border: 1px solid var(--warning); }

/* Buttons */
.btn-secondary {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

.btn-success {
    background: var(--success);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
}

/* Responsive */
@media (max-width: 768px) {
    .withdrawal-container { padding: 20px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .filter-bar { flex-direction: column; align-items: stretch; }
    .search-box { width: 100%; min-width: auto; }
    .table-responsive { padding: 0 15px 15px 15px; }
}

@media (max-width: 480px) {
    .stats-grid { grid-template-columns: 1fr; }
    .balance-grid { grid-template-columns: 1fr; }
}
</style>

<div class="withdrawal-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-hand-holding-usd me-2" style="color: var(--primary);"></i>
                Withdrawal Management
            </h1>
            <p class="text-muted mb-0">Manage vendor withdrawal requests and account balances</p>
        </div>
        <div class="d-flex gap-2">
            <a href="admin-accounts.php" class="btn btn-outline-primary">
                <i class="fas fa-credit-card me-2"></i> Manage Accounts
            </a>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card processing">
            <div class="stat-icon">
                <i class="fas fa-sync-alt"></i>
            </div>
            <div class="stat-value"><?php echo $stats['processing'] ?? 0; ?></div>
            <div class="stat-label">Processing</div>
        </div>
        <div class="stat-card completed">
            <div class="stat-icon">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="stat-value"><?php echo $stats['completed'] ?? 0; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Account Balances -->
    <h4 class="mb-3">
        <i class="fas fa-wallet me-2" style="color: var(--primary);"></i>
        Account Balances
        <small class="text-muted">Total: $<?php echo number_format($total_balance, 2); ?></small>
    </h4>

    <div class="balance-grid">
        <?php foreach ($admin_accounts as $account): 
            $icon = match($account['account_type']) {
                'paypal' => 'fab fa-paypal',
                'stripe' => 'fab fa-stripe',
                'easypaisa' => 'fas fa-mobile-alt',
                'jazzcash' => 'fas fa-mobile-alt',
                'visa' => 'fab fa-cc-visa',
                'mastercard' => 'fab fa-cc-mastercard',
                'bank' => 'fas fa-university',
                default => 'fas fa-credit-card'
            };
        ?>
        <div class="balance-card <?php echo $account['account_type']; ?>">
            <div class="balance-header">
                <div>
                    <i class="<?php echo $icon; ?> me-2"></i>
                    <span class="balance-title"><?php echo htmlspecialchars($account['account_name']); ?></span>
                </div>
                <?php if ($account['is_default']): ?>
                    <span class="badge bg-success">DEFAULT</span>
                <?php endif; ?>
            </div>
            <div class="balance-amount">$<?php echo number_format($account['current_balance'] ?? 0, 2); ?></div>
            <div class="balance-stats">
                <span><i class="fas fa-arrow-up text-success"></i> $<?php echo number_format($account['total_credited'] ?? 0, 2); ?></span>
                <span><i class="fas fa-arrow-down text-danger"></i> $<?php echo number_format($account['total_debited'] ?? 0, 2); ?></span>
                <span><i class="fas fa-clock"></i> <?php echo $account['last_transaction_at'] ? date('d M', strtotime($account['last_transaction_at'])) : 'Never'; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All
                <span class="count"><?php echo $stats['total'] ?? 0; ?></span>
            </a>
            <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Pending
                <span class="count"><?php echo $stats['pending'] ?? 0; ?></span>
            </a>
            <a href="?status=approved" class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Approved
                <span class="count"><?php echo $stats['approved'] ?? 0; ?></span>
            </a>
            <a href="?status=processing" class="filter-tab <?php echo $status === 'processing' ? 'active' : ''; ?>">
                <i class="fas fa-sync-alt"></i> Processing
                <span class="count"><?php echo $stats['processing'] ?? 0; ?></span>
            </a>
            <a href="?status=completed" class="filter-tab <?php echo $status === 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-check-double"></i> Completed
                <span class="count"><?php echo $stats['completed'] ?? 0; ?></span>
            </a>
            <a href="?status=rejected" class="filter-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Rejected
                <span class="count"><?php echo $stats['rejected'] ?? 0; ?></span>
            </a>
        </div>

        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search by vendor or transaction..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search me-1"></i> Search</button>
        </form>
    </div>

    <!-- Withdrawal Requests Table -->
    <div class="requests-card">
        <div class="table-header">
            <h5>
                <i class="fas fa-list-ul me-2" style="color: var(--primary);"></i>
                Withdrawal Requests
                <span class="badge bg-secondary ms-2"><?php echo count($requests); ?></span>
            </h5>
        </div>

        <div class="table-responsive">
            <?php if (empty($requests)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x" style="color: var(--dark); opacity: 0.2;"></i>
                    <h5 class="mt-3" style="color: var(--dark);">No withdrawal requests found</h5>
                    <p style="color: var(--dark); opacity: 0.7;">No <?php echo $status; ?> requests to display</p>
                </div>
            <?php else: ?>
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vendor</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Account</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td>#<?php echo $req['id']; ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($req['full_name'] ?? $req['username']); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($req['email']); ?></small>
                            </td>
                            <td>
                                <span class="fw-bold" style="color: var(--primary);">$<?php echo number_format($req['request_amount'], 2); ?></span>
                                <br><small class="text-muted">Balance: $<?php echo number_format($req['available_balance'] ?? 0, 2); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo ucfirst($req['withdrawal_method']); ?></span>
                            </td>
                            <td>
                                <?php 
                                $details = json_decode($req['account_details'], true);
                                if ($req['withdrawal_method'] == 'paypal') {
                                    echo $details['paypal_email'] ?? 'N/A';
                                } elseif ($req['withdrawal_method'] == 'bank') {
                                    echo $details['bank_name'] ?? 'Bank';
                                } else {
                                    echo $details['account_number'] ?? $details['mobile_number'] ?? 'N/A';
                                }
                                ?>
                            </td>
                            <td>
                                <?php echo date('d M Y', strtotime($req['created_at'])); ?>
                                <br><small class="text-muted"><?php echo timeElapsedString($req['created_at']); ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $req['status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $req['status'] == 'approved' ? 'check-circle' : 
                                            ($req['status'] == 'pending' ? 'clock' : 
                                            ($req['status'] == 'processing' ? 'sync-alt' : 
                                            ($req['status'] == 'completed' ? 'check-double' : 'times-circle'))); 
                                    ?>"></i>
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <?php if ($req['status'] == 'pending'): ?>
                                        <button class="btn-approve" onclick="approveRequest(<?php echo $req['id']; ?>, <?php echo $req['request_amount']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn-reject" onclick="rejectRequest(<?php echo $req['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-view" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($req)); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Transaction Chart -->
    <div class="chart-container">
        <div class="chart-header">
            <h5 class="chart-title">
                <i class="fas fa-chart-line me-2" style="color: var(--primary);"></i>
                Transaction History (Last 30 Days)
            </h5>
            <select class="account-selector" id="chartAccount">
                <option value="all">All Accounts</option>
                <?php foreach ($admin_accounts as $account): ?>
                <option value="<?php echo $account['id']; ?>"><?php echo $account['account_name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="chart-wrapper">
            <canvas id="transactionChart"></canvas>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" id="approve_request_id">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Approve Withdrawal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-money-bill-wave fa-4x" style="color: var(--success);"></i>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Amount:</strong> $<span id="approve_amount"></span>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Admin Account</label>
                        <select name="admin_account_id" class="form-select" required>
                            <option value="">Choose account</option>
                            <?php foreach ($admin_accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>" data-balance="<?php echo $account['current_balance']; ?>">
                                <?php echo $account['account_name']; ?> - $<?php echo number_format($account['current_balance'], 2); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Amount will be deducted from selected account and transferred to vendor.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve & Transfer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="reject_request_id">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i> Reject Withdrawal</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x" style="color: var(--danger);"></i>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection</label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i> Withdrawal Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Dynamic content will be inserted here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Approve request
function approveRequest(id, amount) {
    document.getElementById('approve_request_id').value = id;
    document.getElementById('approve_amount').textContent = amount;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

// Reject request
function rejectRequest(id) {
    document.getElementById('reject_request_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// View details
function viewDetails(request) {
    const details = JSON.parse(decodeURIComponent(request));
    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6>Request Information</h6>
                <table class="table table-sm">
                    <tr><th>ID:</th><td>#${details.id}</td></tr>
                    <tr><th>Amount:</th><td>$${parseFloat(details.request_amount).toFixed(2)}</td></tr>
                    <tr><th>Method:</th><td>${details.withdrawal_method}</td></tr>
                    <tr><th>Status:</th><td><span class="badge bg-${details.status}">${details.status}</span></td></tr>
                    <tr><th>Requested:</th><td>${new Date(details.created_at).toLocaleString()}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Vendor Information</h6>
                <table class="table table-sm">
                    <tr><th>Name:</th><td>${details.full_name || details.username}</td></tr>
                    <tr><th>Email:</th><td>${details.email}</td></tr>
                    <tr><th>Phone:</th><td>${details.phone || 'N/A'}</td></tr>
                </table>
            </div>
        </div>
        <h6 class="mt-3">Account Details</h6>
        <pre class="bg-light p-3 rounded">${JSON.stringify(JSON.parse(details.account_details), null, 2)}</pre>
    `;
    
    if (details.rejection_reason) {
        html += `
            <div class="alert alert-danger mt-3">
                <strong>Rejection Reason:</strong> ${details.rejection_reason}
            </div>
        `;
    }
    
    document.getElementById('detailsContent').innerHTML = html;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

// Initialize chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('transactionChart').getContext('2d');
    
    // Get transaction data from PHP
    const transactionData = <?php echo json_encode($transaction_summary); ?>;
    
    const dates = transactionData && transactionData.length ? transactionData.map(d => d.date) : ['No Data'];
    const amounts = transactionData && transactionData.length ? transactionData.map(d => parseFloat(d.total_amount || 0)) : [0];
    const vendors = transactionData && transactionData.length ? transactionData.map(d => d.unique_vendors || 0) : [0];
    
    // Destroy existing chart if any
    if (window.myChart instanceof Chart) {
        window.myChart.destroy();
    }
    
    // Create new chart
    window.myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Transaction Amount ($)',
                data: amounts,
                borderColor: '#4361ee',
                backgroundColor: 'rgba(67, 97, 238, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y',
                pointRadius: 3,
                pointHoverRadius: 5
            }, {
                label: 'Unique Vendors',
                data: vendors,
                borderColor: '#06d6a0',
                backgroundColor: 'rgba(6, 214, 160, 0.1)',
                tension: 0.4,
                yAxisID: 'y1',
                pointRadius: 3,
                pointHoverRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        boxWidth: 12,
                        padding: 15
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Amount ($)'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Vendors'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeInOutQuart'
            }
        }
    });
});

// Account selector change
document.getElementById('chartAccount')?.addEventListener('change', function() {
    alert('Account-specific chart coming soon!');
});

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);

// Time elapsed function
function timeElapsedString(datetime) {
    const diff = new Date() - new Date(datetime);
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (days > 0) return days + ' day' + (days > 1 ? 's' : '') + ' ago';
    if (hours > 0) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
    if (minutes > 0) return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
    return 'just now';
}
</script>

<?php
// Helper function for time elapsed
function timeElapsedString($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

require_once '../includes/footer.php';
?>