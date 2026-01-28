<?php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied';
    header('Location: ../../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        $db = getDB();
        $vendor_id = $_SESSION['user_id'];
        
        $file = $_FILES['csv_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            throw new Exception('Please upload a CSV file');
        }
        
        // Read CSV file
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Cannot read uploaded file');
        }
        
        // Skip header row
        fgetcsv($handle);
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        // Process rows
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 10) {
                $skipped++;
                continue;
            }
            
            list($class_name, $country, $state, $city, $postcode, $rate, $rate_name, $priority, $compound, $shipping) = $data;
            
            // Clean data
            $class_name = trim($class_name);
            $country = trim($country);
            $state = trim($state);
            $city = trim($city);
            $postcode = trim($postcode);
            $rate = floatval($rate);
            $rate_name = trim($rate_name);
            $priority = intval($priority);
            $compound = strtolower($compound) === 'yes' ? 1 : 0;
            $shipping = strtolower($shipping) === 'yes' ? 1 : 0;
            
            // Validation
            if (empty($class_name) || empty($country) || $rate <= 0) {
                $errors[] = "Invalid data in row: " . implode(',', $data);
                $skipped++;
                continue;
            }
            
            // Get tax class ID
            $stmt = $db->prepare("SELECT id FROM vendor_tax_classes WHERE vendor_id = ? AND class_name = ?");
            $stmt->execute([$vendor_id, $class_name]);
            $tax_class = $stmt->fetch();
            
            if (!$tax_class) {
                // Create new tax class if it doesn't exist
                $stmt = $db->prepare("
                    INSERT INTO vendor_tax_classes 
                    (vendor_id, class_name, class_description, sort_order, is_active, created_at)
                    VALUES (?, ?, ?, 0, 1, NOW())
                ");
                $stmt->execute([$vendor_id, $class_name, 'Imported from CSV']);
                $tax_class_id = $db->lastInsertId();
            } else {
                $tax_class_id = $tax_class['id'];
            }
            
            // Check if rate already exists
            $stmt = $db->prepare("
                SELECT id FROM vendor_tax_rates 
                WHERE vendor_id = ? AND tax_class_id = ? AND country = ? 
                AND state = ? AND city = ? AND postcode = ?
            ");
            $stmt->execute([$vendor_id, $tax_class_id, $country, $state, $city, $postcode]);
            
            if ($stmt->fetch()) {
                // Update existing rate
                $stmt = $db->prepare("
                    UPDATE vendor_tax_rates 
                    SET rate = ?, rate_name = ?, priority = ?, compound = ?, shipping = ?, updated_at = NOW()
                    WHERE vendor_id = ? AND tax_class_id = ? AND country = ? 
                    AND state = ? AND city = ? AND postcode = ?
                ");
                $stmt->execute([
                    $rate, $rate_name, $priority, $compound, $shipping,
                    $vendor_id, $tax_class_id, $country, $state, $city, $postcode
                ]);
            } else {
                // Insert new rate
                $stmt = $db->prepare("
                    INSERT INTO vendor_tax_rates 
                    (vendor_id, tax_class_id, country, state, city, postcode, 
                     rate, rate_name, priority, compound, shipping, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $vendor_id, $tax_class_id, $country, $state, $city, $postcode,
                    $rate, $rate_name, $priority, $compound, $shipping
                ]);
            }
            
            $imported++;
        }
        
        fclose($handle);
        
        // Log activity
        logActivity($vendor_id, 'tax_rates', "Imported {$imported} tax rates from CSV");
        
        $_SESSION['success'] = "Tax rates imported successfully! Imported: {$imported}, Skipped: {$skipped}";
        
        if (!empty($errors)) {
            $_SESSION['warning'] = "Some errors occurred during import: " . implode('; ', array_slice($errors, 0, 5));
        }
        
        header('Location: ../tax.php');
        
    } catch(Exception $e) {
        $_SESSION['error'] = 'Import error: ' . $e->getMessage();
        header('Location: ../tax.php');
    }
}
?>