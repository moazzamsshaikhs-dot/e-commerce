<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'All Notifications';
require_once '../../includes/header.php';

// Get vendor details and all notifications
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
    $stmt->execute([$vendor_id]);
    $total = $stmt->fetch()['total'];
    $total_pages = ceil($total / $per_page);
    
    // Get notifications with pagination
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $vendor_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $per_page, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll();
    
    // Get unread count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$vendor_id]);
    $unread_count = $stmt->fetch()['count'];
    
    // Get notification statistics by type
    $stmt = $db->prepare("
        SELECT 
            type,
            COUNT(*) as count,
            COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_count
        FROM notifications 
        WHERE user_id = ? 
        GROUP BY type
        ORDER BY count DESC
    ");
    $stmt->execute([$vendor_id]);
    $type_stats = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $notifications = [];
    $unread_count = 0;
    $type_stats = [];
    $total_pages = 0;
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">All Notifications</h1>
                <p class="text-muted mb-0">View your complete notification history</p>
            </div>
            <div class="btn-group">
                <a href="notifications.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Settings
                </a>
                <?php if ($unread_count > 0): ?>
                <form method="POST" action="notifications.php" class="d-inline">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-double me-2"></i> Mark All Read
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total</h6>
                                <h3 class="fw-bold text-primary"><?php echo $total; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-inbox text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Unread</h6>
                                <h3 class="fw-bold text-danger"><?php echo $unread_count; ?></h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded">
                                <i class="fas fa-bell text-danger fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">This Month</h6>
                                <h3 class="fw-bold text-success">
                                    <?php
                                    $month_count = 0;
                                    foreach($notifications as $note) {
                                        if (date('Y-m', strtotime($note['created_at'])) == date('Y-m')) {
                                            $month_count++;
                                        }
                                    }
                                    echo $month_count;
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-calendar-alt text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Pages</h6>
                                <h3 class="fw-bold text-warning"><?php echo $total_pages; ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-file text-warning fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters and Actions -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" id="filterType" onchange="filterNotifications()">
                            <option value="">All Types</option>
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterStatus" onchange="filterNotifications()">
                            <option value="">All Status</option>
                            <option value="unread">Unread</option>
                            <option value="read">Read</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" class="form-control" id="filterDate" onchange="filterNotifications()">
                    </div>
                    <div class="col-md-3">
                        <div class="btn-group w-100">
                            <button class="btn btn-outline-primary" onclick="exportNotifications()">
                                <i class="fas fa-download me-2"></i> Export
                            </button>
                            <button class="btn btn-outline-danger" onclick="clearOldNotifications()">
                                <i class="fas fa-trash me-2"></i> Clear Old
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notifications List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-list me-2"></i> All Notifications
                </h5>
                <div>
                    <button class="btn btn-sm btn-outline-secondary" onclick="selectAll()">
                        <i class="fas fa-check-square me-2"></i> Select All
                    </button>
                    <button class="btn btn-sm btn-outline-success ms-2" onclick="markSelectedAsRead()">
                        <i class="fas fa-check me-2"></i> Mark Selected Read
                    </button>
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="deleteSelected()">
                        <i class="fas fa-trash me-2"></i> Delete Selected
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Notifications Found</h5>
                    <p class="text-muted">You have no notifications in your history</p>
                </div>
                <?php else: ?>
                <form id="notificationsForm">
                    <div class="table-responsive">
                        <table class="table table-hover" id="notificationsTable">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                    </th>
                                    <th>Notification</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($notifications as $notification): 
                                    $type_colors = [
                                        'info' => 'primary',
                                        'success' => 'success',
                                        'warning' => 'warning',
                                        'error' => 'danger'
                                    ];
                                    $type_icons = [
                                        'info' => 'info-circle',
                                        'success' => 'check-circle',
                                        'warning' => 'exclamation-triangle',
                                        'error' => 'times-circle'
                                    ];
                                ?>
                                <tr data-type="<?php echo $notification['type']; ?>"
                                    data-status="<?php echo $notification['is_read'] ? 'read' : 'unread'; ?>"
                                    data-date="<?php echo date('Y-m-d', strtotime($notification['created_at'])); ?>">
                                    <td>
                                        <input type="checkbox" name="notification_ids[]" 
                                               value="<?php echo $notification['id']; ?>" 
                                               class="notification-checkbox">
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                            <p class="mb-0 text-muted small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $type_colors[$notification['type']] ?? 'secondary'; ?>">
                                            <i class="fas fa-<?php echo $type_icons[$notification['type']] ?? 'bell'; ?> me-1"></i>
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('d M Y', strtotime($notification['created_at'])); ?><br>
                                            <span class="text-muted"><?php echo date('h:i A', strtotime($notification['created_at'])); ?></span>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($notification['is_read']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i> Read
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-clock me-1"></i> Unread
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!$notification['is_read']): ?>
                                            <button type="button" class="btn btn-outline-success" 
                                                    onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-info" 
                                                    onclick="viewNotification(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Notifications pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mt-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Notifications by Type</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Total</th>
                                        <th>Unread</th>
                                        <th>Read</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_notifications = $total;
                                    foreach($type_stats as $stat): 
                                        $percentage = $total > 0 ? round(($stat['count'] / $total) * 100, 1) : 0;
                                        $read_count = $stat['count'] - $stat['unread_count'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $stat['type'] == 'info' ? 'primary' : 
                                                    ($stat['type'] == 'success' ? 'success' : 
                                                    ($stat['type'] == 'warning' ? 'warning' : 'danger')); 
                                            ?>">
                                                <?php echo ucfirst($stat['type']); ?>
                                            </span>
                                        </td>
                                        <td><strong><?php echo $stat['count']; ?></strong></td>
                                        <td>
                                            <?php if ($stat['unread_count'] > 0): ?>
                                            <span class="text-danger"><?php echo $stat['unread_count']; ?></span>
                                            <?php else: ?>
                                            <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $read_count; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 6px;">
                                                    <div class="progress-bar bg-<?php 
                                                        echo $stat['type'] == 'info' ? 'primary' : 
                                                            ($stat['type'] == 'success' ? 'success' : 
                                                            ($stat['type'] == 'warning' ? 'warning' : 'danger')); 
                                                    ?>" style="width: <?php echo $percentage; ?>%"></div>
                                                </div>
                                                <span class="text-muted small"><?php echo $percentage; ?>%</span>
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
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="markAllAsRead()">
                                <i class="fas fa-check-double me-2"></i> Mark All as Read
                            </button>
                            <button class="btn btn-outline-warning" onclick="markAllAsUnread()">
                                <i class="fas fa-clock me-2"></i> Mark All as Unread
                            </button>
                            <button class="btn btn-outline-danger" onclick="clearAllNotifications()">
                                <i class="fas fa-trash-alt me-2"></i> Clear All Notifications
                            </button>
                            <button class="btn btn-outline-info" onclick="downloadNotificationArchive()">
                                <i class="fas fa-archive me-2"></i> Download Archive
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-bell me-2"></i> Notification Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="notificationDetails">
                <!-- Content loaded via AJAX -->
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="markAsReadFromModal()">
                    <i class="fas fa-check me-2"></i> Mark as Read
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentNotificationId = null;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize any necessary functionality
});

function filterNotifications() {
    const type = document.getElementById('filterType').value;
    const status = document.getElementById('filterStatus').value;
    const date = document.getElementById('filterDate').value;
    
    const rows = document.querySelectorAll('#notificationsTable tbody tr');
    
    rows.forEach(row => {
        let show = true;
        
        if (type && row.dataset.type !== type) {
            show = false;
        }
        
        if (status && row.dataset.status !== status) {
            show = false;
        }
        
        if (date && row.dataset.date !== date) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function toggleSelectAll() {
    const selectAll = document.getElementById('selectAllCheckbox').checked;
    document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
        checkbox.checked = selectAll;
    });
}

function selectAll() {
    document.getElementById('selectAllCheckbox').checked = true;
    document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function markAsRead(notificationId) {
    fetch('action/mark-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    });
}

function markAsReadFromModal() {
    if (currentNotificationId) {
        markAsRead(currentNotificationId);
    }
}

function markSelectedAsRead() {
    const selectedIds = getSelectedNotificationIds();
    if (selectedIds.length === 0) {
        alert('Please select at least one notification.');
        return;
    }
    
    if (confirm('Mark ' + selectedIds.length + ' selected notification(s) as read?')) {
        fetch('action/mark-multiple-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function markAllAsRead() {
    if (confirm('Mark all notifications as read?')) {
        fetch('action/mark-all-read.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function markAllAsUnread() {
    if (confirm('Mark all notifications as unread?')) {
        fetch('action/mark-all-unread.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function deleteNotification(notificationId) {
    if (confirm('Delete this notification?')) {
        fetch('action/delete-notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'notification_id=' + notificationId
        })
    .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function deleteSelected() {
    const selectedIds = getSelectedNotificationIds();
    if (selectedIds.length === 0) {
        alert('Please select at least one notification.');
        return;
    }
    
    if (confirm('Delete ' + selectedIds.length + ' selected notification(s)? This action cannot be undone.')) {
        fetch('action/delete-multiple.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function clearAllNotifications() {
    if (confirm('Clear all notifications? This action cannot be undone.')) {
        fetch('action/clear-all.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function clearOldNotifications() {
    if (confirm('Clear notifications older than 30 days?')) {
        fetch('action/clear-old.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function viewNotification(notificationId) {
    currentNotificationId = notificationId;
    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
    
    fetch('action/get-notification.php?id=' + notificationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notification = data.data;
                document.getElementById('notificationDetails').innerHTML = `
                    <div class="mb-4">
                        <h4>${notification.title}</h4>
                        <p class="text-muted mb-3">
                            <i class="fas fa-clock me-1"></i>
                            ${notification.created_at_formatted}
                        </p>
                        <div class="alert alert-${notification.type}">
                            ${notification.message}
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Notification Details</h6>
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Status:</th>
                                    <td>
                                        <span class="badge bg-${notification.is_read ? 'success' : 'danger'}">
                                            <i class="fas fa-${notification.is_read ? 'check' : 'clock'} me-1"></i>
                                            ${notification.is_read ? 'Read' : 'Unread'}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Type:</th>
                                    <td>
                                        <span class="badge bg-${notification.type === 'info' ? 'primary' : 
                                                                 notification.type === 'success' ? 'success' : 
                                                                 notification.type === 'warning' ? 'warning' : 'danger'}">
                                            <i class="fas fa-${notification.type_icon} me-1"></i>
                                            ${notification.type_formatted}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Notification ID:</th>
                                    <td>#${notification.id}</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold">Actions</h6>
                            <div class="d-grid gap-2">
                                ${!notification.is_read ? `
                                <button class="btn btn-success" onclick="markAsRead(${notification.id})">
                                    <i class="fas fa-check me-2"></i> Mark as Read
                                </button>
                                ` : ''}
                                <button class="btn btn-outline-primary" onclick="printNotification(${notification.id})">
                                    <i class="fas fa-print me-2"></i> Print
                                </button>
                                <button class="btn btn-outline-danger" onclick="deleteNotification(${notification.id})">
                                    <i class="fas fa-trash me-2"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('notificationDetails').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('notificationDetails').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading notification: ${error}
                </div>
            `;
        });
    
    modal.show();
}

function printNotification(notificationId) {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Notification Details</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .notification-header { margin-bottom: 30px; }
                .notification-body { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                .print-date { text-align: right; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="print-date">Printed: ${new Date().toLocaleString()}</div>
            <div id="print-content">
                Loading...
            </div>
        </body>
        </html>
    `);
    
    // Fetch and display notification details
    fetch('action/get-notification.php?id=' + notificationId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const notification = data.data;
                printWindow.document.getElementById('print-content').innerHTML = `
                    <div class="notification-header">
                        <h2>${notification.title}</h2>
                        <p><strong>Date:</strong> ${notification.created_at_formatted}</p>
                        <p><strong>Status:</strong> ${notification.is_read ? 'Read' : 'Unread'}</p>
                    </div>
                    <div class="notification-body">
                        <h3>Message</h3>
                        <p>${notification.message}</p>
                    </div>
                `;
            }
        });
    
    printWindow.document.close();
    printWindow.print();
}

function exportNotifications() {
    // Export notifications as CSV
    fetch('action/export-notifications.php')
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'notifications-' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        });
}

function downloadNotificationArchive() {
    if (confirm('Download all notifications as archive?')) {
        fetch('action/download-archive.php')
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'notifications-archive-' + new Date().toISOString().split('T')[0] + '.zip';
                a.click();
                window.URL.revokeObjectURL(url);
            });
    }
}

function getSelectedNotificationIds() {
    const selected = [];
    document.querySelectorAll('.notification-checkbox:checked').forEach(checkbox => {
        selected.push(checkbox.value);
    });
    return selected;
}
</script>

<?php require_once '../../includes/footer.php'; ?>