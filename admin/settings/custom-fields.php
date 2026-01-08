<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Custom Fields';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get custom fields
    $stmt = $db->query("SELECT cf.*, sg.name as group_name 
                        FROM custom_fields cf 
                        LEFT JOIN settings_groups sg ON cf.group_id = sg.id 
                        ORDER BY cf.sort_order, cf.name");
    $custom_fields = $stmt->fetchAll();
    
    // Get settings groups
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $groups = $stmt->fetchAll();
    
    // Get field types
    $field_types = [
        'text' => 'Text',
        'textarea' => 'Textarea',
        'number' => 'Number',
        'email' => 'Email',
        'password' => 'Password',
        'url' => 'URL',
        'color' => 'Color',
        'date' => 'Date',
        'datetime' => 'Date & Time',
        'time' => 'Time',
        'select' => 'Dropdown',
        'checkbox' => 'Checkbox',
        'radio' => 'Radio',
        'file' => 'File',
        'image' => 'Image',
        'editor' => 'Rich Text Editor',
        'json' => 'JSON'
    ];
    
} catch(PDOException $e) {
    $error = 'Error loading custom fields: ' . $e->getMessage();
    $custom_fields = [];
    $groups = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Custom Fields</h1>
                <p class="text-muted mb-0">Manage custom settings fields</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFieldModal">
                    <i class="fas fa-plus me-2"></i> Add Field
                </button>
            </div>
        </div>
        
        <!-- Custom Fields List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    Custom Fields (<?php echo count($custom_fields); ?>)
                </h5>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" onclick="exportCustomFields()">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <button class="btn btn-outline-secondary" onclick="importCustomFields()">
                        <i class="fas fa-upload me-1"></i> Import
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($custom_fields)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                    <h5>No Custom Fields</h5>
                    <p class="text-muted">Create your first custom field</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">Field Name</th>
                                <th width="15%">Type</th>
                                <th width="15%">Group</th>
                                <th width="15%">Default Value</th>
                                <th width="10%">Required</th>
                                <th width="10%">Active</th>
                                <th width="10%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($custom_fields as $field): ?>
                            <tr>
                                <td><?php echo $field['id']; ?></td>
                                <td>
                                    <strong><?php echo $field['name']; ?></strong><br>
                                    <small class="text-muted"><?php echo $field['field_key']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $field_types[$field['field_type']] ?? $field['field_type']; ?></span>
                                </td>
                                <td>
                                    <?php echo $field['group_name'] ?? 'General'; ?>
                                </td>
                                <td>
                                    <small class="text-truncate d-inline-block" style="max-width: 150px;">
                                        <?php echo $field['default_value'] ? substr($field['default_value'], 0, 50) : '-'; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($field['is_required']): ?>
                                    <span class="badge bg-success">Yes</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input field-toggle" 
                                               type="checkbox" 
                                               data-id="<?php echo $field['id']; ?>"
                                               <?php echo $field['is_active'] ? 'checked' : ''; ?>
                                               onchange="toggleField(this, <?php echo $field['id']; ?>)">
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="editField(<?php echo $field['id']; ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" 
                                                onclick="viewField(<?php echo $field['id']; ?>)"
                                                title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" 
                                                onclick="deleteField(<?php echo $field['id']; ?>)"
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
        
        <!-- Field Types Summary -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Field Types Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    $type_counts = [];
                    foreach($custom_fields as $field) {
                        $type = $field['field_type'];
                        $type_counts[$type] = isset($type_counts[$type]) ? $type_counts[$type] + 1 : 1;
                    }
                    
                    foreach($type_counts as $type => $count):
                        $percentage = ($count / count($custom_fields)) * 100;
                        $type_name = $field_types[$type] ?? $type;
                    ?>
                    <div class="col-md-3 mb-3">
                        <div class="card border">
                            <div class="card-body text-center">
                                <h6 class="text-muted"><?php echo $type_name; ?></h6>
                                <h3 class="fw-bold"><?php echo $count; ?></h3>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Field Modal -->
<div class="modal fade" id="addFieldModal" tabindex="-1" aria-labelledby="addFieldModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFieldModalLabel">Add Custom Field</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addFieldForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Field Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name" required 
                                       placeholder="e.g., Site Logo, Contact Email">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Field Key <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="field_key" required 
                                       pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only"
                                       placeholder="e.g., site_logo, contact_email">
                                <div class="form-text">Must be unique</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Field Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="field_type" required id="fieldType"
                                        onchange="toggleFieldOptions()">
                                    <?php foreach($field_types as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Settings Group</label>
                                <select class="form-select" name="group_id">
                                    <option value="">General</option>
                                    <?php foreach($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>">
                                        <?php echo $group['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Default Value</label>
                                <input type="text" class="form-control" name="default_value" 
                                       id="defaultValue" placeholder="Default value">
                            </div>
                            
                            <div class="mb-3" id="optionsContainer" style="display: none;">
                                <label class="form-label">Options (for Select/Radio)</label>
                                <textarea class="form-control" name="options" rows="3" 
                                          placeholder='Enter options as JSON array: ["option1", "option2"] or as key-value: {"key1": "Value 1", "key2": "Value 2"}'></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Validation Rules</label>
                                <input type="text" class="form-control" name="validation_rules" 
                                       placeholder="e.g., required|email|max:255">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Help Text</label>
                                <textarea class="form-control" name="help_text" rows="2" 
                                          placeholder="Help text to show below the field"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_required" id="isRequired">
                                <label class="form-check-label" for="isRequired">
                                    Required Field
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_public" id="isPublic">
                                <label class="form-check-label" for="isPublic">
                                    Public Field
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked>
                                <label class="form-check-label" for="isActive">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">CSS Class</label>
                                <input type="text" class="form-control" name="css_class" 
                                       placeholder="Custom CSS classes">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveField()">Save Field</button>
            </div>
        </div>
    </div>
</div>

<!-- View Field Modal -->
<div class="modal fade" id="viewFieldModal" tabindex="-1" aria-labelledby="viewFieldModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewFieldModalLabel">Field Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="fieldDetailsContent">
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
// Toggle field options based on type
function toggleFieldOptions() {
    const type = document.getElementById('fieldType').value;
    const optionsContainer = document.getElementById('optionsContainer');
    const defaultValue = document.getElementById('defaultValue');
    
    // Show/hide options for select/radio types
    if (type === 'select' || type === 'radio' || type === 'checkbox') {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
    }
    
    // Update default value placeholder
    switch(type) {
        case 'color':
            defaultValue.placeholder = '#000000';
            break;
        case 'email':
            defaultValue.placeholder = 'email@example.com';
            break;
        case 'url':
            defaultValue.placeholder = 'https://example.com';
            break;
        case 'number':
            defaultValue.placeholder = '123';
            break;
        default:
            defaultValue.placeholder = 'Default value';
    }
}

// Toggle field active status
function toggleField(checkbox, fieldId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('../ajax/settings/toggle-field.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            field_id: fieldId,
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

// Save field
function saveField() {
    const form = document.getElementById('addFieldForm');
    const formData = new FormData(form);
    
    // Validate field key
    const fieldKey = formData.get('field_key');
    if (!fieldKey.match(/^[a-z0-9_]+$/)) {
        Swal.fire('Error!', 'Field key must contain only lowercase letters, numbers, and underscores.', 'error');
        return;
    }
    
    // Validate options if present
    const options = formData.get('options');
    if (options) {
        try {
            JSON.parse(options);
        } catch (e) {
            Swal.fire('Error!', 'Options must be valid JSON.', 'error');
            return;
        }
    }

    fetch('../ajax/settings/save-field.php', {
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
                $('#addFieldModal').modal('hide');
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

// View field
function viewField(fieldId) {
    fetch(`../ajax/settings/get-field.php?id=${fieldId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const field = data.field;
            const options = field.options ? JSON.parse(field.options) : null;
            
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Field ID:</strong></td>
                                <td>#${field.id}</td>
                            </tr>
                            <tr>
                                <td><strong>Field Name:</strong></td>
                                <td>${field.name}</td>
                            </tr>
                            <tr>
                                <td><strong>Field Key:</strong></td>
                                <td><code>${field.field_key}</code></td>
                            </tr>
                            <tr>
                                <td><strong>Field Type:</strong></td>
                                <td><span class="badge bg-info">${field.field_type}</span></td>
                            </tr>
                            <tr>
                                <td><strong>Group:</strong></td>
                                <td>${field.group_name || 'General'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td><strong>Required:</strong></td>
                                <td>${field.is_required ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                            </tr>
                            <tr>
                                <td><strong>Public:</strong></td>
                                <td>${field.is_public ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                            </tr>
                            <tr>
                                <td><strong>Active:</strong></td>
                                <td>${field.is_active ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>'}</td>
                            </tr>
                            <tr>
                                <td><strong>Sort Order:</strong></td>
                                <td>${field.sort_order}</td>
                            </tr>
                            <tr>
                                <td><strong>Created:</strong></td>
                                <td>${new Date(field.created_at).toLocaleDateString()}</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <strong>Default Value</strong>
                            </div>
                            <div class="card-body">
                                <pre class="mb-0"><code>${field.default_value || 'None'}</code></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <strong>Validation Rules</strong>
                            </div>
                            <div class="card-body">
                                <pre class="mb-0"><code>${field.validation_rules || 'None'}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <strong>Help Text</strong>
                            </div>
                            <div class="card-body">
                                <pre class="mb-0"><code>${field.help_text || 'None'}</code></pre>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-header bg-light">
                                <strong>CSS Class</strong>
                            </div>
                            <div class="card-body">
                                <pre class="mb-0"><code>${field.css_class || 'None'}</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (options) {
                html += `
                    <div class="card border mt-3">
                        <div class="card-header bg-light">
                            <strong>Options</strong>
                        </div>
                        <div class="card-body">
                            <pre class="mb-0"><code>${JSON.stringify(options, null, 2)}</code></pre>
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('fieldDetailsContent').innerHTML = html;
            $('#viewFieldModal').modal('show');
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load field details.', 'error');
    });
}

// Edit field
function editField(fieldId) {
    fetch(`../ajax/settings/get-field.php?id=${fieldId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const field = data.field;
            
            // Populate form and open edit modal
            // You can implement this similar to add field modal
            // For now, redirect to edit page
            window.location.href = `edit-field.php?id=${fieldId}`;
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load field.', 'error');
    });
}

// Delete field
function deleteField(fieldId) {
    Swal.fire({
        title: 'Delete Field',
        text: 'Are you sure you want to delete this field?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/delete-field.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    field_id: fieldId
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

// Export custom fields
function exportCustomFields() {
    Swal.fire({
        title: 'Export Custom Fields',
        text: 'Export all custom fields?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('export-custom-fields.php', '_blank');
        }
    });
}

// Import custom fields
function importCustomFields() {
    Swal.fire({
        title: 'Import Custom Fields',
        html: `
            <div class="text-start">
                <p>Import custom fields from file</p>
                <input type="file" class="form-control" id="importFile" accept=".json">
                <div class="form-text">Select JSON file to import</div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Import',
        preConfirm: () => {
            const file = document.getElementById('importFile').files[0];
            if (!file) {
                Swal.showValidationMessage('Please select a file');
                return false;
            }
            return file;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('file', result.value);
            
            fetch('../ajax/settings/import-custom-fields.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Imported!',
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

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleFieldOptions();
});
</script>

<?php require_once '../includes/footer.php'; ?>