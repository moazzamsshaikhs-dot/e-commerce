<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    redirect('admin/profile.php');
}

$page_title = 'My Profile';
require_once '../includes/header.php';

// Get user data
try {
    $db = getDB();
    $user_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error'] = 'User not found';
        redirect('logout.php');
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading profile: ' . $e->getMessage();
    $user = [];
}

// Handle profile update
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    
    if ($action === 'update') {
        // Update basic info
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $gender = sanitize($_POST['gender']);
        $date_of_birth = sanitize($_POST['date_of_birth']);
        $country = sanitize($_POST['country']);
        $city = sanitize($_POST['city']);
        $postal_code = sanitize($_POST['postal_code']);
        $address = sanitize($_POST['address']);
        $bio = sanitize($_POST['bio']);
        
        // Social media
        $social_facebook = sanitize($_POST['social_facebook']);
        $social_twitter = sanitize($_POST['social_twitter']);
        $social_instagram = sanitize($_POST['social_instagram']);
        $social_linkedin = sanitize($_POST['social_linkedin']);
        
        try {
            $db = getDB();
            $stmt = $db->prepare("
                UPDATE users SET 
                full_name = ?, 
                phone = ?, 
                gender = ?, 
                date_of_birth = ?, 
                country = ?, 
                city = ?, 
                postal_code = ?, 
                address = ?, 
                bio = ?,
                social_facebook = ?,
                social_twitter = ?,
                social_instagram = ?,
                social_linkedin = ?,
                updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $full_name, $phone, $gender, $date_of_birth, $country, $city, 
                $postal_code, $address, $bio, $social_facebook, $social_twitter, 
                $social_instagram, $social_linkedin, $user_id
            ]);
            
            // Update session
            $_SESSION['full_name'] = $full_name;
            
            // Log activity
            logUserActivity($user_id, 'profile_update', 'Updated profile information');
            
            // Send security alert
            sendSecurityAlert($user_id, 'profile_update', 'Basic information updated');
            
            $success = 'Profile updated successfully!';
            
        } catch(PDOException $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate
        if (!verifyPassword($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        }
        
        $password_errors = validatePasswordStrength($new_password);
        if (!empty($password_errors)) {
            $errors = array_merge($errors, $password_errors);
        }
        
        if (empty($errors)) {
            try {
                $db = getDB();
                $hashed_password = hashPassword($new_password);
                
                $stmt = $db->prepare("
                    UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Log activity
                logUserActivity($user_id, 'password_change', 'Changed password');
                
                // Send security alert
                sendSecurityAlert($user_id, 'password_change');
                
                $success = 'Password changed successfully!';
                
            } catch(PDOException $e) {
                $errors[] = 'Password change failed: ' . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            
            // Validate file
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $errors[] = 'Only JPG, PNG, and GIF files are allowed';
            } elseif ($file['size'] > $max_size) {
                $errors[] = 'File size must be less than 2MB';
            } else {
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
                $upload_path = '../assets/images/avatars/' . $filename;
                
                // Create avatars directory if it doesn't exist
                if (!file_exists('../assets/images/avatars/')) {
                    mkdir('../assets/images/avatars/', 0777, true);
                }
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // Update database
                    try {
                        $db = getDB();
                        $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                        $stmt->execute([$filename, $user_id]);
                        
                        // Update session
                        $_SESSION['profile_pic'] = $filename;
                        
                        // Log activity
                        logUserActivity($user_id, 'avatar_change', 'Changed profile picture');
                        
                        $success = 'Profile picture updated successfully!';
                        
                    } catch(PDOException $e) {
                        $errors[] = 'Failed to update profile picture: ' . $e->getMessage();
                    }
                } else {
                    $errors[] = 'Failed to upload file';
                }
            }
        } else {
            $errors[] = 'Please select a valid image file';
        }
    }
}

// Log profile access
logUserActivity($_SESSION['user_id'], 'profile_access', 'Accessed profile page');
?>

<!-- Dashboard Layout -->
<div class="dashboard-container">
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Profile Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">My Profile</h1>
                    <p class="text-muted mb-0">Manage your personal information and settings</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <!-- Profile Content -->
        <div class="row g-4">
            <!-- Left Column: Avatar and Basic Info -->
            <div class="col-lg-4">
                <!-- Avatar Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center">
                        <div class="avatar-upload mb-3">
                            <div class="avatar-preview mb-3">
                                <img id="avatarPreview" 
                                     src="<?php echo SITE_URL; ?>assets/images/avatars/<?php echo $user['profile_pic'] ?? 'default.png'; ?>" 
                                     alt="Avatar" class="rounded-circle" width="150" height="150">
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="mb-3">
                                <input type="hidden" name="action" value="upload_avatar">
                                <div class="input-group">
                                    <input type="file" class="form-control" id="avatarInput" name="avatar" 
                                           accept="image/*" style="display: none;">
                                    <label for="avatarInput" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-camera me-2"></i> Change Photo
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-2">Max 2MB. JPG, PNG, GIF</small>
                                <button type="submit" class="btn btn-primary btn-sm mt-2 w-100">
                                    <i class="fas fa-upload me-2"></i> Upload
                                </button>
                            </form>
                        </div>
                        
                        <h5 class="mb-1"><?php echo $user['full_name']; ?></h5>
                        <p class="text-muted mb-2">@<?php echo $user['username']; ?></p>
                        
                        <div class="badge bg-<?php 
                            echo $user['subscription_plan'] == 'premium' ? 'warning' : 
                                 ($user['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                        ?> mb-3">
                            <i class="fas fa-crown me-1"></i> 
                            <?php echo ucfirst($user['subscription_plan']); ?> Plan
                        </div>
                        
                        <div class="d-flex justify-content-center gap-3 mt-3">
                            <?php if ($user['social_facebook']): ?>
                                <a href="<?php echo $user['social_facebook']; ?>" target="_blank" class="text-primary">
                                    <i class="fab fa-facebook fa-lg"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['social_twitter']): ?>
                                <a href="<?php echo $user['social_twitter']; ?>" target="_blank" class="text-info">
                                    <i class="fab fa-twitter fa-lg"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['social_instagram']): ?>
                                <a href="<?php echo $user['social_instagram']; ?>" target="_blank" class="text-danger">
                                    <i class="fab fa-instagram fa-lg"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($user['social_linkedin']): ?>
                                <a href="<?php echo $user['social_linkedin']; ?>" target="_blank" class="text-primary">
                                    <i class="fab fa-linkedin fa-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Account Info Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Account Information</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <strong>Email:</strong><br>
                                <span class="text-muted"><?php echo $user['email']; ?></span>
                                <?php if ($user['email_verified']): ?>
                                    <span class="badge bg-success ms-2">Verified</span>
                                <?php else: ?>
                                    <span class="badge bg-warning ms-2">Not Verified</span>
                                <?php endif; ?>
                            </li>
                            <li class="mb-2">
                                <strong>Phone:</strong><br>
                                <span class="text-muted"><?php echo $user['phone'] ?? 'Not set'; ?></span>
                                <?php if ($user['phone_verified']): ?>
                                    <span class="badge bg-success ms-2">Verified</span>
                                <?php endif; ?>
                            </li>
                            <li class="mb-2">
                                <strong>Member Since:</strong><br>
                                <span class="text-muted"><?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                            </li>
                            <li class="mb-2">
                                <strong>Last Login:</strong><br>
                                <span class="text-muted"><?php echo $user['last_login'] ? date('d M Y h:i A', strtotime($user['last_login'])) : 'Never'; ?></span>
                            </li>
                            <li>
                                <strong>Status:</strong><br>
                                <span class="badge bg-<?php echo $user['account_status'] == 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($user['account_status']); ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Forms -->
            <div class="col-lg-8">
                <!-- Profile Form -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Personal Information</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Select Gender</option>
                                        <option value="male" <?php echo ($user['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($user['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($user['gender'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="country" class="form-label">Country</label>
                                    <input type="text" class="form-control" id="country" name="country" 
                                           value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City</label>
                                    <input type="text" class="form-control" id="city" name="city" 
                                           value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="postal_code" class="form-label">Postal Code</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                           value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-12">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="col-md-12">
                                    <label for="bio" class="form-label">Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Social Media Form -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Social Media Links</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="social_facebook" class="form-label">
                                        <i class="fab fa-facebook text-primary me-2"></i> Facebook
                                    </label>
                                    <input type="url" class="form-control" id="social_facebook" name="social_facebook" 
                                           placeholder="https://facebook.com/username" 
                                           value="<?php echo htmlspecialchars($user['social_facebook'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="social_twitter" class="form-label">
                                        <i class="fab fa-twitter text-info me-2"></i> Twitter
                                    </label>
                                    <input type="url" class="form-control" id="social_twitter" name="social_twitter" 
                                           placeholder="https://twitter.com/username" 
                                           value="<?php echo htmlspecialchars($user['social_twitter'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="social_instagram" class="form-label">
                                        <i class="fab fa-instagram text-danger me-2"></i> Instagram
                                    </label>
                                    <input type="url" class="form-control" id="social_instagram" name="social_instagram" 
                                           placeholder="https://instagram.com/username" 
                                           value="<?php echo htmlspecialchars($user['social_instagram'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="social_linkedin" class="form-label">
                                        <i class="fab fa-linkedin text-primary me-2"></i> LinkedIn
                                    </label>
                                    <input type="url" class="form-control" id="social_linkedin" name="social_linkedin" 
                                           placeholder="https://linkedin.com/in/username" 
                                           value="<?php echo htmlspecialchars($user['social_linkedin'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Social Links
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Change Password</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="current_password" class="form-label">Current Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="current_password" 
                                               name="current_password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="new_password" class="form-label">New Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="new_password" 
                                               name="new_password" required minlength="8">
                                        <button type="button" class="btn btn-outline-secondary toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        Minimum 8 characters with uppercase, lowercase, number, and special character
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" 
                                               name="confirm_password" required>
                                        <button type="button" class="btn btn-outline-secondary toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Profile JS -->
<script>
$(document).ready(function() {
    // Avatar preview
    $('#avatarInput').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#avatarPreview').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Toggle password visibility
    $('.toggle-password').click(function() {
        const input = $(this).siblings('input');
        const icon = $(this).find('i');
        
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Password strength checker
    $('#new_password').on('input', function() {
        const password = $(this).val();
        const strength = checkPasswordStrength(password);
        updatePasswordStrength(strength);
    });
    
    function checkPasswordStrength(password) {
        let score = 0;
        
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        
        return score;
    }
    
    function updatePasswordStrength(score) {
        const strengthText = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'][score];
        const strengthClass = ['danger', 'danger', 'warning', 'info', 'success', 'success'][score];
        
        // You can add a strength meter here if needed
        console.log('Password strength:', strengthText);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>