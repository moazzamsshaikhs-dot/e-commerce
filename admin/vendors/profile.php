<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Vendor Profile';
require_once '../includes/header.php';

// Get vendor details
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor with category details
    $stmt = $db->prepare("
        SELECT u.*, 
               vc.name as category_name,
               vc.slug as category_slug,
               vc.commission_rate,
               (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as total_products,
               (SELECT COUNT(*) FROM products WHERE vendor_id = u.id AND approved_status = 'approved') as approved_products,
               (SELECT COALESCE(SUM(vendor_amount), 0) FROM vendor_earnings WHERE vendor_id = u.id AND status = 'paid') as total_earnings
        FROM users u
        LEFT JOIN vendor_categories vc ON u.vendor_category COLLATE utf8mb4_unicode_ci = vc.slug COLLATE utf8mb4_unicode_ci
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
    
    // Get vendor categories
    $stmt = $db->query("SELECT * FROM vendor_categories WHERE is_active = 1 ORDER BY name");
    $vendor_categories = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading profile: ' . $e->getMessage();
    error_log("Profile error: " . $e->getMessage());
    $vendor = [];
    $documents = [];
    $vendor_categories = [];
}

$errors = [];

// Handle form submissions based on action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    error_log("POST action: " . $action);
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    switch($action) {
        case 'update_profile':
            updateVendorProfile($vendor_id);
            break;
            
        case 'update_social':
            updateVendorSocial($vendor_id);
            break;
            
        case 'update_avatar':
            uploadVendorAvatar($vendor_id);
            break;
            
        case 'upload_document':
            uploadVendorDocument($vendor_id);
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action';
            redirect('profile.php');
    }
}

/**
 * Update vendor profile
 */
function updateVendorProfile($vendor_id) {
    global $db;
    
    // Get form data
    $full_name = sanitize($_POST['full_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $vendor_category = sanitize($_POST['vendor_category'] ?? '');
    $vendor_bio = sanitize($_POST['vendor_bio'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $country = sanitize($_POST['country'] ?? '');
    $postal_code = sanitize($_POST['postal_code'] ?? '');
    
    error_log("Updating vendor profile: $full_name, $phone, $vendor_category");
    
    // Validation
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($vendor_category)) {
        $errors[] = 'Vendor category is required';
    }
    
    if (empty($errors)) {
        try {
            // Check if vendor_category exists
            $stmt = $db->prepare("SELECT id FROM vendor_categories WHERE slug = ? AND is_active = 1");
            $stmt->execute([$vendor_category]);
            $category_exists = $stmt->fetch();
            
            if (!$category_exists) {
                $_SESSION['error'] = 'Invalid vendor category selected';
                redirect('profile.php');
            }
            
            // Perform update
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
            
            $result = $stmt->execute([
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
            
            error_log("Update result: " . ($result ? 'success' : 'failed'));
            error_log("Rows affected: " . $stmt->rowCount());
            
            if ($result) {
                // Update session
                $_SESSION['full_name'] = $full_name;
                
                // Log activity
                if (function_exists('logUserActivity')) {
                    logUserActivity($vendor_id, 'profile_update', 'Updated vendor profile');
                }
                
                $_SESSION['success'] = 'Profile updated successfully!';
            } else {
                $_SESSION['error'] = 'Failed to update profile';
            }
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Error updating profile: ' . $e->getMessage();
            error_log("Profile update error: " . $e->getMessage());
        }
    } else {
        $_SESSION['form_errors'] = $errors;
    }
    
    redirect('profile.php');
}

/**
 * Update vendor social media
 */
function updateVendorSocial($vendor_id) {
    global $db;
    
    $social_facebook = sanitize($_POST['social_facebook'] ?? '');
    $social_twitter = sanitize($_POST['social_twitter'] ?? '');
    $social_instagram = sanitize($_POST['social_instagram'] ?? '');
    $social_linkedin = sanitize($_POST['social_linkedin'] ?? '');
    
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
        
        $result = $stmt->execute([
            $social_facebook,
            $social_twitter,
            $social_instagram,
            $social_linkedin,
            $vendor_id
        ]);
        
        if ($result) {
            if (function_exists('logUserActivity')) {
                logUserActivity($vendor_id, 'social_update', 'Updated social media links');
            }
            $_SESSION['success'] = 'Social media updated successfully!';
        } else {
            $_SESSION['error'] = 'Failed to update social media';
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error updating social media: ' . $e->getMessage();
        error_log("Social update error: " . $e->getMessage());
    }
    
    redirect('profile.php');
}

/**
 * Upload vendor avatar - FIXED VERSION WITH PROPER DEBUGGING
 */
function uploadVendorAvatar($vendor_id) {
    global $db, $vendor;
    
    // Define debug log path properly
    $debug_log = $_SERVER['DOCUMENT_ROOT'] . '/ecommerce/upload_debug.log';
    
    // Create debug directory if it doesn't exist
    $debug_dir = dirname($debug_log);
    if (!file_exists($debug_dir)) {
        mkdir($debug_dir, 0777, true);
    }
    
    // Helper function for debug logging
    function debug_log($message, $log_file) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message\n";
        
        // Try to write to file
        if (@file_put_contents($log_file, $log_message, FILE_APPEND) === false) {
            // If file write fails, log to PHP error log instead
            error_log("DEBUG: " . $message);
        }
    }
    
    debug_log("========== AVATAR UPLOAD STARTED ==========", $debug_log);
    debug_log("Vendor ID: " . $vendor_id, $debug_log);
    debug_log("POST data: " . print_r($_POST, true), $debug_log);
    debug_log("FILES data: " . print_r($_FILES, true), $debug_log);
    
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_pic'];
        
        debug_log("File name: " . $file['name'], $debug_log);
        debug_log("File type: " . $file['type'], $debug_log);
        debug_log("File size: " . $file['size'], $debug_log);
        debug_log("File tmp_name: " . $file['tmp_name'], $debug_log);
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $file['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            debug_log("ERROR: Invalid file type: " . $file_type, $debug_log);
            $_SESSION['error'] = 'Only JPG, PNG, GIF and WebP images are allowed';
            redirect('profile.php');
        }
        
        // Validate file size
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $max_size) {
            debug_log("ERROR: File too large: " . $file['size'], $debug_log);
            $_SESSION['error'] = 'Image size must be less than 2MB';
            redirect('profile.php');
        }
        
        // Define upload directory - FIXED PATH FOR XAMPP
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/profiles/';
        debug_log("Upload directory: " . $upload_dir, $debug_log);
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            debug_log("Directory doesn't exist, creating...", $debug_log);
            if (!mkdir($upload_dir, 0777, true)) {
                debug_log("ERROR: Failed to create directory", $debug_log);
                $_SESSION['error'] = 'Failed to create upload directory';
                redirect('profile.php');
            }
            debug_log("Directory created successfully", $debug_log);
        }
        
        // Check if directory is writable
        if (!is_writable($upload_dir)) {
            debug_log("ERROR: Directory not writable", $debug_log);
            debug_log("Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4), $debug_log);
            
            // Try to fix permissions
            chmod($upload_dir, 0777);
            
            if (!is_writable($upload_dir)) {
                $_SESSION['error'] = 'Upload directory is not writable. Please check permissions.';
                redirect('profile.php');
            }
        }
        debug_log("Directory is writable", $debug_log);
        
        // Generate unique filename
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $profile_pic = 'vendor_' . $vendor_id . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $profile_pic;
        
        debug_log("Generated filename: " . $profile_pic, $debug_log);
        debug_log("Full upload path: " . $upload_path, $debug_log);
        
        // Check if we can write to the directory
        $test_file = $upload_dir . 'test.txt';
        if (@file_put_contents($test_file, 'test')) {
            debug_log("Can write to directory - test file created", $debug_log);
            unlink($test_file);
        } else {
            debug_log("ERROR: Cannot write to directory - test file failed", $debug_log);
        }
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            debug_log("SUCCESS: File moved to: " . $upload_path, $debug_log);
            
            // Verify file exists
            if (file_exists($upload_path)) {
                debug_log("File exists at destination", $debug_log);
                debug_log("File size at destination: " . filesize($upload_path), $debug_log);
            } else {
                debug_log("ERROR: File does not exist at destination after move!", $debug_log);
            }
            
            // Delete old profile picture if not default
            if (!empty($vendor['profile_pic']) && $vendor['profile_pic'] != 'default.png') {
                $old_file = $upload_dir . $vendor['profile_pic'];
                if (file_exists($old_file)) {
                    unlink($old_file);
                    debug_log("Deleted old file: " . $old_file, $debug_log);
                }
            }
            
            // Update database
            debug_log("Updating database with filename: " . $profile_pic, $debug_log);
            $stmt = $db->prepare("UPDATE users SET profile_pic = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$profile_pic, $vendor_id]);
            
            debug_log("Database update result: " . ($result ? 'SUCCESS' : 'FAILED'), $debug_log);
            debug_log("Rows affected: " . $stmt->rowCount(), $debug_log);
            
            if ($result) {
                // Verify database was updated
                $stmt = $db->prepare("SELECT profile_pic FROM users WHERE id = ?");
                $stmt->execute([$vendor_id]);
                $db_profile_pic = $stmt->fetchColumn();
                debug_log("Database now has: " . $db_profile_pic, $debug_log);
                
                // Update session
                $_SESSION['profile_pic'] = $profile_pic;
                
                if (function_exists('logUserActivity')) {
                    logUserActivity($vendor_id, 'avatar_update', 'Updated profile picture');
                }
                
                $_SESSION['success'] = 'Profile picture updated successfully!';
                debug_log("SUCCESS: All steps completed", $debug_log);
            } else {
                debug_log("ERROR: Database update failed", $debug_log);
                $_SESSION['error'] = 'Failed to update database';
            }
        } else {
            $error = error_get_last();
            debug_log("ERROR: move_uploaded_file failed", $debug_log);
            debug_log("PHP Error: " . print_r($error, true), $debug_log);
            $_SESSION['error'] = 'Failed to upload image. Error: ' . ($error['message'] ?? 'Unknown error');
        }
    } else {
        $error_code = $_FILES['profile_pic']['error'] ?? 'No file uploaded';
        debug_log("ERROR: File upload error. Code: " . $error_code, $debug_log);
        
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
        ];
        $error_message = $error_messages[$error_code] ?? 'Unknown upload error';
        $_SESSION['error'] = 'Error uploading file: ' . $error_message;
    }
    
    debug_log("========== AVATAR UPLOAD ENDED ==========\n\n", $debug_log);
    redirect('profile.php');
}

/**
 * Upload vendor document - FIXED VERSION
 */
function uploadVendorDocument($vendor_id) {
    global $db;
    
    $document_type = sanitize($_POST['document_type'] ?? '');
    $document_number = sanitize($_POST['document_number'] ?? '');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    
    if (empty($document_type)) {
        $_SESSION['error'] = 'Document type is required';
        redirect('profile.php');
    }
    
    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Please select a document file';
        redirect('profile.php');
    }
    
    $file = $_FILES['document_file'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    if (!in_array($file['type'], $allowed_types)) {
        $_SESSION['error'] = 'Only JPG, PNG, PDF, DOC and DOCX files are allowed';
        redirect('profile.php');
    }
    
    // Validate file size
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $max_size) {
        $_SESSION['error'] = 'File size must be less than 5MB';
        redirect('profile.php');
    }
    
    // Define upload directory - FIXED PATH FOR XAMPP
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/documents/';
    
    // Create directory if not exists
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            $_SESSION['error'] = 'Failed to create document directory';
            error_log("Failed to create document directory: " . $upload_dir);
            redirect('profile.php');
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        // Try to fix permissions
        chmod($upload_dir, 0777);
        if (!is_writable($upload_dir)) {
            $_SESSION['error'] = 'Document directory is not writable';
            error_log("Document directory not writable: " . $upload_dir);
            redirect('profile.php');
        }
    }
    
    // Generate unique filename
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $document_file = 'doc_' . $vendor_id . '_' . time() . '.' . $file_ext;
    $upload_path = $upload_dir . $document_file;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO vendor_documents (vendor_id, document_type, document_number, document_file, expiry_date, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $vendor_id,
                $document_type,
                $document_number,
                $document_file,
                $expiry_date
            ]);
            
            if ($result) {
                if (function_exists('logUserActivity')) {
                    logUserActivity($vendor_id, 'document_upload', 'Uploaded ' . $document_type . ' document');
                }
                $_SESSION['success'] = 'Document uploaded successfully! It will be verified by admin.';
            } else {
                $_SESSION['error'] = 'Failed to save document information';
            }
            
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Error uploading document: ' . $e->getMessage();
            error_log("Document upload error: " . $e->getMessage());
        }
    } else {
        $error = error_get_last();
        $_SESSION['error'] = 'Failed to upload document. Error: ' . ($error['message'] ?? 'Unknown error');
        error_log("Document upload failed. Path: " . $upload_path . " Error: " . print_r($error, true));
    }
    
    redirect('profile.php');
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
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Display session messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                echo $_SESSION['success'];
                unset($_SESSION['success']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php 
                echo $_SESSION['error'];
                unset($_SESSION['error']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['form_errors'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach($_SESSION['form_errors'] as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['form_errors']); ?>
        <?php endif; ?>
        
        <!-- Profile Overview -->
        <div class="row g-4">
            <!-- Profile Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <!-- Profile Picture -->
                        <div class="position-relative d-inline-block mb-4">
                            <?php 
                            $profile_pic = !empty($vendor['profile_pic']) ? $vendor['profile_pic'] : 'default.png';
                            $profile_pic_url = SITE_URL . 'assets/images/profiles/' . $profile_pic;
                            $default_url = SITE_URL . 'assets/images/avatars/default.png';
                            ?>
                            <img src="<?php echo $profile_pic_url; ?>" 
                                 alt="Profile" class="rounded-circle border border-4 border-white shadow" 
                                 width="170" height="170" style="object-fit: cover;"
                                 onerror="this.onerror=null; this.src='<?php echo $default_url; ?>';">
                            <button class="btn btn-primary btn-sm position-absolute bottom-0 end-0 rounded" 
                                    data-bs-toggle="modal" data-bs-target="#avatarModal" style="width: 40px; height: 40px;">
                                <i class="fas fa-camera position-absolute" style="font-size: 18px; top:50%; left:50%; transform: translate(-50%, -50%);"></i>
                            </button>
                        </div>
                        
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?></h4>
                        <p class="text-muted mb-3">
                            <i class="fas fa-store me-1 text-success"></i> 
                            <?php 
                            $category_display = !empty($vendor['category_name']) ? $vendor['category_name'] : 
                                               (!empty($vendor['vendor_category']) ? $vendor['vendor_category'] : 'Not set');
                            echo htmlspecialchars($category_display); 
                            ?> Vendor
                        </p>
                        
                        <!-- Vendor Status -->
                        <div class="mb-4">
                            <?php 
                            $status_color = 'secondary';
                            $status_icon = 'circle';
                            if ($vendor['vendor_status'] == 'approved') {
                                $status_color = 'success';
                                $status_icon = 'check-circle';
                            } elseif ($vendor['vendor_status'] == 'pending') {
                                $status_color = 'warning';
                                $status_icon = 'clock';
                            } elseif ($vendor['vendor_status'] == 'rejected') {
                                $status_color = 'danger';
                                $status_icon = 'times-circle';
                            }
                            ?>
                            <span class="badge bg-<?php echo $status_color; ?> px-3 py-2">
                                <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
                                <?php echo ucfirst($vendor['vendor_status'] ?? 'pending'); ?>
                            </span>
                        </div>
                        
                        <!-- Stats -->
                        <div class="row text-center">
                            <div class="col-4">
                                <h5 class="fw-bold mb-1"><?php echo number_format($vendor['total_products'] ?? 0); ?></h5>
                                <small class="text-muted">Products</small>
                            </div>
                            <div class="col-4">
                                <h5 class="fw-bold mb-1"><?php echo number_format($vendor['approved_products'] ?? 0); ?></h5>
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
                                <small><?php echo htmlspecialchars($vendor['email'] ?? ''); ?></small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-phone me-2 text-muted"></i>
                                <small><?php echo htmlspecialchars($vendor['phone'] ?? 'Not set'); ?></small>
                            </li>
                            <li>
                                <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                <small><?php echo htmlspecialchars($vendor['city'] ?? 'Location not set'); ?></small>
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
                            'email' => ['icon' => 'envelope', 'status' => $vendor['email_verified'] ?? 0, 'label' => 'Email'],
                            'phone' => ['icon' => 'phone', 'status' => $vendor['phone_verified'] ?? 0, 'label' => 'Phone'],
                            'vendor' => ['icon' => 'store', 'status' => $vendor['vendor_verified'] ?? 0, 'label' => 'Vendor'],
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
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
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
                                        <?php if (!empty($vendor_categories)): ?>
                                            <?php foreach($vendor_categories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat['slug']); ?>"
                                                    <?php echo ($vendor['vendor_category'] ?? '') == $cat['slug'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                    <?php if (!empty($cat['commission_rate'])): ?>
                                                        (Commission: <?php echo $cat['commission_rate']; ?>%)
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                    <button type="submit" class="btn btn-primary px-4">
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
                            <input type="hidden" name="action" value="update_social">
                            
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
                                    <button type="submit" class="btn btn-primary px-4">
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
                                            <td><?php echo htmlspecialchars($doc['document_number'] ?? 'N/A'); ?></td>
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
                                                <?php if (!empty($doc['expiry_date'])): ?>
                                                    <?php echo date('M d, Y', strtotime($doc['expiry_date'])); ?>
                                                    <?php if (strtotime($doc['expiry_date']) < time()): ?>
                                                        <span class="badge bg-danger ms-1">Expired</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>uploads/documents/<?php echo $doc['document_file']; ?>" 
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
                <input type="hidden" name="action" value="update_avatar">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <?php 
                        $preview_pic = !empty($vendor['profile_pic']) ? $vendor['profile_pic'] : 'default.png';
                        $preview_url = SITE_URL . 'assets/images/profiles/' . $preview_pic;
                        $default_url = SITE_URL . 'assets/images/avatars/default.png';
                        ?>
                        <img id="avatarPreview" src="<?php echo $preview_url; ?>" 
                             alt="Preview" class="rounded-circle border" width="150" height="150" style="object-fit: cover;"
                             onerror="this.onerror=null; this.src='<?php echo $default_url; ?>';">
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
                    <button type="submit" class="btn btn-primary">
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
                <input type="hidden" name="action" value="upload_document">
                
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
                    <button type="submit" class="btn btn-primary">
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

.main-content {
    padding: 20px;
    background: #f8f9fa;
    min-height: 100vh;
}

.position-absolute {
    transform: translate(50%, 50%);
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
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    });
}, 5000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Form validation with loading state
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
            submitBtn.disabled = true;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>