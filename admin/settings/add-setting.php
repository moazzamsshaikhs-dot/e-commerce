<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$group = isset($_GET['group']) ? $_GET['group'] : 'general';

try {
    $db = getDB();
    
    // Get groups for dropdown
    $stmt = $db->query("SELECT * FROM settings_groups WHERE is_active = 1 ORDER BY sort_order");
    $groups = $stmt->fetchAll();
    
    // Get categories
    $stmt = $db->query("SELECT DISTINCT category FROM settings WHERE category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get existing setting keys
    $stmt = $db->query("SELECT setting_key FROM settings");
    $existing_keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading data: ' . $e->getMessage();
    redirect('settings.php');
}

$page_title = 'Add New Setting';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Add New Setting</h1>
                <p class="text-muted mb-0">Create a new system setting</p>
            </div>
            <div>
                <a href="settings.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Settings
                </a>
            </div>
        </div>
        
        <!-- Setting Form -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form id="addSettingForm" method="POST" action="<?php echo SITE_URL; ?>admin/ajax/add-setting.php">
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-6 mb-4">
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Setting Key <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="setting_key" required 
                                       pattern="[a-z0-9_]+" title="Lowercase letters, numbers, and underscores only"
                                       placeholder="e.g., site_title, max_file_size">
                                <div class="form-text">
                                    Use lowercase with underscores. Must be unique.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Display Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="display_name" required 
                                       placeholder="e.g., Site Title, Maximum File Size">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Setting Group <span class="text-danger">*</span></label>
                                <select class="form-select" name="group" required>
                                    <option value="">Select Group</option>
                                    <?php foreach($groups as $group_item): ?>
                                    <option value="<?php echo $group_item['slug']; ?>" 
                                            <?php echo $group_item['slug'] == $group ? 'selected' : ''; ?>>
                                        <?php echo $group_item['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <input type="text" class="form-control" name="category" 
                                       list="categoryList" 
                                       placeholder="Enter or select category">
                                <datalist id="categoryList">
                                    <?php foreach($categories as $category): ?>
                                    <option value="<?php echo $category; ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>
                        
                        <!-- Setting Configuration -->
                        <div class="col-md-6 mb-4">
                            <h5 class="mb-3">Setting Configuration</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Setting Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="setting_type" required id="settingType"
                                        onchange="toggleTypeOptions()">
                                    <option value="text">Text</option>
                                    <option value="textarea">Textarea</option>
                                    <option value="number">Number</option>
                                    <option value="email">Email</option>
                                    <option value="password">Password</option>
                                    <option value="url">URL</option>
                                    <option value="color">Color</option>
                                    <option value="boolean">Boolean (Yes/No)</option>
                                    <option value="select">Select (Dropdown)</option>
                                    <option value="json">JSON</option>
                                    <option value="file">File</option>
                                </select>
                            </div>
                            
                            <!-- Default Value -->
                            <div class="mb-3">
                                <label class="form-label">Default Value</label>
                                <input type="text" class="form-control" name="default_value" 
                                       id="defaultValue" placeholder="Default value for this setting">
                            </div>
                            
                            <!-- Options for Select type -->
                            <div class="mb-3" id="optionsContainer" style="display: none;">
                                <label class="form-label">Options (for Select type)</label>
                                <textarea class="form-control" name="options" rows="3" 
                                          placeholder='Enter options as JSON array: ["option1", "option2"] or as key-value: {"key1": "Value 1", "key2": "Value 2"}'></textarea>
                                <div class="form-text">
                                    Enter as JSON array or object
                                </div>
                            </div>
                            
                            <!-- Validation Rules -->
                            <div class="mb-3">
                                <label class="form-label">Validation Rules</label>
                                <input type="text" class="form-control" name="validation_rules" 
                                       placeholder="e.g., required|email|max:255">
                                <div class="form-text">
                                    Pipe-separated rules: required, email, url, numeric, min:5, max:100, regex:/^[a-z]+$/
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced Options -->
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Advanced Options</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Help Text</label>
                                <textarea class="form-control" name="help_text" rows="2" 
                                          placeholder="Help text to show below the setting field"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" value="0">
                            </div>
                            
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_required" id="isRequired">
                                        <label class="form-check-label" for="isRequired">
                                            Required Field
                                        </label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_public" id="isPublic">
                                        <label class="form-check-label" for="isPublic">
                                            Public Setting
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Preview</h5>
                            <div class="card border">
                                <div class="card-body">
                                    <div id="settingPreview">
                                        <p class="text-muted mb-0">Preview will appear here</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="mt-4">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="fas fa-redo me-2"></i> Reset
                            </button>
                            <div>
                                <a href="settings.php" class="btn btn-secondary me-2">Cancel</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i> Add Setting
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Existing Settings -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Existing Setting Keys</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php 
                    $chunks = array_chunk($existing_keys, ceil(count($existing_keys) / 3));
                    foreach($chunks as $chunk): 
                    ?>
                    <div class="col-md-4">
                        <div class="list-group list-group-flush">
                            <?php foreach($chunk as $key): ?>
                            <div class="list-group-item px-0 border-0">
                                <code><?php echo $key; ?></code>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Toggle type-specific options
function toggleTypeOptions() {
    const type = document.getElementById('settingType').value;
    const optionsContainer = document.getElementById('optionsContainer');
    const defaultValue = document.getElementById('defaultValue');
    
    // Show/hide options for select type
    if (type === 'select') {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
    }
    
    // Update default value placeholder
    switch(type) {
        case 'text':
            defaultValue.placeholder = 'Enter text value';
            break;
        case 'number':
            defaultValue.placeholder = 'Enter number value';
            break;
        case 'email':
            defaultValue.placeholder = 'Enter email address';
            break;
        case 'url':
            defaultValue.placeholder = 'Enter URL';
            break;
        case 'color':
            defaultValue.placeholder = '#000000';
            break;
        case 'boolean':
            defaultValue.placeholder = '1 for Yes, 0 for No';
            break;
        case 'json':
            defaultValue.placeholder = 'Enter JSON value';
            break;
        case 'file':
            defaultValue.placeholder = 'Enter filename';
            break;
    }
    
    updatePreview();
}

// Update preview
function updatePreview() {
    const type = document.getElementById('settingType').value;
    const preview = document.getElementById('settingPreview');
    let html = '';
    
    switch(type) {
        case 'text':
            html = '<input type="text" class="form-control" placeholder="Text input">';
            break;
        case 'textarea':
            html = '<textarea class="form-control" rows="3" placeholder="Textarea"></textarea>';
            break;
        case 'number':
            html = '<input type="number" class="form-control" placeholder="Number input">';
            break;
        case 'email':
            html = '<input type="email" class="form-control" placeholder="Email input">';
            break;
        case 'password':
            html = '<input type="password" class="form-control" placeholder="Password input">';
            break;
        case 'url':
            html = '<input type="url" class="form-control" placeholder="URL input">';
            break;
        case 'color':
            html = '<input type="color" class="form-control" value="#4e73df">';
            break;
        case 'boolean':
            html = `
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox">
                    <label class="form-check-label">Enable/Disable</label>
                </div>
            `;
            break;
        case 'select':
            html = `
                <select class="form-select">
                    <option>Option 1</option>
                    <option>Option 2</option>
                    <option>Option 3</option>
                </select>
            `;
            break;
        case 'json':
            html = '<textarea class="form-control font-monospace" rows="3" placeholder="{}"></textarea>';
            break;
        case 'file':
            html = `
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Filename" readonly>
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fas fa-upload"></i>
                    </button>
                </div>
            `;
            break;
    }
    
    preview.innerHTML = html;
}

// Reset form
function resetForm() {
    Swal.fire({
        title: 'Reset Form',
        text: 'Clear all form fields?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Reset'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('addSettingForm').reset();
            toggleTypeOptions();
            Swal.fire('Reset!', 'Form has been reset.', 'success');
        }
    });
}

// Form submission
document.getElementById('addSettingForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate setting key
    const settingKey = document.querySelector('input[name="setting_key"]').value;
    const existingKeys = <?php echo json_encode($existing_keys); ?>;
    
    if (existingKeys.includes(settingKey)) {
        Swal.fire('Error!', 'Setting key already exists. Please choose a different key.', 'error');
        return;
    }
    
    // Validate options for select type
    const settingType = document.getElementById('settingType').value;
    if (settingType === 'select') {
        const options = document.querySelector('textarea[name="options"]').value;
        if (options) {
            try {
                JSON.parse(options);
            } catch (e) {
                Swal.fire('Error!', 'Options must be valid JSON.', 'error');
                return;
            }
        }
    }
    
    // Submit form
    const formData = new FormData(this);
    
    Swal.fire({
        title: 'Add Setting',
        text: 'Create new system setting?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Add Setting'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Added!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = `settings-group.php?group=${data.group}`;
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred while adding the setting.', 'error');
            });
        }
    });
});

// Event listeners for real-time preview
document.getElementById('settingType').addEventListener('change', toggleTypeOptions);
document.querySelectorAll('#addSettingForm input, #addSettingForm select, #addSettingForm textarea').forEach(element => {
    element.addEventListener('input', updatePreview);
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleTypeOptions();
});
</script>

<?php require_once '../includes/footer.php'; ?>