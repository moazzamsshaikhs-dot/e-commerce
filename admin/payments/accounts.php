<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

requireSystemAdmin();

$db = getDB();
$page_title = 'Payment Accounts Management';
require_once '../includes/header.php';

// Get account type from URL
$account_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// Define account types and their properties
$account_types = [
    'paypal' => [
        'name' => 'PayPal',
        'icon' => 'fab fa-paypal',
        'color' => '#003087',
        'bg_color' => 'rgba(0, 48, 135, 0.1)',
        'fields' => ['account_email', 'account_name'],
        'table' => 'admin_accounts'
    ],
    'stripe' => [
        'name' => 'Stripe',
        'icon' => 'fab fa-stripe',
        'color' => '#6772e5',
        'bg_color' => 'rgba(103, 114, 229, 0.1)',
        'fields' => ['account_email', 'account_name'],
        'table' => 'admin_accounts'
    ],
    'easypaisa' => [
        'name' => 'Easypaisa',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#1a4d2e',
        'bg_color' => 'rgba(26, 77, 46, 0.1)',
        'fields' => ['phone_number', 'account_name'],
        'table' => 'admin_accounts'
    ],
    'jazzcash' => [
        'name' => 'JazzCash',
        'icon' => 'fas fa-mobile-alt',
        'color' => '#ed1c24',
        'bg_color' => 'rgba(237, 28, 36, 0.1)',
        'fields' => ['phone_number', 'account_name'],
        'table' => 'admin_accounts'
    ],
    'bank' => [
        'name' => 'Bank Transfer',
        'icon' => 'fas fa-university',
        'color' => '#2b2d42',
        'bg_color' => 'rgba(43, 45, 66, 0.1)',
        'fields' => ['bank_name', 'account_number', 'account_holder', 'swift_code', 'iban'],
        'table' => 'admin_accounts'
    ],
    'visa' => [
        'name' => 'Visa Card',
        'icon' => 'fab fa-cc-visa',
        'color' => '#1a1f71',
        'bg_color' => 'rgba(26, 31, 113, 0.1)',
        'fields' => ['account_number', 'account_holder'],
        'table' => 'admin_accounts'
    ],
    'mastercard' => [
        'name' => 'Mastercard',
        'icon' => 'fab fa-cc-mastercard',
        'color' => '#f79e1b',
        'bg_color' => 'rgba(247, 158, 27, 0.1)',
        'fields' => ['account_number', 'account_holder'],
        'table' => 'admin_accounts'
    ]
];

// Handle form submission for add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $db->beginTransaction();
            
            if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
                $id = $_POST['id'] ?? 0;
                $account_name = $_POST['account_name'] ?? '';
                $account_email = $_POST['account_email'] ?? '';
                $account_number = $_POST['account_number'] ?? '';
                $account_holder = $_POST['account_holder'] ?? '';
                $bank_name = $_POST['bank_name'] ?? '';
                $swift_code = $_POST['swift_code'] ?? '';
                $routing_number = $_POST['routing_number'] ?? '';
                $iban = $_POST['iban'] ?? '';
                $phone_number = $_POST['phone_number'] ?? '';
                $cnic = $_POST['cnic'] ?? '';
                $notes = $_POST['notes'] ?? '';
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                $is_default = isset($_POST['is_default']) ? 1 : 0;
                
                // If setting as default, remove default from others
                if ($is_default) {
                    $stmt = $db->prepare("UPDATE admin_accounts SET is_default = 0 WHERE account_type = ?");
                    $stmt->execute([$account_type]);
                }
                
                if ($_POST['action'] === 'add') {
                    $stmt = $db->prepare("
                        INSERT INTO admin_accounts (
                            account_type, account_name, account_email, account_number, 
                            account_holder, bank_name, swift_code, routing_number, iban,
                            phone_number, cnic, is_active, is_default, notes, created_by, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $account_type, $account_name, $account_email, $account_number,
                        $account_holder, $bank_name, $swift_code, $routing_number, $iban,
                        $phone_number, $cnic, $is_active, $is_default, $notes, $_SESSION['user_id']
                    ]);
                    $message = "Account added successfully!";
                } else {
                    $stmt = $db->prepare("
                        UPDATE admin_accounts SET
                            account_name = ?, account_email = ?, account_number = ?,
                            account_holder = ?, bank_name = ?, swift_code = ?,
                            routing_number = ?, iban = ?, phone_number = ?, cnic = ?,
                            is_active = ?, is_default = ?, notes = ?, updated_at = NOW()
                        WHERE id = ? AND account_type = ?
                    ");
                    $stmt->execute([
                        $account_name, $account_email, $account_number,
                        $account_holder, $bank_name, $swift_code,
                        $routing_number, $iban, $phone_number, $cnic,
                        $is_active, $is_default, $notes, $id, $account_type
                    ]);
                    $message = "Account updated successfully!";
                }
            }
            
            if ($_POST['action'] === 'delete') {
                $id = $_POST['id'] ?? 0;
                $stmt = $db->prepare("DELETE FROM admin_accounts WHERE id = ? AND account_type = ?");
                $stmt->execute([$id, $account_type]);
                $message = "Account deleted successfully!";
            }
            
            if ($_POST['action'] === 'toggle_status') {
                $id = $_POST['id'] ?? 0;
                $stmt = $db->prepare("UPDATE admin_accounts SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Account status updated!";
            }
            
            if ($_POST['action'] === 'set_default') {
                $id = $_POST['id'] ?? 0;
                
                // Remove default from all accounts of this type
                $stmt = $db->prepare("UPDATE admin_accounts SET is_default = 0 WHERE account_type = ?");
                $stmt->execute([$account_type]);
                
                // Set new default
                $stmt = $db->prepare("UPDATE admin_accounts SET is_default = 1 WHERE id = ?");
                $stmt->execute([$id]);
                
                $message = "Default account updated!";
            }
            
            $db->commit();
            $_SESSION['success'] = $message;
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        redirect("accounts.php?type=" . $account_type);
    }
}

// Get accounts based on type
if ($account_type === 'all') {
    $stmt = $db->query("
        SELECT * FROM admin_accounts 
        WHERE is_active = 1 
        ORDER BY account_type, is_default DESC, id DESC
    ");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by type
    $grouped_accounts = [];
    foreach ($accounts as $acc) {
        $grouped_accounts[$acc['account_type']][] = $acc;
    }
} else {
    $stmt = $db->prepare("
        SELECT * FROM admin_accounts 
        WHERE account_type = ? 
        ORDER BY is_default DESC, id DESC
    ");
    $stmt->execute([$account_type]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$stats = [];
if ($account_type !== 'all') {
    $stats_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_accounts,
            SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END) as default_count,
            SUM(current_balance) as total_balance,
            SUM(total_credited) as total_credited,
            SUM(total_debited) as total_debited,
            MAX(last_transaction_at) as last_transaction
        FROM admin_accounts 
        WHERE account_type = ? AND is_active = 1
    ");
    $stats_stmt->execute([$account_type]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get transaction history for this account type
$transactions = [];
if ($account_type !== 'all') {
    $txn_stmt = $db->prepare("
        SELECT 
            abh.*,
            aa.account_name
        FROM account_balance_history abh
        JOIN admin_accounts aa ON abh.admin_account_id = aa.id
        WHERE aa.account_type = ?
        ORDER BY abh.created_at DESC
        LIMIT 20
    ");
    $txn_stmt->execute([$account_type]);
    $transactions = $txn_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --success: #06d6a0;
    --success-dark: #0ca678;
    --warning: #ffb703;
    --warning-dark: #f77f00;
    --danger: #ef476f;
    --danger-dark: #d62828;
    --info: #4cc9f0;
    --info-dark: #0096c7;
    --dark: #2b2d42;
    --light: #f8f9fa;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
}

/* Header Styles */
.account-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 2rem 2.5rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
    position: relative;
    overflow: hidden;
}

.account-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse 8s ease infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.8; }
}

.account-header .header-icon {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.account-header h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.account-header p {
    font-size: 1rem;
    opacity: 0.9;
    margin-bottom: 0;
}

/* Navigation Styles */
.type-nav {
    background: white;
    border-radius: 50px;
    padding: 0.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
    border: 1px solid var(--gray-200);
}

.type-nav .nav {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.25rem;
}

.type-nav .nav-link {
    border-radius: 50px;
    padding: 0.6rem 1.5rem;
    color: var(--gray-600);
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.type-nav .nav-link:hover {
    background: rgba(67, 97, 238, 0.05);
    color: var(--primary);
    transform: translateY(-2px);
}

.type-nav .nav-link.active {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.type-nav .nav-link i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    height: 100%;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 30px rgba(67, 97, 238, 0.1);
    border-color: var(--primary);
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, transparent, rgba(67, 97, 238, 0.03));
    border-radius: 50%;
    transform: translate(30px, -30px);
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.stat-label {
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--gray-200);
    font-size: 0.85rem;
    color: var(--gray-600);
}

/* Account Cards */
.account-card {
    background: white;
    border-radius: 20px;
    border: 1px solid var(--gray-200);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    height: 100%;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.02);
}

.account-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 30px rgba(67, 97, 238, 0.1);
    border-color: var(--primary);
}

.account-card.default {
    border: 2px solid var(--success);
    box-shadow: 0 10px 25px rgba(6, 214, 160, 0.15);
}

.account-card .card-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 2;
}

.account-card .default-badge {
    background: linear-gradient(135deg, var(--success), var(--success-dark));
    color: white;
    padding: 0.35rem 1rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    box-shadow: 0 5px 10px rgba(6, 214, 160, 0.3);
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.account-card .default-badge i {
    font-size: 0.6rem;
}

.account-card .status-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    z-index: 2;
    padding: 0.35rem 1rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.account-card .status-badge.active {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.account-card .status-badge.inactive {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.account-card .card-body {
    padding: 2rem 1.5rem 1.5rem;
}

.account-card .account-icon {
    width: 60px;
    height: 60px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.account-card:hover .account-icon {
    transform: scale(1.1) rotate(5deg);
}

.account-card .account-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.account-card .account-id {
    font-size: 0.8rem;
    color: var(--gray-500);
    margin-bottom: 1rem;
}

.account-card .detail-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    color: var(--gray-600);
}

.account-card .detail-row i {
    width: 20px;
    color: var(--gray-400);
    font-size: 0.9rem;
}

.account-card .balance-section {
    background: linear-gradient(135deg, var(--gray-100), white);
    padding: 1.25rem;
    border-radius: 16px;
    margin: 1.25rem 0;
    text-align: center;
    border: 1px solid var(--gray-200);
}

.account-card .balance {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
    line-height: 1.2;
    margin-bottom: 0.25rem;
}

.account-card .balance-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.account-card .card-footer {
    background: transparent;
    border-top: 1px solid var(--gray-200);
    padding: 1rem 1.5rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.account-card .date {
    font-size: 0.8rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.account-card .btn-group {
    display: flex;
    gap: 0.5rem;
}

.account-card .btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-600);
    transition: all 0.2s ease;
    cursor: pointer;
}

.account-card .btn-icon:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 5px 10px rgba(67, 97, 238, 0.2);
}

.account-card .btn-icon.delete:hover {
    background: var(--danger);
    border-color: var(--danger);
}

.account-card .btn-icon.default:hover {
    background: var(--success);
    border-color: var(--success);
}

/* Transaction List */
.transaction-list {
    background: white;
    border-radius: 20px;
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.02);
}

.transaction-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 1.25rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.transaction-header h5 {
    margin: 0;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.transaction-header h5 i {
    font-size: 1.25rem;
}

.transaction-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    transition: background 0.3s ease;
}

.transaction-item:last-child {
    border-bottom: none;
}

.transaction-item:hover {
    background: rgba(67, 97, 238, 0.02);
}

.transaction-info {
    flex: 1;
}

.transaction-info .account {
    font-weight: 600;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
}

.transaction-info .meta {
    font-size: 0.8rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.transaction-info .meta i {
    font-size: 0.7rem;
}

.transaction-amount {
    text-align: right;
}

.transaction-amount .amount {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.transaction-amount .amount.credit {
    color: var(--success);
}

.transaction-amount .amount.debit {
    color: var(--danger);
}

.transaction-amount .balance {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 20px;
    border: 2px dashed var(--gray-300);
}

.empty-state i {
    font-size: 4rem;
    color: var(--gray-300);
    margin-bottom: 1.5rem;
}

.empty-state h5 {
    font-size: 1.25rem;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--gray-500);
    margin-bottom: 1.5rem;
}

.empty-state .btn {
    padding: 0.75rem 2rem;
    font-weight: 500;
    border-radius: 12px;
}

/* Modal Styles */
.modal-content {
    border-radius: 24px;
    border: none;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
}

.modal-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 0;
    padding: 1.5rem 2rem;
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.modal-header .modal-title {
    font-weight: 600;
    font-size: 1.25rem;
    position: relative;
    z-index: 1;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
    transition: all 0.3s ease;
    position: relative;
    z-index: 1;
}

.modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid var(--gray-200);
    padding: 1.5rem 2rem;
}

/* Form Styles */
.form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-label .text-danger {
    color: var(--danger);
}

.form-control, .form-select {
    border-radius: 12px;
    border: 1.5px solid var(--gray-200);
    padding: 0.6rem 1rem;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.form-control:hover, .form-select:hover {
    border-color: var(--primary);
}

.form-check-input {
    width: 1.2rem;
    height: 1.2rem;
    margin-top: 0.15rem;
    border: 1.5px solid var(--gray-300);
    transition: all 0.2s ease;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.form-check-input:focus {
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    border-color: var(--primary);
}

.form-check-label {
    color: var(--gray-700);
    font-weight: 500;
    margin-left: 0.5rem;
    cursor: pointer;
}

/* Button Styles */
.btn {
    border-radius: 12px;
    padding: 0.6rem 1.5rem;
    font-weight: 500;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.btn::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn:hover::before {
    width: 300px;
    height: 300px;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border: none;
    color: white;
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(67, 97, 238, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, var(--success), var(--success-dark));
    border: none;
    color: white;
    box-shadow: 0 5px 15px rgba(6, 214, 160, 0.3);
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(6, 214, 160, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), var(--danger-dark));
    border: none;
    color: white;
    box-shadow: 0 5px 15px rgba(239, 71, 111, 0.3);
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 71, 111, 0.4);
}

.btn-outline-primary {
    background: transparent;
    border: 1.5px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
}

.btn-light {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.btn-light:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

/* Alert Styles */
.alert {
    border-radius: 16px;
    border: none;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
}

.alert-success {
    background: rgba(6, 214, 160, 0.1);
    color: var(--success);
    border-left: 4px solid var(--success);
}

.alert-danger {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

.alert i {
    margin-right: 0.5rem;
}

/* Animation */
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

.account-card {
    animation: slideIn 0.5s ease forwards;
}

/* Responsive */
@media (max-width: 768px) {
    .account-header {
        padding: 1.5rem;
    }
    
    .account-header h2 {
        font-size: 1.5rem;
    }
    
    .type-nav .nav-link {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
    }
    
    .type-nav .nav-link i {
        margin-right: 0.25rem;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .account-card .card-body {
        padding: 1.5rem 1rem 1rem;
    }
    
    .account-card .account-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .balance-section {
        padding: 1rem;
    }
    
    .balance {
        font-size: 1.5rem;
    }
}
</style>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="account-header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-4">
                <?php if ($account_type !== 'all' && isset($account_types[$account_type])): ?>
                    <div class="header-icon">
                        <i class="<?php echo $account_types[$account_type]['icon']; ?>"></i>
                    </div>
                    <div>
                        <h2 class="mb-1"><?php echo $account_types[$account_type]['name']; ?> Accounts</h2>
                        <p class="mb-0 opacity-75">Manage your <?php echo $account_types[$account_type]['name']; ?> payment accounts</p>
                    </div>
                <?php else: ?>
                    <div class="header-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div>
                        <h2 class="mb-1">All Payment Accounts</h2>
                        <p class="mb-0 opacity-75">Manage all payment gateway accounts</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Dashboard
                </a>
                <?php if ($account_type !== 'all'): ?>
                    <button class="btn btn-light" onclick="openAddModal()">
                        <i class="fas fa-plus me-2"></i>Add Account
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Type Navigation -->
    <div class="type-nav">
        <div class="nav">
            <a class="nav-link <?php echo $account_type === 'all' ? 'active' : ''; ?>" href="accounts.php?type=all">
                <i class="fas fa-th-large"></i>All Accounts
            </a>
            <?php foreach ($account_types as $type => $info): ?>
                <a class="nav-link <?php echo $account_type === $type ? 'active' : ''; ?>" 
                   href="accounts.php?type=<?php echo $type; ?>"
                   style="<?php echo $account_type === $type ? 'background: linear-gradient(135deg, ' . $info['color'] . ', ' . $info['color'] . 'dd);' : ''; ?>">
                    <i class="<?php echo $info['icon']; ?>"></i><?php echo $info['name']; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($account_type !== 'all'): ?>
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_accounts'] ?? 0; ?></div>
                    <div class="stat-label">Total Accounts</div>
                    <div class="stat-footer">
                        <i class="fas fa-star text-warning me-1"></i>
                        <?php echo $stats['default_count'] ?? 0; ?> default
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_balance'] ?? 0, 2); ?></div>
                    <div class="stat-label">Current Balance</div>
                    <div class="stat-footer">
                        <i class="fas fa-clock text-info me-1"></i>
                        Last updated: <?php echo $stats['last_transaction'] ? date('d M', strtotime($stats['last_transaction'])) : 'Never'; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_credited'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Credited</div>
                    <div class="stat-footer">
                        <i class="fas fa-arrow-up text-success me-1"></i>
                        Incoming payments
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_debited'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Debited</div>
                    <div class="stat-footer">
                        <i class="fas fa-arrow-down text-danger me-1"></i>
                        Withdrawals & payouts
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Accounts Display -->
    <?php if ($account_type === 'all'): ?>
        <!-- Grouped by Type -->
        <?php foreach ($grouped_accounts as $type => $type_accounts): ?>
            <div class="card shadow-sm mb-4" style="border: none; border-radius: 20px; overflow: hidden;">
                <div class="card-header bg-white" style="border-bottom: 1px solid var(--gray-200); padding: 1.25rem 1.5rem;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="width: 40px; height: 40px; border-radius: 12px; background: <?php echo $account_types[$type]['bg_color']; ?>; display: flex; align-items: center; justify-content: center;">
                                <i class="<?php echo $account_types[$type]['icon']; ?>" style="color: <?php echo $account_types[$type]['color']; ?>; font-size: 1.2rem;"></i>
                            </span>
                            <span style="font-weight: 600; color: var(--gray-800);"><?php echo $account_types[$type]['name']; ?> Accounts</span>
                            <span class="badge" style="background: var(--gray-100); color: var(--gray-600); padding: 0.35rem 0.75rem; border-radius: 50px; font-weight: 500;">
                                <?php echo count($type_accounts); ?> accounts
                            </span>
                        </h5>
                        <a href="accounts.php?type=<?php echo $type; ?>" class="btn btn-outline-primary btn-sm">
                            View All <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="row g-4">
                        <?php foreach ($type_accounts as $account): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="account-card">
                                    <?php if ($account['is_default']): ?>
                                        <div class="card-badge">
                                            <span class="default-badge">
                                                <i class="fas fa-star"></i> Default
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <span class="status-badge <?php echo $account['is_active'] ? 'active' : 'inactive'; ?>">
                                        <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                        <?php echo $account['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                    
                                    <div class="card-body">
                                        <div class="d-flex align-items-center gap-3 mb-3">
                                            <div class="account-icon" style="background: linear-gradient(135deg, <?php echo $account_types[$account['account_type']]['color']; ?>, <?php echo $account_types[$account['account_type']]['color']; ?>dd);">
                                                <i class="<?php echo $account_types[$account['account_type']]['icon']; ?>"></i>
                                            </div>
                                            <div>
                                                <div class="account-name"><?php echo htmlspecialchars($account['account_name']); ?></div>
                                                <div class="account-id">ID: #<?php echo $account['id']; ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="detail-row">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo $account['account_email'] ? htmlspecialchars($account['account_email']) : 'No email'; ?></span>
                                        </div>
                                        
                                        <?php if ($account['account_number']): ?>
                                            <div class="detail-row">
                                                <i class="fas fa-credit-card"></i>
                                                <span>**** <?php echo substr($account['account_number'], -4); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($account['phone_number']): ?>
                                            <div class="detail-row">
                                                <i class="fas fa-phone"></i>
                                                <span><?php echo htmlspecialchars($account['phone_number']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="balance-section">
                                            <div class="balance">$<?php echo number_format($account['current_balance'], 2); ?></div>
                                            <div class="balance-label">Current Balance</div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer">
                                        <div class="date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php echo date('d M Y', strtotime($account['created_at'])); ?>
                                        </div>
                                        <div class="btn-group">
                                            <a href="accounts.php?type=<?php echo $account['account_type']; ?>" class="btn-icon" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <!-- Single Type View -->
        <div class="row g-4">
            <?php if (empty($accounts)): ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="<?php echo $account_types[$account_type]['icon']; ?>"></i>
                        <h5>No <?php echo $account_types[$account_type]['name']; ?> Accounts Found</h5>
                        <p class="text-muted">Click the button below to add your first <?php echo $account_types[$account_type]['name']; ?> account.</p>
                        <button class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus me-2"></i>Add <?php echo $account_types[$account_type]['name']; ?> Account
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($accounts as $account): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="account-card <?php echo $account['is_default'] ? 'default' : ''; ?>">
                            <?php if ($account['is_default']): ?>
                                <div class="card-badge">
                                    <span class="default-badge">
                                        <i class="fas fa-star"></i> Default
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <span class="status-badge <?php echo $account['is_active'] ? 'active' : 'inactive'; ?>">
                                <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                <?php echo $account['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                            
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="account-icon" style="background: linear-gradient(135deg, <?php echo $account_types[$account['account_type']]['color']; ?>, <?php echo $account_types[$account['account_type']]['color']; ?>dd);">
                                        <i class="<?php echo $account_types[$account['account_type']]['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="account-name"><?php echo htmlspecialchars($account['account_name']); ?></div>
                                        <div class="account-id">ID: #<?php echo $account['id']; ?></div>
                                    </div>
                                </div>
                                
                                <?php if ($account['account_email']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($account['account_email']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($account['phone_number']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($account['phone_number']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($account['account_number']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-credit-card"></i>
                                        <span>**** <?php echo substr($account['account_number'], -4); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($account['bank_name']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-university"></i>
                                        <span><?php echo htmlspecialchars($account['bank_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($account['swift_code']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-code"></i>
                                        <span>SWIFT: <?php echo htmlspecialchars($account['swift_code']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($account['iban']): ?>
                                    <div class="detail-row">
                                        <i class="fas fa-globe"></i>
                                        <span>IBAN: <?php echo htmlspecialchars($account['iban']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="balance-section">
                                    <div class="balance">$<?php echo number_format($account['current_balance'], 2); ?></div>
                                    <div class="balance-label">Current Balance</div>
                                </div>
                                
                                <?php if ($account['notes']): ?>
                                    <div class="detail-row" style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed var(--gray-200);">
                                        <i class="fas fa-sticky-note"></i>
                                        <span style="font-size: 0.85rem;"><?php echo htmlspecialchars($account['notes']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer">
                                <div class="date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('d M Y', strtotime($account['created_at'])); ?>
                                    <?php if ($account['last_transaction_at']): ?>
                                        <br>
                                        <i class="far fa-clock"></i>
                                        Last txn: <?php echo date('d M', strtotime($account['last_transaction_at'])); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <button class="btn-icon" onclick="editAccount(<?php echo htmlspecialchars(json_encode($account)); ?>)" title="Edit Account">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (!$account['is_default']): ?>
                                        <button class="btn-icon default" onclick="setDefault(<?php echo $account['id']; ?>)" title="Set as Default">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-icon delete" onclick="deleteAccount(<?php echo $account['id']; ?>)" title="Delete Account">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Transaction History -->
        <?php if (!empty($transactions)): ?>
            <div class="transaction-list mt-4">
                <div class="transaction-header">
                    <h5>
                        <i class="fas fa-history"></i>
                        Recent Transactions - <?php echo $account_types[$account_type]['name']; ?>
                    </h5>
                    <span class="badge bg-white text-primary">Last 20 transactions</span>
                </div>
                <div class="transaction-items">
                    <?php foreach ($transactions as $txn): ?>
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <div class="account"><?php echo htmlspecialchars($txn['account_name']); ?></div>
                                <div class="meta">
                                    <span><i class="far fa-clock"></i> <?php echo date('d M Y H:i', strtotime($txn['created_at'])); ?></span>
                                    <span><i class="fas fa-tag"></i> <?php echo ucfirst($txn['change_type']); ?></span>
                                    <?php if ($txn['reference_type']): ?>
                                        <span><i class="fas fa-link"></i> <?php echo $txn['reference_type']; ?> #<?php echo $txn['reference_id']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="transaction-amount">
                                <div class="amount <?php echo $txn['change_type']; ?>">
                                    <?php echo $txn['change_type'] === 'credit' ? '+' : '-'; ?>$<?php echo number_format(abs($txn['change_amount']), 2); ?>
                                </div>
                                <div class="balance">Balance: $<?php echo number_format($txn['balance'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add <?php echo $account_types[$account_type]['name']; ?> Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="accountForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="action" value="add">
                    <input type="hidden" name="id" id="accountId" value="0">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" name="account_name" id="account_name" class="form-control" required>
                            <small class="text-muted">A descriptive name for this account</small>
                        </div>
                        
                        <?php if (in_array('account_email', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Account Email</label>
                                <input type="email" name="account_email" id="account_email" class="form-control">
                                <small class="text-muted">PayPal/Stripe account email</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('phone_number', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="phone_number" id="phone_number" class="form-control" required>
                                <small class="text-muted">Mobile wallet number</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('account_number', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_number" id="account_number" class="form-control">
                                <small class="text-muted">Card/account number</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('account_holder', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Account Holder Name</label>
                                <input type="text" name="account_holder" id="account_holder" class="form-control">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('bank_name', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" id="bank_name" class="form-control">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('swift_code', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">SWIFT Code</label>
                                <input type="text" name="swift_code" id="swift_code" class="form-control">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('routing_number', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Routing Number</label>
                                <input type="text" name="routing_number" id="routing_number" class="form-control">
                            </div>
                        <?php endif; ?>
                        
                        <?php if (in_array('iban', $account_types[$account_type]['fields'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">IBAN</label>
                                <input type="text" name="iban" id="iban" class="form-control">
                            </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6">
                            <label class="form-label">CNIC/NIC</label>
                            <input type="text" name="cnic" id="cnic" class="form-control">
                            <small class="text-muted">Optional identification</small>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2" placeholder="Any additional information..."></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                <label class="form-check-label" for="is_active">
                                    Active Account
                                </label>
                                <small class="text-muted d-block">Account is ready to receive payments</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_default" id="is_default">
                                <label class="form-check-label" for="is_default">
                                    Set as Default Account
                                </label>
                                <small class="text-muted d-block">Primary account for this payment method</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--danger), var(--danger-dark));">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p class="mb-0">Are you sure you want to delete this account?</p>
                <small class="text-muted">This action cannot be undone.</small>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id" value="">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Account</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'Add <?php echo $account_types[$account_type]['name']; ?> Account';
    document.getElementById('action').value = 'add';
    document.getElementById('accountId').value = '0';
    document.getElementById('accountForm').reset();
    document.getElementById('is_active').checked = true;
    document.getElementById('is_default').checked = false;
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}

function editAccount(account) {
    document.getElementById('modalTitle').innerText = 'Edit <?php echo $account_types[$account_type]['name']; ?> Account';
    document.getElementById('action').value = 'edit';
    document.getElementById('accountId').value = account.id;
    document.getElementById('account_name').value = account.account_name || '';
    document.getElementById('account_email').value = account.account_email || '';
    document.getElementById('account_number').value = account.account_number || '';
    document.getElementById('account_holder').value = account.account_holder || '';
    document.getElementById('bank_name').value = account.bank_name || '';
    document.getElementById('swift_code').value = account.swift_code || '';
    document.getElementById('routing_number').value = account.routing_number || '';
    document.getElementById('iban').value = account.iban || '';
    document.getElementById('phone_number').value = account.phone_number || '';
    document.getElementById('cnic').value = account.cnic || '';
    document.getElementById('notes').value = account.notes || '';
    document.getElementById('is_active').checked = account.is_active == 1;
    document.getElementById('is_default').checked = account.is_default == 1;
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}

function deleteAccount(id) {
    document.getElementById('delete_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function setDefault(id) {
    if (confirm('Set this account as default?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="set_default">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleStatus(id, currentStatus) {
    const action = currentStatus ? 'deactivate' : 'activate';
    if (confirm(`Are you sure you want to ${action} this account?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>