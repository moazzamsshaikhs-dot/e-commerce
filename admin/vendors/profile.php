<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirectToDashboard();
}

$page_title = 'Vendor Profile';
require_once '../includes/header.php';

// Get vendor details
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as total_products,
               (SELECT COUNT(*) FROM products WHERE vendor_id = u.id AND approved_status = 'approved') as approved_products,
               (SELECT SUM(vendor_amount) FROM vendor_earnings WHERE vendor_id = u.id AND status = 'paid') as total_earnings
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        $_SESSION['error'] = 'Vendor not found.';
        redirect('dashboard.php');
    }
    
    // Get vendor documents
    $stmt = $db->prepare("SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$vendor_id]);
    $documents = $stmt->fetchAll();
    
    // Get vendor bank accounts
    $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE vendor_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->execute([$vendor_id]);
    $bank_accounts = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading profile: ' . $e->getMessage();
    $vendor = [];
    $documents = [];
    $bank_accounts = [];
}

$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Basic info update
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $vendor_category = sanitize($_POST['vendor_category']);
        $vendor_bio = sanitize($_POST['vendor_bio']);
        $address = sanitize($_POST['address']);
        $city = sanitize($_POST['city']);
        $country = sanitize($_POST['country']);
        $postal_code = sanitize($_POST['postal_code']);
        
        // Validation
        if (empty($full_name)) {
            $errors[] = 'Full name is required';
        }
        
        if (empty($vendor_category)) {
            $errors[] = 'Vendor category is required';
        }
        
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("
                    UPDATE users SET 
                        full_name = ?, 
                        phone = ?,
                        vendor_category = ?,
                        vendor_bio = ?,
                        address = ?,
                        city = ?,
                        country = ?,
                        postal_code = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $full_name,
                    $phone,
                    $vendor_category,
                    $vendor_bio,
                    $address,
                    $city,
                    $country,
                    $postal_code,
                    $vendor_id
                ]);
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                
                // Log activity
                logUserActivity($vendor_id, 'profile_update', 'Updated vendor profile');
                
                $_SESSION['success'] = 'Profile updated successfully!';
                
                // Refresh page
                redirect('profile.php');
                
            } catch(PDOException $e) {
                $errors[] = 'Error updating profile: ' . $e->getMessage();
            }
        }
    }
    
    // Handle social media update
    if (isset($_POST['update_social'])) {
        $social_facebook = sanitize($_POST['social_facebook']);
        $social_twitter = sanitize($_POST['social_twitter']);
        $social_instagram = sanitize($_POST['social_instagram']);
        $social_linkedin = sanitize($_POST['social_linkedin']);
        
        try {
            $stmt = $db->prepare("
                UPDATE users SET 
                    social_facebook = ?,
                    social_twitter = ?,
                    social_instagram = ?,
                    social_linkedin = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $social_facebook,
                $social_twitter,
                $social_instagram,
                $social_linkedin,
                $vendor_id
            ]);
            
            $_SESSION['success'] = 'Social media updated successfully!';
            
            // Log activity
            logUserActivity($vendor_id, 'social_update', 'Updated social media links');
            
            redirect('profile.php');
            
        } catch(PDOException $e) {
            $errors[] = 'Error updating social media: ' . $e->getMessage();
        }
    }
    
    // Handle profile picture upload
    if (isset($_POST['update_avatar']) && isset($_FILES['profile_pic'])) {
        if ($_FILES['profile_pic']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['profile_pic']['type'], $allowed_types)) {
                $errors[] = 'Only JPG, PNG, GIF and WebP images are allowed';
            } elseif ($_FILES['profile_pic']['size'] > $max_size) {
                $errors[] = 'Image size must be less than 2MB';
            } else {
                // Generate unique filename
                $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                $profile_pic = 'vendor_' . $vendor_id . '_' . time() . '.' . $file_ext;
                $upload_path = SITE_URL . 'assets/images/profiles/' . $profile_pic;
                
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if not default
                    if ($vendor['profile_pic'] && $vendor['profile_pic'] != 'default.png') {
                        $old_file = SITE_URL .  'assets/images/avatars/' . $vendor['profile_pic'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                    
                    // Update database
                    $stmt = $db->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $stmt->execute([$profile_pic, $vendor_id]);
                    
                    // Update session
                    $_SESSION['profile_pic'] = $profile_pic;
                    
                    $_SESSION['success'] = 'Profile picture updated successfully!';
                    
                    // Log activity
                    logUserActivity($vendor_id, 'avatar_update', 'Updated profile picture');
                    
                    redirect('profile.php');
                } else {
                    $errors[] = 'Failed to upload image';
                }
            }
        }
    }
}

// Handle document upload
if (isset($_POST['upload_document']) && isset($_FILES['document_file'])) {
    $document_type = sanitize($_POST['document_type']);
    $document_number = sanitize($_POST['document_number']);
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    if (empty($document_type)) {
        $errors[] = 'Document type is required';
    }
    
    if ($_FILES['document_file']['error'] != 0) {
        $errors[] = 'Please select a document file';
    }
    
    if (empty($errors)) {
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['document_file']['type'], $allowed_types)) {
            $errors[] = 'Only JPG, PNG, PDF, DOC and DOCX files are allowed';
        } elseif ($_FILES['document_file']['size'] > $max_size) {
            $errors[] = 'File size must be less than 5MB';
        } else {
            // Generate unique filename
            $file_ext = pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION);
            $document_file = 'doc_' . $vendor_id . '_' . time() . '.' . $file_ext;
            $upload_path = '../assets/uploads/documents/' . $document_file;
            
            // Create directory if not exists
            if (!is_dir('../assets/uploads/documents/')) {
                mkdir('../assets/uploads/documents/', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_path)) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO vendor_documents (vendor_id, document_type, document_number, document_file, expiry_date) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $vendor_id,
                        $document_type,
                        $document_number,
                        $document_file,
                        $expiry_date
                    ]);
                    
                    $_SESSION['success'] = 'Document uploaded successfully! It will be verified by admin.';
                    
                    // Log activity
                    logUserActivity($vendor_id, 'document_upload', 'Uploaded ' . $document_type . ' document');
                    
                    redirect('profile.php');
                    
                } catch(PDOException $e) {
                    $errors[] = 'Error uploading document: ' . $e->getMessage();
                }
            } else {
                $errors[] = 'Failed to upload document';
            }
        }
    }
}
?>

<div class="dashboard-container">
    <?php include '../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Vendor Profile</h1>
                    <p class="text-muted mb-0">Manage your vendor profile and settings</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="../vendor/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Error/Success Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Profile Overview -->
        <div class="row g-4">
            <!-- Profile Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <!-- Profile Picture -->
                        <div class="position-relative d-inline-block mb-4">
                            <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $vendor['profile_pic'] ?? 'default.png'; ?>" 
                                 alt="Profile" class="rounded-circle border border-4 border-white shadow" 
                                 width="150" height="150" style="object-fit: cover;"
                                 onerror="this.src='<?php echo SITE_URL; ?>assets/images/profiles/default.png'">
                            <button class="btn btn-primary btn-sm position-absolute bottom-0 end-0 rounded-circle" 
                                    data-bs-toggle="modal" data-bs-target="#avatarModal" style="width: 40px; height: 40px;">
                                <i class="fas fa-camera"></i>
                            </button>
                        </div>
                        
                        <h4 class="fw-bold mb-1"><?php echo $vendor['full_name']; ?></h4>
                        <p class="text-muted mb-3">
                            <i class="fas fa-store me-1 text-success"></i> 
                            <?php echo ucfirst($vendor['vendor_category'] ?? 'Not set'); ?> Vendor
                        </p>
                        
                        <!-- Vendor Status -->
                        <div class="mb-4">
                            <span class="badge bg-<?php 
                                echo $vendor['vendor_status'] == 'approved' ? 'success' : 
                                     ($vendor['vendor_status'] == 'pending' ? 'warning' : 'danger'); 
                            ?> px-3 py-2">
                                <i class="fas fa-<?php 
                                    echo $vendor['vendor_status'] == 'approved' ? 'check-circle' : 
                                         ($vendor['vendor_status'] == 'pending' ? 'clock' : 'times-circle'); 
                                ?> me-2"></i>
                                <?php echo ucfirst($vendor['vendor_status'] ?? 'pending'); ?>
                            </span>
                        </div>
                        
                        <!-- Stats -->
                        <div class="row text-center">
                            <div class="col-4">
                                <h5 class="fw-bold mb-1"><?php echo $vendor['total_products'] ?? 0; ?></h5>
                                <small class="text-muted">Products</small>
                            </div>
                            <div class="col-4">
                                <h5 class="fw-bold mb-1"><?php echo $vendor['approved_products'] ?? 0; ?></h5>
                                <small class="text-muted">Approved</small>
                            </div>
                            <div class="col-4">
                                <h5 class="fw-bold mb-1">$<?php echo number_format($vendor['total_earnings'] ?? 0, 2); ?></h5>
                                <small class="text-muted">Earnings</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contact Info -->
                    <div class="card-footer bg-white border-top">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-info-circle me-2 text-primary"></i> Contact Info
                        </h6>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-envelope me-2 text-muted"></i>
                                <small><?php echo $vendor['email']; ?></small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-phone me-2 text-muted"></i>
                                <small><?php echo $vendor['phone'] ?? 'Not set'; ?></small>
                            </li>
                            <li>
                                <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                <small><?php echo $vendor['city'] ?? 'Location not set'; ?></small>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Verification Status -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-user-check me-2 text-success"></i> Verification Status
                        </h6>
                        
                        <?php 
                        $verifications = [
                            'email' => ['icon' => 'envelope', 'status' => $vendor['email_verified'], 'label' => 'Email'],
                            'phone' => ['icon' => 'phone', 'status' => $vendor['phone_verified'], 'label' => 'Phone'],
                            'vendor' => ['icon' => 'store', 'status' => $vendor['vendor_verified'], 'label' => 'Vendor'],
                        ];
                        ?>
                        
                        <div class="mb-3">
                            <?php foreach($verifications as $key => $ver): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <i class="fas fa-<?php echo $ver['icon']; ?> me-2 text-muted"></i>
                                    <span><?php echo $ver['label']; ?></span>
                                </div>
                                <?php if ($ver['status']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning">
                                        <i class="fas fa-clock me-1"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <a href="verify.php" class="btn btn-outline-primary btn-sm w-100">
                            <i class="fas fa-user-check me-2"></i> Complete Verification
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Profile Forms -->
            <div class="col-lg-8">
                <!-- Basic Info Form -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-user-edit me-2 text-primary"></i> Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" 
                                           value="<?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Vendor Category *</label>
                                    <select class="form-select" name="vendor_category" required>
                                        <option value="">Select Category</option>
                                        <option value="Electronics" <?php echo ($vendor['vendor_category'] ?? '') == 'Electronics' ? 'selected' : ''; ?>>Electronics</option>
                                        <option value="Fashion" <?php echo ($vendor['vendor_category'] ?? '') == 'Fashion' ? 'selected' : ''; ?>>Fashion & Clothing</option>
                                        <option value="Home & Living" <?php echo ($vendor['vendor_category'] ?? '') == 'Home & Living' ? 'selected' : ''; ?>>Home & Living</option>
                                        <option value="Books" <?php echo ($vendor['vendor_category'] ?? '') == 'Books' ? 'selected' : ''; ?>>Books & Stationery</option>
                                        <option value="Sports" <?php echo ($vendor['vendor_category'] ?? '') == 'Sports' ? 'selected' : ''; ?>>Sports & Fitness</option>
                                        <option value="Beauty" <?php echo ($vendor['vendor_category'] ?? '') == 'Beauty' ? 'selected' : ''; ?>>Beauty & Cosmetics</option>
                                        <option value="Food" <?php echo ($vendor['vendor_category'] ?? '') == 'Food' ? 'selected' : ''; ?>>Food & Beverages</option>
                                        <option value="Other" <?php echo ($vendor['vendor_category'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Vendor Bio</label>
                                    <textarea class="form-control" name="vendor_bio" rows="3" 
                                              placeholder="Tell customers about your business..."><?php echo htmlspecialchars($vendor['vendor_bio'] ?? ''); ?></textarea>
                                    <div class="form-text">This will be displayed on your vendor page</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Address</label>
                                    <input type="text" class="form-control" name="address" 
                                           value="<?php echo htmlspecialchars($vendor['address'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">City</label>
                                    <input type="text" class="form-control" name="city" 
                                           value="<?php echo htmlspecialchars($vendor['city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Country</label>
                                    <input type="text" class="form-control" name="country" 
                                           value="<?php echo htmlspecialchars($vendor['country'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Postal Code</label>
                                    <input type="text" class="form-control" name="postal_code" 
                                           value="<?php echo htmlspecialchars($vendor['postal_code'] ?? ''); ?>">
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="submit" name="update_profile" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Social Media Form -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-share-alt me-2 text-primary"></i> Social Media Links
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fab fa-facebook me-2 text-primary"></i> Facebook
                                    </label>
                                    <input type="url" class="form-control" name="social_facebook" 
                                           placeholder="https://facebook.com/yourpage"
                                           value="<?php echo htmlspecialchars($vendor['social_facebook'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fab fa-twitter me-2 text-info"></i> Twitter
                                    </label>
                                    <input type="url" class="form-control" name="social_twitter" 
                                           placeholder="https://twitter.com/yourhandle"
                                           value="<?php echo htmlspecialchars($vendor['social_twitter'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fab fa-instagram me-2 text-danger"></i> Instagram
                                    </label>
                                    <input type="url" class="form-control" name="social_instagram" 
                                           placeholder="https://instagram.com/yourprofile"
                                           value="<?php echo htmlspecialchars($vendor['social_instagram'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fab fa-linkedin me-2 text-primary"></i> LinkedIn
                                    </label>
                                    <input type="url" class="form-control" name="social_linkedin" 
                                           placeholder="https://linkedin.com/company/yourcompany"
                                           value="<?php echo htmlspecialchars($vendor['social_linkedin'] ?? ''); ?>">
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="submit" name="update_social" class="btn btn-primary px-4">
                                        <i class="fas fa-save me-2"></i> Save Social Links
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Documents Section -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-file-alt me-2 text-primary"></i> Vendor Documents
                        </h5>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#documentModal">
                            <i class="fas fa-plus me-1"></i> Upload Document
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($documents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No documents uploaded yet</p>
                                <p class="text-muted small">Upload verification documents to complete your vendor profile</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Document Type</th>
                                            <th>Document Number</th>
                                            <th>Upload Date</th>
                                            <th>Status</th>
                                            <th>Expiry Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($documents as $doc): ?>
                                        <tr>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></td>
                                            <td><?php echo $doc['document_number'] ?? 'N/A'; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                            <td>
                                                <?php if ($doc['verified']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i> Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($doc['expiry_date']): ?>
                                                    <?php echo date('M d, Y', strtotime($doc['expiry_date'])); ?>
                                                    <?php if (strtotime($doc['expiry_date']) < time()): ?>
                                                        <span class="badge bg-danger ms-1">Expired</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="../assets/uploads/documents/<?php echo $doc['document_file']; ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Required documents for verification: ID Proof, Address Proof, Business Registration (if applicable)
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Avatar Update Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Update Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <img id="avatarPreview" src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $vendor['profile_pic'] ?? 'default.png'; ?>" 
                             alt="Preview" class="rounded-circle border" width="150" height="150" style="object-fit: cover;"
                             onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png'">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Choose new profile picture</label>
                        <input type="file" class="form-control" name="profile_pic" accept="image/*" required 
                               onchange="previewAvatar(this)">
                        <div class="form-text">JPG, PNG, GIF or WebP. Max 2MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_avatar" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i> Upload Picture
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Upload Modal -->
<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Document Type *</label>
                        <select class="form-select" name="document_type" required>
                            <option value="">Select Type</option>
                            <option value="id_proof">ID Proof</option>
                            <option value="address_proof">Address Proof</option>
                            <option value="business_registration">Business Registration</option>
                            <option value="tax_certificate">Tax Certificate</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Document Number</label>
                        <input type="text" class="form-control" name="document_number" 
                               placeholder="Document number (if applicable)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Document File *</label>
                        <input type="file" class="form-control" name="document_file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                        <div class="form-text">JPG, PNG, PDF, DOC or DOCX. Max 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_document" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i> Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.rounded-circle {
    border: 4px solid #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.card {
    border-radius: 10px;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
}

.badge {
    font-weight: 500;
    padding: 6px 12px;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
}
</style>

<script>
// Avatar preview
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-close alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Form validation
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
            submitBtn.disabled = true;
            
            setTimeout(() => {
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled = false;
            }, 3000);
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>