<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if (!isLoggedIn() || $_SESSION['user_type'] !== 'user') {
    redirect('../../login.php');
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? 0;

// Get order details
$stmt = $db->prepare("
    SELECT o.*, u.full_name, u.email 
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ? AND o.payment_method = 'bank'
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error'] = 'Order not found';
    redirect('my-orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = $_POST['bank_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $account_holder = $_POST['account_holder'] ?? '';
    $transfer_date = $_POST['transfer_date'] ?? '';
    $transfer_amount = $_POST['transfer_amount'] ?? '';
    $reference_number = $_POST['reference_number'] ?? '';
    
    // Handle file upload
    $slip_path = '';
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/payment_slips/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
        $file_name = 'slip_' . $order_id . '_' . time() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $file_path)) {
            $slip_path = 'uploads/payment_slips/' . $file_name;
        }
    }
    
    // Save payment proof
    $stmt = $db->prepare("
        INSERT INTO payment_proofs (
            order_id, user_id, bank_name, account_number, account_holder,
            transfer_date, transfer_amount, reference_number, slip_path, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $order_id, $user_id, $bank_name, $account_number, $account_holder,
        $transfer_date, $transfer_amount, $reference_number, $slip_path
    ]);
    
    // Add order note
    $stmt = $db->prepare("
        INSERT INTO order_notes (order_id, user_id, note_type, note)
        VALUES (?, ?, 'customer', 'Payment proof uploaded. Awaiting verification.')
    ");
    $stmt->execute([$order_id, $user_id]);
    
    // Notify admin
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        SELECT id, ?, ?, 'info'
        FROM users WHERE user_type = 'admin'
    ");
    $stmt->execute([
        'New Payment Proof Uploaded',
        "Order #{$order['order_number']} has uploaded payment proof. Please verify."
    ]);
    
    $_SESSION['success'] = 'Payment proof uploaded successfully. Admin will verify soon.';
    redirect('order-details.php?id=' . $order_id);
}

$page_title = 'Upload Payment Slip';
require_once '../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Upload Bank Transfer Payment Proof</h5>
                </div>
                <div class="card-body">
                    
                    <!-- Order Summary -->
                    <div class="alert alert-info">
                        <h6>Order #<?php echo $order['order_number']; ?></h6>
                        <p class="mb-1">Amount to Pay: <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></p>
                        <p class="mb-0">Payment Due: <?php echo date('d M Y', strtotime($order['order_date'] . ' +3 days')); ?></p>
                    </div>
                    
                    <!-- Bank Account Details (from admin_accounts) -->
                    <?php
                    $stmt = $db->prepare("
                        SELECT * FROM admin_accounts 
                        WHERE account_type = 'bank' AND is_active = 1
                    ");
                    $stmt->execute();
                    $bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    
                    <div class="mb-4">
                        <h6>Our Bank Account Details:</h6>
                        <?php foreach ($bank_accounts as $account): ?>
                            <div class="card bg-light mb-2">
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>Bank:</strong> <?php echo htmlspecialchars($account['bank_name']); ?></p>
                                    <p class="mb-1"><strong>Account Holder:</strong> <?php echo htmlspecialchars($account['account_holder']); ?></p>
                                    <p class="mb-1"><strong>Account Number:</strong> <?php echo htmlspecialchars($account['account_number']); ?></p>
                                    <?php if (!empty($account['iban'])): ?>
                                        <p class="mb-1"><strong>IBAN:</strong> <?php echo htmlspecialchars($account['iban']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Upload Form -->
                    <form method="POST" enctype="multipart/form-data">
                        <h6 class="mb-3">Your Transfer Details:</h6>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Bank Name <span class="text-danger">*</span></label>
                                <input type="text" name="bank_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Your Account Number <span class="text-danger">*</span></label>
                                <input type="text" name="account_number" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Account Holder Name <span class="text-danger">*</span></label>
                                <input type="text" name="account_holder" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Transfer Date <span class="text-danger">*</span></label>
                                <input type="date" name="transfer_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Transfer Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="transfer_amount" class="form-control" 
                                       value="<?php echo $order['total_amount']; ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference/Transaction Number</label>
                                <input type="text" name="reference_number" class="form-control" 
                                       placeholder="Optional">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Upload Payment Slip/Proof <span class="text-danger">*</span></label>
                            <input type="file" name="payment_slip" class="form-control" accept="image/*,.pdf" required>
                            <small class="text-muted">Upload bank slip, screenshot, or PDF (max 2MB)</small>
                        </div>
                        
                        <hr>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Submit Payment Proof
                            </button>
                            <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Order
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>