<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    redirect('inventory.php');
}

$vendor_id = $_SESSION['user_id'];
$import_action = $_POST['import_action'] ?? 'add_new';
$update_stock = isset($_POST['update_stock']) && $_POST['update_stock'] == 'on';
$update_prices = isset($_POST['update_prices']) && $_POST['update_prices'] == 'on';

// Check if file was uploaded
if (!isset($_FILES['inventory_file']) || $_FILES['inventory_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'Please select a file to upload';
    redirect('inventory.php');
}

$file = $_FILES['inventory_file'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file type
$allowed_extensions = ['csv', 'xlsx', 'xls'];
if (!in_array($file_ext, $allowed_extensions)) {
    $_SESSION['error'] = 'Invalid file type. Please upload CSV or Excel files only.';
    redirect('inventory.php');
}

// Process the file
try {
    $db = getDB();
    $imported_count = 0;
    $updated_count = 0;
    $errors = [];
    
    if ($file_ext === 'csv') {
        // Process CSV file
        $handle = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle); // Get headers
        
        // Expected headers
        $expected_headers = ['name', 'description', 'price', 'stock', 'category'];
        $header_mapping = array_flip($headers);
        
        // Validate headers
        foreach($expected_headers as $expected) {
            if (!isset($header_mapping[$expected])) {
                $_SESSION['error'] = "CSV file must contain '{$expected}' column";
                redirect('inventory.php');
            }
        }
        
        // Process each row
        $row_number = 1;
        while (($data = fgetcsv($handle)) !== false) {
            $row_number++;
            
            // Map data to columns
            $name = $data[$header_mapping['name']] ?? '';
            $description = $data[$header_mapping['description']] ?? '';
            $price = floatval($data[$header_mapping['price']] ?? 0);
            $stock = intval($data[$header_mapping['stock']] ?? 0);
            $category = $data[$header_mapping['category']] ?? '';
            
            // Validate data
            if (empty($name) || $price <= 0) {
                $errors[] = "Row {$row_number}: Invalid name or price";
                continue;
            }
            
            // Check if product exists
            $stmt = $db->prepare("SELECT id FROM products WHERE name = ? AND vendor_id = ?");
            $stmt->execute([$name, $vendor_id]);
            $existing = $stmt->fetch();
            
            if ($existing && $import_action !== 'add_new') {
                // Update existing product
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
                    $update_values[] = $existing['id'];
                    $update_values[] = $vendor_id;
                    
                    $sql = "UPDATE products SET " . implode(', ', $update_fields) . " WHERE id = ? AND vendor_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($update_values);
                    
                    $updated_count++;
                }
                
            } elseif (!$existing || $import_action === 'add_update') {
                // Add new product or update existing
                if ($existing && $import_action === 'add_update') {
                    // Update
                    $sql = "UPDATE products SET description = ?, price = ?, stock = ?, category = ?, updated_at = NOW() 
                            WHERE id = ? AND vendor_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$description, $price, $stock, $category, $existing['id'], $vendor_id]);
                    $updated_count++;
                } else {
                    // Insert new
                    $sql = "INSERT INTO products (vendor_id, name, description, price, stock, category, created_at, updated_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$vendor_id, $name, $description, $price, $stock, $category]);
                    $imported_count++;
                }
            }
        }
        
        fclose($handle);
        
    } else {
        // For Excel files, you would need PHPExcel or PhpSpreadsheet
        $_SESSION['error'] = 'Excel import requires additional setup. Please use CSV format.';
        redirect('inventory.php');
    }
    
    // Log import activity
    logUserActivity($vendor_id, 'inventory_import', 
        "Imported {$imported_count} new products, updated {$updated_count} products");
    
    // Set success message
    $message = "Import completed successfully!";
    if ($imported_count > 0) $message .= " Added {$imported_count} new products.";
    if ($updated_count > 0) $message .= " Updated {$updated_count} existing products.";
    if (!empty($errors)) $message .= " " . count($errors) . " errors occurred.";
    
    if (!empty($errors)) {
        $_SESSION['import_errors'] = $errors;
    }
    
    $_SESSION['success'] = $message;
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
    error_log("Import Error: " . $e->getMessage());
}

redirect('inventory.php');
?>