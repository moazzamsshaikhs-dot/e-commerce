<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Email Logs';
require_once '../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$template_id = isset($_GET['template_id']) ? $_GET['template_id'] : '';
$recipient = isset($_GET['recipient']) ? $_GET['recipient'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    $db = getDB();
    
    // Create email_logs table if not exists
    $table_exists = $db->query("SHOW TABLES LIKE 'email_logs'")->fetch();
    if (!$table_exists) {
        $db->exec("CREATE TABLE IF NOT EXISTS email_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            template_key VARCHAR(100),
            recipient_email VARCHAR(255) NOT NULL,
            recipient_name VARCHAR(255),
            subject VARCHAR(255),
            message TEXT,
            status ENUM('sent', 'failed', 'pending', 'bounced') DEFAULT 'pending',
            error_message TEXT,
            sent_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert sample data if table was just created
        $db->exec("INSERT INTO email_logs (template_key, recipient_email, recipient_name, subject, status, sent_at) VALUES
            ('welcome_email', 'test@example.com', 'Test User', 'Welcome to our site', 'sent', NOW()),
            ('order_confirmation', 'customer@example.com', 'John Doe', 'Order #1234 Confirmation', 'sent', DATE_SUB(NOW(), INTERVAL 1 DAY)),
            ('password_reset', 'user@example.com', NULL, 'Password Reset Request', 'failed', NULL)");
    }
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($template_id)) {
        $where[] = "template_key = ?";
        $params[] = $template_id;
    }
    
    if (!empty($recipient)) {
        $where[] = "recipient_email LIKE ?";
        $params[] = "%$recipient%";
    }
    
    if (!empty($status)) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM email_logs WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get email logs
    $logs_sql = "SELECT * FROM email_logs WHERE $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($logs_sql);
    $stmt->execute($all_params);
    $email_logs = $stmt->fetchAll();
    
    // Get email templates for filter
    $stmt = $db->query("SELECT DISTINCT template_key FROM email_logs WHERE template_key IS NOT NULL ORDER BY template_key");
    $templates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get statistics - handle NULL values properly
    $stats_sql = "SELECT 
        COUNT(*) as total_emails,
        COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as sent,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
        COALESCE(SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END), 0) as bounced
        FROM email_logs";
    $stats_result = $db->query($stats_sql)->fetch();
    
    // Ensure stats array has all keys with proper defaults
    $stats = [
        'total_emails' => (int)($stats_result['total_emails'] ?? 0),
        'sent' => (int)($stats_result['sent'] ?? 0),
        'failed' => (int)($stats_result['failed'] ?? 0),
        'pending' => (int)($stats_result['pending'] ?? 0),
        'bounced' => (int)($stats_result['bounced'] ?? 0)
    ];
    
    // Get today's emails
    $today_sql = "SELECT COUNT(*) as count FROM email_logs WHERE DATE(created_at) = CURDATE()";
    $today_result = $db->query($today_sql)->fetch();
    $today_emails = (int)($today_result['count'] ?? 0);
    
} catch(PDOException $e) {
    $error = 'Error loading email logs: ' . $e->getMessage();
    $email_logs = [];
    $total_records = 0;
    $templates = [];
    $stats = ['total_emails' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0, 'bounced' => 0];
    $today_emails = 0;
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Email Logs</h1>
                <p class="text-muted mb-0">Email sending history and status</p>
            </div>
            <div>
                <button class="btn btn-outline-danger me-2" onclick="clearEmailLogs()">
                    <i class="fas fa-trash me-2"></i> Clear Logs
                </button>
                <button class="btn btn-outline-primary" onclick="exportEmailLogs()">
                    <i class="fas fa-download me-2"></i> Export Logs
                </button>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo number_format($stats['total_emails']); ?></h3>
                        <p class="text-muted mb-0">Total Emails</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold text-success"><?php echo number_format($stats['sent']); ?></h3>
                        <p class="text-muted mb-0">Sent</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold text-danger"><?php echo number_format($stats['failed']); ?></h3>
                        <p class="text-muted mb-0">Failed</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold text-warning"><?php echo number_format($stats['pending']); ?></h3>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold text-info"><?php echo number_format($stats['bounced']); ?></h3>
                        <p class="text-muted mb-0">Bounced</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo number_format($today_emails); ?></h3>
                        <p class="text-muted mb-0">Today</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-2">
                        <select name="template_id" class="form-select">
                            <option value="">All Templates</option>
                            <?php foreach($templates as $template): ?>
                            <option value="<?php echo htmlspecialchars($template); ?>" 
                                <?php echo $template_id == $template ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($template); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="sent" <?php echo $status == 'sent' ? 'selected' : ''; ?>>Sent</option>
                            <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="bounced" <?php echo $status == 'bounced' ? 'selected' : ''; ?>>Bounced</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <input type="text" name="recipient" class="form-control" 
                               value="<?php echo htmlspecialchars($recipient); ?>" 
                               placeholder="Recipient email">
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
                    
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Email Logs Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Email Logs (<?php echo number_format($total_records); ?> records)
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="refreshLogs()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($email_logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                    <h5>No Email Logs</h5>
                    <p class="text-muted">No email logs match your criteria</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">ID</th>
                                <th width="20%">Recipient</th>
                                <th width="15%">Template</th>
                                <th width="20%">Subject</th>
                                <th width="15%">Status</th>
                                <th width="15%">Sent At</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($email_logs as $log): ?>
                            <tr>
                                <td>#<?php echo (int)$log['id']; ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($log['recipient_email']); ?></div>
                                    <?php if (!empty($log['recipient_name'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($log['recipient_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($log['template_key'])): ?>
                                    <code><?php echo htmlspecialchars($log['template_key']); ?></code>
                                    <?php else: ?>
                                    <em class="text-muted">Custom</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;">
                                        <?php echo htmlspecialchars($log['subject']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $status_badge = [
                                        'sent' => 'success',
                                        'failed' => 'danger',
                                        'pending' => 'warning',
                                        'bounced' => 'info'
                                    ];
                                    $status_class = $status_badge[$log['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($log['sent_at'])): ?>
                                    <?php echo date('M d, H:i', strtotime($log['sent_at'])); ?>
                                    <?php else: ?>
                                    <em class="text-muted">Not sent</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="viewEmail(<?php echo (int)$log['id']; ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-success" 
                                            onclick="resendEmail(<?php echo (int)$log['id']; ?>)"
                                            title="Resend">
                                        <i class="fas fa-redo"></i>
                                    </button>
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
        
        <!-- Status Distribution -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Email Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="emailStatusChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Daily Email Volume</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="emailVolumeChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- View Email Modal -->
<div class="modal fade" id="viewEmailModal" tabindex="-1" aria-labelledby="viewEmailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewEmailModalLabel">Email Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="emailDetailsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="resendCurrentEmail()">
                    <i class="fas fa-redo me-2"></i> Resend Email
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentEmailId = null;

// View email details
function viewEmail(emailId) {
    currentEmailId = parseInt(emailId);
    
    fetch(`../ajax/settings/get-email-details.php?id=${emailId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const email = data.email;
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Email ID:</strong></td>
                                <td>#${email.id}</td>
                            </tr>
                            <tr>
                                <td><strong>Recipient:</strong></td>
                                <td>
                                    <div>${escapeHtml(email.recipient_email || '')}</div>
                                    ${email.recipient_name ? `<small class="text-muted">${escapeHtml(email.recipient_name)}</small>` : ''}
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Template:</strong></td>
                                <td><code>${escapeHtml(email.template_key || 'Custom')}</code></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-${email.status === 'sent' ? 'success' : email.status === 'failed' ? 'danger' : email.status === 'pending' ? 'warning' : 'info'}">
                                        ${escapeHtml(email.status || '')}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Subject:</strong></td>
                                <td>${escapeHtml(email.subject || '')}</td>
                            </tr>
                            <tr>
                                <td><strong>Sent At:</strong></td>
                                <td>${email.sent_at ? new Date(email.sent_at).toLocaleString() : 'Not sent'}</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td>${new Date(email.created_at).toLocaleString()}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-3">
                    <h6>Message Preview</h6>
                    <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                        ${escapeHtml(email.message || '')}
                    </div>
                </div>
            `;
            
            if (email.error_message) {
                html += `
                    <div class="alert alert-danger mt-3">
                        <h6>Error Details:</h6>
                        <pre class="mb-0"><code>${escapeHtml(email.error_message)}</code></pre>
                    </div>
                `;
            }
            
            document.getElementById('emailDetailsContent').innerHTML = html;
            $('#viewEmailModal').modal('show');
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load email details.', 'error');
    });
}

// Resend email
function resendEmail(emailId) {
    emailId = parseInt(emailId);
    
    Swal.fire({
        title: 'Resend Email',
        text: 'Resend this email?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Resend'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/resend-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email_id: emailId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Resent!',
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

// Resend current email from modal
function resendCurrentEmail() {
    if (currentEmailId) {
        $('#viewEmailModal').modal('hide');
        resendEmail(currentEmailId);
    }
}

// Clear email logs
function clearEmailLogs() {
    Swal.fire({
        title: 'Clear Email Logs',
        text: 'Delete all email logs? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Clear All Logs'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/clear-email-logs.php')
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

// Export email logs
function exportEmailLogs() {
    const params = new URLSearchParams(window.location.search);
    
    Swal.fire({
        title: 'Export Email Logs',
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
            window.open(`export-email-logs.php?${params.toString()}&format=json`, '_blank');
        } else if (result.isDenied) {
            window.open(`export-email-logs.php?${params.toString()}&format=csv`, '_blank');
        }
    });
}

// Refresh logs
function refreshLogs() {
    location.reload();
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize charts
document.addEventListener('DOMContentLoaded', function() {
    // Status Distribution Chart
    const statusCtx = document.getElementById('emailStatusChart');
    if (statusCtx) {
        const sent = <?php echo (int)$stats['sent']; ?>;
        const failed = <?php echo (int)$stats['failed']; ?>;
        const pending = <?php echo (int)$stats['pending']; ?>;
        const bounced = <?php echo (int)$stats['bounced']; ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sent', 'Failed', 'Pending', 'Bounced'],
                datasets: [{
                    data: [sent, failed, pending, bounced],
                    backgroundColor: [
                        '#1cc88a',
                        '#e74a3b',
                        '#f6c23e',
                        '#36b9cc'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
    
    // Daily Volume Chart
    const volumeCtx = document.getElementById('emailVolumeChart');
    if (volumeCtx) {
        // Create sample data for chart
        const labels = [];
        const data = [];
        
        // Generate last 7 days
        for (let i = 6; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            
            // Generate random data between 5-50 emails per day
            data.push(Math.floor(Math.random() * 45) + 5);
        }
        
        new Chart(volumeCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Emails Sent',
                    data: data,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 10
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>