<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Import/Export Settings';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get all settings groups
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $groups = $stmt->fetchAll();
    
    // Get recent imports/exports
    $stmt = $db->query("SELECT * FROM import_export_logs ORDER BY created_at DESC LIMIT 10");
    $history = $stmt->fetchAll();
    
    // Get total settings count
    $stmt = $db->query("SELECT COUNT(*) as total FROM settings");
    $total_settings = $stmt->fetch()['total'];
    
    // Get settings by group
    $settings_by_group = [];
    foreach ($groups as $group) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM settings WHERE `group` = ?");
        $stmt->execute([$group['slug']]);
        $count = $stmt->fetch()['count'];
        $settings_by_group[$group['slug']] = $count;
    }
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $groups = [];
    $history = [];
    $total_settings = 0;
    $settings_by_group = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Import/Export Settings</h1>
                <p class="text-muted mb-0">Backup and restore system settings</p>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo $total_settings; ?></h3>
                        <p class="text-muted mb-0">Total Settings</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo count($groups); ?></h3>
                        <p class="text-muted mb-0">Settings Groups</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold">
                            <?php 
                            $public_count = 0;
                            try {
                                $stmt = $db->query("SELECT COUNT(*) as count FROM settings WHERE is_public = 1");
                                $public_count = $stmt->fetch()['count'];
                            } catch(Exception $e) {}
                            echo $public_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Public Settings</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold">
                            <?php 
                            $recent_count = 0;
                            try {
                                $stmt = $db->query("SELECT COUNT(*) as count FROM import_export_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                                $recent_count = $stmt->fetch()['count'];
                            } catch(Exception $e) {}
                            echo $recent_count;
                            ?>
                        </h3>
                        <p class="text-muted mb-0">Recent Activities</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Export Section -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Export Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="exportForm">
                            <div class="mb-3">
                                <label class="form-label">Export Format</label>
                                <select class="form-select" name="format">
                                    <option value="json">JSON (Recommended)</option>
                                    <option value="csv">CSV</option>
                                    <option value="xml">XML</option>
                                    <option value="php">PHP Array</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Export Scope</label>
                                <select class="form-select" name="scope" id="exportScope" onchange="toggleExportOptions()">
                                    <option value="all">All Settings</option>
                                    <option value="group">Specific Group</option>
                                    <option value="selected">Selected Settings</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="groupSelect" style="display: none;">
                                <label class="form-label">Select Group</label>
                                <select class="form-select" name="group">
                                    <?php foreach($groups as $group): ?>
                                    <option value="<?php echo $group['slug']; ?>">
                                        <?php echo $group['name']; ?> (<?php echo $settings_by_group[$group['slug']] ?? 0; ?> settings)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="settingsSelect" style="display: none;">
                                <label class="form-label">Select Settings</label>
                                <div class="form-control" style="height: 200px; overflow-y: auto;">
                                    <?php 
                                    try {
                                        $stmt = $db->query("SELECT setting_key, `group` FROM settings ORDER BY `group`, setting_key");
                                        $all_settings = $stmt->fetchAll();
                                        
                                        foreach($all_settings as $setting):
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="settings[]" value="<?php echo $setting['setting_key']; ?>"
                                               id="setting_<?php echo $setting['setting_key']; ?>">
                                        <label class="form-check-label" for="setting_<?php echo $setting['setting_key']; ?>">
                                            <?php echo $setting['setting_key']; ?>
                                            <small class="text-muted">(<?php echo $setting['group']; ?>)</small>
                                        </label>
                                    </div>
                                    <?php endforeach; } catch(Exception $e) {} ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Export Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_metadata" id="includeMetadata" checked>
                                    <label class="form-check-label" for="includeMetadata">
                                        Include metadata (type, validation, etc.)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="include_values" id="includeValues" checked>
                                    <label class="form-check-label" for="includeValues">
                                        Include setting values
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="compress" id="compressExport">
                                    <label class="form-check-label" for="compressExport">
                                        Compress export file (ZIP)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="button" class="btn btn-primary" onclick="exportSettings()">
                                    <i class="fas fa-download me-2"></i> Export Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Import Section -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Import Settings</h5>
                    </div>
                    <div class="card-body">
                        <form id="importForm" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label">Import File</label>
                                <input type="file" class="form-control" name="import_file" 
                                       accept=".json,.csv,.xml,.zip" required>
                                <div class="form-text">
                                    Supported formats: JSON, CSV, XML, ZIP
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Import Mode</label>
                                <select class="form-select" name="import_mode">
                                    <option value="merge">Merge (Keep existing, add new)</option>
                                    <option value="replace">Replace (Overwrite all)</option>
                                    <option value="update">Update (Only update existing)</option>
                                    <option value="skip">Skip existing (Only add new)</option>
                                </select>
                                <div class="form-text">
                                    <strong>Merge:</strong> Add new settings, keep existing<br>
                                    <strong>Replace:</strong> Delete all settings, import new<br>
                                    <strong>Update:</strong> Only update existing settings<br>
                                    <strong>Skip:</strong> Only add new settings
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Import Options</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="create_backup" id="createBackup" checked>
                                    <label class="form-check-label" for="createBackup">
                                        Create backup before import
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="dry_run" id="dryRun">
                                    <label class="form-check-label" for="dryRun">
                                        Dry run (test import without changes)
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="preserve_ids" id="preserveIds">
                                    <label class="form-check-label" for="preserveIds">
                                        Preserve setting IDs (if available)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Conflict Resolution</label>
                                <select class="form-select" name="conflict_resolution">
                                    <option value="skip">Skip conflicting settings</option>
                                    <option value="overwrite">Overwrite conflicting settings</option>
                                    <option value="rename">Rename conflicting settings</option>
                                </select>
                            </div>
                            
                            <div class="d-grid">
                                <button type="button" class="btn btn-success" onclick="importSettings()">
                                    <i class="fas fa-upload me-2"></i> Import Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Import/Export Activities</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshHistory()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h5>No Recent Activities</h5>
                    <p class="text-muted">No import/export activities recorded</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>File</th>
                                <th>Settings</th>
                                <th>Mode</th>
                                <th>User</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($history as $record): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-<?php echo $record['type'] == 'import' ? 'success' : 'primary'; ?>">
                                        <?php echo ucfirst($record['type']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo $record['filename']; ?></small>
                                </td>
                                <td><?php echo $record['settings_count'] ?? 'N/A'; ?></td>
                                <td><?php echo $record['import_mode'] ?? 'N/A'; ?></td>
                                <td>
                                    <?php 
                                    try {
                                        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
                                        $stmt->execute([$record['user_id']]);
                                        $user = $stmt->fetch();
                                        echo $user ? $user['username'] : 'System';
                                    } catch(Exception $e) {
                                        echo 'System';
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('M d, H:i', strtotime($record['created_at'])); ?></td>
                                <td>
                                    <?php if ($record['status'] == 'success'): ?>
                                    <span class="badge bg-success">Success</span>
                                    <?php elseif ($record['status'] == 'failed'): ?>
                                    <span class="badge bg-danger">Failed</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                    <?php endif; ?>
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

<!-- Import Preview Modal -->
<div class="modal fade" id="importPreviewModal" tabindex="-1" aria-labelledby="importPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importPreviewModalLabel">Import Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="importPreviewContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmImport()">Confirm Import</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Toggle export options
function toggleExportOptions() {
    const scope = document.getElementById('exportScope').value;
    
    document.getElementById('groupSelect').style.display = scope === 'group' ? 'block' : 'none';
    document.getElementById('settingsSelect').style.display = scope === 'selected' ? 'block' : 'none';
}

// Export settings
function exportSettings() {
    const form = document.getElementById('exportForm');
    const formData = new FormData(form);
    
    // Convert form data to query string
    const params = new URLSearchParams();
    for (const pair of formData.entries()) {
        if (pair[0] === 'settings[]') {
            // Handle multiple checkboxes
            if (!params.has(pair[0])) {
                params.set(pair[0], pair[1]);
            } else {
                params.append(pair[0], pair[1]);
            }
        } else {
            params.set(pair[0], pair[1]);
        }
    }
    
    // Open export in new window
    window.open(`export-settings.php?${params.toString()}`, '_blank');
    
    // Log export activity
    fetch('../ajax/settings/log-export.php', {
        method: 'POST',
        body: formData
    }).catch(error => console.error('Error logging export:', error));
}

// Import settings
function importSettings() {
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    const file = formData.get('import_file');
    
    if (!file || !file.name) {
        Swal.fire('Error!', 'Please select a file to import.', 'error');
        return;
    }
    
    // Validate file extension
    const validExtensions = ['.json', '.csv', '.xml', '.zip'];
    const fileExtension = file.name.slice(file.name.lastIndexOf('.')).toLowerCase();
    
    if (!validExtensions.includes(fileExtension)) {
        Swal.fire('Error!', 'Invalid file format. Please upload a JSON, CSV, XML, or ZIP file.', 'error');
        return;
    }
    
    // Show preview
    Swal.fire({
        title: 'Processing File...',
        text: 'Please wait while we process your file.',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            
            fetch('../ajax/settings/preview-import.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    showImportPreview(data);
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while processing the file.', 'error');
            });
        }
    });
}

// Show import preview
function showImportPreview(data) {
    const preview = document.getElementById('importPreviewContent');
    
    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6>Import Summary</h6>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>File:</strong></td>
                        <td>${data.filename}</td>
                    </tr>
                    <tr>
                        <td><strong>Format:</strong></td>
                        <td>${data.format}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Settings:</strong></td>
                        <td>${data.total_settings}</td>
                    </tr>
                    <tr>
                        <td><strong>New Settings:</strong></td>
                        <td><span class="badge bg-success">${data.new_settings}</span></td>
                    </tr>
                    <tr>
                        <td><strong>Existing Settings:</strong></td>
                        <td><span class="badge bg-warning">${data.existing_settings}</span></td>
                    </tr>
                    <tr>
                        <td><strong>Conflicts:</strong></td>
                        <td><span class="badge bg-danger">${data.conflicts}</span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6>Groups Distribution</h6>
                <div class="list-group list-group-flush">
    `;
    
    if (data.groups && Object.keys(data.groups).length > 0) {
        for (const [group, count] of Object.entries(data.groups)) {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                    ${group}
                    <span class="badge bg-primary rounded-pill">${count}</span>
                </div>
            `;
        }
    } else {
        html += `<div class="text-muted">No group information available</div>`;
    }
    
    html += `
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <h6>Preview (First 5 Settings)</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Setting Key</th>
                            <th>Group</th>
                            <th>Type</th>
                            <th>Value Preview</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    if (data.preview && data.preview.length > 0) {
        data.preview.forEach(setting => {
            let statusBadge = '<span class="badge bg-success">New</span>';
            if (setting.exists) {
                statusBadge = '<span class="badge bg-warning">Update</span>';
            }
            if (setting.conflict) {
                statusBadge = '<span class="badge bg-danger">Conflict</span>';
            }
            
            html += `
                <tr>
                    <td><code>${setting.key}</code></td>
                    <td>${setting.group}</td>
                    <td>${setting.type}</td>
                    <td><small>${setting.value_preview}</small></td>
                    <td>${statusBadge}</td>
                </tr>
            `;
        });
    }
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
        
        <input type="hidden" id="importData" value='${JSON.stringify(data)}'>
    `;
    
    preview.innerHTML = html;
    $('#importPreviewModal').modal('show');
}

// Confirm import
function confirmImport() {
    const importData = JSON.parse(document.getElementById('importData').value);
    const form = document.getElementById('importForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Confirm Import',
        text: `Import ${importData.total_settings} settings?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Import Settings'
    }).then((result) => {
        if (result.isConfirmed) {
            $('#importPreviewModal').modal('hide');
            
            Swal.fire({
                title: 'Importing...',
                text: 'Please wait while settings are being imported.',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                    
                    fetch('../ajax/settings/import-settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        Swal.close();
                        
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Imported!',
                                html: `
                                    <div class="text-center">
                                        <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                        <p>${data.message}</p>
                                        <div class="text-start">
                                            <p><strong>Total:</strong> ${data.total_imported}</p>
                                            <p><strong>New:</strong> ${data.new_settings}</p>
                                            <p><strong>Updated:</strong> ${data.updated_settings}</p>
                                            <p><strong>Skipped:</strong> ${data.skipped_settings}</p>
                                        </div>
                                    </div>
                                `,
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.close();
                        console.error('Error:', error);
                        Swal.fire('Error!', 'An error occurred during import.', 'error');
                    });
                }
            });
        }
    });
}

// Refresh history
function refreshHistory() {
    location.reload();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleExportOptions();
});
</script>

<?php require_once '../includes/footer.php'; ?>