<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Settings History';
require_once '../includes/header.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$filter_setting = isset($_GET['setting']) ? $_GET['setting'] : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

try {
    $db = getDB();
    
    // Build WHERE clause
    $where = ["1=1"];
    $params = [];
    
    if (!empty($filter_setting)) {
        $where[] = "sh.setting_key LIKE ?";
        $params[] = "%$filter_setting%";
    }
    
    if (!empty($filter_user)) {
        $where[] = "sh.changed_by = ?";
        $params[] = $filter_user;
    }
    
    if (!empty($start_date)) {
        $where[] = "DATE(sh.changed_at) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $where[] = "DATE(sh.changed_at) <= ?";
        $params[] = $end_date;
    }
    
    $where_sql = implode(' AND ', $where);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM settings_history sh WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get history with user info
    $history_sql = "SELECT sh.*, u.full_name, u.email 
                    FROM settings_history sh 
                    LEFT JOIN users u ON sh.changed_by = u.id 
                    WHERE $where_sql 
                    ORDER BY sh.changed_at DESC 
                    LIMIT ? OFFSET ?";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $db->prepare($history_sql);
    $stmt->execute($all_params);
    $history = $stmt->fetchAll();
    
    // Get users for filter
    $stmt = $db->query("SELECT id, full_name, email FROM users WHERE user_type = 'admin' ORDER BY full_name");
    $users = $stmt->fetchAll();
    
    // Get unique setting keys
    $stmt = $db->query("SELECT DISTINCT setting_key FROM settings_history ORDER BY setting_key");
    $setting_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $error = 'Error loading history: ' . $e->getMessage();
    $history = [];
    $total_records = 0;
    $users = [];
    $setting_keys = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Settings History</h1>
                <p class="text-muted mb-0">Audit trail of all setting changes</p>
            </div>
            <div>
                <button class="btn btn-outline-danger me-2" onclick="clearHistory()">
                    <i class="fas fa-trash me-2"></i> Clear History
                </button>
                <a href="settings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <input type="text" 
                               name="setting" 
                               class="form-control" 
                               placeholder="Filter by setting key"
                               value="<?php echo htmlspecialchars($filter_setting); ?>"
                               list="settingKeys">
                        <datalist id="settingKeys">
                            <?php foreach($setting_keys as $key): ?>
                            <option value="<?php echo $key; ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="col-md-2">
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" 
                               name="start_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($start_date); ?>"
                               placeholder="Start Date">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="date" 
                               name="end_date" 
                               class="form-control" 
                               value="<?php echo htmlspecialchars($end_date); ?>"
                               placeholder="End Date">
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Filter
                        </button>
                    </div>
                    
                    <div class="col-md-1">
                        <a href="settings-history.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- History Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Change History (<?php echo number_format($total_records); ?> records)
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="exportHistory('csv')">
                        <i class="fas fa-file-csv me-1"></i> CSV
                    </button>
                    <button class="btn btn-outline-primary" onclick="exportHistory('json')">
                        <i class="fas fa-file-code me-1"></i> JSON
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-history fa-4x text-muted mb-3"></i>
                    <h5>No History Found</h5>
                    <p class="text-muted">No setting changes match your criteria</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">ID</th>
                                <th width="20%">Setting</th>
                                <th width="25%">Old Value</th>
                                <th width="25%">New Value</th>
                                <th width="15%">Changed By</th>
                                <th width="10%">Date</th>
                                <th width="5%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $record): ?>
                            <tr>
                                <td>#<?php echo $record['id']; ?></td>
                                <td>
                                    <code><?php echo $record['setting_key']; ?></code>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" 
                                         title="<?php echo htmlspecialchars($record['old_value'] ?? 'NULL'); ?>">
                                        <?php echo $record['old_value'] ? substr($record['old_value'], 0, 50) : '<em class="text-muted">NULL</em>'; ?>
                                        <?php echo $record['old_value'] && strlen($record['old_value']) > 50 ? '...' : ''; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" 
                                         title="<?php echo htmlspecialchars($record['new_value'] ?? 'NULL'); ?>">
                                        <?php echo $record['new_value'] ? substr($record['new_value'], 0, 50) : '<em class="text-muted">NULL</em>'; ?>
                                        <?php echo $record['new_value'] && strlen($record['new_value']) > 50 ? '...' : ''; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($record['full_name']): ?>
                                    <div><?php echo htmlspecialchars($record['full_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['email']); ?></small>
                                    <?php else: ?>
                                    <em class="text-muted">System</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d', strtotime($record['changed_at'])); ?><br>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($record['changed_at'])); ?></small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" 
                                            onclick="viewChangeDetails(<?php echo $record['id']; ?>)"
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
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
        
        <!-- Statistics -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Changes</h6>
                        <h3 class="fw-bold"><?php echo number_format($total_records); ?></h3>
                        <small class="text-muted">All time</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Today's Changes</h6>
                        <h3 class="fw-bold">
                            <?php
                            try {
                                $stmt = $db->prepare("SELECT COUNT(*) FROM settings_history WHERE DATE(changed_at) = CURDATE()");
                                $stmt->execute();
                                echo number_format($stmt->fetchColumn());
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </h3>
                        <small class="text-muted">Today</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Most Changed</h6>
                        <h6 class="fw-bold">
                            <?php
                            try {
                                $stmt = $db->query("SELECT setting_key, COUNT(*) as count FROM settings_history GROUP BY setting_key ORDER BY count DESC LIMIT 1");
                                $result = $stmt->fetch();
                                if ($result) {
                                    echo substr($result['setting_key'], 0, 20);
                                    echo strlen($result['setting_key']) > 20 ? '...' : '';
                                    echo '<br><small class="text-muted">' . number_format($result['count']) . ' changes</small>';
                                } else {
                                    echo 'N/A';
                                }
                            } catch (Exception $e) {
                                echo 'N/A';
                            }
                            ?>
                        </h6>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Change Details Modal -->
<div class="modal fade" id="changeDetailsModal" tabindex="-1" aria-labelledby="changeDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeDetailsModalLabel">Change Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="changeDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="revertChange()">
                    <i class="fas fa-undo me-2"></i> Revert Change
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentChangeId = null;

// View change details
function viewChangeDetails(changeId) {
    currentChangeId = changeId;
    
    fetch(`ajax/get-change-details.php?id=${changeId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const content = document.getElementById('changeDetailsContent');
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Change ID:</strong></td>
                                <td>#${data.change.id}</td>
                            </tr>
                            <tr>
                                <td><strong>Setting Key:</strong></td>
                                <td><code>${data.change.setting_key}</code></td>
                            </tr>
                            <tr>
                                <td><strong>Changed By:</strong></td>
                                <td>${data.change.full_name || 'System'}</td>
                            </tr>
                            <tr>
                                <td><strong>Change Date:</strong></td>
                                <td>${new Date(data.change.changed_at).toLocaleString()}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>IP Address:</strong></td>
                                <td>${data.change.ip_address || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>User Agent:</strong></td>
                                <td><small class="text-muted">${data.change.user_agent || 'N/A'}</small></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <strong>Old Value</strong>
                            </div>
                            <div class="card-body">
                                <pre class="mb-0"><code>${escapeHtml(data.change.old_value || 'NULL')}</code></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <strong>New Value</strong>
                            </div>
                            <div class="card-body">
                                <pre class="mb-0"><code>${escapeHtml(data.change.new_value || 'NULL')}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Change made via ${data.change.change_source || 'Unknown'}
                </div>
            `;
            
            $('#changeDetailsModal').modal('show');
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load change details.', 'error');
    });
}

// Revert change
function revertChange() {
    Swal.fire({
        title: 'Revert Change',
        text: 'Revert this setting to its previous value?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Revert'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/revert-change.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    change_id: currentChangeId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reverted!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#changeDetailsModal').modal('hide');
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

// Export history
function exportHistory(format) {
    const params = new URLSearchParams(window.location.search);
    params.set('format', format);
    
    window.open(`export-settings-history.php?${params.toString()}`, '_blank');
}

// Clear history
function clearHistory() {
    Swal.fire({
        title: 'Clear History',
        text: 'Delete all settings change history? This action cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Clear All History'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/clear-history.php')
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

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once '../includes/footer.php'; ?>