<?php
// admin/users/user-edit.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/super-admin-check.php';

// Require super admin access
requireSuperAdmin();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];
$db = getDB();
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $user_type = $_POST['user_type'] ?? 'user';
    $account_status = $_POST['account_status'] ?? 'active';
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Check if email exists for other users
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    if ($stmt->fetch()) {
        $errors[] = 'Email already exists';
    }
    
    // Password validation
    if (!empty($new_password)) {
        if (strlen($new_password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        if ($new_password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Build update query
            $query = "
                UPDATE users SET 
                    full_name = ?,
                    email = ?,
                    phone = ?,
                    user_type = ?,
                    account_status = ?,
                    address = ?,
                    city = ?,
                    country = ?,
                    postal_code = ?,
                    updated_at = NOW()
            ";
            $params = [$full_name, $email, $phone, $user_type, $account_status, $address, $city, $country, $postal_code];
            
            // Add password if provided
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $query .= ", password = ?";
                $params[] = $hashed_password;
            }
            
            $query .= " WHERE id = ?";
            $params[] = $user_id;
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            // Log activity
            logUserActivity($_SESSION['user_id'], 'user_updated', "Updated user ID: {$user_id}");
            
            $db->commit();
            
            $_SESSION['success'] = 'User updated successfully!';
            header('Location: user-view.php?id=' . $user_id);
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Fetch user details
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = 'User not found.';
        header('Location: users.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: users.php');
    exit;
}

$page_title = 'Edit User: ' . ($user['full_name'] ?? $user['username']);
require_once '../includes/header.php';
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3651c4;
    --primary-light: rgba(67, 97, 238, 0.1);
    --success: #06d6a0;
    --success-dark: #05b585;
    --success-light: rgba(6, 214, 160, 0.1);
    --warning: #ffb703;
    --warning-dark: #e6a500;
    --warning-light: rgba(255, 183, 3, 0.1);
    --danger: #ef476f;
    --danger-dark: #d64161;
    --danger-light: rgba(239, 71, 111, 0.1);
    --info: #4cc9f0;
    --info-dark: #3aa9d9;
    --info-light: rgba(76, 201, 240, 0.1);
    --dark: #2b2d42;
    --dark-light: rgba(43, 45, 66, 0.1);
    --light: #f8f9fa;
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --transition: all 0.3s ease;
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

.edit-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
    border-radius: 50%;
    z-index: 0;
}

.page-header > div {
    position: relative;
    z-index: 1;
}

/* Form Card */
.form-card {
    background: white;
    border-radius: var(--radius-xl);
    padding: 30px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-title i {
    color: var(--primary);
}

.form-label {
    font-weight: 500;
    color: var(--dark);
    margin-bottom: 8px;
}

.form-control, .form-select {
    border-radius: var(--radius);
    border: 2px solid var(--border);
    padding: 10px 15px;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
    outline: none;
}

.form-text {
    color: var(--dark);
    opacity: 0.6;
    font-size: 12px;
    margin-top: 5px;
}

.btn-save {
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: var(--radius);
    font-weight: 600;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-save:hover {
    background: var(--primary-dark);
    transform: translateY(-2px);
    color: white;
}

.btn-cancel {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
    padding: 12px 30px;
    border-radius: var(--radius);
    font-weight: 600;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-cancel:hover {
    background: var(--border);
    transform: translateY(-2px);
    color: var(--dark);
}

.alert-danger {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
    border-radius: var(--radius);
    padding: 15px;
    margin-bottom: 20px;
}

.alert-danger ul {
    margin-bottom: 0;
    padding-left: 20px;
}

/* Responsive */
@media (max-width: 768px) {
    .edit-container {
        padding: 20px;
    }
    
    .form-card {
        padding: 20px;
    }
}
</style>

<div class="edit-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-user-edit me-2" style="color: var(--primary);"></i>
                    Edit User
                </h1>
                <p class="text-muted mb-0">
                    Editing: <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="user-view.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-info">
                    <i class="fas fa-eye me-2"></i> View Profile
                </a>
                <a href="users.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Users
                </a>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert-danger">
            <strong>Please fix the following errors:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <div class="form-card">
        <form method="POST">
            <!-- Basic Information -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Basic Information
                </h5>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" readonly disabled>
                        <div class="form-text">Username cannot be changed</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? $user['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Email Address *</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Account Settings -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-cog"></i>
                    Account Settings
                </h5>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">User Type</label>
                        <select name="user_type" class="form-select">
                            <option value="user" <?php echo ($_POST['user_type'] ?? $user['user_type']) == 'user' ? 'selected' : ''; ?>>Customer</option>
                            <option value="vendor" <?php echo ($_POST['user_type'] ?? $user['user_type']) == 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                            <option value="admin" <?php echo ($_POST['user_type'] ?? $user['user_type']) == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Account Status</label>
                        <select name="account_status" class="form-select">
                            <option value="active" <?php echo ($_POST['account_status'] ?? $user['account_status']) == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="suspended" <?php echo ($_POST['account_status'] ?? $user['account_status']) == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="deactivated" <?php echo ($_POST['account_status'] ?? $user['account_status']) == 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-map-marker-alt"></i>
                    Address Information
                </h5>
                
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['address'] ?? $user['address'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['city'] ?? $user['city'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Country</label>
                        <input type="text" name="country" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['country'] ?? $user['country'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['postal_code'] ?? $user['postal_code'] ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Change Password -->
            <div class="form-section">
                <h5 class="section-title">
                    <i class="fas fa-lock"></i>
                    Change Password
                </h5>
                <p class="text-muted small mb-3">Leave empty to keep current password</p>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Enter new password">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password">
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="d-flex gap-3 justify-content-end mt-4">
                <a href="user-view.php?id=<?php echo $user['id']; ?>" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>