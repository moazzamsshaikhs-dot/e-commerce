<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

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

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $export_format = $_POST['format'] ?? 'csv';
    $export_type = $_POST['type'] ?? 'all';
    
    // Build query based on export type
    $where_conditions = ["p.vendor_id = :vendor_id"];
    $params = [':vendor_id' => $vendor_id];
    
    switch ($export_type) {
        case 'approved':
            $where_conditions[] = "p.approved_status = 'approved'";
            break;
        case 'pending':
            $where_conditions[] = "p.approved_status = 'pending'";
            break;
        case 'low_stock':
            $where_conditions[] = "p.stock > 0 AND p.stock < 10";
            break;
        case 'out_of_stock':
            $where_conditions[] = "p.stock = 0";
            break;
        case 'featured':
            $where_conditions[] = "p.featured = 1";
            break;
    }
    
    $where_clause = implode(" AND ", $where_conditions);
    
    try {
        $db = getDB();
        $query = "SELECT 
                    p.id,
                    p.name,
                    p.description,
                    p.price,
                    p.old_price,
                    p.category,
                    p.stock,
                    p.featured,
                    p.approved_status,
                    p.views,
                    p.sales_count,
                    p.created_at,
                    p.updated_at,
                    c.name as category_name,
                    (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) as review_count,
                    (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id) as avg_rating
                  FROM products p 
                  LEFT JOIN categories c ON p.category = c.slug 
                  WHERE $where_clause 
                  ORDER BY p.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Get vendor info for export
        $stmt = $db->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
        $stmt->execute([$vendor_id]);
        $vendor_info = $stmt->fetch();
        
        // Create export data
        $export_data = [];
        
        // Add header row
        $export_data[] = [
            'Product ID',
            'Product Name',
            'Description',
            'Price ($)',
            'Old Price ($)',
            'Category',
            'Stock',
            'Featured',
            'Status',
            'Views',
            'Sales',
            'Reviews',
            'Avg Rating',
            'Created Date',
            'Last Updated'
        ];
        
        // Add data rows
        foreach ($products as $product) {
            $export_data[] = [
                $product['id'],
                $product['name'],
                strip_tags($product['description']),
                number_format($product['price'], 2),
                $product['old_price'] ? number_format($product['old_price'], 2) : '',
                $product['category_name'] ?: $product['category'],
                $product['stock'],
                $product['featured'] ? 'Yes' : 'No',
                ucfirst($product['approved_status']),
                $product['views'],
                $product['sales_count'],
                $product['review_count'],
                number_format($product['avg_rating'] ?? 0, 1),
                date('Y-m-d', strtotime($product['created_at'])),
                date('Y-m-d H:i', strtotime($product['updated_at']))
            ];
        }
        
        // Generate filename
        $filename = 'products_export_' . date('Y-m-d_H-i-s') . '_' . $export_type;
        
        if ($export_format === 'csv') {
            exportToCSV($export_data, $filename . '.csv', $vendor_info);
        } elseif ($export_format === 'excel') {
            exportToExcel($export_data, $filename . '.xlsx', $vendor_info);
        } else {
            exportToJSON($export_data, $filename . '.json', $vendor_info);
        }
        
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error generating export: ' . $e->getMessage();
        header('Location: ' . SITE_URL . 'admin/vendors/products/export.php');
        exit();
    }
}

$page_title = 'Export Products';
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">
                        <i class="fas fa-download me-2"></i>
                        Export Products
                    </h1>
                    <p class="text-muted mb-0">Export your product data in various formats</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="products.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Products
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Export Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <form method="POST" action="" id="exportForm">
                            <div class="row g-4">
                                <!-- Export Format -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-file me-2"></i>
                                        Export Format
                                    </label>
                                    <div class="form-options">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="format" id="formatCsv" value="csv" checked>
                                            <label class="form-check-label" for="formatCsv">
                                                <div class="d-flex align-items-center">
                                                    <div class="format-icon me-3">
                                                        <i class="fas fa-file-csv fa-2x text-success"></i>
                                                    </div>
                                                    <div>
                                                        <strong>CSV Format</strong><br>
                                                        <small class="text-muted">Comma separated values, compatible with Excel</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="format" id="formatExcel" value="excel">
                                            <label class="form-check-label" for="formatExcel">
                                                <div class="d-flex align-items-center">
                                                    <div class="format-icon me-3">
                                                        <i class="fas fa-file-excel fa-2x text-success"></i>
                                                    </div>
                                                    <div>
                                                        <strong>Excel Format</strong><br>
                                                        <small class="text-muted">Microsoft Excel file (.xlsx)</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="format" id="formatJson" value="json">
                                            <label class="form-check-label" for="formatJson">
                                                <div class="d-flex align-items-center">
                                                    <div class="format-icon me-3">
                                                        <i class="fas fa-file-code fa-2x text-warning"></i>
                                                    </div>
                                                    <div>
                                                        <strong>JSON Format</strong><br>
                                                        <small class="text-muted">JavaScript Object Notation for developers</small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Export Type -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-filter me-2"></i>
                                        Export Type
                                    </label>
                                    <div class="form-options">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="type" id="typeAll" value="all" checked>
                                            <label class="form-check-label" for="typeAll">
                                                <span class="badge bg-primary me-2">All</span>
                                                <strong>All Products</strong><br>
                                                <small class="text-muted">Export all your products</small>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="type" id="typeApproved" value="approved">
                                            <label class="form-check-label" for="typeApproved">
                                                <span class="badge bg-success me-2">Live</span>
                                                <strong>Approved Products Only</strong><br>
                                                <small class="text-muted">Products currently live on store</small>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="type" id="typePending" value="pending">
                                            <label class="form-check-label" for="typePending">
                                                <span class="badge bg-warning me-2">Pending</span>
                                                <strong>Pending Products Only</strong><br>
                                                <small class="text-muted">Products awaiting approval</small>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="type" id="typeLowStock" value="low_stock">
                                            <label class="form-check-label" for="typeLowStock">
                                                <span class="badge bg-danger me-2">Low</span>
                                                <strong>Low Stock Products</strong><br>
                                                <small class="text-muted">Products with less than 10 units</small>
                                            </label>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="type" id="typeFeatured" value="featured">
                                            <label class="form-check-label" for="typeFeatured">
                                                <span class="badge bg-info me-2">Featured</span>
                                                <strong>Featured Products Only</strong><br>
                                                <small class="text-muted">Products marked as featured</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Export Options -->
                                <div class="col-12">
                                    <hr class="my-4">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-cogs me-2"></i>
                                        Export Options
                                    </label>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="includeImages" name="include_images" value="1">
                                                <label class="form-check-label" for="includeImages">
                                                    <strong>Include Image URLs</strong><br>
                                                    <small class="text-muted">Add product image URLs to export</small>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="includeReviews" name="include_reviews" value="1" checked>
                                                <label class="form-check-label" for="includeReviews">
                                                    <strong>Include Review Summary</strong><br>
                                                    <small class="text-muted">Add review counts and average ratings</small>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="includeDates" name="include_dates" value="1" checked>
                                                <label class="form-check-label" for="includeDates">
                                                    <strong>Include Date Information</strong><br>
                                                    <small class="text-muted">Add created and updated dates</small>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="compressFile" name="compress_file" value="1">
                                                <label class="form-check-label" for="compressFile">
                                                    <strong>Compress Export File</strong><br>
                                                    <small class="text-muted">Create ZIP file for large exports</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Export Preview -->
                                <div class="col-12">
                                    <hr class="my-4">
                                    <div class="export-preview">
                                        <h6 class="mb-3 fw-bold">
                                            <i class="fas fa-eye me-2"></i>
                                            Export Preview
                                        </h6>
                                        <div class="table-responsive border rounded">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Product ID</th>
                                                        <th>Product Name</th>
                                                        <th>Price</th>
                                                        <th>Category</th>
                                                        <th>Stock</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="previewTable">
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted py-3">
                                                            Select export options to see preview
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="form-text mt-2">
                                            Shows first 5 records based on your selection
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Form Actions -->
                                <div class="col-12">
                                    <hr class="my-4">
                                    <div class="d-flex justify-content-between">
                                        <a href="products.php" class="btn btn-outline-secondary px-4">
                                            <i class="fas fa-times me-2"></i> Cancel
                                        </a>
                                        <div class="d-flex gap-2">
                                            <button type="button" 
                                                    class="btn btn-outline-primary px-4"
                                                    onclick="updatePreview()">
                                                <i class="fas fa-sync me-2"></i> Update Preview
                                            </button>
                                            <button type="submit" 
                                                    class="btn btn-success px-4"
                                                    id="exportBtn">
                                                <i class="fas fa-download me-2"></i> Export Products
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Previous Exports -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white border-bottom-0 py-3">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2"></i>
                            Recent Exports
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        try {
                            $db = getDB();
                            $stmt = $db->prepare("SELECT * FROM import_export_logs 
                                                  WHERE user_id = ? AND type = 'export' 
                                                  ORDER BY created_at DESC 
                                                  LIMIT 5");
                            $stmt->execute([$vendor_id]);
                            $exports = $stmt->fetchAll();
                            
                            if (count($exports) > 0):
                        ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>File</th>
                                            <th>Type</th>
                                            <th>Records</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($exports as $export): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y H:i', strtotime($export['created_at'])); ?></td>
                                            <td>
                                                <code><?php echo htmlspecialchars($export['filename']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo strtoupper(pathinfo($export['filename'], PATHINFO_EXTENSION)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($export['settings_count']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $export['status'] == 'success' ? 'success' : 
                                                         ($export['status'] == 'failed' ? 'danger' : 'warning');
                                                ?>">
                                                    <?php echo ucfirst($export['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($export['status'] == 'success' && file_exists('../../../exports/' . $export['filename'])): ?>
                                                    <a href="../../../exports/<?php echo $export['filename']; ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-3">No previous exports found.</p>
                        <?php endif; ?>
                        } catch(PDOException $e) {
                            echo '<p class="text-danger text-center py-3">Error loading export history.</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.form-options .form-check {
    padding: 12px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 10px;
    transition: all 0.2s ease;
}

.form-options .form-check:hover {
    border-color: #4361ee;
    background-color: rgba(67, 97, 238, 0.05);
}

.form-options .form-check-input:checked ~ .form-check-label {
    color: #4361ee;
}

.form-options .form-check-input:checked {
    background-color: #4361ee;
    border-color: #4361ee;
}

.format-icon {
    width: 40px;
    text-align: center;
}

.export-preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

#previewTable tr td {
    font-size: 0.85rem;
    padding: 8px;
}
</style>

<script>
// Update preview function
function updatePreview() {
    const exportBtn = document.getElementById('exportBtn');
    const previewTable = document.getElementById('previewTable');
    
    // Show loading
    previewTable.innerHTML = `
        <tr>
            <td colspan="6" class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading preview...</p>
            </td>
        </tr>
    `;
    
    // Get selected options
    const format = document.querySelector('input[name="format"]:checked').value;
    const type = document.querySelector('input[name="type"]:checked').value;
    
    // Send AJAX request for preview
    fetch('export_preview.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `type=${type}&format=${format}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update preview table
            let html = '';
            
            if (data.products.length > 0) {
                data.products.forEach(product => {
                    html += `
                        <tr>
                            <td>${product.id}</td>
                            <td>${product.name}</td>
                            <td>$${product.price}</td>
                            <td>${product.category}</td>
                            <td>
                                <span class="badge ${product.stock == 0 ? 'bg-danger' : (product.stock < 10 ? 'bg-warning' : 'bg-success')}">
                                    ${product.stock}
                                </span>
                            </td>
                            <td>
                                <span class="badge ${product.status == 'approved' ? 'bg-success' : (product.status == 'pending' ? 'bg-warning' : 'bg-danger')}">
                                    ${product.status}
                                </span>
                            </td>
                        </tr>
                    `;
                });
                
                // Add summary row
                html += `
                    <tr class="table-light">
                        <td colspan="6" class="text-center">
                            <small class="text-muted">
                                Showing ${data.products.length} of ${data.total} products
                            </small>
                        </td>
                    </tr>
                `;
            } else {
                html = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">
                            No products found for selected criteria
                        </td>
                    </tr>
                `;
            }
            
            previewTable.innerHTML = html;
            
            // Enable/disable export button
            exportBtn.disabled = data.total === 0;
            if (data.total === 0) {
                exportBtn.title = 'No products to export';
            }
            
        } else {
            previewTable.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-danger py-3">
                        ${data.message}
                    </td>
                </tr>
            `;
            exportBtn.disabled = true;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        previewTable.innerHTML = `
            <tr>
                <td colspan="6" class="text-center text-danger py-3">
                    Error loading preview. Please try again.
                </td>
            </tr>
        `;
    });
}

// Initial preview on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePreview();
    
    // Update preview when options change
    document.querySelectorAll('input[name="format"], input[name="type"]').forEach(input => {
        input.addEventListener('change', updatePreview);
    });
    
    // Form submission
    const exportForm = document.getElementById('exportForm');
    const exportBtn = document.getElementById('exportBtn');
    
    if (exportForm) {
        exportForm.addEventListener('submit', function(e) {
            // Show loading
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Exporting...';
            exportBtn.disabled = true;
            
            // Allow form submission
            return true;
        });
    }
});
// Export helper functions (to be included in config.php or separate file)

function exportToCSV(data, filename, vendorInfo) {
    <?php
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add vendor info as comment
    fwrite($output, "# Vendor: {$vendorInfo['full_name']} ({$vendorInfo['email']})\n");
    fwrite($output, "# Export Date: " . date('Y-m-d H:i:s') . "\n");
    fwrite($output, "# Total Products: " . (count(data) - 1) . "\n\n");
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    ?>
}

function exportToExcel(data, filename, vendorInfo) {
   <?php require_once '../../includes/PhpSpreadsheet/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add vendor info
    $sheet->setCellValue('A1', 'Vendor Export Report');
    $sheet->setCellValue('A2', 'Vendor: ' . $vendorInfo['full_name']);
    $sheet->setCellValue('A3', 'Email: ' . $vendorInfo['email']);
    $sheet->setCellValue('A4', 'Export Date: ' . date('Y-m-d H:i:s'));
    $sheet->setCellValue('A5', 'Total Products: ' . (count(data) - 1));
    
    // Write data starting from row 7
    $row = 7;
    foreach ($data as $rowData) {
        $col = 'A';
        foreach ($rowData as $cellData) {
            $sheet->setCellValue($col . $row, $cellData);
            $col++;
        }
        $row++;
    }
    
    // Style header row
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8E8E8']
        ]
    ];
    $sheet->getStyle('A7:' . $col . '7')->applyFromArray($headerStyle);
    
    // Set column widths
    foreach(range('A',$col) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    ?>
}
function exportToJSON(data, filename, vendorInfo) {
    // Prepare JSON structure
    <?php
    $export = [
        'meta' => [
            'vendor' => $vendorInfo,
            'export_date' => date('Y-m-d H:i:s'),
            'total_products' => count(data) - 1
        ],
        'products' => []
    ];
    
    // Convert CSV data to array
    $headers = array_shift(data);
    foreach (data as $row) {
        $product = [];
        foreach ($headers as $index => $header) {
            $product[$header] = $row[$index];
        }
        $export['products'][] = $product;
    }
    
    // Output
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($export, JSON_PRETTY_PRINT);
    ?>
}
</script>