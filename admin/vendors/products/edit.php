<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Define SITE_URL if not defined


// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ' . SITE_URL . 'index.php');
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
        header('Location: ' . SITE_URL . 'vendor/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'vendor/dashboard.php');
    exit();
}

// Get product ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid product ID.';
    header('Location: ' . SITE_URL . 'admin/vendors/products/products.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Fetch product details for editing
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();

    if (!$product) {
        $_SESSION['error'] = 'Product not found or access denied.';
        header('Location: ' . SITE_URL . 'admin/vendors/products/products.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading product: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'admin/vendors/products/products.php');
    exit();
}

// Get categories
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    if (empty($categories)) {
        error_log("No categories found in database");
    }
} catch (PDOException $e) {
    error_log("Categories fetch error: " . $e->getMessage());
    $categories = [];
    $_SESSION['error'] = 'Error loading categories. Please try again.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $old_price = !empty($_POST['old_price']) ? floatval($_POST['old_price']) : null;
    $category = trim($_POST['category'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);
    $featured = isset($_POST['featured']) ? 1 : 0;

    // Validation
    $errors = [];

    if (empty($name)) {
        $errors[] = 'Product name is required.';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Product name must be at least 3 characters.';
    }

    if (empty($description)) {
        $errors[] = 'Product description is required.';
    } elseif (strlen($description) < 10) {
        $errors[] = 'Description must be at least 10 characters.';
    }

    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0.';
    }

    if ($old_price !== null && $old_price <= 0) {
        $errors[] = 'Old price must be greater than 0.';
    }

    if (empty($category)) {
        $errors[] = 'Please select a category.';
    }

    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative.';
    }

    // Handle image upload
    $image_name = $product['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB

        $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = 'Invalid image type. Only JPG, PNG, GIF, and WebP are allowed.';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'Image size must be less than 5MB.';
        } else {
            // Generate unique filename
            $new_filename = 'product_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;

            // Use absolute path for upload
            $upload_dir = __DIR__ . '/../../../assets/images/products/';

            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Delete old image if not default
                if ($image_name && $image_name !== 'default.png') {
                    $old_image_path = $upload_dir . $image_name;
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $image_name = $new_filename;
            } else {
                $errors[] = 'Failed to upload image. Please check directory permissions.';
                error_log("Upload failed for: " . $_FILES['image']['tmp_name'] . " to " . $upload_path);
            }
        }
    }

    // If no errors, update product
    if (empty($errors)) {
        try {
            $db = getDB();

            $update_query = "UPDATE products SET 
                            name = :name,
                            description = :description,
                            price = :price,
                            old_price = :old_price,
                            image = :image,
                            category = :category,
                            stock = :stock,
                            featured = :featured,
                            updated_at = NOW()
                            WHERE id = :id AND vendor_id = :vendor_id";

            $stmt = $db->prepare($update_query);
            $result = $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':old_price' => $old_price,
                ':image' => $image_name,
                ':category' => $category,
                ':stock' => $stock,
                ':featured' => $featured,
                ':id' => $product_id,
                ':vendor_id' => $vendor_id
            ]);

            if ($result) {
                // Reset approval status if product was rejected
                if ($product['approved_status'] === 'rejected') {
                    $stmt = $db->prepare("UPDATE products SET approved_status = 'pending' WHERE id = ?");
                    $stmt->execute([$product_id]);
                }

                $_SESSION['success'] = 'Product updated successfully!';

                // Log activity
                if (function_exists('logUserActivity')) {
                    logUserActivity($vendor_id, 'product_edit', "Updated product: $name (ID: $product_id)");
                }

                // Redirect to view page
                header("Location: " . SITE_URL . "admin/vendors/products/view.php?id=$product_id");
                exit();
            } else {
                $errors[] = 'Failed to update product. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log("Update error: " . $e->getMessage());
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

$page_title = 'Edit Product: ' . htmlspecialchars($product['name']);
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Edit Product</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>vendor/dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                            <li class="breadcrumb-item"><a href="view.php?id=<?php echo $product_id; ?>">View</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <a href="view.php?id=<?php echo $product_id; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Product
                    </a>
                    <a href="products.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i> All Products
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                            <div class="row g-4">
                                <!-- Product Name -->
                                <div class="col-12">
                                    <label for="name" class="form-label fw-bold">
                                        Product Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                        class="form-control form-control-lg"
                                        id="name"
                                        name="name"
                                        value="<?php echo htmlspecialchars($product['name']); ?>"
                                        required
                                        placeholder="Enter product name...">
                                    <div class="form-text">
                                        Make it descriptive and clear. 3-100 characters.
                                    </div>
                                </div>

                                <!-- Category -->
                                <div class="col-md-6">
                                    <label for="category" class="form-label fw-bold">
                                        Category <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        if (!empty($categories)):
                                            foreach ($categories as $cat):
                                                $selected = ($product['category'] == $cat['slug']) ? 'selected' : '';
                                        ?>
                                                <option value="<?php echo htmlspecialchars($cat['slug']); ?>" <?php echo $selected; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php
                                            endforeach;
                                        else:
                                            ?>
                                            <option value="" disabled>No categories available</option>
                                        <?php endif; ?>
                                    </select>
                                    <?php if (empty($categories)): ?>
                                        <div class="form-text text-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            No categories found. Please add categories first.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Current Status -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Current Status</label>
                                    <?php
                                    $status_class = '';
                                    $status_icon = '';
                                    switch ($product['approved_status']) {
                                        case 'approved':
                                            $status_class = 'success';
                                            $status_icon = 'check';
                                            break;
                                        case 'pending':
                                            $status_class = 'warning';
                                            $status_icon = 'clock';
                                            break;
                                        case 'rejected':
                                            $status_class = 'danger';
                                            $status_icon = 'times';
                                            break;
                                    }
                                    ?>
                                    <div class="alert alert-<?php echo $status_class; ?> mb-0" role="alert">
                                        <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
                                        <strong><?php echo ucfirst($product['approved_status']); ?></strong>
                                        <?php if ($product['approved_status'] === 'rejected'): ?>
                                            <br>
                                            <small>Product was rejected. Edit and resubmit for approval.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Price and Old Price -->
                                <div class="col-md-6">
                                    <label for="price" class="form-label fw-bold">
                                        Price ($) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number"
                                            class="form-control"
                                            id="price"
                                            name="price"
                                            step="0.01"
                                            min="0.01"
                                            value="<?php echo number_format($product['price'], 2, '.', ''); ?>"
                                            required
                                            placeholder="0.00">
                                    </div>
                                    <div class="form-text">Current selling price</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="old_price" class="form-label fw-bold">
                                        Old Price ($) <span class="text-muted">(Optional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number"
                                            class="form-control"
                                            id="old_price"
                                            name="old_price"
                                            step="0.01"
                                            min="0.01"
                                            value="<?php echo $product['old_price'] ? number_format($product['old_price'], 2, '.', '') : ''; ?>"
                                            placeholder="Original price for discount">
                                    </div>
                                    <div class="form-text">Leave empty if no discount</div>
                                </div>

                                <!-- Stock -->
                                <div class="col-md-6">
                                    <label for="stock" class="form-label fw-bold">
                                        Stock Quantity <span class="text-danger">*</span>
                                    </label>
                                    <input type="number"
                                        class="form-control"
                                        id="stock"
                                        name="stock"
                                        min="0"
                                        value="<?php echo $product['stock']; ?>"
                                        required>
                                    <div class="form-text">
                                        Set to 0 for out of stock
                                    </div>
                                </div>

                                <!-- Featured Product -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold d-block">Featured Product</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            id="featured"
                                            name="featured"
                                            value="1"
                                            <?php echo $product['featured'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="featured">
                                            Mark as featured
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Featured products appear prominently on the store
                                    </div>
                                </div>

                                <!-- Product Image -->
                                <div class="col-12">
                                    <label for="image" class="form-label fw-bold">
                                        Product Image
                                    </label>

                                    <!-- Current Image -->
                                    <div class="mb-3">
                                        <?php
                                        $current_image = SITE_URL . 'assets/images/products/' . ($product['image'] ?: 'default.png');
                                        ?>
                                        <div class="current-image-container border rounded p-3 mb-3 text-center">
                                            <p class="text-muted mb-2">Current Image:</p>
                                            <img src="<?php echo $current_image; ?>"
                                                alt="Current product image"
                                                class="img-fluid rounded"
                                                style="max-height: 200px;"
                                                onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'">
                                        </div>
                                    </div>

                                    <!-- Image Upload -->
                                    <div class="mb-3">
                                        <input class="form-control"
                                            type="file"
                                            id="image"
                                            name="image"
                                            accept=".jpg,.jpeg,.png,.gif,.webp">
                                        <div class="form-text">
                                            Upload new image (JPG, PNG, GIF, WebP, max 5MB).
                                            Leave empty to keep current image.
                                        </div>
                                    </div>

                                    <!-- Image Preview -->
                                    <div class="image-preview-container d-none">
                                        <p class="text-muted mb-2">New Image Preview:</p>
                                        <img id="imagePreview"
                                            src="#"
                                            alt="Image preview"
                                            class="img-fluid rounded border"
                                            style="max-height: 200px; display: none;">
                                    </div>
                                </div>

                                <!-- Description -->
                                <div class="col-12">
                                    <label for="description" class="form-label fw-bold">
                                        Product Description <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control"
                                        id="description"
                                        name="description"
                                        rows="6"
                                        required
                                        placeholder="Describe your product in detail..."><?php echo htmlspecialchars($product['description']); ?></textarea>
                                    <div class="form-text">
                                        <span id="charCounter"><?php echo strlen($product['description']); ?> characters</span>
                                        (Minimum 10 characters, recommended 50+)
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="col-12">
                                    <hr class="my-4">
                                    <div class="d-flex justify-content-between">
                                        <a href="view.php?id=<?php echo $product_id; ?>"
                                            class="btn btn-outline-secondary px-4">
                                            <i class="fas fa-times me-2"></i> Cancel
                                        </a>
                                        <button type="submit"
                                            class="btn btn-primary px-4"
                                            id="submitBtn">
                                            <i class="fas fa-save me-2"></i> Update Product
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="card border-0 shadow-sm border-danger mt-4">
                    <div class="card-header bg-danger text-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Danger Zone
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            These actions are irreversible. Please proceed with caution.
                        </p>
                        <div class="d-flex flex-wrap gap-3">
                            <button type="button"
                                class="btn btn-outline-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteModal">
                                <i class="fas fa-trash me-2"></i> Delete Product
                            </button>

                            <?php if ($product['approved_status'] === 'approved'): ?>
                                <button type="button"
                                    class="btn btn-outline-warning"
                                    onclick="markAsOutOfStock()">
                                    <i class="fas fa-times me-2"></i> Mark as Out of Stock
                                </button>
                            <?php endif; ?>

                            <?php if ($product['featured']): ?>
                                <button type="button"
                                    class="btn btn-outline-info"
                                    onclick="removeFeatured()">
                                    <i class="fas fa-star me-2"></i> Remove Featured Status
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Delete Product
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone!
                </div>
                <p>Are you sure you want to delete the product:</p>
                <h5 class="text-center text-danger mb-4">
                    "<?php echo htmlspecialchars($product['name']); ?>"
                </h5>
                <p class="text-muted">
                    This will permanently remove the product from the store.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <a href="delete.php?id=<?php echo $product_id; ?>"
                    class="btn btn-danger"
                    onclick="return confirm('Are you absolutely sure? This cannot be undone.')">
                    <i class="fas fa-trash me-2"></i> Delete Product
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .form-label {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .form-control,
    .form-select {
        border-radius: 8px;
        padding: 0.75rem;
        border: 1px solid #dee2e6;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #4361ee;
        box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
    }

    .form-switch .form-check-input:checked {
        background-color: #4361ee;
        border-color: #4361ee;
    }

    .current-image-container {
        background: #f8f9fa;
    }

    #imagePreview {
        border: 2px dashed #dee2e6;
    }

    .card {
        border-radius: 12px;
    }

    .modal-content {
        border-radius: 12px;
        border: none;
    }

    .modal-header {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    }
</style>

<script>
    // FIXED: All elements are checked before use
    document.addEventListener('DOMContentLoaded', function() {
        // Character counter
        const descriptionField = document.getElementById('description');
        const charCounter = document.getElementById('charCounter');

        if (descriptionField && charCounter) {
            function updateCharCounter() {
                const charCount = descriptionField.value.length;
                charCounter.textContent = `${charCount} characters`;
                charCounter.className = charCount < 10 ? 'form-text text-danger' :
                    (charCount < 50 ? 'form-text text-warning' : 'form-text text-success');
            }
            updateCharCounter();
            descriptionField.addEventListener('input', updateCharCounter);
        }

        // Image preview
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const previewContainer = document.querySelector('.image-preview-container');

        if (imageInput && imagePreview && previewContainer) {
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = 'block';
                        previewContainer.classList.remove('d-none');
                    }
                    reader.readAsDataURL(this.files[0]);
                } else {
                    imagePreview.style.display = 'none';
                    previewContainer.classList.add('d-none');
                }
            });
        }

        // Price validation
        const priceInput = document.getElementById('price');
        const oldPriceInput = document.getElementById('old_price');

        if (priceInput && oldPriceInput) {
            function validatePrices() {
                const price = parseFloat(priceInput.value) || 0;
                const oldPrice = parseFloat(oldPriceInput.value) || 0;

                if (oldPrice > 0 && oldPrice <= price) {
                    alert('Old price must be greater than current price to show discount.');
                    oldPriceInput.value = '';
                    oldPriceInput.focus();
                }
            }
            priceInput.addEventListener('blur', validatePrices);
            oldPriceInput.addEventListener('blur', validatePrices);
        }

        // Form submission
        const productForm = document.getElementById('productForm');
        const submitBtn = document.getElementById('submitBtn');

        if (productForm && submitBtn) {
            productForm.addEventListener('submit', function(e) {
                const name = document.getElementById('name');
                const description = document.getElementById('description');
                const price = document.getElementById('price');
                const category = document.getElementById('category');

                if (name && name.value.trim().length < 3) {
                    e.preventDefault();
                    alert('Product name must be at least 3 characters.');
                    name.focus();
                    return;
                }

                if (description && description.value.trim().length < 10) {
                    e.preventDefault();
                    alert('Description must be at least 10 characters.');
                    description.focus();
                    return;
                }

                if (price) {
                    const priceValue = parseFloat(price.value);
                    if (priceValue <= 0 || isNaN(priceValue)) {
                        e.preventDefault();
                        alert('Price must be greater than 0.');
                        price.focus();
                        return;
                    }
                }

                if (category && !category.value) {
                    e.preventDefault();
                    alert('Please select a category.');
                    category.focus();
                    return;
                }

                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';
                submitBtn.disabled = true;
            });
        }
    });

    // Danger zone functions
    function markAsOutOfStock() {
        if (confirm('Mark product as out of stock instead of deleting?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'update_stock.php';
            form.style.display = 'none';

            const productId = document.createElement('input');
            productId.type = 'hidden';
            productId.name = 'product_id';
            productId.value = <?php echo $product_id; ?>;

            const stock = document.createElement('input');
            stock.type = 'hidden';
            stock.name = 'stock';
            stock.value = '0';

            form.appendChild(productId);
            form.appendChild(stock);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function removeFeatured() {
        if (confirm('Remove featured status from this product?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'update_featured.php';
            form.style.display = 'none';

            const productId = document.createElement('input');
            productId.type = 'hidden';
            productId.name = 'product_id';
            productId.value = <?php echo $product_id; ?>;

            const featured = document.createElement('input');
            featured.type = 'hidden';
            featured.name = 'featured';
            featured.value = '0';

            form.appendChild(productId);
            form.appendChild(featured);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Prevent form resubmission
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>

<?php require_once '../../includes/footer.php'; ?>