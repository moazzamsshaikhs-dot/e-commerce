<?php
// admin/vendors/verify.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Vendor Verification';
require_once '../includes/header.php';

// Get vendor details
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor info
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vendor) {
        $_SESSION['error'] = 'Vendor not found.';
        header('Location: dashboard.php');
        exit();
    }
    
    // Get uploaded documents
    $stmt = $db->prepare("SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$vendor_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get verification status
    $verification_status = [
        'email' => $vendor['email_verified'] ?? 0,
        'phone' => $vendor['phone_verified'] ?? 0,
        'vendor' => $vendor['vendor_verified'] ?? 0,
        'identity' => 0,
        'address' => 0,
        'business' => 0
    ];
    
    // Check document types
    foreach ($documents as $doc) {
        if ($doc['verified']) {
            switch ($doc['document_type']) {
                case 'id_proof':
                    $verification_status['identity'] = 1;
                    break;
                case 'address_proof':
                    $verification_status['address'] = 1;
                    break;
                case 'business_registration':
                    $verification_status['business'] = 1;
                    break;
            }
        }
    }
    
    // Calculate overall progress
    $total_steps = 6; // email, phone, identity, address, business, vendor
    $completed_steps = array_sum($verification_status);
    $progress_percentage = round(($completed_steps / $total_steps) * 100);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading verification data: ' . $e->getMessage();
    $vendor = [];
    $documents = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-success">
                        <i class="fas fa-check-circle me-2"></i> Vendor Verification
                    </h1>
                    <p class="text-muted mb-0">Complete your verification to start selling</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="profile.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Profile
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Progress Card -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-3">Verification Progress</h5>
                        <div class="progress mb-2" style="height: 25px;">
                            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                 style="width: <?php echo $progress_percentage; ?>%">
                                <?php echo $progress_percentage; ?>% Complete
                            </div>
                        </div>
                        <p class="text-muted small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            <?php echo $completed_steps; ?> of <?php echo $total_steps; ?> verification steps completed
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <?php if ($vendor['vendor_status'] == 'approved'): ?>
                            <span class="badge bg-success p-3">
                                <i class="fas fa-check-circle fa-2x me-2"></i>
                                <span class="h5 mb-0">Verified Vendor</span>
                            </span>
                        <?php elseif ($vendor['vendor_status'] == 'pending'): ?>
                            <span class="badge bg-warning p-3">
                                <i class="fas fa-clock fa-2x me-2"></i>
                                <span class="h5 mb-0">Pending Review</span>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger p-3">
                                <i class="fas fa-times-circle fa-2x me-2"></i>
                                <span class="h5 mb-0">Verification Required</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Verification Steps -->
        <div class="row g-4">
            <!-- Left Column - Verification Steps -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-list-check me-2 text-primary"></i> Verification Steps
                        </h5>
                    </div>
                    <div class="card-body">
                        
                        <!-- Step 1: Email Verification -->
                        <div class="verification-step mb-4 <?php echo $verification_status['email'] ? 'completed' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="step-icon me-3">
                                    <?php if ($verification_status['email']): ?>
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1">Email Verification</h6>
                                        <?php if ($verification_status['email']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2">Verify your email address: <strong><?php echo maskEmails($vendor['email'] ?? ''); ?></strong></p>
                                    <?php if (!$verification_status['email']): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="sendVerificationEmail()">
                                            <i class="fas fa-paper-plane me-1"></i> Send Verification Email
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Phone Verification -->
                        <div class="verification-step mb-4 <?php echo $verification_status['phone'] ? 'completed' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="step-icon me-3">
                                    <?php if ($verification_status['phone']): ?>
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1">Phone Verification</h6>
                                        <?php if ($verification_status['phone']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2">Verify your phone number: <strong><?php echo maskPhones($vendor['phone'] ?? 'Not provided'); ?></strong></p>
                                    <?php if (!$verification_status['phone'] && !empty($vendor['phone'])): ?>
                                        <button class="btn btn-sm btn-outline-primary" onclick="sendPhoneOTP()">
                                            <i class="fas fa-paper-plane me-1"></i> Send OTP
                                        </button>
                                    <?php elseif (empty($vendor['phone'])): ?>
                                        <a href="profile.php" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit me-1"></i> Add Phone Number
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 3: Identity Verification (ID Proof) -->
                        <div class="verification-step mb-4 <?php echo $verification_status['identity'] ? 'completed' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="step-icon me-3">
                                    <?php if ($verification_status['identity']): ?>
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-id-card"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1">Identity Verification</h6>
                                        <?php if ($verification_status['identity']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2">Upload a valid government-issued ID (Passport, Driver's License, National ID)</p>
                                    
                                    <?php 
                                    $id_doc = null;
                                    foreach ($documents as $doc) {
                                        if ($doc['document_type'] == 'id_proof') {
                                            $id_doc = $doc;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($id_doc): ?>
                                        <div class="uploaded-file mb-2">
                                            <i class="fas fa-file-alt me-2 text-primary"></i>
                                            Uploaded: <?php echo basename($id_doc['document_file']); ?>
                                            <span class="badge bg-<?php echo $id_doc['verified'] ? 'success' : 'warning'; ?> ms-2">
                                                <?php echo $id_doc['verified'] ? 'Verified' : 'Under Review'; ?>
                                            </span>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewDocument('<?php echo $id_doc['document_file']; ?>')">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadIdModal">
                                            <i class="fas fa-upload me-1"></i> Upload ID Proof
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 4: Address Verification -->
                        <div class="verification-step mb-4 <?php echo $verification_status['address'] ? 'completed' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="step-icon me-3">
                                    <?php if ($verification_status['address']): ?>
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1">Address Verification</h6>
                                        <?php if ($verification_status['address']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2">Upload a utility bill or bank statement (not older than 3 months)</p>
                                    
                                    <?php 
                                    $address_doc = null;
                                    foreach ($documents as $doc) {
                                        if ($doc['document_type'] == 'address_proof') {
                                            $address_doc = $doc;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($address_doc): ?>
                                        <div class="uploaded-file mb-2">
                                            <i class="fas fa-file-alt me-2 text-primary"></i>
                                            Uploaded: <?php echo basename($address_doc['document_file']); ?>
                                            <span class="badge bg-<?php echo $address_doc['verified'] ? 'success' : 'warning'; ?> ms-2">
                                                <?php echo $address_doc['verified'] ? 'Verified' : 'Under Review'; ?>
                                            </span>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewDocument('<?php echo $address_doc['document_file']; ?>')">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadAddressModal">
                                            <i class="fas fa-upload me-1"></i> Upload Address Proof
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 5: Business Verification (Optional) -->
                        <div class="verification-step mb-4 <?php echo $verification_status['business'] ? 'completed' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="step-icon me-3">
                                    <?php if ($verification_status['business']): ?>
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1">Business Verification</h6>
                                        <?php if ($verification_status['business']): ?>
                                            <span class="badge bg-success">Verified</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Optional</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2">Upload business registration certificate (if applicable)</p>
                                    
                                    <?php 
                                    $business_doc = null;
                                    foreach ($documents as $doc) {
                                        if ($doc['document_type'] == 'business_registration') {
                                            $business_doc = $doc;
                                            break;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if ($business_doc): ?>
                                        <div class="uploaded-file mb-2">
                                            <i class="fas fa-file-alt me-2 text-primary"></i>
                                            Uploaded: <?php echo basename($business_doc['document_file']); ?>
                                            <span class="badge bg-<?php echo $business_doc['verified'] ? 'success' : 'warning'; ?> ms-2">
                                                <?php echo $business_doc['verified'] ? 'Verified' : 'Under Review'; ?>
                                            </span>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="viewDocument('<?php echo $business_doc['document_file']; ?>')">
                                            <i class="fas fa-eye me-1"></i> View
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadBusinessModal">
                                            <i class="fas fa-upload me-1"></i> Upload Business Registration
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 6: Final Verification -->
                        <div class="verification-step <?php echo $verification_status['vendor'] ? 'completed' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="step-icon me-3">
                                    <?php if ($verification_status['vendor']): ?>
                                        <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="bg-light text-muted rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-store"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1">Final Approval</h6>
                                        <?php if ($vendor['vendor_status'] == 'approved'): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php elseif ($vendor['vendor_status'] == 'pending'): ?>
                                            <span class="badge bg-warning">Pending Review</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Started</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-muted small mb-2">After completing all steps, admin will review and approve your vendor account</p>
                                    
                                    <?php if ($completed_steps >= 5 && $vendor['vendor_status'] == 'pending'): ?>
                                        <div class="alert alert-info mt-2">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Your application is under review. We'll notify you once approved.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Status & Info -->
            <div class="col-lg-4">
                <!-- Status Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-shield-alt me-2 text-primary"></i> Verification Status
                        </h6>
                        
                        <div class="verification-status-list">
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-envelope me-2 text-muted"></i> Email</span>
                                <?php if ($verification_status['email']): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="text-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-phone me-2 text-muted"></i> Phone</span>
                                <?php if ($verification_status['phone']): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="text-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-id-card me-2 text-muted"></i> Identity</span>
                                <?php if ($verification_status['identity']): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="text-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-map-marker-alt me-2 text-muted"></i> Address</span>
                                <?php if ($verification_status['address']): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="text-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-building me-2 text-muted"></i> Business</span>
                                <?php if ($verification_status['business']): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Verified</span>
                                <?php else: ?>
                                    <span class="text-secondary"><i class="fas fa-minus-circle"></i> Optional</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><i class="fas fa-store me-2 text-muted"></i> Vendor</span>
                                <?php if ($vendor['vendor_status'] == 'approved'): ?>
                                    <span class="text-success"><i class="fas fa-check-circle"></i> Approved</span>
                                <?php elseif ($vendor['vendor_status'] == 'pending'): ?>
                                    <span class="text-warning"><i class="fas fa-clock"></i> Pending</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="fas fa-times-circle"></i> Not Started</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info Card -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-info-circle me-2 text-info"></i> Why Verify?
                        </h6>
                        
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Build Trust</strong>
                                <p class="text-muted small mb-0">Verified vendors get a badge and higher customer trust</p>
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Higher Visibility</strong>
                                <p class="text-muted small mb-0">Verified products appear higher in search results</p>
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Faster Payments</strong>
                                <p class="text-muted small mb-0">Verified vendors get priority in payment processing</p>
                            </li>
                            <li>
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>More Features</strong>
                                <p class="text-muted small mb-0">Access to premium features and marketing tools</p>
                            </li>
                        </ul>
                        
                        <hr>
                        
                        <p class="small text-muted mb-0">
                            <i class="fas fa-clock me-1"></i>
                            Verification usually takes 24-48 hours after submitting all documents.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Upload ID Modal -->
<div class="modal fade" id="uploadIdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload ID Proof</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select class="form-select" name="document_type" required>
                            <option value="id_proof">ID Proof</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document Number</label>
                        <input type="text" class="form-control" name="document_number" placeholder="ID number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload File *</label>
                        <input type="file" class="form-control" name="document_file" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">JPG, PNG or PDF. Max 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_document" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Address Modal -->
<div class="modal fade" id="uploadAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Address Proof</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select class="form-select" name="document_type" required>
                            <option value="address_proof">Address Proof</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document Number</label>
                        <input type="text" class="form-control" name="document_number" placeholder="Bill/Statement number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload File *</label>
                        <input type="file" class="form-control" name="document_file" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">JPG, PNG or PDF. Max 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_document" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Business Modal -->
<div class="modal fade" id="uploadBusinessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Business Registration</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select class="form-select" name="document_type" required>
                            <option value="business_registration">Business Registration</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Registration Number</label>
                        <input type="text" class="form-control" name="document_number" placeholder="Registration number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Upload File *</label>
                        <input type="file" class="form-control" name="document_file" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div class="form-text">JPG, PNG or PDF. Max 5MB.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_document" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Helper functions -->
<?php
function maskEmails($email) {
    if (empty($email)) return '';
    $parts = explode('@', $email);
    if (count($parts) != 2) return $email;
    
    $name = $parts[0];
    $domain = $parts[1];
    
    $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 4)) . substr($name, -2);
    return $maskedName . '@' . $domain;
}

function maskPhones($phone) {
    if (empty($phone)) return 'Not provided';
    if (strlen($phone) <= 4) return str_repeat('*', strlen($phone));
    return substr($phone, 0, 2) . str_repeat('*', strlen($phone) - 4) . substr($phone, -2);
}
?>

<style>
.verification-step {
    padding: 15px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.verification-step:hover {
    background: #f8f9fa;
}

.verification-step.completed {
    border-left: 4px solid #28a745;
    background: #f0fff4;
}

.step-icon {
    flex-shrink: 0;
}

.uploaded-file {
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.9rem;
}

.progress {
    border-radius: 20px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 20px;
    font-weight: 600;
}

.verification-status-list {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
}
</style>

<script>
function sendVerificationEmail() {
    alert('Verification email sent! Please check your inbox.');
}

function sendPhoneOTP() {
    alert('OTP sent to your phone number!');
}

function viewDocument(filename) {
    window.open('<?php echo SITE_URL; ?>uploads/documents/' + filename, '_blank');
}

// Auto-close alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>