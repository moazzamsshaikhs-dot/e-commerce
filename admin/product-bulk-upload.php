<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('../index.php');
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
        redirect('products.php');
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
function getUploadError($error_code) {
    $errors = [
        UPLOAD_ERR_OK => 'No error',
        UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'File partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION => 'File upload stopped'
    ];
    return $errors[$error_code] ?? 'Unknown error';
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Bulk Product Upload</h1>
                <p class="text-muted mb-0">Upload multiple products using CSV file</p>
            </div>
            <div>
                <a href="products.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Products
                </a>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Upload Form -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-upload me-2"></i> Upload CSV File</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            
                            <div class="mb-4">
                                <label for="csv_file" class="form-label">Select CSV File *</label>
                                <input type="file" 
                                       class="form-control" 
                                       id="csv_file" 
                                       name="csv_file"
                                       accept=".csv"
                                       required>
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Maximum file size: 5MB • Only CSV files allowed
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Import Options</label>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="update_existing" 
                                           id="updateExisting">
                                    <label class="form-check-label" for="updateExisting">
                                        <i class="fas fa-sync me-1"></i> Update existing products
                                    </label>
                                    <div class="form-text small">
                                        If checked, products with matching names will be updated instead of skipped
                                    </div>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="skip_duplicates" 
                                           id="skipDuplicates" checked>
                                    <label class="form-check-label" for="skipDuplicates">
                                        <i class="fas fa-forward me-1"></i> Skip duplicate products
                                    </label>
                                    <div class="form-text small">
                                        If checked, duplicate products will be skipped without error
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6 class="alert-heading">
                                    <i class="fas fa-file-csv me-2"></i> CSV Format Requirements
                                </h6>
                                <ul class="mb-0">
                                    <li><strong>Required headers:</strong> name, price, stock</li>
                                    <li><strong>Optional headers:</strong> description, old_price, category, featured, image</li>
                                    <li><strong>Featured field:</strong> Use 1/0, yes/no, or true/false</li>
                                    <li><strong>Image field:</strong> Can be filename or URL (optional)</li>
                                    <li>Download <a href="#" onclick="downloadSampleCSV(); return false;" class="text-primary">sample CSV template</a></li>
                                </ul>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary" id="uploadBtn">
                                    <i class="fas fa-upload me-2"></i> Upload CSV
                                </button>
                                <a href="products.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Quick Guide -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i> Quick Guide</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <small>CSV must have proper headers</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <small>Name, price, stock are required</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <small>Use commas to separate values</small>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <small>Enclose text with commas in quotes</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sample CSV -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0"><i class="fas fa-table me-2"></i> Sample CSV Data</h6>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-3 rounded small" style="font-size: 12px; max-height: 300px; overflow-y: auto;">
name,description,price,old_price,category,stock,featured,image
iPhone 14 Pro,Latest Apple iPhone with advanced camera,999.99,1099.99,Electronics,50,1,iphone14.jpg
Wireless Headphones,Noise cancelling wireless headphones,199.99,,Electronics,100,0,headphones.jpg
Running Shoes,Comfortable running shoes for athletes,89.99,,Sports,200,1,
Coffee Maker,Automatic coffee maker with timer,129.99,149.99,Home & Living,75,0,coffee-maker.jpg
Designer Watch,Luxury watch with premium finish,499.99,599.99,Fashion,30,1,watch.jpg
Yoga Mat,Premium yoga mat for workouts,29.99,,Sports,300,0,
Bluetooth Speaker,Portable Bluetooth speaker,79.99,99.99,Electronics,120,1,speaker.jpg
Winter Jacket,Warm winter jacket for cold weather,149.99,179.99,Fashion,80,0,jacket.jpg
                        </pre>
                        <div class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadSampleCSV()">
                                <i class="fas fa-download me-1"></i> Download Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upload Results -->
        <?php if ($upload_result !== null): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h6 class="mb-0">
                    <i class="fas fa-chart-bar me-2"></i> Upload Results
                    <span class="badge bg-primary ms-2">
                        <?php echo ($upload_result['total_rows'] ?? 0); ?> Total Rows
                    </span>
                </h6>
            </div>
            <div class="card-body">
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success bg-opacity-10 border-success">
                            <div class="card-body text-center">
                                <h3 class="text-success mb-1"><?php echo $upload_result['success_count'] ?? 0; ?></h3>
                                <p class="text-success mb-0 small">Successful</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger bg-opacity-10 border-danger">
                            <div class="card-body text-center">
                                <h3 class="text-danger mb-1"><?php echo $upload_result['failed_count'] ?? 0; ?></h3>
                                <p class="text-danger mb-0 small">Failed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info bg-opacity-10 border-info">
                            <div class="card-body text-center">
                                <h3 class="text-info mb-1"><?php echo $upload_result['total_rows'] ?? 0; ?></h3>
                                <p class="text-info mb-0 small">Total Rows</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary bg-opacity-10 border-primary">
                            <div class="card-body text-center">
                                <h3 class="text-primary mb-1"><?php 
                                    $total = $upload_result['total_rows'] ?? 0;
                                    $success = $upload_result['success_count'] ?? 0;
                                    echo $total > 0 ? round(($success / $total) * 100, 1) : 0;
                                ?>%</h3>
                                <p class="text-primary mb-0 small">Success Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Results -->
                <?php if (!empty($upload_result['results'])): ?>
                <div class="mb-3">
                    <h6 class="border-bottom pb-2 mb-3">Detailed Results</h6>
                    
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th width="80">Row #</th>
                                    <th width="100">Status</th>
                                    <th>Product Name</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($upload_result['results'] as $result): ?>
                                <tr class="<?php echo $result['status'] === 'success' ? 'table-success' : 'table-danger'; ?>">
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
                <div class="alert alert-danger">
                    <h6 class="alert-heading">
                        <i class="fas fa-exclamation-triangle me-2"></i> Upload Errors
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
                    <div>
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="fas fa-boxes me-2"></i> View Products
                        </a>
                    </div>
                    <div>
                        <?php if (isset($upload_result['success_count']) && $upload_result['success_count'] > 0): ?>
                        <a href="products.php" class="btn btn-primary">
                            <i class="fas fa-eye me-2"></i> View Uploaded Products
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<style>
.dashboard-container {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    width: 250px;
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    z-index: 1000;
    overflow-y: auto;
    background: #fff;
    box-shadow: 0 0 15px rgba(0,0,0,0.1);
}

.main-content {
    flex: 1;
    margin-left: 250px;
    padding: 20px;
    background: #f8f9fa;
    min-height: 100vh;
}

@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    .sidebar.active {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
        padding-top: 70px;
    }
}

.card {
    border-radius: 10px;
    border: 1px solid #e9ecef;
}

.card-header {
    background: white;
    border-bottom: 1px solid #e9ecef;
}

.badge {
    font-weight: 500;
}

.table-sm th,
.table-sm td {
    padding: 8px 12px;
}

pre {
    margin: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.list-group-item {
    border-left: 0;
    border-right: 0;
    padding: 12px 0;
}

.list-group-item:first-child {
    border-top: 0;
}

.list-group-item:last-child {
    border-bottom: 0;
}
</style>

<script>
function downloadSampleCSV() {
    // Create sample CSV content
    const csvContent = `name,description,price,old_price,category,stock,featured,image
iPhone 14 Pro,Latest Apple iPhone with advanced camera,999.99,1099.99,Electronics,50,1,iphone14.jpg
Wireless Headphones,Noise cancelling wireless headphones,199.99,,Electronics,100,0,headphones.jpg
Running Shoes,Comfortable running shoes for athletes,89.99,,Sports,200,1,
Coffee Maker,Automatic coffee maker with timer,129.99,149.99,Home & Living,75,0,coffee-maker.jpg
Designer Watch,Luxury watch with premium finish,499.99,599.99,Fashion,30,1,watch.jpg
Yoga Mat,Premium yoga mat for workouts,29.99,,Sports,300,0,
Bluetooth Speaker,Portable Bluetooth speaker,79.99,99.99,Electronics,120,1,speaker.jpg
Winter Jacket,Warm winter jacket for cold weather,149.99,179.99,Fashion,80,0,jacket.jpg`;

    // Create download link
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    
    link.setAttribute('href', url);
    link.setAttribute('download', 'sample-products.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    const alert = document.createElement('div');
    alert.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
    alert.style.zIndex = '9999';
    alert.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        Sample CSV template downloaded successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    // Auto remove alert after 3 seconds
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    }, 3000);
}

// Add form validation
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.getElementById('uploadForm');
    const uploadBtn = document.getElementById('uploadBtn');
    
    if (uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('csv_file');
            
            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a CSV file to upload');
                return false;
            }
            
            // Check file extension
            const fileName = fileInput.files[0].name;
            const fileExt = fileName.split('.').pop().toLowerCase();
            
            if (fileExt !== 'csv') {
                e.preventDefault();
                alert('Please select a CSV file (.csv)');
                return false;
            }
            
            // Show loading state
            if (uploadBtn) {
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading...';
            }
            
            return true;
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>