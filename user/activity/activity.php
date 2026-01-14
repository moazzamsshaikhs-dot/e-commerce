<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'Activity Log';
require_once '../../includes/header.php';
$db = getDB();
$user_id = $_SESSION['user_id'];

// Get activity logs with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$stmt = $db->prepare("SELECT COUNT(*) as total FROM user_activities WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_activities = $stmt->fetch()['total'];
$total_pages = ceil($total_activities / $limit);

// Get activities
$stmt = $db->prepare("
    SELECT * FROM user_activities 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $user_id, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$activities = $stmt->fetchAll();

// Log activity
logUserActivity($user_id, 'activity_view', 'Viewed activity log');
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Activity Log</h1>
                    <p class="text-muted mb-0">Track your account activities</p>
                </div>
                <div>
                    <button class="btn btn-outline-danger" id="clearActivityBtn">
                        <i class="fas fa-trash me-2"></i> Clear All
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                            <i class="fas fa-history text-primary"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $total_activities; ?></h4>
                        <p class="text-muted small mb-0">Total Activities</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                            <i class="fas fa-sign-in-alt text-success"></i>
                        </div>
                        <h4 class="mb-1"><?php echo $_SESSION['login_count'] ?? 0; ?></h4>
                        <p class="text-muted small mb-0">Login Count</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                            <i class="fas fa-calendar-alt text-info"></i>
                        </div>
                        <h4 class="mb-1"><?php echo date('d M Y', strtotime($_SESSION['created_at'] ?? 'now')); ?></h4>
                        <p class="text-muted small mb-0">Joined On</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php if (empty($activities)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h4>No activities found</h4>
                        <p class="text-muted">Your activity log is empty</p>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($activities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-<?php 
                                    echo $activity['activity_type'] === 'login' ? 'success' : 
                                         (strpos($activity['activity_type'], 'error') !== false ? 'danger' : 'info'); 
                                ?>"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <h6 class="mb-0">
                                            <?php 
                                            // Format activity type for display
                                            $activity_type = str_replace('_', ' ', $activity['activity_type']);
                                            echo ucwords($activity_type);
                                            ?>
                                        </h6>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <small class="text-muted">
                                        <?php echo date('d M Y', strtotime($activity['created_at'])); ?>
                                        <?php if ($activity['ip_address']): ?>
                                            • IP: <?php echo $activity['ip_address']; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
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
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
}

.timeline-content {
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.timeline-item:last-child .timeline-content {
    border-bottom: none;
}
</style>

<script>
document.getElementById('clearActivityBtn').addEventListener('click', function() {
    if (confirm('Are you sure you want to clear all activity logs? This action cannot be undone.')) {
        fetch('activity-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'clear_all'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Activity logs cleared successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showToast(data.message || 'Error clearing logs', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error', 'error');
        });
    }
});

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>