<?php
// admin/product-bulk-upload.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Bulk Product Upload';
require_once '../includes/header.php';

// Define CSV headers
define('CSV_HEADERS', ['name', 'description', 'price', 'old_price', 'category', 'stock', 'featured', 'image']);

// Initialize variables
$upload_result = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid security token';
        redirect('product-bulk-upload.php');
    }
    
    // Process CSV upload
    $upload_result = processCSVUpload($_FILES['csv_file']);
}

/**
 * Process CSV file upload
 */
function processCSVUpload($file) {
    $errors = [];
    $success_count = 0;
    $failed_count = 0;
    $results = [];
    
    if (empty($file['name'])) {
        $errors[] = 'Please select a CSV file';
        return ['errors' => $errors];
    }
    
    // Check file extension
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_extension !== 'csv') {
        $errors[] = 'Only CSV files are allowed';
        return ['errors' => $errors];
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = 'File size too large. Maximum 5MB allowed';
        return ['errors' => $errors];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload error: ' . getUploadError($file['error']);
        return ['errors' => $errors];
    }
    
    // Open CSV file
    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
        $errors[] = 'Failed to open CSV file';
        return ['errors' => $errors];
    }
    
    // Read CSV headers
    $headers = fgetcsv($handle);
    if (!$headers) {
        $errors[] = 'CSV file is empty or invalid';
        fclose($handle);
        return ['errors' => $errors];
    }
    
    // Validate headers
    $header_errors = validateCSVHeaders($headers);
    if (!empty($header_errors)) {
        $errors = array_merge($errors, $header_errors);
        fclose($handle);
        return ['errors' => $errors];
    }
    
    // Get options from form
    $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] == 'on';
    $skip_duplicates = isset($_POST['skip_duplicates']) && $_POST['skip_duplicates'] == 'on';
    
    try {
        $db = getDB();
        $row_number = 1;
        $total_rows = 0;
        
        // First count total rows
        while (($row = fgetcsv($handle)) !== false) {
            if (!empty(array_filter($row))) {
                $total_rows++;
            }
        }
        
        // Reset file pointer
        fseek($handle, 0);
        fgetcsv($handle); // Skip headers again
        
        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            // Process row data
            $result = processCSVRow($db, $row, $headers, $row_number, $update_existing, $skip_duplicates);
            
            if ($result['success']) {
                $success_count++;
                $results[] = [
                    'row' => $row_number,
                    'status' => 'success',
                    'message' => $result['message'],
                    'product_name' => $result['product_name']
                ];
            } else {
                $failed_count++;
                $results[] = [
                    'row' => $row_number,
                    'status' => 'error',
                    'message' => $result['message'],
                    'product_name' => $result['product_name'] ?? 'Unknown'
                ];
            }
        }
        
        fclose($handle);
        
        // Log activity
        if (function_exists('logUserActivity')) {
            logUserActivity($_SESSION['user_id'], 'bulk_upload', 
                "Bulk product upload completed: {$success_count} successful, {$failed_count} failed");
        }
        
        // Set session messages
        if ($success_count > 0) {
            $_SESSION['success'] = "Successfully imported {$success_count} products. {$failed_count} failed.";
        } elseif ($failed_count > 0) {
            $_SESSION['error'] = "Failed to import products. {$failed_count} errors occurred.";
        }
        
        return [
            'success' => $success_count > 0,
            'success_count' => $success_count,
            'failed_count' => $failed_count,
            'total_rows' => $total_rows,
            'results' => $results,
            'errors' => $errors
        ];
        
    } catch (PDOException $e) {
        fclose($handle);
        $errors[] = 'Database error: ' . $e->getMessage();
        return ['errors' => $errors];
    }
}

/**
 * Validate CSV headers
 */
function validateCSVHeaders($headers) {
    $errors = [];
    
    // Check required headers
    $required_headers = ['name', 'price', 'stock'];
    foreach ($required_headers as $required) {
        if (!in_array($required, $headers)) {
            $errors[] = "Missing required column: {$required}";
        }
    }
    
    // Check for invalid headers
    foreach ($headers as $header) {
        if (!in_array($header, CSV_HEADERS)) {
            $errors[] = "Invalid column: {$header}";
        }
    }
    
    return $errors;
}

/**
 * Process a single CSV row
 */
function processCSVRow($db, $row, $headers, $row_number, $update_existing, $skip_duplicates) {
    // Ensure row has same number of columns as headers
    if (count($row) != count($headers)) {
        return [
            'success' => false,
            'message' => 'Column count mismatch',
            'product_name' => 'Unknown'
        ];
    }
    
    $data = array_combine($headers, $row);
    
    // Clean data
    $name = sanitize(trim($data['name'] ?? ''));
    $description = sanitize(trim($data['description'] ?? ''));
    $price = !empty($data['price']) ? floatval(trim($data['price'])) : 0;
    $old_price = !empty($data['old_price']) && trim($data['old_price']) !== '' ? floatval(trim($data['old_price'])) : null;
    $category = sanitize(trim($data['category'] ?? ''));
    $stock = !empty($data['stock']) ? intval(trim($data['stock'])) : 0;
    
    // Parse featured field
    $featured = 0;
    if (!empty($data['featured'])) {
        $featured_str = strtolower(trim($data['featured']));
        $featured = ($featured_str === '1' || $featured_str === 'yes' || $featured_str === 'true') ? 1 : 0;
    }
    
    $image = sanitize(trim($data['image'] ?? ''));
    
    // Validate data
    $validation_errors = validateProductData($name, $price, $stock, $old_price);
    
    if (!empty($validation_errors)) {
        return [
            'success' => false,
            'message' => implode(', ', $validation_errors),
            'product_name' => $name
        ];
    }
    
    // Check if product already exists
    $existing_product = null;
    if (!empty($name)) {
        $check_stmt = $db->prepare("SELECT id, image FROM products WHERE name = ?");
        $check_stmt->execute([$name]);
        $existing_product = $check_stmt->fetch();
    }
    
    // Handle duplicates
    if ($existing_product) {
        if ($skip_duplicates) {
            return [
                'success' => false,
                'message' => 'Skipped: Product already exists',
                'product_name' => $name
            ];
        }
        
        if (!$update_existing) {
            return [
                'success' => false,
                'message' => 'Product already exists (use update option)',
                'product_name' => $name
            ];
        }
    }
    
    // Handle image
    $image_name = 'default.jpg';
    if (!empty($image)) {
        // Check if image is a URL
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            // Download image from URL
            $image_result = downloadProductImage($image, $existing_product['image'] ?? '');
            if ($image_result['success']) {
                $image_name = $image_result['image_name'];
            }
        } elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $image)) {
            // Use existing image file
            $image_name = $image;
        }
    }
    
    // Perform database operation
    try {
        if ($existing_product && $update_existing) {
            // Update existing product
            if (isset($image_result) && $image_result['success']) {
                $image_name = $image_result['image_name'];
            } elseif (empty($image)) {
                $image_name = $existing_product['image'];
            }
            
            $stmt = $db->prepare("
                UPDATE products 
                SET description = ?, price = ?, old_price = ?, 
                    image = ?, category = ?, stock = ?, featured = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE name = ?
            ");
            
            $stmt->execute([
                $description, $price, $old_price, 
                $image_name, $category, $stock, $featured,
                $name
            ]);
            
            $message = 'Product updated';
            
        } else {
            // Insert new product
            $stmt = $db->prepare("
                INSERT INTO products (name, description, price, old_price, image, category, stock, featured) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $name, $description, $price, $old_price, 
                $image_name, $category, $stock, $featured
            ]);
            
            $message = 'Product added';
        }
        
        return [
            'success' => true,
            'message' => $message,
            'product_name' => $name
        ];
        
    } catch (PDOException $e) {
        error_log("CSV Row Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage(),
            'product_name' => $name
        ];
    }
}

/**
 * Validate product data
 */
function validateProductData($name, $price, $stock, $old_price) {
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Name is required';
    } elseif (strlen($name) > 255) {
        $errors[] = 'Name too long (max 255 chars)';
    }
    
    if ($price <= 0) {
        $errors[] = 'Price must be greater than 0';
    }
    
    if ($stock < 0) {
        $errors[] = 'Stock cannot be negative';
    }
    
    if ($old_price !== null && $old_price <= 0) {
        $errors[] = 'Old price must be valid';
    }
    
    if ($old_price !== null && $old_price <= $price) {
        $errors[] = 'Old price must be greater than current price';
    }
    
    return $errors;
}

/**
 * Download product image from URL
 */
function downloadProductImage($url, $existing_image = '') {
    $errors = [];
    
    // Get file info from URL
    $path_info = pathinfo($url);
    $file_extension = strtolower($path_info['extension'] ?? 'jpg');
    
    // Allowed extensions
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $errors[] = "Invalid image format: {$file_extension}";
        return ['success' => false, 'errors' => $errors];
    }
    
    // Generate unique filename
    $image_name = uniqid('product_', true) . '_' . time() . '.' . $file_extension;
    $upload_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $image_name;
    
    // Download image
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
        'http' => [
            'timeout' => 30,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
        ]
    ]);
    
    $image_data = @file_get_contents($url, false, $context);
    if ($image_data === false) {
        $errors[] = "Failed to download image from URL";
        return ['success' => false, 'errors' => $errors];
    }
    
    // Save image
    if (file_put_contents($upload_path, $image_data) === false) {
        $errors[] = "Failed to save image";
        return ['success' => false, 'errors' => $errors];
    }
    
    // Delete old image if exists
    if (!empty($existing_image) && $existing_image !== 'default.jpg') {
        $old_image_path = $_SERVER['DOCUMENT_ROOT'] . '/e-commerce/assets/images/products/' . $existing_image;
        if (file_exists($old_image_path)) {
            @unlink($old_image_path);
        }
    }
    
    return [
        'success' => true,
        'image_name' => $image_name,
        'path' => $upload_path
    ];
}

/**
 * Get upload error message
 */
// function getUploadError($error_code) {
//     $errors = [
//         UPLOAD_ERR_OK => 'No error',
//         UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
//         UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
//         UPLOAD_ERR_PARTIAL => 'File partially uploaded',
//         UPLOAD_ERR_NO_FILE => 'No file uploaded',
//         UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
//         UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
//         UPLOAD_ERR_EXTENSION => 'File upload stopped'
//     ];
//     return $errors[$error_code] ?? 'Unknown error';
// }
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

.bulk-upload-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

/* Header */
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

/* Form Card */
.form-card {
    background: white;
    border-radius: 20px;
    padding: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    height: 100%;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #edf2f9;
}

.form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
}

.form-section-title i {
    width: 35px;
    height: 35px;
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
    font-size: 14px;
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

/* Info Card */
.info-card {
    background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
    border-radius: 20px;
    padding: 25px;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.info-card::before {
    content: '\f0eb';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    bottom: -20px;
    right: -20px;
    font-size: 120px;
    color: rgba(67, 97, 238, 0.1);
    transform: rotate(15deg);
}

.info-card-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 20px;
    position: relative;
    z-index: 1;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
    position: relative;
    z-index: 1;
}

.info-list li {
    padding: 10px 0;
    border-bottom: 1px dashed rgba(67, 97, 238, 0.2);
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.info-list li:last-child {
    border-bottom: none;
}

.info-list i {
    width: 30px;
    height: 30px;
    background: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary);
    font-size: 14px;
    flex-shrink: 0;
}

/* Sample CSV */
.sample-csv {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 15px;
    margin-top: 20px;
}

.sample-csv pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
    font-size: 12px;
    color: var(--dark);
    max-height: 200px;
    overflow-y: auto;
}

/* Options Card */
.options-card {
    background: #f8f9fa;
    border-radius: 15px;
    padding: 20px;
    margin-top: 20px;
}

.form-check-input:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Buttons */
.btn-upload {
    background: var(--primary);
    color: white;
    border: none;
    padding: 14px 30px;
    border-radius: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-upload:hover {
    background: #3651c4;
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
    color: white;
}

.btn-download {
    background: var(--success);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-download:hover {
    background: #05b585;
    transform: translateY(-2px);
    color: white;
}

/* Results Card */
.results-card {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-top: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.result-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.result-item {
    text-align: center;
    padding: 20px;
    border-radius: 15px;
}

.result-item.success { background: rgba(6, 214, 160, 0.1); }
.result-item.danger { background: rgba(239, 71, 111, 0.1); }
.result-item.info { background: rgba(76, 201, 240, 0.1); }
.result-item.primary { background: rgba(67, 97, 238, 0.1); }

.result-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}

.result-label {
    font-size: 14px;
    color: #6c757d;
}

/* Results Table */
.results-table {
    width: 100%;
    border-collapse: collapse;
}

.results-table th {
    background: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-radius: 10px 10px 0 0;
}

.results-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #edf2f9;
}

.results-table tr:last-child td {
    border-bottom: none;
}

.row-success { background: rgba(6, 214, 160, 0.05); }
.row-error { background: rgba(239, 71, 111, 0.05); }

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slide-in {
    animation: slideIn 0.5s ease forwards;
}

.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }

/* Responsive */
@media (max-width: 768px) {
    .bulk-upload-container {
        padding: 20px;
    }
    
    .form-card {
        padding: 20px;
    }
    
    .result-summary {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<div class="bulk-upload-container">
    <!-- Page Header -->
    <div class="page-header animate-slide-in">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h2 fw-bold mb-1">
                    <i class="fas fa-cloud-upload-alt me-2 text-primary"></i>
                    Bulk Product Upload
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-file-csv me-2"></i>
                    Upload multiple products at once using CSV format
                </p>
            </div>
            <div>
                <a href="products.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>
                    Back to Products
                </a>
            </div>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-15 mb-4 animate-slide-in delay-1" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle fa-2x me-3"></i>
                <div>
                    <?php echo $_SESSION['success']; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-15 mb-4 animate-slide-in delay-1" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                <div>
                    <?php echo $_SESSION['error']; ?>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="row">
        <!-- Main Form Column -->
        <div class="col-lg-8">
            <div class="form-card animate-slide-in delay-2">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <!-- Upload Section -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-upload"></i>
                            Upload CSV File
                        </div>
                        
                        <div class="mb-4">
                            <label for="csv_file" class="form-label">
                                <i class="fas fa-file-csv me-2 text-primary"></i>
                                Select CSV File <span class="text-danger">*</span>
                            </label>
                            
                            <div class="upload-area" id="uploadArea">
                                <input type="file" 
                                       class="form-control" 
                                       id="csv_file" 
                                       name="csv_file"
                                       accept=".csv"
                                       required
                                       style="display: none;">
                                
                                <div class="text-center p-5 border border-2 border-dashed rounded-15" 
                                     style="cursor: pointer; border-color: #edf2f9; background: #f8f9fa;"
                                     onclick="document.getElementById('csv_file').click()">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                    <h5>Click to select CSV file</h5>
                                    <p class="text-muted mb-0">or drag and drop</p>
                                    <small class="text-muted">Maximum file size: 5MB</small>
                                </div>
                                
                                <div id="fileInfo" class="mt-3" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-file-csv me-2"></i>
                                        Selected file: <strong id="fileName"></strong>
                                        <span class="ms-2 text-muted">(<span id="fileSize"></span>)</span>
                                        <button type="button" class="btn-close float-end" onclick="clearFile()"></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Import Options -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-cog"></i>
                            Import Options
                        </div>
                        
                        <div class="options-card">
                            <div class="form-check mb-3">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="update_existing" 
                                       id="updateExisting">
                                <label class="form-check-label fw-bold" for="updateExisting">
                                    <i class="fas fa-sync me-2 text-warning"></i>
                                    Update existing products
                                </label>
                                <div class="form-text ps-4">
                                    If checked, products with matching names will be updated
                                </div>
                            </div>
                            
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       name="skip_duplicates" 
                                       id="skipDuplicates" checked>
                                <label class="form-check-label fw-bold" for="skipDuplicates">
                                    <i class="fas fa-forward me-2 text-info"></i>
                                    Skip duplicate products
                                </label>
                                <div class="form-text ps-4">
                                    If checked, duplicate products will be skipped without error
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-3 justify-content-end">
                        <a href="products.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-times me-2"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn-upload btn-lg" id="uploadBtn">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Upload CSV
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Info Sidebar -->
        <div class="col-lg-4">
            <div class="info-card animate-slide-in delay-3">
                <div class="info-card-title">
                    <i class="fas fa-info-circle me-2"></i>
                    CSV Format Guide
                </div>
                
                <ul class="info-list">
                    <li>
                        <i class="fas fa-check-circle text-success"></i>
                        <div>
                            <strong>Required columns:</strong>
                            <div class="small text-muted">name, price, stock</div>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-tag"></i>
                        <div>
                            <strong>Optional columns:</strong>
                            <div class="small text-muted">description, old_price, category, featured, image</div>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-star"></i>
                        <div>
                            <strong>Featured field:</strong>
                            <div class="small text-muted">Use 1/0, yes/no, or true/false</div>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-image"></i>
                        <div>
                            <strong>Image field:</strong>
                            <div class="small text-muted">Can be filename or URL</div>
                        </div>
                    </li>
                    <li>
                        <i class="fas fa-dollar-sign"></i>
                        <div>
                            <strong>Price format:</strong>
                            <div class="small text-muted">Use decimal numbers (e.g., 19.99)</div>
                        </div>
                    </li>
                </ul>
                
                <div class="sample-csv">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold mb-0">Sample CSV Data</h6>
                        <button class="btn-download btn-sm" onclick="downloadSampleCSV()">
                            <i class="fas fa-download me-1"></i> Download Template
                        </button>
                    </div>
                    <pre>name,description,price,old_price,category,stock,featured,image
iPhone 14 Pro,Latest Apple iPhone,999.99,1099.99,Electronics,50,1,iphone.jpg
Wireless Headphones,Noise cancelling,199.99,,Electronics,100,0,headphones.jpg
Running Shoes,Comfortable shoes,89.99,,Sports,200,1,
Coffee Maker,Automatic coffee maker,129.99,149.99,Home,75,0,coffee.jpg</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Results -->
    <?php if ($upload_result !== null): ?>
    <div class="results-card animate-slide-in">
        <h5 class="mb-4">
            <i class="fas fa-chart-bar me-2 text-primary"></i>
            Upload Results
        </h5>
        
        <!-- Summary -->
        <div class="result-summary">
            <div class="result-item success">
                <div class="result-value text-success"><?php echo $upload_result['success_count'] ?? 0; ?></div>
                <div class="result-label">Successful</div>
            </div>
            <div class="result-item danger">
                <div class="result-value text-danger"><?php echo $upload_result['failed_count'] ?? 0; ?></div>
                <div class="result-label">Failed</div>
            </div>
            <div class="result-item info">
                <div class="result-value text-info"><?php echo $upload_result['total_rows'] ?? 0; ?></div>
                <div class="result-label">Total Rows</div>
            </div>
            <div class="result-item primary">
                <div class="result-value text-primary">
                    <?php 
                    $total = $upload_result['total_rows'] ?? 0;
                    $success = $upload_result['success_count'] ?? 0;
                    echo $total > 0 ? round(($success / $total) * 100, 1) : 0;
                    ?>%
                </div>
                <div class="result-label">Success Rate</div>
            </div>
        </div>
        
        <!-- Detailed Results -->
        <?php if (!empty($upload_result['results'])): ?>
        <div class="mt-4">
            <h6 class="fw-bold mb-3">Detailed Results</h6>
            <div class="table-responsive">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Row #</th>
                            <th>Status</th>
                            <th>Product Name</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($upload_result['results'] as $result): ?>
                        <tr class="row-<?php echo $result['status']; ?>">
                            <td>#<?php echo $result['row']; ?></td>
                            <td>
                                <span class="badge bg-<?php echo $result['status'] === 'success' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($result['status']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($result['product_name']); ?></strong>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($result['message']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Errors -->
        <?php if (!empty($upload_result['errors'])): ?>
        <div class="alert alert-danger mt-4">
            <h6 class="alert-heading fw-bold">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Upload Errors
            </h6>
            <ul class="mb-0">
                <?php foreach($upload_result['errors'] as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <!-- Actions -->
        <div class="d-flex justify-content-between mt-4">
            <a href="products.php" class="btn btn-outline-secondary">
                <i class="fas fa-boxes me-2"></i> View Products
            </a>
            <?php if (isset($upload_result['success_count']) && $upload_result['success_count'] > 0): ?>
            <a href="products.php" class="btn btn-primary">
                <i class="fas fa-eye me-2"></i> View Uploaded Products
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// File upload handling
document.getElementById('csv_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
        document.getElementById('fileInfo').style.display = 'block';
    }
});

function clearFile() {
    document.getElementById('csv_file').value = '';
    document.getElementById('fileInfo').style.display = 'none';
}

// Download sample CSV
function downloadSampleCSV() {
    const csvContent = `name,description,price,old_price,category,stock,featured,image
iPhone 14 Pro,Latest Apple iPhone with advanced camera,999.99,1099.99,Electronics,50,1,iphone14.jpg
Wireless Headphones,Noise cancelling wireless headphones,199.99,,Electronics,100,0,headphones.jpg
Running Shoes,Comfortable running shoes for athletes,89.99,,Sports,200,1,
Coffee Maker,Automatic coffee maker with timer,129.99,149.99,Home & Living,75,0,coffee-maker.jpg`;

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'sample-products.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success toast
    showNotification('success', 'Sample CSV template downloaded successfully!');
}

// Show notification
function showNotification(type, message) {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    const toastId = 'toast-' + Date.now();
    
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        new bootstrap.Toast(toast, { autohide: true, delay: 3000 }).show();
    }
    
    setTimeout(() => toast.remove(), 3500);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Form validation
document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
    const fileInput = document.getElementById('csv_file');
    
    if (!fileInput.files.length) {
        e.preventDefault();
        alert('Please select a CSV file to upload');
        return false;
    }
    
    // Show loading state
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';
    
    return true;
});

// Drag and drop
const uploadArea = document.querySelector('.upload-area div');
if (uploadArea) {
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadArea.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadArea.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight() {
        uploadArea.style.background = 'rgba(67, 97, 238, 0.05)';
        uploadArea.style.borderColor = 'var(--primary)';
    }
    
    function unhighlight() {
        uploadArea.style.background = '#f8f9fa';
        uploadArea.style.borderColor = '#edf2f9';
    }
    
    uploadArea.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length) {
            document.getElementById('csv_file').files = files;
            const event = new Event('change');
            document.getElementById('csv_file').dispatchEvent(event);
        }
    }
}

// Auto-hide alerts
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>