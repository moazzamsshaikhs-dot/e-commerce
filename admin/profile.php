<?php
require_once './includes/config.php';
require_once './includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('/dasboard.php');
}

$page_title = 'Admin Profile';
require_once './includes/header.php';

// Get admin data
try {
    $db = getDB();
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        $_SESSION['error'] = 'Admin not found';
        redirect('logout.php');
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading profile: ' . $e->getMessage();
    $admin = [];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch($action) {
        case 'update_profile':
            updateAdminProfile($admin_id);
            break;
            
        case 'change_password':
            changeAdminPassword($admin_id);
            break;
            
        case 'upload_avatar':
            uploadAdminAvatar($admin_id);
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action';
            redirect('admin/profile.php');
    }
}

/**
 * Update admin profile
 */
function updateAdminProfile($admin_id) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('admin/profile.php');
    }
    
    // Get form data
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $bio = sanitize($_POST['bio'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $postal_code = sanitize($_POST['postal_code'] ?? '');
    $gender = sanitize($_POST['gender'] ?? 'other');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    
    // Social media
    $social_facebook = sanitize($_POST['social_facebook'] ?? '');
    $social_twitter = sanitize($_POST['social_twitter'] ?? '');
    $social_instagram = sanitize($_POST['social_instagram'] ?? '');
    $social_linkedin = sanitize($_POST['social_linkedin'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,20}$/', $phone)) {
        $errors[] = 'Invalid phone number';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check if email already exists for another user
            $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->execute([$email, $admin_id]);
            
            if ($check_stmt->fetch()) {
                $_SESSION['error'] = 'Email already exists for another user';
                redirect('admin/profile.php');
            }
            
            // Update admin profile
            $stmt = $db->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, address = ?, bio = ?, 
                    country = ?, city = ?, postal_code = ?, gender = ?, date_of_birth = ?,
                    social_facebook = ?, social_twitter = ?, social_instagram = ?, social_linkedin = ?,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            $stmt->execute([
                $full_name, $email, $phone, $address, $bio,
                $country, $city, $postal_code, $gender, $date_of_birth,
                $social_facebook, $social_twitter, $social_instagram, $social_linkedin,
                $admin_id
            ]);
            
            // Update session data
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
            
            // Log activity
            if (function_exists('logUserActivity')) {
                logUserActivity($admin_id, 'profile_update', 'Updated admin profile');
            }
            
            // Send security alert
            if (function_exists('sendSecurityAlert')) {
                sendSecurityAlert($admin_id, 'profile_update', "Profile updated on " . date('Y-m-d H:i:s'));
            }
            
            $_SESSION['success'] = 'Profile updated successfully!';
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Failed to update profile: ' . $e->getMessage();
        }
    } else {
        $_SESSION['form_errors'] = $errors;
    }
    
    redirect('admin/profile.php');
}

/**
 * Change admin password
 */
function changeAdminPassword($admin_id) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('admin/profile.php');
    }
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    if (empty($current_password)) {
        $errors[] = 'Current password is required';
    }
    
    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters';
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Get current password hash
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$admin_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $_SESSION['error'] = 'User not found';
                redirect('admin/profile.php');
            }
            
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $_SESSION['error'] = 'Current password is incorrect';
                redirect('admin/profile.php');
            }
            
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$new_password_hash, $admin_id]);
            
            // Log activity
            if (function_exists('logUserActivity')) {
                logUserActivity($admin_id, 'password_change', 'Changed admin password');
            }
            
            // Send security alert
            if (function_exists('sendSecurityAlert')) {
                sendSecurityAlert($admin_id, 'password_change');
            }
            
            $_SESSION['success'] = 'Password changed successfully!';
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Failed to change password: ' . $e->getMessage();
        }
    } else {
        $_SESSION['form_errors'] = $errors;
    }
    
    redirect('admin/profile.php');
}

/**
 * Upload admin avatar
 */
function uploadAdminAvatar($admin_id) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('/git-clone/e-commerce/admin/profile.php');
    }
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            $_SESSION['error'] = 'Invalid file type. Allowed: jpg, jpeg, png, gif';
            redirect('admin/profile.php');
        }
        
        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            $_SESSION['error'] = 'File too large. Maximum 2MB allowed';
            redirect('admin/profile.php');
        }
        
        // Generate unique filename
        $filename = 'admin_' . $admin_id . '_' . time() . '.' . $file_extension;
        $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/git-clone/e-commerce/assets/images/profiles/' . $filename;
        
        // Create directory if it doesn't exist
        $directory = dirname($upload_path);
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            try {
                $db = getDB();
                
                // Get old avatar to delete
                $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ?");
                $stmt->execute([$admin_id]);
                $old_avatar = $stmt->fetchColumn();
                
                // Delete old avatar if not default
                if ($old_avatar && $old_avatar !== 'default.png') {
                    $old_path = $_SERVER['DOCUMENT_ROOT'] . '/git-clone/e-commerce/assets/images/profiles/' . $old_avatar;
                    if (file_exists($old_path)) {
                        @unlink($old_path);
                    }
                }
                
                // Update database
                $stmt = $db->prepare("UPDATE users SET profile_pic = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$filename, $admin_id]);
                
                // Log activity
                if (function_exists('logUserActivity')) {
                    logUserActivity($admin_id, 'avatar_update', 'Uploaded new admin avatar');
                }
                
                $_SESSION['success'] = 'Profile picture updated successfully!';
                
            } catch(PDOException $e) {
                // Delete uploaded file if database update fails
                @unlink($upload_path);
                $_SESSION['error'] = 'Failed to update profile picture: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = 'Failed to upload file';
        }
    } else {
        $_SESSION['error'] = 'No file uploaded or upload error';
    }
    
    redirect('/git-clone/e-commerce/admin/profile.php');
}

// Log admin profile access
if (function_exists('logUserActivity')) {
    logUserActivity($_SESSION['user_id'], 'admin_profile_access', 'Accessed admin profile page');
}
?>

<div class="dashboard-container">
    <?php 
    include './includes/sidebar.php';
     ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Admin Profile</h1>
                <p class="text-muted mb-0">Manage your admin account and settings</p>
            </div>
            <div class="d-flex gap-2">
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
                <a href="../logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['form_errors'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Please fix the following errors:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach($_SESSION['form_errors'] as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['form_errors']); ?>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Column: Profile Info -->
            <div class="col-lg-4 mb-4">
                <!-- Profile Card -->
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center p-4">
                        <!-- Avatar -->
                        <div class="position-relative mx-auto mb-4" style="width: 150px;">
                            <?php 
                            $avatar_url = !empty($admin['profile_pic']) 
                                ? '../assets/images/profiles/' . htmlspecialchars($admin['profile_pic'])
                                : '../assets/images/profiles/default.png';
                            ?>
                            <img src="<?php echo $avatar_url; ?>" 
                                 class="rounded-circle border border-4 border-white shadow-sm" 
                                 width="200px" height="200px"
                                 alt="<?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?>"
                                 style="object-fit: cover;">
                            
                            <!-- Upload Avatar Button -->
                            <button type="button" 
                                    class="btn btn-primary btn-sm rounded-square position-absolute bottom-0 end-0"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#avatarModal"
                                    title="Change Profile Picture"
                                    >
                                <i class="fas fa-camera" height="50" wdth="50"></i>
                            </button>
                        </div>
                        
                        <!-- Admin Info -->
                        <h4 class="mb-1"><?php echo htmlspecialchars($admin['full_name'] ?? 'Admin'); ?></h4>
                        <p class="text-muted mb-3">
                            <i class="fas fa-user-shield me-1"></i> Administrator
                        </p>
                        
                        <!-- Admin Badge -->
                        <div class="mb-4">
                            <span class="badge bg-danger">
                                <i class="fas fa-crown me-1"></i> System Administrator
                            </span>
                        </div>
                        
                        <!-- Stats -->
                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <h5 class="mb-0 text-primary">
                                        <?php 
                                        try {
                                            $db = getDB();
                                            $stmt = $db->query("SELECT COUNT(*) FROM users");
                                            echo $stmt->fetchColumn();
                                        } catch(Exception $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </h5>
                                    <small class="text-muted">Total Users</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="border rounded p-3">
                                    <h5 class="mb-0 text-success">
                                        <?php 
                                        try {
                                            $db = getDB();
                                            $stmt = $db->query("SELECT COUNT(*) FROM products");
                                            echo $stmt->fetchColumn();
                                        } catch(Exception $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </h5>
                                    <small class="text-muted">Total Products</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Info -->
                        <div class="text-start mb-4">
                            <div class="mb-3">
                                <i class="fas fa-envelope text-primary me-2"></i>
                                <span><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></span>
                            </div>
                            <?php if (!empty($admin['phone'])): ?>
                            <div class="mb-3">
                                <i class="fas fa-phone text-primary me-2"></i>
                                <span><?php echo htmlspecialchars($admin['phone']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($admin['country'])): ?>
                            <div class="mb-3">
                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                <span><?php echo htmlspecialchars($admin['city'] ?? ''); ?>, <?php echo htmlspecialchars($admin['country']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Member Since -->
                        <div class="border-top pt-3">
                            <small class="text-muted">
                                <i class="far fa-calendar me-1"></i>
                                Member since <?php echo date('M d, Y', strtotime($admin['created_at'] ?? 'now')); ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Tabs -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                    <i class="fas fa-user-edit me-2"></i> Edit Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                    <i class="fas fa-shield-alt me-2"></i> Security
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                                    <i class="fas fa-history me-2"></i> Recent Activity
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content" id="profileTabsContent">
                            <!-- Profile Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                <form method="POST" action="<?php echo SITE_URL; ?>admin/profile.php" id="profileForm">
                                    <input type="hidden" name="action" value="update_profile">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    
                                    <div class="row g-3">
                                        <!-- Personal Info -->
                                        <div class="col-md-6">
                                            <label for="full_name" class="form-label">Full Name *</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="full_name" 
                                                   name="full_name" 
                                                   value="<?php echo htmlspecialchars($admin['full_name'] ?? ''); ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email *</label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="email" 
                                                   name="email" 
                                                   value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="phone" 
                                                   name="phone" 
                                                   value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>"
                                                   placeholder="+1234567890">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="gender" class="form-label">Gender</label>
                                            <select class="form-select" id="gender" name="gender">
                                                <option value="male" <?php echo ($admin['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                                <option value="female" <?php echo ($admin['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                                <option value="other" <?php echo empty($admin['gender']) || ($admin['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                                            <input type="date" 
                                                   class="form-control" 
                                                   id="date_of_birth" 
                                                   name="date_of_birth" 
                                                   value="<?php echo !empty($admin['date_of_birth']) ? htmlspecialchars($admin['date_of_birth']) : ''; ?>">
                                        </div>
                                        
                                        <!-- Location -->
                                        <div class="col-md-6">
                                            <label for="country" class="form-label">Country</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="country" 
                                                   name="country" 
                                                   value="<?php echo htmlspecialchars($admin['country'] ?? ''); ?>"
                                                   placeholder="Your country">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="city" 
                                                   name="city" 
                                                   value="<?php echo htmlspecialchars($admin['city'] ?? ''); ?>"
                                                   placeholder="Your city">
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="postal_code" class="form-label">Postal Code</label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="postal_code" 
                                                   name="postal_code" 
                                                   value="<?php echo htmlspecialchars($admin['postal_code'] ?? ''); ?>"
                                                   placeholder="12345">
                                        </div>
                                        
                                        <!-- Address -->
                                        <div class="col-12">
                                            <label for="address" class="form-label">Address</label>
                                            <textarea class="form-control" 
                                                      id="address" 
                                                      name="address" 
                                                      rows="2"><?php echo htmlspecialchars($admin['address'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <!-- Bio -->
                                        <div class="col-12">
                                            <label for="bio" class="form-label">Bio</label>
                                            <textarea class="form-control" 
                                                      id="bio" 
                                                      name="bio" 
                                                      rows="3"
                                                      placeholder="Tell us about yourself..."><?php echo htmlspecialchars($admin['bio'] ?? ''); ?></textarea>
                                        </div>
                                        
                                        <!-- Social Media -->
                                        <div class="col-12">
                                            <h6 class="border-bottom pb-2 mb-3">Social Media</h6>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="social_facebook" class="form-label">
                                                        <i class="fab fa-facebook text-primary me-1"></i> Facebook
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_facebook" 
                                                           name="social_facebook" 
                                                           value="<?php echo htmlspecialchars($admin['social_facebook'] ?? ''); ?>"
                                                           placeholder="https://facebook.com/username">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_twitter" class="form-label">
                                                        <i class="fab fa-twitter text-info me-1"></i> Twitter
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_twitter" 
                                                           name="social_twitter" 
                                                           value="<?php echo htmlspecialchars($admin['social_twitter'] ?? ''); ?>"
                                                           placeholder="https://twitter.com/username">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_instagram" class="form-label">
                                                        <i class="fab fa-instagram text-danger me-1"></i> Instagram
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_instagram" 
                                                           name="social_instagram" 
                                                           value="<?php echo htmlspecialchars($admin['social_instagram'] ?? ''); ?>"
                                                           placeholder="https://instagram.com/username">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="social_linkedin" class="form-label">
                                                        <i class="fab fa-linkedin text-primary me-1"></i> LinkedIn
                                                    </label>
                                                    <input type="url" 
                                                           class="form-control" 
                                                           id="social_linkedin" 
                                                           name="social_linkedin" 
                                                           value="<?php echo htmlspecialchars($admin['social_linkedin'] ?? ''); ?>"
                                                           placeholder="https://linkedin.com/in/username">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Form Actions -->
                                        <div class="col-12 mt-4">
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save me-2"></i> Save Changes
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel">
                                <form method="POST" action="" id="passwordForm">
                                    <input type="hidden" name="action" value="change_password">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>
                                                For security, please enter your current password to change it.
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="current_password" class="form-label">Current Password *</label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="current_password" 
                                                   name="current_password" 
                                                   required>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="new_password" class="form-label">New Password *</label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="new_password" 
                                                   name="new_password" 
                                                   required
                                                   minlength="6">
                                            <div class="form-text">Minimum 6 characters</div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                            <input type="password" 
                                                   class="form-control" 
                                                   id="confirm_password" 
                                                   name="confirm_password" 
                                                   required
                                                   minlength="6">
                                        </div>
                                        
                                        <!-- Password Strength Meter -->
                                        <div class="col-12">
                                            <div class="progress mb-2" style="height: 5px;">
                                                <div class="progress-bar" id="passwordStrength" role="progressbar" style="width: 0%"></div>
                                            </div>
                                            <small class="text-muted" id="passwordStrengthText">Password strength</small>
                                        </div>
                                        
                                        <!-- Security Tips -->
                                        <div class="col-12">
                                            <div class="alert alert-warning">
                                                <h6 class="alert-heading">
                                                    <i class="fas fa-shield-alt me-2"></i> Security Tips
                                                </h6>
                                                <ul class="mb-0">
                                                    <li>Use a strong, unique password</li>
                                                    <li>Don't reuse passwords from other sites</li>
                                                    <li>Consider using a password manager</li>
                                                    <li>Change your password regularly</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <!-- Form Actions -->
                                        <div class="col-12 mt-4">
                                            <div class="d-flex justify-content-end">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-key me-2"></i> Change Password
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Activity Tab -->
                            <div class="tab-pane fade" id="activity" role="tabpanel">
                                <?php
                                try {
                                    $db = getDB();
                                    $stmt = $db->prepare("
                                        SELECT * FROM user_activities 
                                        WHERE user_id = ? 
                                        ORDER BY created_at DESC 
                                        LIMIT 10
                                    ");
                                    $stmt->execute([$admin_id]);
                                    $activities = $stmt->fetchAll();
                                    
                                    if (!empty($activities)): 
                                ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach($activities as $activity): 
                                        $icon = 'fa-info-circle';
                                        $color = 'text-info';
                                        
                                        switch($activity['activity_type']) {
                                            case 'login':
                                                $icon = 'fa-sign-in-alt';
                                                $color = 'text-success';
                                                break;
                                            case 'logout':
                                                $icon = 'fa-sign-out-alt';
                                                $color = 'text-warning';
                                                break;
                                            case 'password_change':
                                                $icon = 'fa-key';
                                                $color = 'text-danger';
                                                break;
                                            case 'profile_update':
                                                $icon = 'fa-user-edit';
                                                $color = 'text-primary';
                                                break;
                                        }
                                    ?>
                                    <div class="list-group-item border-0 px-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas <?php echo $icon; ?> fa-lg <?php echo $color; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <div class="d-flex justify-content-between">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['activity_type']))); ?></h6>
                                                    <small class="text-muted"><?php echo formatDate($activity['created_at'], 'h:i A'); ?></small>
                                                </div>
                                                <p class="mb-1 small"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-globe me-1"></i> <?php echo htmlspecialchars($activity['ip_address'] ?? ''); ?>
                                                    • <?php echo formatDate($activity['created_at'], 'M d, Y'); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <a href="admin-logs.php" class="btn btn-outline-primary">
                                        <i class="fas fa-history me-2"></i> View All Activity Logs
                                    </a>
                                </div>
                                
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="text-muted mb-4">
                                        <i class="fas fa-history fa-4x opacity-25"></i>
                                    </div>
                                    <h5>No Activity Found</h5>
                                    <p class="text-muted">Your recent activities will appear here</p>
                                </div>
                                <?php endif; ?>
                                
                                <?php } catch(Exception $e) { ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Unable to load activity logs: <?php echo $e->getMessage(); ?>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="/git-clone/e-commerce/admin/profile.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_avatar">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="avatarModalLabel">Change Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div id="avatarPreview" class="mx-auto mb-3" style="width: 200px; height: 200px;">
                            <?php 
                            $avatar_url = !empty($admin['profile_pic']) 
                                ? '../assets/images/profiles/' . htmlspecialchars($admin['profile_pic'])
                                : '../assets/images/profiles/default.png';
                            ?>
                            <img src="<?php echo $avatar_url; ?>" 
                                 class="img-fluid rounded-circle border"
                                 id="previewAvatar"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="avatar" class="form-label">Choose new profile picture</label>
                        <input type="file" 
                               class="form-control" 
                               id="avatar" 
                               name="avatar"
                               accept="image/*"
                               required>
                        <div class="form-text">
                            Allowed: JPG, JPEG, PNG, GIF • Max size: 2MB
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.dashboard-container {
    display: flex;
    min-height: calc(100vh - 70px);
}

.sidebar {
    width: 250px;
    background: #1a1a2e;
    transition: all 0.3s;
    position: fixed;
    height: calc(100vh - 70px);
    overflow-y: auto;
    z-index: 1000;
}

.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 20px;
    background: #f8f9fa;
    /* color: #f8f9fa; */
    min-height: calc(100vh - 70px);
}

.sidebar-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1001;
    display: none;
}

@media (max-width: 992px) {
    .sidebar {
        margin-left: -250px;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .sidebar.active {
        margin-left: 0;
    }
    
    .sidebar-toggle {
        display: block;
    }
}
.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link:hover {
    color: #4361ee;
}

.nav-tabs .nav-link.active {
    color: #4361ee;
    border-bottom: 3px solid #4361ee;
    background: transparent;
}

.card {
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.card-header {
    background: white;
    border-bottom: 1px solid #e9ecef;
}

.progress {
    background-color: #e9ecef;
}

.progress-bar {
    transition: width 0.3s ease;
}

.list-group-item {
    border-left: 0;
    border-right: 0;
}

.list-group-item:first-child {
    border-top: 0;
}

.list-group-item:last-child {
    border-bottom: 0;
}

.badge {
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 20px;
}

/* Avatar upload button */
.position-absolute {
    transform: translate(50%, 50%);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password strength checker
    const passwordInput = document.getElementById('new_password');
    const confirmInput = document.getElementById('confirm_password');
    const strengthBar = document.getElementById('passwordStrength');
    const strengthText = document.getElementById('passwordStrengthText');
    
    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let text = 'Very Weak';
            let color = '#dc3545';
            
            // Length check
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            
            // Complexity checks
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            // Update UI
            switch(strength) {
                case 0:
                case 1:
                    text = 'Very Weak';
                    color = '#dc3545';
                    break;
                case 2:
                    text = 'Weak';
                    color = '#fd7e14';
                    break;
                case 3:
                    text = 'Medium';
                    color = '#ffc107';
                    break;
                case 4:
                    text = 'Strong';
                    color = '#28a745';
                    break;
                case 5:
                    text = 'Very Strong';
                    color = '#198754';
                    break;
            }
            
            const percentage = (strength / 5) * 100;
            strengthBar.style.width = percentage + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
            
            // Check password match
            if (confirmInput.value !== '') {
                checkPasswordMatch();
            }
        });
        
        // Password match checker
        if (confirmInput) {
            confirmInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordMatch() {
            if (passwordInput.value !== confirmInput.value) {
                confirmInput.style.borderColor = '#dc3545';
                confirmInput.classList.add('is-invalid');
            } else {
                confirmInput.style.borderColor = '#28a745';
                confirmInput.classList.remove('is-invalid');
            }
        }
    }
    
    // Avatar preview
    const avatarInput = document.getElementById('avatar');
    const previewAvatar = document.getElementById('previewAvatar');
    
    if (avatarInput) {
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewAvatar.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Form validation
    const profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
        });
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});
</script>

<?php require_once './includes/footer.php'; ?>