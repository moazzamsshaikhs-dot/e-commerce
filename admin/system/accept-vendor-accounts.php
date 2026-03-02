<?php
// admin/system/accept-vendor-accounts.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

// Special check for system administrator
requireSystemAdmin();

$page_title = 'Accept Vendor Accounts';
require_once '../includes/header.php';

$db = getDB();
$message = '';
$message_type = 'success';

// Handle export requests
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $status = $_GET['status'] ?? 'pending';
    
    // Get data for export
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.vendor_status,
            u.vendor_category,
            c.name as category_name,
            u.vendor_bio,
            u.created_at as registered_date,
            (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as total_products,
            (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id) as total_documents,
            (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id AND verified = 1) as verified_documents
        FROM users u
        LEFT JOIN categories c ON u.vendor_category = c.id
        WHERE u.user_type = 'vendor' AND u.vendor_status = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$status]);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'pdf') {
        // PDF Export
        require_once dirname(__DIR__, 2) . '/vendor/autoload.php'; // If using TCPDF or similar
        
        // Create new PDF document
        $pdf = new dompdf(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('E-Commerce System');
        $pdf->SetAuthor('System Administrator');
        $pdf->SetTitle(ucfirst($status) . ' Vendor Accounts Report');
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, ucfirst($status) . ' Vendor Accounts Report', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Date
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 10, 'Generated on: ' . date('d M Y h:i A'), 0, 1, 'R');
        $pdf->Ln(5);
        
        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(67, 97, 238);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(10, 8, '#', 1, 0, 'C', true);
        $pdf->Cell(50, 8, 'Vendor Name', 1, 0, 'L', true);
        $pdf->Cell(50, 8, 'Email', 1, 0, 'L', true);
        $pdf->Cell(30, 8, 'Category', 1, 0, 'L', true);
        $pdf->Cell(20, 8, 'Products', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Registered', 1, 1, 'C', true);
        
        // Table data
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(245, 245, 245);
        
        $fill = false;
        $count = 1;
        foreach ($vendors as $vendor) {
            $pdf->Cell(10, 8, $count++, 1, 0, 'C', $fill);
            $pdf->Cell(50, 8, substr($vendor['full_name'] ?? $vendor['username'], 0, 25), 1, 0, 'L', $fill);
            $pdf->Cell(50, 8, substr($vendor['email'], 0, 25), 1, 0, 'L', $fill);
            $pdf->Cell(30, 8, substr($vendor['category_name'] ?? 'N/A', 0, 15), 1, 0, 'L', $fill);
            $pdf->Cell(20, 8, $vendor['total_products'], 1, 0, 'C', $fill);
            $pdf->Cell(30, 8, date('d M Y', strtotime($vendor['registered_date'])), 1, 1, 'C', $fill);
            $fill = !$fill;
        }
        
        // Output PDF
        $pdf->Output(ucfirst($status) . '_Vendors_' . date('Y-m-d') . '.pdf', 'D');
        exit();
        
    } elseif ($format === 'excel') {
        // Excel Export (CSV format)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . ucfirst($status) . '_Vendors_' . date('Y-m-d') . '.csv');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, [
            'ID',
            'Username',
            'Full Name',
            'Email',
            'Phone',
            'Status',
            'Category',
            'Products',
            'Documents',
            'Verified Docs',
            'Registered Date'
        ]);
        
        // CSV Data
        foreach ($vendors as $vendor) {
            fputcsv($output, [
                $vendor['id'],
                $vendor['username'],
                $vendor['full_name'],
                $vendor['email'],
                $vendor['phone'],
                $vendor['vendor_status'],
                $vendor['category_name'] ?? 'N/A',
                $vendor['total_products'],
                $vendor['total_documents'],
                $vendor['verified_documents'],
                date('d M Y', strtotime($vendor['registered_date']))
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Handle vendor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($vendor_id && in_array($action, ['approve', 'reject', 'suspend'])) {
        try {
            $db->beginTransaction();
            
            // Get vendor details
            $stmt = $db->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
            $stmt->execute([$vendor_id]);
            $vendor = $stmt->fetch();
            
            if (!$vendor) {
                throw new Exception('Vendor not found');
            }
            
            $new_status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'suspended');
            
            // Update vendor status
            $stmt = $db->prepare("UPDATE users SET vendor_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $vendor_id]);
            
            // Create notification
            if ($action === 'approve') {
                $message = "🎉 Congratulations! Your vendor account has been approved. You can now start adding products.";
            } elseif ($action === 'reject') {
                $message = " Your vendor account has been rejected.<br><strong>Reason:</strong> {$rejection_reason}";
            } else {
                $message = " Your vendor account has been suspended.<br><strong>Reason:</strong> {$rejection_reason}";
            }
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, 'Account Status Update', ?, ?, NOW())
            ");
            $type = $action === 'approve' ? 'success' : ($action === 'reject' ? 'error' : 'warning');
            $stmt->execute([$vendor_id, $message, $type]);
            
            // Log activity
            logUserActivity($_SESSION['user_id'], 'vendor_status_change', 
                ucfirst($action) . " vendor account: {$vendor['username']} (ID: {$vendor_id})");
            
            $db->commit();
            
            $_SESSION['success'] = "Vendor account " . ($action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'suspended')) . " successfully!";
            
        } catch(Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        redirect('accept-vendor-accounts.php');
        exit();
    }
}

// Get filter
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        u.*,
        c.name as category_name,
        (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as total_products,
        (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id) as total_documents,
        (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id AND verified = 1) as verified_documents,
        (SELECT GROUP_CONCAT(document_type SEPARATOR ', ') FROM vendor_documents WHERE vendor_id = u.id) as document_types
    FROM users u
    LEFT JOIN categories c ON u.vendor_category = c.id
    WHERE u.user_type = 'vendor'
";

$params = [];

if ($status !== 'all') {
    $query .= " AND u.vendor_status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
$stats_query = $db->query("
    SELECT 
        COUNT(CASE WHEN vendor_status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN vendor_status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN vendor_status = 'rejected' THEN 1 END) as rejected,
        COUNT(CASE WHEN vendor_status = 'suspended' THEN 1 END) as suspended,
        COUNT(*) as total
    FROM users WHERE user_type = 'vendor'
");
$stats = $stats_query->fetch(PDO::FETCH_ASSOC);
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
    --border: #edf2f9;
}

.vendor-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--success), var(--warning), var(--danger));
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-card.pending { border-left-color: var(--warning); }
.stat-card.approved { border-left-color: var(--success); }
.stat-card.rejected { border-left-color: var(--danger); }
.stat-card.suspended { border-left-color: var(--dark); }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 15px;
}

.stat-card.pending .stat-icon { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.stat-card.approved .stat-icon { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.stat-card.rejected .stat-icon { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
.stat-card.suspended .stat-icon { background: rgba(43, 45, 66, 0.1); color: var(--dark); }

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-size: 14px;
}

.filter-bar {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    justify-content: space-between;
}

.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    color: #6c757d;
    background: var(--light);
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-tab:hover {
    background: var(--primary);
    color: white;
}

.filter-tab.active {
    background: var(--primary);
    color: white;
}

.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

.search-box {
    display: flex;
    align-items: center;
    background: var(--light);
    border-radius: 30px;
    padding: 4px;
    min-width: 280px;
}

.search-box input {
    border: none;
    background: transparent;
    padding: 8px 16px;
    flex: 1;
    font-size: 14px;
}

.search-box input:focus {
    outline: none;
}

.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.search-box button:hover {
    background: #3651c4;
}

.export-buttons {
    display: flex;
    gap: 10px;
}

.btn-export {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-pdf {
    background: #dc2626;
    color: white;
}

.btn-excel {
    background: #059669;
    color: white;
}

.btn-export:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

.vendors-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.table-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.table-responsive {
    padding: 0 25px 25px 25px;
    overflow-x: auto;
}

.vendors-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.vendors-table th {
    padding: 12px 15px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    border-bottom: 2px solid var(--border);
}

.vendors-table td {
    padding: 15px;
    background: var(--light);
    border-radius: 10px;
    transition: all 0.3s ease;
    font-size: 13px;
}

.vendors-table tr:hover td {
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transform: scale(1.01);
}

.vendor-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.vendor-avatar {
    width: 45px;
    height: 45px;
    border-radius: 10px;
    object-fit: cover;
    background: var(--light);
}

.vendor-details {
    line-height: 1.4;
}

.vendor-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 3px;
}

.vendor-email {
    font-size: 11px;
    color: #6c757d;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-pending {
    background: rgba(255, 183, 3, 0.1);
    color: var(--warning);
}

.status-approved {
    background: rgba(6, 214, 160, 0.1);
    color: var(--success);
}

.status-rejected {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger);
}

.status-suspended {
    background: rgba(43, 45, 66, 0.1);
    color: var(--dark);
}

.document-badge {
    background: white;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 10px;
    color: #6c757d;
    display: inline-block;
}

.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.btn-approve {
    background: var(--success);
    color: white;
}

.btn-reject {
    background: var(--danger);
    color: white;
}

.btn-suspend {
    background: var(--dark);
    color: white;
}

.btn-view {
    background: var(--primary);
    color: white;
}

.btn-action:hover {
    transform: translateY(-2px);
    filter: brightness(110%);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 60px;
    color: #dee2e6;
    margin-bottom: 20px;
}

.empty-state h5 {
    color: var(--dark);
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
}
</style>

<div class="vendor-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-user-check me-2 text-primary"></i>
                    Accept Vendor Accounts
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-store me-2"></i>
                    Review and manage vendor registration requests
                </p>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
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
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
        <div class="stat-card suspended">
            <div class="stat-icon">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-value"><?php echo $stats['suspended'] ?? 0; ?></div>
            <div class="stat-label">Suspended</div>
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
            <a href="?status=rejected" class="filter-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Rejected
                <span class="count"><?php echo $stats['rejected'] ?? 0; ?></span>
            </a>
            <a href="?status=suspended" class="filter-tab <?php echo $status === 'suspended' ? 'active' : ''; ?>">
                <i class="fas fa-ban"></i> Suspended
                <span class="count"><?php echo $stats['suspended'] ?? 0; ?></span>
            </a>
        </div>

        <div class="d-flex gap-3">
            <form method="GET" class="search-box">
                <?php if ($status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search vendors..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search me-1"></i> Search</button>
            </form>

            <div class="export-buttons">
                <a href="?export=pdf&status=<?php echo $status; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn-export btn-pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="?export=excel&status=<?php echo $status; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn-export btn-excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Vendors Table -->
    <div class="vendors-card">
        <div class="table-header">
            <h5>
                <i class="fas fa-store me-2 text-primary"></i>
                <?php echo ucfirst($status); ?> Vendor Accounts
                <span class="badge bg-secondary ms-2"><?php echo count($vendors); ?></span>
            </h5>
        </div>

        <div class="table-responsive">
            <?php if (empty($vendors)): ?>
                <div class="empty-state">
                    <i class="fas fa-store-alt"></i>
                    <h5>No Vendors Found</h5>
                    <p>No <?php echo $status; ?> vendor accounts to display.</p>
                </div>
            <?php else: ?>
                <table class="vendors-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Category</th>
                            <th>Products</th>
                            <th>Documents</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td>
                                <div class="vendor-info">
                                    <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $vendor['profile_pic'] ?? 'default.png'; ?>" 
                                         class="vendor-avatar" onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png';">
                                    <div class="vendor-details">
                                        <div class="vendor-name"><?php echo htmlspecialchars($vendor['full_name'] ?? $vendor['username']); ?></div>
                                        <div class="vendor-email"><?php echo htmlspecialchars($vendor['email']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($vendor['phone'] ?? 'No phone'); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($vendor['category_name'] ?? 'Not set'); ?></span>
                            </td>
                            <td>
                                <span class="fw-bold"><?php echo $vendor['total_products']; ?></span>
                            </td>
                            <td>
                                <span class="document-badge">
                                    <i class="fas fa-file-alt me-1"></i>
                                    <?php echo $vendor['verified_documents']; ?>/<?php echo $vendor['total_documents']; ?> verified
                                </span>
                                <?php if (!empty($vendor['document_types'])): ?>
                                    <br><small class="text-muted"><?php echo $vendor['document_types']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d M Y', strtotime($vendor['created_at'])); ?>
                                <br><small class="text-muted"><?php echo timeElapsedString($vendor['created_at']); ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $vendor['vendor_status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $vendor['vendor_status'] == 'approved' ? 'check-circle' : 
                                            ($vendor['vendor_status'] == 'pending' ? 'clock' : 
                                            ($vendor['vendor_status'] == 'rejected' ? 'times-circle' : 'ban')); 
                                    ?>"></i>
                                    <?php echo ucfirst($vendor['vendor_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($vendor['vendor_status'] == 'pending'): ?>
                                        <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn-action btn-reject" onclick="rejectVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($vendor['vendor_status'] == 'approved'): ?>
                                        <button class="btn-action btn-suspend" onclick="suspendVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-ban"></i> Suspend
                                        </button>
                                    <?php elseif ($vendor['vendor_status'] == 'suspended'): ?>
                                        <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-check"></i> Reactivate
                                        </button>
                                    <?php endif; ?>
                                    <a href="../vendors/view-vendor.php?id=<?php echo $vendor['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="vendor_id" id="approve_id">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Approve Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5>Confirm Approval</h5>
                    <p>Are you sure you want to approve this vendor account?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Vendor</button>
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
                <input type="hidden" name="vendor_id" id="reject_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i> Reject Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-times-circle fa-4x text-danger"></i>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection</label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div class="modal fade" id="suspendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="vendor_id" id="suspend_id">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i> Suspend Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning"></i>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Suspension</label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Suspend Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveVendor(id) {
    document.getElementById('approve_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectVendor(id) {
    document.getElementById('reject_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function suspendVendor(id) {
    document.getElementById('suspend_id').value = id;
    new bootstrap.Modal(document.getElementById('suspendModal')).show();
}

function timeElapsedString($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}
</script>

<?php require_once '../includes/footer.php'; ?>