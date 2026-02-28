<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Categories Management';
require_once '../includes/header.php';

$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_category') {
        // Add new category
        $name = sanitize($_POST['name'] ?? '');
        $slug = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $commission_rate = (float)($_POST['commission_rate'] ?? 0);
        $meta_title = sanitize($_POST['meta_title'] ?? '');
        $meta_description = sanitize($_POST['meta_description'] ?? '');
        
        if (empty($name)) {
            $_SESSION['error'] = 'Category name is required';
        } elseif (empty($slug)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }
        
        if (empty($_SESSION['error'])) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO categories (name, slug, description, parent_id, commission_rate, 
                                          meta_title, meta_description, approval_status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', NOW())
                ");
                $stmt->execute([$name, $slug, $description, $parent_id, $commission_rate, 
                               $meta_title, $meta_description]);
                
                logUserActivity($_SESSION['user_id'], 'category_add', "Added category: {$name}");
                $_SESSION['success'] = 'Category added successfully!';
            } catch(PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    $_SESSION['error'] = 'Category slug already exists';
                } else {
                    $_SESSION['error'] = 'Error adding category: ' . $e->getMessage();
                }
            }
        }
        
    } elseif ($action === 'edit_category') {
        $category_id = (int)$_POST['category_id'];
        $name = sanitize($_POST['name'] ?? '');
        $slug = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $commission_rate = (float)($_POST['commission_rate'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $meta_title = sanitize($_POST['meta_title'] ?? '');
        $meta_description = sanitize($_POST['meta_description'] ?? '');
        
        if (empty($name)) {
            $_SESSION['error'] = 'Category name is required';
        }
        
        if (empty($_SESSION['error'])) {
            try {
                $stmt = $db->prepare("
                    UPDATE categories SET 
                        name = ?, slug = ?, description = ?, parent_id = ?, 
                        commission_rate = ?, is_active = ?, meta_title = ?, meta_description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $slug, $description, $parent_id, $commission_rate, 
                               $is_active, $meta_title, $meta_description, $category_id]);
                
                logUserActivity($_SESSION['user_id'], 'category_edit', "Edited category: {$name}");
                $_SESSION['success'] = 'Category updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Error updating category: ' . $e->getMessage();
            }
        }
        
    } elseif ($action === 'delete_category') {
        $category_id = (int)$_POST['category_id'];
        
        try {
            // Check if category has products
            $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $product_count = $stmt->fetchColumn();
            
            if ($product_count > 0) {
                $_SESSION['error'] = "Cannot delete category. It has {$product_count} products associated.";
            } else {
                $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                
                logUserActivity($_SESSION['user_id'], 'category_delete', "Deleted category ID: {$category_id}");
                $_SESSION['success'] = 'Category deleted successfully!';
            }
        } catch(PDOException $e) {
            $_SESSION['error'] = 'Error deleting category: ' . $e->getMessage();
        }
    }
    
    redirect('index.php');
    exit();
}

// Get all categories with parent info
$stmt = $db->query("
    SELECT c.*, 
           p.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY c.name
");
$categories = $stmt->fetchAll();

// Get pending category change requests
$stmt = $db->query("
    SELECT ccr.*, 
           c.name as category_name,
           u.full_name as vendor_name,
           u.email as vendor_email
    FROM category_change_requests ccr
    JOIN categories c ON ccr.category_id = c.id
    JOIN users u ON ccr.vendor_id = u.id
    WHERE ccr.status = 'pending'
    ORDER BY ccr.created_at DESC
    LIMIT 10
");
$pending_requests = $stmt->fetchAll();

// Get pending vendor category commissions
$stmt = $db->query("
    SELECT vcc.*, 
           c.name as category_name,
           u.full_name as vendor_name,
           u.email as vendor_email
    FROM vendor_category_commissions vcc
    JOIN categories c ON vcc.category_id = c.id
    JOIN users u ON vcc.vendor_id = u.id
    WHERE vcc.approved_by IS NULL
    ORDER BY vcc.created_at DESC
    LIMIT 10
");
$pending_commissions = $stmt->fetchAll();
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.stat-card.primary { border-left-color: var(--primary); }
.stat-card.success { border-left-color: var(--success); }
.stat-card.warning { border-left-color: var(--warning); }
.stat-card.info { border-left-color: var(--info); }

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--dark);
}

.stat-label {
    color: #6c757d;
    font-size: 14px;
    font-weight: 500;
}

.table-categories {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.03);
}

.table-categories thead th {
    background: #f8f9fa;
    border-bottom: none;
    font-weight: 600;
    color: var(--dark);
    padding: 15px;
}

.table-categories tbody td {
    padding: 15px;
    vertical-align: middle;
    border-bottom: 1px solid #edf2f9;
}

.badge-commission {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: 500;
}

.btn-icon {
    width: 35px;
    height: 35px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    transform: translateY(-2px);
}

.tree-indent {
    display: inline-block;
    width: 20px;
    margin-right: 5px;
}

.request-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid var(--warning);
    transition: all 0.3s ease;
}

.request-card:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.request-card.commission {
    border-left-color: var(--info);
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.request-title {
    font-weight: 600;
    color: var(--dark);
}

.request-meta {
    font-size: 12px;
    color: #6c757d;
}

.request-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-approve {
    background: var(--success);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
}

.btn-reject {
    background: var(--danger);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
}
</style>

<div class="categories-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-tags me-2 text-primary"></i>
                    Categories Management
                </h1>
                <p class="text-muted mb-0">Manage product categories and vendor commissions</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../dashboard.php" class="text-decoration-none btn btn-primary">
                <i class="fas fa-home"></i>    
                back</a>
                <a href="approvals.php" class="text-decoration-none btn btn-primary">
                <i class="fas fa-check-double"></i>    
                Approvals</a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus-circle me-2"></i> Add New Category
                </button>
            </div>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="fas fa-check-circle me-2"></i>
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-value"><?php echo count($categories); ?></div>
            <div class="stat-label">Total Categories</div>
        </div>
        <div class="stat-card success">
            <?php 
            $active_count = count(array_filter($categories, fn($c) => $c['is_active']));
            ?>
            <div class="stat-value"><?php echo $active_count; ?></div>
            <div class="stat-label">Active Categories</div>
        </div>
        <div class="stat-card warning">
            <div class="stat-value"><?php echo count($pending_requests); ?></div>
            <div class="stat-label">Pending Requests</div>
        </div>
        <div class="stat-card info">
            <div class="stat-value"><?php echo count($pending_commissions); ?></div>
            <div class="stat-label">Commission Approvals</div>
        </div>
    </div>

    <!-- Pending Requests Section -->
    <?php if (!empty($pending_requests) || !empty($pending_commissions)): ?>
    <div class="row mb-4">
        <?php if (!empty($pending_requests)): ?>
        <div class="col-md-6">
            <h5 class="mb-3">
                <i class="fas fa-clock text-warning me-2"></i>
                Category Change Requests
            </h5>
            <?php foreach($pending_requests as $request): ?>
            <div class="request-card">
                <div class="request-header">
                    <div>
                        <div class="request-title"><?php echo htmlspecialchars($request['category_name']); ?></div>
                        <div class="request-meta">
                            By: <?php echo htmlspecialchars($request['vendor_name']); ?> • 
                            <?php echo date('d M Y, h:i A', strtotime($request['created_at'])); ?>
                        </div>
                    </div>
                    <span class="badge bg-warning">Pending</span>
                </div>
                <div>
                    <strong>Request Type:</strong> 
                    <?php echo ucfirst(str_replace('_', ' ', $request['request_type'])); ?>
                </div>
                <?php if ($request['request_type'] == 'change_commission'): ?>
                <div class="mt-2">
                    <span class="text-muted">Commission Change:</span>
                    <span class="text-danger"><s><?php echo $request['old_commission_rate']; ?>%</s></span>
                    <i class="fas fa-arrow-right mx-2"></i>
                    <span class="text-success"><?php echo $request['new_commission_rate']; ?>%</span>
                </div>
                <?php endif; ?>
                <div class="request-actions">
                    <button class="btn-approve" onclick="approveRequest(<?php echo $request['id']; ?>)">
                        <i class="fas fa-check me-1"></i> Approve
                    </button>
                    <button class="btn-reject" onclick="rejectRequest(<?php echo $request['id']; ?>)">
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pending_commissions)): ?>
        <div class="col-md-6">
            <h5 class="mb-3">
                <i class="fas fa-percentage text-info me-2"></i>
                Commission Rate Approvals
            </h5>
            <?php foreach($pending_commissions as $commission): ?>
            <div class="request-card commission">
                <div class="request-header">
                    <div>
                        <div class="request-title"><?php echo htmlspecialchars($commission['category_name']); ?></div>
                        <div class="request-meta">
                            Vendor: <?php echo htmlspecialchars($commission['vendor_name']); ?> • 
                            <?php echo date('d M Y', strtotime($commission['created_at'])); ?>
                        </div>
                    </div>
                    <span class="badge bg-info">Pending</span>
                </div>
                <div class="mt-2">
                    <strong>Requested Commission:</strong> 
                    <span class="badge-commission"><?php echo $commission['commission_rate']; ?>%</span>
                </div>
                <div class="request-actions">
                    <button class="btn-approve" onclick="approveCommission(<?php echo $commission['id']; ?>)">
                        <i class="fas fa-check me-1"></i> Approve
                    </button>
                    <button class="btn-reject" onclick="rejectCommission(<?php echo $commission['id']; ?>)">
                        <i class="fas fa-times me-1"></i> Reject
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Categories Table -->
    <div class="table-responsive table-categories">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Slug</th>
                    <th>Parent Category</th>
                    <th>Commission</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                function displayCategoryRow($category, $categories, $level = 0) {
                    $indent = str_repeat('<span class="tree-indent"></span>', $level);
                ?>
                    <tr>
                        <td>#<?php echo $category['id']; ?></td>
                        <td>
                            <?php echo $indent; ?>
                            <?php if ($level > 0): ?>
                                <i class="fas fa-level-down-alt text-muted me-1" style="font-size: 12px;"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                        <td>
                            <?php 
                            if (!empty($category['parent_name'])) {
                                echo htmlspecialchars($category['parent_name']);
                            } else {
                                echo '<span class="text-muted">-</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="badge-commission">
                                <?php echo $category['commission_rate']; ?>%
                            </span>
                        </td>
                        <td><?php echo $category['product_count']; ?></td>
                        <td>
                            <?php if ($category['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d M Y', strtotime($category['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary btn-icon me-1" 
                                    onclick="editCategory(<?php echo $category['id']; ?>)"
                                    data-bs-toggle="tooltip" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btn-icon" 
                                    onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')"
                                    data-bs-toggle="tooltip" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php
                }

                // Build tree structure
                $category_tree = [];
                foreach ($categories as $cat) {
                    if (empty($cat['parent_id'])) {
                        $category_tree[] = $cat;
                    }
                }

                foreach ($category_tree as $parent) {
                    displayCategoryRow($parent, $categories);
                    
                    // Find children
                    foreach ($categories as $child) {
                        if ($child['parent_id'] == $parent['id']) {
                            displayCategoryRow($child, $categories, 1);
                        }
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle me-2"></i> Add New Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category Name *</label>
                            <input type="text" class="form-control" name="name" id="categoryName" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Slug</label>
                            <input type="text" class="form-control" name="slug" id="categorySlug">
                            <div class="form-text">Leave empty to auto-generate</div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Parent Category</label>
                            <select class="form-select" name="parent_id">
                                <option value="">None (Top Level)</option>
                                <?php foreach($categories as $cat): ?>
                                    <?php if (empty($cat['parent_id'])): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Commission Rate (%) *</label>
                            <input type="number" class="form-control" name="commission_rate" 
                                   value="0" step="0.1" min="0" max="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Meta Title</label>
                            <input type="text" class="form-control" name="meta_title">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Meta Description</label>
                            <input type="text" class="form-control" name="meta_description">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="category_id" id="edit_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i> Edit Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Category Name *</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Slug</label>
                            <input type="text" class="form-control" name="slug" id="edit_slug">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Parent Category</label>
                            <select class="form-select" name="parent_id" id="edit_parent_id">
                                <option value="">None (Top Level)</option>
                                <?php foreach($categories as $cat): ?>
                                    <?php if (empty($cat['parent_id'])): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Commission Rate (%) *</label>
                            <input type="number" class="form-control" name="commission_rate" 
                                   id="edit_commission_rate" step="0.1" min="0" max="100" required>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active" value="1">
                                <label class="form-check-label fw-bold" for="edit_is_active">
                                    Active Category
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Meta Title</label>
                            <input type="text" class="form-control" name="meta_title" id="edit_meta_title">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Meta Description</label>
                            <input type="text" class="form-control" name="meta_description" id="edit_meta_description">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" id="delete_id">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i> Delete Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete category: <strong id="delete_name"></strong>?</p>
                    <p class="text-danger mb-0">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        This action cannot be undone. Any subcategories will become top-level categories.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Delete Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Approval Modal -->
<div class="modal fade" id="approveRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="process-request.php">
                <input type="hidden" name="request_id" id="approve_request_id">
                <input type="hidden" name="action" value="approve">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle me-2"></i> Approve Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve this request?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i> Approve Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Rejection Modal -->
<div class="modal fade" id="rejectRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="process-request.php">
                <input type="hidden" name="request_id" id="reject_request_id">
                <input type="hidden" name="action" value="reject">
                
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle me-2"></i> Reject Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason for Rejection *</label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                        <div class="form-text">This reason will be sent to the vendor</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i> Reject Request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-generate slug from name
document.getElementById('categoryName')?.addEventListener('input', function() {
    const slugField = document.getElementById('categorySlug');
    if (slugField.value === '') {
        slugField.value = this.value.toLowerCase()
            .replace(/[^a-z0-9-]/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-|-$/g, '');
    }
});

// Edit category function
function editCategory(id) {
    fetch(`get-category.php?id=${id}`)
        .then(response => response.json())
        .then(category => {
            document.getElementById('edit_id').value = category.id;
            document.getElementById('edit_name').value = category.name;
            document.getElementById('edit_slug').value = category.slug;
            document.getElementById('edit_description').value = category.description || '';
            document.getElementById('edit_parent_id').value = category.parent_id || '';
            document.getElementById('edit_commission_rate').value = category.commission_rate;
            document.getElementById('edit_is_active').checked = category.is_active == 1;
            document.getElementById('edit_meta_title').value = category.meta_title || '';
            document.getElementById('edit_meta_description').value = category.meta_description || '';
            
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        });
}

// Delete category function
function deleteCategory(id, name) {
    document.getElementById('delete_id').value = id;
    document.getElementById('delete_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteCategoryModal')).show();
}

// Approve request function
function approveRequest(id) {
    document.getElementById('approve_request_id').value = id;
    new bootstrap.Modal(document.getElementById('approveRequestModal')).show();
}

// Reject request function
function rejectRequest(id) {
    document.getElementById('reject_request_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectRequestModal')).show();
}

// Approve commission function
function approveCommission(id) {
    if (confirm('Are you sure you want to approve this commission rate?')) {
        window.location.href = `process-commission.php?id=${id}&action=approve`;
    }
}

// Reject commission function
function rejectCommission(id) {
    const reason = prompt('Please enter rejection reason:');
    if (reason !== null && reason.trim() !== '') {
        window.location.href = `process-commission.php?id=${id}&action=reject&reason=${encodeURIComponent(reason)}`;
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>