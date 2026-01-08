<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'System Settings';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get all settings groups
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $settings_groups = $stmt->fetchAll();
    
    // Get settings count per group
    $settings_counts = [];
    foreach ($settings_groups as $group) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE `group` = ?");
        $stmt->execute([$group['slug']]);
        $settings_counts[$group['slug']] = $stmt->fetchColumn();
    }
    
    // Get recent changes
    $stmt = $db->query("SELECT sh.*, u.full_name 
                        FROM settings_history sh 
                        LEFT JOIN users u ON sh.changed_by = u.id 
                        ORDER BY sh.changed_at DESC 
                        LIMIT 10");
    $recent_changes = $stmt->fetchAll();
    
    // Get system information
    $system_info = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'mysql_version' => $db->getAttribute(PDO::ATTR_SERVER_VERSION),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
    ];
    
} catch(PDOException $e) {
    $error = 'Error loading settings: ' . $e->getMessage();
    $settings_groups = [];
    $settings_counts = [];
    $recent_changes = [];
    $system_info = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">System Settings</h1>
                <p class="text-muted mb-0">Manage website configuration and preferences</p>
            </div>
            <div>
                <button class="btn btn-outline-secondary me-2" onclick="exportSettings()">
                    <i class="fas fa-download me-2"></i> <a href="<?php echo SITE_URL; ?>/admin/import-export.php" class="text-decoration-none">Export</a>
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importSettingsModal">
                    <i class="fas fa-upload me-2"></i> Import
                </button>
            </div>
        </div>
        
        <!-- Settings Groups Grid -->
        <div class="row mb-4">
            <?php foreach($settings_groups as $group): ?>
            <div class="col-xl-3 col-md-4 col-sm-6 mb-4">
                <a href="settings-group.php?group=<?php echo $group['slug']; ?>" class="card card-link text-decoration-none">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center" 
                                 style="width: 70px; height: 70px;">
                                <i class="<?php echo $group['icon']; ?> fa-2x text-primary"></i>
                            </div>
                        </div>
                        <h5 class="card-title mb-1"><?php echo $group['name']; ?></h5>
                        <p class="text-muted small mb-2"><?php echo $settings_counts[$group['slug']] ?? 0; ?> settings</p>
                        <p class="card-text small text-muted"><?php echo $group['description']; ?></p>
                        <span class="badge bg-primary">Manage</span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- System Information -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">System Information</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshSystemInfo()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>PHP Version:</strong></td>
                                        <td><?php echo $system_info['php_version']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Server Software:</strong></td>
                                        <td><?php echo $system_info['server_software']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>MySQL Version:</strong></td>
                                        <td><?php echo $system_info['mysql_version']; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Upload Max Filesize:</strong></td>
                                        <td><?php echo $system_info['upload_max_filesize']; ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Max Execution Time:</strong></td>
                                        <td><?php echo $system_info['max_execution_time']; ?> seconds</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Memory Limit:</strong></td>
                                        <td><?php echo $system_info['memory_limit']; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Disk Space Usage -->
                        <div class="mt-4">
                            <h6 class="mb-3">Disk Space Usage</h6>
                            <?php
                            $total_space = disk_total_space('.');
                            $free_space = disk_free_space('.');
                            $used_space = $total_space - $free_space;
                            $used_percent = round(($used_space / $total_space) * 100, 2);
                            ?>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $used_percent; ?>%" 
                                     aria-valuenow="<?php echo $used_percent; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $used_percent; ?>%
                                </div>
                            </div>
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Used: <?php echo formatBytes($used_space); ?></span>
                                <span>Free: <?php echo formatBytes($free_space); ?></span>
                                <span>Total: <?php echo formatBytes($total_space); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary text-start" onclick="clearCache()">
                                <i class="fas fa-broom me-2"></i> Clear Cache
                            </button>
                            <button class="btn btn-outline-success text-start" onclick="backupDatabase()">
                                <i class="fas fa-database me-2"></i> Backup Database
                            </button>
                            <button class="btn btn-outline-info text-start" data-bs-toggle="modal" data-bs-target="#environmentModal">
                                <i class="fas fa-code me-2"></i> View Environment
                            </button>
                            <button class="btn btn-outline-warning text-start" data-bs-toggle="modal" data-bs-target="#phpInfoModal">
                                <i class="fas fa-info-circle me-2"></i> PHP Info
                            </button>
                            <button class="btn btn-outline-danger text-start" onclick="checkUpdates()">
                                <i class="fas fa-sync-alt me-2"></i> Check for Updates
                            </button>
                        </div>
                        
                        <!-- Maintenance Mode -->
                        <div class="mt-4">
                            <h6 class="mb-3">Maintenance Mode</h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenanceSwitch" 
                                       onchange="toggleMaintenanceMode(this.checked)">
                                <label class="form-check-label" for="maintenanceSwitch">
                                    Enable Maintenance Mode
                                </label>
                            </div>
                            <small class="text-muted">When enabled, only admins can access the site</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Changes -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Changes</h5>
                <a href="settings-history.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_changes)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent changes</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Old Value</th>
                                <th>New Value</th>
                                <th>Changed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_changes as $change): ?>
                            <tr>
                                <td><code><?php echo $change['setting_key']; ?></code></td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" 
                                          title="<?php echo htmlspecialchars($change['old_value'] ?? 'NULL'); ?>">
                                        <?php echo substr($change['old_value'] ?? 'NULL', 0, 30); ?>
                                        <?php echo strlen($change['old_value'] ?? '') > 30 ? '...' : ''; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-truncate d-inline-block" style="max-width: 150px;" 
                                          title="<?php echo htmlspecialchars($change['new_value'] ?? 'NULL'); ?>">
                                        <?php echo substr($change['new_value'] ?? 'NULL', 0, 30); ?>
                                        <?php echo strlen($change['new_value'] ?? '') > 30 ? '...' : ''; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($change['full_name'] ?? 'System'); ?></td>
                                <td><?php echo date('M d, H:i', strtotime($change['changed_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Import Settings Modal -->
<div class="modal fade" id="importSettingsModal" tabindex="-1" aria-labelledby="importSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importSettingsModalLabel">Import Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importSettingsForm">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" class="form-control" name="settings_file" accept=".json,.csv" required>
                        <div class="form-text">Supported formats: JSON, CSV</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Import Mode</label>
                        <select class="form-select" name="import_mode">
                            <option value="merge">Merge (Keep existing, add new)</option>
                            <option value="replace">Replace (Overwrite all)</option>
                            <option value="update">Update (Only update existing)</option>
                        </select>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="create_backup" id="createBackup" checked>
                        <label class="form-check-label" for="createBackup">
                            Create backup before importing
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="importSettings()">Import Settings</button>
            </div>
        </div>
    </div>
</div>

<!-- Environment Modal -->
<div class="modal fade" id="environmentModal" tabindex="-1" aria-labelledby="environmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="environmentModalLabel">Environment Variables</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <pre class="bg-light p-3 rounded"><code><?php
                $env_vars = [
                    'SITE_URL' => SITE_URL,
                    'DB_HOST' => DB_HOST,
                    'DB_NAME' => DB_NAME,
                    'DB_USER' => DB_USER,
                    // 'DEBUG_MODE' => DEBUG_MODE ? 'true' : 'false',
                    'TIMEZONE' => date_default_timezone_get(),
                ];
                echo json_encode($env_vars, JSON_PRETTY_PRINT);
                ?></code></pre>
            </div>
        </div>
    </div>
</div>

<!-- PHP Info Modal -->
<div class="modal fade" id="phpInfoModal" tabindex="-1" aria-labelledby="phpInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="phpInfoModalLabel">PHP Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <iframe src="phpinfo.php" width="100%" height="500px" style="border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Export settings
function exportSettings() {
    Swal.fire({
        title: 'Export Settings',
        text: 'Export all settings to a file?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export',
        showDenyButton: true,
        denyButtonText: 'Export with values',
        denyButtonColor: '#1cc88a'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('export-settings.php?format=json', '_blank');
        } else if (result.isDenied) {
            window.open('export-settings.php?format=json&with_values=1', '_blank');
        }
    });
}

// Import settings
function importSettings() {
    const form = document.getElementById('importSettingsForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Import Settings',
        text: 'This will modify your system settings. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, import'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/import-settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
                    $('#importSettingsModal').modal('hide');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred during import.', 'error');
            });
        }
    });
}

// Clear cache
function clearCache() {
    Swal.fire({
        title: 'Clear Cache',
        text: 'This will clear all cached data. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Clear Cache'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/clear-cache.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Success!', data.message, 'success');
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

// Backup database
function backupDatabase() {
    Swal.fire({
        title: 'Backup Database',
        text: 'Create a backup of the database?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Create Backup'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/backup-database.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Backup Created!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <p>${data.message}</p>
                                <p class="small text-muted">File: ${data.filename}</p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Download',
                        cancelButtonText: 'Close'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open(`backup-download.php?file=${data.filename}`, '_blank');
                        }
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

// Refresh system info
function refreshSystemInfo() {
    const button = event.target;
    const originalHTML = button.innerHTML;
    
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Toggle maintenance mode
function toggleMaintenanceMode(enabled) {
    fetch('ajax/toggle-maintenance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            enabled: enabled
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const status = enabled ? 'enabled' : 'disabled';
            Swal.fire({
                icon: 'success',
                title: 'Maintenance Mode',
                text: `Maintenance mode ${status} successfully`,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
            // Reset switch
            document.getElementById('maintenanceSwitch').checked = !enabled;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
        document.getElementById('maintenanceSwitch').checked = !enabled;
    });
}

// Check for updates
function checkUpdates() {
    Swal.fire({
        title: 'Checking for Updates',
        text: 'Please wait while we check for updates...',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            
            fetch('ajax/check-updates.php')
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    if (data.update_available) {
                        Swal.fire({
                            title: 'Update Available!',
                            html: `
                                <div class="text-start">
                                    <p><strong>Current Version:</strong> ${data.current_version}</p>
                                    <p><strong>Latest Version:</strong> ${data.latest_version}</p>
                                    <p><strong>Release Notes:</strong></p>
                                    <div class="bg-light p-2 small rounded">${data.release_notes}</div>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Update Now',
                            cancelButtonText: 'Later',
                            confirmButtonColor: '#1cc88a'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'update-system.php';
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Up to Date',
                            text: 'Your system is running the latest version.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to check for updates.', 'error');
            });
        }
    });
}

// Format bytes to human readable
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Initialize maintenance switch
document.addEventListener('DOMContentLoaded', function() {
    // Load current maintenance mode status
    fetch('ajax/get-maintenance-status.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('maintenanceSwitch').checked = data.maintenance_mode;
        }
    })
    .catch(error => {
        console.error('Error loading maintenance status:', error);
    });
});
</script>

<?php 
// Helper function to format bytes
function formatBytes($bytes, $decimals = 2) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    
    $i = floor(log($bytes) / log($k));
    
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}

require_once '../includes/footer.php'; 
?>