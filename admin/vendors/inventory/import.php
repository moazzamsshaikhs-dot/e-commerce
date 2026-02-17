<?php
// admin/vendors/inventory/import.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: inventory.php');
    exit();
}

$vendor_id = $_SESSION['user_id'];
$import_action = $_POST['import_action'] ?? 'add_new';
$update_stock = isset($_POST['update_stock']) && $_POST['update_stock'] == 'on';
$update_prices = isset($_POST['update_prices']) && $_POST['update_prices'] == 'on';
$header_row = isset($_POST['header_row']) ? intval($_POST['header_row']) : 1;

// Check if file was uploaded
if (!isset($_FILES['inventory_file']) || $_FILES['inventory_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'Please select a file to upload.';
    header('Location: inventory.php');
    exit();
}

$file = $_FILES['inventory_file'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file type
$allowed_extensions = ['csv', 'xlsx', 'xls'];
if (!in_array($file_ext, $allowed_extensions)) {
    $_SESSION['error'] = 'Invalid file type. Please upload CSV or Excel files only.';
    header('Location: inventory.php');
    exit();
}

// Check file size (5MB max)
if ($file['size'] > 5 * 1024 * 1024) {
    $_SESSION['error'] = 'File size too large. Maximum size is 5MB.';
    header('Location: inventory.php');
    exit();
}

try {
    $db = getDB();
    $imported_count = 0;
    $updated_count = 0;
    $errors = [];
    $warnings = [];
    
    if ($file_ext === 'csv') {
        // Process CSV file
        $result = processCSVFile($file, $header_row, $warnings, $imported_count, $updated_count, 
                                 $db, $vendor_id, $import_action, $update_stock, $update_prices);
        
        if ($result === false) {
            // Error already set in session
            header('Location: inventory.php');
            exit();
        }
        
    } else {
        $_SESSION['error'] = 'Only CSV files are supported at this time.';
        header('Location: inventory.php');
        exit();
    }
    
    // Log import activity
    if (function_exists('logUserActivity')) {
        logUserActivity($vendor_id, 'inventory_import', 
            "Imported {$imported_count} new products, updated {$updated_count} products");
    }
    
    // Set success message
    $message = "✅ Import completed successfully!";
    if ($imported_count > 0) $message .= " Added {$imported_count} new products.";
    if ($updated_count > 0) $message .= " Updated {$updated_count} existing products.";
    
    $_SESSION['success'] = $message;
    
    if (!empty($warnings)) {
        $_SESSION['import_errors'] = $warnings;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Import failed: Database error - ' . $e->getMessage();
    error_log("Import Error: " . $e->getMessage());
} catch (Exception $e) {
    $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
    error_log("Import Error: " . $e->getMessage());
}

header('Location: inventory.php');
exit();

/**
 * Process CSV file
 */
function processCSVFile($file, $header_row, &$warnings, &$imported_count, &$updated_count, 
                        $db, $vendor_id, $import_action, $update_stock, $update_prices) {
    
    // Check if file exists and is readable
    if (!file_exists($file['tmp_name'])) {
        $_SESSION['error'] = 'Temporary file not found';
        return false;
    }
    
    // Read the entire file content
    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        $_SESSION['error'] = 'Cannot read file';
        return false;
    }
    
    // Remove BOM if present
    $bom = "\xEF\xBB\xBF";
    if (strpos($content, $bom) === 0) {
        $content = substr($content, 3);
    }
    
    // Split into lines
    $lines = explode("\n", $content);
    if (empty($lines)) {
        $_SESSION['error'] = 'File is empty';
        return false;
    }
    
    // Parse CSV lines
    $all_rows = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $row = str_getcsv($line);
        if (!empty($row)) {
            // Clean each cell
            $clean_row = array_map(function($cell) {
                return trim($cell);
            }, $row);
            $all_rows[] = $clean_row;
        }
    }
    
    if (empty($all_rows)) {
        $_SESSION['error'] = 'No valid data found in CSV file';
        return false;
    }
    
    // Get headers
    if ($header_row == 0) {
        // Auto-detect headers
        $headers = autoDetectHeaders($all_rows, $warnings);
        $data_start = findDataStartRow($all_rows, $headers);
    } else {
        // Use specified row
        $header_index = $header_row - 1;
        if (!isset($all_rows[$header_index])) {
            $_SESSION['error'] = "Header row {$header_row} not found. File has only " . count($all_rows) . " rows.";
            return false;
        }
        $headers = $all_rows[$header_index];
        $data_start = $header_row;
    }
    
    // Clean headers
    $headers = array_map('trim', $headers);
    $lower_headers = array_map('strtolower', $headers);
    
    // Create column mapping
    $column_map = [
        'name' => array_search('name', $lower_headers),
        'description' => array_search('description', $lower_headers),
        'price' => array_search('price', $lower_headers),
        'stock' => array_search('stock', $lower_headers),
        'category' => array_search('category', $lower_headers)
    ];
    
    // Check required columns
    if ($column_map['name'] === false) {
        $warnings[] = "'name' column not found. Using column 1 as product name.";
        $column_map['name'] = 0;
    }
    
    if ($column_map['price'] === false) {
        $warnings[] = "'price' column not found. Using column 3 as price.";
        $column_map['price'] = 2;
    }
    
    if ($column_map['stock'] === false) {
        $warnings[] = "'stock' column not found. Using default stock 0.";
    }
    
    // Process data rows
    for ($i = $data_start; $i < count($all_rows); $i++) {
        $row = $all_rows[$i];
        $row_num = $i + 1;
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Extract data
        $name = isset($row[$column_map['name']]) ? trim($row[$column_map['name']]) : '';
        $description = ($column_map['description'] !== false && isset($row[$column_map['description']])) 
                      ? trim($row[$column_map['description']]) : '';
        $price_cell = ($column_map['price'] !== false && isset($row[$column_map['price']])) 
                     ? trim($row[$column_map['price']]) : '0';
        $stock_cell = ($column_map['stock'] !== false && isset($row[$column_map['stock']])) 
                     ? trim($row[$column_map['stock']]) : '0';
        $category = ($column_map['category'] !== false && isset($row[$column_map['category']])) 
                   ? trim($row[$column_map['category']]) : '';
        
        // Validate name
        if (empty($name)) {
            $warnings[] = "Row {$row_num}: Product name is empty, skipping";
            continue;
        }
        
        // Parse price
        $price = parseNumericValue($price_cell);
        if ($price <= 0) {
            $warnings[] = "Row {$row_num}: Invalid price '{$price_cell}' for '{$name}', using default";
            $price = 0;
        }
        
        // Parse stock
        $stock = intval(parseNumericValue($stock_cell));
        if ($stock < 0) $stock = 0;
        
        // Check if product exists
        $stmt = $db->prepare("SELECT id FROM products WHERE name = ? AND vendor_id = ?");
        $stmt->execute([$name, $vendor_id]);
        $existing = $stmt->fetch();
        
        if ($existing && ($import_action === 'update_existing' || $import_action === 'add_update')) {
            // Update existing product
            updateProduct($db, $existing['id'], $vendor_id, $name, $description, $price, $stock, $category, 
                         $update_stock, $update_prices, $updated_count);
            
        } elseif (!$existing && ($import_action === 'add_new' || $import_action === 'add_update')) {
            // Insert new product
            insertProduct($db, $vendor_id, $name, $description, $price, $stock, $category, $imported_count);
        }
    }
    
    return true;
}

/**
 * Auto-detect headers from CSV rows
 */
function autoDetectHeaders($all_rows, &$warnings) {
    $best_score = 0;
    $best_headers = $all_rows[0];
    
    foreach ($all_rows as $index => $row) {
        $score = 0;
        $lower_row = array_map('strtolower', $row);
        
        // Check for column name patterns
        if (in_array('name', $lower_row)) $score += 10;
        if (in_array('description', $lower_row)) $score += 8;
        if (in_array('price', $lower_row)) $score += 10;
        if (in_array('stock', $lower_row)) $score += 8;
        if (in_array('category', $lower_row)) $score += 5;
        
        // Penalize rows with many numbers
        $numeric_count = 0;
        foreach ($row as $cell) {
            if (is_numeric($cell)) $numeric_count++;
        }
        $score -= $numeric_count * 2;
        
        if ($score > $best_score) {
            $best_score = $score;
            $best_headers = $row;
        }
    }
    
    if ($best_score < 5) {
        $warnings[] = "Could not reliably detect headers. Using first row.";
        return $all_rows[0];
    }
    
    return $best_headers;
}

/**
 * Find where data starts after headers
 */
function findDataStartRow($all_rows, $headers) {
    foreach ($all_rows as $index => $row) {
        if ($row === $headers) {
            return $index + 1;
        }
    }
    return 1;
}

/**
 * Parse numeric value from string
 */
function parseNumericValue($value) {
    // Remove currency symbols, commas, and spaces
    $value = preg_replace('/[^0-9.-]/', '', $value);
    return floatval($value);
}

/**
 * Insert new product
 */
function insertProduct($db, $vendor_id, $name, $description, $price, $stock, $category, &$imported_count) {
    $sql = "INSERT INTO products (vendor_id, name, description, price, stock, category, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $db->prepare($sql);
    $stmt->execute([$vendor_id, $name, $description, $price, $stock, $category]);
    $imported_count++;
}

/**
 * Update existing product
 */
function updateProduct($db, $product_id, $vendor_id, $name, $description, $price, $stock, $category, 
                      $update_stock, $update_prices, &$updated_count) {
    
    $update_fields = [];
    $update_values = [];
    
    if ($update_stock) {
        $update_fields[] = "stock = ?";
        $update_values[] = $stock;
    }
    
    if ($update_prices) {
        $update_fields[] = "price = ?";
        $update_values[] = $price;
    }
    
    if (!empty($description)) {
        $update_fields[] = "description = ?";
        $update_values[] = $description;
    }
    
    if (!empty($category)) {
        $update_fields[] = "category = ?";
        $update_values[] = $category;
    }
    
    if (!empty($update_fields)) {
        $update_fields[] = "updated_at = NOW()";
        $update_values[] = $product_id;
        $update_values[] = $vendor_id;
        
        $sql = "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = ? AND vendor_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($update_values);
        
        $updated_count++;
    }
}
?>