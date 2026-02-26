<?php
// admin/users/user-add.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: ../index.php');
    exit;
}

$page_title = 'Add New User';
require_once '../includes/header.php';

// Initialize variables
$errors = [];
$form_data = [
    'full_name' => '',
    'email' => '',
    'username' => '',
    'phone' => '',
    'user_type' => 'user',
    'subscription_plan' => 'free',
    'account_status' => 'active',
    'gender' => '',
    'date_of_birth' => '',
    'address' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $form_data = array_map('trim', $_POST);
    
    // Validate required fields
    if (empty($form_data['full_name'])) {
        $errors['full_name'] = 'Full name is required';
    } elseif (strlen($form_data['full_name']) < 2) {
        $errors['full_name'] = 'Full name must be at least 2 characters';
    }
    
    if (empty($form_data['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    if (empty($form_data['username'])) {
        $errors['username'] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
        $errors['username'] = 'Username can only contain letters, numbers and underscores';
    } elseif (strlen($form_data['username']) < 3 || strlen($form_data['username']) > 50) {
        $errors['username'] = 'Username must be between 3 and 50 characters';
    }
    
    // Check if email already exists
    if (empty($errors['email'])) {
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
                // Generate a secure temporary password
                $temp_password = bin2hex(random_bytes(4)); // 8 characters
                $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
                
                // Insert user
                $stmt = $db->prepare("
                    INSERT INTO users (
                        full_name, email, username, phone, 
                        password, user_type, subscription_plan, 
                        account_status, gender, date_of_birth, address,
                        email_verified, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $form_data['full_name'],
                    $form_data['email'],
                    $form_data['username'],
                    $form_data['phone'] ?: null,
                    $hashed_password,
                    $form_data['user_type'],
                    $form_data['subscription_plan'],
                    $form_data['account_status'],
                    $form_data['gender'] ?: null,
                    $form_data['date_of_birth'] ?: null,
                    $form_data['address'] ?: null
                ]);
                
                $user_id = $db->lastInsertId();
                
                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $log = $db->prepare("
                    INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
                    VALUES (?, 'user_created', ?, ?, ?, NOW())
                ");
                $log->execute([$_SESSION['user_id'], "Created new user: {$form_data['username']} ({$form_data['email']})", $ip, $ua]);
                
                // Create notification for new user
                $notify = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Welcome to Our Platform', ?, 'success', NOW())
                ");
                $notify->execute([$user_id, "Your account has been created successfully. Your username: {$form_data['username']}. Please login with the temporary password sent to your email."]);
                
                // Store success message with password in session
                $_SESSION['success'] = [
                    'title' => 'User Created Successfully!',
                    'message' => "User <strong>{$form_data['full_name']}</strong> has been created.",
                    'credentials' => [
                        'username' => $form_data['username'],
                        'password' => $temp_password,
                        'email' => $form_data['email']
                    ]
                ];
                
                header('Location: users.php');
                exit;
            }
            
        } catch(PDOException $e) {
            $errors['database'] = 'Database error: ' . $e->getMessage();
            error_log('User creation error: ' . $e->getMessage());
        }
    }
}

// Get countries for dropdown (if needed)
$countries = [];
try {
    $db = getDB();
    $stmt = $db->query("SELECT code, name FROM countries ORDER BY name");
    $countries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Ignore
}
?>

<style>
:root {
    --primary: #4361ee;
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --dark: #2b2d42;
    --light: #f8f9fa;
}

.add-user-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Header */
.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success), var(--warning), var(--danger));
}

/* Form Card */
.form-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #edf2f9;
}

.form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.form-section-title i {
    width: 35px;
    height: 35px;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid #edf2f9;
    padding: 12px 15px;
    transition: all 0.3s ease;
    font-size: 14px;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.form-control.is-invalid {
    border-color: var(--danger);
    background-image: none;
}

.form-control.is-invalid:focus {
    box-shadow: 0 0 0 3px rgba(239, 71, 111, 0.1);
}

/* Input Group */
.input-group-text {
    background: #f8f9fa;
    border: 2px solid #edf2f9;
    border-radius: 12px 0 0 12px;
    color: #6c757d;
}

.input-group .form-control {
    border-radius: 0 12px 12px 0;
}

/* Password Strength */
.password-strength {
    height: 5px;
    border-radius: 5px;
    margin-top: 8px;
    transition: all 0.3s ease;
    background: #edf2f9;
    overflow: hidden;
}

.strength-bar {
    height: 100%;
    width: 0;
    transition: all 0.3s ease;
}

.strength-0 { width: 0; }
.strength-1 { width: 25%; background: var(--danger); }
.strength-2 { width: 50%; background: var(--warning); }
.strength-3 { width: 75%; background: var(--info); }
.strength-4 { width: 100%; background: var(--success); }

.strength-text {
    font-size: 12px;
    margin-top: 5px;
}

/* Info Card */
.info-card {
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
    border-radius: 20px;
    padding: 25px;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.info-card::before {
    content: '\f0eb';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    bottom: -20px;
    right: -20px;
    font-size: 120px;
    color: rgba(67, 97, 238, 0.1);
    transform: rotate(15deg);
}

.info-card-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    position: relative;
    z-index: 1;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
    position: relative;
    z-index: 1;
}

.info-list li {
    padding: 10px 0;
    border-bottom: 1px dashed rgba(67, 97, 238, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-list li:last-child {
    border-bottom: none;
}

.info-list i {
    width: 30px;
    height: 30px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 14px;
}

/* Buttons */
.btn-submit {
    background: var(--primary);
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-submit:hover {
    background: #3651c4;
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
    color: white;
}

.btn-reset {
    background: #6c757d;
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
}

.btn-reset:hover {
    background: #5a6268;
    transform: translateY(-2px);
    color: white;
}

.btn-generate {
    background: var(--info);
    color: white;
    border: none;
    padding: 14px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.btn-generate:hover {
    background: #3aa0c0;
    transform: translateY(-2px);
    color: white;
}

/* Alert */
.alert-custom {
    border-radius: 15px;
    border: none;
    padding: 20px;
    margin-bottom: 25px;
}

.alert-danger {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger);
}

.alert-warning {
    background: rgba(255, 183, 3, 0.1);
    color: var(--warning);
}

/* Responsive */
@media (max-width: 768px) {
    .add-user-container {
        padding: 20px;
    }
    
    .form-card {
        padding: 20px;
    }
    
    .info-card {
        margin-top: 20px;
    }
}
</style>

<div class="add-user-container">
    <!-- Page Header -->
    <div class="page-header animate-slide-in">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h2 fw-bold mb-1">
                    <i class="fas fa-user-plus me-2 text-primary"></i>
                    Add New User
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-users me-2"></i>
                    Create a new user account with custom permissions
                </p>
            </div>
            <a href="users.php" class="btn btn-outline-secondary btn-lg">
                <i class="fas fa-arrow-left me-2"></i>
                Back to Users
            </a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show animate-slide-in delay-1">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                <div>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <?php if (!is_array($error)): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Form Column -->
        <div class="col-lg-8">
            <div class="form-card animate-slide-in delay-2">
                <form method="POST" id="userForm" novalidate>
                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-info-circle"></i>
                            Basic Information
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-user me-2 text-primary"></i>
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" 
                                       class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                       name="full_name" 
                                       value="<?php echo htmlspecialchars($form_data['full_name']); ?>"
                                       placeholder="Enter full name"
                                       required>
                                <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['full_name']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-envelope me-2 text-primary"></i>
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" 
                                       class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                       placeholder="user@example.com"
                                       required>
                                <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback">
                                    <?php echo $errors['email']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-at me-2 text-primary"></i>
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" 
                                           class="form-control <?php echo isset($errors['username']) ? 'is-invalid' : ''; ?>" 
                                           name="username" 
                                           value="<?php echo htmlspecialchars($form_data['username']); ?>"
                                           placeholder="username"
                                           pattern="[a-zA-Z0-9_]+"
                                           required>
                                </div>
                                <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback d-block">
                                    <?php echo $errors['username']; ?>
                                </div>
                                <?php endif; ?>
                                <small class="text-muted">Only letters, numbers and underscore allowed</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-phone me-2 text-primary"></i>
                                    Phone Number
                                </label>
                                <input type="tel" 
                                       class="form-control" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                       placeholder="+1 234 567 8900">
                                <small class="text-muted">Include country code</small>
                            </div>
                        </div>
                    </div>

                    <!-- Account Settings Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-cog"></i>
                            Account Settings
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-user-tag me-2 text-primary"></i>
                                    User Type
                                </label>
                                <select class="form-select" name="user_type" id="userType">
                                    <option value="user" <?php echo $form_data['user_type'] == 'user' ? 'selected' : ''; ?>>Regular User</option>
                                    <option value="vendor" <?php echo $form_data['user_type'] == 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                                    <option value="admin" <?php echo $form_data['user_type'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-crown me-2 text-primary"></i>
                                    Subscription Plan
                                </label>
                                <select class="form-select" name="subscription_plan">
                                    <option value="free" <?php echo $form_data['subscription_plan'] == 'free' ? 'selected' : ''; ?>>Free Plan</option>
                                    <option value="premium" <?php echo $form_data['subscription_plan'] == 'premium' ? 'selected' : ''; ?>>Premium Plan</option>
                                    <option value="business" <?php echo $form_data['subscription_plan'] == 'business' ? 'selected' : ''; ?>>Business Plan</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">
                                    <i class="fas fa-toggle-on me-2 text-primary"></i>
                                    Account Status
                                </label>
                                <select class="form-select" name="account_status">
                                    <option value="active" <?php echo $form_data['account_status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $form_data['account_status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="deactivated" <?php echo $form_data['account_status'] == 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-address-card"></i>
                            Personal Information (Optional)
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo $form_data['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $form_data['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $form_data['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" 
                                       class="form-control" 
                                       name="date_of_birth" 
                                       value="<?php echo htmlspecialchars($form_data['date_of_birth']); ?>"
                                       max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Country</label>
                                <select class="form-select" name="country">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo $country['code']; ?>">
                                        <?php echo htmlspecialchars($country['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" 
                                          name="address" 
                                          rows="3"
                                          placeholder="Street address, city, state, postal code"><?php echo htmlspecialchars($form_data['address']); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="d-flex gap-3 justify-content-end mt-4">
                        <button type="button" class="btn-generate" id="generatePassword">
                            <i class="fas fa-key"></i>
                            Generate Password
                        </button>
                        <button type="reset" class="btn-reset">
                            <i class="fas fa-redo"></i>
                            Reset Form
                        </button>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i>
                            Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Sidebar -->
        <div class="col-lg-4">
            <div class="info-card animate-slide-in delay-3">
                <div class="info-card-title">
                    <i class="fas fa-lightbulb me-2"></i>
                    Quick Tips
                </div>
                <ul class="info-list">
                    <li>
                        <i class="fas fa-asterisk text-danger"></i>
                        <span>Fields marked with * are required</span>
                    </li>
                    <li>
                        <i class="fas fa-key text-warning"></i>
                        <span>A secure password will be generated automatically</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope text-success"></i>
                        <span>User will receive login credentials via email</span>
                    </li>
                    <li>
                        <i class="fas fa-shield-alt text-info"></i>
                        <span>Admin users have full system access</span>
                    </li>
                    <li>
                        <i class="fas fa-history text-primary"></i>
                        <span>All actions are logged for security</span>
                    </li>
                </ul>

                <div class="alert alert-warning mt-4 mb-0" style="background: rgba(255, 183, 3, 0.1); border: none; border-radius: 15px;">
                    <h6 class="fw-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Important Note
                    </h6>
                    <p class="small mb-0">
                        The temporary password will be shown only once after successful creation. 
                        Make sure to save it securely.
                    </p>
                </div>

                <div class="mt-4 text-center">
                    <img src="../../assets/images/user-illustration.jpg" alt="User" style="max-width: 100%; opacity: 0.8;">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Username validation - only letters, numbers, underscore
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        });
    }

    // Email validation on blur
    const emailInput = document.querySelector('input[name="email"]');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }

    // Password generator
    document.getElementById('generatePassword')?.addEventListener('click', function() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%';
        let password = '';
        for (let i = 0; i < 10; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        // Show generated password in a modal
        const modalHtml = `
            <div class="modal fade" id="passwordModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-key me-2"></i>
                                Generated Password
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <p>Your generated password is:</p>
                            <div class="p-3 bg-light rounded-15">
                                <code style="font-size: 24px;">${password}</code>
                            </div>
                            <p class="text-muted small mt-3">
                                Note: This is for testing only. The system will generate a secure password automatically.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('passwordModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to DOM and show it
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const passwordModal = new bootstrap.Modal(document.getElementById('passwordModal'));
        passwordModal.show();
    });

    // Form submission validation
    const form = document.getElementById('userForm');
    if (form) {
        form.addEventListener('submit', function(e) {
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
                alert('Please fill all required fields.');
            }
        });
    }
});

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>