<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Vendor Category Approvals';
require_once '../includes/header.php';

$db = getDB();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selection_id = (int)($_POST['selection_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($selection_id && in_array($action, ['approve', 'reject'])) {
        try {
            $db->beginTransaction();
            
            // Get selection details
            $stmt = $db->prepare("
                SELECT vcs.*, c.name as category_name, c.commission_rate as default_rate, 
                       c.slug as category_slug,
                       u.full_name as vendor_name, u.email as vendor_email, u.id as vendor_id,
                       u.username, u.profile_pic
                FROM vendor_categories_selected vcs
                JOIN categories c ON vcs.category_id = c.id
                JOIN users u ON vcs.vendor_id = u.id
                WHERE vcs.id = ?
            ");
            $stmt->execute([$selection_id]);
            $selection = $stmt->fetch();
            
            if (!$selection) {
                throw new Exception('Selection not found');
            }
            
            if ($action === 'approve') {
                // Approve the selection in vendor_categories_selected
                $stmt = $db->prepare("
                    UPDATE vendor_categories_selected 
                    SET status = 'approved', approved_by = ?, approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $selection_id]);
                
                // Insert into vendor_categories
                $stmt = $db->prepare("
                    INSERT INTO vendor_categories 
                    (vendor_id, category_id, name, slug, commission_rate, approval_status, approved_by, approved_at, created_at)
                    VALUES (?, ?, ?, ?, ?, 'approved', ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        approval_status = 'approved',
                        commission_rate = VALUES(commission_rate),
                        approved_by = VALUES(approved_by),
                        approved_at = NOW(),
                        rejection_reason = NULL
                ");
                
                $stmt->execute([
                    $selection['vendor_id'],
                    $selection['category_id'],
                    $selection['category_name'],
                    $selection['category_slug'],
                    $selection['commission_rate'],
                    $_SESSION['user_id']
                ]);
                
                // Update user's vendor_category if first approved category
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM vendor_categories 
                    WHERE vendor_id = ? AND approval_status = 'approved'
                ");
                $stmt->execute([$selection['vendor_id']]);
                $approved_count = $stmt->fetchColumn();
                
                if ($approved_count == 1) {
                    $stmt = $db->prepare("
                        UPDATE users SET vendor_category = ? WHERE id = ?
                    ");
                    $stmt->execute([$selection['category_id'], $selection['vendor_id']]);
                }
                
                // Create notification
                $message = "🎉 Your category <strong>'{$selection['category_name']}'</strong> has been approved!";
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Category Approved ✅', ?, 'success', NOW())
                ");
                $stmt->execute([$selection['vendor_id'], $message]);
                
                $_SESSION['success'] = "✅ Category approved for {$selection['vendor_name']}";
                
            } else {
                // Reject the selection
                $stmt = $db->prepare("
                    UPDATE vendor_categories_selected 
                    SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $rejection_reason, $selection_id]);
                
                // Insert into vendor_categories with rejection
                $stmt = $db->prepare("
                    INSERT INTO vendor_categories 
                    (vendor_id, category_id, name, slug, commission_rate, approval_status, rejection_reason, approved_by, approved_at, created_at)
                    VALUES (?, ?, ?, ?, ?, 'rejected', ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        approval_status = 'rejected',
                        rejection_reason = VALUES(rejection_reason),
                        approved_by = VALUES(approved_by),
                        approved_at = NOW()
                ");
                
                $stmt->execute([
                    $selection['vendor_id'],
                    $selection['category_id'],
                    $selection['category_name'],
                    $selection['category_slug'],
                    $selection['commission_rate'],
                    $rejection_reason,
                    $_SESSION['user_id']
                ]);
                
                // Create notification
                $message = "❌ Your category <strong>'{$selection['category_name']}'</strong> has been rejected.<br>
                           <strong>Reason:</strong> {$rejection_reason}";
                $stmt = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    VALUES (?, 'Category Rejected ❌', ?, 'error', NOW())
                ");
                $stmt->execute([$selection['vendor_id'], $message]);
                
                $_SESSION['success'] = "❌ Category rejected";
            }
            
            $db->commit();
            
        } catch(Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Error: ' . $e->getMessage();
            error_log("Approval error: " . $e->getMessage());
        }
        
        redirect('approvals.php');
        exit();
    }
}

// Get statistics
$stats = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(*) as total_count
    FROM vendor_categories_selected
")->fetch();

// Get vendor categories statistics
$vendor_cat_stats = $db->query("
    SELECT 
        COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) as active_vendor_categories,
        COUNT(CASE WHEN approval_status = 'rejected' THEN 1 END) as rejected_vendor_categories,
        COUNT(DISTINCT vendor_id) as vendors_with_categories
    FROM vendor_categories
")->fetch();

// Get pending selections
$pending = $db->query("
    SELECT vcs.*, 
           c.name as category_name,
           c.commission_rate as default_rate,
           c.slug as category_slug,
           u.full_name as vendor_name,
           u.email as vendor_email,
           u.username,
           u.profile_pic,
           (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as vendor_products,
           (SELECT COUNT(*) FROM vendor_categories WHERE vendor_id = u.id AND approval_status = 'approved') as vendor_active_categories
    FROM vendor_categories_selected vcs
    JOIN categories c ON vcs.category_id = c.id
    JOIN users u ON vcs.vendor_id = u.id
    WHERE vcs.status = 'pending'
    ORDER BY vcs.created_at ASC
")->fetchAll();

// Get history with search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$history_query = "
    SELECT vcs.*, 
           c.name as category_name,
           c.commission_rate,
           u.full_name as vendor_name,
           u.email as vendor_email,
           a.full_name as approved_by_name,
           vc.approval_status as vendor_cat_status
    FROM vendor_categories_selected vcs
    JOIN categories c ON vcs.category_id = c.id
    JOIN users u ON vcs.vendor_id = u.id
    LEFT JOIN users a ON vcs.approved_by = a.id
    LEFT JOIN vendor_categories vc ON vc.vendor_id = u.id AND vc.category_id = c.id
    WHERE vcs.status IN ('approved', 'rejected')
";

if (!empty($search)) {
    $history_query .= " AND (u.full_name LIKE :search OR u.email LIKE :search OR c.name LIKE :search)";
}

$history_query .= " ORDER BY vcs.updated_at DESC LIMIT 20";

$stmt = $db->prepare($history_query);
if (!empty($search)) {
    $search_term = "%$search%";
    $stmt->bindParam(':search', $search_term);
}
$stmt->execute();
$history = $stmt->fetchAll();

// Helper function for time elapsed
function timeElapsedString($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $weeks = floor($diff->days / 7);
    $days = $diff->days % 7;
    
    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($weeks > 0) {
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    }
    if ($days > 0) {
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}
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

.approvals-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Page Header - Same as products.php */
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

/* Stats Cards - Same as products.php */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.stat-card.pending { border-left-color: var(--warning); }
.stat-card.approved { border-left-color: var(--success); }
.stat-card.rejected { border-left-color: var(--danger); }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-card.pending .stat-icon { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.stat-card.approved .stat-icon { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.stat-card.rejected .stat-icon { background: rgba(239, 71, 111, 0.1); color: var(--danger); }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
    line-height: 1.2;
    margin-bottom: 4px;
}

.stat-label {
    color: #6c757d;
    font-size: 13px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Filter Tabs - Same as products.php */
.filter-tabs {
    background: white;
    border-radius: 15px;
    padding: 15px;
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 14px;
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
    margin-left: 8px;
    font-size: 12px;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

/* Search Box - Same as products.php */
.search-box {
    background: white;
    border-radius: 15px;
    padding: 5px;
    display: flex;
    align-items: center;
    border: 1px solid var(--border);
    min-width: 300px;
}

.search-box input {
    border: none;
    padding: 10px 15px;
    flex: 1;
    border-radius: 12px;
}

.search-box input:focus {
    outline: none;
}

.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 12px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.search-box button:hover {
    background: #3651c4;
}

/* Pending Cards Grid */
.pending-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.pending-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    transition: all 0.3s ease;
    border: 1px solid var(--border);
    border-left: 4px solid var(--warning);
}

.pending-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(67, 97, 238, 0.1);
}

.pending-card-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.vendor-avatar {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
    margin-right: 15px;
    border: 2px solid white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.vendor-info {
    flex: 1;
}

.vendor-info h5 {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 4px;
}

.vendor-meta {
    font-size: 12px;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.vendor-badge {
    background: var(--light);
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 11px;
    color: #6c757d;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

/* Category Detail */
.category-detail {
    background: var(--light);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
}

.category-name {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.category-name i {
    color: var(--primary);
}

.category-meta {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.meta-item {
    background: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    color: #6c757d;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.meta-item i {
    color: var(--primary);
}

.meta-item strong {
    color: var(--dark);
    margin-left: 3px;
}

.request-time {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: #6c757d;
    padding-top: 8px;
    border-top: 1px dashed var(--border);
}

.request-time i {
    color: var(--warning);
}

/* Action Buttons - Same as products.php */
.pending-card-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-approve {
    flex: 1;
    background: var(--success);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
}

.btn-approve:hover {
    background: #05b585;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(6, 214, 160, 0.3);
}

.btn-reject {
    flex: 1;
    background: white;
    color: var(--danger);
    border: 2px solid var(--danger);
    padding: 10px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 13px;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
}

.btn-reject:hover {
    background: var(--danger);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(239, 71, 111, 0.3);
}

/* History Card */
.history-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    margin-top: 30px;
}

.history-header {
    padding: 20px 25px;
    background: var(--dark);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.history-header h5 {
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.history-header h5 i {
    color: var(--info);
}

.history-search {
    position: relative;
    min-width: 280px;
}

.history-search i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255,255,255,0.7);
}

.history-search input {
    width: 100%;
    padding: 8px 15px 8px 40px;
    border-radius: 30px;
    border: none;
    background: rgba(255,255,255,0.2);
    color: white;
}

.history-search input::placeholder {
    color: rgba(255,255,255,0.7);
}

.history-search input:focus {
    outline: none;
    background: rgba(255,255,255,0.3);
}

/* Table Styles */
.table-responsive {
    padding: 20px 25px 25px 25px;
    overflow-x: auto;
}

.history-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.history-table th {
    padding: 12px 15px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    border-bottom: 2px solid var(--border);
}

.history-table td {
    padding: 15px;
    background: var(--light);
    border-radius: 10px;
    transition: all 0.3s ease;
    font-size: 13px;
}

.history-table tr:hover td {
    background: white;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    transform: scale(1.01);
}

.vendor-cell .vendor-name {
    font-weight: 600;
    color: var(--dark);
}

.vendor-cell .vendor-email {
    font-size: 11px;
    color: #6c757d;
}

/* Status Badges */
.status-badge {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.status-badge.approved {
    background: rgba(6, 214, 160, 0.1);
    color: var(--success);
}

.status-badge.rejected {
    background: rgba(239, 71, 111, 0.1);
    color: var(--danger);
}

/* Badges */
.badge {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 500;
}

.bg-info { background: rgba(76, 201, 240, 0.1); color: var(--info); }
.bg-success { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.bg-danger { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
.bg-light { background: var(--light); color: #6c757d; }

/* Empty State */
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
    max-width: 300px;
    margin: 0 auto;
}

/* Modal Styles */
.modal-content {
    border-radius: 20px;
    border: none;
}

.modal-header {
    border-radius: 20px 20px 0 0;
    padding: 20px 25px;
}

.modal-header.bg-gradient-success {
    background: var(--success) !important;
}

.modal-header.bg-gradient-danger {
    background: var(--danger) !important;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    border-top: 1px solid var(--border);
    padding: 20px 25px;
}

/* Responsive */
@media (max-width: 768px) {
    .approvals-container {
        padding: 20px;
    }
    
    .pending-grid {
        grid-template-columns: 1fr;
    }
    
    .history-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .history-search {
        width: 100%;
    }
    
    .stats-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .pending-card-actions {
        flex-direction: column;
    }
}
</style>

<div class="approvals-container">
    <!-- Page Header - Same as products.php -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-check-double me-2 text-primary"></i>
                    Category Approvals
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-tags me-2"></i>
                    Review and manage vendor category selection requests
                </p>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards - Same as products.php -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending_count']; ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
        </div>
        
        <div class="stat-card approved">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['approved_count']; ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
        
        <div class="stat-card rejected">
            <div class="stat-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['rejected_count']; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>

    <!-- Filters and Search - Same as products.php -->
    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-4">
        <div class="filter-tabs">
            <a href="approvals.php" class="filter-tab active">
                <i class="fas fa-list me-1"></i> All
                <span class="count"><?php echo $stats['total_count']; ?></span>
            </a>
            <a href="approvals.php?filter=pending" class="filter-tab">
                <i class="fas fa-clock me-1"></i> Pending
                <span class="count"><?php echo $stats['pending_count']; ?></span>
            </a>
            <a href="approvals.php?filter=approved" class="filter-tab">
                <i class="fas fa-check-circle me-1"></i> Approved
                <span class="count"><?php echo $stats['approved_count']; ?></span>
            </a>
            <a href="approvals.php?filter=rejected" class="filter-tab">
                <i class="fas fa-times-circle me-1"></i> Rejected
                <span class="count"><?php echo $stats['rejected_count']; ?></span>
            </a>
        </div>

        <form method="GET" class="search-box">
            <input type="text" name="search" placeholder="Search vendors or categories..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit">
                <i class="fas fa-search me-1"></i> Search
            </button>
        </form>
    </div>

    <!-- Pending Approvals Section -->
    <?php if (empty($pending)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <h5>No Pending Approvals</h5>
            <p>All vendor category requests have been reviewed.</p>
        </div>
    <?php else: ?>
        <div class="pending-grid">
            <?php foreach($pending as $index => $p): ?>
            <div class="pending-card" data-id="<?php echo $p['id']; ?>">
                <div class="pending-card-header">
                    <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo !empty($p['profile_pic']) ? $p['profile_pic'] : 'default.png'; ?>" 
                         alt="Vendor" class="vendor-avatar"
                         onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png';">
                    <div class="vendor-info">
                        <h5><?php echo htmlspecialchars($p['vendor_name']); ?></h5>
                        <div class="vendor-meta">
                            <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($p['vendor_email']); ?></span>
                            <span class="vendor-badge">
                                <i class="fas fa-box"></i> <?php echo $p['vendor_products']; ?> products
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="pending-card-body">
                    <div class="category-detail">
                        <div class="category-name">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($p['category_name']); ?>
                        </div>
                        <div class="category-meta">
                            <span class="meta-item">
                                <i class="fas fa-percentage"></i> Commission: 
                                <strong><?php echo $p['commission_rate']; ?>%</strong>
                            </span>
                            <span class="meta-item">
                                <i class="fas fa-code"></i> Slug: 
                                <strong><?php echo $p['category_slug']; ?></strong>
                            </span>
                        </div>
                        <div class="request-time">
                            <i class="fas fa-hourglass-half"></i>
                            <span>Requested <?php echo timeElapsedString($p['created_at']); ?></span>
                            <span class="ms-auto">
                                <i class="fas fa-calendar-alt"></i> <?php echo date('d M', strtotime($p['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="pending-card-actions">
                    <button class="btn-approve" onclick="approve(<?php echo $p['id']; ?>)">
                        <i class="fas fa-check-circle"></i> Approve
                    </button>
                    <button class="btn-reject" onclick="reject(<?php echo $p['id']; ?>)">
                        <i class="fas fa-times-circle"></i> Reject
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- History Section -->
    <div class="history-card">
        <div class="history-header">
            <h5>
                <i class="fas fa-history"></i>
                Recent Activity
            </h5>
            <form method="GET" class="history-search">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search vendors or categories..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </form>
        </div>
        
        <div class="table-responsive">
            <?php if (empty($history)): ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h5>No History Found</h5>
                    <p>No approval history matches your search.</p>
                </div>
            <?php else: ?>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Category</th>
                            <th>Commission</th>
                            <th>Status</th>
                            <th>Vendor Cat</th>
                            <th>Processed</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <td>
                                <div class="vendor-cell">
                                    <div class="vendor-details">
                                        <div class="vendor-name"><?php echo htmlspecialchars($h['vendor_name']); ?></div>
                                        <div class="vendor-email"><?php echo htmlspecialchars($h['vendor_email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($h['category_name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-info text-white"><?php echo $h['commission_rate']; ?>%</span>
                            </td>
                            <td>
                                <?php if ($h['status'] == 'approved'): ?>
                                    <span class="status-badge approved">
                                        <i class="fas fa-check-circle"></i> Approved
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge rejected">
                                        <i class="fas fa-times-circle"></i> Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($h['vendor_cat_status'])): ?>
                                    <?php if ($h['vendor_cat_status'] == 'approved'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($h['vendor_cat_status'] == 'rejected'): ?>
                                        <span class="badge bg-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-light">Pending</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-light">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-calendar-alt me-1 text-muted"></i>
                                <?php echo date('d M', strtotime($h['updated_at'])); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($h['approved_by_name'] ?? 'System'); ?>
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
                <input type="hidden" name="selection_id" id="approve_id">
                <input type="hidden" name="action" value="approve">
                
                <div class="modal-header bg-gradient-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i>
                        Approve Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5 class="mb-3">Confirm Approval</h5>
                    <p class="text-muted mb-0">
                        Are you sure you want to approve this category?<br>
                        The vendor will be notified immediately.
                    </p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle me-2"></i> Approve Category
                    </button>
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
                <input type="hidden" name="selection_id" id="reject_id">
                <input type="hidden" name="action" value="reject">
                
                <div class="modal-header bg-gradient-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i>
                        Reject Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-times-circle fa-4x text-danger"></i>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Rejection</label>
                        <textarea name="rejection_reason" class="form-control" rows="4" 
                                  placeholder="Please explain why this category is being rejected..." required></textarea>
                        <div class="form-text text-muted">
                            This reason will be shared with the vendor.
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle me-2"></i> Reject Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approve(id) {
    document.getElementById('approve_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function reject(id) {
    document.getElementById('reject_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);

// Search with debounce
let searchTimeout;
document.querySelector('.history-search input')?.addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});

// Add loading state to buttons
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
            btn.disabled = true;
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>