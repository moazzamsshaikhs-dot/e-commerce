<?php
// admin/vendors/edit-vendor.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Edit Vendor';
require_once '../includes/header.php';

$db = getDB();
$vendor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$vendor_id) {
    $_SESSION['error'] = 'Invalid vendor ID';
    header('Location: vendors.php');
    exit();
}

// Get vendor details
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND user_type = 'vendor'");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$vendor) {
        $_SESSION['error'] = 'Vendor not found';
        header('Location: vendors.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading vendor: ' . $e->getMessage();
    header('Location: vendors.php');
    exit();
}

$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $vendor_status = $_POST['vendor_status'] ?? 'pending';
    $vendor_verified = isset($_POST['vendor_verified']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    // Check if username exists (excluding current vendor)
    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $vendor_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
        
        // Check if email exists (excluding current vendor)
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $vendor_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already exists';
        }
    } catch(PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE users SET 
                        username = ?, email = ?, password = ?, full_name = ?, 
                        phone = ?, address = ?, country = ?, tax_id = ?,
                        vendor_status = ?, vendor_verified = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username, $email, $hashed_password, $full_name,
                    $phone, $address, $country, $tax_id,
                    $vendor_status, $vendor_verified, $vendor_id
                ]);
            } else {
                // Update without password
                $stmt = $db->prepare("
                    UPDATE users SET 
                        username = ?, email = ?, full_name = ?, 
                        phone = ?, address = ?, country = ?, tax_id = ?,
                        vendor_status = ?, vendor_verified = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $username, $email, $full_name,
                    $phone, $address, $country, $tax_id,
                    $vendor_status, $vendor_verified, $vendor_id
                ]);
            }
            
            // Log activity
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $log = $db->prepare("
                INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
                VALUES (?, 'vendor_updated', ?, ?, ?, NOW())
            ");
            $log->execute([$_SESSION['user_id'], "Updated vendor #$vendor_id ($email)", $ip, $ua]);
            
            $db->commit();
            
            $_SESSION['success'] = "Vendor updated successfully";
            header('Location: view-vendor.php?id=' . $vendor_id);
            exit();
            
        } catch(PDOException $e) {
            $db->rollBack();
            $errors[] = 'Error updating vendor: ' . $e->getMessage();
        }
    }
}

// Get countries for dropdown
$countries = [];
try {
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
    --dark: #2b2d42;
    --light: #f8f9fa;
}

.edit-vendor-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

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

.form-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid #edf2f9;
    padding: 12px 15px;
    transition: all 0.3s ease;
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

.btn-save {
    background: var(--primary);
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-save:hover {
    background: #3651c4;
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
}

.btn-cancel {
    background: #6c757d;
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

/* Password field styling */
.password-field {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #6c757d;
}

.password-toggle:hover {
    color: var(--primary);
}

/* Status badges */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
}

.status-approved { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.status-pending { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.status-suspended { background: rgba(239, 71, 111, 0.1); color: var(--danger); }

/* Responsive */
@media (max-width: 768px) {
    .edit-vendor-container {
        padding: 20px;
    }
    
    .form-card {
        padding: 20px;
    }
}
</style>

<div class="edit-vendor-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-edit me-2 text-primary"></i>
                    Edit Vendor
                </h1>
                <p class="text-muted mb-0">
                    Editing: <strong><?php echo htmlspecialchars($vendor['full_name'] ?? $vendor['username']); ?></strong>
                    <span class="status-badge status-<?php echo $vendor['vendor_status']; ?> ms-2">
                        <?php echo ucfirst($vendor['vendor_status']); ?>
                    </span>
                    <?php if ($vendor['vendor_verified']): ?>
                        <span class="status-badge status-approved ms-1">
                            <i class="fas fa-check-circle me-1"></i> Verified
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div>
                <a href="view-vendor.php?id=<?php echo $vendor_id; ?>" class="btn btn-outline-info me-2">
                    <i class="fas fa-eye me-2"></i> View
                </a>
                <a href="vendors.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Vendors
                </a>
            </div>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-15 mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                <div>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Edit Form -->
    <div class="form-card">
        <form method="POST" action="">
            <div class="row g-4">
                <!-- Basic Information -->
                <div class="col-12">
                    <h5 class="mb-3 pb-2 border-bottom">Basic Information</h5>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? $vendor['username']); ?>" required>
                    <small class="text-muted">Unique username for login</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? $vendor['email']); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? $vendor['full_name']); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? $vendor['phone']); ?>">
                </div>

                <!-- Password Change -->
                <div class="col-12">
                    <h5 class="mb-3 pb-2 border-bottom">Change Password (Optional)</h5>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">New Password</label>
                    <div class="password-field">
                        <input type="password" name="new_password" class="form-control" id="new_password">
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password')"></i>
                    </div>
                    <small class="text-muted">Leave blank to keep current password</small>
                </div>

                <!-- Address Information -->
                <div class="col-12">
                    <h5 class="mb-3 pb-2 border-bottom">Address Information</h5>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? $vendor['address']); ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country['code']; ?>" 
                                <?php echo (($_POST['country'] ?? $vendor['country']) == $country['code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Tax ID / Business Registration</label>
                    <input type="text" name="tax_id" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['tax_id'] ?? $vendor['tax_id']); ?>">
                </div>

                <!-- Vendor Status -->
                <div class="col-12">
                    <h5 class="mb-3 pb-2 border-bottom">Vendor Status</h5>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Account Status</label>
                    <select name="vendor_status" class="form-select">
                        <option value="pending" <?php echo (($_POST['vendor_status'] ?? $vendor['vendor_status']) == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo (($_POST['vendor_status'] ?? $vendor['vendor_status']) == 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="suspended" <?php echo (($_POST['vendor_status'] ?? $vendor['vendor_status']) == 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        <option value="rejected" <?php echo (($_POST['vendor_status'] ?? $vendor['vendor_status']) == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Verification</label>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="vendor_verified" id="vendor_verified" 
                               <?php echo (isset($_POST['vendor_verified']) ? $_POST['vendor_verified'] : $vendor['vendor_verified']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="vendor_verified">
                            Mark as verified
                        </label>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Member Since</label>
                    <input type="text" class="form-control" value="<?php echo date('d M Y', strtotime($vendor['created_at'])); ?>" readonly disabled>
                </div>

                <!-- Submit Buttons -->
                <div class="col-12 mt-4">
                    <hr>
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="vendors.php" class="btn btn-cancel">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-save">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = event.currentTarget;
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

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