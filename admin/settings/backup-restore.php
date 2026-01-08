<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Database Backup & Restore';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get backup schedules
    $stmt = $db->query("SELECT * FROM backup_schedules ORDER BY id");
    $schedules = $stmt->fetchAll();
    
    // Get recent backups
    $backup_dir = '../backups/';
    $backups = [];
    
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && (strpos($file, '.sql') !== false || strpos($file, '.zip') !== false)) {
                $filepath = $backup_dir . $file;
                $backups[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'modified' => filemtime($filepath),
                    'type' => strpos($file, '.zip') !== false ? 'full' : 'database'
                ];
            }
        }
        
        // Sort by modified time (newest first)
        usort($backups, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
    
    // Get database info
    $stmt = $db->query("SELECT 
        COUNT(*) as total_tables,
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()");
    $db_info = $stmt->fetch();
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $schedules = [];
    $backups = [];
    $db_info = ['total_tables' => 0, 'size_mb' => 0];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Database Backup & Restore</h1>
                <p class="text-muted mb-0">Manage database backups and restoration</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="createBackup()">
                    <i class="fas fa-database me-2"></i> Create Backup
                </button>
            </div>
        </div>
        
        <!-- Database Information -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-database fa-3x text-primary"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo $db_info['size_mb']; ?> MB</h3>
                        <p class="text-muted mb-0">Database Size</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-table fa-3x text-success"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo $db_info['total_tables']; ?></h3>
                        <p class="text-muted mb-0">Total Tables</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-history fa-3x text-info"></i>
                        </div>
                        <h3 class="fw-bold"><?php echo count($backups); ?></h3>
                        <p class="text-muted mb-0">Total Backups</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Backup Options -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Quick Backup</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-primary w-100" onclick="backupDatabase('database')">
                            <i class="fas fa-database me-2"></i> Database Only
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-success w-100" onclick="backupDatabase('files')">
                            <i class="fas fa-folder me-2"></i> Files Only
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-info w-100" onclick="backupDatabase('full')">
                            <i class="fas fa-archive me-2"></i> Full Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Backups -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Backups</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshBackups()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($backups)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-database fa-3x text-muted mb-3"></i>
                    <h5>No Backups Found</h5>
                    <p class="text-muted">Create your first backup</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Backup File</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($backups as $backup): 
                                $size = formatBytes($backup['size']);
                                $date = date('M d, Y H:i', $backup['modified']);
                            ?>
                            <tr>
                                <td>
                                    <i class="fas fa-file-<?php echo $backup['type'] == 'full' ? 'archive' : 'code'; ?> me-2"></i>
                                    <?php echo $backup['name']; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $backup['type'] == 'full' ? 'info' : 'primary'; ?>">
                                        <?php echo ucfirst($backup['type']); ?>
                                    </span>
                                </td>
                                <td><?php echo $size; ?></td>
                                <td><?php echo $date; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="downloadBackup('<?php echo $backup['name']; ?>')"
                                                title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-outline-success" 
                                                onclick="restoreBackup('<?php echo $backup['name']; ?>')"
                                                title="Restore">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" 
                                                onclick="deleteBackup('<?php echo $backup['name']; ?>')"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Backup Schedules -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Backup Schedules</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus me-2"></i> Add Schedule
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($schedules)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                    <h5>No Schedules</h5>
                    <p class="text-muted">Create automated backup schedules</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Schedule</th>
                                <th>Type</th>
                                <th>Time</th>
                                <th>Keep For</th>
                                <th>Last Run</th>
                                <th>Next Run</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($schedules as $schedule): ?>
                            <tr>
                                <td>
                                    <strong><?php echo ucfirst($schedule['schedule_type']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst($schedule['backup_type']); ?></span>
                                </td>
                                <td><?php echo date('H:i', strtotime($schedule['time'])); ?></td>
                                <td><?php echo $schedule['keep_backups']; ?> days</td>
                                <td>
                                    <?php if ($schedule['last_run']): ?>
                                    <?php echo date('M d, H:i', strtotime($schedule['last_run'])); ?>
                                    <?php else: ?>
                                    <em class="text-muted">Never</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($schedule['next_run']): ?>
                                    <?php echo date('M d, H:i', strtotime($schedule['next_run'])); ?>
                                    <?php else: ?>
                                    <em class="text-muted">Not scheduled</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input schedule-toggle" 
                                               type="checkbox" 
                                               data-id="<?php echo $schedule['id']; ?>"
                                               <?php echo $schedule['is_active'] ? 'checked' : ''; ?>
                                               onchange="toggleSchedule(this, <?php echo $schedule['id']; ?>)">
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="runSchedule(<?php echo $schedule['id']; ?>)"
                                                title="Run Now">
                                            <i class="fas fa-play"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" 
                                                onclick="editSchedule(<?php echo $schedule['id']; ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" 
                                                onclick="deleteSchedule(<?php echo $schedule['id']; ?>)"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
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

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addScheduleModalLabel">Add Backup Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addScheduleForm">
                    <div class="mb-3">
                        <label class="form-label">Schedule Type</label>
                        <select class="form-select" name="schedule_type" required>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Backup Type</label>
                        <select class="form-select" name="backup_type" required>
                            <option value="database">Database Only</option>
                            <option value="files">Files Only</option>
                            <option value="full">Full Backup</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Time</label>
                        <input type="time" class="form-control" name="time" value="02:00" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keep Backups For (days)</label>
                        <input type="number" class="form-control" name="keep_backups" value="30" min="1" max="365" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveSchedule()">Save Schedule</button>
            </div>
        </div>
    </div>
</div>

<!-- Restore Backup Modal -->
<div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restoreModalLabel">Restore Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="restoreContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Create backup
function createBackup() {
    Swal.fire({
        title: 'Create Backup',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Backup Type</label>
                    <select class="form-select" id="backupType">
                        <option value="database">Database Only</option>
                        <option value="files">Files Only</option>
                        <option value="full">Full Backup (Database + Files)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Backup Name</label>
                    <input type="text" class="form-control" id="backupName" 
                           value="backup_${new Date().toISOString().slice(0,10)}">
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="compressBackup" checked>
                    <label class="form-check-label">Compress backup (ZIP)</label>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Create Backup',
        preConfirm: () => {
            return {
                type: document.getElementById('backupType').value,
                name: document.getElementById('backupName').value,
                compress: document.getElementById('compressBackup').checked
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { type, name, compress } = result.value;
            
            Swal.fire({
                title: 'Creating Backup...',
                html: 'Please wait while backup is being created.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../ajax/settings/create-backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    type: type,
                    name: name,
                    compress: compress
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup Created!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <p>${data.message}</p>
                                <p class="small text-muted">File: ${data.filename}</p>
                                <p class="small text-muted">Size: ${data.size}</p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Download',
                        cancelButtonText: 'Close',
                        confirmButtonColor: '#1cc88a'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open(`download-backup.php?file=${data.filename}`, '_blank');
                        }
                        setTimeout(() => location.reload(), 1000);
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred during backup.', 'error');
            });
        }
    });
}

// Quick backup
function backupDatabase(type) {
    fetch('../ajax/settings/create-backup.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: type,
            quick: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Backup Created!',
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

// Download backup
function downloadBackup(filename) {
    window.open(`download-backup.php?file=${filename}`, '_blank');
}

// Restore backup
function restoreBackup(filename) {
    Swal.fire({
        title: 'Restore Backup',
        html: `
            <div class="text-start">
                <p>Restore from <strong>${filename}</strong>?</p>
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="createBackupBefore">
                    <label class="form-check-label">Create backup before restore</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="verifyOnly">
                    <label class="form-check-label">Verify only (dry run)</label>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Restore',
        preConfirm: () => {
            return {
                createBackup: document.getElementById('createBackupBefore').checked,
                verifyOnly: document.getElementById('verifyOnly').checked
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { createBackup, verifyOnly } = result.value;
            
            Swal.fire({
                title: 'Restoring...',
                text: 'Please wait while backup is being restored.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('../ajax/settings/restore-backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: filename,
                    create_backup: createBackup,
                    verify_only: verifyOnly
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Restored!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        if (!verifyOnly) {
                            location.reload();
                        }
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred during restore.', 'error');
            });
        }
    });
}

// Delete backup
function deleteBackup(filename) {
    Swal.fire({
        title: 'Delete Backup',
        text: `Delete backup file "${filename}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/delete-backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    filename: filename
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
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

// Toggle schedule
function toggleSchedule(checkbox, scheduleId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('../ajax/settings/toggle-schedule.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            schedule_id: scheduleId,
            is_active: isActive
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            checkbox.checked = !checkbox.checked;
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        checkbox.checked = !checkbox.checked;
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// Save schedule
function saveSchedule() {
    const form = document.getElementById('addScheduleForm');
    const formData = new FormData(form);
    
    fetch('../ajax/settings/save-schedule.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                $('#addScheduleModal').modal('hide');
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

// Run schedule
function runSchedule(scheduleId) {
    Swal.fire({
        title: 'Run Schedule Now',
        text: 'Execute this backup schedule immediately?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Run Now'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/run-schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    schedule_id: scheduleId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Executed!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
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

// Edit schedule
function editSchedule(scheduleId) {
    fetch(`../ajax/settings/get-schedule.php?id=${scheduleId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const schedule = data.schedule;
            
            Swal.fire({
                title: 'Edit Schedule',
                html: `
                    <div class="text-start">
                        <div class="mb-3">
                            <label class="form-label">Schedule Type</label>
                            <select class="form-select" id="editScheduleType">
                                <option value="daily" ${schedule.schedule_type === 'daily' ? 'selected' : ''}>Daily</option>
                                <option value="weekly" ${schedule.schedule_type === 'weekly' ? 'selected' : ''}>Weekly</option>
                                <option value="monthly" ${schedule.schedule_type === 'monthly' ? 'selected' : ''}>Monthly</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Backup Type</label>
                            <select class="form-select" id="editBackupType">
                                <option value="database" ${schedule.backup_type === 'database' ? 'selected' : ''}>Database Only</option>
                                <option value="files" ${schedule.backup_type === 'files' ? 'selected' : ''}>Files Only</option>
                                <option value="full" ${schedule.backup_type === 'full' ? 'selected' : ''}>Full Backup</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" id="editTime" value="${schedule.time.slice(0,5)}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keep Backups For (days)</label>
                            <input type="number" class="form-control" id="editKeepBackups" value="${schedule.keep_backups}" min="1" max="365">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" ${schedule.is_active ? 'checked' : ''}>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Save Changes',
                preConfirm: () => {
                    return {
                        schedule_type: document.getElementById('editScheduleType').value,
                        backup_type: document.getElementById('editBackupType').value,
                        time: document.getElementById('editTime').value,
                        keep_backups: document.getElementById('editKeepBackups').value,
                        is_active: document.getElementById('editIsActive').checked
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = result.value;
                    formData.schedule_id = scheduleId;
                    
                    fetch('../ajax/settings/update-schedule.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
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
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load schedule.', 'error');
    });
}

// Delete schedule
function deleteSchedule(scheduleId) {
    Swal.fire({
        title: 'Delete Schedule',
        text: 'Are you sure you want to delete this schedule?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/delete-schedule.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    schedule_id: scheduleId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
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

// Refresh backups
function refreshBackups() {
    location.reload();
}

// Format bytes
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}
</script>

<?php 
function formatBytes($bytes, $decimals = 2) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}
require_once '../includes/footer.php'; 
?>