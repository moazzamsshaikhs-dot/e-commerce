<?php
// admin/vendors/categories/submit-category.php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Submit New Category';
require_once '../../includes/header.php';

$vendor_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $commission_rate = floatval($_POST['commission_rate'] ?? 0);
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Category name is required';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Category name must be at least 3 characters';
    }
    
    if (empty($slug)) {
        // Generate slug from name
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    }
    
    if ($commission_rate < 0 || $commission_rate > 100) {
        $errors[] = 'Commission rate must be between 0 and 100';
    }
    
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check if category already exists
            $stmt = $db->prepare("SELECT id FROM vendor_categories WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetch()) {
                $errors[] = 'A category with this slug already exists';
            } else {
                // Insert new category with pending status
                $stmt = $db->prepare("
                    INSERT INTO vendor_categories (
                        vendor_id, name, slug, description, icon, 
                        commission_rate, approval_status, submitted_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
                ");
                
                $stmt->execute([
                    $vendor_id,
                    $name,
                    $slug,
                    $description,
                    $icon,
                    $commission_rate,
                    $_SESSION['user_id']
                ]);
                
                $category_id = $db->lastInsertId();
                
                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $log = $db->prepare("
                    INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
                    VALUES (?, 'category_submit', ?, ?, ?, NOW())
                ");
                $log->execute([$vendor_id, "Submitted new category: {$name} for approval", $ip, $ua]);
                
                // Create notification for admin
                $admin_notify = $db->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at)
                    SELECT id, 'New Category Pending Approval', ?, 'info', NOW()
                    FROM users WHERE user_type = 'admin'
                ");
                $admin_notify->execute(["Vendor has submitted a new category '{$name}' for approval"]);
                
                $_SESSION['success'] = "Category '{$name}' has been submitted for approval. You'll be notified once it's reviewed.";
                redirect('my-categories.php');
                exit();
            }
        } catch(PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log("Category submit error: " . $e->getMessage());
        }
    }
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

.category-container {
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

.form-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.form-control, .form-select {
    border-radius: 12px;
    border: 2px solid #edf2f9;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.btn-submit {
    background: var(--primary);
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-submit:hover {
    background: #3651c4;
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
}

.info-card {
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
    border-radius: 20px;
    padding: 25px;
    height: 100%;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    padding: 10px 0;
    border-bottom: 1px dashed rgba(67, 97, 238, 0.2);
    display: flex;
    align-items: center;
    gap: 12px;
}

.info-list li i {
    width: 30px;
    height: 30px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
}
</style>

<div class="category-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-tags me-2 text-primary"></i>
                    Submit New Category
                </h1>
                <p class="text-muted mb-0">Propose a new category for your products</p>
            </div>
            <a href="my-categories.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to My Categories
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-15 mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                <div>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="form-card">
                <form method="POST">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Category Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                   required
                                   placeholder="e.g., Electronics, Fashion, etc.">
                            <div class="form-text">Enter a unique category name</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Category Slug</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="slug" 
                                   value="<?php echo htmlspecialchars($_POST['slug'] ?? ''); ?>"
                                   placeholder="electronics, fashion, etc.">
                            <div class="form-text">URL-friendly name (leave empty to auto-generate)</div>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" 
                                      name="description" 
                                      rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            <div class="form-text">Describe what products belong in this category</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Icon (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-icons"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       name="icon" 
                                       value="<?php echo htmlspecialchars($_POST['icon'] ?? ''); ?>"
                                       placeholder="fa-mobile-alt, fa-tshirt, etc.">
                            </div>
                            <div class="form-text">Font Awesome icon class (e.g., fa-mobile-alt)</div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Commission Rate (%) *</label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       name="commission_rate" 
                                       value="<?php echo htmlspecialchars($_POST['commission_rate'] ?? '10'); ?>"
                                       step="0.1"
                                       min="0"
                                       max="100"
                                       required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Platform commission for products in this category</div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <div class="d-flex gap-3 justify-content-end">
                            <a href="my-categories.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                            <button type="submit" class="btn-submit btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>
                                Submit for Approval
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Info Sidebar -->
        <div class="col-lg-4">
            <div class="info-card">
                <h5 class="mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Submission Guidelines
                </h5>
                <ul class="info-list">
                    <li>
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Category names should be clear and descriptive</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Avoid duplicate or existing categories</span>
                    </li>
                    <li>
                        <i class="fas fa-check-circle text-success"></i>
                        <span>Commission rate will be reviewed by admin</span>
                    </li>
                    <li>
                        <i class="fas fa-clock text-warning"></i>
                        <span>Approval typically takes 24-48 hours</span>
                    </li>
                    <li>
                        <i class="fas fa-bell text-info"></i>
                        <span>You'll be notified when your category is reviewed</span>
                    </li>
                </ul>
                
                <div class="alert alert-warning mt-4">
                    <h6 class="alert-heading">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Note
                    </h6>
                    <p class="small mb-0">
                        You can only use approved categories for your products. 
                        Pending categories cannot be selected until approved.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>