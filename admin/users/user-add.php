<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
    exit;
}

$page_title = 'Add New User';
require_once '../includes/header.php';

// Initialize variables
$errors = [];
$success = '';
$form_data = [
    'full_name' => '',
    'email' => '',
    'username' => '',
    'phone' => '',
    'user_type' => 'user',
    'subscription_plan' => 'free',
    'account_status' => 'active'  // Changed from is_active to account_status
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $form_data = array_map('trim', $_POST);
    
    // Validate required fields
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = 'Full name is required';
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($form_data['username'])) {
        $errors['username'] = 'Username is required';
    }
    
    // Check if email already exists
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$form_data['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
        
        // Check if username already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$form_data['username']]);
        if ($stmt->fetch()) {
            $errors['username'] = 'Username already exists';
        }
        
        // If no errors, insert user
        if (empty($errors)) {
            // Generate a temporary password
            $temp_password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Insert user - FIXED: Use account_status instead of is_active
            $stmt = $db->prepare("
                INSERT INTO users (
                    full_name, email, username, phone, 
                    password, user_type, subscription_plan, 
                    account_status, email_verified, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $stmt->execute([
                $form_data['full_name'],
                $form_data['email'],
                $form_data['username'],
                $form_data['phone'] ?? '',
                $hashed_password,
                $form_data['user_type'],
                $form_data['subscription_plan'],
                $form_data['account_status']
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Create user activity log
            $activity_stmt = $db->prepare("
                INSERT INTO user_activities (user_id, activity_type, description) 
                VALUES (?, 'user_created', 'New user account created by admin')
            ");
            $activity_stmt->execute([$user_id]);
            
            // Send notification
            $notification_stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type) 
                VALUES (?, 'Account Created', 'Your account has been created successfully. Please check your email for login details.', 'success')
            ");
            $notification_stmt->execute([$user_id]);
            
            // Store success message with password
            $_SESSION['success'] = 'User created successfully!<br><strong>Temporary Password:</strong> ' . $temp_password . 
                                 '<br><strong>Username:</strong> ' . $form_data['username'] . 
                                 '<br><strong>Email:</strong> ' . $form_data['email'];
            
            // Redirect to users page
            header('Location: /e-commerce/admin/users/users.php');
            exit;
        }
        
    } catch(PDOException $e) {
        $errors['database'] = 'Error creating user: ' . $e->getMessage();
        error_log('User creation error: ' . $e->getMessage());
    }
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Add New User</h1>
                <p class="text-muted mb-0">Create a new user account</p>
            </div>
            <a href="users.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Users
            </a>
        </div>
        
        <!-- Success Message -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <?php if (!empty($errors['database'])): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $errors['database']; ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" novalidate id="userForm">
                            <div class="row g-3">
                                <!-- Full Name -->
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                           id="full_name" 
                                           name="full_name" 
                                           value="<?php echo htmlspecialchars($form_data['full_name']); ?>" 
                                           required
                                           minlength="2"
                                           maxlength="100">
                                    <?php if (isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['full_name']; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="form-text">Enter user's full name (2-100 characters)</div>
                                </div>
                                
                                <!-- Email -->
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" 
                                           class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                                           required>
                                    <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback">
                                        <?php echo $errors['email']; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="form-text">User will receive login credentials at this email</div>
                                </div>
                                
                                <!-- Username -->
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">@</span>
                                        <input type="text" 
                                               class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                               id="username" 
                                               name="username" 
                                               value="<?php echo htmlspecialchars($form_data['username']); ?>" 
                                               required
                                               minlength="3"
                                               maxlength="50"
                                               pattern="[a-zA-Z0-9_]+">
                                    </div>
                                    <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback d-block">
                                        <?php echo $errors['username']; ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="form-text">3-50 characters, letters, numbers and underscore only</div>
                                </div>
                                
                                <!-- Phone -->
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone" 
                                           name="phone" 
                                           value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                           pattern="[0-9+\-\s()]{10,20}">
                                    <div class="form-text">Optional - Include country code</div>
                                </div>
                                
                                <!-- User Type -->
                                <div class="col-md-6">
                                    <label for="user_type" class="form-label">User Type</label>
                                    <select class="form-select" id="user_type" name="user_type" required>
                                        <option value="user" <?php echo $form_data['user_type'] == 'user' ? 'selected' : ''; ?>>Regular User</option>
                                        <option value="admin" <?php echo $form_data['user_type'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                    </select>
                                    <div class="form-text">Administrators have full system access</div>
                                </div>
                                
                                <!-- Subscription Plan -->
                                <div class="col-md-6">
                                    <label for="subscription_plan" class="form-label">Subscription Plan</label>
                                    <select class="form-select" id="subscription_plan" name="subscription_plan" required>
                                        <option value="free" <?php echo $form_data['subscription_plan'] == 'free' ? 'selected' : ''; ?>>Free Plan</option>
                                        <option value="premium" <?php echo $form_data['subscription_plan'] == 'premium' ? 'selected' : ''; ?>>Premium Plan ($9.99/month)</option>
                                        <option value="business" <?php echo $form_data['subscription_plan'] == 'business' ? 'selected' : ''; ?>>Business Plan ($29.99/month)</option>
                                    </select>
                                    <div class="form-text">Determines user permissions and features</div>
                                </div>
                                
                                <!-- Account Status -->
                                <div class="col-12">
                                    <label for="account_status" class="form-label">Account Status</label>
                                    <select class="form-select" id="account_status" name="account_status" required>
                                        <option value="active" <?php echo $form_data['account_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="suspended" <?php echo $form_data['account_status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <option value="deactivated" <?php echo $form_data['account_status'] == 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                    </select>
                                    <div class="form-text">Only active accounts can login to the system</div>
                                </div>
                                
                                <!-- Additional Fields (Optional) -->
                                <div class="col-12 mt-3">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h6 class="card-title">Additional Information (Optional)</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="gender" class="form-label">Gender</label>
                                                    <select class="form-select" id="gender" name="gender">
                                                        <option value="">Select Gender</option>
                                                        <option value="male">Male</option>
                                                        <option value="female">Female</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label for="address" class="form-label">Address</label>
                                                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-user-plus me-2"></i><a href="users.php" class="text-decoration-none text-white">Create User</a> 
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-redo me-2"></i> Reset Form
                                    </button>
                                    <button type="button" class="btn btn-outline-info ms-2" id="generatePassword">
                                        <i class="fas fa-key me-2"></i> Generate New Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Side Info -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="mb-3">
                            <i class="fas fa-info-circle text-primary me-2"></i>
                            Instructions
                        </h6>
                        <ul class="list-unstyled text-muted small">
                            <li class="mb-2">
                                <i class="fas fa-asterisk text-danger me-2"></i>
                                Fields marked with * are required
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-key text-warning me-2"></i>
                                A secure temporary password will be generated
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-envelope text-success me-2"></i>
                                User receives login credentials via email
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-user-shield text-danger me-2"></i>
                                Admins have full system access
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-history text-info me-2"></i>
                                All actions are logged for security
                            </li>
                        </ul>
                        
                        <div class="alert alert-info mt-3">
                            <h6 class="alert-heading">
                                <i class="fas fa-lightbulb me-2"></i>
                                Security Note
                            </h6>
                            <p class="small mb-0">
                                User will be prompted to change their password on first login.
                                Temporary password will be displayed after successful creation.
                            </p>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <h6 class="alert-heading">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Important
                            </h6>
                            <p class="small mb-0">
                                Save the temporary password securely. It will only be shown once.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('userForm');
    const usernameInput = document.getElementById('username');
    
    // Username validation
    usernameInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
    });
    
    // Email validation
    document.getElementById('email').addEventListener('blur', function() {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (this.value && !emailRegex.test(this.value)) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }
    });
    
    // Password generator button
    document.getElementById('generatePassword').addEventListener('click', function() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        // Show password in alert
        alert('Generated Password: ' + password + '\n\nNote: This is for demonstration only. System will generate a secure password automatically.');
    });
    
    // Form submission
    form.addEventListener('submit', function(e) {
        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill all required fields marked with *');
        }
    });
});
</script>

<style>
    .sidbar {
    width: 250px;
    position: fixed;
}
    .main-content {
    flex: 1;
    padding: 20px;
    background: #f8f9fa;
    min-height: calc(100vh - 70px);
}
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        background-color: #f8f9fa;
    }
.input-group-text {
    background-color: #e9ecef;
    border-color: #dee2e6;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
}

.card.bg-light {
    background-color: #f8f9fa !important;
}

.invalid-feedback {
    display: block;
}

.form-text {
    font-size: 0.875rem;
    color: #6c757d;
    margin-top: 0.25rem;
}

.alert .alert-heading {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}
</style>

<?php require_once '../includes/footer.php'; ?>