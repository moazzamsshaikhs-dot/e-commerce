<?php
// admin/system/admin-new-accounts.php

// IMPORTANT: Sabse pehle output buffering start karo
ob_start();

require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

// Special check for system administrator
requireSystemAdmin();

$page_title = 'Admin Accounts Management';
require_once '../includes/header.php';

$db = getDB();
$message = '';
$message_type = 'success';

// Handle admin account creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_admin') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $subscription_plan = $_POST['subscription_plan'] ?? 'free';
        $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;
        
        // Validation
        $errors = [];
        if (empty($username)) $errors[] = 'Username required';
        if (empty($email)) $errors[] = 'Email required';
        if (empty($password)) $errors[] = 'Password required';
        
        if (empty($errors)) {
            try {
                $db->beginTransaction();
                
                // Check if username/email exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetch()) {
                    throw new Exception('Username or email already exists');
                }
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password, full_name, user_type, subscription_plan, created_at)
                    VALUES (?, ?, ?, ?, 'admin', ?, NOW())
                ");
                $stmt->execute([$username, $email, $hashed_password, $full_name, $subscription_plan]);
                
                $admin_id = $db->lastInsertId();
                
                // Insert into admin_system_access
                $stmt = $db->prepare("
                    INSERT INTO admin_system_access 
                    (admin_id, access_level, can_manage_accounts, can_manage_withdrawals, 
                     can_view_commissions, can_process_payments, can_manage_admins, is_super_admin, granted_by)
                    VALUES (?, 'full', ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $can_manage = $is_super_admin ? 1 : 0;
                $stmt->execute([
                    $admin_id,
                    $can_manage,
                    $can_manage,
                    1,
                    $can_manage,
                    $can_manage,
                    $is_super_admin,
                    $_SESSION['user_id']
                ]);
                
                logUserActivity($_SESSION['user_id'], 'admin_created', "Created admin account: {$username}");
                
                $db->commit();
                $_SESSION['success'] = "Admin account created successfully!";
                
            } catch(Exception $e) {
                $db->rollBack();
                $_SESSION['error'] = "Error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
        
        redirect('admin/system/admin-new-accounts.php');
        exit();
    }
    
    if ($action === 'update_access') {
        $admin_id = (int)$_POST['admin_id'];
        $can_manage_accounts = isset($_POST['can_manage_accounts']) ? 1 : 0;
        $can_manage_withdrawals = isset($_POST['can_manage_withdrawals']) ? 1 : 0;
        $can_process_payments = isset($_POST['can_process_payments']) ? 1 : 0;
        $can_manage_admins = isset($_POST['can_manage_admins']) ? 1 : 0;
        $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("
                UPDATE admin_system_access SET
                    can_manage_accounts = ?,
                    can_manage_withdrawals = ?,
                    can_process_payments = ?,
                    can_manage_admins = ?,
                    is_super_admin = ?
                WHERE admin_id = ?
            ");
            $stmt->execute([
                $can_manage_accounts,
                $can_manage_withdrawals,
                $can_process_payments,
                $can_manage_admins,
                $is_super_admin,
                $admin_id
            ]);
            
            logUserActivity($_SESSION['user_id'], 'admin_updated', "Updated admin access for ID: {$admin_id}");
            $_SESSION['success'] = "Admin access updated successfully!";
            
        } catch(Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        redirect('admin/system/admin-new-accounts.php');
        exit();
    }
    
    if ($action === 'toggle_status') {
        $admin_id = (int)$_POST['admin_id'];
        
        try {
            $stmt = $db->prepare("UPDATE users SET account_status = NOT account_status WHERE id = ?");
            $stmt->execute([$admin_id]);
            
            $_SESSION['success'] = "Admin status toggled!";
            
        } catch(Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        redirect('admin/system/admin-new-accounts.php');
        exit();
    }
}

// Get all admins
$admins = $db->query("
    SELECT u.*, asa.*, 
           (SELECT COUNT(*) FROM users WHERE created_by = u.id) as admins_created
    FROM users u
    LEFT JOIN admin_system_access asa ON u.id = asa.admin_id
    WHERE u.user_type = 'admin'
    ORDER BY asa.is_super_admin DESC, u.created_at DESC
")->fetchAll();

// Get stats
$stats = [];
$stats_query = $db->query("
    SELECT 
        COUNT(*) as total_admins,
        COUNT(CASE WHEN asa.is_super_admin = 1 THEN 1 END) as super_admins,
        COUNT(CASE WHEN u.account_status = 'active' THEN 1 END) as active_admins
    FROM users u
    LEFT JOIN admin_system_access asa ON u.id = asa.admin_id
    WHERE u.user_type = 'admin'
");
$stats = $stats_query->fetch(PDO::FETCH_ASSOC);
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

/* Main Layout */
.admin-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border-left: 4px solid var(--primary);
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, transparent 50%, rgba(255,255,255,0.1) 100%);
    border-radius: 50%;
}

.stat-value {
    font-size: 32px;
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
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Admin Grid */
.admin-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.admin-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

.admin-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
}

.admin-card.super-admin {
    border: 2px solid var(--warning);
    position: relative;
}

.admin-card.super-admin::before {
    content: '👑';
    position: absolute;
    top: -10px;
    right: -10px;
    font-size: 40px;
    opacity: 0.1;
    transform: rotate(15deg);
}

.admin-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.admin-avatar {
    width: 55px;
    height: 55px;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--primary) 0%, var(--info) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 600;
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.admin-info {
    flex: 1;
}

.admin-info h5 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
}

.admin-info .admin-email {
    font-size: 12px;
    color: var(--dark);
    opacity: 0.7;
    margin-bottom: 6px;
}

/* Admin Badges */
.admin-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    margin-right: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-super {
    background: var(--warning-light);
    color: var(--warning-dark);
    border: 1px solid var(--warning);
}

.badge-plan {
    background: var(--info-light);
    color: var(--info-dark);
    border: 1px solid var(--info);
}

.badge-active {
    background: var(--success-light);
    color: var(--success-dark);
    border: 1px solid var(--success);
}

.badge-inactive {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
}

/* Access List */
.access-list {
    margin: 15px 0;
    padding: 15px;
    background: var(--light);
    border-radius: var(--radius);
    border: 1px solid var(--border);
}

.access-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border);
}

.access-item:last-child {
    border-bottom: none;
}

.access-label {
    font-size: 12px;
    color: var(--dark);
    opacity: 0.7;
    display: flex;
    align-items: center;
    gap: 6px;
}

.access-label i {
    width: 16px;
    color: var(--primary);
}

.access-value {
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 12px;
}

.access-value.yes {
    background: var(--success-light);
    color: var(--success-dark);
}

.access-value.no {
    background: var(--danger-light);
    color: var(--danger-dark);
}

/* Buttons */
.btn-add {
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.btn-add:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
    color: white;
}

.btn-edit {
    background: var(--info);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
}

.btn-edit:hover {
    background: var(--info-dark);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(76, 201, 240, 0.3);
    color: white;
}

/* Alerts */
.alert-success {
    background: var(--success-light);
    color: var(--success-dark);
    border: 1px solid var(--success);
    border-radius: var(--radius);
}

.alert-danger {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
    border-radius: var(--radius);
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

.modal-header.bg-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
}

.modal-header.bg-info {
    background: linear-gradient(135deg, var(--info) 0%, var(--info-dark) 100%) !important;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--border);
    background: var(--light);
}

/* Form Controls */
.form-control, .form-select {
    border-radius: var(--radius);
    border: 2px solid var(--border);
    padding: 10px 15px;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
    outline: none;
}

.form-check-input {
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.form-label {
    color: var(--dark);
    font-weight: 500;
    margin-bottom: 8px;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-container {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .admin-grid {
        grid-template-columns: 1fr;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 15px;
    }
    
    .btn-add {
        width: 100%;
        justify-content: center;
    }
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

.admin-card {
    animation: slideIn 0.3s ease forwards;
}

.admin-card:nth-child(1) { animation-delay: 0.1s; }
.admin-card:nth-child(2) { animation-delay: 0.15s; }
.admin-card:nth-child(3) { animation-delay: 0.2s; }
.admin-card:nth-child(4) { animation-delay: 0.25s; }
.admin-card:nth-child(5) { animation-delay: 0.3s; }
</style>

<div class="admin-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-user-shield me-2" style="color: var(--primary);"></i>
                Admin Accounts Management
            </h1>
            <p class="text-muted mb-0">Create and manage administrator accounts</p>
        </div>
        <div class="flex-1 gap-2">
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-users me-1"></i> View All Admins
            </a>
            <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                <i class="fas fa-user-plus me-1"></i> Add New Admin
            </button>
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

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['total_admins'] ?? 0; ?></div>
            <div class="stat-label">Total Admins</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['super_admins'] ?? 0; ?></div>
            <div class="stat-label">Super Admins</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $stats['active_admins'] ?? 0; ?></div>
            <div class="stat-label">Active Admins</div>
        </div>
    </div>

    <!-- Admin Grid -->
    <div class="admin-grid">
        <?php foreach ($admins as $admin): ?>
        <div class="admin-card <?php echo ($admin['is_super_admin'] ?? 0) ? 'super-admin' : ''; ?>">
            <div class="admin-header">
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($admin['username'] ?? 'A', 0, 1)); ?>
                </div>
                <div class="admin-info">
                    <h5><?php echo htmlspecialchars($admin['full_name'] ?? $admin['username']); ?></h5>
                    <div class="admin-email"><?php echo htmlspecialchars($admin['email']); ?></div>
                    <div class="mt-1">
                        <?php if ($admin['is_super_admin'] ?? 0): ?>
                            <span class="admin-badge badge-super"><i class="fas fa-crown me-1"></i> SUPER</span>
                        <?php endif; ?>
                        <span class="admin-badge badge-plan"><?php echo ucfirst($admin['subscription_plan'] ?? 'free'); ?></span>
                        <span class="admin-badge <?php echo ($admin['account_status'] ?? 'active') == 'active' ? 'badge-active' : 'badge-inactive'; ?>">
                            <i class="fas fa-<?php echo ($admin['account_status'] ?? 'active') == 'active' ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                            <?php echo ucfirst($admin['account_status'] ?? 'active'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="access-list">
                <div class="access-item">
                    <span class="access-label">
                        <i class="fas fa-credit-card"></i> Manage Accounts
                    </span>
                    <span class="access-value <?php echo ($admin['can_manage_accounts'] ?? 0) ? 'yes' : 'no'; ?>">
                        <?php echo ($admin['can_manage_accounts'] ?? 0) ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div class="access-item">
                    <span class="access-label">
                        <i class="fas fa-hand-holding-usd"></i> Manage Withdrawals
                    </span>
                    <span class="access-value <?php echo ($admin['can_manage_withdrawals'] ?? 0) ? 'yes' : 'no'; ?>">
                        <?php echo ($admin['can_manage_withdrawals'] ?? 0) ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div class="access-item">
                    <span class="access-label">
                        <i class="fas fa-exchange-alt"></i> Process Payments
                    </span>
                    <span class="access-value <?php echo ($admin['can_process_payments'] ?? 0) ? 'yes' : 'no'; ?>">
                        <?php echo ($admin['can_process_payments'] ?? 0) ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                <div class="access-item">
                    <span class="access-label">
                        <i class="fas fa-user-shield"></i> Manage Admins
                    </span>
                    <span class="access-value <?php echo ($admin['can_manage_admins'] ?? 0) ? 'yes' : 'no'; ?>">
                        <?php echo ($admin['can_manage_admins'] ?? 0) ? 'Yes' : 'No'; ?>
                    </span>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
                <button class="btn-edit" onclick="editAccess(<?php echo htmlspecialchars(json_encode($admin)); ?>)">
                    <i class="fas fa-edit me-1"></i> Edit Access
                </button>
                <button class="btn-edit" style="background: var(--warning);" onclick="toggleStatus(<?php echo $admin['id']; ?>)">
                    <i class="fas fa-power-off me-1"></i> Toggle Status
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="admin-new-accounts.php">
                <input type="hidden" name="action" value="add_admin">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i> Add New Admin</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subscription Plan</label>
                        <select name="subscription_plan" class="form-select">
                            <option value="free">Free</option>
                            <option value="premium">Premium</option>
                            <option value="business">Business</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_super_admin" class="form-check-input" id="is_super_admin">
                            <label class="form-check-label" for="is_super_admin">
                                Grant Super Admin Privileges
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Access Modal -->
<div class="modal fade" id="editAccessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="admin-new-accounts.php">
                <input type="hidden" name="action" value="update_access">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-user-cog me-2"></i> Edit Admin Access</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="can_manage_accounts" class="form-check-input" id="can_manage_accounts">
                            <label class="form-check-label" for="can_manage_accounts">
                                Can Manage Accounts
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="can_manage_withdrawals" class="form-check-input" id="can_manage_withdrawals">
                            <label class="form-check-label" for="can_manage_withdrawals">
                                Can Manage Withdrawals
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="can_process_payments" class="form-check-input" id="can_process_payments">
                            <label class="form-check-label" for="can_process_payments">
                                Can Process Payments
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="can_manage_admins" class="form-check-input" id="can_manage_admins">
                            <label class="form-check-label" for="can_manage_admins">
                                Can Manage Other Admins
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_super_admin" class="form-check-input" id="edit_is_super_admin">
                            <label class="form-check-label" for="edit_is_super_admin">
                                Super Admin (Full Access)
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Access</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Form for Toggle Status -->
<form method="POST" id="toggleStatusForm" action="admin-new-accounts.php" style="display: none;">
    <input type="hidden" name="action" value="toggle_status">
    <input type="hidden" name="admin_id" id="toggle_admin_id">
</form>

<script>
function editAccess(admin) {
    document.getElementById('edit_admin_id').value = admin.id;
    document.getElementById('can_manage_accounts').checked = admin.can_manage_accounts == 1;
    document.getElementById('can_manage_withdrawals').checked = admin.can_manage_withdrawals == 1;
    document.getElementById('can_process_payments').checked = admin.can_process_payments == 1;
    document.getElementById('can_manage_admins').checked = admin.can_manage_admins == 1;
    document.getElementById('edit_is_super_admin').checked = admin.is_super_admin == 1;
    
    new bootstrap.Modal(document.getElementById('editAccessModal')).show();
}

function toggleStatus(id) {
    if (confirm('Toggle admin account status?')) {
        document.getElementById('toggle_admin_id').value = id;
        document.getElementById('toggleStatusForm').submit();
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>