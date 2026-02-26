<?php
// admin/vendors/add-vendor.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Add New Vendor';
require_once '../includes/header.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $tax_id = trim($_POST['tax_id'] ?? '');
    $vendor_status = $_POST['vendor_status'] ?? 'pending';
    $vendor_verified = isset($_POST['vendor_verified']) ? 1 : 0;
    
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
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check if username exists
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors[] = 'Username already exists';
            }
            
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already exists';
            }
            
            if (empty($errors)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("
                    INSERT INTO users (
                        username, email, password, full_name, phone, address, country, 
                        tax_id, user_type, vendor_status, vendor_verified, vendor_since, 
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, 'vendor', ?, ?, CURDATE(), NOW(), NOW()
                    )
                ");
                
                $stmt->execute([
                    $username, $email, $hashed_password, $full_name, $phone, $address, $country,
                    $tax_id, $vendor_status, $vendor_verified
                ]);
                
                $vendor_id = $db->lastInsertId();
                
                $_SESSION['success'] = "Vendor created successfully";
                header('Location: view-vendor.php?id=' . $vendor_id);
                exit();
            }
            
        } catch(PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get countries for dropdown
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
    --dark: #2b2d42;
    --light: #f8f9fa;
}

.add-vendor-container {
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

.btn-submit {
    background: var(--primary);
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-submit:hover {
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
</style>

<div class="add-vendor-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-user-plus me-2 text-primary"></i>
                    Add New Vendor
                </h1>
                <p class="text-muted mb-0">Create a new vendor account</p>
            </div>
            <a href="vendors.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Vendors
            </a>
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

    <!-- Add Vendor Form -->
    <div class="form-card">
        <form method="POST" action="">
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    <small class="text-muted">Unique username for login</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="">Select Country</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country['code']; ?>" 
                                <?php echo (($_POST['country'] ?? '') == $country['code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Tax ID / Business Registration</label>
                    <input type="text" name="tax_id" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['tax_id'] ?? ''); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Vendor Status</label>
                    <select name="vendor_status" class="form-select">
                        <option value="pending" <?php echo (($_POST['vendor_status'] ?? '') == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo (($_POST['vendor_status'] ?? '') == 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo (($_POST['vendor_status'] ?? '') == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Verification</label>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="vendor_verified" id="vendor_verified" 
                               <?php echo isset($_POST['vendor_verified']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="vendor_verified">
                            Mark as verified
                        </label>
                    </div>
                </div>
                
                <div class="col-12 mt-4">
                    <hr>
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="vendors.php" class="btn btn-cancel">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-save me-2"></i> Create Vendor
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>