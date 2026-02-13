<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirectToDashboard();
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        redirect(SITE_URL . 'admin/vendor/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect(SITE_URL . 'admin/vendor/dashboard.php');
}

$page_title = 'Tax Documents';
require_once '../../includes/header.php';

// Get vendor info
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor details
    $stmt = $db->prepare("
        SELECT id, username, full_name, email, phone, address, 
               vendor_since, tax_id, country, city, postal_code
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get tax documents from vendor_documents table
    $stmt = $db->prepare("
        SELECT * FROM vendor_documents 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $tax_documents = $stmt->fetchAll();
    
    // Get earnings summary for tax year
    $current_year = date('Y');
    $stmt = $db->prepare("
        SELECT 
            YEAR(created_at) as tax_year,
            COUNT(*) as total_transactions,
            SUM(vendor_amount) as total_earnings,
            SUM(commission_amount) as total_commission,
            MIN(created_at) as first_payment,
            MAX(created_at) as last_payment
        FROM vendor_earnings 
        WHERE vendor_id = ? AND status = 'paid'
        GROUP BY YEAR(created_at)
        ORDER BY tax_year DESC
    ");
    $stmt->execute([$vendor_id]);
    $yearly_earnings = $stmt->fetchAll();
    
    // Get settings for tax thresholds
    $stmt = $db->prepare("SELECT * FROM settings WHERE setting_key LIKE 'tax_%'");
    $stmt->execute();
    $tax_settings = [];
    while($row = $stmt->fetch()) {
        $tax_settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Default tax settings if not configured
    $default_tax_settings = [
        'tax_threshold' => 600, // USD - IRS threshold for 1099 forms
        'tax_year_start' => '01-01',
        'tax_year_end' => '12-31',
        'require_tax_form' => '1',
        'tax_form_deadline' => '01-31'
    ];
    
    foreach($default_tax_settings as $key => $value) {
        if (!isset($tax_settings[$key])) {
            $tax_settings[$key] = $value;
        }
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading tax data: ' . $e->getMessage();
    $vendor = [];
    $tax_documents = [];
    $yearly_earnings = [];
    $tax_settings = $default_tax_settings;
}
?>

<div class="dashboard-container">
    <?php
    //  include '../../includes/vendor-sidebar.php'; 
     ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Tax Documents</h1>
                    <p class="text-muted mb-0">Manage your tax forms and documents</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="earnings.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Earnings
                    </a>
                    <?php if (!empty($vendor['tax_id'])): ?>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadTaxDocModal">
                            <i class="fas fa-upload me-2"></i> Upload Document
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#updateTaxInfoModal">
                            <i class="fas fa-id-card me-2"></i> Add Tax ID
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tax Year Alert -->
            <div class="alert alert-info mt-3 mb-0">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle me-3"></i>
                    <div>
                        <strong>Tax Year <?php echo $current_year; ?>:</strong> 
                        All tax forms for <?php echo $current_year; ?> will be available by January 31, <?php echo $current_year + 1; ?>.
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tax Summary Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Tax ID Status</h6>
                                <h4 class="mb-0">
                                    <?php echo !empty($vendor['tax_id']) ? 
                                        '<span class="text-success">On File</span>' : 
                                        '<span class="text-warning">Required</span>'; 
                                    ?>
                                </h4>
                            </div>
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-id-card text-primary"></i>
                            </div>
                        </div>
                        <?php if (!empty($vendor['tax_id'])): ?>
                            <small class="text-muted d-block mt-2">
                                ID: <?php echo maskTaxID($vendor['tax_id']); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Year-to-Date Earnings</h6>
                                <h4 class="mb-0">
                                    <?php
                                    $current_year_earnings = 0;
                                    foreach($yearly_earnings as $year) {
                                        if ($year['tax_year'] == $current_year) {
                                            $current_year_earnings = $year['total_earnings'];
                                            break;
                                        }
                                    }
                                    ?>
                                    $<?php echo number_format($current_year_earnings, 2); ?>
                                </h4>
                            </div>
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-chart-line text-success"></i>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <?php echo $current_year; ?> Tax Year
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Tax Threshold</h6>
                                <h4 class="mb-0">$<?php echo number_format($tax_settings['tax_threshold'], 0); ?></h4>
                            </div>
                            <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-balance-scale text-warning"></i>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            1099-K Filing Limit
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-5 border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Forms Due By</h6>
                                <h4 class="mb-0">Jan 31</h4>
                            </div>
                            <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                <i class="fas fa-calendar-day text-info"></i>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">
                            <?php echo $current_year + 1; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tax Documents Section -->
        <div class="row g-4">
            <!-- Available Tax Forms -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Available Tax Documents</h5>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#requestFormModal">
                                <i class="fas fa-file-download me-1"></i> Request Form
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($yearly_earnings)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No tax documents available yet</p>
                                <p class="text-muted small">Tax forms will be generated at the end of each tax year</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tax Year</th>
                                            <th>Total Earnings</th>
                                            <th>Transactions</th>
                                            <th>Forms Available</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($yearly_earnings as $year): ?>
                                        <?php
                                        $requires_form = $year['total_earnings'] >= $tax_settings['tax_threshold'];
                                        $form_available = ($current_year - $year['tax_year'] >= 1) || 
                                                         (date('m-d') > $tax_settings['tax_form_deadline'] && $current_year == $year['tax_year'] + 1);
                                        ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo $year['tax_year']; ?></td>
                                            <td class="text-success fw-bold">$<?php echo number_format($year['total_earnings'], 2); ?></td>
                                            <td><?php echo $year['total_transactions']; ?> transactions</td>
                                            <td>
                                                <?php if ($requires_form): ?>
                                                    <span class="badge bg-success">1099-K Required</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">No Form Required</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($form_available): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i> Available
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i> Pending
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($form_available && $requires_form): ?>
                                                    <button type="button" class="btn btn-sm btn-primary download-tax-form" 
                                                            data-year="<?php echo $year['tax_year']; ?>">
                                                        <i class="fas fa-download me-1"></i> Download
                                                    </button>
                                                <?php elseif ($requires_form): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                        Available Jan 31
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                            onclick="generateEarningsReport(<?php echo $year['tax_year']; ?>)">
                                                        <i class="fas fa-file-alt me-1"></i> View Report
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Uploaded Documents -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Uploaded Tax Documents</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tax_documents)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-folder-open fa-2x text-muted mb-3"></i>
                                <p class="text-muted">No documents uploaded yet</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadTaxDocModal">
                                    <i class="fas fa-upload me-2"></i> Upload Your First Document
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach($tax_documents as $doc): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card document-card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
                                                <div class="document-icon bg-primary bg-opacity-10 rounded p-2 me-3">
                                                    <i class="fas fa-file-pdf text-primary"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></h6>
                                                    <small class="text-muted">
                                                        Uploaded: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <?php if ($doc['verified']): ?>
                                                <span class="badge bg-success mb-2">
                                                    <i class="fas fa-check me-1"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning mb-2">
                                                    <i class="fas fa-clock me-1"></i> Pending Review
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($doc['expiry_date']): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">Expires:</small>
                                                    <br>
                                                    <small class="<?php echo strtotime($doc['expiry_date']) < time() ? 'text-danger' : 'text-muted'; ?>">
                                                        <?php echo date('M d, Y', strtotime($doc['expiry_date'])); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex gap-2 mt-3">
                                                <a href="action/download_document.php?id=<?php echo $doc['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-info view-document" 
                                                        data-id="<?php echo $doc['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-document" 
                                                        data-id="<?php echo $doc['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tax Information & Resources -->
            <div class="col-lg-4">
                <!-- Tax Information -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Your Tax Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Tax ID / EIN</h6>
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <?php echo !empty($vendor['tax_id']) ? 
                                        maskTaxID($vendor['tax_id']) : 
                                        '<span class="text-danger">Not Provided</span>'; 
                                    ?>
                                </h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" data-bs-target="#updateTaxInfoModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Tax Classification</h6>
                            <p class="mb-0">Individual / Sole Proprietor</p>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-muted mb-2">Country of Taxation</h6>
                            <p class="mb-0">
                                <?php echo !empty($vendor['country']) ? $vendor['country'] : 'Not specified'; ?>
                            </p>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Important:</strong> Ensure your tax information is accurate and up to date.
                                Incorrect information may delay form processing.
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Tax Resources -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0 fw-bold">Tax Resources</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="https://www.irs.gov/forms-pubs/about-form-1099-k" 
                               target="_blank" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-external-link-alt me-3 text-primary"></i>
                                    <div>
                                        <h6 class="mb-1">IRS Form 1099-K Guide</h6>
                                        <small class="text-muted">Official IRS documentation</small>
                                    </div>
                                </div>
                            </a>
                            <a href="https://www.irs.gov/businesses/small-businesses-self-employed/self-employed-individuals-tax-center" 
                               target="_blank" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-external-link-alt me-3 text-primary"></i>
                                    <div>
                                        <h6 class="mb-1">Self-Employed Tax Center</h6>
                                        <small class="text-muted">IRS resources for self-employed</small>
                                    </div>
                                </div>
                            </a>
                            <a href="https://www.irs.gov/payments" 
                               target="_blank" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-external-link-alt me-3 text-primary"></i>
                                    <div>
                                        <h6 class="mb-1">Make Tax Payments</h6>
                                        <small class="text-muted">Pay taxes online</small>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action border-0 px-0 py-3" 
                               data-bs-toggle="modal" data-bs-target="#faqModal">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-question-circle me-3 text-info"></i>
                                    <div>
                                        <h6 class="mb-1">Tax FAQ</h6>
                                        <small class="text-muted">Common tax questions answered</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Important Dates -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-calendar-alt me-2 text-danger"></i> Important Tax Dates
                        </h5>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0 py-2">
                                <small class="text-muted">January 31</small>
                                <h6 class="mb-0">1099-K Forms Available</h6>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <small class="text-muted">April 15</small>
                                <h6 class="mb-0">Tax Filing Deadline</h6>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <small class="text-muted">Quarterly</small>
                                <h6 class="mb-0">Estimated Tax Payments Due</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Update Tax Information Modal -->
<div class="modal fade" id="updateTaxInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="taxInfoForm" action="update_tax_info.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Update Tax Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tax ID / EIN</label>
                        <input type="text" class="form-control" name="tax_id" 
                               value="<?php echo htmlspecialchars($vendor['tax_id'] ?? ''); ?>"
                               placeholder="XX-XXXXXXX" required>
                        <small class="text-muted">Enter your Social Security Number (SSN) or Employer Identification Number (EIN)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Country for Taxation</label>
                        <select class="form-select" name="country" required>
                            <option value="">Select Country</option>
                            <option value="US" <?php echo ($vendor['country'] ?? '') == 'US' ? 'selected' : ''; ?>>United States</option>
                            <option value="CA" <?php echo ($vendor['country'] ?? '') == 'CA' ? 'selected' : ''; ?>>Canada</option>
                            <option value="UK" <?php echo ($vendor['country'] ?? '') == 'UK' ? 'selected' : ''; ?>>United Kingdom</option>
                            <option value="AU" <?php echo ($vendor['country'] ?? '') == 'AU' ? 'selected' : ''; ?>>Australia</option>
                            <option value="IN" <?php echo ($vendor['country'] ?? '') == 'IN' ? 'selected' : ''; ?>>India</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Legal Business Name</label>
                        <input type="text" class="form-control" name="business_name" 
                               value="<?php echo htmlspecialchars($vendor['full_name'] ?? ''); ?>">
                        <small class="text-muted">Leave blank if using your personal name</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            This information is required for tax reporting purposes and will be kept secure.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Tax Information</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Tax Document Modal -->
<div class="modal fade" id="uploadTaxDocModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="uploadTaxDocForm" action="upload_tax_document.php" method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Tax Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select class="form-select" name="document_type" required>
                            <option value="">Select Document Type</option>
                            <option value="id_proof">Government ID</option>
                            <option value="tax_certificate">Tax Certificate</option>
                            <option value="business_registration">Business Registration</option>
                            <option value="address_proof">Address Proof</option>
                            <option value="other">Other Tax Document</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Document Number</label>
                        <input type="text" class="form-control" name="document_number" 
                               placeholder="Document identification number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expiry Date (if applicable)</label>
                        <input type="date" class="form-control" name="expiry_date">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Upload Document</label>
                        <input type="file" class="form-control" name="document_file" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted">Accepted formats: PDF, JPG, PNG (Max 5MB)</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Only upload official tax documents. All documents will be verified by our team.
                        </small>
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

<!-- Request Tax Form Modal -->
<div class="modal fade" id="requestFormModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="requestFormForm" action="request_tax_form.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Request Tax Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tax Year</label>
                        <select class="form-select" name="tax_year" required>
                            <?php for($i = $current_year; $i >= $current_year - 5; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i == $current_year ? 'selected' : ''; ?>>
                                <?php echo $i; ?> Tax Year
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Form Type</label>
                        <select class="form-select" name="form_type" required>
                            <option value="1099k">1099-K (Payment Card Transactions)</option>
                            <option value="1099nec">1099-NEC (Nonemployee Compensation)</option>
                            <option value="1099misc">1099-MISC (Miscellaneous Income)</option>
                            <option value="earnings_summary">Earnings Summary Report</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Delivery Method</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_method" value="email" checked>
                            <label class="form-check-label">
                                Email (Digital PDF)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="delivery_method" value="mail">
                            <label class="form-check-label">
                                Postal Mail (Physical Copy)
                            </label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Forms are typically processed within 3-5 business days. You will receive a confirmation email.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FAQ Modal -->
<div class="modal fade" id="faqModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tax FAQ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="accordion" id="taxFaqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                When will I receive my 1099-K form?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#taxFaqAccordion">
                            <div class="accordion-body">
                                We are required by law to provide 1099-K forms by January 31st of each year for the previous tax year. Forms will be available for download in your vendor dashboard by this date.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                Who needs to file a 1099-K form?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#taxFaqAccordion">
                            <div class="accordion-body">
                                You will receive a 1099-K form if you meet both of these criteria in a calendar year:
                                <ul>
                                    <li>You received more than $600 in gross payments</li>
                                    <li>You had more than 200 transactions</li>
                                </ul>
                                The threshold may vary by state.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                What if I didn't make $600?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#taxFaqAccordion">
                            <div class="accordion-body">
                                If you earned less than $600, we are not required to send you a 1099-K form. However, you are still required to report all income on your tax return, regardless of amount.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Can I deduct business expenses?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#taxFaqAccordion">
                            <div class="accordion-body">
                                Yes, as a self-employed individual, you can deduct ordinary and necessary business expenses. These might include:
                                <ul>
                                    <li>Home office expenses</li>
                                    <li>Internet and phone bills</li>
                                    <li>Shipping and packaging costs</li>
                                    <li>Advertising expenses</li>
                                    <li>Equipment and supplies</li>
                                </ul>
                                We recommend consulting with a tax professional for specific advice.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="../help/support.php?category=tax" class="btn btn-primary">Contact Support</a>
            </div>
        </div>
    </div>
</div>

<style>
.document-card {
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}

.document-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
}

.document-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-sm {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card.border-start {
    border-left-width: 5px !important;
}

.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

.table th {
    background: #f8f9fa;
    font-weight: 600;
}
</style>

<script>
// Helper function to mask tax ID
function maskTaxID(taxId) {
    if (!taxId) return '';
    if (taxId.length <= 4) return '••••';
    return '••••' + taxId.slice(-4);
}

// Download tax form
document.querySelectorAll('.download-tax-form').forEach(button => {
    button.addEventListener('click', function() {
        const year = this.getAttribute('data-year');
        if (confirm(`Download tax form for ${year}?`)) {
            window.location.href = `download_tax_form.php?year=${year}`;
        }
    });
});

// View document
document.querySelectorAll('.view-document').forEach(button => {
    button.addEventListener('click', function() {
        const docId = this.getAttribute('data-id');
        window.open(`action/view_document.php?id=${docId}`, '_blank');
    });
});

// Delete document
document.querySelectorAll('.delete-document').forEach(button => {
    button.addEventListener('click', function() {
        const docId = this.getAttribute('data-id');
        if (confirm('Are you sure you want to delete this document?')) {
            window.open(`action/delete_document.php?id=${docId}`);
        }
    });
});

// Generate earnings report
function generateEarningsReport(year) {
    const url = `generate_earnings_report.php?year=${year}`;
    window.open(url, '_blank');
}

// Form validation
document.getElementById('taxInfoForm')?.addEventListener('submit', function(e) {
    const taxId = this.querySelector('[name="tax_id"]').value;
    if (!taxId.match(/^[0-9]{2}-[0-9]{7}$/) && !taxId.match(/^[0-9]{9}$/)) {
        e.preventDefault();
        alert('Please enter a valid Tax ID (format: XX-XXXXXXX or XXXXXXXXX)');
    }
});

document.getElementById('uploadTaxDocForm')?.addEventListener('submit', function(e) {
    const fileInput = this.querySelector('[name="document_file"]');
    const file = fileInput.files[0];
    
    if (file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        
        if (file.size > maxSize) {
            e.preventDefault();
            alert('File size must be less than 5MB');
            return;
        }
        
        if (!validTypes.includes(file.type)) {
            e.preventDefault();
            alert('Please upload a PDF, JPG, or PNG file');
            return;
        }
    }
});

// Auto-close alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    });
}, 8000);

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>