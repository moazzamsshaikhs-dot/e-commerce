<?php
// admin/vendors/document-approvals.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

// Special check for system administrator
requireSystemAdmin();

$page_title = 'Vendor Document Approvals';
require_once '../includes/header.php';

$db = getDB();
$message = '';
$message_type = 'success';

// Handle document approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $document_id = (int)($_POST['document_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($document_id && in_array($action, ['approve', 'reject'])) {
        try {
            $db->beginTransaction();
            
            // Get document details
            $stmt = $db->prepare("
                SELECT vd.*, u.username, u.email, u.full_name, u.id as vendor_id
                FROM vendor_documents vd
                JOIN users u ON vd.vendor_id = u.id
                WHERE vd.id = ?
            ");
            $stmt->execute([$document_id]);
            $document = $stmt->fetch();
            
            if (!$document) {
                throw new Exception('Document not found');
            }
            
            if ($action === 'approve') {
                // Check if verified_by column exists
                $columns = $db->query("SHOW COLUMNS FROM vendor_documents LIKE 'verified_by'")->rowCount();
                
                if ($columns > 0) {
                    // Approve document with verified_by
                    $stmt = $db->prepare("
                        UPDATE vendor_documents 
                        SET verified = 1, verified_by = ?, verified_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id'], $document_id]);
                } else {
                    // Approve document without verified_by
                    $stmt = $db->prepare("
                        UPDATE vendor_documents 
                        SET verified = 1
                        WHERE id = ?
                    ");
                    $stmt->execute([$document_id]);
                }
                
                // Check if all vendor documents are verified
                $stmt = $db->prepare("
                    SELECT COUNT(*) as total, 
                           SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified
                    FROM vendor_documents 
                    WHERE vendor_id = ?
                ");
                $stmt->execute([$document['vendor_id']]);
                $doc_stats = $stmt->fetch();
                
                // If all documents verified, update vendor verification status
                if ($doc_stats['total'] == $doc_stats['verified']) {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET vendor_verified = 1 
                        WHERE id = ?
                    ");
                    $stmt->execute([$document['vendor_id']]);
                }
                
                // Create notification for vendor
                $doc_type = ucfirst(str_replace('_', ' ', $document['document_type']));
                $message = "✅ Your <strong>{$doc_type}</strong> has been approved and verified.";
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Document Approved', ?, 'success', NOW())
                ");
                $stmt->execute([$document['vendor_id'], $message]);
                
                // Log activity
                logUserActivity($_SESSION['user_id'], 'document_approved', 
                    "Approved document ID: {$document_id} for vendor: {$document['full_name']}");
                
                $_SESSION['success'] = "Document approved successfully!";
                
            } else {
                // Check if rejection_reason column exists
                $columns = $db->query("SHOW COLUMNS FROM vendor_documents LIKE 'rejection_reason'")->rowCount();
                
                if ($columns > 0) {
                    // Reject document with rejection reason
                    $stmt = $db->prepare("
                        UPDATE vendor_documents 
                        SET verified = 0, rejection_reason = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$rejection_reason, $document_id]);
                } else {
                    // Reject document without rejection reason (just set verified = 0)
                    $stmt = $db->prepare("
                        UPDATE vendor_documents 
                        SET verified = 0
                        WHERE id = ?
                    ");
                    $stmt->execute([$document_id]);
                }
                
                // Update vendor verification status (set to 0 if any document is rejected)
                $stmt = $db->prepare("
                    UPDATE users 
                    SET vendor_verified = 0 
                    WHERE id = ?
                ");
                $stmt->execute([$document['vendor_id']]);
                
                // Create notification for vendor
                $doc_type = ucfirst(str_replace('_', ' ', $document['document_type']));
                $message = " Your <strong>{$doc_type}</strong> has been rejected.";
                if (!empty($rejection_reason)) {
                    $message .= "<br><strong>Reason:</strong> {$rejection_reason}";
                }
                
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Document Rejected', ?, 'error', NOW())
                ");
                $stmt->execute([$document['vendor_id'], $message]);
                
                // Log activity
                logUserActivity($_SESSION['user_id'], 'document_rejected', 
                    "Rejected document ID: {$document_id} for vendor: {$document['full_name']}" . 
                    (!empty($rejection_reason) ? ". Reason: {$rejection_reason}" : ""));
                
                $_SESSION['success'] = "Document rejected!";
            }
            
            $db->commit();
            
        } catch(Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
            error_log("Document approval error: " . $e->getMessage());
        }
        
        redirect('document-approvals.php');
        exit();
    }
}

// Get filter
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';

// Build query - FIXED: Remove rejection_reason from WHERE clause if column doesn't exist
$columns = $db->query("SHOW COLUMNS FROM vendor_documents LIKE 'rejection_reason'")->rowCount();
$has_rejection_reason = ($columns > 0);

$query = "
    SELECT 
        vd.*,
        u.username,
        u.full_name,
        u.email,
        u.phone,
        u.profile_pic,
        u.vendor_verified,
        (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id AND verified = 1) as verified_count,
        (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id) as total_count
    FROM vendor_documents vd
    JOIN users u ON vd.vendor_id = u.id
    WHERE 1=1
";

$params = [];

if ($status === 'pending') {
    $query .= " AND vd.verified = 0";
    // Only filter by rejection_reason if column exists
    if ($has_rejection_reason) {
        $query .= " AND vd.rejection_reason IS NULL";
    }
} elseif ($status === 'approved') {
    $query .= " AND vd.verified = 1";
} elseif ($status === 'rejected') {
    if ($has_rejection_reason) {
        $query .= " AND vd.rejection_reason IS NOT NULL";
    } else {
        // If rejection_reason doesn't exist, we can't filter by rejected
        // So show no results or show a message
        $query .= " AND 1=0"; // No results
    }
}

if (!empty($type)) {
    $query .= " AND vd.document_type = ?";
    $params[] = $type;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR vd.document_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY vd.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics - FIXED: Handle missing columns
$pending_query = "SELECT COUNT(*) as count FROM vendor_documents WHERE verified = 0";
if ($has_rejection_reason) {
    $pending_query .= " AND rejection_reason IS NULL";
}
$pending = $db->query($pending_query)->fetchColumn();

$approved = $db->query("SELECT COUNT(*) FROM vendor_documents WHERE verified = 1")->fetchColumn();

$rejected = 0;
if ($has_rejection_reason) {
    $rejected = $db->query("SELECT COUNT(*) FROM vendor_documents WHERE rejection_reason IS NOT NULL")->fetchColumn();
}

$total = $db->query("SELECT COUNT(*) FROM vendor_documents")->fetchColumn();

$stats = [
    'pending' => $pending,
    'approved' => $approved,
    'rejected' => $rejected,
    'total' => $total
];

// Get document types for filter
$doc_types = $db->query("
    SELECT DISTINCT document_type, COUNT(*) as count 
    FROM vendor_documents 
    GROUP BY document_type
")->fetchAll();

// Get vendor verification stats
$vendor_stats = $db->query("
    SELECT 
        COUNT(CASE WHEN vendor_verified = 1 THEN 1 END) as verified_vendors,
        COUNT(CASE WHEN vendor_verified = 0 AND vendor_status = 'approved' THEN 1 END) as pending_vendors,
        COUNT(*) as total_vendors
    FROM users
    WHERE user_type = 'vendor'
")->fetch();
?>

<!-- Rest of your HTML/CSS remains exactly the same as before -->
<!-- Copy all the CSS from the previous response here -->
<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3651c4;
    --primary-light: rgba(67, 97, 238, 0.1);
    --success: #06d6a0;
    --success-dark: #05b585;
    --success-light: rgba(6, 214, 160, 0.1);
    --warning: #ffb703;
    --warning-dark: #e6a500;
    --warning-light: rgba(255, 183, 3, 0.1);
    --danger: #ef476f;
    --danger-dark: #d64161;
    --danger-light: rgba(239, 71, 111, 0.1);
    --info: #4cc9f0;
    --info-dark: #3aa9d9;
    --info-light: rgba(76, 201, 240, 0.1);
    --dark: #2b2d42;
    --dark-light: rgba(43, 45, 66, 0.1);
    --light: #f8f9fa;
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --shadow-glow: 0 0 20px rgba(67, 97, 238, 0.3);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInLeft {
    from {
        transform: translateX(-30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes pulse-glow {
    0% { box-shadow: 0 0 0 0 var(--warning); }
    70% { box-shadow: 0 0 0 10px rgba(255, 183, 3, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 183, 3, 0); }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

/* Main Layout */
.documents-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.6s ease-out;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
    border-radius: 50%;
    z-index: 0;
}

.page-header > div {
    position: relative;
    z-index: 1;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border-left: 4px solid transparent;
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.5s ease-out;
    animation-fill-mode: both;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.15s; }
.stat-card:nth-child(3) { animation-delay: 0.2s; }
.stat-card:nth-child(4) { animation-delay: 0.25s; }

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: translateX(-100%);
    animation: shimmer 2s infinite;
    pointer-events: none;
}

.stat-card.pending { border-left-color: var(--warning); }
.stat-card.approved { border-left-color: var(--success); }
.stat-card.rejected { border-left-color: var(--danger); }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}

.stat-card.pending .stat-icon { background: var(--warning-light); color: var(--warning); }
.stat-card.approved .stat-icon { background: var(--success-light); color: var(--success); }
.stat-card.rejected .stat-icon { background: var(--danger-light); color: var(--danger); }

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
}

.stat-label {
    color: var(--dark);
    opacity: 0.7;
    font-size: 14px;
    font-weight: 500;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow);
    animation: slideInUp 0.5s ease-out 0.3s both;
}

.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark);
    background: var(--light);
    transition: var(--transition);
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--border);
}

.filter-tab:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
}

.filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.75rem;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

/* Search Box */
.search-box {
    display: flex;
    align-items: center;
    background: var(--light);
    border-radius: 30px;
    padding: 4px;
    min-width: 300px;
    border: 1px solid var(--border);
}

.search-box input {
    border: none;
    background: transparent;
    padding: 8px 16px;
    flex: 1;
    font-size: 0.875rem;
    outline: none;
    color: var(--dark);
}

.search-box input::placeholder {
    color: var(--dark);
    opacity: 0.5;
}

.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

.search-box button:hover {
    background: var(--primary-dark);
}

/* Documents Grid */
.documents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.document-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
    animation: slideInLeft 0.4s ease-out;
    animation-fill-mode: both;
}

.document-card:nth-child(1) { animation-delay: 0.1s; }
.document-card:nth-child(2) { animation-delay: 0.15s; }
.document-card:nth-child(3) { animation-delay: 0.2s; }
.document-card:nth-child(4) { animation-delay: 0.25s; }
.document-card:nth-child(5) { animation-delay: 0.3s; }

.document-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: var(--shadow-hover);
}

.document-card.pending { border-left: 4px solid var(--warning); }
.document-card.approved { border-left: 4px solid var(--success); }
.document-card.rejected { border-left: 4px solid var(--danger); }

.document-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
}

.document-type {
    display: flex;
    align-items: center;
    gap: 8px;
}

.document-type i {
    width: 40px;
    height: 40px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.document-type.id_proof i { background: var(--primary-light); color: var(--primary); }
.document-type.address_proof i { background: var(--info-light); color: var(--info); }
.document-type.business_registration i { background: var(--success-light); color: var(--success); }
.document-type.tax_certificate i { background: var(--warning-light); color: var(--warning); }

.document-type h6 {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.document-type small {
    font-size: 11px;
    color: var(--dark);
    opacity: 0.6;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
}

.status-pending {
    background: var(--warning-light);
    color: var(--warning-dark);
    animation: pulse-glow 2s infinite;
}

.status-approved {
    background: var(--success-light);
    color: var(--success-dark);
}

.status-rejected {
    background: var(--danger-light);
    color: var(--danger-dark);
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.vendor-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.vendor-details h6 {
    font-size: 14px;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.vendor-details small {
    font-size: 11px;
    color: var(--dark);
    opacity: 0.6;
}

.document-details {
    margin-bottom: 15px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 13px;
}

.detail-label {
    color: var(--dark);
    opacity: 0.7;
}

.detail-value {
    font-weight: 500;
    color: var(--dark);
}

.document-actions {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.btn-action {
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 500;
    transition: var(--transition-bounce);
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
    flex: 1;
    justify-content: center;
}

.btn-approve {
    background: var(--success-light);
    color: var(--success-dark);
    border: 1px solid var(--success);
}

.btn-approve:hover {
    background: var(--success);
    color: white;
}

.btn-reject {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
}

.btn-reject:hover {
    background: var(--danger);
    color: white;
}

.btn-view {
    background: var(--primary-light);
    color: var(--primary-dark);
    border: 1px solid var(--primary);
    flex: 0.5;
}

.btn-view:hover {
    background: var(--primary);
    color: white;
}

/* Rejection Reason */
.rejection-reason {
    margin-top: 10px;
    padding: 10px;
    background: var(--danger-light);
    border-radius: var(--radius);
    font-size: 12px;
    color: var(--danger-dark);
    border: 1px solid var(--danger);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
}

.empty-state i {
    font-size: 4rem;
    color: var(--dark);
    opacity: 0.2;
    margin-bottom: 20px;
}

.empty-state h5 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--dark);
    opacity: 0.7;
    max-width: 300px;
    margin: 0 auto;
}

/* Modal Styles */
.modal-content {
    border-radius: var(--radius-lg);
    border: none;
    overflow: hidden;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: none;
}

.modal-header.bg-success { background: var(--success) !important; }
.modal-header.bg-danger { background: var(--danger) !important; }

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--border);
    background: var(--light);
}

/* Responsive */
@media (max-width: 768px) {
    .documents-container {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        width: 100%;
        min-width: auto;
    }
    
    .documents-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .document-actions {
        flex-direction: column;
    }
}
</style>
<div class="documents-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-file-alt me-2" style="color: var(--primary);"></i>
                    Vendor Document Approvals
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-store me-2" style="color: var(--primary);"></i>
                    Review and verify vendor documents
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="vendors.php" class="btn btn-outline-primary">
                    <i class="fas fa-store me-2"></i> Vendors
                </a>
                <a href="../system/dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
            <div class="stat-label">Pending Documents</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <?php if ($has_rejection_reason): ?>
        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
        <?php endif; ?>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-store"></i>
            </div>
            <div class="stat-value"><?php echo $vendor_stats['verified_vendors'] ?? 0; ?>/<?php echo $vendor_stats['total_vendors'] ?? 0; ?></div>
            <div class="stat-label">Verified Vendors</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All
                <span class="count"><?php echo $stats['total'] ?? 0; ?></span>
            </a>
            <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Pending
                <span class="count"><?php echo $stats['pending'] ?? 0; ?></span>
            </a>
            <a href="?status=approved" class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Approved
                <span class="count"><?php echo $stats['approved'] ?? 0; ?></span>
            </a>
            <?php if ($has_rejection_reason): ?>
            <a href="?status=rejected" class="filter-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Rejected
                <span class="count"><?php echo $stats['rejected'] ?? 0; ?></span>
            </a>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-3">
            <form method="GET" class="search-box">
                <?php if ($status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                <?php endif; ?>
                <?php if (!empty($type)): ?>
                    <input type="hidden" name="type" value="<?php echo $type; ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search vendors or document numbers..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search me-1"></i> Search</button>
            </form>

            <select class="form-select" style="width: auto;" onchange="window.location.href='?type='+this.value<?php echo $status !== 'all' ? '+\'&status='.$status.'\'' : ''; ?>">
                <option value="">All Types</option>
                <?php foreach ($doc_types as $dt): ?>
                <option value="<?php echo $dt['document_type']; ?>" <?php echo $type == $dt['document_type'] ? 'selected' : ''; ?>>
                    <?php echo ucfirst(str_replace('_', ' ', $dt['document_type'])); ?> (<?php echo $dt['count']; ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Documents Grid -->
    <?php if (empty($documents)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h5>No Documents Found</h5>
            <p>No <?php echo $status; ?> documents to display.</p>
        </div>
    <?php else: ?>
        <div class="documents-grid">
            <?php foreach ($documents as $doc): 
                // Determine document status
                if ($doc['verified'] == 1) {
                    $doc_status = 'approved';
                } elseif ($has_rejection_reason && !empty($doc['rejection_reason'])) {
                    $doc_status = 'rejected';
                } else {
                    $doc_status = 'pending';
                }
                
                $doc_type_class = str_replace('_', '-', $doc['document_type']);
                $doc_type_name = ucfirst(str_replace('_', ' ', $doc['document_type']));
                
                $icon = match($doc['document_type']) {
                    'id_proof' => 'fa-id-card',
                    'address_proof' => 'fa-map-marker-alt',
                    'business_registration' => 'fa-building',
                    'tax_certificate' => 'fa-file-invoice',
                    default => 'fa-file'
                };
            ?>
            <div class="document-card <?php echo $doc_status; ?>">
                <div class="document-header">
                    <div class="document-type <?php echo $doc['document_type']; ?>">
                        <i class="fas <?php echo $icon; ?>"></i>
                        <div>
                            <h6><?php echo $doc_type_name; ?></h6>
                            <small>Uploaded <?php echo date('d M Y', strtotime($doc['created_at'])); ?></small>
                        </div>
                    </div>
                    <span class="status-badge status-<?php echo $doc_status; ?>">
                        <i class="fas fa-<?php 
                            echo $doc_status == 'approved' ? 'check-circle' : 
                                ($doc_status == 'pending' ? 'clock' : 'times-circle'); 
                        ?>"></i>
                        <?php echo ucfirst($doc_status); ?>
                    </span>
                </div>

                <div class="vendor-info">
                    <?php 
                    $avatar = !empty($doc['profile_pic']) ? '../../assets/images/profiles/' . $doc['profile_pic'] : '../../assets/images/avatars/default.png';
                    ?>
                    <img src="<?php echo $avatar; ?>" alt="Vendor" class="vendor-avatar" onerror="this.src='../../assets/images/avatars/default.png';">
                    <div class="vendor-details">
                        <h6><?php echo htmlspecialchars($doc['full_name']); ?></h6>
                        <small><?php echo htmlspecialchars($doc['email']); ?></small>
                    </div>
                </div>

                <div class="document-details">
                    <div class="detail-row">
                        <span class="detail-label">Document Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($doc['document_number'] ?? 'N/A'); ?></span>
                    </div>
                    <?php if (!empty($doc['expiry_date'])): ?>
                    <div class="detail-row">
                        <span class="detail-label">Expiry Date:</span>
                        <span class="detail-value <?php echo strtotime($doc['expiry_date']) < time() ? 'text-danger' : ''; ?>">
                            <?php echo date('d M Y', strtotime($doc['expiry_date'])); ?>
                            <?php if (strtotime($doc['expiry_date']) < time()): ?>
                                <span class="badge bg-danger ms-1">Expired</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="detail-row">
                        <span class="detail-label">Verified Count:</span>
                        <span class="detail-value"><?php echo $doc['verified_count']; ?>/<?php echo $doc['total_count']; ?></span>
                    </div>
                </div>

                <?php if ($doc_status == 'rejected' && $has_rejection_reason && !empty($doc['rejection_reason'])): ?>
                <div class="rejection-reason">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($doc['rejection_reason']); ?>
                </div>
                <?php endif; ?>

                <div class="document-actions">
                    <?php if ($doc_status == 'pending'): ?>
                        <button class="btn-action btn-approve" onclick="approveDocument(<?php echo $doc['id']; ?>)">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn-action btn-reject" onclick="rejectDocument(<?php echo $doc['id']; ?>)">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo SITE_URL; ?>uploads/documents/<?php echo $doc['document_file']; ?>" 
                       target="_blank" class="btn-action btn-view">
                        <i class="fas fa-eye"></i> View
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="document_id" id="approve_id">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Approve Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body text-center">
                    <i class="fas fa-file-alt fa-4x" style="color: var(--success);"></i>
                    <h5 class="mt-3">Confirm Approval</h5>
                    <p class="text-muted">Are you sure you want to approve this document?</p>
                    <p class="small text-muted">This document will be marked as verified and vendor verification status will be updated.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="document_id" id="reject_id">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i> Reject Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x" style="color: var(--danger);"></i>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection (Optional)</label>
                        <textarea name="rejection_reason" class="form-control" rows="4"></textarea>
                        <div class="form-text text-muted">This reason will be shared with the vendor if provided.</div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveDocument(id) {
    document.getElementById('approve_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectDocument(id) {
    document.getElementById('reject_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>