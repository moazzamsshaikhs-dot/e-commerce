<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'System Logs';
require_once '../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$log_type = isset($_GET['log_type']) ? $_GET['log_type'] : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($log_type)) {
        $where[] = "activity_type = ?";
        $params[] = $log_type;
    }
    
    if (!empty($user_id)) {
        $where[] = "ua.user_id = ?";
        $params[] = $user_id;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(ua.created_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(ua.created_at) <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($search)) {
        $where[] = "(ua.description LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total 
                  FROM user_activities ua 
                  LEFT JOIN users u ON ua.user_id = u.id 
                  WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get logs with user info
    $logs_sql = "SELECT ua.*, u.full_name, u.username, u.email 
                 FROM user_activities ua 
                 LEFT JOIN users u ON ua.user_id = u.id 
                 WHERE $where_sql 
                 ORDER BY ua.created_at DESC 
                 LIMIT ? OFFSET ?";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($logs_sql);
    $stmt->execute($all_params);
    $logs = $stmt->fetchAll();
    
    // Get users for filter
    $stmt = $db->query("SELECT id, username, full_name FROM users ORDER BY username");
    $users = $stmt->fetchAll();
    
    // Get distinct activity types
    $stmt = $db->query("SELECT DISTINCT activity_type FROM user_activities WHERE activity_type != '' ORDER BY activity_type");
    $activity_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get today's logs count
    $stmt = $db->query("SELECT COUNT(*) as count FROM user_activities WHERE DATE(created_at) = CURDATE()");
    $today_logs = $stmt->fetch()['count'];
    
    // Get most active user
    $stmt = $db->query("SELECT u.username, COUNT(*) as count 
                        FROM user_activities ua 
                        JOIN users u ON ua.user_id = u.id 
                        GROUP BY ua.user_id 
                        ORDER BY count DESC 
                        LIMIT 1");
    $most_active = $stmt->fetch();
    
} catch(PDOException $e) {
    $error = 'Error loading logs: ' . $e->getMessage();
    $logs = [];
    $total_records = 0;
    $users = [];
    $activity_types = [];
    $today_logs = 0;
    $most_active = null;
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">System Logs</h1>
                <p class="text-muted mb-0">Audit trail of all system activities</p>
            </div>
            <div>
                <button class="btn btn-outline-danger me-2" onclick="clearLogs()">
                    <i class="fas fa-trash me-2"></i> Clear Logs
                </button>
                <button class="btn btn-outline-primary" onclick="exportLogs()">
                    <i class="fas fa-download me-2"></i> Export Logs
                </button>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo number_format($total_records); ?></h3>
                        <p class="text-muted mb-0">Total Logs</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo number_format($today_logs); ?></h3>
                        <p class="text-muted mb-0">Today's Logs</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo count($activity_types); ?></h3>
                        <p class="text-muted mb-0">Activity Types</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="fw-bold">
                            <?php if ($most_active): ?>
                            <?php echo $most_active['username']; ?>
                            <small class="d-block text-muted"><?php echo $most_active['count']; ?> activities</small>
                            <?php else: ?>
                            N/A
                            <?php endif; ?>
                        </h6>
                        <p class="text-muted mb-0">Most Active User</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <select name="log_type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach($activity_types as $type): ?>
                            <option value="<?php echo $type; ?>" <?php echo $log_type == $type ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" name="start_date" class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>" 
                               placeholder="Start Date">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" name="end_date" class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>" 
                               placeholder="End Date">
                    </div>
                    
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search in logs...">
                    </div>
                    
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Logs Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Activity Logs (<?php echo number_format($total_records); ?> records)
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="refreshLogs()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5>No Logs Found</h5>
                    <p class="text-muted">No activity logs match your criteria</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">ID</th>
                                <th width="15%">Time</th>
                                <th width="15%">User</th>
                                <th width="15%">Activity Type</th>
                                <th width="40%">Description</th>
                                <th width="10%">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($logs as $log): ?>
                            <tr>
                                <td>#<?php echo $log['id']; ?></td>
                                <td>
                                    <?php echo date('M d', strtotime($log['created_at'])); ?><br>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if ($log['user_id']): ?>
                                    <div><?php echo htmlspecialchars($log['username']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['full_name']); ?></small>
                                    <?php else: ?>
                                    <em class="text-muted">System</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                    switch($log['activity_type']) {
                                        case 'login': echo 'success'; break;
                                        case 'logout': echo 'warning'; break;
                                        case 'error': echo 'danger'; break;
                                        case 'create': echo 'info'; break;
                                        case 'update': echo 'primary'; break;
                                        case 'delete': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $log['activity_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 400px;">
                                        <?php echo htmlspecialchars($log['description']); ?>
                                    </div>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo $log['ip_address'] ?? 'N/A'; ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white border-0">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm justify-content-center mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" 
                                   href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Log Types Chart -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Activity Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    try {
                        $stmt = $db->query("SELECT activity_type, COUNT(*) as count 
                                           FROM user_activities 
                                           WHERE activity_type != '' 
                                           GROUP BY activity_type 
                                           ORDER BY count DESC 
                                           LIMIT 10");
                        $activity_counts = $stmt->fetchAll();
                        
                        foreach($activity_counts as $activity):
                            $percentage = ($activity['count'] / $total_records) * 100;
                    ?>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span><?php echo ucwords(str_replace('_', ' ', $activity['activity_type'])); ?></span>
                            <span><?php echo number_format($activity['count']); ?> (<?php echo round($percentage, 1); ?>%)</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar" role="progressbar" 
                                 style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php } catch(Exception $e) {} ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Clear logs
function clearLogs() {
    Swal.fire({
        title: 'Clear All Logs',
        text: 'Delete all system activity logs? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Clear All Logs'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/clear-logs.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cleared!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Export logs
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    
    Swal.fire({
        title: 'Export Logs',
        text: 'Export logs in which format?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        showDenyButton: true,
        denyButtonText: 'CSV',
        confirmButtonText: 'JSON'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`export-logs.php?${params.toString()}&format=json`, '_blank');
        } else if (result.isDenied) {
            window.open(`export-logs.php?${params.toString()}&format=csv`, '_blank');
        }
    });
}

// Refresh logs
function refreshLogs() {
    location.reload();
}

// Auto-refresh logs every 30 seconds (optional)
let autoRefresh = false;

function toggleAutoRefresh() {
    autoRefresh = !autoRefresh;
    if (autoRefresh) {
        setInterval(refreshLogs, 30000);
        document.getElementById('autoRefreshBtn').innerHTML = '<i class="fas fa-stop me-2"></i>Stop Auto-refresh';
    } else {
        document.getElementById('autoRefreshBtn').innerHTML = '<i class="fas fa-sync-alt me-2"></i>Auto-refresh (30s)';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>