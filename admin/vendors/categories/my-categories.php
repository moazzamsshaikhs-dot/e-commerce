<?php
// admin/vendors/categories/my-categories.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'My Categories';
require_once '../../includes/header.php';

$vendor_id = $_SESSION['user_id'];

try {
    $db = getDB();
    
    // Get vendor's submitted categories
    $stmt = $db->prepare("
        SELECT * FROM vendor_categories 
        WHERE vendor_id = ? 
        ORDER BY 
            CASE approval_status
                WHEN 'pending' THEN 1
                WHEN 'approved' THEN 2
                WHEN 'rejected' THEN 3
            END,
            created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats
    $pending_count = count(array_filter($categories, fn($c) => $c['approval_status'] == 'pending'));
    $approved_count = count(array_filter($categories, fn($c) => $c['approval_status'] == 'approved'));
    $rejected_count = count(array_filter($categories, fn($c) => $c['approval_status'] == 'rejected'));
    

    // Add this at the top after getting vendor_id
// Mark category-related notifications as read when viewed
try {
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND title LIKE '%Category%' AND is_read = 0
    ");
    $stmt->execute([$vendor_id]);
} catch(Exception $e) {}
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading categories: ' . $e->getMessage();
    $categories = [];
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
}

.categories-container {
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
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    border-left: 4px solid transparent;
}

.stat-card.pending { border-left-color: var(--warning); }
.stat-card.approved { border-left-color: var(--success); }
.stat-card.rejected { border-left-color: var(--danger); }

.stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--dark);
}

.stat-label {
    color: #6c757d;
    font-size: 13px;
}

.category-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
}

.category-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.category-card.pending { border-left-color: var(--warning); }
.category-card.approved { border-left-color: var(--success); }
.category-card.rejected { border-left-color: var(--danger); }

.status-badge {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 12px;
    font-weight: 500;
}

.status-pending { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
.status-approved { background: rgba(6, 214, 160, 0.1); color: var(--success); }
.status-rejected { background: rgba(239, 71, 111, 0.1); color: var(--danger); }

.rejection-reason {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 12px;
    margin-top: 10px;
    font-size: 13px;
    border-left: 3px solid var(--danger);
}
</style>

<div class="categories-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-tags me-2 text-primary"></i>
                    My Categories
                </h1>
                <p class="text-muted mb-0">Manage your submitted categories</p>
            </div>
            <a href="submit-category.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>
                Submit New Category
            </a>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-value"><?php echo $pending_count; ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card approved">
            <div class="stat-value"><?php echo $approved_count; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-value"><?php echo $rejected_count; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
    </div>

    <!-- Categories List -->
    <?php if (empty($categories)): ?>
        <div class="text-center py-5">
            <i class="fas fa-tags fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No categories submitted yet</h5>
            <p class="text-muted">Submit your first category to get started</p>
            <a href="submit-category.php" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i> Submit New Category
            </a>
        </div>
    <?php else: ?>
        <?php foreach($categories as $cat): ?>
        <div class="category-card <?php echo $cat['approval_status']; ?>">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h5 class="mb-1">
                        <?php if (!empty($cat['icon'])): ?>
                        <i class="fas <?php echo htmlspecialchars($cat['icon']); ?> me-2"></i>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </h5>
                    <p class="text-muted small mb-2">Slug: <?php echo htmlspecialchars($cat['slug']); ?></p>
                </div>
                <span class="status-badge status-<?php echo $cat['approval_status']; ?>">
                    <i class="fas fa-<?php 
                        echo $cat['approval_status'] == 'pending' ? 'clock' : 
                             ($cat['approval_status'] == 'approved' ? 'check-circle' : 'times-circle'); 
                    ?> me-1"></i>
                    <?php echo ucfirst($cat['approval_status']); ?>
                </span>
            </div>
            
            <p class="mb-2"><?php echo nl2br(htmlspecialchars($cat['description'] ?? 'No description')); ?></p>
            
            <div class="d-flex gap-4 mt-3">
                <small class="text-muted">
                    <i class="fas fa-percentage me-1"></i>
                    Commission: <?php echo $cat['commission_rate']; ?>%
                </small>
                <small class="text-muted">
                    <i class="fas fa-calendar me-1"></i>
                    Submitted: <?php echo date('d M Y', strtotime($cat['created_at'])); ?>
                </small>
            </div>
            
            <?php if ($cat['approval_status'] == 'rejected' && !empty($cat['rejection_reason'])): ?>
            <div class="rejection-reason">
                <i class="fas fa-exclamation-circle text-danger me-2"></i>
                <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($cat['rejection_reason']); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($cat['approval_status'] == 'approved' && !empty($cat['approved_at'])): ?>
            <div class="mt-2 text-success small">
                <i class="fas fa-check-circle me-1"></i>
                Approved on <?php echo date('d M Y, h:i A', strtotime($cat['approved_at'])); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once '../../../includes/footer.php'; ?>