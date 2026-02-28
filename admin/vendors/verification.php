<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

$page_title = 'Vendor Verification';
require_once '../includes/header.php';

$vendor_id = $_SESSION['user_id'];
$db = getDB();

// Get vendor verification status
$stmt = $db->prepare("
    SELECT email_verified, phone_verified, vendor_verified 
    FROM users WHERE id = ?
");
$stmt->execute([$vendor_id]);
$verification = $stmt->fetch();

// Get documents
$stmt = $db->prepare("
    SELECT * FROM vendor_documents 
    WHERE vendor_id = ? 
    ORDER BY verified DESC, created_at DESC
");
$stmt->execute([$vendor_id]);
$documents = $stmt->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <h2 class="mb-4">Vendor Verification</h2>
            
            <!-- Simple verification page content -->
            <div class="alert alert-info">
                Verification page - Coming soon
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>