<?php
require_once './includes/config.php';
require_once './includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('../index.php');
}

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/**
 * Get product by ID
 */
function getProductById($product_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Get product failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Get product categories
 */
function getProductCategories() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch(PDOException $e) {
        error_log("Get categories failed: " . $e->getMessage());
        return [];
    }
}

/**
 * Upload product image
 */
function uploadProductImageWrapper($file, $existing_image = '') {
    $errors = [];
    $image_name = '';
    
    if (!empty($file['name'])) {
        // Basic validation
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error";
        }
        
        // Generate unique filename
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "File type not allowed";
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "File size too large (max 5MB)";
        }
        
        if (empty($errors)) {
            $image_name = uniqid('product_', true) . '_' . time() . '.' . $file_extension;
            $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $image_name;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old image if exists
                if (!empty($existing_image) && $existing_image !== 'default.jpg') {
                    $old_image_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $existing_image;
                    if (file_exists($old_image_path)) {
                        @unlink($old_image_path);
                    }
                }
                return ['success' => true, 'image_name' => $image_name];
            } else {
                $errors[] = "Failed to move uploaded file";
            }
        }
    } else {
        // No new image uploaded
        if (!empty($existing_image)) {
            return ['success' => true, 'image_name' => $existing_image];
        } else {
            return ['success' => true, 'image_name' => 'default.jpg'];
        }
    }
    
    return ['success' => false, 'errors' => $errors];
}

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
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $old_price = !empty($_POST['old_price']) ? floatval($_POST['old_price']) : null;
        $category = sanitize($_POST['category'] ?? '');
        $stock = intval($_POST['stock'] ?? 0);
        $featured = isset($_POST['featured']) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Product name is required';
        }
        
        if (strlen($name) > 255) {
            $errors[] = 'Product name is too long (max 255 characters)';
        }
        
        if ($price <= 0) {
            $errors[] = 'Valid price is required';
        }
        
        if ($old_price !== null && $old_price <= 0) {
            $errors[] = 'Old price must be valid if provided';
        }
        
        if ($stock < 0) {
            $errors[] = 'Stock cannot be negative';
        }
        
        // Handle image upload
        $image_result = uploadProductImageWrapper($_FILES['image'] ?? []);
        
        if (!$image_result['success']) {
            $errors = array_merge($errors, $image_result['errors']);
        }
        
        if (empty($errors)) {
            try {
                $db = getDB();
                
                // Insert product into database
                $stmt = $db->prepare("
                    INSERT INTO products (name, description, price, old_price, image, category, stock, featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
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
                
                // Log activity if function exists
                if (function_exists('logUserActivity')) {
                    logUserActivity($_SESSION['user_id'], 'product_add', "Added product: {$name} (ID: {$product_id})");
                }
                
                $_SESSION['success'] = 'Product added successfully!';
                redirect('products.php');
                
            } catch(PDOException $e) {
                // Delete uploaded image if database insertion fails
                if (isset($image_result['full_path']) && file_exists($image_result['full_path'])) {
                    @unlink($image_result['full_path']);
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
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $old_price = !empty($_POST['old_price']) ? floatval($_POST['old_price']) : null;
        $category = sanitize($_POST['category'] ?? '');
        $stock = intval($_POST['stock'] ?? 0);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $remove_image = isset($_POST['remove_image']) ? true : false;
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Product name is required';
        }
        
        if (strlen($name) > 255) {
            $errors[] = 'Product name is too long (max 255 characters)';
        }
        
        if ($price <= 0) {
            $errors[] = 'Valid price is required';
        }
        
        if ($old_price !== null && $old_price <= 0) {
            $errors[] = 'Old price must be valid if provided';
        }
        
        if ($stock < 0) {
            $errors[] = 'Stock cannot be negative';
        }
        
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
            $image_result = uploadProductImageWrapper($_FILES['image'], $existing_image);
            
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
                        updated_at = CURRENT_TIMESTAMP 
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
                
                // Log activity if function exists
                if (function_exists('logUserActivity')) {
                    logUserActivity($_SESSION['user_id'], 'product_edit', "Edited product: {$name} (ID: {$product_id})");
                }
                
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
        $product = $stmt->fetch();
        
        if (!$product) {
            $_SESSION['error'] = 'Product not found';
            redirect('products.php');
        }
        
        // Delete product image if exists
        if (!empty($product['image']) && $product['image'] !== 'default.jpg') {
            $image_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $product['image'];
            if (file_exists($image_path)) {
                @unlink($image_path);
            }
        }
        
        // Delete product from database
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        // Log activity if function exists
        if (function_exists('logUserActivity')) {
            logUserActivity($_SESSION['user_id'], 'product_delete', "Deleted product: {$product['name']} (ID: {$product_id})");
        }
        
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
    
    // Get categories
    $categories = getProductCategories();
    
    // Get form data from session if exists
    $form_data = $_SESSION['form_data'] ?? [];
    $form_errors = $_SESSION['form_errors'] ?? [];
    
    // Clear session data
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);
    ?>
    
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Add New Product</h1>
                    <p class="text-muted mb-0">Add a new product to your store</p>
                </div>
                <div>
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Products
                    </a>
                </div>
            </div>
            
            <!-- Form Errors -->
            <?php if (!empty($form_errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach($form_errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Add Product Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-plus-circle me-2"></i> Product Details</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-4">
                            <!-- Product Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label">Product Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($form_data['name'] ?? ''); ?>"
                                       required
                                       maxlength="255">
                                <div class="form-text">Enter the product name (max 255 characters)</div>
                            </div>
                            
                            <!-- Category -->
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="category" 
                                       name="category" 
                                       value="<?php echo htmlspecialchars($form_data['category'] ?? ''); ?>"
                                       list="categorySuggestions">
                                <datalist id="categorySuggestions">
                                    <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text">Start typing to see existing categories</div>
                            </div>
                            
                            <!-- Price -->
                            <div class="col-md-4">
                                <label for="price" class="form-label">Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           value="<?php echo htmlspecialchars($form_data['price'] ?? ''); ?>"
                                           step="0.01"
                                           min="0.01"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Old Price -->
                            <div class="col-md-4">
                                <label for="old_price" class="form-label">Old Price (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="old_price" 
                                           name="old_price" 
                                           value="<?php echo htmlspecialchars($form_data['old_price'] ?? ''); ?>"
                                           step="0.01"
                                           min="0">
                                </div>
                                <div class="form-text">Leave empty if no discount</div>
                            </div>
                            
                            <!-- Stock -->
                            <div class="col-md-4">
                                <label for="stock" class="form-label">Stock Quantity</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="stock" 
                                       name="stock" 
                                       value="<?php echo htmlspecialchars($form_data['stock'] ?? 0); ?>"
                                       min="0"
                                       required>
                            </div>
                            
                            <!-- Featured -->
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="featured" 
                                           name="featured" 
                                           value="1"
                                           <?php echo isset($form_data['featured']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">
                                        <i class="fas fa-star me-1"></i> Featured Product
                                    </label>
                                </div>
                                <div class="form-text">Featured products appear on the homepage</div>
                            </div>
                            
                            <!-- Image Upload -->
                            <div class="col-12">
                                <label for="image" class="form-label">Product Image</label>
                                <input type="file" 
                                       class="form-control" 
                                       id="image" 
                                       name="image"
                                       accept="image/*">
                                <div class="form-text">
                                    Allowed formats: jpg, jpeg, png, gif, webp | Max size: 5MB
                                </div>
                                
                                <!-- Image Preview -->
                                <div class="mt-3" id="imagePreview" style="display: none;">
                                    <img id="previewImage" class="img-thumbnail" style="max-height: 200px;">
                                    <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeImagePreview()">
                                        <i class="fas fa-trash me-1"></i> Remove Image
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="5"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                                <div class="form-text">Detailed product description</div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex justify-content-between">
                                <a href="products.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Add Product
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <style>
    .main-content {
        margin-left: 250px;
        padding: 20px;
        background: #f8f9fa;
        min-height: 100vh;
    }
    
    @media (max-width: 991.98px) {
        .main-content {
            margin-left: 0;
            padding-top: 70px;
        }
    }
    
    .card {
        border-radius: 10px;
    }
    
    .form-label {
        font-weight: 500;
        color: #333;
    }
    </style>
    
    <script>
    // Image preview functionality
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
    
    function removeImagePreview() {
        document.getElementById('image').value = '';
        document.getElementById('previewImage').src = '';
        document.getElementById('imagePreview').style.display = 'none';
    }
    
    // Form validation
    document.getElementById('productForm').addEventListener('submit', function(e) {
        const price = parseFloat(document.getElementById('price').value);
        const oldPrice = parseFloat(document.getElementById('old_price').value) || 0;
        
        if (oldPrice > 0 && oldPrice <= price) {
            e.preventDefault();
            alert('Old price must be greater than current price for discounts');
            document.getElementById('old_price').focus();
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
    
    // Get categories
    $categories = getProductCategories();
    
    // Get form data from session if exists (for errors)
    $form_data = $_SESSION['form_data'] ?? $product;
    $form_errors = $_SESSION['form_errors'] ?? [];
    
    // Clear session data
    unset($_SESSION['form_data']);
    unset($_SESSION['form_errors']);
    ?>
    
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Edit Product</h1>
                    <p class="text-muted mb-0">Edit product details</p>
                </div>
                <div>
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Products
                    </a>
                </div>
            </div>
            
            <!-- Form Errors -->
            <?php if (!empty($form_errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach($form_errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Edit Product Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="mb-0"><i class="fas fa-edit me-2"></i> Edit Product Details</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <!-- Current Image Preview -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label">Current Image</label>
                                <div>
                                    <?php 
                                    $image_url = '../assets/images/products/' . $product['image'];
                                    $default_url = '../assets/images/products/default.jpg';
                                    ?>
                                    <img src="<?php echo $image_url; ?>" 
                                         class="img-thumbnail" 
                                         style="max-height: 200px;"
                                         onerror="this.src='<?php echo $default_url; ?>'">
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="remove_image" 
                                           name="remove_image">
                                    <label class="form-check-label text-danger" for="remove_image">
                                        <i class="fas fa-trash me-1"></i> Remove current image
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-4">
                            <!-- Product Name -->
                            <div class="col-md-6">
                                <label for="name" class="form-label">Product Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($form_data['name']); ?>"
                                       required
                                       maxlength="255">
                            </div>
                            
                            <!-- Category -->
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="category" 
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
                                <label for="price" class="form-label">Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="price" 
                                           name="price" 
                                           value="<?php echo htmlspecialchars($form_data['price']); ?>"
                                           step="0.01"
                                           min="0.01"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Old Price -->
                            <div class="col-md-4">
                                <label for="old_price" class="form-label">Old Price (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" 
                                           class="form-control" 
                                           id="old_price" 
                                           name="old_price" 
                                           value="<?php echo htmlspecialchars($form_data['old_price'] ?? ''); ?>"
                                           step="0.01"
                                           min="0">
                                </div>
                            </div>
                            
                            <!-- Stock -->
                            <div class="col-md-4">
                                <label for="stock" class="form-label">Stock Quantity</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="stock" 
                                       name="stock" 
                                       value="<?php echo htmlspecialchars($form_data['stock']); ?>"
                                       min="0"
                                       required>
                            </div>
                            
                            <!-- Featured -->
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="featured" 
                                           name="featured" 
                                           value="1"
                                           <?php echo $form_data['featured'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">
                                        <i class="fas fa-star me-1"></i> Featured Product
                                    </label>
                                </div>
                            </div>
                            
                            <!-- New Image Upload -->
                            <div class="col-12">
                                <label for="image" class="form-label">Upload New Image (Optional)</label>
                                <input type="file" 
                                       class="form-control" 
                                       id="image" 
                                       name="image"
                                       accept="image/*">
                                <div class="form-text">
                                    Leave empty to keep current image. Allowed formats: jpg, jpeg, png, gif, webp
                                </div>
                                
                                <!-- New Image Preview -->
                                <div class="mt-3" id="imagePreview" style="display: none;">
                                    <img id="previewImage" class="img-thumbnail" style="max-height: 200px;">
                                    <button type="button" class="btn btn-sm btn-danger mt-2" onclick="removeImagePreview()">
                                        <i class="fas fa-trash me-1"></i> Remove New Image
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="5"><?php echo htmlspecialchars($form_data['description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="mt-4 pt-3 border-top">
                            <div class="d-flex justify-content-between">
                                <a href="products.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Update Product
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
    // Image preview functionality
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
    
    function removeImagePreview() {
        document.getElementById('image').value = '';
        document.getElementById('previewImage').src = '';
        document.getElementById('imagePreview').style.display = 'none';
    }
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
    
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <div class="text-danger mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x"></i>
                    </div>
                    <h4 class="mb-3">Are you sure you want to delete this product?</h4>
                    <p class="text-muted mb-4">
                        Product: <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                        This action cannot be undone and will permanently remove the product.
                    </p>
                    
                    <div class="d-flex justify-content-center gap-3">
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i> Cancel
                        </a>
                        <form method="POST" action="" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash me-2"></i> Delete Product
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
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
    $image_url = '../assets/images/products/' . $product['image'];
    $default_url = '../assets/images/products/default.jpg';
    ?>
    
    <div class="dashboard-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Product Details</h1>
                    <p class="text-muted mb-0">View product information</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back
                    </a>
                    <a href="product-action.php?action=edit&id=<?php echo $product['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit me-2"></i> Edit
                    </a>
                </div>
            </div>
            
            <!-- Product Details -->
            <div class="row">
                <!-- Product Image -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body text-center">
                            <img src="<?php echo $image_url; ?>" 
                                 class="img-fluid rounded" 
                                 style="max-height: 300px;"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='<?php echo $default_url; ?>'">
                        </div>
                    </div>
                </div>
                
                <!-- Product Info -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h4 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <?php if ($product['featured']): ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-star me-1"></i> Featured
                                        </span>
                                        <?php endif; ?>
                                        <span class="badge bg-<?php echo $product['stock'] == 0 ? 'danger' : ($product['stock'] < 10 ? 'warning' : 'success'); ?>">
                                            <?php echo $product['stock'] == 0 ? 'Out of Stock' : ($product['stock'] < 10 ? 'Low Stock' : 'In Stock'); ?>
                                        </span>
                                        <?php if (!empty($product['category'])): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($product['category']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <h3 class="text-primary mb-0">$<?php echo number_format($product['price'], 2); ?></h3>
                                    <?php if (!empty($product['old_price']) && $product['old_price'] > 0): ?>
                                    <small class="text-muted text-decoration-line-through">
                                        $<?php echo number_format($product['old_price'], 2); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td class="text-muted">Stock Quantity:</td>
                                            <td class="fw-bold"><?php echo $product['stock']; ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Created:</td>
                                            <td><?php echo !empty($product['created_at']) ? date('M d, Y h:i A', strtotime($product['created_at'])) : 'N/A'; ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-sm">
                                        <tr>
                                            <td class="text-muted">Last Updated:</td>
                                            <td><?php echo !empty($product['updated_at']) ? date('M d, Y h:i A', strtotime($product['updated_at'])) : 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Product ID:</td>
                                            <td>#<?php echo $product['id']; ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div class="mb-3">
                                <h6 class="border-bottom pb-2">Description</h6>
                                <p class="mb-0">
                                    <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description provided')); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-white border-0 pt-0">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="product-action.php?action=edit&id=<?php echo $product['id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i> Edit Product
                                </a>
                                <a href="product-action.php?action=delete&id=<?php echo $product['id']; ?>" 
                                   class="btn btn-outline-danger">
                                    <i class="fas fa-trash me-2"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php
    require_once './includes/footer.php';
    exit();
}