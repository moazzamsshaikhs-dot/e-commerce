<?php
// admin/users/users.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: ../index.php');
    exit;
}

$page_title = 'Manage Users';
require_once '../includes/header.php';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_plan = isset($_GET['plan']) ? $_GET['plan'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

// Initialize variables
$total_regular = 0;
$total_vendors = 0;
$total_premium = 0;
$total_active = 0;
$total_suspended = 0;
$users = [];
$total_users = 0;
$total_pages = 0;
$error = '';

try {
    $db = getDB();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Build WHERE clause
    $where_conditions = [];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($filter_type) && $filter_type != 'all') {
        $where_conditions[] = "user_type = ?";
        $params[] = $filter_type;
    }

    if (!empty($filter_plan) && $filter_plan != 'all') {
        $where_conditions[] = "subscription_plan = ?";
        $params[] = $filter_plan;
    }

    if (!empty($filter_status) && $filter_status != 'all') {
        $where_conditions[] = "account_status = ?";
        $params[] = $filter_status;
    }

    // Build the WHERE clause
    $where_sql = '';
    if (!empty($where_conditions)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Get total users count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
    $stmt = $db->prepare($count_sql);

    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_users = $result ? (int)$result['total'] : 0;
    $total_pages = ceil($total_users / $limit);

    // FIXED: Get users with pagination - using direct values instead of parameters for LIMIT and OFFSET
    $users_sql = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($users_sql);

    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get counts for stats
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
    $total_regular = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'vendor'");
    $total_vendors = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE subscription_plan = 'premium'");
    $total_premium = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE account_status = 'active'");
    $total_active = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE account_status = 'suspended'");
    $total_suspended = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    $error = 'Database Error: ' . $e->getMessage();
    error_log("Users page error: " . $e->getMessage());
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    error_log("Users page error: " . $e->getMessage());
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

    .users-container {
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
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
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

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
    }

    .stat-card.primary {
        border-left-color: var(--primary);
    }

    .stat-card.success {
        border-left-color: var(--success);
    }

    .stat-card.warning {
        border-left-color: var(--warning);
    }

    .stat-card.danger {
        border-left-color: var(--danger);
    }

    .stat-card.info {
        border-left-color: var(--info);
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

    /* Filter Card */
    .filter-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
    }

    .filter-card .form-control,
    .filter-card .form-select {
        border-radius: 12px;
        border: 2px solid #edf2f9;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }

    .filter-card .form-control:focus,
    .filter-card .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
    }

    .filter-card .btn-filter {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s ease;
        width: 100%;
    }

    .filter-card .btn-filter:hover {
        background: #3651c4;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
    }

    .filter-card .btn-reset {
        background: #6c757d;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.3s ease;
        width: 100%;
    }

    .filter-card .btn-reset:hover {
        background: #5a6268;
        transform: translateY(-2px);
    }

    /* Users Table Card */
    .users-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
        margin-bottom: 30px;
    }

    .table-header {
        padding: 20px 25px;
        border-bottom: 1px solid #edf2f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .table-header h5 {
        font-weight: 600;
        color: var(--dark);
        margin: 0;
    }

    .table-responsive {
        padding: 0 25px 25px 25px;
    }

    .table {
        margin-bottom: 0;
    }

    .table th {
        background: #f8f9fa;
        font-weight: 600;
        color: var(--dark);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 15px 10px;
        border-bottom: 2px solid #edf2f9;
    }

    .table td {
        padding: 15px 10px;
        vertical-align: middle;
        border-bottom: 1px solid #edf2f9;
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background: #f8f9fa;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    /* User Avatar */
    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--info));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 600;
        color: white;
        margin-right: 12px;
        flex-shrink: 0;
    }

    .user-info h6 {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 2px;
    }

    .user-info small {
        color: #6c757d;
        font-size: 12px;
    }

    /* Badges */
    .badge-custom {
        padding: 6px 12px;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-user {
        background: rgba(67, 97, 238, 0.1);
        color: var(--primary);
    }

    .badge-vendor {
        background: rgba(255, 183, 3, 0.1);
        color: var(--warning);
    }

    .badge-admin {
        background: rgba(239, 71, 111, 0.1);
        color: var(--danger);
    }

    .badge-plan-free {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }

    .badge-plan-premium {
        background: rgba(255, 183, 3, 0.1);
        color: var(--warning);
    }

    .badge-plan-business {
        background: rgba(239, 71, 111, 0.1);
        color: var(--danger);
    }

    .badge-status-active {
        background: rgba(6, 214, 160, 0.1);
        color: var(--success);
    }

    .badge-status-suspended {
        background: rgba(239, 71, 111, 0.1);
        color: var(--danger);
    }

    .badge-status-deactivated {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }

    /* Action Buttons */
    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        border: none;
        color: white;
        margin: 0 3px;
        text-decoration: none;
    }

    .btn-action.edit {
        background: var(--primary);
    }

    .btn-action.view {
        background: var(--info);
    }

    .btn-action.delete {
        background: var(--danger);
    }

    .btn-action:hover {
        transform: translateY(-3px);
        filter: brightness(110%);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        color: white;
    }

    /* Pagination */
    .pagination {
        gap: 5px;
    }

    .page-link {
        border: none;
        border-radius: 10px !important;
        padding: 8px 14px;
        color: var(--dark);
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .page-link:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
    }

    .page-item.active .page-link {
        background: var(--primary);
        color: white;
    }

    .page-item.disabled .page-link {
        background: #e9ecef;
        color: #6c757d;
        pointer-events: none;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }

    .empty-state i {
        font-size: 60px;
        color: #dee2e6;
        margin-bottom: 20px;
    }

    .empty-state h5 {
        color: var(--dark);
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #6c757d;
        margin-bottom: 20px;
    }

    /* Error Alert */
    .error-alert {
        background: rgba(239, 71, 111, 0.1);
        color: var(--danger);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        border-left: 4px solid var(--danger);
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

    .animate-slide-in {
        animation: slideIn 0.5s ease forwards;
    }

    .delay-1 {
        animation-delay: 0.1s;
    }

    .delay-2 {
        animation-delay: 0.2s;
    }

    .delay-3 {
        animation-delay: 0.3s;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .users-container {
            padding: 20px;
        }

        .stats-grid {
            grid-template-columns: 1fr 1fr;
        }

        .table-responsive {
            padding: 0 15px 15px 15px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            font-size: 12px;
        }

        .table td {
            font-size: 13px;
        }
    }
</style>

<div class="users-container">
    <!-- Page Header -->
    <div class="page-header animate-slide-in">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h2 fw-bold mb-1">
                    <i class="fas fa-users-cog me-2 text-primary"></i>
                    User Management
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-users me-2"></i>
                    Total <strong><?php echo number_format($total_users); ?></strong> users found
                    <?php if (!empty($search) || ($filter_type && $filter_type != 'all') || ($filter_plan && $filter_plan != 'all') || ($filter_status && $filter_status != 'all')): ?>
                        <a href="users.php" class="ms-3 text-primary">
                            <i class="fas fa-times-circle me-1"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="../dashboard.php" class="btn btn-primary ">
                    <i class="fas fa-home me-2"></i>
                    Back
                </a>
                <a href="user-add.php" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i> Add New User
                </a>
            </div>
        </div>
    </div>

    <!-- Error Message -->
    <?php if (!empty($error)): ?>
        <div class="error-alert animate-slide-in delay-1">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid animate-slide-in delay-1">
        <div class="stat-card primary">
            <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1);">
                <i class="fas fa-users text-primary"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_regular); ?></div>
            <div class="stat-label">Regular Users</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon" style="background: rgba(255, 183, 3, 0.1);">
                <i class="fas fa-store text-warning"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_vendors); ?></div>
            <div class="stat-label">Vendors</div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon" style="background: rgba(6, 214, 160, 0.1);">
                <i class="fas fa-crown text-success"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_premium); ?></div>
            <div class="stat-label">Premium Users</div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon" style="background: rgba(76, 201, 240, 0.1);">
                <i class="fas fa-check-circle text-info"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_active); ?></div>
            <div class="stat-label">Active Users</div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon" style="background: rgba(239, 71, 111, 0.1);">
                <i class="fas fa-ban text-danger"></i>
            </div>
            <div class="stat-value"><?php echo number_format($total_suspended); ?></div>
            <div class="stat-label">Suspended</div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="filter-card animate-slide-in delay-2">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search by name, email or username..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="all">All Types</option>
                    <option value="user" <?php echo $filter_type == 'user' ? 'selected' : ''; ?>>Regular Users</option>
                    <option value="vendor" <?php echo $filter_type == 'vendor' ? 'selected' : ''; ?>>Vendors</option>
                    <option value="admin" <?php echo $filter_type == 'admin' ? 'selected' : ''; ?>>Admins</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="plan" class="form-select">
                    <option value="all">All Plans</option>
                    <option value="free" <?php echo $filter_plan == 'free' ? 'selected' : ''; ?>>Free</option>
                    <option value="premium" <?php echo $filter_plan == 'premium' ? 'selected' : ''; ?>>Premium</option>
                    <option value="business" <?php echo $filter_plan == 'business' ? 'selected' : ''; ?>>Business</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="all">All Status</option>
                    <option value="active" <?php echo $filter_status == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $filter_status == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="deactivated" <?php echo $filter_status == 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                    <a href="users.php" class="btn-reset">
                        <i class="fas fa-redo me-2"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Users Table Card -->
    <div class="users-card animate-slide-in delay-3">
        <div class="table-header">
            <h5>
                <i class="fas fa-list me-2 text-primary"></i>
                User List
            </h5>
            <span class="badge bg-primary"><?php echo number_format($total_users); ?> Total Users</span>
        </div>

        <div class="table-responsive">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fas fa-users-slash"></i>
                    <h5>No Users Found</h5>
                    <p>No users match your search criteria. Try adjusting your filters.</p>
                    <a href="user-add.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i> Add New User
                    </a>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-info">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h6>
                                            <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-envelope me-2 text-muted"></i>
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <?php if (!empty($user['phone'])): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-2"></i>
                                            <?php echo htmlspecialchars($user['phone']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-custom badge-<?php
                                                                    echo $user['user_type'] == 'admin' ? 'admin' : ($user['user_type'] == 'vendor' ? 'vendor' : 'user');
                                                                    ?>">
                                        <i class="fas fa-<?php
                                                            echo $user['user_type'] == 'admin' ? 'crown' : ($user['user_type'] == 'vendor' ? 'store' : 'user');
                                                            ?> me-1"></i>
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-custom badge-plan-<?php echo $user['subscription_plan']; ?>">
                                        <i class="fas fa-<?php
                                                            echo $user['subscription_plan'] == 'premium' ? 'crown' : ($user['subscription_plan'] == 'business' ? 'building' : 'user');
                                                            ?> me-1"></i>
                                        <?php echo ucfirst($user['subscription_plan']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-custom badge-status-<?php echo $user['account_status']; ?>">
                                        <i class="fas fa-<?php
                                                            echo $user['account_status'] == 'active' ? 'check-circle' : ($user['account_status'] == 'suspended' ? 'ban' : 'times-circle');
                                                            ?> me-1"></i>
                                        <?php echo ucfirst($user['account_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <i class="fas fa-calendar me-2 text-muted"></i>
                                    <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('h:i A', strtotime($user['created_at'])); ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end">
                                        <a href="user-edit.php?id=<?php echo $user['id']; ?>"
                                            class="btn-action edit"
                                            title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="user-view.php?id=<?php echo $user['id']; ?>"
                                            class="btn-action view"
                                            title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button"
                                                class="btn-action delete"
                                                title="Delete User"
                                                onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['full_name'] ?: $user['username'])); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer border-0 bg-white py-4">
                <nav aria-label="User pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link"
                                href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link"
                                    href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor;

                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
                        }
                        ?>

                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link"
                                href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger small">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    This action cannot be undone. All user data will be permanently removed.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Delete User</a>
            </div>
        </div>
    </div>
</div>

<script>
    function confirmDelete(userId, userName) {
        document.getElementById('deleteUserName').textContent = userName;
        document.getElementById('deleteConfirmBtn').href = 'user-delete.php?id=' + userId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    // Auto-hide alerts
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => {
            try {
                bootstrap.Alert.getOrCreateInstance(alert).close();
            } catch (e) {}
        });
    }, 5000);

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?>