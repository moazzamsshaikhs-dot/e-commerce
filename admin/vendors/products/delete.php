<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Define SITE_URL if not defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/e-commerce/');
}

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
        $_SESSION['error'] = 'Your vendor account is not approved.';
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
    header('Location: products.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Fetch product details
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();

    if (!$product) {
        $_SESSION['error'] = 'Product not found or access denied.';
        header('Location: products.php');
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Error loading product: ' . $e->getMessage();
    header('Location: products.php');
    exit();
}

$page_title = 'Delete Product: ' . htmlspecialchars($product['name']);
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-danger">
                        <i class="fas fa-trash me-2"></i>
                        Delete Product
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>vendor/dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                            <li class="breadcrumb-item"><a href="view.php?id=<?php echo $product_id; ?>">View</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Delete</li>
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

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Warning Card -->
                <div class="card border-0 shadow-sm border-danger mb-4">
                    <div class="card-header bg-danger text-white border-0">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Delete Confirmation Required
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning" role="alert">
                            <h5 class="alert-heading">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Important: Soft Delete
                            </h5>
                            <p class="mb-0">
                                This product will be moved to a deleted products archive instead of being permanently deleted.
                                You can restore it later if needed.
                            </p>
                        </div>

                        <div class="product-info p-4 border rounded bg-light mb-4">
                            <div class="row">
                                <div class="col-md-3 text-center">
                                    <?php
                                    $image_path = SITE_URL . 'assets/images/products/' . ($product['image'] ?: 'default.png');
                                    ?>
                                    <img src="<?php echo $image_path; ?>"
                                        alt="<?php echo htmlspecialchars($product['name']); ?>"
                                        class="img-fluid rounded mb-3"
                                        style="max-height: 150px;"
                                        onerror="this.src='<?php echo SITE_URL; ?>assets/images/products/default.png'">
                                </div>
                                <div class="col-md-9">
                                    <h4 class="text-danger mb-2"><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <table class="table table-sm table-bordered">
                                        <tbody>
                                            <tr>
                                                <th width="30%">Product ID</th>
                                                <td><?php echo $product['id']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Category</th>
                                                <td><?php echo htmlspecialchars($product['category'] ?: 'Uncategorized'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Price</th>
                                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Stock</th>
                                                <td>
                                                    <?php if ($product['stock'] == 0): ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    <?php else: ?>
                                                        <?php echo $product['stock']; ?> units
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Status</th>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $product['approved_status'] == 'approved' ? 'success' : ($product['approved_status'] == 'pending' ? 'warning' : 'danger');
                                                                            ?>">
                                                        <?php echo ucfirst($product['approved_status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Created</th>
                                                <td><?php echo date('F d, Y', strtotime($product['created_at'])); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Impact Analysis -->
                        <div class="impact-analysis mb-4">
                            <h5 class="mb-3 text-danger">
                                <i class="fas fa-chart-bar me-2"></i>
                                Impact of Deletion
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-warning mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-shopping-cart me-2 text-warning"></i>
                                                Sales Impact
                                            </h6>
                                            <ul class="list-unstyled mb-0">
                                                <li><i class="fas fa-check text-danger me-2"></i> Product removed from store</li>
                                                <li><i class="fas fa-check text-danger me-2"></i> Can't be purchased</li>
                                                <li><i class="fas fa-check text-danger me-2"></i> Links will show "Not Found"</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-info mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="fas fa-database me-2 text-info"></i>
                                                Data Preservation
                                            </h6>
                                            <ul class="list-unstyled mb-0">
                                                <li><i class="fas fa-check text-success me-2"></i> Data archived in deleted table</li>
                                                <li><i class="fas fa-check text-success me-2"></i> Reviews preserved</li>
                                                <li><i class="fas fa-check text-success me-2"></i> Can be restored later</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Reason -->
                        <div class="mb-4">
                            <label for="deleteReason" class="form-label fw-bold">
                                <i class="fas fa-comment me-2"></i>
                                Reason for Deletion (Optional)
                            </label>
                            <textarea class="form-control" id="deleteReason" rows="3"
                                placeholder="Why are you deleting this product? (e.g., Out of stock permanently, Replaced by new model, etc.)"></textarea>
                            <div class="form-text">Helps with future analysis and reporting</div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center border-top pt-4">
                            <div>
                                <a href="view.php?id=<?php echo $product_id; ?>"
                                    class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button"
                                    class="btn btn-outline-warning"
                                    onclick="markAsOutOfStock()">
                                    <i class="fas fa-ban me-2"></i> Mark as Out of Stock Instead
                                </button>
                                <button type="button"
                                    class="btn btn-danger"
                                    onclick="confirmDelete()">
                                    <i class="fas fa-trash me-2"></i> Delete Product
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="confirmDeleteModalLabel">
                    <i class="fas fa-shield-alt me-2"></i>
                    Security Verification
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="security-icon mb-3">
                        <i class="fas fa-lock fa-3x text-danger"></i>
                    </div>
                    <h5 class="text-danger mb-3">Final Confirmation Required</h5>
                    <p>To delete the product, please type the product name below:</p>

                    <div class="mb-3">
                        <code class="bg-light p-2 rounded d-block">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </code>
                    </div>

                    <div class="mb-4">
                        <input type="text"
                            class="form-control text-center"
                            id="confirmProductName"
                            placeholder="Type the product name exactly as shown"
                            autocomplete="off">
                        <div class="form-text mt-2">
                            This prevents accidental deletions
                        </div>
                    </div>

                    <div class="form-check mb-3 text-start">
                        <input class="form-check-input" type="checkbox" id="confirmUnderstood">
                        <label class="form-check-label" for="confirmUnderstood">
                            I understand this action will remove the product from the store and archive it
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
                <button type="button"
                    class="btn btn-danger"
                    id="finalDeleteBtn"
                    disabled
                    onclick="processDelete()">
                    <i class="fas fa-trash me-2"></i> Confirm Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container for Notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<style>
    .card {
        border-radius: 12px;
    }

    .product-info {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    }

    .impact-analysis .card {
        transition: transform 0.2s ease;
    }

    .impact-analysis .card:hover {
        transform: translateY(-3px);
    }

    .form-check-input:checked {
        background-color: #dc3545;
        border-color: #dc3545;
    }

    .security-icon {
        width: 80px;
        height: 80px;
        background: #f8d7da;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }

    #confirmProductName:focus {
        border-color: #dc3545;
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }

    /* Toast styles */
    .toast-container {
        z-index: 9999;
    }

    .toast {
        min-width: 300px;
    }
</style>

<script>
    // FIXED: Working validation for delete button
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Delete page loaded');

        // Add event listeners for modal validation
        const confirmInput = document.getElementById('confirmProductName');
        const confirmCheckbox = document.getElementById('confirmUnderstood');
        const finalDeleteBtn = document.getElementById('finalDeleteBtn');

        if (confirmInput && confirmCheckbox && finalDeleteBtn) {
            console.log('Modal elements found');

            function validateConfirmation() {
                const productName = "<?php echo addslashes($product['name']); ?>";
                const inputValue = confirmInput.value.trim();
                const isChecked = confirmCheckbox.checked;

                console.log('Input:', inputValue, 'Expected:', productName, 'Checked:', isChecked);

                // Enable button if both conditions are met
                if (inputValue === productName && isChecked) {
                    finalDeleteBtn.disabled = false;
                    console.log('Button enabled');
                } else {
                    finalDeleteBtn.disabled = true;
                    console.log('Button disabled');
                }
            }

            // Add event listeners
            confirmInput.addEventListener('input', validateConfirmation);
            confirmInput.addEventListener('keyup', validateConfirmation);
            confirmInput.addEventListener('change', validateConfirmation);
            confirmCheckbox.addEventListener('change', validateConfirmation);

            // Initial validation
            validateConfirmation();
        } else {
            console.log('Modal elements not found');
        }
    });

    // Confirm delete function
    function confirmDelete() {
        console.log('Confirm delete called');

        if (typeof bootstrap === 'undefined') {
            alert('Bootstrap not loaded. Please refresh the page.');
            return;
        }

        const modalElement = document.getElementById('confirmDeleteModal');
        if (!modalElement) {
            console.log('Modal element not found');
            return;
        }

        const confirmModal = new bootstrap.Modal(modalElement);
        confirmModal.show();
    }

    // Process delete function
    function processDelete() {
        console.log('Process delete called');

        const deleteReason = document.getElementById('deleteReason') ? document.getElementById('deleteReason').value : '';
        const finalDeleteBtn = document.getElementById('finalDeleteBtn');

        if (!finalDeleteBtn) return;

        // Show loading
        finalDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Deleting...';
        finalDeleteBtn.disabled = true;

        // Send delete request
        const formData = new FormData();
        formData.append('product_id', <?php echo $product_id; ?>);
        formData.append('delete_reason', deleteReason);
        formData.append('action', 'soft_delete');

        console.log('Sending delete request for product ID:', <?php echo $product_id; ?>);

        fetch('delete_product.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);

                if (data.success) {
                    // Show success message
                    showToast('success', data.message);

                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmDeleteModal'));
                    if (modal) modal.hide();

                    // Redirect after delay
                    setTimeout(() => {
                        window.location.href = 'products.php';
                    }, 2000);
                } else {
                    // Show error
                    showToast('danger', data.message);
                    finalDeleteBtn.innerHTML = '<i class="fas fa-trash me-2"></i> Confirm Delete';
                    finalDeleteBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showToast('danger', 'Network error. Please try again.');
                finalDeleteBtn.innerHTML = '<i class="fas fa-trash me-2"></i> Confirm Delete';
                finalDeleteBtn.disabled = false;
            });
    }

    // Mark as out of stock function
    function markAsOutOfStock() {
        if (confirm('Mark product as out of stock instead of deleting?')) {
            // Create form and submit
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

    // Toast notification function
    function showToast(type, message) {
        // Check if bootstrap is loaded
        if (typeof bootstrap === 'undefined') {
            alert(message);
            return;
        }

        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;

        const toastId = 'toast-' + Date.now();
        const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
        const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';

        const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${icon} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        const toastElement = document.getElementById(toastId);
        if (toastElement) {
            const bsToast = new bootstrap.Toast(toastElement, {
                animation: true,
                autohide: true,
                delay: 3000
            });
            bsToast.show();

            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }
    }

    // Prevent accidental navigation
    let isDirty = false;
    const confirmInput = document.getElementById('confirmProductName');
    if (confirmInput) {
        confirmInput.addEventListener('input', function() {
            const productName = "<?php echo addslashes($product['name']); ?>";
            isDirty = this.value.trim() === productName;
        });
    }

    window.addEventListener('beforeunload', function(e) {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = 'Are you sure you want to leave? Your delete confirmation will be lost.';
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>