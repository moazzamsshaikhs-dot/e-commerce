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
        redirect('../../vendor/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status.';
    redirect('../../vendor/dashboard.php');
}

$page_title = 'Add Product';
require_once '../../includes/header.php';

// Get categories
try {
    $stmt = $db->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
} catch(PDOException $e) {
    $categories = [];
}

$errors = [];
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = (float)$_POST['price'];
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : null;
    $category = sanitize($_POST['category']);
    $stock = (int)$_POST['stock'];
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Product name is required';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Product name must be at least 3 characters';
    }
    
    if (empty($description)) {
        $errors[] = 'Description is required';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    }
    
    if ($old_price !== null && $old_price <= $price) {
        $errors[] = 'Old price must be greater than current price';
    }
    
    if (empty($category)) {
        $errors[] = 'Category is required';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative';
    }
    
    // Image upload
    $image_name = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 7 * 2000 * 2000; // 7MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = 'Only JPG, PNG, GIF and WebP images are allowed';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'Image size must be less than 7MB';
        } else {
            // Generate unique filename
            $file_ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_name = 'product_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
            $upload_path =  '../../../assets/images/products/' . $image_name;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload image';
            }
        }
    }
    
    // If no errors, insert product
    if (empty($errors)) {
        try {
            $vendor_id = $_SESSION['user_id'];
            
            $stmt = $db->prepare("
                INSERT INTO products (vendor_id, name, description, price, old_price, 
                                    image, category, stock, featured, approved_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $stmt->execute([
                $vendor_id,
                $name,
                $description,
                $price,
                $old_price,
                $image_name,
                $category,
                $stock,
                $featured
            ]);
            
            $product_id = $db->lastInsertId();
            
            // Log activity
            logUserActivity($vendor_id, 'product_add', "Added new product: $name (ID: $product_id)");
            
            $_SESSION['success'] = 'Product added successfully! It will be available after admin approval.';
            
            // Redirect to products list
            redirect('products.php');
            
        } catch(PDOException $e) {
            $errors[] = 'Error adding product: ' . $e->getMessage();
        }
    }
}
?>

<div class="dashboard-container">
    <?php include '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Add New Product</h1>
                    <p class="text-muted mb-0">Add a new product to your store</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Products
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Product Form -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach($errors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                            <!-- Product Name -->
                            <div class="mb-4">
                                <label for="name" class="form-label fw-bold">
                                    <i class="fas fa-tag me-2 text-primary"></i> Product Name *
                                </label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required minlength="3" maxlength="255">
                                <div class="form-text">Enter a descriptive name for your product</div>
                            </div>
                            
                            <!-- Description -->
                            <div class="mb-4">
                                <label for="description" class="form-label fw-bold">
                                    <i class="fas fa-align-left me-2 text-primary"></i> Description *
                                </label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="5" required minlength="10"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                <div class="form-text">Describe your product in detail. Include features, benefits, and specifications.</div>
                            </div>
                            
                            <!-- Price -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="price" class="form-label fw-bold">
                                        <i class="fas fa-dollar-sign me-2 text-primary"></i> Price *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               step="0.01" min="0.01" max="999999.99" 
                                               value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="old_price" class="form-label fw-bold">
                                        <i class="fas fa-tags me-2 text-secondary"></i> Old Price (Optional)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="old_price" name="old_price" 
                                               step="0.01" min="0.01" max="999999.99" 
                                               value="<?php echo isset($_POST['old_price']) ? $_POST['old_price'] : ''; ?>">
                                    </div>
                                    <div class="form-text">Set an old price to show discount</div>
                                </div>
                            </div>
                            
                            <!-- Category and Stock -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="category" class="form-label fw-bold">
                                        <i class="fas fa-folder me-2 text-primary"></i> Category *
                                    </label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?php echo $cat['slug']; ?>" 
                                                <?php echo (isset($_POST['category']) && $_POST['category'] == $cat['slug']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="stock" class="form-label fw-bold">
                                        <i class="fas fa-boxes me-2 text-primary"></i> Stock Quantity *
                                    </label>
                                    <input type="number" class="form-control" id="stock" name="stock" 
                                           min="0" max="999999" 
                                           value="<?php echo isset($_POST['stock']) ? $_POST['stock'] : '0'; ?>" required>
                                    <div class="form-text">Enter 0 for out of stock</div>
                                </div>
                            </div>
                            
                            <!-- Image Upload -->
                            <div class="mb-4">
                                <label for="image" class="form-label fw-bold">
                                    <i class="fas fa-image me-2 text-primary"></i> Product Image
                                </label>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text">Recommended size: 900x900 pixels. Max size: 5MB</div>
                                
                                <!-- Image Preview -->
                                <div id="imagePreview" class="mt-3" style="display: none;">
                                    <img id="previewImage" class="img-thumbnail" style="max-width: 200px;">
                                </div>
                            </div>
                            
                            <!-- Featured Checkbox -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="featured" name="featured" 
                                           <?php echo isset($_POST['featured']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="featured">
                                        <i class="fas fa-star me-2 text-warning"></i> Mark as Featured Product
                                    </label>
                                    <div class="form-text">Featured products get special visibility on the website</div>
                                </div>
                            </div>
                            
                            <!-- Form Buttons -->
                            <div class="d-flex justify-content-between pt-4 border-top">
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i> Reset Form
                                </button>
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-plus-circle me-2"></i> Add Product
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Tips -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-lightbulb me-2 text-warning"></i> Product Tips
                        </h5>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> All products require admin approval before they appear in the store.
                        </div>
                        
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Clear Images</strong>
                                        <p class="text-muted small mb-0">Use high-quality images from multiple angles</p>
                                    </div>
                                </div>
                            </li>
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Detailed Description</strong>
                                        <p class="text-muted small mb-0">Include specifications, materials, and dimensions</p>
                                    </div>
                                </div>
                            </li>
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Competitive Pricing</strong>
                                        <p class="text-muted small mb-0">Research similar products for pricing</p>
                                    </div>
                                </div>
                            </li>
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Accurate Stock</strong>
                                        <p class="text-muted small mb-0">Keep inventory updated to avoid overselling</p>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Proper Category</strong>
                                        <p class="text-muted small mb-0">Choose the most relevant category</p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Approval Process -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3 fw-bold">
                            <i class="fas fa-clipboard-check me-2 text-success"></i> Approval Process
                        </h5>
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Product Submission</h6>
                                    <p class="text-muted small mb-0">You submit the product details</p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-warning"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Admin Review</h6>
                                    <p class="text-muted small mb-0">Admin reviews product details</p>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-marker bg-success"></div>
                                <div class="timeline-content">
                                    <h6 class="mb-1">Approval/Rejection</h6>
                                    <p class="text-muted small mb-0">Product is approved or needs revision</p>
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="fas fa-clock me-1"></i>
                                Approval typically takes 24-48 hours
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    top: 0;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 3px solid white;
}

.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
}

.img-thumbnail {
    border-radius: 8px;
    border: 2px dashed #dee2e6;
    padding: 5px;
}
</style>

<script>
// Image preview
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('previewImage');
            preview.src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});

// Form validation
document.getElementById('productForm').addEventListener('submit', function(e) {
    const price = parseFloat(document.getElementById('price').value);
    const oldPrice = document.getElementById('old_price').value;
    
    if (oldPrice && parseFloat(oldPrice) <= price) {
        e.preventDefault();
        alert('Old price must be greater than current price');
        document.getElementById('old_price').focus();
        return false;
    }
    
    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding Product...';
    submitBtn.disabled = true;
});

// Character counter for description
document.getElementById('description').addEventListener('input', function() {
    const charCount = this.value.length;
    const counter = document.getElementById('charCounter') || (() => {
        const div = document.createElement('div');
        div.id = 'charCounter';
        div.className = 'form-text text-end';
        this.parentNode.appendChild(div);
        return div;
    })();
    
    counter.textContent = `${charCount} characters`;
    
    if (charCount < 10) {
        counter.className = 'form-text text-end text-danger';
    } else if (charCount < 50) {
        counter.className = 'form-text text-end text-warning';
    } else {
        counter.className = 'form-text text-end text-success';
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>