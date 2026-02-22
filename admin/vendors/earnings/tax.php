<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'Session expired. Please login again.';
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirectToDashboard();
}

// Set vendor_id from session
$vendor_id = $_SESSION['user_id'];
error_log("Tax page loaded - Vendor ID: " . $vendor_id);

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();

    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        redirect(SITE_URL . 'admin/vendor/dashboard.php');
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect(SITE_URL . 'admin/vendor/dashboard.php');
}

$page_title = 'Tax Documents';

// Handle form submissions based on action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    error_log("POST action: " . $action);
    error_log("POST data: " . print_r($_POST, true));
    error_log("Vendor ID in POST handler: " . $vendor_id);

    switch ($action) {
        case 'update_tax_info':
            handleTaxInfoUpdate($vendor_id);
            break;

        case 'upload_tax_document':
            handleTaxDocumentUpload($vendor_id);
            break;

        case 'request_tax_form':
            handleTaxFormRequest($vendor_id);
            break;

        case 'delete_document':
            handleDocumentDelete($vendor_id);
            break;

        default:
            $_SESSION['error'] = 'Invalid action';
            header('Location: tax.php');
            exit();
    }
}

/**
 * Handle tax information update with country-specific validation
 */
function handleTaxInfoUpdate($vendor_id)
{
    global $db;

    error_log("handleTaxInfoUpdate called with vendor_id: " . $vendor_id);

    if (empty($vendor_id)) {
        error_log("ERROR: vendor_id is empty in handleTaxInfoUpdate");
        $_SESSION['error'] = "Session error. Please log in again.";
        header('Location: tax.php');
        exit();
    }

    $tax_id = trim($_POST['tax_id'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $business_name = trim($_POST['business_name'] ?? '');

    // Validation
    $errors = [];

    if (empty($tax_id)) {
        $errors[] = "Tax ID is required";
    } elseif (empty($country)) {
        $errors[] = "Country is required for tax ID validation";
    } else {
        // Remove spaces and dashes for validation
        $clean_tax_id = preg_replace('/[-\s]/', '', $tax_id);

        // Country-specific validation
        switch ($country) {
            case 'US': // United States
                if (!preg_match('/^\d{9}$/', $clean_tax_id) && !preg_match('/^\d{2}\d{7}$/', $clean_tax_id)) {
                    $errors[] = "US Tax ID must be 9 digits (SSN: 123-45-6789 or EIN: 12-3456789)";
                }
                break;

            case 'CA': // Canada
                if (!preg_match('/^\d{9}$/', $clean_tax_id)) {
                    $errors[] = "Canadian SIN must be 9 digits (format: 123-456-789)";
                }
                break;

            case 'UK': // United Kingdom
                if (!preg_match('/^\d{10}$/', $clean_tax_id)) {
                    $errors[] = "UK UTR must be 10 digits";
                }
                break;

            case 'AU': // Australia
                if (!preg_match('/^\d{11}$/', $clean_tax_id)) {
                    $errors[] = "Australian ABN must be 11 digits";
                }
                break;

            case 'IN': // India
                if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', strtoupper($clean_tax_id))) {
                    $errors[] = "Indian PAN must be format: ABCDE1234F";
                }
                break;

            case 'PK': // Pakistan
                if (!preg_match('/^\d{7}-\d$/', $tax_id) && !preg_match('/^\d{8}$/', $clean_tax_id)) {
                    $errors[] = "Pakistani NTN must be 7 digits with 1 check digit (format: 1234567-8)";
                }
                break;

            default:
                // Generic validation for other countries
                if (strlen($clean_tax_id) < 8 || strlen($clean_tax_id) > 15) {
                    $errors[] = "Tax ID should be between 8-15 characters";
                } elseif (!preg_match('/^[A-Z0-9]+$/i', $clean_tax_id)) {
                    $errors[] = "Tax ID can only contain letters and numbers";
                }
                break;
        }
    }

    if (empty($country)) {
        $errors[] = "Country is required";
    }

    if (empty($errors)) {
        try {
            $db = getDB();

            $stmt = $db->prepare("
                UPDATE users SET 
                    tax_id = ?,
                    country = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $tax_id,
                $country,
                $vendor_id
            ]);

            error_log("Tax update result: " . ($result ? 'success' : 'failed'));

            if ($result) {
                $_SESSION['success'] = "Tax information updated successfully!";

                // Log activity
                if (function_exists('logUserActivity')) {
                    logUserActivity($vendor_id, 'tax_update', 'Updated tax information');
                }
            } else {
                $_SESSION['error'] = "Failed to update tax information";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating tax information: " . $e->getMessage();
            error_log("Tax update error: " . $e->getMessage());
        }
    } else {
        $_SESSION['form_errors'] = $errors;
    }

    header('Location: tax.php');
    exit();
}
/**
 * Handle tax document upload
 */
function handleTaxDocumentUpload($vendor_id)
{
    global $db;

    error_log("handleTaxDocumentUpload called with vendor_id: " . $vendor_id);

    if (empty($vendor_id)) {
        error_log("ERROR: vendor_id is empty in handleTaxDocumentUpload");
        $_SESSION['error'] = "Session error. Please log in again.";
        header('Location: tax.php');
        exit();
    }

    $document_type = trim($_POST['document_type'] ?? '');
    $document_number = trim($_POST['document_number'] ?? '');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    $errors = [];

    if (empty($document_type)) {
        $errors[] = "Document type is required";
    }

    if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Please select a document file";
    }

    if (empty($errors)) {
        $file = $_FILES['document_file'];

        // Validate file type
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed_types)) {
            $errors[] = "Only PDF, JPG and PNG files are allowed";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "File size must be less than 5MB";
        }
    }

    if (empty($errors)) {
        // Create upload directory if it doesn't exist
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/documents/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate unique filename
        $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'tax_' . $vendor_id . '_' . time() . '.' . $file_ext;
        $upload_path = $upload_dir . $filename;

        error_log("Uploading to: " . $upload_path);

        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            try {
                $db = getDB();
                $stmt = $db->prepare("
                    INSERT INTO vendor_documents (
                        vendor_id, 
                        document_type, 
                        document_number, 
                        document_file, 
                        expiry_date,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");

                $result = $stmt->execute([
                    $vendor_id,
                    $document_type,
                    $document_number,
                    $filename,
                    $expiry_date
                ]);

                error_log("Document insert result: " . ($result ? 'success' : 'failed'));

                if ($result) {
                    $_SESSION['success'] = "Document uploaded successfully! It will be verified by admin.";

                    // Log activity
                    if (function_exists('logUserActivity')) {
                        logUserActivity($vendor_id, 'document_upload', 'Uploaded tax document');
                    }
                } else {
                    $errors[] = "Failed to save document information";
                    // Delete uploaded file if database insert fails
                    unlink($upload_path);
                }
            } catch (PDOException $e) {
                $errors[] = "Error saving document: " . $e->getMessage();
                error_log("Document upload error: " . $e->getMessage());
                // Delete uploaded file if database insert fails
                unlink($upload_path);
            }
        } else {
            $errors[] = "Failed to upload file";
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
    }

    header('Location: tax.php');
    exit();
}

/**
 * Handle tax form request
 */
function handleTaxFormRequest($vendor_id)
{
    global $db;

    error_log("handleTaxFormRequest called with vendor_id: " . $vendor_id);

    if (empty($vendor_id)) {
        error_log("ERROR: vendor_id is empty in handleTaxFormRequest");
        $_SESSION['error'] = "Session error. Please log in again.";
        header('Location: tax.php');
        exit();
    }

    $tax_year = (int)($_POST['tax_year'] ?? 0);
    $form_type = trim($_POST['form_type'] ?? '');
    $delivery_method = trim($_POST['delivery_method'] ?? 'email');

    $errors = [];

    if ($tax_year < 2000 || $tax_year > date('Y')) {
        $errors[] = "Invalid tax year selected";
    }

    if (empty($form_type)) {
        $errors[] = "Form type is required";
    }

    if (empty($errors)) {
        try {
            $db = getDB();

            // First check if the table exists
            $stmt = $db->query("SHOW TABLES LIKE 'tax_form_requests'");
            $table_exists = $stmt->rowCount() > 0;

            if (!$table_exists) {
                error_log("Creating tax_form_requests table");
                // Create the table if it doesn't exist
                $db->exec("
                    CREATE TABLE IF NOT EXISTS `tax_form_requests` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `vendor_id` int(11) NOT NULL,
                        `tax_year` int(11) NOT NULL,
                        `form_type` varchar(50) NOT NULL,
                        `delivery_method` enum('email','mail') DEFAULT 'email',
                        `status` enum('pending','processing','completed','rejected') DEFAULT 'pending',
                        `notes` text DEFAULT NULL,
                        `processed_by` int(11) DEFAULT NULL,
                        `processed_at` datetime DEFAULT NULL,
                        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                        PRIMARY KEY (`id`),
                        KEY `vendor_id` (`vendor_id`),
                        KEY `status` (`status`),
                        KEY `tax_year` (`tax_year`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
                ");

                // Add foreign key constraint
                $db->exec("
                    ALTER TABLE `tax_form_requests`
                    ADD CONSTRAINT `tax_form_requests_ibfk_1` 
                    FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ");
            }

            // Insert form request with explicit vendor_id
            $stmt = $db->prepare("
                INSERT INTO tax_form_requests (
                    vendor_id, 
                    tax_year, 
                    form_type, 
                    delivery_method, 
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, 'pending', NOW())
            ");

            error_log("Inserting tax form request with vendor_id: " . $vendor_id);

            $result = $stmt->execute([
                $vendor_id,
                $tax_year,
                $form_type,
                $delivery_method
            ]);

            error_log("Tax form insert result: " . ($result ? 'success' : 'failed'));

            if ($result) {
                $_SESSION['success'] = "Tax form request submitted successfully! You will receive it within 3-5 business days.";

                // Log activity
                if (function_exists('logUserActivity')) {
                    logUserActivity($vendor_id, 'form_request', "Requested {$form_type} for {$tax_year}");
                }
            } else {
                $errors[] = "Failed to submit request";
                error_log("Failed to insert tax form request: " . print_r($stmt->errorInfo(), true));
            }
        } catch (PDOException $e) {
            $errors[] = "Error submitting request: " . $e->getMessage();
            error_log("Form request error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
    }

    header('Location: tax.php');
    exit();
}

/**
 * Handle document deletion
 */
function handleDocumentDelete($vendor_id)
{
    global $db;

    error_log("handleDocumentDelete called with vendor_id: " . $vendor_id);

    if (empty($vendor_id)) {
        error_log("ERROR: vendor_id is empty in handleDocumentDelete");
        $_SESSION['error'] = "Session error. Please log in again.";
        header('Location: tax.php');
        exit();
    }

    $document_id = (int)($_POST['document_id'] ?? 0);

    if ($document_id) {
        try {
            $db = getDB();

            // Get document info first
            $stmt = $db->prepare("SELECT document_file FROM vendor_documents WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$document_id, $vendor_id]);
            $document = $stmt->fetch();

            if ($document) {
                // Delete from database
                $stmt = $db->prepare("DELETE FROM vendor_documents WHERE id = ? AND vendor_id = ?");
                $result = $stmt->execute([$document_id, $vendor_id]);

                error_log("Document delete result: " . ($result ? 'success' : 'failed'));

                if ($result) {
                    // Delete physical file
                    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/uploads/documents/' . $document['document_file'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                        error_log("Deleted file: " . $file_path);
                    }

                    $_SESSION['success'] = "Document deleted successfully";

                    // Log activity
                    if (function_exists('logUserActivity')) {
                        logUserActivity($vendor_id, 'document_delete', 'Deleted tax document');
                    }
                } else {
                    $_SESSION['error'] = "Failed to delete document";
                }
            } else {
                $_SESSION['error'] = "Document not found";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error deleting document: " . $e->getMessage();
            error_log("Document delete error: " . $e->getMessage());
        }
    }

    header('Location: tax.php');
    exit();
}

// Get vendor info
try {
    $db = getDB();

    // Get vendor details
    $stmt = $db->prepare("
        SELECT id, username, full_name, email, phone, address, 
               vendor_since, tax_id, country, city, postal_code
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();

    error_log("Vendor data loaded: " . ($vendor ? 'success' : 'failed'));

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
    // Get earnings summary for tax year (including all statuses)
    $stmt = $db->prepare("
    SELECT 
        YEAR(created_at) as tax_year,
        COUNT(*) as total_transactions,
        SUM(vendor_amount) as total_earnings,
        SUM(commission_amount) as total_commission,
        MIN(created_at) as first_payment,
        MAX(created_at) as last_payment
    FROM vendor_earnings 
    WHERE vendor_id = ?  -- Removed status filter
    GROUP BY YEAR(created_at)
    ORDER BY tax_year DESC
");
    $stmt->execute([$vendor_id]);
    $yearly_earnings = $stmt->fetchAll();

    // After fetching yearly earnings, add this debug code
    error_log("Yearly earnings count: " . count($yearly_earnings));
    error_log("Yearly earnings data: " . print_r($yearly_earnings, true));

    // Also check total earnings regardless of status
    $stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_count,
        COALESCE(SUM(vendor_amount), 0) as total_amount
    FROM vendor_earnings 
    WHERE vendor_id = ?
");
    $stmt->execute([$vendor_id]);
    $total_earnings = $stmt->fetch();
    error_log("Total earnings records: " . $total_earnings['total_count'] . ", Total amount: " . $total_earnings['total_amount']);

    // Get settings for tax thresholds
    $stmt = $db->prepare("SELECT * FROM settings WHERE setting_key LIKE 'tax_%'");
    $stmt->execute();
    $tax_settings = [];
    while ($row = $stmt->fetch()) {
        $tax_settings[$row['setting_key']] = $row['setting_value'];
    }

    // Default tax settings if not configured
    $default_tax_settings = [
        'tax_threshold' => 600,
        'tax_year_start' => '01-01',
        'tax_year_end' => '12-31',
        'require_tax_form' => '1',
        'tax_form_deadline' => '01-31'
    ];

    foreach ($default_tax_settings as $key => $value) {
        if (!isset($tax_settings[$key])) {
            $tax_settings[$key] = $value;
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading tax data: ' . $e->getMessage();
    error_log("Tax data error: " . $e->getMessage());
    $vendor = [];
    $tax_documents = [];
    $yearly_earnings = [];
    $tax_settings = $default_tax_settings;
}

// Helper function to mask tax ID
function maskTaxID($taxId)
{
    if (!$taxId) return '';
    if (strlen($taxId) <= 4) return '••••';
    return '••••' . substr($taxId, -4);
}

require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <?php
    // include '../../includes/vendor-sidebar.php';
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

            <!-- Display Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['form_errors'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($_SESSION['form_errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['form_errors']); ?>
            <?php endif; ?>

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
                                    foreach ($yearly_earnings as $year) {
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
                                <p class="text-muted">You don't have any paid earnings records in our system.</p>

                                <?php if ($total_earnings['total_count'] > 0): ?>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        You have <?php echo $total_earnings['total_count']; ?> earnings records totaling
                                        $<?php echo number_format($total_earnings['total_amount'], 2); ?>,
                                        but they may be in 'pending' or 'processing' status.
                                        Tax documents are only generated for 'paid' earnings.
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning mt-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        No earnings records found. Start selling to generate earnings and tax documents.
                                    </div>
                                    <a href="<?php echo SITE_URL; ?>admin/vendors/products/products.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-plus me-2"></i> Add Products
                                    </a>
                                <?php endif; ?>
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
                                        <?php foreach ($yearly_earnings as $year): ?>
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
                                <?php foreach ($tax_documents as $doc): ?>
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
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this document?');">
                                                        <input type="hidden" name="action" value="delete_document">
                                                        <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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
                            <button type="button" class="list-group-item list-group-item-action border-0 px-0 py-3"
                                data-bs-toggle="modal" data-bs-target="#faqModal">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-question-circle me-3 text-info"></i>
                                    <div>
                                        <h6 class="mb-1">Tax FAQ</h6>
                                        <small class="text-muted">Common tax questions answered</small>
                                    </div>
                                </div>
                            </button>
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
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_tax_info">

                <div class="modal-header">
                    <h5 class="modal-title">Update Tax Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- In the Update Tax Information Modal -->
                    <div class="mb-3">
                        <label class="form-label">Tax ID / EIN</label>
                        <input type="text" class="form-control" name="tax_id"
                            value="<?php echo htmlspecialchars($vendor['tax_id'] ?? ''); ?>"
                            placeholder="Enter your tax ID" required>
                        <!-- <small class="text-muted">
                            Examples:
                            <br>US: 123-45-6789 or 12-3456789
                            <br>Canada: 123-456-789
                            <br>UK: 1234567890
                            <br>Australia: 12 345 678 901
                            <br>India: ABCDE1234F
                            <br>Pakistan: 1234567-8
                        </small> -->
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
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_tax_document">

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
            <form method="POST" action="">
                <input type="hidden" name="action" value="request_tax_form">

                <div class="modal-header">
                    <h5 class="modal-title">Request Tax Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tax Year</label>
                        <select class="form-select" name="tax_year" required>
                            <?php for ($i = $current_year; $i >= $current_year - 5; $i--): ?>
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
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
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
                window.location.href = `action/download_tax_form.php?year=${year}`;
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

    // Generate earnings report
    function generateEarningsReport(year) {
        window.open(`action/generate_earnings_report.php?year=${year}`, '_blank');
    }

    // Form validation
    document.querySelectorAll('form[action=""]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                submitBtn.disabled = true;
            }
        });
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
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>