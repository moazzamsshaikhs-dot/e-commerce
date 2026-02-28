<?php
// admin/products/product-action.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;


/**
 * Upload product image
 */
function uploadProductImage($file, $existing_image = '') {
    $errors = [];
    $image_name = '';
    
    if (!empty($file['name'])) {
        // Basic validation
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . getUploadError($file['error']);
            return ['success' => false, 'errors' => $errors];
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "File size too large (max 5MB)";
            return ['success' => false, 'errors' => $errors];
        }
        
        // Get file info
        $file_info = pathinfo($file['name']);
        $file_extension = strtolower($file_info['extension'] ?? '');
        
        // Allowed extensions
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "File type not allowed. Allowed: " . implode(', ', $allowed_extensions);
            return ['success' => false, 'errors' => $errors];
        }
        
        // Generate unique filename
        $image_name = uniqid('product_', true) . '_' . time() . '.' . $file_extension;
        $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $image_name;
        
        // Create directory if not exists
        $upload_dir = dirname($upload_path);
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Delete old image if exists and not default
            if (!empty($existing_image) && $existing_image !== 'default.jpg') {
                $old_image_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $existing_image;
                if (file_exists($old_image_path)) {
                    @unlink($old_image_path);
                }
            }
            return ['success' => true, 'image_name' => $image_name];
        } else {
            $errors[] = "Failed to save uploaded file";
            return ['success' => false, 'errors' => $errors];
        }
    } else {
        // No new image uploaded
        if (!empty($existing_image)) {
            return ['success' => true, 'image_name' => $existing_image];
        } else {
            return ['success' => true, 'image_name' => 'default.jpg'];
        }
    }
}


/**
 * Validate product data
 */
function validateProductData($name, $price, $stock, $old_price, $description = '') {
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Product name is required';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Product name is too long (max 255 characters)';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    } elseif (!is_numeric($price)) {
        $errors[] = 'Price must be a number';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative';
    } elseif (!is_numeric($stock)) {
        $errors[] = 'Stock must be a number';
    }
    
    if (!empty($old_price)) {
        if (!is_numeric($old_price)) {
            $errors[] = 'Old price must be a number';
        } elseif ($old_price <= 0) {
            $errors[] = 'Old price must be greater than 0';
        } elseif ($old_price <= $price) {
            $errors[] = 'Old price must be greater than current price for discounts';
        }
    }
    
    if (strlen($description) > 5000) {
        $errors[] = 'Description is too long (max 5000 characters)';
    }
    
    return $errors;
}

/**
 * Generate CSRF token
 */
// function generateCSRFToken() {
//     if (!isset($_SESSION['csrf_token'])) {
//         $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
//     }
//     return $_SESSION['csrf_token'];
// }

/**
 * Validate CSRF token
 */
// function validateCSRFToken($token) {
//     return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
// }

try {
    $db = getDB();
    
    // Handle different actions
    switch($action) {
        case 'add':
            handleAddProduct();
            break;
            
        case 'edit':
            handleEditProduct($product_id);
            break;
            
        case 'view':
            handleViewProduct($product_id);
            break;
            
        case 'delete':
            handleDeleteProduct($product_id);
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action';
            redirect('products.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Database Error: " . $e->getMessage();
    error_log("Product action error: " . $e->getMessage());
    redirect('products.php');
}

/**
 * Handle adding new product
 */
function handleAddProduct() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Invalid security token';
            redirect('product-action.php?action=add');
        }
        
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $old_price = !empty($_POST['old_price']) ? floatval($_POST['old_price']) : null;
        $category = trim($_POST['category'] ?? '');
        $stock = intval($_POST['stock'] ?? 0);
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        // Validate input
        $errors = validateProductData($name, $price, $stock, $old_price, $description);
        
        // Handle image upload
        $image_result = uploadProductImage($_FILES['image'] ?? []);
        
        if (!$image_result['success']) {
            $errors = array_merge($errors, $image_result['errors']);
        }
        
        if (empty($errors)) {
            try {
                $db = getDB();
                
                // Insert product into database
                $stmt = $db->prepare("
                    INSERT INTO products (name, description, price, old_price, image, category, stock, featured, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $stmt->execute([
                    $name, 
                    $description, 
                    $price, 
                    $old_price, 
                    $image_result['image_name'], 
                    $category, 
                    $stock, 
                    $featured
                ]);
                
                $product_id = $db->lastInsertId();
                
                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $log = $db->prepare("
                    INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
                    VALUES (?, 'product_add', ?, ?, ?, NOW())
                ");
                $log->execute([$_SESSION['user_id'], "Added product: {$name} (ID: {$product_id})", $ip, $ua]);
                
                $_SESSION['success'] = 'Product added successfully!';
                redirect('products.php');
                
            } catch(PDOException $e) {
                // Delete uploaded image if database insertion fails
                if (isset($image_result['image_name']) && $image_result['image_name'] !== 'default.jpg') {
                    $image_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $image_result['image_name'];
                    if (file_exists($image_path)) {
                        @unlink($image_path);
                    }
                }
                
                $_SESSION['error'] = 'Failed to add product: ' . $e->getMessage();
                redirect('product-action.php?action=add');
            }
        } else {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            redirect('product-action.php?action=add');
        }
    } else {
        // Show add product form
        showAddProductForm();
    }
}

/**
 * Handle editing product
 */
function handleEditProduct($product_id) {
    if ($product_id <= 0) {
        $_SESSION['error'] = 'Invalid product ID';
        redirect('products.php');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = 'Invalid security token';
            redirect("product-action.php?action=edit&id={$product_id}");
        }
        
        // Get existing product
        $product = getProductById($product_id);
        if (!$product) {
            $_SESSION['error'] = 'Product not found';
            redirect('products.php');
        }
        
        // Get form data
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $old_price = !empty($_POST['old_price']) ? floatval($_POST['old_price']) : null;
        $category = trim($_POST['category'] ?? '');
        $stock = intval($_POST['stock'] ?? 0);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $remove_image = isset($_POST['remove_image']) ? true : false;
        
        // Validate input
        $errors = validateProductData($name, $price, $stock, $old_price, $description);
        
        // Handle image upload/removal
        $existing_image = $product['image'];
        
        if ($remove_image) {
            // Remove existing image
            if (!empty($existing_image) && $existing_image !== 'default.jpg') {
                $image_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $existing_image;
                if (file_exists($image_path)) {
                    @unlink($image_path);
                }
            }
            $image_name = 'default.jpg';
        } elseif (!empty($_FILES['image']['name'])) {
            // Upload new image
            $image_result = uploadProductImage($_FILES['image'], $existing_image);
            
            if (!$image_result['success']) {
                $errors = array_merge($errors, $image_result['errors']);
            } else {
                $image_name = $image_result['image_name'];
            }
        } else {
            // Keep existing image
            $image_name = $existing_image;
        }
        
        if (empty($errors)) {
            try {
                $db = getDB();
                
                // Update product in database
                $stmt = $db->prepare("
                    UPDATE products 
                    SET name = ?, description = ?, price = ?, old_price = ?, 
                        image = ?, category = ?, stock = ?, featured = ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $name, 
                    $description, 
                    $price, 
                    $old_price, 
                    $image_name, 
                    $category, 
                    $stock, 
                    $featured, 
                    $product_id
                ]);
                
                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $log = $db->prepare("
                    INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
                    VALUES (?, 'product_edit', ?, ?, ?, NOW())
                ");
                $log->execute([$_SESSION['user_id'], "Edited product: {$name} (ID: {$product_id})", $ip, $ua]);
                
                $_SESSION['success'] = 'Product updated successfully!';
                redirect('products.php');
                
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update product: ' . $e->getMessage();
                redirect("product-action.php?action=edit&id={$product_id}");
            }
        } else {
            $_SESSION['form_errors'] = $errors;
            $_SESSION['form_data'] = $_POST;
            redirect("product-action.php?action=edit&id={$product_id}");
        }
    } else {
        // Show edit product form
        showEditProductForm($product_id);
    }
}

/**
 * Handle viewing product
 */
function handleViewProduct($product_id) {
    if ($product_id <= 0) {
        $_SESSION['error'] = 'Invalid product ID';
        redirect('products.php');
    }
    
    $product = getProductById($product_id);
    if (!$product) {
        $_SESSION['error'] = 'Product not found';
        redirect('products.php');
    }
    
    showViewProduct($product);
}

/**
 * Handle deleting product
 */
function handleDeleteProduct($product_id) {
    if ($product_id <= 0) {
        $_SESSION['error'] = 'Invalid product ID';
        redirect('products.php');
    }
    
    // Verify this is a POST request (for security)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // Show confirmation form
        showDeleteConfirmation($product_id);
        return;
    }
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('products.php');
    }
    
    try {
        $db = getDB();
        
        // Get product details before deletion
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            $_SESSION['error'] = 'Product not found';
            redirect('products.php');
        }
        
        // Delete product image if exists and not default
        if (!empty($product['image']) && $product['image'] !== 'default.jpg') {
            $image_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $product['image'];
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }
        
        // Delete product from database
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        // Log activity
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log = $db->prepare("
            INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent, created_at)
            VALUES (?, 'product_delete', ?, ?, ?, NOW())
        ");
        $log->execute([$_SESSION['user_id'], "Deleted product: {$product['name']} (ID: {$product_id})", $ip, $ua]);
        
        $_SESSION['success'] = 'Product deleted successfully!';
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Failed to delete product: ' . $e->getMessage();
    }
    
    redirect('products.php');
}

/**
 * Show add product form
 */
function showAddProductForm() {
    $page_title = 'Add New Product';
    require_once '../includes/header.php';
    
    // Get categories from products table
    $categories = [];
    try {
        $db = getDB();
        $stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e) {
        // Ignore
    }
    
    // Get form data from session if exists
    $form_data = $_SESSION['form_data'] ?? [];
    $form_errors = $_SESSION['form_errors'] ?? [];
    
    // Clear session data
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);
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

    .product-form-container {
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

    .image-preview {
        margin-top: 15px;
        max-width: 200px;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid #edf2f9;
    }

    .image-preview img {
        width: 100%;
        height: auto;
        display: block;
    }
    </style>

    <div class="product-form-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>
                        Add New Product
                    </h1>
                    <p class="text-muted mb-0">Add a new product to your catalog</p>
                </div>
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Products
                </a>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($form_errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-15 mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach($form_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Product Form -->
        <div class="form-card">
            <form method="POST" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="row g-4">
                    <!-- Product Name -->
                    <div class="col-md-6">
                        <label class="form-label">Product Name *</label>
                        <input type="text" 
                               class="form-control" 
                               name="name" 
                               value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                               required
                               maxlength="255">
                    </div>
                    
                    <!-- Category -->
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <input type="text" 
                               class="form-control" 
                               name="category" 
                               value="<?php echo htmlspecialchars($form_data['category'] ?? ''); ?>"
                               list="categorySuggestions">
                        <datalist id="categorySuggestions">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <!-- Price -->
                    <div class="col-md-4">
                        <label class="form-label">Price *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   class="form-control" 
                                   name="price" 
                                   value="<?php echo htmlspecialchars($form_data['price'] ?? ''); ?>"
                                   step="0.01"
                                   min="0.01"
                                   required>
                        </div>
                    </div>
                    
                    <!-- Old Price -->
                    <div class="col-md-4">
                        <label class="form-label">Old Price (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   class="form-control" 
                                   name="old_price" 
                                   value="<?php echo htmlspecialchars($form_data['old_price'] ?? ''); ?>"
                                   step="0.01"
                                   min="0">
                        </div>
                    </div>
                    
                    <!-- Stock -->
                    <div class="col-md-4">
                        <label class="form-label">Stock Quantity *</label>
                        <input type="number" 
                               class="form-control" 
                               name="stock" 
                               value="<?php echo htmlspecialchars($form_data['stock'] ?? 0); ?>"
                               min="0"
                               required>
                    </div>
                    
                    <!-- Featured -->
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="featured" 
                                   name="featured" 
                                   value="1"
                                   <?php echo isset($form_data['featured']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="featured">
                                <i class="fas fa-star text-warning me-1"></i> Featured Product
                            </label>
                        </div>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="col-12">
                        <label class="form-label">Product Image</label>
                        <input type="file" 
                               class="form-control" 
                               id="image" 
                               name="image"
                               accept="image/*">
                        <div class="form-text">
                            Allowed formats: jpg, jpeg, png, gif, webp | Max size: 5MB
                        </div>
                        
                        <!-- Image Preview -->
                        <div class="image-preview" id="imagePreview" style="display: none;">
                            <img id="previewImage" src="" alt="Preview">
                            <button type="button" class="btn btn-sm btn-danger w-100 rounded-0" onclick="removeImage()">
                                <i class="fas fa-trash me-1"></i> Remove Image
                            </button>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" 
                                  name="description" 
                                  rows="6"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between">
                        <a href="products.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-submit btn-lg">
                            <i class="fas fa-save me-2"></i> Add Product
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Image preview
    document.getElementById('image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
                document.getElementById('imagePreview').style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    });

    function removeImage() {
        document.getElementById('image').value = '';
        document.getElementById('imagePreview').style.display = 'none';
    }

    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        const price = parseFloat(document.querySelector('input[name="price"]').value);
        const oldPrice = parseFloat(document.querySelector('input[name="old_price"]').value) || 0;
        
        if (oldPrice > 0 && oldPrice <= price) {
            e.preventDefault();
            alert('Old price must be greater than current price for discounts');
        }
    });
    </script>

    <?php
    require_once '../includes/footer.php';
    exit();
}

/**
 * Show edit product form
 */
function showEditProductForm($product_id) {
    $product = getProductById($product_id);
    if (!$product) {
        $_SESSION['error'] = 'Product not found';
        redirect('products.php');
    }
    
    $page_title = 'Edit Product';
    require_once '../includes/header.php';
    
    // Get categories from products table
    $categories = [];
    try {
        $db = getDB();
        $stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(Exception $e) {
        // Ignore
    }
    
    // Get form data from session if exists (for errors)
    $form_data = $_SESSION['form_data'] ?? $product;
    $form_errors = $_SESSION['form_errors'] ?? [];
    
    // Clear session data
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);
    
    // Image URL
    $image_url = '../../assets/images/products/' . $product['image'];
    $default_url = '../../assets/images/products/default.jpg';
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

    .product-form-container {
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

    .current-image {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 15px;
    }

    .current-image img {
        max-width: 200px;
        max-height: 200px;
        border-radius: 10px;
        border: 2px solid #edf2f9;
    }

    .image-preview {
        margin-top: 15px;
        max-width: 200px;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid #edf2f9;
    }

    .image-preview img {
        width: 100%;
        height: auto;
        display: block;
    }
    </style>

    <div class="product-form-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-edit me-2 text-primary"></i>
                        Edit Product
                    </h1>
                    <p class="text-muted mb-0">Edit product: <strong><?php echo htmlspecialchars($product['name']); ?></strong></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="product-action.php?action=view&id=<?php echo $product_id; ?>" class="btn btn-outline-info">
                        <i class="fas fa-eye me-2"></i> View
                    </a>
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($form_errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-15 mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                    <div>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach($form_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Edit Product Form -->
        <div class="form-card">
            <!-- Current Image -->
            <div class="current-image">
                <h6 class="mb-2">Current Image</h6>
                <img src="<?php echo $image_url; ?>" alt="Current" onerror="this.src='<?php echo $default_url; ?>'">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="remove_image" id="remove_image">
                    <label class="form-check-label text-danger" for="remove_image">
                        <i class="fas fa-trash me-1"></i> Remove current image
                    </label>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" id="productForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="row g-4">
                    <!-- Product Name -->
                    <div class="col-md-6">
                        <label class="form-label">Product Name *</label>
                        <input type="text" 
                               class="form-control" 
                               name="name" 
                               value="<?php echo htmlspecialchars($form_data['name']); ?>"
                               required
                               maxlength="255">
                    </div>
                    
                    <!-- Category -->
                    <div class="col-md-6">
                        <label class="form-label">Category</label>
                        <input type="text" 
                               class="form-control" 
                               name="category" 
                               value="<?php echo htmlspecialchars($form_data['category']); ?>"
                               list="categorySuggestions">
                        <datalist id="categorySuggestions">
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <!-- Price -->
                    <div class="col-md-4">
                        <label class="form-label">Price *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   class="form-control" 
                                   name="price" 
                                   value="<?php echo htmlspecialchars($form_data['price']); ?>"
                                   step="0.01"
                                   min="0.01"
                                   required>
                        </div>
                    </div>
                    
                    <!-- Old Price -->
                    <div class="col-md-4">
                        <label class="form-label">Old Price (Optional)</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" 
                                   class="form-control" 
                                   name="old_price" 
                                   value="<?php echo htmlspecialchars($form_data['old_price'] ?? ''); ?>"
                                   step="0.01"
                                   min="0">
                        </div>
                    </div>
                    
                    <!-- Stock -->
                    <div class="col-md-4">
                        <label class="form-label">Stock Quantity *</label>
                        <input type="number" 
                               class="form-control" 
                               name="stock" 
                               value="<?php echo htmlspecialchars($form_data['stock']); ?>"
                               min="0"
                               required>
                    </div>
                    
                    <!-- Featured -->
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   id="featured" 
                                   name="featured" 
                                   value="1"
                                   <?php echo $form_data['featured'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="featured">
                                <i class="fas fa-star text-warning me-1"></i> Featured Product
                            </label>
                        </div>
                    </div>
                    
                    <!-- New Image Upload -->
                    <div class="col-12">
                        <label class="form-label">Upload New Image (Optional)</label>
                        <input type="file" 
                               class="form-control" 
                               id="image" 
                               name="image"
                               accept="image/*">
                        <div class="form-text">
                            Leave empty to keep current image. Allowed formats: jpg, jpeg, png, gif, webp
                        </div>
                        
                        <!-- New Image Preview -->
                        <div class="image-preview" id="imagePreview" style="display: none;">
                            <img id="previewImage" src="" alt="Preview">
                            <button type="button" class="btn btn-sm btn-danger w-100 rounded-0" onclick="removeNewImage()">
                                <i class="fas fa-trash me-1"></i> Remove New Image
                            </button>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" 
                                  name="description" 
                                  rows="6"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="mt-4 pt-3 border-top">
                    <div class="d-flex justify-content-between">
                        <a href="products.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-submit btn-lg">
                            <i class="fas fa-save me-2"></i> Update Product
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Image preview for new image
    document.getElementById('image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
                document.getElementById('imagePreview').style.display = 'block';
            }
            reader.readAsDataURL(file);
        }
    });

    function removeNewImage() {
        document.getElementById('image').value = '';
        document.getElementById('imagePreview').style.display = 'none';
    }

    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        const price = parseFloat(document.querySelector('input[name="price"]').value);
        const oldPrice = parseFloat(document.querySelector('input[name="old_price"]').value) || 0;
        
        if (oldPrice > 0 && oldPrice <= price) {
            e.preventDefault();
            alert('Old price must be greater than current price for discounts');
        }
    });
    </script>

    <?php
    require_once '../includes/footer.php';
    exit();
}

/**
 * Show delete confirmation
 */
function showDeleteConfirmation($product_id) {
    $product = getProductById($product_id);
    if (!$product) {
        $_SESSION['error'] = 'Product not found';
        redirect('products.php');
    }
    
    $page_title = 'Delete Product';
    require_once '../includes/header.php';
    ?>
    
    <style>
    .delete-container {
        padding: 30px;
        background: #f4f7fc;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .delete-card {
        background: white;
        border-radius: 20px;
        padding: 40px;
        max-width: 500px;
        width: 100%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        text-align: center;
    }

    .delete-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(239, 71, 111, 0.1);
        color: var(--danger);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        margin: 0 auto 20px;
    }

    .delete-card h4 {
        color: var(--dark);
        margin-bottom: 15px;
    }

    .delete-card p {
        color: #6c757d;
        margin-bottom: 25px;
    }

    .product-name {
        font-weight: 600;
        color: var(--dark);
        padding: 10px;
        background: #f8f9fa;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .btn-group {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .btn-cancel {
        background: #6c757d;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-cancel:hover {
        background: #5a6268;
        transform: translateY(-2px);
        color: white;
    }

    .btn-delete {
        background: var(--danger);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        background: #d64161;
        transform: translateY(-2px);
    }
    </style>

    <div class="delete-container">
        <div class="delete-card">
            <div class="delete-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h4>Are you sure?</h4>
            <p>This action cannot be undone. The product will be permanently deleted.</p>
            
            <div class="product-name">
                <i class="fas fa-box me-2"></i>
                <?php echo htmlspecialchars($product['name']); ?>
            </div>
            
            <div class="btn-group">
                <a href="products.php" class="btn-cancel">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <button type="submit" class="btn-delete">
                        <i class="fas fa-trash me-2"></i> Delete Product
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php
    require_once '../includes/footer.php';
    exit();
}

/**
 * Show product details
 */
function showViewProduct($product) {
    $page_title = 'View Product';
    require_once '../includes/header.php';
    
    // Image URL
    $image_url = '../../assets/images/products/' . $product['image'];
    $default_url = '../../assets/images/products/default.jpg';
    
    // Stock status
    $stock_class = 'success';
    $stock_text = 'In Stock';
    
    if ($product['stock'] == 0) {
        $stock_class = 'danger';
        $stock_text = 'Out of Stock';
    } elseif ($product['stock'] < 10) {
        $stock_class = 'warning';
        $stock_text = 'Low Stock';
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

    .view-product-container {
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

    .product-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    }

    .product-image {
        height: 400px;
        overflow: hidden;
        background: #f8f9fa;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .product-image img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .product-details {
        padding: 30px;
    }

    .product-title {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark);
        margin-bottom: 15px;
    }

    .product-meta {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .badge-custom {
        padding: 8px 16px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .badge-featured { background: rgba(255, 193, 7, 0.1); color: #856404; }
    .badge-success { background: rgba(6, 214, 160, 0.1); color: var(--success); }
    .badge-warning { background: rgba(255, 183, 3, 0.1); color: var(--warning); }
    .badge-danger { background: rgba(239, 71, 111, 0.1); color: var(--danger); }
    .badge-info { background: rgba(76, 201, 240, 0.1); color: var(--info); }

    .price-section {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .current-price {
        font-size: 36px;
        font-weight: 700;
        color: var(--primary);
    }

    .old-price {
        font-size: 20px;
        color: #6c757d;
        text-decoration: line-through;
        margin-left: 15px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .info-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 12px;
    }

    .info-label {
        font-size: 13px;
        color: #6c757d;
        margin-bottom: 5px;
    }

    .info-value {
        font-size: 18px;
        font-weight: 600;
        color: var(--dark);
    }

    .description-section {
        background: #f8f9fa;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
    }

    .description-section h6 {
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 15px;
    }

    .action-buttons {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
    }

    .btn-action {
        padding: 12px 30px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-edit {
        background: var(--primary);
        color: white;
    }

    .btn-edit:hover {
        background: #3651c4;
        transform: translateY(-2px);
        color: white;
    }

    .btn-delete {
        background: var(--danger);
        color: white;
    }

    .btn-delete:hover {
        background: #d64161;
        transform: translateY(-2px);
        color: white;
    }
    </style>

    <div class="view-product-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-eye me-2 text-primary"></i>
                        Product Details
                    </h1>
                    <p class="text-muted mb-0">Viewing product #<?php echo $product['id']; ?></p>
                </div>
                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Products
                    </a>
                </div>
            </div>
        </div>

        <!-- Product Details Card -->
        <div class="product-card">
            <div class="row g-0">
                <!-- Product Image -->
                <div class="col-lg-5">
                    <div class="product-image">
                        <img src="<?php echo $image_url; ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             onerror="this.src='<?php echo $default_url; ?>'">
                    </div>
                </div>
                
                <!-- Product Info -->
                <div class="col-lg-7">
                    <div class="product-details">
                        <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                        
                        <div class="product-meta">
                            <?php if ($product['featured']): ?>
                            <span class="badge-custom badge-featured">
                                <i class="fas fa-star"></i> Featured Product
                            </span>
                            <?php endif; ?>
                            
                            <span class="badge-custom badge-<?php echo $stock_class; ?>">
                                <i class="fas fa-<?php 
                                    echo $product['stock'] == 0 ? 'times-circle' : 
                                         ($product['stock'] < 10 ? 'exclamation-triangle' : 'check-circle'); 
                                ?>"></i>
                                <?php echo $stock_text; ?>
                            </span>
                            
                            <!-- // Replace the existing category section with this: -->

<!-- Category Dropdown with only approved categories -->
<div class="col-md-6">
    <label class="form-label">Category</label>
    <select class="form-select" name="category_id">
        <option value="">Select Category</option>
        <?php
        try {
            $db = getDB();
            // Only show approved categories
            $stmt = $db->query("
                SELECT id, name, icon, commission_rate 
                FROM vendor_categories 
                WHERE approval_status = 'approved' 
                ORDER BY name ASC
            ");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($categories as $cat) {
                $selected = (isset($form_data['category_id']) && $form_data['category_id'] == $cat['id']) ? 'selected' : '';
                $icon_html = !empty($cat['icon']) ? '<i class="fas ' . $cat['icon'] . ' me-1"></i>' : '';
                echo "<option value='{$cat['id']}' {$selected}>{$icon_html} " . htmlspecialchars($cat['name']) . " ({$cat['commission_rate']}%)</option>";
            }
        } catch(Exception $e) {
            echo "<option value=''>Error loading categories</option>";
        }
        ?>
    </select>
    <div class="form-text">
        <i class="fas fa-info-circle me-1"></i>
        Only approved categories are shown. 
        <a href="../vendors/categories/submit-category.php" target="_blank" class="text-primary">
            Request new category
        </a>
    </div>
</div>
                        </div>
                        
                        <!-- Price Section -->
                        <div class="price-section">
                            <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                            <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']): ?>
                            <span class="old-price">$<?php echo number_format($product['old_price'], 2); ?></span>
                            <span class="badge bg-success ms-3">
                                Save $<?php echo number_format($product['old_price'] - $product['price'], 2); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Info Grid -->
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Product ID</div>
                                <div class="info-value">#<?php echo $product['id']; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Stock Quantity</div>
                                <div class="info-value"><?php echo $product['stock']; ?> units</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Created</div>
                                <div class="info-value"><?php echo date('M d, Y', strtotime($product['created_at'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Updated</div>
                                <div class="info-value">
                                    <?php echo !empty($product['updated_at']) ? date('M d, Y', strtotime($product['updated_at'])) : 'Never'; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <div class="description-section">
                            <h6>
                                <i class="fas fa-align-left me-2"></i>
                                Description
                            </h6>
                            <p class="mb-0">
                                <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description provided.')); ?>
                            </p>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="product-action.php?action=edit&id=<?php echo $product['id']; ?>" 
                               class="btn-action btn-edit">
                                <i class="fas fa-edit"></i> Edit Product
                            </a>
                            <a href="product-action.php?action=delete&id=<?php echo $product['id']; ?>" 
                               class="btn-action btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    require_once '../includes/footer.php';
    exit();
}
?>