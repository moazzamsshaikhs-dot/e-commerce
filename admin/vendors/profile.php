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
    
    // Get vendor complete details
    $stmt = $db->prepare("
        SELECT u.*, 
               c.id as current_category_id,
               c.name as current_category_name,
               c.slug as current_category_slug,
               c.commission_rate as current_commission,
               p.name as parent_category_name,
               (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id) as total_documents,
               (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id AND verified = 1) as verified_documents
        FROM users u
        LEFT JOIN categories c ON u.vendor_category = c.id
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE u.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    if (!$vendor) {
        $_SESSION['error'] = 'Vendor not found.';
        redirect('dashboard.php');
    }
    
    // Check for pending category request
    $stmt = $db->prepare("
        SELECT ccr.*, c.name as requested_category_name
        FROM category_change_requests ccr
        JOIN categories c ON ccr.category_id = c.id
        WHERE ccr.vendor_id = ? AND ccr.status = 'pending'
        ORDER BY ccr.created_at DESC LIMIT 1
    ");
    $stmt->execute([$vendor_id]);
    $pending_request = $stmt->fetch();
    
    // Get all categories for dropdown
    $stmt = $db->query("
        SELECT c.*, p.name as parent_name
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE c.is_active = 1
        ORDER BY 
            CASE WHEN c.parent_id IS NULL THEN 0 ELSE 1 END,
            c.name
    ");
    $all_categories = $stmt->fetchAll();
    
    // Get vendor documents with full details
    $stmt = $db->prepare("
        SELECT * FROM vendor_documents 
        WHERE vendor_id = ? 
        ORDER BY verified DESC, created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $documents = $stmt->fetchAll();
    
    // Get recent unread notifications
    $stmt = $db->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$vendor_id]);
    $unread_notifications = $stmt->fetchAll();
    
} catch(PDOException $e) {
    error_log("Profile Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading profile: ' . $e->getMessage();
    $vendor = [];
    $all_categories = [];
    $documents = [];
    $pending_request = null;
    $unread_notifications = [];
}

// Calculate verification progress
$verification_score = 0;
$total_verification_items = 5; // email, phone, id proof, address proof, business registration

if ($vendor['email_verified'] ?? 0) $verification_score++;
if ($vendor['phone_verified'] ?? 0) $verification_score++;

// Check document types
$has_id_proof = false;
$has_address_proof = false;
$has_business_reg = false;

foreach ($documents as $doc) {
    if ($doc['verified']) {
        if ($doc['document_type'] == 'id_proof') $has_id_proof = true;
        if ($doc['document_type'] == 'address_proof') $has_address_proof = true;
        if ($doc['document_type'] == 'business_registration') $has_business_reg = true;
    }
}

if ($has_id_proof) $verification_score++;
if ($has_address_proof) $verification_score++;
if ($has_business_reg) $verification_score++;

$verification_percentage = ($verification_score / $total_verification_items) * 100;
$is_fully_verified = ($verification_score >= 5);
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

.dashboard-container {
    display: flex;
    min-height: 100vh;
}

.main-content {
    flex: 1;
    padding: 30px;
    background: #f4f7fc;
}

.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

/* Profile Card Styles */
.profile-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    margin-bottom: 25px;
}

.profile-pic-container {
    position: relative;
    display: inline-block;
    margin-bottom: 15px;
}

.profile-pic {
    width: 130px;
    height: 130px;
    border-radius: 50%;
    border: 4px solid white;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    object-fit: cover;
}

.profile-pic-upload {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: var(--primary);
    color: white;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: 3px solid white;
    transition: all 0.3s ease;
}

.profile-pic-upload:hover {
    background: #3651c4;
    transform: scale(1.1);
}

/* Verification Badge Styles */
.verification-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
    margin-right: 8px;
}

.verification-badge.verified {
    background: rgba(6, 214, 160, 0.1);
    color: var(--success);
}

.verification-badge.pending {
    background: rgba(255, 183, 3, 0.1);
    color: var(--warning);
}

.verification-badge i {
    margin-right: 5px;
    font-size: 10px;
}

/* Progress Bar Styles */
.verification-progress {
    background: #f1f3f5;
    border-radius: 30px;
    height: 8px;
    margin: 15px 0;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, var(--primary) 0%, var(--success) 100%);
    height: 100%;
    border-radius: 30px;
    transition: width 0.3s ease;
}

/* Info Item Styles */
.info-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
}

.info-icon {
    width: 40px;
    height: 40px;
    background: rgba(67, 97, 238, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    margin-right: 15px;
}

/* Category Card Styles */
.current-category-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
}

.pending-card {
    background: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
    border-radius: 15px;
    padding: 20px;
    margin: 20px 0;
}

/* Category Option Styles */
.category-option {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    display: block;
}

.category-option:hover {
    border-color: var(--primary);
    transform: translateX(5px);
}

.category-option.selected {
    border-color: var(--success);
    background: rgba(6, 214, 160, 0.05);
}

.category-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.category-option .radio-custom {
    position: absolute;
    top: 50%;
    right: 15px;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    border: 2px solid #ced4da;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.category-option input[type="radio"]:checked + .radio-custom {
    border-color: var(--success);
    background: var(--success);
    box-shadow: inset 0 0 0 4px white;
}

.category-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 5px;
}

.commission-badge {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
    padding: 3px 8px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 10px;
}

/* Document Card Styles */
.document-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 12px;
    transition: all 0.3s ease;
}

.document-card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}

.document-card.verified {
    border-left: 4px solid var(--success);
}

.document-card.pending {
    border-left: 4px solid var(--warning);
}

.document-icon {
    width: 45px;
    height: 45px;
    background: rgba(67, 97, 238, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 20px;
}

.document-info h6 {
    margin-bottom: 4px;
    font-weight: 600;
}

.document-meta {
    font-size: 11px;
    color: #6c757d;
}

.document-actions {
    display: flex;
    gap: 8px;
}

.btn-view {
    background: var(--info);
    color: white;
    border: none;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    transition: all 0.3s ease;
}

.btn-view:hover {
    background: #3aa9d9;
    color: white;
}

.btn-upload {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    transition: all 0.3s ease;
}

.btn-upload:hover {
    background: #3651c4;
    color: white;
}

/* Notification Badge */
.notification-badge {
    position: relative;
    display: inline-block;
}

.notification-badge .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Button Styles */
.btn-save {
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-save:hover {
    background: #3651c4;
    transform: translateY(-2px);
    color: white;
}

.btn-verify {
    background: var(--success);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-verify:hover {
    background: #05b585;
    transform: translateY(-2px);
    color: white;
}
</style>

<div class="dashboard-container">
    <?php include '../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header with Notifications -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-store me-2 text-primary"></i>
                        Vendor Profile
                    </h1>
                    <p class="text-muted mb-0">Manage your profile, verification, and documents</p>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Notification Bell -->
                    <div class="notification-badge">
                        <i class="fas fa-bell fa-lg" style="color: #6c757d; cursor: pointer;" 
                           data-bs-toggle="dropdown"></i>
                        <?php if (!empty($unread_notifications)): ?>
                            <span class="badge"><?php echo count($unread_notifications); ?></span>
                        <?php endif; ?>
                        <div class="dropdown-menu dropdown-menu-end p-3" style="width: 300px;">
                            <h6 class="dropdown-header">Notifications</h6>
                            <?php if (empty($unread_notifications)): ?>
                                <p class="text-muted small mb-0 p-2">No new notifications</p>
                            <?php else: ?>
                                <?php foreach($unread_notifications as $notif): ?>
                                    <div class="border-bottom p-2">
                                        <small class="fw-bold"><?php echo $notif['title']; ?></small>
                                        <p class="small mb-0"><?php echo $notif['message']; ?></p>
                                        <small class="text-muted"><?php echo date('d M H:i', strtotime($notif['created_at'])); ?></small>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-2">
                                    <a href="notifications.php" class="small">View All</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Left Column - Profile & Verification -->
            <div class="col-lg-4">
                <!-- Profile Card -->
                <div class="profile-card text-center">
                    <div class="profile-pic-container">
                        <?php 
                        $profile_pic = !empty($vendor['profile_pic']) ? $vendor['profile_pic'] : 'default.png';
                        $profile_pic_url = SITE_URL . 'assets/images/profiles/' . $profile_pic;
                        ?>
                        <img src="<?php echo $profile_pic_url; ?>" 
                             alt="Profile" class="profile-pic"
                             onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png';">
                        <div class="profile-pic-upload" data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?></h4>
                    <p class="text-muted mb-2">@<?php echo htmlspecialchars($vendor['username'] ?? ''); ?></p>
                    
                    <!-- Vendor Status -->
                    <?php 
                    $vendor_status = $vendor['vendor_status'] ?? 'pending';
                    $status_class = $vendor_status == 'approved' ? 'success' : ($vendor_status == 'pending' ? 'warning' : 'danger');
                    ?>
                    <span class="badge bg-<?php echo $status_class; ?> px-3 py-2 mb-3">
                        <i class="fas fa-<?php echo $vendor_status == 'approved' ? 'check-circle' : 'clock'; ?> me-2"></i>
                        <?php echo ucfirst($vendor_status); ?> Vendor
                    </span>
                    
                    <!-- Verification Progress -->
                    <div class="text-start mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold">Verification Progress</span>
                            <span class="text-<?php echo $is_fully_verified ? 'success' : 'warning'; ?>">
                                <?php echo $verification_score; ?>/<?php echo $total_verification_items; ?>
                            </span>
                        </div>
                        <div class="verification-progress">
                            <div class="progress-bar" style="width: <?php echo $verification_percentage; ?>%;"></div>
                        </div>
                        
                        <!-- Verification Status Items -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <i class="fas fa-envelope me-2"></i> Email
                                </span>
                                <?php if ($vendor['email_verified'] ?? 0): ?>
                                    <span class="verification-badge verified">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <i class="fas fa-phone me-2"></i> Phone
                                </span>
                                <?php if ($vendor['phone_verified'] ?? 0): ?>
                                    <span class="verification-badge verified">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <i class="fas fa-id-card me-2"></i> ID Proof
                                </span>
                                <?php if ($has_id_proof): ?>
                                    <span class="verification-badge verified">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <i class="fas fa-map-marker-alt me-2"></i> Address Proof
                                </span>
                                <?php if ($has_address_proof): ?>
                                    <span class="verification-badge verified">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>
                                    <i class="fas fa-building me-2"></i> Business Reg
                                </span>
                                <?php if ($has_business_reg): ?>
                                    <span class="verification-badge verified">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="verification-badge pending">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Complete Verification Button -->
                        <?php if (!$is_fully_verified): ?>
                        <a href="verify.php" class="btn-verify w-100 mt-3 text-center d-block">
                            <i class="fas fa-shield-alt me-2"></i>
                            Complete Verification (<?php echo $verification_score; ?>/<?php echo $total_verification_items; ?>)
                        </a>
                        <?php else: ?>
                        <div class="alert alert-success mt-3 text-center">
                            <i class="fas fa-check-circle me-2"></i>
                            Fully Verified Vendor
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Contact Info Card -->
                <div class="profile-card">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-address-card me-2 text-primary"></i>
                        Contact Information
                    </h6>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small class="text-muted">Email Address</small>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($vendor['email'] ?? ''); ?></span>
                                <?php if ($vendor['email_verified'] ?? 0): ?>
                                    <span class="badge bg-success">Verified</span>
                                <?php else: ?>
                                    <a href="verify-email.php" class="btn btn-sm btn-outline-warning">Verify</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="flex-grow-1">
                            <small class="text-muted">Phone Number</small>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><?php echo htmlspecialchars($vendor['phone'] ?? 'Not set'); ?></span>
                                <?php if ($vendor['phone_verified'] ?? 0): ?>
                                    <span class="badge bg-success">Verified</span>
                                <?php elseif (!empty($vendor['phone'])): ?>
                                    <a href="verify-phone.php" class="btn btn-sm btn-outline-warning">Verify</a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Set</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <small class="text-muted">Location</small>
                            <div>
                                <?php 
                                $location = [];
                                if (!empty($vendor['city'])) $location[] = $vendor['city'];
                                if (!empty($vendor['country'])) $location[] = $vendor['country'];
                                echo !empty($location) ? implode(', ', $location) : 'Not set';
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                            <small class="text-muted">Member Since</small>
                            <div><?php echo date('d F Y', strtotime($vendor['created_at'] ?? 'now')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Category & Documents -->
            <div class="col-lg-8">
                <!-- Category Selection Card -->
                <div class="profile-card">
                    <h5 class="fw-bold mb-4">
                        <i class="fas fa-tag me-2 text-primary"></i>
                        Category Selection
                    </h5>
                    
                    <!-- Current Category Display -->
                    <?php if (!empty($vendor['current_category_id'])): ?>
                    <div class="current-category-card">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="opacity-75">Current Active Category</small>
                                <h4 class="mb-1"><?php echo htmlspecialchars($vendor['current_category_name']); ?></h4>
                                <?php if (!empty($vendor['parent_category_name'])): ?>
                                    <small>Parent: <?php echo $vendor['parent_category_name']; ?></small>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-code me-1"></i> Slug: <?php echo $vendor['current_category_slug']; ?>
                                    </span>
                                    <span class="badge bg-light text-dark ms-2">
                                        <i class="fas fa-percentage me-1"></i> Commission: <?php echo $vendor['current_commission']; ?>%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pending Request Display -->
                    <?php if ($pending_request): ?>
                    <div class="pending-card">
                        <div class="d-flex">
                            <div class="me-3">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Pending Category Change Request</h6>
                                <p class="mb-1">Requested: <strong><?php echo $pending_request['requested_category_name']; ?></strong></p>
                                <small>Submitted on <?php echo date('d M Y, h:i A', strtotime($pending_request['created_at'])); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Category Selection Form -->
                    <?php if (!$pending_request): ?>
                    <form method="POST" action="select-category.php" id="categoryForm" class="mt-4">
                        <h6 class="fw-bold mb-3">Select New Category (Requires Admin Approval)</h6>
                        
                        <?php if (empty($all_categories)): ?>
                            <div class="alert alert-warning">No categories available</div>
                        <?php else: ?>
                            <?php 
                            $current_id = $vendor['current_category_id'] ?? null;
                            
                            // Group categories
                            $parents = [];
                            $children = [];
                            
                            foreach($all_categories as $cat) {
                                if (empty($cat['parent_id'])) {
                                    $parents[] = $cat;
                                } else {
                                    $children[$cat['parent_id']][] = $cat;
                                }
                            }
                            ?>
                            
                            <?php foreach($parents as $parent): ?>
                                <!-- Parent Category -->
                                <label class="category-option <?php echo $current_id == $parent['id'] ? 'selected' : ''; ?>">
                                    <input type="radio" name="category_id" value="<?php echo $parent['id']; ?>"
                                           <?php echo $current_id == $parent['id'] ? 'checked' : ''; ?>>
                                    <span class="radio-custom"></span>
                                    <div class="category-name">
                                        <?php echo htmlspecialchars($parent['name']); ?>
                                        <span class="commission-badge"><?php echo $parent['commission_rate']; ?>%</span>
                                        <?php if ($current_id == $parent['id']): ?>
                                            <span class="badge bg-success ms-2">Current</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-code me-1"></i> <?php echo $parent['slug']; ?>
                                    </small>
                                </label>
                                
                                <!-- Child Categories -->
                                <?php if (!empty($children[$parent['id']])): ?>
                                    <?php foreach($children[$parent['id']] as $child): ?>
                                    <label class="category-option" style="margin-left: 30px; border-left-color: var(--primary);">
                                        <input type="radio" name="category_id" value="<?php echo $child['id']; ?>"
                                               <?php echo $current_id == $child['id'] ? 'checked' : ''; ?>>
                                        <span class="radio-custom"></span>
                                        <div class="category-name">
                                            <i class="fas fa-level-down-alt me-1" style="font-size: 12px;"></i>
                                            <?php echo htmlspecialchars($child['name']); ?>
                                            <span class="commission-badge"><?php echo $child['commission_rate']; ?>%</span>
                                            <?php if ($current_id == $child['id']): ?>
                                                <span class="badge bg-success ms-2">Current</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-code me-1"></i> <?php echo $child['slug']; ?>
                                        </small>
                                    </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn-save w-100">
                                <i class="fas fa-paper-plane me-2"></i>
                                Submit Category Change Request
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                
                <!-- Documents Card -->
                <div class="profile-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">
                            <i class="fas fa-file-alt me-2 text-primary"></i>
                            My Documents
                        </h5>
                        <button class="btn-upload" data-bs-toggle="modal" data-bs-target="#documentModal">
                            <i class="fas fa-upload me-2"></i> Upload New
                        </button>
                    </div>
                    
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                            <h6 class="text-muted">No Documents Uploaded</h6>
                            <p class="text-muted small">Upload your verification documents to become a verified vendor</p>
                            <button class="btn-upload" data-bs-toggle="modal" data-bs-target="#documentModal">
                                <i class="fas fa-upload me-2"></i> Upload First Document
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach($documents as $doc): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="document-card <?php echo $doc['verified'] ? 'verified' : 'pending'; ?>">
                                        <div class="d-flex">
                                            <div class="document-icon me-3">
                                                <?php 
                                                $icon = 'fa-file';
                                                if ($doc['document_type'] == 'id_proof') $icon = 'fa-id-card';
                                                elseif ($doc['document_type'] == 'address_proof') $icon = 'fa-map-marker-alt';
                                                elseif ($doc['document_type'] == 'business_registration') $icon = 'fa-building';
                                                elseif ($doc['document_type'] == 'tax_certificate') $icon = 'fa-file-invoice';
                                                ?>
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <?php 
                                                            $type_names = [
                                                                'id_proof' => 'ID Proof',
                                                                'address_proof' => 'Address Proof',
                                                                'business_registration' => 'Business Registration',
                                                                'tax_certificate' => 'Tax Certificate'
                                                            ];
                                                            echo $type_names[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
                                                            ?>
                                                        </h6>
                                                        <?php if (!empty($doc['document_number'])): ?>
                                                            <div class="document-meta">
                                                                <i class="fas fa-hashtag me-1"></i> <?php echo htmlspecialchars($doc['document_number']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="document-meta">
                                                            <i class="fas fa-calendar me-1"></i> 
                                                            Uploaded: <?php echo date('d M Y', strtotime($doc['created_at'])); ?>
                                                        </div>
                                                        <?php if (!empty($doc['expiry_date'])): ?>
                                                            <div class="document-meta">
                                                                <i class="fas fa-hourglass-end me-1"></i>
                                                                Expires: <?php echo date('d M Y', strtotime($doc['expiry_date'])); ?>
                                                                <?php if (strtotime($doc['expiry_date']) < time()): ?>
                                                                    <span class="badge bg-danger">Expired</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($doc['verified']): ?>
                                                            <span class="badge bg-success">Verified</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Pending</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="document-actions mt-2">
                                                    <a href="<?php echo SITE_URL; ?>uploads/documents/<?php echo $doc['document_file']; ?>" 
                                                       target="_blank" class="btn-view">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                    <?php if (!$doc['verified']): ?>
                                                        <button class="btn-view" style="background: var(--danger);" 
                                                                onclick="deleteDocument(<?php echo $doc['id']; ?>)">
                                                            <i class="fas fa-trash me-1"></i> Delete
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Basic Info Form -->
                <div class="profile-card">
                    <h5 class="fw-bold mb-4">
                        <i class="fas fa-user-edit me-2 text-primary"></i>
                        Basic Information
                    </h5>
                    
                    <form method="POST" action="profile-update.php">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($vendor['username'] ?? ''); ?>" readonly disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($vendor['email'] ?? ''); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($vendor['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" 
                                       value="<?php echo htmlspecialchars($vendor['address'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" 
                                       value="<?php echo htmlspecialchars($vendor['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" 
                                       value="<?php echo htmlspecialchars($vendor['country'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" 
                                       value="<?php echo htmlspecialchars($vendor['postal_code'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Bio / Store Description</label>
                                <textarea class="form-control" name="vendor_bio" rows="4"><?php echo htmlspecialchars($vendor['vendor_bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update Profile
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="profile-update.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_avatar">
                <div class="modal-header">
                    <h5 class="modal-title">Update Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img id="avatarPreview" src="<?php echo $profile_pic_url; ?>" 
                             class="rounded-circle" width="120" height="120" style="object-fit: cover;">
                    </div>
                    <input type="file" class="form-control" name="profile_pic" accept="image/*" required 
                           onchange="previewAvatar(this)">
                    <small class="text-muted">Max size: 2MB. Allowed: JPG, PNG, GIF</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Upload Modal -->
<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="profile-update.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select class="form-select" name="document_type" required>
                            <option value="">Select Document Type</option>
                            <option value="id_proof">ID Proof (CNIC/Passport/Driver License)</option>
                            <option value="address_proof">Address Proof (Utility Bill/Bank Statement)</option>
                            <option value="business_registration">Business Registration</option>
                            <option value="tax_certificate">Tax Certificate</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Document Number (Optional)</label>
                        <input type="text" class="form-control" name="document_number" 
                               placeholder="e.g., ID card number, registration number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expiry Date (If applicable)</label>
                        <input type="date" class="form-control" name="expiry_date">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Upload File</label>
                        <input type="file" class="form-control" name="document_file" 
                               accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required>
                        <small class="text-muted">
                            Max size: 5MB. Allowed: JPG, PNG, PDF, DOC, DOCX
                        </small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Your document will be reviewed by admin within 24-48 hours.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

// Form validation for category
document.getElementById('categoryForm')?.addEventListener('submit', function(e) {
    const selected = document.querySelector('input[name="category_id"]:checked');
    if (!selected) {
        e.preventDefault();
        alert('Please select a category');
        return false;
    }
    
    // Confirm before submitting
    if (!confirm('Submit category change request for admin approval?')) {
        e.preventDefault();
        return false;
    }
    
    // Show loading
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting...';
    btn.disabled = true;
});

// Delete document function
function deleteDocument(docId) {
    if (confirm('Are you sure you want to delete this document? This action cannot be undone.')) {
        window.location.href = 'delete-document.php?id=' + docId;
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>