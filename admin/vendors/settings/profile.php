<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Profile Settings';
require_once '../../includes/header.php';

// Get vendor details
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("
        SELECT 
            u.*,
            vs.store_name,
            DATE_FORMAT(u.created_at, '%d %b %Y') as joined_date
        FROM users u
        LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id
        WHERE u.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        $_SESSION['error'] = 'Vendor not found.';
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $vendor = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $gender = $_POST['gender'] ?? 'other';
            $date_of_birth = $_POST['date_of_birth'] ?? null;
            $country = trim($_POST['country'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            
            // Validate phone number
            if (!empty($phone) && !preg_match('/^[0-9+\-\s]{10,20}$/', $phone)) {
                throw new Exception('Invalid phone number format.');
            }
            
            // Update user profile
            $sql = "UPDATE users SET 
                    full_name = ?, phone = ?, bio = ?, gender = ?,
                    date_of_birth = ?, country = ?, city = ?, postal_code = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $full_name, $phone, $bio, $gender,
                $date_of_birth ?: null, $country, $city, $postal_code,
                $vendor_id
            ]);
            
            $_SESSION['success'] = 'Profile updated successfully!';
            redirect('profile.php');
            
        } elseif ($action === 'update_social') {
            $social_facebook = trim($_POST['social_facebook'] ?? '');
            $social_twitter = trim($_POST['social_twitter'] ?? '');
            $social_instagram = trim($_POST['social_instagram'] ?? '');
            $social_linkedin = trim($_POST['social_linkedin'] ?? '');
            
            // Validate URLs
            $urls = [
                'facebook' => $social_facebook,
                'twitter' => $social_twitter,
                'instagram' => $social_instagram,
                'linkedin' => $social_linkedin
            ];
            
            foreach ($urls as $platform => $url) {
                if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid $platform URL format.");
                }
            }
            
            $sql = "UPDATE users SET 
                    social_facebook = ?, social_twitter = ?,
                    social_instagram = ?, social_linkedin = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $social_facebook, $social_twitter,
                $social_instagram, $social_linkedin,
                $vendor_id
            ]);
            
            $_SESSION['success'] = 'Social links updated!';
            redirect('profile.php');
            
        } elseif ($action === 'upload_avatar') {
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $profile_pic = uploadProfileImage($_FILES['profile_pic']);
                
                // Delete old profile picture if not default
                if ($vendor['profile_pic'] !== 'default.png') {
                    $old_file = dirname(SITE_URL) . '/uploads/profiles/' . $vendor['profile_pic'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                $stmt = $db->prepare("UPDATE users SET profile_pic = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$profile_pic, $vendor_id]);
                
                $_SESSION['profile_pic'] = $profile_pic;
                $_SESSION['success'] = 'Profile picture updated!';
                redirect('profile.php');
            }
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Helper function for profile image upload
function uploadProfileImage($file) {
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Check for errors
    if ($file_error !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file_error);
    }
    
    // Check file size
    if ($file_size > $max_size) {
        throw new Exception('File size too large. Maximum size is 2MB.');
    }
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Check extension
    if (!in_array($file_ext, $allowed_ext)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WebP');
    }
    
    // Generate unique filename
    $new_filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
    $upload_path = SITE_URL . 'uploads/profiles/' . $new_filename;
    
    // Create directory if not exists
    $upload_dir = dirname($upload_path);
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $upload_path)) {
        throw new Exception('Failed to upload file.');
    }
    
    // Resize image
    resizeImage($upload_path, 200, 200);
    
    return $new_filename;
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Profile Settings</h1>
                <p class="text-muted mb-0">Manage your personal information and profile</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Profile Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="profileTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" 
                                data-bs-target="#personal" type="button">
                            <i class="fas fa-user me-2"></i> Personal Info
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="avatar-tab" data-bs-toggle="tab" 
                                data-bs-target="#avatar" type="button">
                            <i class="fas fa-camera me-2"></i> Profile Picture
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="social-tab" data-bs-toggle="tab" 
                                data-bs-target="#social" type="button">
                            <i class="fas fa-share-alt me-2"></i> Social Profiles
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Profile Content -->
        <div class="tab-content" id="profileTabContent">
            <!-- Personal Info Tab -->
            <div class="tab-pane fade show active" id="personal" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-user me-2"></i> Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="profileForm">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row g-4">
                                <!-- Basic Info -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Full Name *</label>
                                        <input type="text" name="full_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Bio -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Bio / About</label>
                                        <textarea name="bio" class="form-control" rows="4"
                                                  placeholder="Tell us about yourself..."><?php echo htmlspecialchars($vendor['bio'] ?? ''); ?></textarea>
                                        <small class="text-muted">This will be displayed on your vendor profile</small>
                                    </div>
                                </div>
                                
                                <!-- Demographics -->
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Gender</label>
                                        <select name="gender" class="form-select">
                                            <option value="male" <?php echo ($vendor['gender'] ?? 'other') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($vendor['gender'] ?? 'other') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($vendor['gender'] ?? 'other') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Date of Birth</label>
                                        <input type="date" name="date_of_birth" class="form-control" 
                                               value="<?php echo $vendor['date_of_birth'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Country</label>
                                        <select name="country" class="form-select">
                                            <option value="">Select Country</option>
                                            <?php
                                            $countries = [
                                                'USA', 'India', 'UK', 'Canada', 'Australia',
                                                'Germany', 'France', 'Japan', 'China', 'Brazil'
                                            ];
                                            foreach($countries as $country): ?>
                                            <option value="<?php echo $country; ?>"
                                                <?php echo ($vendor['country'] ?? '') === $country ? 'selected' : ''; ?>>
                                                <?php echo $country; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Address -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">City</label>
                                        <input type="text" name="city" class="form-control" 
                                               value="<?php echo htmlspecialchars($vendor['city'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Postal Code</label>
                                        <input type="text" name="postal_code" class="form-control" 
                                               value="<?php echo htmlspecialchars($vendor['postal_code'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <!-- Account Info -->
                                <div class="col-12 mt-4">
                                    <h6 class="fw-bold mb-3">Account Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="border rounded p-3">
                                                <small class="text-muted d-block">Username</small>
                                                <strong class="d-block"><?php echo htmlspecialchars($vendor['username']); ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3">
                                                <small class="text-muted d-block">Email</small>
                                                <strong class="d-block"><?php echo htmlspecialchars($vendor['email']); ?></strong>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3">
                                                <small class="text-muted d-block">Joined Date</small>
                                                <strong class="d-block"><?php echo $vendor['joined_date']; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Profile
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Profile Picture Tab -->
            <div class="tab-pane fade" id="avatar" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-camera me-2"></i> Profile Picture
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <!-- Current Avatar -->
                                <div class="text-center mb-4">
                                    <div class="avatar-preview mb-3">
                                        <img src="<?php echo SITE_URL; ?>uploads/profiles/<?php echo htmlspecialchars($vendor['profile_pic']); ?>" 
                                             alt="Profile Picture" class="rounded-circle" 
                                             style="width: 200px; height: 200px; object-fit: cover;">
                                    </div>
                                    <p class="text-muted mb-0">Current Profile Picture</p>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <!-- Upload Form -->
                                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                                    <input type="hidden" name="action" value="upload_avatar">
                                    
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Upload New Picture</label>
                                        <input type="file" name="profile_pic" class="form-control" 
                                               accept="image/*" id="avatarInput" required>
                                        <small class="text-muted">
                                            Recommended: 200x200px, JPG or PNG, max 2MB
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="progress d-none" id="avatarProgress">
                                            <div class="progress-bar" role="progressbar"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload me-2"></i> Upload Picture
                                        </button>
                                        
                                        <?php if ($vendor['profile_pic'] !== 'default.png'): ?>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteAvatar()">
                                            <i class="fas fa-trash me-2"></i> Remove Picture
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                                
                                <!-- Avatar Guidelines -->
                                <div class="alert alert-info mt-4">
                                    <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> Guidelines</h6>
                                    <ul class="mb-0">
                                        <li>Use a clear, high-quality headshot</li>
                                        <li>Face should be clearly visible</li>
                                        <li>Avoid group photos or logos</li>
                                        <li>Square images work best</li>
                                        <li>Maximum file size: 2MB</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Social Profiles Tab -->
            <div class="tab-pane fade" id="social" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-share-alt me-2"></i> Social Profiles
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="socialForm">
                            <input type="hidden" name="action" value="update_social">
                            
                            <div class="row g-4">
                                <!-- Facebook -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fab fa-facebook text-primary me-2"></i> Facebook Profile
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">facebook.com/</span>
                                            <input type="text" name="social_facebook" class="form-control" 
                                                   placeholder="username"
                                                   value="<?php echo htmlspecialchars($vendor['social_facebook'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Twitter -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fab fa-twitter text-info me-2"></i> Twitter Profile
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">twitter.com/</span>
                                            <input type="text" name="social_twitter" class="form-control" 
                                                   placeholder="username"
                                                   value="<?php echo htmlspecialchars($vendor['social_twitter'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Instagram -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fab fa-instagram text-danger me-2"></i> Instagram
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">instagram.com/</span>
                                            <input type="text" name="social_instagram" class="form-control" 
                                                   placeholder="username"
                                                   value="<?php echo htmlspecialchars($vendor['social_instagram'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- LinkedIn -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="fab fa-linkedin text-primary me-2"></i> LinkedIn Profile
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">linkedin.com/in/</span>
                                            <input type="text" name="social_linkedin" class="form-control" 
                                                   placeholder="username"
                                                   value="<?php echo htmlspecialchars($vendor['social_linkedin'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Social Preview -->
                                <div class="col-12 mt-4">
                                    <h6 class="fw-bold mb-3">Preview</h6>
                                    <div class="border rounded p-4 bg-light">
                                        <div class="d-flex flex-wrap gap-3" id="socialPreview">
                                            <?php
                                            $social_links = [
                                                'facebook' => $vendor['social_facebook'] ?? '',
                                                'twitter' => $vendor['social_twitter'] ?? '',
                                                'instagram' => $vendor['social_instagram'] ?? '',
                                                'linkedin' => $vendor['social_linkedin'] ?? ''
                                            ];
                                            
                                            $has_links = false;
                                            foreach($social_links as $platform => $link):
                                                if (!empty($link)):
                                                    $has_links = true;
                                                    $icons = [
                                                        'facebook' => 'fab fa-facebook text-primary',
                                                        'twitter' => 'fab fa-twitter text-info',
                                                        'instagram' => 'fab fa-instagram text-danger',
                                                        'linkedin' => 'fab fa-linkedin text-primary'
                                                    ];
                                                    $labels = [
                                                        'facebook' => 'Facebook',
                                                        'twitter' => 'Twitter',
                                                        'instagram' => 'Instagram',
                                                        'linkedin' => 'LinkedIn'
                                                    ];
                                            ?>
                                            <a href="https://<?php echo $link; ?>" target="_blank" class="social-preview-item">
                                                <i class="<?php echo $icons[$platform]; ?>"></i>
                                                <?php echo $labels[$platform]; ?>
                                            </a>
                                            <?php
                                                endif;
                                            endforeach;
                                            
                                            if (!$has_links):
                                            ?>
                                            <p class="text-muted">No social profiles added yet.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Social Links
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Profile JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const triggerTabList = [].slice.call(document.querySelectorAll('#profileTab button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Form submissions
    const forms = ['profileForm', 'avatarForm', 'socialForm'];
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this);
            });
        }
    });
    
    // Avatar preview
    const avatarInput = document.getElementById('avatarInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            previewImage(this, '.avatar-preview');
        });
    }
});

function submitForm(form) {
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

function previewImage(input, previewSelector) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector(previewSelector);
            if (preview) {
                preview.innerHTML = `<img src="${e.target.result}" class="rounded-circle" style="width: 200px; height: 200px; object-fit: cover;">`;
            }
        }
        reader.readAsDataURL(file);
    }
}

function deleteAvatar() {
    if (confirm('Are you sure you want to remove your profile picture?')) {
        fetch('action/delete-avatar.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}
</script>

<style>
.avatar-preview img {
    border: 3px solid #dee2e6;
}

.social-preview-item {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 50px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s ease;
}

.social-preview-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.social-preview-item i {
    font-size: 1.2rem;
    margin-right: 8px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>