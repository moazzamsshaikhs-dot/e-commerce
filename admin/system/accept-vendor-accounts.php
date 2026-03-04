<?php
// admin/system/accept-vendor-accounts.php

// IMPORTANT: Sabse pehle output buffering start karo
ob_start();

require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

// Special check for system administrator
requireSystemAdmin();

$db = getDB();

// Helper function for time elapsed
function timeElapsedString($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'just now';
}

// Handle export requests
if (isset($_GET['export'])) {
    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $format = $_GET['export'];
    $status = $_GET['status'] ?? 'pending';
    
    // Get data for export
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.vendor_status,
            u.vendor_category,
            c.name as category_name,
            u.vendor_bio,
            u.created_at as registered_date,
            (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as total_products,
            (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id) as total_documents,
            (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id AND verified = 1) as verified_documents
        FROM users u
        LEFT JOIN categories c ON u.vendor_category = c.id
        WHERE u.user_type = 'vendor' AND u.vendor_status = ?
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$status]);
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($format === 'pdf') {
        // Check if Dompdf exists
        $dompdfPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        
        if (file_exists($dompdfPath)) {
            // Use Dompdf
            require_once $dompdfPath;
            
            // Create HTML content with your root colors
            $html = '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>' . ucfirst($status) . ' Vendor Accounts Report</title>
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
                    body { font-family: Arial, sans-serif; margin: 20px; background: var(--light); }
                    h1 { color: var(--primary); text-align: center; font-size: 24px; margin-bottom: 10px; }
                    .header { margin-bottom: 30px; }
                    .date { text-align: right; color: var(--dark); font-size: 12px; margin-bottom: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
                    th { background: var(--primary); color: white; padding: 10px; text-align: left; font-weight: bold; }
                    td { padding: 8px; border-bottom: 1px solid #ddd; }
                    tr:nth-child(even) { background: var(--light); }
                    .status-pending { color: var(--warning); font-weight: bold; }
                    .status-approved { color: var(--success); font-weight: bold; }
                    .status-rejected { color: var(--danger); font-weight: bold; }
                    .status-suspended { color: var(--dark); font-weight: bold; }
                    .footer { margin-top: 30px; text-align: center; color: var(--dark); font-size: 10px; }
                </style>
            </head>
            <body>
                <h1>' . ucfirst($status) . ' Vendor Accounts Report</h1>
                <div class="date">Generated on: ' . date('d M Y h:i A') . '</div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Vendor Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Category</th>
                            <th>Products</th>
                            <th>Documents</th>
                            <th>Verified</th>
                            <th>Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>';
            
            $count = 1;
            foreach ($vendors as $vendor) {
                $statusClass = 'status-' . $vendor['vendor_status'];
                $html .= '<tr>';
                $html .= '<td>' . $count++ . '</td>';
                $html .= '<td>' . htmlspecialchars($vendor['full_name'] ?? $vendor['username']) . '</td>';
                $html .= '<td>' . htmlspecialchars($vendor['email']) . '</td>';
                $html .= '<td>' . htmlspecialchars($vendor['phone'] ?? 'N/A') . '</td>';
                $html .= '<td>' . htmlspecialchars($vendor['category_name'] ?? 'N/A') . '</td>';
                $html .= '<td style="text-align: center;">' . $vendor['total_products'] . '</td>';
                $html .= '<td style="text-align: center;">' . $vendor['total_documents'] . '</td>';
                $html .= '<td style="text-align: center;">' . $vendor['verified_documents'] . '</td>';
                $html .= '<td>' . date('d M Y', strtotime($vendor['registered_date'])) . '</td>';
                $html .= '<td class="' . $statusClass . '">' . ucfirst($vendor['vendor_status']) . '</td>';
                $html .= '</tr>';
            }
            
            $html .= '       </tbody>
                </table>
                <div class="footer">Generated by E-Commerce System</div>
            </body>
            </html>';
            
            // Generate PDF
            $dompdf = new Dompdf\Dompdf();
            $dompdf->set_option('isHtml5ParserEnabled', true);
            $dompdf->set_option('isRemoteEnabled', true);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();
            $dompdf->stream(ucfirst($status) . '_Vendors_' . date('Y-m-d') . '.pdf', array('Attachment' => 1));
            exit();
        } else {
            // Fallback to HTML if Dompdf not installed
            header('Content-Type: text/html; charset=utf-8');
            echo '<html><head><title>Vendor Report</title>';
            echo '<style>
                :root {
                    --primary: #4361ee;
                    --success: #06d6a0;
                    --warning: #ffb703;
                    --danger: #ef476f;
                    --info: #4cc9f0;
                    --dark: #2b2d42;
                    --light: #f8f9fa;
                }
                body{font-family:Arial;padding:20px;background:var(--light)}
                h1{color:var(--primary);text-align:center}
                table{border-collapse:collapse;width:100%;margin-top:20px}
                th{background:var(--primary);color:white;padding:10px;text-align:left}
                td{padding:8px;border:1px solid #ddd}
                tr:nth-child(even){background:var(--light)}
                .date{text-align:right;color:var(--dark);margin-bottom:20px}
                .status-pending{color:var(--warning);font-weight:bold}
                .status-approved{color:var(--success);font-weight:bold}
                .status-rejected{color:var(--danger);font-weight:bold}
                .status-suspended{color:var(--dark);font-weight:bold}
            </style>';
            echo '</head><body>';
            echo '<h1>' . ucfirst($status) . ' Vendor Accounts Report</h1>';
            echo '<div class="date">Generated on: ' . date('d M Y h:i A') . '</div>';
            echo '<table>';
            echo '<tr><th>#</th><th>Vendor</th><th>Email</th><th>Phone</th><th>Category</th><th>Products</th><th>Docs</th><th>Verified</th><th>Registered</th><th>Status</th></tr>';
            
            $count = 1;
            foreach ($vendors as $vendor) {
                $statusClass = 'status-' . $vendor['vendor_status'];
                echo '<tr>';
                echo '<td>' . $count++ . '</td>';
                echo '<td>' . htmlspecialchars($vendor['full_name'] ?? $vendor['username']) . '</td>';
                echo '<td>' . htmlspecialchars($vendor['email']) . '</td>';
                echo '<td>' . htmlspecialchars($vendor['phone'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($vendor['category_name'] ?? 'N/A') . '</td>';
                echo '<td style="text-align:center">' . $vendor['total_products'] . '</td>';
                echo '<td style="text-align:center">' . $vendor['total_documents'] . '</td>';
                echo '<td style="text-align:center">' . $vendor['verified_documents'] . '</td>';
                echo '<td>' . date('d M Y', strtotime($vendor['registered_date'])) . '</td>';
                echo '<td class="' . $statusClass . '">' . ucfirst($vendor['vendor_status']) . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            echo '<p><em>Note: Dompdf not installed. Install with: composer require dompdf/dompdf</em></p>';
            echo '</body></html>';
            exit();
        }
        
    } elseif ($format === 'excel') {
        // Excel Export (CSV format)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . ucfirst($status) . '_Vendors_' . date('Y-m-d') . '.csv');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV Headers
        fputcsv($output, [
            'ID',
            'Username',
            'Full Name',
            'Email',
            'Phone',
            'Status',
            'Category',
            'Products',
            'Documents',
            'Verified Docs',
            'Registered Date'
        ]);
        
        // CSV Data
        foreach ($vendors as $vendor) {
            fputcsv($output, [
                $vendor['id'],
                $vendor['username'],
                $vendor['full_name'],
                $vendor['email'],
                $vendor['phone'],
                $vendor['vendor_status'],
                $vendor['category_name'] ?? 'N/A',
                $vendor['total_products'],
                $vendor['total_documents'],
                $vendor['verified_documents'],
                date('d M Y', strtotime($vendor['registered_date']))
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// Handle vendor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $vendor_id = (int)($_POST['vendor_id'] ?? 0);
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');
    
    if ($vendor_id && in_array($action, ['approve', 'reject', 'suspend'])) {
        try {
            $db->beginTransaction();
            
            // Get vendor details
            $stmt = $db->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
            $stmt->execute([$vendor_id]);
            $vendor = $stmt->fetch();
            
            if (!$vendor) {
                throw new Exception('Vendor not found');
            }
            
            $new_status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'suspended');
            
            // Update vendor status
            $stmt = $db->prepare("UPDATE users SET vendor_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $vendor_id]);
            
            // Create notification
            if ($action === 'approve') {
                $message = "🎉 Congratulations! Your vendor account has been approved. You can now start adding products.";
            } elseif ($action === 'reject') {
                $message = "❌ Your vendor account has been rejected.<br><strong>Reason:</strong> {$rejection_reason}";
            } else {
                $message = "⚠️ Your vendor account has been suspended.<br><strong>Reason:</strong> {$rejection_reason}";
            }
            
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at)
                VALUES (?, 'Account Status Update', ?, ?, NOW())
            ");
            $type = $action === 'approve' ? 'success' : ($action === 'reject' ? 'error' : 'warning');
            $stmt->execute([$vendor_id, $message, $type]);
            
            // Log activity
            logUserActivity($_SESSION['user_id'], 'vendor_status_change', 
                ucfirst($action) . " vendor account: {$vendor['username']} (ID: {$vendor_id})");
            
            $db->commit();
            
            $_SESSION['success'] = "Vendor account " . ($action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'suspended')) . " successfully!";
            
        } catch(Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        
        redirect('accept-vendor-accounts.php');
        exit();
    }
}

// YAHAN SE NORMAL PAGE DISPLAY SHURU HOTA HAI
$page_title = 'Accept Vendor Accounts';
require_once '../includes/header.php';

// Get filter
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT 
        u.*,
        c.name as category_name,
        (SELECT COUNT(*) FROM products WHERE vendor_id = u.id) as total_products,
        (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id) as total_documents,
        (SELECT COUNT(*) FROM vendor_documents WHERE vendor_id = u.id AND verified = 1) as verified_documents,
        (SELECT GROUP_CONCAT(document_type SEPARATOR ', ') FROM vendor_documents WHERE vendor_id = u.id) as document_types
    FROM users u
    LEFT JOIN categories c ON u.vendor_category = c.id
    WHERE u.user_type = 'vendor'
";

$params = [];

if ($status !== 'all') {
    $query .= " AND u.vendor_status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];
$stats_query = $db->query("
    SELECT 
        COUNT(CASE WHEN vendor_status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN vendor_status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN vendor_status = 'rejected' THEN 1 END) as rejected,
        COUNT(CASE WHEN vendor_status = 'suspended' THEN 1 END) as suspended,
        COUNT(*) as total
    FROM users WHERE user_type = 'vendor'
");
$stats = $stats_query->fetch(PDO::FETCH_ASSOC);
?>

<style>
/* Your Root Colors */
:root {
    --primary: #4361ee;
    --primary-dark: #3651c4;
    --primary-light: rgba(67, 97, 238, 0.1);
    --success: #06d6a0;
    --success-dark: #05b585;
    --success-light: rgba(6, 214, 160, 0.1);
    --warning: #ffb703;
    --warning-dark: #e6a500;
    --warning-light: rgba(255, 183, 3, 0.1);
    --danger: #ef476f;
    --danger-dark: #d64161;
    --danger-light: rgba(239, 71, 111, 0.1);
    --info: #4cc9f0;
    --info-dark: #3aa9d9;
    --info-light: rgba(76, 201, 240, 0.1);
    --dark: #2b2d42;
    --dark-light: rgba(43, 45, 66, 0.1);
    --light: #f8f9fa;
    --border: #e9ecef;
    --shadow: 0 10px 30px rgba(0,0,0,0.05);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.1);
    --shadow-glow: 0 0 20px rgba(67, 97, 238, 0.3);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    --radius-sm: 0.375rem;
    --radius: 0.5rem;
    --radius-md: 0.75rem;
    --radius-lg: 1rem;
    --radius-xl: 1.5rem;
}

/* Base Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInUp {
    from {
        transform: translateY(30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideInLeft {
    from {
        transform: translateX(-30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideInRight {
    from {
        transform: translateX(30px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes scaleIn {
    from {
        transform: scale(0.9);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

@keyframes pulse-glow {
    0% { 
        box-shadow: 0 0 0 0 var(--primary);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(67, 97, 238, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(67, 97, 238, 0);
    }
}

@keyframes shimmer {
    0% {
        background-position: -1000px 0;
    }
    100% {
        background-position: 1000px 0;
    }
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0px); }
}

@keyframes glow {
    0% { box-shadow: 0 0 5px var(--primary-light); }
    50% { box-shadow: 0 0 20px var(--primary); }
    100% { box-shadow: 0 0 5px var(--primary-light); }
}

@keyframes border-pulse {
    0% { border-color: var(--primary); }
    50% { border-color: var(--info); }
    100% { border-color: var(--primary); }
}

@keyframes count-up {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Apply animations to elements */
.page-header {
    animation: slideInUp 0.6s ease-out;
}

.stat-card {
    animation: slideInUp 0.5s ease-out;
    animation-fill-mode: both;
    position: relative;
    overflow: hidden;
}

.stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transform: translateX(-100%);
    animation: shimmer 2s infinite;
    pointer-events: none;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.15s; }
.stat-card:nth-child(3) { animation-delay: 0.2s; }
.stat-card:nth-child(4) { animation-delay: 0.25s; }

.stat-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: var(--shadow-hover);
}

.stat-card:hover .stat-icon-wrapper {
    animation: pulse 0.5s ease;
}

.stat-icon-wrapper {
    transition: var(--transition);
}

.stat-value {
    animation: count-up 0.8s ease-out;
}

.quick-stat-card {
    animation: slideInLeft 0.5s ease-out;
    animation-fill-mode: both;
}

.quick-stat-card:nth-child(1) { animation-delay: 0.1s; }
.quick-stat-card:nth-child(2) { animation-delay: 0.15s; }
.quick-stat-card:nth-child(3) { animation-delay: 0.2s; }
.quick-stat-card:nth-child(4) { animation-delay: 0.25s; }

.quick-stat-card:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: var(--shadow-hover);
}

.quick-stat-card:hover .quick-stat-icon {
    animation: pulse 0.5s ease;
}

.filter-bar {
    animation: slideInUp 0.5s ease-out 0.3s both;
}

.filter-tab {
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.filter-tab::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.filter-tab:active::after {
    width: 200px;
    height: 200px;
}

.filter-tab:hover {
    transform: translateY(-2px);
}

.filter-tab.active {
    animation: pulse-glow 2s infinite;
}

.search-box {
    transition: var(--transition);
}

.search-box:focus-within {
    transform: scale(1.02);
    box-shadow: 0 0 0 3px var(--primary-light);
}

.search-box button {
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.search-box button::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.search-box button:active::after {
    width: 200px;
    height: 200px;
}

.export-buttons {
    display: flex;
    gap: 8px;
}

.btn-export {
    transition: var(--transition-bounce);
    position: relative;
    overflow: hidden;
}

.btn-export::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-export:active::after {
    width: 200px;
    height: 200px;
}

.btn-export:hover {
    transform: translateY(-3px) scale(1.05);
}

.vendors-card {
    animation: slideInUp 0.6s ease-out 0.4s both;
}

.vendors-table tbody tr {
    animation: slideInRight 0.4s ease-out;
    animation-fill-mode: both;
    transition: var(--transition);
}

.vendors-table tbody tr:nth-child(1) { animation-delay: 0.1s; }
.vendors-table tbody tr:nth-child(2) { animation-delay: 0.15s; }
.vendors-table tbody tr:nth-child(3) { animation-delay: 0.2s; }
.vendors-table tbody tr:nth-child(4) { animation-delay: 0.25s; }
.vendors-table tbody tr:nth-child(5) { animation-delay: 0.3s; }
.vendors-table tbody tr:nth-child(6) { animation-delay: 0.35s; }
.vendors-table tbody tr:nth-child(7) { animation-delay: 0.4s; }
.vendors-table tbody tr:nth-child(8) { animation-delay: 0.45s; }
.vendors-table tbody tr:nth-child(9) { animation-delay: 0.5s; }
.vendors-table tbody tr:nth-child(10) { animation-delay: 0.55s; }

.vendors-table tbody tr:hover {
    transform: translateX(5px) scale(1.01);
    background: white;
    box-shadow: var(--shadow);
}

.status-badge {
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.status-badge:hover {
    transform: scale(1.1);
    filter: brightness(1.1);
}

.btn-action {
    transition: var(--transition-bounce);
    position: relative;
    overflow: hidden;
}

.btn-action::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-action:active::after {
    width: 200px;
    height: 200px;
}

.btn-action:hover {
    transform: translateY(-3px) scale(1.05);
}

.modal-content {
    animation: scaleIn 0.3s ease-out;
}

.modal-header {
    animation: slideInUp 0.3s ease-out;
}

.modal-footer .btn {
    transition: var(--transition-bounce);
    position: relative;
    overflow: hidden;
}

.modal-footer .btn::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.modal-footer .btn:active::after {
    width: 200px;
    height: 200px;
}

.modal-footer .btn:hover {
    transform: translateY(-2px);
}

/* Main Layout */
.vendor-container {
    padding: 30px;
    background: linear-gradient(135deg, var(--light) 0%, #e9ecef 100%);
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    overflow-x: hidden;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--radius-xl);
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
}

.page-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, var(--primary-light) 0%, transparent 100%);
    border-radius: 50%;
    z-index: 0;
}

.page-header > div {
    position: relative;
    z-index: 1;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid transparent;
}

.stat-card.pending { border-left-color: var(--warning); }
.stat-card.approved { border-left-color: var(--success); }
.stat-card.rejected { border-left-color: var(--danger); }
.stat-card.suspended { border-left-color: var(--dark); }

.stat-icon-wrapper {
    width: 60px;
    height: 60px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}

.stat-card.pending .stat-icon-wrapper { background: var(--warning-light); color: var(--warning); }
.stat-card.approved .stat-icon-wrapper { background: var(--success-light); color: var(--success); }
.stat-card.rejected .stat-icon-wrapper { background: var(--danger-light); color: var(--danger); }
.stat-card.suspended .stat-icon-wrapper { background: var(--dark-light); color: var(--dark); }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--dark);
    line-height: 1.2;
}

.stat-label {
    color: var(--dark);
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.stat-trend {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    color: var(--dark);
    opacity: 0.7;
}

/* Quick Stats Row */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.quick-stat-card {
    background: white;
    border-radius: var(--radius);
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: var(--shadow);
    transition: var(--transition);
    border: 1px solid var(--border);
}

.quick-stat-icon {
    width: 45px;
    height: 45px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.quick-stat-icon.success { background: var(--success-light); color: var(--success); }
.quick-stat-icon.warning { background: var(--warning-light); color: var(--warning); }
.quick-stat-icon.danger { background: var(--danger-light); color: var(--danger); }
.quick-stat-icon.info { background: var(--info-light); color: var(--info); }

.quick-stat-content h4 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    margin: 0;
    line-height: 1.2;
}

.quick-stat-content span {
    font-size: 0.75rem;
    color: var(--dark);
    opacity: 0.7;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Filter Bar */
.filter-bar {
    background: white;
    border-radius: var(--radius-lg);
    padding: 20px;
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.filter-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-tab {
    padding: 8px 16px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark);
    background: var(--light);
    transition: var(--transition);
    cursor: pointer;
    border: none;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid var(--border);
}

.filter-tab:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.filter-tab .count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.75rem;
}

.filter-tab.active .count {
    background: rgba(255,255,255,0.2);
}

/* Search Box */
.search-box {
    display: flex;
    align-items: center;
    background: var(--light);
    border-radius: 30px;
    padding: 4px;
    min-width: 300px;
    border: 1px solid var(--border);
}

.search-box input {
    border: none;
    background: transparent;
    padding: 8px 16px;
    flex: 1;
    font-size: 0.875rem;
    outline: none;
    color: var(--dark);
}

.search-box input::placeholder {
    color: var(--dark);
    opacity: 0.5;
}

.search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 30px;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

/* Export Buttons */
.export-buttons {
    display: flex;
    gap: 8px;
}

.btn-export {
    padding: 8px 16px;
    border-radius: var(--radius);
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.btn-pdf {
    background: var(--danger);
    color: white;
}

.btn-pdf:hover {
    background: var(--danger-dark);
}

.btn-excel {
    background: var(--success);
    color: white;
}

.btn-excel:hover {
    background: var(--success-dark);
}

/* Vendors Card */
.vendors-card {
    background: white;
    border-radius: var(--radius-xl);
    box-shadow: var(--shadow);
    overflow: hidden;
    border: 1px solid var(--border);
}

.table-header {
    padding: 20px 25px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    background: var(--light);
}

.table-header h5 {
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Table Styles */
.table-responsive {
    padding: 0 25px 25px 25px;
    overflow-x: auto;
}

.vendors-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

.vendors-table th {
    padding: 12px 15px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--dark);
    opacity: 0.7;
    border-bottom: 2px solid var(--border);
}

.vendors-table td {
    padding: 15px;
    background: var(--light);
    border-radius: var(--radius);
    transition: var(--transition);
    font-size: 0.875rem;
    vertical-align: middle;
    border: 1px solid var(--border);
}

/* Vendor Info */
.vendor-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.vendor-avatar {
    width: 45px;
    height: 45px;
    border-radius: var(--radius);
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.vendor-details {
    line-height: 1.4;
}

.vendor-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 2px;
}

.vendor-email {
    font-size: 0.75rem;
    color: var(--dark);
    opacity: 0.7;
}

/* Status Badges */
.status-badge {
    padding: 5px 12px;
    border-radius: 30px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: 1px solid transparent;
}

.status-pending {
    background: var(--warning-light);
    color: var(--warning-dark);
    border-color: var(--warning);
}

.status-approved {
    background: var(--success-light);
    color: var(--success-dark);
    border-color: var(--success);
}

.status-rejected {
    background: var(--danger-light);
    color: var(--danger-dark);
    border-color: var(--danger);
}

.status-suspended {
    background: var(--dark-light);
    color: var(--dark);
    border-color: var(--dark);
}

/* Document Badge */
.document-badge {
    background: white;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    color: var(--dark);
    display: inline-block;
    border: 1px solid var(--border);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.btn-action {
    padding: 6px 12px;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
    font-weight: 500;
    transition: var(--transition);
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
    border: 1px solid transparent;
}

.btn-approve {
    background: var(--success-light);
    color: var(--success-dark);
    border-color: var(--success);
}

.btn-approve:hover {
    background: var(--success);
    color: white;
}

.btn-reject {
    background: var(--danger-light);
    color: var(--danger-dark);
    border-color: var(--danger);
}

.btn-reject:hover {
    background: var(--danger);
    color: white;
}

.btn-suspend {
    background: var(--dark-light);
    color: var(--dark);
    border-color: var(--dark);
}

.btn-suspend:hover {
    background: var(--dark);
    color: white;
}

.btn-view {
    background: var(--primary-light);
    color: var(--primary-dark);
    border-color: var(--primary);
}

.btn-view:hover {
    background: var(--primary);
    color: white;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state i {
    font-size: 4rem;
    color: var(--dark);
    opacity: 0.2;
    margin-bottom: 20px;
}

.empty-state h5 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty-state p {
    color: var(--dark);
    opacity: 0.7;
    max-width: 300px;
    margin: 0 auto;
}

/* Badge */
.badge {
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 500;
}

.bg-info {
    background: var(--info) !important;
    color: white !important;
}

.bg-secondary {
    background: var(--light) !important;
    color: var(--dark) !important;
    border: 1px solid var(--border);
}

/* Modal Styles */
.modal-content {
    border-radius: var(--radius-lg);
    border: none;
    overflow: hidden;
}

.modal-header {
    padding: 20px 25px;
    border-bottom: none;
}

.modal-header.bg-success {
    background: var(--success) !important;
}

.modal-header.bg-danger {
    background: var(--danger) !important;
}

.modal-header.bg-dark {
    background: var(--dark) !important;
}

.modal-body {
    padding: 25px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid var(--border);
    background: var(--light);
}

/* Buttons */
.btn-secondary {
    background: var(--light);
    color: var(--dark);
    border: 1px solid var(--border);
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

.btn-secondary:hover {
    background: var(--border);
}

.btn-success {
    background: var(--success);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

.btn-success:hover {
    background: var(--success-dark);
}

.btn-danger {
    background: var(--danger);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

.btn-danger:hover {
    background: var(--danger-dark);
}

.btn-dark {
    background: var(--dark);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: var(--radius);
    font-weight: 500;
    transition: var(--transition);
    cursor: pointer;
}

.btn-dark:hover {
    background: #1a1e2f;
}

/* Alerts */
.alert-success {
    background: var(--success-light);
    color: var(--success-dark);
    border: 1px solid var(--success);
    border-radius: var(--radius);
}

.alert-danger {
    background: var(--danger-light);
    color: var(--danger-dark);
    border: 1px solid var(--danger);
    border-radius: var(--radius);
}

/* Loading state for pending */
.status-pending {
    position: relative;
    animation: pulse-glow 2s infinite;
}

/* Float animation for icons */
.fa-store, .fa-user-check, .fa-box, .fa-file-alt {
    animation: float 3s ease-in-out infinite;
}

/* Responsive */
@media (max-width: 768px) {
    .vendor-container {
        padding: 20px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        width: 100%;
        min-width: auto;
    }
    
    .export-buttons {
        justify-content: flex-end;
    }
    
    .table-responsive {
        padding: 0 15px 15px 15px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
        justify-content: center;
    }
    
    .quick-stats {
        grid-template-columns: 1fr;
    }
}

/* Extra fixes for body/html */
html, body {
    overflow-x: hidden;
    overflow-y: auto;
    height: auto;
    min-height: 100vh;
    margin: 0;
    padding: 0;
    scroll-behavior: smooth;
}

/* Ensure nothing creates horizontal scroll */
* {
    box-sizing: border-box;
    max-width: 100%;
}
</style>

<div class="vendor-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1 class="h3 mb-0">
                    <i class="fas fa-user-check me-2" style="color: var(--primary);"></i>
                    Accept Vendor Accounts
                </h1>
                <p class="text-muted mb-0">
                    <i class="fas fa-store me-2" style="color: var(--primary);"></i>
                    Review and manage vendor registration requests
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="admin-accounts.php" class="btn btn-outline-primary">
                    <i class="fas fa-user me-2"></i> Admin Accounts
                </a>
                <a href="withdrawal-management.php" class="btn btn-outline-primary">
                    <i class="fas fa-hand-holding-usd me-2"></i> Withdrawals
                </a>
                <a href="dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-icon-wrapper">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                <div class="stat-label">Pending</div>
                <div class="stat-trend">
                    <i class="fas fa-hourglass-half me-1"></i> Awaiting review
                </div>
            </div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon-wrapper">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['approved'] ?? 0; ?></div>
                <div class="stat-label">Approved</div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up me-1"></i> Active vendors
                </div>
            </div>
        </div>
        <div class="stat-card rejected">
            <div class="stat-icon-wrapper">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div class="stat-label">Rejected</div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-down me-1"></i> Need revision
                </div>
            </div>
        </div>
        <div class="stat-card suspended">
            <div class="stat-icon-wrapper">
                <i class="fas fa-ban"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value"><?php echo $stats['suspended'] ?? 0; ?></div>
                <div class="stat-label">Suspended</div>
                <div class="stat-trend">
                    <i class="fas fa-exclamation-triangle me-1"></i> Inactive
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="quick-stats">
        <div class="quick-stat-card">
            <div class="quick-stat-icon success">
                <i class="fas fa-store"></i>
            </div>
            <div class="quick-stat-content">
                <h4><?php echo $stats['total'] ?? 0; ?></h4>
                <span>Total Vendors</span>
            </div>
        </div>
        <div class="quick-stat-card">
            <div class="quick-stat-icon warning">
                <i class="fas fa-box"></i>
            </div>
            <div class="quick-stat-content">
                <?php 
                $totalProducts = 0;
                foreach($vendors as $v) { $totalProducts += $v['total_products']; }
                ?>
                <h4><?php echo $totalProducts; ?></h4>
                <span>Total Products</span>
            </div>
        </div>
        <div class="quick-stat-card">
            <div class="quick-stat-icon info">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="quick-stat-content">
                <?php 
                $totalDocs = 0;
                foreach($vendors as $v) { $totalDocs += $v['total_documents']; }
                ?>
                <h4><?php echo $totalDocs; ?></h4>
                <span>Total Documents</span>
            </div>
        </div>
        <div class="quick-stat-card">
            <div class="quick-stat-icon danger">
                <i class="fas fa-clock"></i>
            </div>
            <div class="quick-stat-content">
                <h4><?php echo $stats['pending'] ?? 0; ?></h4>
                <span>Awaiting Review</span>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <div class="filter-tabs">
            <a href="?status=all" class="filter-tab <?php echo $status === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All
                <span class="count"><?php echo $stats['total'] ?? 0; ?></span>
            </a>
            <a href="?status=pending" class="filter-tab <?php echo $status === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Pending
                <span class="count"><?php echo $stats['pending'] ?? 0; ?></span>
            </a>
            <a href="?status=approved" class="filter-tab <?php echo $status === 'approved' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> Approved
                <span class="count"><?php echo $stats['approved'] ?? 0; ?></span>
            </a>
            <a href="?status=rejected" class="filter-tab <?php echo $status === 'rejected' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i> Rejected
                <span class="count"><?php echo $stats['rejected'] ?? 0; ?></span>
            </a>
            <a href="?status=suspended" class="filter-tab <?php echo $status === 'suspended' ? 'active' : ''; ?>">
                <i class="fas fa-ban"></i> Suspended
                <span class="count"><?php echo $stats['suspended'] ?? 0; ?></span>
            </a>
        </div>

        <div class="d-flex gap-3">
            <form method="GET" class="search-box">
                <?php if ($status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo $status; ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search vendors..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search me-1"></i> Search</button>
            </form>

            <div class="export-buttons">
                <a href="?export=pdf&status=<?php echo $status; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn-export btn-pdf">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
                <a href="?export=excel&status=<?php echo $status; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn-export btn-excel">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Vendors Table -->
    <div class="vendors-card">
        <div class="table-header">
            <h5>
                <i class="fas fa-store me-2" style="color: var(--primary);"></i>
                <?php echo ucfirst($status); ?> Vendor Accounts
                <span class="badge bg-secondary ms-2"><?php echo count($vendors); ?></span>
            </h5>
        </div>

        <div class="table-responsive">
            <?php if (empty($vendors)): ?>
                <div class="empty-state">
                    <i class="fas fa-store-alt"></i>
                    <h5>No Vendors Found</h5>
                    <p>No <?php echo $status; ?> vendor accounts to display.</p>
                </div>
            <?php else: ?>
                <table class="vendors-table">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Category</th>
                            <th>Products</th>
                            <th>Documents</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor): ?>
                        <tr>
                            <td>
                                <div class="vendor-info">
                                    <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $vendor['profile_pic'] ?? 'default.png'; ?>" 
                                         class="vendor-avatar" onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png';">
                                    <div class="vendor-details">
                                        <div class="vendor-name"><?php echo htmlspecialchars($vendor['full_name'] ?? $vendor['username']); ?></div>
                                        <div class="vendor-email"><?php echo htmlspecialchars($vendor['email']); ?></div>
                                        <small style="color: var(--dark); opacity: 0.6;"><?php echo htmlspecialchars($vendor['phone'] ?? 'No phone'); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($vendor['category_name'] ?? 'Not set'); ?></span>
                            </td>
                            <td>
                                <span class="fw-bold" style="color: var(--dark);"><?php echo $vendor['total_products']; ?></span>
                            </td>
                            <td>
                                <span class="document-badge">
                                    <i class="fas fa-file-alt me-1" style="color: var(--info);"></i>
                                    <?php echo $vendor['verified_documents']; ?>/<?php echo $vendor['total_documents']; ?> verified
                                </span>
                                <?php if (!empty($vendor['document_types'])): ?>
                                    <br><small style="color: var(--dark); opacity: 0.6;"><?php echo $vendor['document_types']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: var(--dark);"><?php echo date('d M Y', strtotime($vendor['created_at'])); ?></span>
                                <br><small style="color: var(--dark); opacity: 0.6;"><?php echo timeElapsedString($vendor['created_at']); ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $vendor['vendor_status']; ?>">
                                    <i class="fas fa-<?php 
                                        echo $vendor['vendor_status'] == 'approved' ? 'check-circle' : 
                                            ($vendor['vendor_status'] == 'pending' ? 'clock' : 
                                            ($vendor['vendor_status'] == 'rejected' ? 'times-circle' : 'ban')); 
                                    ?>"></i>
                                    <?php echo ucfirst($vendor['vendor_status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($vendor['vendor_status'] == 'pending'): ?>
                                        <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn-action btn-reject" onclick="rejectVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($vendor['vendor_status'] == 'approved'): ?>
                                        <button class="btn-action btn-suspend" onclick="suspendVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-ban"></i> Suspend
                                        </button>
                                    <?php elseif ($vendor['vendor_status'] == 'suspended'): ?>
                                        <button class="btn-action btn-approve" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                            <i class="fas fa-check"></i> Reactivate
                                        </button>
                                    <?php endif; ?>
                                    <a href="../vendors/view-vendor.php?id=<?php echo $vendor['id']; ?>" class="btn-action btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="vendor_id" id="approve_id">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> Approve Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-4x" style="color: var(--success);"></i>
                    <h5 style="color: var(--dark); margin-top: 15px;">Confirm Approval</h5>
                    <p style="color: var(--dark); opacity: 0.7;">Are you sure you want to approve this vendor account?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="vendor_id" id="reject_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i> Reject Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-times-circle fa-4x" style="color: var(--danger);"></i>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--dark);">Reason for Rejection</label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Suspend Modal -->
<div class="modal fade" id="suspendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="vendor_id" id="suspend_id">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fas fa-ban me-2"></i> Suspend Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-exclamation-triangle fa-4x" style="color: var(--warning);"></i>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color: var(--dark);">Reason for Suspension</label>
                        <textarea name="rejection_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Suspend Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveVendor(id) {
    document.getElementById('approve_id').value = id;
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function rejectVendor(id) {
    document.getElementById('reject_id').value = id;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function suspendVendor(id) {
    document.getElementById('suspend_id').value = id;
    new bootstrap.Modal(document.getElementById('suspendModal')).show();
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(alert => {
        try {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        } catch(e) {}
    });
}, 5000);
</script>

<?php require_once '../includes/footer.php'; ?>