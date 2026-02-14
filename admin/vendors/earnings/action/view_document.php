<?php
// view_document.php
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    // header('HTTP/1.0 403 Forbidden');
    header('Location: ' . SITE_URL . 'index.php');
    die('Access denied. Vendor only.');
}

$vendor_id = $_SESSION['user_id'];
$doc_ids = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($doc_ids <= 0) {
    $_SESSION['error'] = 'Invalid document ID';
    header('Location: ../tax.php');
    exit();
}

try {
    $db = getDB();
    
    // Get document info and verify vendor owns it
    $stmt = $db->prepare("
        SELECT vd.*, u.username 
        FROM vendor_documents vd
        JOIN users u ON vd.vendor_id = u.id
        WHERE vd.id = ? AND vd.vendor_id = ?
    ");
    $stmt->execute([$doc_ids, $vendor_id]);
    $document = $stmt->fetch();
    
    if (!$document) {
        $_SESSION['error'] = 'Document not found or access denied';
        header('Location: ../tax.php');
        exit();
    }
    
    $file_path = SITE_URL . 'uploads/tax_documents/' . $document['document_file'];
    
    if (!file_exists($file_path)) {
        $_SESSION['error'] = 'File not found on server';
        header('Location: ../tax.php');
        exit();
    }
    
    // Get file info
    $file_name = basename($file_path);
    $file_size = filesize($file_path);
    $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Determine content type
    $content_types = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    $content_type = $content_types[$file_extension] ?? 'application/octet-stream';
    
    // Check if file is viewable in browser
    $viewable_types = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    
    if (in_array($file_extension, $viewable_types)) {
        // Set headers for inline viewing
        header('Content-Type: ' . $content_type);
        header('Content-Disposition: inline; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: public, must-revalidate, max-age=0');
        header('Pragma: public');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Read and output file
        readfile($file_path);
    } else {
        // For non-viewable files, show an info page
        showDocumentInfoPage($document, $file_path, $file_extension);
    }
    
    // Log the view
    logActivity($vendor_id, 'view_document', 'Viewed tax document: ' . $document['document_type']);
    
    exit();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error viewing document: ' . $e->getMessage();
    header('Location: ../tax.php');
    exit();
}

/**
 * Display document information page for non-viewable files
 */
function showDocumentInfoPage($document, $file_path, $file_extension) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Document Preview - E-Commerce</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            
            .document-container {
                max-width: 800px;
                margin: 40px auto;
                background: white;
                border-radius: 15px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .document-header {
                background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            
            .document-icon {
                font-size: 4rem;
                margin-bottom: 20px;
            }
            
            .document-body {
                padding: 40px;
            }
            
            .document-info {
                background: #f8f9fa;
                border-radius: 10px;
                padding: 25px;
                margin-bottom: 30px;
            }
            
            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .info-row:last-child {
                border-bottom: none;
            }
            
            .info-label {
                font-weight: 600;
                color: #495057;
            }
            
            .info-value {
                color: #212529;
            }
            
            .action-buttons {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .btn-custom {
                padding: 12px 30px;
                border-radius: 8px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .btn-custom:hover {
                transform: translateY(-3px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
            
            @media (max-width: 768px) {
                .document-body {
                    padding: 20px;
                }
                
                .action-buttons {
                    flex-direction: column;
                }
                
                .btn-custom {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="document-container">
            <div class="document-header">
                <div class="document-icon">
                    <?php
                    $icons = [
                        'pdf' => 'fas fa-file-pdf',
                        'doc' => 'fas fa-file-word',
                        'docx' => 'fas fa-file-word',
                        'xls' => 'fas fa-file-excel',
                        'xlsx' => 'fas fa-file-excel',
                        'csv' => 'fas fa-file-csv',
                        'txt' => 'fas fa-file-alt',
                        'zip' => 'fas fa-file-archive',
                        'rar' => 'fas fa-file-archive'
                    ];
                    $icon = $icons[$file_extension] ?? 'fas fa-file';
                    ?>
                    <i class="<?php echo $icon; ?>"></i>
                </div>
                <h1 class="h3 mb-2">Document Preview</h1>
                <p class="mb-0">This file type cannot be displayed in the browser</p>
            </div>
            
            <div class="document-body">
                <div class="document-info">
                    <div class="info-row">
                        <span class="info-label">Document Type:</span>
                        <span class="info-value"><?php echo ucfirst(str_replace('_', ' ', $document['document_type'])); ?></span>
                    </div>
                    
                    <?php if ($document['document_number']): ?>
                    <div class="info-row">
                        <span class="info-label">Document Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($document['document_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <span class="info-label">File Type:</span>
                        <span class="info-value text-uppercase"><?php echo $file_extension; ?> File</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">File Size:</span>
                        <span class="info-value"><?php echo formatFileSize(filesize($file_path)); ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Uploaded:</span>
                        <span class="info-value"><?php echo date('F j, Y', strtotime($document['created_at'])); ?></span>
                    </div>
                    
                    <?php if ($document['verified']): ?>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value text-success">
                            <i class="fas fa-check-circle me-1"></i> Verified
                            <?php if ($document['verified_at']): ?>
                                (<?php echo date('M d, Y', strtotime($document['verified_at'])); ?>)
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php else: ?>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value text-warning">
                            <i class="fas fa-clock me-1"></i> Pending Verification
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($document['expiry_date']): ?>
                    <div class="info-row">
                        <span class="info-label">Expiry Date:</span>
                        <span class="info-value <?php echo strtotime($document['expiry_date']) < time() ? 'text-danger' : 'text-success'; ?>">
                            <?php echo date('F j, Y', strtotime($document['expiry_date'])); ?>
                            <?php if (strtotime($document['expiry_date']) < time()): ?>
                                <span class="badge bg-danger ms-2">Expired</span>
                            <?php else: ?>
                                <span class="badge bg-success ms-2">Valid</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="action-buttons">
                    <a href="download_document.php?id=<?php echo $doc_id; ?>" 
                       class="btn btn-primary btn-custom">
                        <i class="fas fa-download me-2"></i> Download Document
                    </a>
                    
                    <a href="../tax.php" class="btn btn-outline-secondary btn-custom">
                        <i class="fas fa-arrow-left me-2"></i> Back to Tax Documents
                    </a>
                    
                    <a href="delete_document.php?id=<?php echo $doc_id; ?>" 
                       class="btn btn-outline-danger btn-custom" 
                       onclick="return confirm('Are you sure you want to delete this document?');">
                        <i class="fas fa-trash me-2"></i> Delete Document
                    </a>
                </div>
                
                <div class="mt-4 text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This file format (.<strong><?php echo $file_extension; ?></strong>) cannot be displayed in the browser.
                        Please download the file to view its contents.
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Auto close after 10 seconds for security
            setTimeout(() => {
                document.querySelector('.alert-info').innerHTML = 
                    '<i class="fas fa-shield-alt me-2"></i>For security, this page will close automatically.';
                setTimeout(() => {
                    window.history.back();
                }, 3000);
            }, 10000);
        </script>
    </body>
    </html>
    <?php
}

/**
 * Format file size to human readable format
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return number_format(($bytes / pow($k, $i)), 2) . ' ' . $sizes[$i];
}
?>