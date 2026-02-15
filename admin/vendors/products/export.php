<?php
// admin/vendors/products/export.php
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
        $_SESSION['error'] = 'Your vendor account is not approved.';
        header('Location: ' . SITE_URL . 'vendor/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error checking vendor status: ' . $e->getMessage();
    header('Location: ' . SITE_URL . 'vendor/dashboard.php');
    exit();
}

// Get export parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$debug = isset($_GET['debug']) ? $_GET['debug'] : 0;

// Validate parameters
$valid_types = ['all', 'low-stock', 'products', 'in-stock', 'out-of-stock', 'featured'];
$valid_formats = ['csv', 'pdf'];

if (!in_array($type, $valid_types)) {
    $type = 'all';
}

if (!in_array($format, $valid_formats)) {
    $format = 'csv';
}

// Get filename based on type and format
$filename = generateFilename($type, $format);

// Buffer output to prevent headers already sent error
ob_start();

try {
    $db = getDB();
    
    // Build query based on type
    $query = buildQuery($type, $vendor_id);
    $params = [$vendor_id];
    
    // Add threshold for low-stock
    if ($type === 'low-stock') {
        $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 10;
        $query = str_replace('{threshold}', $threshold, $query);
    }
    
    // Execute query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        $_SESSION['error'] = 'No products found to export.';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
    
    // Get vendor info for report header
    $vendor_stmt = $db->prepare("SELECT full_name, email, phone FROM users WHERE id = ?");
    $vendor_stmt->execute([$vendor_id]);
    $vendor_info = $vendor_stmt->fetch();
    
    // Clear output buffer before sending headers
    ob_clean();
    
    // Export based on format
    if ($format === 'csv') {
        exportCSV($products, $filename, $type, $vendor_info);
    } elseif ($format === 'pdf') {
        exportPDF($products, $filename, $type, $vendor_info, $debug);
    }
    
} catch(PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    $_SESSION['error'] = 'Error exporting data: ' . $e->getMessage();
    
    // Redirect back to referring page
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : SITE_URL . 'admin/vendors/products/products.php';
    header('Location: ' . $referer);
    exit();
}

/**
 * Generate filename based on type and format
 */
function generateFilename($type, $format) {
    $date = date('Y-m-d_H-i-s');
    $type_names = [
        'all' => 'all_products',
        'low-stock' => 'low_stock_products',
        'products' => 'products',
        'in-stock' => 'in_stock_products',
        'out-of-stock' => 'out_of_stock_products',
        'featured' => 'featured_products'
    ];
    
    $type_name = isset($type_names[$type]) ? $type_names[$type] : 'products';
    return "vendor_products_{$type_name}_{$date}.{$format}";
}

/**
 * Build SQL query based on export type
 */
function buildQuery($type, $vendor_id) {
    $base_query = "
        SELECT 
            p.id,
            p.name,
            p.description,
            p.price,
            p.old_price,
            p.stock,
            p.category,
            p.featured,
            p.views,
            p.sales_count as total_sold,
            p.approved_status,
            p.created_at,
            p.updated_at,
            COALESCE((
                SELECT AVG(rating) 
                FROM reviews 
                WHERE product_id = p.id
            ), 0) as avg_rating,
            COALESCE((
                SELECT COUNT(*) 
                FROM reviews 
                WHERE product_id = p.id
            ), 0) as review_count
        FROM products p
        WHERE p.vendor_id = ?
    ";
    
    switch($type) {
        case 'low-stock':
            return $base_query . " AND (p.stock < {threshold} OR p.stock = 0) ORDER BY p.stock ASC";
            
        case 'in-stock':
            return $base_query . " AND p.stock > 0 ORDER BY p.stock DESC";
            
        case 'out-of-stock':
            return $base_query . " AND p.stock = 0 ORDER BY p.name ASC";
            
        case 'featured':
            return $base_query . " AND p.featured = 1 ORDER BY p.name ASC";
            
        case 'all':
        case 'products':
        default:
            return $base_query . " ORDER BY p.created_at DESC";
    }
}

/**
 * Export as CSV
 */
function exportCSV($products, $filename, $type, $vendor_info) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add report header
    fputcsv($output, ['VENDOR PRODUCT EXPORT REPORT']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Vendor:', $vendor_info['full_name']]);
    fputcsv($output, ['Email:', $vendor_info['email']]);
    fputcsv($output, ['Export Type:', ucfirst(str_replace('-', ' ', $type))]);
    fputcsv($output, []); // Empty row for spacing
    
    // Define CSV columns
    $headers = [
        'ID',
        'Product Name',
        'Description',
        'Price ($)',
        'Old Price ($)',
        'Stock',
        'Category',
        'Featured',
        'Views',
        'Total Sold',
        'Status',
        'Avg Rating',
        'Review Count',
        'Created Date',
        'Last Updated'
    ];
    
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($products as $product) {
        $row = [
            $product['id'],
            $product['name'],
            strip_tags(substr($product['description'], 0, 100)) . (strlen($product['description']) > 100 ? '...' : ''),
            number_format($product['price'], 2),
            $product['old_price'] ? number_format($product['old_price'], 2) : '',
            $product['stock'],
            $product['category'] ?: 'Uncategorized',
            $product['featured'] ? 'Yes' : 'No',
            $product['views'],
            $product['total_sold'],
            ucfirst($product['approved_status']),
            number_format($product['avg_rating'], 1),
            $product['review_count'],
            date('Y-m-d', strtotime($product['created_at'])),
            date('Y-m-d', strtotime($product['updated_at']))
        ];
        fputcsv($output, $row);
    }
    
    // Add summary at the bottom
    fputcsv($output, []); // Empty row
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Products:', count($products)]);
    
    if ($type === 'low-stock') {
        $out_of_stock = count(array_filter($products, function($p) { return $p['stock'] == 0; }));
        $critical = count(array_filter($products, function($p) { return $p['stock'] > 0 && $p['stock'] < 5; }));
        $low = count(array_filter($products, function($p) { return $p['stock'] >= 5 && $p['stock'] < 10; }));
        
        fputcsv($output, ['Out of Stock:', $out_of_stock]);
        fputcsv($output, ['Critical (<5):', $critical]);
        fputcsv($output, ['Low (5-9):', $low]);
    }
    
    fclose($output);
    exit();
}

/**
 * Export as PDF - FIXED VERSION
 */
function exportPDF($products, $filename, $type, $vendor_info, $debug = 0) {
    // Generate HTML content
    $html = generatePDFHTML($products, $type, $vendor_info);
    
    // Try to use Dompdf if available
    $dompdf_path = __DIR__ . '/../../../vendor/autoload.php';
    
    if (file_exists($dompdf_path) && $debug == 0) {
        // Dompdf is installed - use it (WITHOUT DEBUG OPTIONS)
        return exportWithDompdf($html, $filename);
    } else {
        // Dompdf not installed or debug mode - fallback to HTML download
        if ($debug == 1) {
            // Show HTML for debugging
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit();
        }
        return exportAsHTML($html, $filename);
    }
}

/**
 * Export using Dompdf - FIXED: Removed debug options
 */
function exportWithDompdf($html, $filename) {
    try {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        
        // Check if Dompdf classes exist
        if (!class_exists('\\Dompdf\\Dompdf')) {
            throw new Exception('Dompdf class not found');
        }
        
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        // IMPORTANT: Remove all debug options that cause output
        // $options->set('debugPng', false);
        // $options->set('debugKeepTemp', false);
        // $options->set('debugCss', false);
        // $options->set('debugLayout', false);
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        // Get PDF content
        $pdf_content = $dompdf->output();
        
        // Clear any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Output PDF for download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf_content));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $pdf_content;
        exit();
        
    } catch (Exception $e) {
        error_log("Dompdf error: " . $e->getMessage());
        
        // Fallback to HTML on error
        return exportAsHTML($html, $filename);
    }
}

/**
 * Fallback: Export as HTML file
 */
function exportAsHTML($html, $filename) {
    $html_filename = str_replace('.pdf', '.html', $filename);
    
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $html_filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . strlen($html));
    
    echo $html;
    exit();
}

/**
 * Generate HTML for PDF export
 */
function generatePDFHTML($products, $type, $vendor_info) {
    $type_label = ucfirst(str_replace('-', ' ', $type));
    $date = date('Y-m-d H:i:s');
    
    // Calculate statistics
    $out_of_stock = 0;
    $critical = 0;
    $low = 0;
    $total_value = 0;
    $total_sold = 0;
    $total_views = 0;
    
    foreach ($products as $product) {
        if ($product['stock'] == 0) $out_of_stock++;
        elseif ($product['stock'] < 5) $critical++;
        elseif ($product['stock'] < 10) $low++;
        
        $total_value += $product['price'] * $product['stock'];
        $total_sold += $product['total_sold'];
        $total_views += $product['views'];
    }
    
    // Start building HTML
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Product Export - ' . $type_label . '</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                line-height: 1.5;
            }
            h1 { 
                color: #dc3545; 
                border-bottom: 3px solid #dc3545; 
                padding-bottom: 10px; 
                margin-bottom: 20px;
                font-size: 24px;
            }
            .header-info { 
                background: #f8f9fa; 
                padding: 20px; 
                margin-bottom: 30px; 
                border-radius: 8px; 
                border-left: 5px solid #dc3545;
            }
            .header-info p { 
                margin: 8px 0; 
                font-size: 14px;
            }
            .header-info strong {
                color: #333;
                width: 120px;
                display: inline-block;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 25px 0; 
                font-size: 12px; 
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            }
            th { 
                background: #dc3545; 
                color: white; 
                padding: 12px 8px; 
                text-align: left; 
                font-weight: 600;
                font-size: 12px;
            }
            td { 
                padding: 10px 8px; 
                border-bottom: 1px solid #e9ecef; 
                vertical-align: top;
            }
            tr:nth-child(even) { 
                background: #f8f9fa; 
            }
            tr:hover {
                background: #f1f3f5;
            }
            .out-of-stock { 
                background-color: #f8d7da; 
            }
            .out-of-stock td {
                color: #721c24;
            }
            .critical { 
                background-color: #fff3cd; 
            }
            .critical td {
                color: #856404;
            }
            .low-stock { 
                background-color: #d1ecf1; 
            }
            .low-stock td {
                color: #0c5460;
            }
            .summary { 
                margin-top: 25px; 
                padding: 20px; 
                background: #f8f9fa; 
                border-radius: 8px; 
                border: 1px solid #dee2e6;
            }
            .summary h3 {
                color: #333;
                margin-top: 0;
                border-bottom: 2px solid #dc3545;
                padding-bottom: 10px;
            }
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                margin-top: 15px;
            }
            .summary-item {
                background: white;
                padding: 15px;
                border-radius: 6px;
                text-align: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .summary-item .label {
                font-size: 12px;
                color: #6c757d;
                margin-bottom: 5px;
            }
            .summary-item .value {
                font-size: 22px;
                font-weight: bold;
                color: #dc3545;
            }
            .badge {
                display: inline-block;
                padding: 3px 6px;
                border-radius: 4px;
                font-size: 10px;
                font-weight: bold;
            }
            .badge-success { background: #28a745; color: white; }
            .badge-warning { background: #ffc107; color: #333; }
            .badge-danger { background: #dc3545; color: white; }
            .badge-info { background: #17a2b8; color: white; }
            .footer { 
                margin-top: 40px; 
                text-align: center; 
                color: #6c757d; 
                font-size: 11px; 
                border-top: 1px solid #dee2e6;
                padding-top: 20px;
            }
        </style>
    </head>
    <body>
        <h1>Vendor Products Export - ' . $type_label . '</h1>
        
        <div class="header-info">
            <p><strong>Vendor:</strong> ' . htmlspecialchars($vendor_info['full_name']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($vendor_info['email']) . '</p>
            <p><strong>Phone:</strong> ' . htmlspecialchars($vendor_info['phone'] ?? 'N/A') . '</p>
            <p><strong>Generated:</strong> ' . $date . '</p>
            <p><strong>Total Products:</strong> ' . count($products) . '</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Price ($)</th>
                    <th>Stock</th>
                    <th>Category</th>
                    <th>Featured</th>
                    <th>Views</th>
                    <th>Sold</th>
                    <th>Rating</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($products as $product) {
        $row_class = '';
        $stock_badge = '';
        
        if ($product['stock'] == 0) {
            $row_class = 'out-of-stock';
            $stock_badge = '<span class="badge badge-danger">Out of Stock</span>';
        } elseif ($product['stock'] < 5) {
            $row_class = 'critical';
            $stock_badge = '<span class="badge badge-warning">Critical</span>';
        } elseif ($product['stock'] < 10) {
            $row_class = 'low-stock';
            $stock_badge = '<span class="badge badge-info">Low Stock</span>';
        } else {
            $stock_badge = '<span class="badge badge-success">In Stock</span>';
        }
        
        $status_badge = '<span class="badge badge-' . 
            ($product['approved_status'] == 'approved' ? 'success' : 
            ($product['approved_status'] == 'pending' ? 'warning' : 'danger')) . '">' . 
            ucfirst($product['approved_status']) . '</span>';
        
        $html .= '
                <tr class="' . $row_class . '">
                    <td>' . $product['id'] . '</td>
                    <td><strong>' . htmlspecialchars($product['name']) . '</strong></td>
                    <td>$' . number_format($product['price'], 2) . '</td>
                    <td>' . $product['stock'] . ' ' . $stock_badge . '</td>
                    <td>' . htmlspecialchars($product['category'] ?: 'Uncategorized') . '</td>
                    <td>' . ($product['featured'] ? '✓ Yes' : '✗ No') . '</td>
                    <td>' . number_format($product['views']) . '</td>
                    <td>' . number_format($product['total_sold']) . '</td>
                    <td>' . number_format($product['avg_rating'], 1) . ' (' . $product['review_count'] . ')</td>
                    <td>' . $status_badge . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
        
        <div class="summary">
            <h3>Summary Statistics</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="label">Total Products</div>
                    <div class="value">' . count($products) . '</div>
                </div>
                <div class="summary-item">
                    <div class="label">Total Sold</div>
                    <div class="value">' . number_format($total_sold) . '</div>
                </div>
                <div class="summary-item">
                    <div class="label">Total Views</div>
                    <div class="value">' . number_format($total_views) . '</div>
                </div>
                <div class="summary-item">
                    <div class="label">Inventory Value</div>
                    <div class="value">$' . number_format($total_value, 2) . '</div>
                </div>
            </div>';
    
    if ($type === 'low-stock') {
        $html .= '
            <div style="margin-top: 20px;">
                <h4>Low Stock Breakdown</h4>
                <table style="width: 100%; margin-top: 10px; border: 1px solid #dee2e6;">
                    <tr>
                        <th style="background: #6c757d; color: white; padding: 8px;">Category</th>
                        <th style="background: #6c757d; color: white; padding: 8px;">Count</th>
                        <th style="background: #6c757d; color: white; padding: 8px;">Percentage</th>
                    </tr>
                    <tr>
                        <td>Out of Stock (0)</td>
                        <td>' . $out_of_stock . '</td>
                        <td>' . (count($products) > 0 ? round(($out_of_stock/count($products))*100, 1) : 0) . '%</td>
                    </tr>
                    <tr>
                        <td>Critical (<5)</td>
                        <td>' . $critical . '</td>
                        <td>' . (count($products) > 0 ? round(($critical/count($products))*100, 1) : 0) . '%</td>
                    </tr>
                    <tr>
                        <td>Low (5-9)</td>
                        <td>' . $low . '</td>
                        <td>' . (count($products) > 0 ? round(($low/count($products))*100, 1) : 0) . '%</td>
                    </tr>
                </table>
            </div>';
    }
    
    $html .= '
        </div>
        
        <div class="footer">
            <p>Generated by SHOPEASEPRO Vendor System | ' . date('Y-m-d H:i:s') . '</p>
            <p>This is a system-generated report. For any queries, please contact support.</p>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>