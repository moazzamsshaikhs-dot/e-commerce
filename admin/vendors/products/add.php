<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ../../index.php');
    exit();
}

// Check if vendor is approved
$vendor_id = $_SESSION['user_id'];
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['error'] = 'Your vendor account is not approved. Please wait for admin approval.';
        header('Location: ../../vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ../../vendor/dashboard.php');
    exit();
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price = !empty($_POST['price']) ? (float)$_POST['price'] : 0;
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : null;
    $category = sanitize($_POST['category'] ?? '');
    $stock = !empty($_POST['stock']) ? (int)$_POST['stock'] : 0;
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Validation
    if (empty($name) || strlen($name) < 3) {
        $errors[] = 'Product name must be at least 3 characters';
    }
    
    if (empty($description) || strlen($description) < 10) {
        $errors[] = 'Description must be at least 10 characters';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    }
    
    if ($old_price !== null && $old_price <= $price) {
        $errors[] = 'Original price must be greater than current price to show discount';
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
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['image']['type'];
        $file_size = $_FILES['image']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = 'Only JPG, PNG, GIF and WebP images are allowed';
        } elseif ($file_size > $max_size) {
            $errors[] = 'Image size must be less than 5MB';
        } else {
            // Generate unique filename
            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $image_name = 'product_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            
            // Check if directory exists, if not create it
            $upload_dir = '../../../assets/images/products/';
            
            // Ensure the directory exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $upload_path = $upload_dir . $image_name;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $errors[] = 'Failed to upload image. Please try again.';
                error_log("Image upload failed for vendor $vendor_id. Path: $upload_path");
            }
        }
    }
    
    // If no errors, insert product
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO products (vendor_id, name, description, price, old_price, 
                                    image, category, stock, featured, approved_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $result = $stmt->execute([
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
            
            if ($result) {
                $product_id = $db->lastInsertId();
                
                // Log activity
                logUserActivity($vendor_id, 'product_add', "Added new product: $name (ID: $product_id)");
                
                $_SESSION['success'] = 'Product added successfully! It will be available after admin approval.';
                
                // Redirect to products list
                header('Location: products.php');
                exit();
            } else {
                $errors[] = 'Failed to add product. Please try again.';
            }
            
        } catch(PDOException $e) {
            $errors[] = 'Error adding product: ' . $e->getMessage();
            error_log("Add Product Error - Vendor ID $vendor_id: " . $e->getMessage());
        }
    }
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
                                <div class="form-text d-flex justify-content-between">
                                    <span>Describe your product in detail</span>
                                    <span id="charCounter" class="text-muted">0 characters</span>
                                </div>
                            </div>
                            
                            <!-- Price -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="price" class="form-label fw-bold">
                                        <i class="fas fa-dollar-sign me-2 text-primary"></i> Current Price *
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               step="0.01" min="0.01" max="999999.99" 
                                               value="<?php echo isset($_POST['price']) ? $_POST['price'] : ''; ?>" 
                                               required>
                                    </div>
                                    <div class="form-text">Set the selling price</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="old_price" class="form-label fw-bold">
                                        <i class="fas fa-tags me-2 text-secondary"></i> Original Price (Optional)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="old_price" name="old_price" 
                                               step="0.01" min="0.01" max="999999.99" 
                                               value="<?php echo isset($_POST['old_price']) ? $_POST['old_price'] : ''; ?>">
                                    </div>
                                    <div class="form-text">Set original price to show discount</div>
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
                                    <div class="form-text">Choose the most relevant category</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="stock" class="form-label fw-bold">
                                        <i class="fas fa-boxes me-2 text-primary"></i> Stock Quantity *
                                    </label>
                                    <input type="number" class="form-control" id="stock" name="stock" 
                                           min="0" max="999999" 
                                           value="<?php echo isset($_POST['stock']) ? $_POST['stock'] : '0'; ?>" required>
                                    <div class="form-text">Enter 0 for out of stock items</div>
                                </div>
                            </div>
                            
                            <!-- Image Upload -->
                            <div class="mb-4">
                                <label for="image" class="form-label fw-bold">
                                    <i class="fas fa-image me-2 text-primary"></i> Product Image
                                </label>
                                <input type="file" class="form-control" id="image" name="image" 
                                       accept=".jpg,.jpeg,.png,.gif,.webp">
                                <div class="form-text">
                                    Recommended: 900x900 pixels. Max: 5MB. Formats: JPG, PNG, GIF, WebP
                                </div>
                                
                                <!-- Image Preview -->
                                <div id="imagePreview" class="mt-3" style="display: none;">
                                    <div class="d-flex align-items-center">
                                        <img id="previewImage" class="img-thumbnail me-3" style="max-width: 150px; max-height: 150px;">
                                        <div>
                                            <p class="mb-1"><strong>Image Preview</strong></p>
                                            <small class="text-muted">This is how your product image will appear</small>
                                        </div>
                                    </div>
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
                                    <div class="form-text">Featured products get special visibility on the website homepage</div>
                                </div>
                            </div>
                            
                            <!-- Form Buttons -->
                            <div class="d-flex justify-content-between pt-4 border-top">
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i> Clear Form
                                </button>
                                <button type="submit" class="btn btn-primary px-5" id="submitBtn">
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
                            <i class="fas fa-lightbulb me-2 text-warning"></i> Product Guidelines
                        </h5>
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> All products require admin approval (24-48 hours)
                        </div>
                        
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>High-Quality Images</strong>
                                        <p class="text-muted small mb-0">Use clear, well-lit product photos</p>
                                    </div>
                                </div>
                            </li>
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Detailed Descriptions</strong>
                                        <p class="text-muted small mb-0">Include all relevant specifications</p>
                                    </div>
                                </div>
                            </li>
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Accurate Pricing</strong>
                                        <p class="text-muted small mb-0">Research market prices before setting</p>
                                    </div>
                                </div>
                            </li>
                            <li class="mb-3">
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Proper Categorization</strong>
                                        <p class="text-muted small mb-0">Helps customers find your products</p>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex">
                                    <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                    <div>
                                        <strong>Realistic Stock Levels</strong>
                                        <p class="text-muted small mb-0">Keep inventory updated regularly</p>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.form-control:focus, .form-select:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
}

.img-thumbnail {
    border-radius: 8px;
    border: 2px dashed #dee2e6;
    padding: 5px;
}

.alert {
    border-radius: 8px;
    border: none;
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
    } else {
        document.getElementById('imagePreview').style.display = 'none';
    }
});

// Character counter for description
const descriptionField = document.getElementById('description');
const charCounter = document.getElementById('charCounter');

descriptionField.addEventListener('input', function() {
    const charCount = this.value.length;
    charCounter.textContent = `${charCount} characters`;
    
    if (charCount < 10) {
        charCounter.className = 'text-danger';
    } else if (charCount < 50) {
        charCounter.className = 'text-warning';
    } else {
        charCounter.className = 'text-success';
    }
});

// Form validation
document.getElementById('productForm').addEventListener('submit', function(e) {
    const price = parseFloat(document.getElementById('price').value);
    const oldPrice = document.getElementById('old_price').value;
    
    // Validate old price
    if (oldPrice && parseFloat(oldPrice) <= price) {
        e.preventDefault();
        alert(' Original price must be greater than current price to show discount.');
        document.getElementById('old_price').focus();
        return false;
    }
    
    // Validate stock
    const stock = parseInt(document.getElementById('stock').value);
    if (stock < 0) {
        e.preventDefault();
        alert(' Stock quantity cannot be negative.');
        document.getElementById('stock').focus();
        return false;
    }
    
    // Show loading
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding Product...';
    submitBtn.disabled = true;
    
    return true;
});

// Reset form previews
document.querySelector('button[type="reset"]').addEventListener('click', function() {
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('charCounter').textContent = '0 characters';
    document.getElementById('charCounter').className = 'text-muted';
});



// Add this to your add.php JavaScript section
document.addEventListener('DOMContentLoaded', function() {
    // Fix for null errors - check if elements exist
    const imageInput = document.getElementById('image');
    const descriptionField = document.getElementById('description');
    const charCounter = document.getElementById('charCounter');
    const productForm = document.getElementById('productForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // Image preview (only if element exists)
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('previewImage');
                    const previewContainer = document.getElementById('imagePreview');
                    if (preview && previewContainer) {
                        preview.src = e.target.result;
                        previewContainer.style.display = 'block';
                    }
                }
                reader.readAsDataURL(file);
            } else {
                const previewContainer = document.getElementById('imagePreview');
                if (previewContainer) {
                    previewContainer.style.display = 'none';
                }
            }
        });
    }
    
    // Character counter (only if elements exist)
    if (descriptionField && charCounter) {
        descriptionField.addEventListener('input', function() {
            const charCount = this.value.length;
            charCounter.textContent = `${charCount} characters`;
            
            if (charCount < 10) {
                charCounter.className = 'text-danger';
            } else if (charCount < 50) {
                charCounter.className = 'text-warning';
            } else {
                charCounter.className = 'text-success';
            }
        });
    }
    
    // Form validation (only if form exists)
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            const price = parseFloat(document.getElementById('price').value);
            const oldPrice = document.getElementById('old_price').value;
            
            // Validate old price
            if (oldPrice && parseFloat(oldPrice) <= price) {
                e.preventDefault();
                alert(' Original price must be greater than current price to show discount.');
                document.getElementById('old_price').focus();
                return false;
            }
            
            // Validate stock
            const stock = parseInt(document.getElementById('stock').value);
            if (stock < 0) {
                e.preventDefault();
                alert(' Stock quantity cannot be negative.');
                document.getElementById('stock').focus();
                return false;
            }
            
            // Show loading (only if button exists)
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Adding Product...';
                submitBtn.disabled = true;
            }
            
            return true;
        });
    }
    
    // Reset form (only if button exists)
    const resetBtn = document.querySelector('button[type="reset"]');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            const previewContainer = document.getElementById('imagePreview');
            if (previewContainer) {
                previewContainer.style.display = 'none';
            }
            if (charCounter) {
                charCounter.textContent = '0 characters';
                charCounter.className = 'text-muted';
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>