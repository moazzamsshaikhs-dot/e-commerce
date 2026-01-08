<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Email Templates';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get all email templates
    $stmt = $db->query("SELECT * FROM email_templates ORDER BY id DESC");
    $templates = $stmt->fetchAll();
    
    // Get template categories
    $categories = [];
    foreach ($templates as $template) {
        $key_parts = explode('_', $template['template_key']);
        $category = $key_parts[0] ?? 'other';
        if (!in_array($category, $categories)) {
            $categories[] = $category;
        }
    }
    
    // Get email settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%'");
    $email_settings = $stmt->fetchAll();
    $settings = [];
    foreach ($email_settings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
    
} catch(PDOException $e) {
    $error = 'Error loading templates: ' . $e->getMessage();
    $templates = [];
    $categories = [];
    $settings = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Email Templates</h1>
                <p class="text-muted mb-0">Manage email templates and notifications</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="fas fa-plus me-2"></i> Add Template
                </button>
            </div>
        </div>
        
        <!-- Email Configuration Status -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Email Configuration</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>SMTP Status:</span>
                            <span class="badge bg-<?php echo !empty($settings['smtp_host']) ? 'success' : 'danger'; ?>">
                                <?php echo !empty($settings['smtp_host']) ? 'Configured' : 'Not Configured'; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>From Email:</span>
                            <span><?php echo $settings['email_from_address'] ?? 'Not set'; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>From Name:</span>
                            <span><?php echo $settings['email_from_name'] ?? 'Not set'; ?></span>
                        </div>
                        <div class="mt-3">
                            <a href="settings-group.php?group=email" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fas fa-cog me-2"></i> Configure Email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Test Email</h6>
                        <p class="text-muted small mb-3">Send a test email to verify configuration</p>
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" id="testEmail" 
                                   placeholder="Enter test email" value="<?php echo $_SESSION['email'] ?? ''; ?>">
                            <button class="btn btn-outline-primary" onclick="sendTestEmail()">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Quick Actions</h6>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary text-start" onclick="exportTemplates()">
                                <i class="fas fa-download me-2"></i> Export Templates
                            </button>
                            <button class="btn btn-outline-success text-start" onclick="resetTemplates()">
                                <i class="fas fa-undo me-2"></i> Reset to Default
                            </button>
                            <button class="btn btn-outline-info text-start" data-bs-toggle="modal" data-bs-target="#variablesModal">
                                <i class="fas fa-code me-2"></i> View Variables
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Template Categories -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap mb-3">
                    <button class="btn btn-sm mb-1 me-2 btn-primary" onclick="filterTemplates('all')">
                        All Templates
                    </button>
                    <?php foreach($categories as $category): ?>
                    <button class="btn btn-sm mb-1 me-2 btn-outline-primary" 
                            onclick="filterTemplates('<?php echo $category; ?>')">
                        <?php echo ucfirst($category); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Templates Grid -->
        <div class="row" id="templatesContainer">
            <?php foreach($templates as $template): 
                $category = explode('_', $template['template_key'])[0] ?? 'other';
                $variables = $template['variables'] ? json_decode($template['variables'], true) : [];
            ?>
            <div class="col-md-6 col-lg-4 mb-4 template-card" data-category="<?php echo $category; ?>">
                <div class="card border h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="card-title mb-1"><?php echo $template['name']; ?></h6>
                                <small class="text-muted">
                                    <code><?php echo $template['template_key']; ?></code>
                                </small>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input template-toggle" 
                                       type="checkbox" 
                                       data-id="<?php echo $template['id']; ?>"
                                       <?php echo $template['is_active'] ? 'checked' : ''; ?>
                                       onchange="toggleTemplate(this, <?php echo $template['id']; ?>)">
                            </div>
                        </div>
                        
                        <p class="card-text small text-muted mb-3">
                            <?php echo $template['subject'] ? 'Subject: ' . $template['subject'] : 'No subject set'; ?>
                        </p>
                        
                        <?php if (!empty($variables)): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Available Variables:</small>
                            <div class="d-flex flex-wrap">
                                <?php foreach($variables as $variable): ?>
                                <span class="badge bg-light text-dark me-1 mb-1"><?php echo $variable; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="btn-group w-100">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="editTemplate(<?php echo $template['id']; ?>)">
                                <i class="fas fa-edit me-1"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-info" 
                                    onclick="previewTemplate(<?php echo $template['id']; ?>)">
                                <i class="fas fa-eye me-1"></i> Preview
                            </button>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteTemplate(<?php echo $template['id']; ?>)">
                                <i class="fas fa-trash me-1"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 border-top">
                        <small class="text-muted">
                            Updated: <?php echo date('M d, Y', strtotime($template['updated_at'])); ?>
                        </small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($templates)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-envelope fa-4x text-muted mb-3"></i>
                    <h5>No Email Templates</h5>
                    <p class="text-muted">No email templates found</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                        <i class="fas fa-plus me-2"></i> Create First Template
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-labelledby="addTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addTemplateModalLabel">Add Email Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addTemplateForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Key <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="template_key" required 
                                   pattern="[a-z0-9_]+" placeholder="e.g., welcome_email, order_confirmation">
                            <div class="form-text">Lowercase with underscores only</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required 
                                   placeholder="e.g., Welcome Email, Order Confirmation">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="subject" required 
                               placeholder="e.g., Welcome to {{site_name}}">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" rows="10" required 
                                  placeholder="HTML email content with variables like {{user_name}}"></textarea>
                        <div class="form-text">Use HTML for email formatting. Variables: {{variable_name}}</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Variables (JSON array)</label>
                        <textarea class="form-control" name="variables" rows="3" 
                                  placeholder='["site_name", "user_name", "user_email"]'></textarea>
                        <div class="form-text">List all variables used in the template</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveTemplate()">Save Template</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1" aria-labelledby="editTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTemplateModalLabel">Edit Email Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="editTemplateContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Variables Modal -->
<div class="modal fade" id="variablesModal" tabindex="-1" aria-labelledby="variablesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="variablesModalLabel">Available Variables</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Variable</th>
                                <th>Description</th>
                                <th>Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>{{site_name}}</code></td>
                                <td>Website name from settings</td>
                                <td>ShopEase Pro</td>
                            </tr>
                            <tr>
                                <td><code>{{site_url}}</code></td>
                                <td>Website URL</td>
                                <td>https://example.com</td>
                            </tr>
                            <tr>
                                <td><code>{{user_name}}</code></td>
                                <td>User's full name</td>
                                <td>John Doe</td>
                            </tr>
                            <tr>
                                <td><code>{{user_email}}</code></td>
                                <td>User's email address</td>
                                <td>john@example.com</td>
                            </tr>
                            <tr>
                                <td><code>{{order_number}}</code></td>
                                <td>Order number</td>
                                <td>ORD-2024-001</td>
                            </tr>
                            <tr>
                                <td><code>{{order_total}}</code></td>
                                <td>Order total amount</td>
                                <td>$99.99</td>
                            </tr>
                            <tr>
                                <td><code>{{reset_link}}</code></td>
                                <td>Password reset link</td>
                                <td>https://example.com/reset-password?token=xyz</td>
                            </tr>
                            <tr>
                                <td><code>{{current_date}}</code></td>
                                <td>Current date</td>
                                <td>January 15, 2024</td>
                            </tr>
                            <tr>
                                <td><code>{{current_year}}</code></td>
                                <td>Current year</td>
                                <td>2024</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Filter templates by category
function filterTemplates(category) {
    const templateCards = document.querySelectorAll('.template-card');
    
    templateCards.forEach(card => {
        if (category === 'all' || card.dataset.category === category) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update button states
    document.querySelectorAll('#templatesContainer').closest('.card').querySelectorAll('.btn').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    
    if (event) {
        event.target.classList.remove('btn-outline-primary');
        event.target.classList.add('btn-primary');
    }
}

// Toggle template active status
function toggleTemplate(checkbox, templateId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('../ajax/settings/toggle-email-template.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            template_id: templateId,
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

// Edit template
function editTemplate(templateId) {
    fetch(`../ajax/settings/settings/get-email-template.php?id=${templateId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const template = data.template;
            const variables = template.variables ? JSON.parse(template.variables) : [];
            
            document.getElementById('editTemplateContent').innerHTML = `
                <form id="editTemplateForm">
                    <input type="hidden" name="template_id" value="${template.id}">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Key</label>
                            <input type="text" class="form-control" value="${template.template_key}" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Name</label>
                            <input type="text" class="form-control" name="name" value="${template.name}" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Subject</label>
                        <input type="text" class="form-control" name="subject" value="${template.subject}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email Content</label>
                        <textarea class="form-control" name="content" rows="15" required>${template.content}</textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Variables (JSON)</label>
                            <textarea class="form-control" name="variables" rows="5">${template.variables}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Preview Variables</label>
                            <div class="bg-light p-3 rounded">
                                <h6>Available Variables:</h6>
                                ${variables.map(v => `<span class="badge bg-info me-1 mb-1">${v}</span>`).join('')}
                                <div class="mt-3">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="testTemplate(${template.id})">
                                        <i class="fas fa-paper-plane me-1"></i> Send Test
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <div>
                            <button type="button" class="btn btn-outline-primary me-2" onclick="previewTemplate(${template.id})">
                                <i class="fas fa-eye me-1"></i> Preview
                            </button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </div>
                </form>
            `;
            
            $('#editTemplateModal').modal('show');
            
            // Add form submission handler
            document.getElementById('editTemplateForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveTemplateChanges();
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load template.', 'error');
    });
}

// Save template
function saveTemplate() {
    const form = document.getElementById('addTemplateForm');
    const formData = new FormData(form);
    
    // Validate variables JSON
    const variables = formData.get('variables');
    if (variables) {
        try {
            JSON.parse(variables);
        } catch (e) {
            Swal.fire('Error!', 'Variables must be valid JSON.', 'error');
            return;
        }
    }

    fetch('../ajax/settings/save-email-template.php', {
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
                $('#addTemplateModal').modal('hide');
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

// Save template changes
function saveTemplateChanges() {
    const form = document.getElementById('editTemplateForm');
    const formData = new FormData(form);
    
    // Validate variables JSON
    const variables = formData.get('variables');
    if (variables) {
        try {
            JSON.parse(variables);
        } catch (e) {
            Swal.fire('Error!', 'Variables must be valid JSON.', 'error');
            return;
        }
    }

    fetch('../ajax/settings/save-email-template.php', {
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
                $('#editTemplateModal').modal('hide');
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

// Preview template
function previewTemplate(templateId) {
    window.open(`preview-email.php?id=${templateId}`, '_blank');
}

// Test template
function testTemplate(templateId) {
    Swal.fire({
        title: 'Test Email',
        input: 'email',
        inputLabel: 'Enter email address to send test',
        inputPlaceholder: 'test@example.com',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Send Test'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/send-test-email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    template_id: templateId,
                    email: result.value
                })
            })
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

// Send test email
function sendTestEmail() {
    const email = document.getElementById('testEmail').value;
    
    if (!email) {
        Swal.fire('Error!', 'Please enter an email address.', 'error');
        return;
    }
    
    if (!validateEmail(email)) {
        Swal.fire('Error!', 'Please enter a valid email address.', 'error');
        return;
    }

    fetch('../ajax/settings/send-test-config-email.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            email: email
        })
    })
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

// Delete template
function deleteTemplate(templateId) {
    Swal.fire({
        title: 'Delete Template',
        text: 'Are you sure you want to delete this template?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/delete-email-template.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    template_id: templateId
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

// Export templates
function exportTemplates() {
    Swal.fire({
        title: 'Export Templates',
        text: 'Export all email templates?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Export'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open('export-email-templates.php', '_blank');
        }
    });
}

// Reset templates
function resetTemplates() {
    Swal.fire({
        title: 'Reset Templates',
        text: 'Reset all templates to default? This cannot be undone!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Reset to Default'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../../ajax/settings/reset-email-templates.php')
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

// Email validation
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}
</script>

<?php require_once '../includes/footer.php'; ?>