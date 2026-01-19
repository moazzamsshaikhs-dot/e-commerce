<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

// Handle admin message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_admin_message'])) {
    $subject = $_POST['subject'] ?? '';
    $priority = $_POST['priority'] ?? 'normal';
    $message = $_POST['message'] ?? '';
    $category = $_POST['category'] ?? 'general';
    
    // Basic validation
    if (empty($subject) || empty($message)) {
        $_SESSION['error'] = 'Please fill all required fields';
    } else {
        // In a real system, you would save this to admin_messages table
        // and send email notification to admin
        
        $message_data = [
            'vendor_id' => $_SESSION['user_id'],
            'vendor_name' => $_SESSION['full_name'],
            'subject' => $subject,
            'priority' => $priority,
            'category' => $category,
            'message' => $message,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // For demo, store in session
        if (!isset($_SESSION['admin_messages'])) {
            $_SESSION['admin_messages'] = [];
        }
        $_SESSION['admin_messages'][] = $message_data;
        
        logUserActivity($_SESSION['user_id'], 'admin_message_sent', 
            "Sent message to admin: {$subject}");
        
        $_SESSION['success'] = 'Message sent to admin successfully! You will receive a response within 24 hours.';
        redirect('contact-admin.php');
    }
}

$page_title = 'Contact Admin - Vendor Dashboard';
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Contact Admin</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-user-shield me-1 text-warning"></i>
                        Direct communication with platform administrators
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="support.php" class="btn btn-outline-primary">
                        <i class="fas fa-headset me-2"></i> General Support
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Admin Contact Info -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-0 bg-warning bg-opacity-10">
                    <div class="card-body">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold mb-1">Important Note</h6>
                                <p class="small mb-0">
                                    Use this form only for issues requiring direct admin attention.
                                    For general support, use the regular support system.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Response Time</small>
                                <div class="fw-bold">12-24 Hours</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Available</small>
                                <div class="fw-bold">Monday - Friday</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">Send Message to Admin</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Vendor Name</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" readonly>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Vendor ID</label>
                                    <input type="text" class="form-control" 
                                           value="VENDOR-<?php echo str_pad($_SESSION['user_id'], 6, '0', STR_PAD_LEFT); ?>" readonly>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                                    <input type="text" name="subject" class="form-control" required 
                                           placeholder="Brief summary of your issue">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select name="priority" class="form-select" required>
                                        <option value="low">Low - General Inquiry</option>
                                        <option value="normal" selected>Normal - Standard Request</option>
                                        <option value="high">High - Urgent Matter</option>
                                        <option value="critical">Critical - System Issue</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-select" required>
                                        <option value="account">Account Issues</option>
                                        <option value="payment">Payment Problems</option>
                                        <option value="technical">Technical Problems</option>
                                        <option value="policy">Policy & Compliance</option>
                                        <option value="feature">Feature Request</option>
                                        <option value="abuse">Report Abuse</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Detailed Message <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="8" required 
                                              placeholder="Please provide detailed information about your issue. Include relevant order numbers, product IDs, or transaction references if applicable."></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Attach Files (Optional)</label>
                                    <input type="file" class="form-control" multiple 
                                           accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx">
                                    <small class="text-muted">Maximum 3 files, 5MB each. Screenshots help us understand your issue better.</small>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" required>
                                        <label class="form-check-label">
                                            I understand this message will be reviewed by platform administrators
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="urgent_callback">
                                        <label class="form-check-label">
                                            Request urgent callback for critical issues
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="submit_admin_message" class="btn btn-primary px-5">
                                        <i class="fas fa-paper-plane me-2"></i> Send to Admin
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-redo me-2"></i> Reset Form
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Guidelines & Info -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-info-circle me-2 text-primary"></i>
                            When to Contact Admin
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Account suspension appeals</small>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Payment disputes</small>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Serious technical issues</small>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Policy violation reports</small>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <small>Security concerns</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-times-circle me-2 text-danger"></i>
                            When NOT to Contact Admin
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-times text-danger me-2"></i>
                                <small>General product questions</small>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-times text-danger me-2"></i>
                                <small>Order status updates</small>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-times text-danger me-2"></i>
                                <small>Basic technical support</small>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <i class="fas fa-times text-danger me-2"></i>
                                <small>Feature how-to questions</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- My Messages -->
                <?php if (!empty($_SESSION['admin_messages'])): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2 text-warning"></i>
                            My Recent Messages
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php 
                            $recent_messages = array_slice($_SESSION['admin_messages'], -3);
                            foreach(array_reverse($recent_messages) as $msg):
                                $priority_colors = [
                                    'low' => 'secondary',
                                    'normal' => 'info',
                                    'high' => 'warning',
                                    'critical' => 'danger'
                                ];
                            ?>
                            <div class="list-group-item border-0 px-0 py-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="fw-bold d-block"><?php echo htmlspecialchars($msg['subject']); ?></small>
                                        <small class="text-muted"><?php echo date('M d', strtotime($msg['created_at'])); ?></small>
                                    </div>
                                    <span class="badge bg-<?php echo $priority_colors[$msg['priority']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($msg['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
// Add urgency indicator based on priority
document.querySelector('select[name="priority"]').addEventListener('change', function() {
    const priority = this.value;
    const messageField = document.querySelector('textarea[name="message"]');
    const originalPlaceholder = messageField.getAttribute('placeholder');
    
    let urgencyNote = '';
    switch(priority) {
        case 'high':
            urgencyNote = '\n\n[URGENT: Please respond within 4 hours]';
            break;
        case 'critical':
            urgencyNote = '\n\n[CRITICAL: Immediate attention required]';
            break;
    }
    
    messageField.placeholder = originalPlaceholder.replace(/\n\n\[.*\]/, '') + urgencyNote;
});

// Form validation for critical issues
document.querySelector('form').addEventListener('submit', function(e) {
    const priority = document.querySelector('select[name="priority"]').value;
    const message = document.querySelector('textarea[name="message"]').value;
    
    if (priority === 'critical' && message.length < 50) {
        e.preventDefault();
        alert('For critical issues, please provide detailed information (at least 50 characters).');
        return false;
    }
    
    if (priority === 'critical' || priority === 'high') {
        if (!confirm('You are marking this as a ' + priority.toUpperCase() + ' priority issue. Continue?')) {
            e.preventDefault();
            return false;
        }
    }
    
    return true;
});

// Auto-save draft (optional)
let draftTimer;
document.querySelectorAll('input, textarea, select').forEach(element => {
    element.addEventListener('input', function() {
        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, 2000);
    });
});

function saveDraft() {
    const formData = {
        subject: document.querySelector('input[name="subject"]').value,
        priority: document.querySelector('select[name="priority"]').value,
        category: document.querySelector('select[name="category"]').value,
        message: document.querySelector('textarea[name="message"]').value
    };
    
    // Save to localStorage
    localStorage.setItem('admin_message_draft', JSON.stringify(formData));
    console.log('Draft saved');
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const draft = localStorage.getItem('admin_message_draft');
    if (draft) {
        const formData = JSON.parse(draft);
        
        if (formData.subject) {
            document.querySelector('input[name="subject"]').value = formData.subject;
        }
        if (formData.priority) {
            document.querySelector('select[name="priority"]').value = formData.priority;
        }
        if (formData.category) {
            document.querySelector('select[name="category"]').value = formData.category;
        }
        if (formData.message) {
            document.querySelector('textarea[name="message"]').value = formData.message;
        }
        
        // Clear draft after loading
        localStorage.removeItem('admin_message_draft');
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>