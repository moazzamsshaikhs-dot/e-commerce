<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

$db = getDB();

// Get product if editing
$product = null;
if ($product_id) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = 'Product not found';
        header('Location: ../products.php');
        exit();
    }
}

// Get available categories
$stmt = $db->query("
    SELECT c.*, 
           (SELECT commission_rate FROM vendor_category_commissions 
            WHERE vendor_id = {$vendor_id} AND category_id = c.id) as vendor_commission
    FROM categories c
    WHERE c.is_active = 1
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

// Get pending category usage requests
$stmt = $db->prepare("
    SELECT * FROM category_change_requests 
    WHERE vendor_id = ? AND request_type = 'use_category' AND status = 'pending'
");
$stmt->execute([$vendor_id]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pending_category_ids = array_column($pending_requests, 'category_id');
?>

<div class="category-selection">
    <?php if (!empty($pending_requests)): ?>
    <div class="alert alert-warning mb-4">
        <i class="fas fa-clock me-2"></i>
        You have <?php echo count($pending_requests); ?> pending category approval request(s).
        You can only use approved categories for products.
    </div>
    <?php endif; ?>
    
    <div class="mb-3">
        <label class="form-label fw-bold">Category *</label>
        <select class="form-select" name="category_id" id="categorySelect" required>
            <option value="">Select Category</option>
            <?php foreach($categories as $cat): ?>
                <?php 
                $is_pending = in_array($cat['id'], $pending_category_ids);
                $can_use = $cat['vendor_commission'] !== null || $product && $product['category_id'] == $cat['id'];
                ?>
                <option value="<?php echo $cat['id']; ?>" 
                        data-commission="<?php echo $cat['vendor_commission'] ?? $cat['commission_rate']; ?>"
                        <?php if ($is_pending): ?>
                        class="text-warning"
                        disabled
                        title="Pending approval"
                        <?php endif; ?>
                        <?php echo ($product && $product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                    <?php if (!empty($cat['parent_name'])): ?>
                        (<?php echo htmlspecialchars($cat['parent_name']); ?>)
                    <?php endif; ?>
                    - Commission: <?php echo $cat['vendor_commission'] ?? $cat['commission_rate']; ?>%
                    <?php if ($is_pending): ?>
                        (Pending Approval)
                    <?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="alert alert-info" id="commissionInfo" style="display: none;">
        <i class="fas fa-percentage me-2"></i>
        Commission Rate: <strong><span id="commissionRate">0</span>%</strong>
    </div>
    
    <?php if (!$product || !$product['category_id']): ?>
    <div class="mt-3">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#requestCategoryModal">
            <i class="fas fa-paper-plane me-2"></i> Request New Category Usage
        </button>
    </div>
    <?php endif; ?>
</div>

<!-- Request Category Modal -->
<div class="modal fade" id="requestCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="request-category.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-paper-plane me-2"></i> Request Category Usage
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Request permission to use a category for your products:</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Category</label>
                        <select class="form-select" name="category_id" required>
                            <option value="">Choose category</option>
                            <?php foreach($categories as $cat): ?>
                                <?php if (!in_array($cat['id'], $pending_category_ids)): ?>
                                <option value="<?php echo $cat['id']; ?>">
                                    <?php echo htmlspecialchars($cat['name']); ?> 
                                    (Commission: <?php echo $cat['commission_rate']; ?>%)
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        Your request will be reviewed by admin. You'll be notified once approved.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i> Submit Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('categorySelect')?.addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const commission = selected.dataset.commission;
    
    if (commission) {
        document.getElementById('commissionRate').textContent = commission;
        document.getElementById('commissionInfo').style.display = 'block';
    } else {
        document.getElementById('commissionInfo').style.display = 'none';
    }
});

// Trigger on page load if category selected
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('categorySelect');
    if (select.value) {
        const event = new Event('change');
        select.dispatchEvent(event);
    }
});
</script>