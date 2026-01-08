<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

if (!isset($_GET['group']) || empty($_GET['group'])) {
    $_SESSION['error'] = 'Settings group is required.';
    redirect('settings.php');
}

$group_slug = $_GET['group'];

try {
    $db = getDB();
    
    // Get group information
    $stmt = $db->prepare("SELECT * FROM settings_groups WHERE slug = ?");
    $stmt->execute([$group_slug]);
    $group = $stmt->fetch();
    
    if (!$group) {
        $_SESSION['error'] = 'Settings group not found.';
        redirect('settings.php');
    }
    
    // Get settings for this group
    $stmt = $db->prepare("SELECT * FROM settings WHERE `group` = ? ORDER BY sort_order, setting_key");
    $stmt->execute([$group_slug]);
    $settings = $stmt->fetchAll();
    
    // Get all groups for navigation
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $all_groups = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading settings: ' . $e->getMessage();
    redirect('settings.php');
}

$page_title = $group['name'] . ' Settings';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="<?php echo $group['icon']; ?> me-2"></i>
                    <?php echo $group['name']; ?> Settings
                </h1>
                <p class="text-muted mb-0"><?php echo $group['description']; ?></p>
            </div>
            <div>
                <a href="settings.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
                <button class="btn btn-primary" onclick="saveGroupSettings()">
                    <i class="fas fa-save me-2"></i> Save Changes
                </button>
            </div>
        </div>
        
        <!-- Group Navigation -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap">
                    <?php foreach($all_groups as $nav_group): ?>
                    <a href="settings-group.php?group=<?php echo $nav_group['slug']; ?>" 
                       class="btn btn-sm mb-1 me-2 <?php echo $nav_group['slug'] == $group_slug ? 'btn-primary' : 'btn-outline-primary'; ?>">
                        <i class="<?php echo $nav_group['icon']; ?> me-1"></i>
                        <?php echo $nav_group['name']; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Settings Form -->
        <form id="settingsForm" method="POST" action="<?php echo SITE_URL; ?>admin/ajax/save-settings.php">
            <input type="hidden" name="group" value="<?php echo $group_slug; ?>">
            
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <?php if (empty($settings)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-cogs fa-4x text-muted mb-3"></i>
                        <h5>No Settings Found</h5>
                        <p class="text-muted">No settings available for this group</p>
                        <a href="add-setting.php?group=<?php echo $group_slug; ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i> Add Setting
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach($settings as $setting): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="card border h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="card-title mb-1"><?php 
                                                echo ucwords(str_replace('_', ' ', $setting['setting_key'])); 
                                            ?></h6>
                                            <?php if ($setting['help_text']): ?>
                                            <small class="text-muted"><?php echo $setting['help_text']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="badge bg-light text-dark">
                                            <?php echo $setting['setting_type']; ?>
                                        </span>
                                    </div>
                                    
                                    <?php 
                                    $field_id = 'setting_' . $setting['setting_key'];
                                    $field_name = 'settings[' . $setting['setting_key'] . ']';
                                    $field_value = htmlspecialchars($setting['setting_value'] ?? '');
                                    $field_required = $setting['is_required'] ? 'required' : '';
                                    
                                    switch($setting['setting_type']):
                                        case 'text':
                                        case 'number':
                                        case 'email':
                                        case 'password':
                                        case 'url':
                                        case 'color':
                                    ?>
                                        <input type="<?php echo $setting['setting_type']; ?>" 
                                               class="form-control" 
                                               id="<?php echo $field_id; ?>"
                                               name="<?php echo $field_name; ?>"
                                               value="<?php echo $field_value; ?>"
                                               placeholder="<?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>"
                                               <?php echo $field_required; ?>>
                                    
                                    <?php break; case 'textarea': ?>
                                        <textarea class="form-control" 
                                                  id="<?php echo $field_id; ?>"
                                                  name="<?php echo $field_name; ?>"
                                                  rows="3"
                                                  <?php echo $field_required; ?>><?php echo $field_value; ?></textarea>
                                    
                                    <?php break; case 'boolean': ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   id="<?php echo $field_id; ?>"
                                                   name="<?php echo $field_name; ?>"
                                                   value="1"
                                                   <?php echo $field_value == '1' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="<?php echo $field_id; ?>">
                                                Enable <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                                            </label>
                                        </div>
                                    
                                    <?php break; case 'select': 
                                        $options = $setting['options'] ? json_decode($setting['options'], true) : [];
                                    ?>
                                        <select class="form-select" 
                                                id="<?php echo $field_id; ?>"
                                                name="<?php echo $field_name; ?>"
                                                <?php echo $field_required; ?>>
                                            <option value="">Select option</option>
                                            <?php foreach($options as $option): ?>
                                            <option value="<?php echo $option; ?>" 
                                                <?php echo $field_value == $option ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($option); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    
                                    <?php break; case 'json': ?>
                                        <textarea class="form-control font-monospace" 
                                                  id="<?php echo $field_id; ?>"
                                                  name="<?php echo $field_name; ?>"
                                                  rows="4"
                                                  <?php echo $field_required; ?>><?php 
                                            if ($field_value) {
                                                $json = json_decode($field_value, true);
                                                echo json_encode($json, JSON_PRETTY_PRINT);
                                            }
                                        ?></textarea>
                                    
                                    <?php break; case 'file': ?>
                                        <div class="input-group">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="<?php echo $field_id; ?>_display"
                                                   value="<?php echo $field_value; ?>"
                                                   readonly>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary"
                                                    onclick="uploadFile('<?php echo $field_id; ?>')">
                                                <i class="fas fa-upload"></i>
                                            </button>
                                            <input type="hidden" 
                                                   id="<?php echo $field_id; ?>"
                                                   name="<?php echo $field_name; ?>"
                                                   value="<?php echo $field_value; ?>">
                                        </div>
                                        <?php if ($field_value && file_exists('./uploads/' . $field_value)): ?>
                                        <div class="mt-2">
                                            <img src="<?php echo SITE_URL; ?>uploads/<?php echo $field_value; ?>" 
                                                 alt="<?php echo $setting['setting_key']; ?>" 
                                                 class="img-thumbnail" 
                                                 style="max-height: 100px;">
                                        </div>
                                        <?php endif; ?>
                                    
                                    <?php break; default: ?>
                                        <input type="text" 
                                               class="form-control" 
                                               id="<?php echo $field_id; ?>"
                                               name="<?php echo $field_name; ?>"
                                               value="<?php echo $field_value; ?>"
                                               <?php echo $field_required; ?>>
                                    
                                    <?php endswitch; ?>
                                    
                                    <?php if ($setting['validation_rules']): ?>
                                    <small class="text-muted d-block mt-1">
                                        Validation: <?php echo $setting['validation_rules']; ?>
                                    </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($setting['is_public']): ?>
                                    <small class="text-info d-block mt-1">
                                        <i class="fas fa-eye me-1"></i> Public setting
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Form Actions -->
                <?php if (!empty($settings)): ?>
                <div class="card-footer bg-white border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i> Save All Changes
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="fas fa-redo me-2"></i> Reset
                            </button>
                        </div>
                        <div>
                            <span class="text-muted me-3">
                                <?php echo count($settings); ?> settings
                            </span>
                            <a href="add-setting.php?group=<?php echo $group_slug; ?>" class="btn btn-outline-success">
                                <i class="fas fa-plus me-2"></i> Add New
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Advanced Options -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Advanced Options</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-primary w-100" onclick="exportGroupSettings()">
                            <i class="fas fa-download me-2"></i> Export Group
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-warning w-100" onclick="resetGroupToDefault()">
                            <i class="fas fa-undo me-2"></i> Reset to Default
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-danger w-100" onclick="deleteGroupSettings()">
                            <i class="fas fa-trash me-2"></i> Delete Group
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- File Upload Modal -->
<div class="modal fade" id="fileUploadModal" tabindex="-1" aria-labelledby="fileUploadModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fileUploadModalLabel">Upload File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="fileUploadForm">
                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" class="form-control" id="fileInput" required>
                        <input type="hidden" id="targetField">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File Type</label>
                        <select class="form-select" id="fileType">
                            <option value="image">Image</option>
                            <option value="document">Document</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="progress d-none" id="uploadProgress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="processFileUpload()">Upload</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentUploadField = null;

// Save group settings
function saveGroupSettings() {
    const form = document.getElementById('settingsForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Save Settings',
        text: 'Save all changes in this group?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Save Changes'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(form.action, {
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
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while saving.', 'error');
            });
        }
    });
}

// Reset form
function resetForm() {
    Swal.fire({
        title: 'Reset Form',
        text: 'Reset all fields to their current values?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Reset'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('settingsForm').reset();
            Swal.fire('Reset!', 'Form has been reset.', 'success');
        }
    });
}

// Upload file
function uploadFile(fieldId) {
    currentUploadField = fieldId;
    document.getElementById('targetField').value = fieldId;
    document.getElementById('fileInput').value = '';
    document.getElementById('uploadProgress').classList.add('d-none');
    
    $('#fileUploadModal').modal('show');
}

// Process file upload
function processFileUpload() {
    const fileInput = document.getElementById('fileInput');
    const fileType = document.getElementById('fileType').value;
    const progressBar = document.getElementById('uploadProgress');
    
    if (!fileInput.files[0]) {
        Swal.fire('Error!', 'Please select a file to upload.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('type', fileType);
    formData.append('field', currentUploadField);
    
    progressBar.classList.remove('d-none');
    
    fetch('../ajax/upload-setting-file.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the hidden field
            document.getElementById(currentUploadField).value = data.filename;
            // Update the display field
            document.getElementById(currentUploadField + '_display').value = data.filename;
            
            Swal.fire('Success!', 'File uploaded successfully.', 'success');
            $('#fileUploadModal').modal('hide');
            
            // If it's an image, show preview
            if (fileType === 'image' && data.filename) {
                const previewContainer = document.getElementById(currentUploadField).closest('.card-body');
                let preview = previewContainer.querySelector('.img-thumbnail');
                
                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'img-thumbnail mt-2';
                    preview.style.maxHeight = '100px';
                    previewContainer.appendChild(preview);
                }
                
                preview.src = '<?php echo SITE_URL; ?>uploads/' + data.filename + '?t=' + new Date().getTime();
            }
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred during upload.', 'error');
    })
    .finally(() => {
        progressBar.classList.add('d-none');
    });
}

// Export group settings
function exportGroupSettings() {
    Swal.fire({
        title: 'Export Group Settings',
        text: 'Export all settings from this group?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`import-export.php?group=<?php echo $group_slug; ?>&format=json`, '_blank');
        }
    });
}

// Reset group to default
function resetGroupToDefault() {
    Swal.fire({
        title: 'Reset to Default',
        text: 'Reset all settings in this group to their default values? This cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Reset to Default'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/reset-settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    group: '<?php echo $group_slug; ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reset!',
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

// Delete group settings
function deleteGroupSettings() {
    Swal.fire({
        title: 'Delete Group Settings',
        text: 'Delete all settings in this group? This action cannot be undone!',
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete All'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/delete-settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    group: '<?php echo $group_slug; ?>'
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
                        window.location.href = 'settings.php';
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

// Form submission
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    saveGroupSettings();
});

// JSON formatting
document.querySelectorAll('textarea.font-monospace').forEach(textarea => {
    textarea.addEventListener('blur', function() {
        try {
            const json = JSON.parse(this.value);
            this.value = JSON.stringify(json, null, 2);
        } catch (e) {
            // Invalid JSON, leave as is
        }
    });
});

// Auto-save for some fields
let saveTimeout;
document.querySelectorAll('#settingsForm input, #settingsForm select, #settingsForm textarea').forEach(element => {
    element.addEventListener('change', function() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            // Auto-save for important fields
            if (this.id.includes('site_') || this.id.includes('email_') || this.id.includes('payment_')) {
                const form = document.getElementById('settingsForm');
                const formData = new FormData();
                formData.append('settings[' + this.name.replace('settings[', '').replace(']', '') + ']', this.value);
                formData.append('group', '<?php echo $group_slug; ?>');
                
                fetch('../ajax/save-single-setting.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Auto-saved:', this.name);
                    }
                });
            }
        }, 2000);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>