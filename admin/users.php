<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('../index.php');
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

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "(full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($filter_type)) {
        $where[] = "user_type = ?";
        $params[] = $filter_type;
    }
    
    if (!empty($filter_plan)) {
        $where[] = "subscription_plan = ?";
        $params[] = $filter_plan;
    }
    
    $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total users count
    $count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetch()['total'];
    $total_pages = ceil($total_users / $limit);
    
    // Get users with pagination
    $users_sql = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $all_params = $params;
    $all_params[] = $limit;
    $all_params[] = $offset;
    
    $stmt = $db->prepare($users_sql);
    $stmt->execute($all_params);
    $users = $stmt->fetchAll();
    // Get user types for filter
    $stmte = $db->query("SELECT DISTINCT user_type FROM users WHERE user_type = 'admin'");
    $user_types = $stmte->fetchAll(PDO::FETCH_COLUMN);
    
    // Get subscription plans for filter
    $stmts = $db->query("SELECT DISTINCT subscription_plan FROM users WHERE subscription_plan != 'free'");
    $subscription_plans = $stmts->fetchAll(PDO::FETCH_COLUMN);
    
} 
catch(PDOException $e) {
    $error = 'Error loading users: ' . $e->getMessage();
    $users = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="container d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Manage Users</h1>
                <p class="text-muted mb-0">Total <?php echo $total_users; ?> users found</p>
            </div>
            <a href="user-add.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i> Add New User
            </a>
        </div>
        
        <!-- Filters Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" 
                               name="search" 
                               class="form-control" 
                               placeholder="Search by name, email or username"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach($user_types as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo $filter_type == $t ? 'selected' : ''; ?>>
                                <?php echo ucfirst($t); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="plan" class="form-select">
                            <option value="">All Plans</option>
                            <?php foreach($subscription_plans as $plan): ?>
                            <option value="<?php echo $plan; ?>" <?php echo $filter_plan == $plan ? 'selected' : ''; ?>>
                                <?php echo ucfirst($plan); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Filter
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="users.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo me-2"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0">User</th>
                                <th class="border-0">Email</th>
                                <th class="border-0">Type</th>
                                <th class="border-0">Plan</th>
                                <th class="border-0">Status</th>
                                <th class="border-0">Joined</th>
                                <th class="border-0 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $selectAll = "select * from users where user_type != 'admin'";
                            $conn = mysqli_connect("localhost","root","","ecommerce_db");
                            $users = mysqli_query($conn, $selectAll); 
                            if (empty($users)){ ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No users found</p>
                                </td>
                            </tr>
                            <?php } else{ ?>
                                <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo SITE_URL; ?>assets/images/avatars/<?php echo $user['profile_pic'] ?? 'default.png'; ?>" 
                                                 alt="Avatar" class="rounded-circle me-3" width="40" height="40">
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                                <small class="text-muted">@<?php echo htmlspecialchars($user['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['user_type'] == 'admin' ? 'danger' : 'primary'; ?>">
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $user['subscription_plan'] == 'premium' ? 'warning' : 
                                                 ($user['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo $user['subscription_plan'] == 'premium' ? 'crown' : 
                                                     ($user['subscription_plan'] == 'business' ? 'building' : 'user'); 
                                            ?> me-1"></i>
                                            <?php echo ucfirst($user['subscription_plan']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['account_status']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> Active
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times-circle me-1"></i> Inactive
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <a href="user-edit.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="user-view.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-sm btn-outline-info" 
                                               title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-danger" 
                                                    title="Delete"
                                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="card-footer border-0 bg-white">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" 
                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
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
        
        <!-- Quick Stats -->
        <div class="row mt-4">
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-primary mb-0">
                            <?php 
                            try {
                                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'user'");
                                echo $stmt->fetchColumn();
                            } catch(Exception $e) {
                                echo '0';
                            }
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Regular Users</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-danger mb-0">
                            <?php 
                            try {
                                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
                                echo $stmt->fetchColumn();
                            } catch(Exception $e) {
                                echo '0';
                            }
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Admins</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-warning mb-0">
                            <?php 
                            try {
                                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE subscription_plan = 'premium'");
                                echo $stmt->fetchColumn();
                            } catch(Exception $e) {
                                echo '0';
                            }
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Premium Users</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-success mb-0">
                            <?php 
                            try {
                                $stmt = $db->query("SELECT COUNT(*) FROM users WHERE account_status = 'active'");
                                echo $stmt->fetchColumn();
                            } catch(Exception $e) {
                                echo '0';
                            }
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Active Users</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<style>
    .sidbar {
    width: 250px;
    
}
    .main-content {
    flex: 1;
    padding: 20px;
    background: #f8f9fa;
    min-height: calc(100vh - 70px);
}
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background-color: #f8f9fa;
    }
</style>

<script>
function confirmDelete(userId, userName) {
    if (confirm('Are you sure you want to delete user "' + userName + '"? This action cannot be undone.')) {
        window.location.href = 'user-delete.php?id=' + userId;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>