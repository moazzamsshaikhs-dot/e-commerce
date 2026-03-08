<?php
// vendor/payments/settings.php - Vendor Payment Settings
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/vendor-access-check.php';

$page_title = 'Payment Settings';
require_once '../includes/header.php';

$db = getDB();
$vendorId = $_SESSION['user_id'];

// Handle add payment method
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_method'])) {
    $methodType = $_POST['method_type'];
    
    try {
        $db->beginTransaction();
        
        // Check if this is the first method
        $stmt = $db->prepare("SELECT COUNT(*) FROM vendors_payment_methods WHERE vendor_id = ?");
        $stmt->execute([$vendorId]);
        $isFirst = $stmt->fetchColumn() == 0;
        
        // Insert payment method
        $stmt = $db->prepare("
            INSERT INTO vendors_payment_methods 
            (vendor_id, method_type, is_default, is_active, created_at)
            VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$vendorId, $methodType, $isFirst ? 1 : 0]);
        $methodId = $db->lastInsertId();
        
        // Insert method-specific details
        if ($methodType === 'bank') {
            $stmt = $db->prepare("
                INSERT INTO vendor_bank_accounts 
                (vendor_id, payment_method_id, bank_name, account_number, account_title, routing_number, is_default, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $vendorId,
                $methodId,
                $_POST['bank_name'],
                $_POST['account_number'],
                $_POST['account_title'],
                $_POST['routing_number'] ?? '',
                1
            ]);
            
        } elseif ($methodType === 'paypal') {
            $stmt = $db->prepare("
                INSERT INTO vendor_paypal_accounts 
                (vendor_id, payment_method_id, paypal_email, is_default, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$vendorId, $methodId, $_POST['paypal_email'], 1]);
            
        } elseif ($methodType === 'stripe') {
            // For Stripe, we'd typically use OAuth connect
            // For demo, we'll create a placeholder
            $stmt = $db->prepare("
                INSERT INTO vendor_stripe_accounts 
                (vendor_id, payment_method_id, stripe_account_id, is_default, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$vendorId, $methodId, 'stripe_demo_' . time(), 1]);
            
        } elseif ($methodType === 'easypaisa') {
            $stmt = $db->prepare("
                INSERT INTO vendor_mobile_accounts 
                (vendor_id, payment_method_id, mobile_number, account_title, account_type, is_default, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $vendorId,
                $methodId,
                $_POST['mobile_number'],
                $_POST['account_title'] ?? '',
                'easypaisa',
                1
            ]);
            
        } elseif ($methodType === 'jazzcash') {
            $stmt = $db->prepare("
                INSERT INTO vendor_mobile_accounts 
                (vendor_id, payment_method_id, mobile_number, account_title, account_type, is_default, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $vendorId,
                $methodId,
                $_POST['mobile_number'],
                $_POST['account_title'] ?? '',
                'jazzcash',
                1
            ]);
        }
        
        $db->commit();
        $message = 'Payment method added successfully!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle set default
if (isset($_GET['set_default']) && is_numeric($_GET['set_default'])) {
    $methodId = (int)$_GET['set_default'];
    
    try {
        $db->beginTransaction();
        
        // Remove default from all methods
        $stmt = $db->prepare("UPDATE vendors_payment_methods SET is_default = 0 WHERE vendor_id = ?");
        $stmt->execute([$vendorId]);
        
        // Set new default
        $stmt = $db->prepare("UPDATE vendors_payment_methods SET is_default = 1 WHERE id = ? AND vendor_id = ?");
        $stmt->execute([$methodId, $vendorId]);
        
        $db->commit();
        $message = 'Default payment method updated!';
        $messageType = 'success';
        
    } catch (Exception $e) {
        $db->rollBack();
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Handle delete method
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $methodId = (int)$_GET['delete'];
    
    try {
        // Check if this is the only method
        $stmt = $db->prepare("SELECT COUNT(*) FROM vendors_payment_methods WHERE vendor_id = ?");
        $stmt->execute([$vendorId]);
        $count = $stmt->fetchColumn();
        
        if ($count <= 1) {
            $message = 'Cannot delete the only payment method.';
            $messageType = 'danger';
        } else {
            // Delete method (cascade will handle related records)
            $stmt = $db->prepare("DELETE FROM vendors_payment_methods WHERE id = ? AND vendor_id = ?");
            $stmt->execute([$methodId, $vendorId]);
            
            // If deleted was default, set another as default
            $stmt = $db->prepare("SELECT COUNT(*) FROM vendors_payment_methods WHERE vendor_id = ? AND is_default = 1");
            $stmt->execute([$vendorId]);
            if ($stmt->fetchColumn() == 0) {
                $stmt = $db->prepare("UPDATE vendors_payment_methods SET is_default = 1 WHERE vendor_id = ? LIMIT 1");
                $stmt->execute([$vendorId]);
            }
            
            $message = 'Payment method deleted successfully!';
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get payment methods
$stmt = $db->prepare("
    SELECT * FROM vendors_payment_methods 
    WHERE vendor_id = ?
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$vendorId]);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get method details
foreach ($paymentMethods as &$method) {
    switch ($method['method_type']) {
        case 'bank':
            $stmt = $db->prepare("SELECT * FROM vendor_bank_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'paypal':
            $stmt = $db->prepare("SELECT * FROM vendor_paypal_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'stripe':
            $stmt = $db->prepare("SELECT * FROM vendor_stripe_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
        case 'easypaisa':
        case 'jazzcash':
            $stmt = $db->prepare("SELECT * FROM vendor_mobile_accounts WHERE vendor_id = ? ORDER BY is_default DESC LIMIT 1");
            $stmt->execute([$vendorId]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
    }
}
unset($method);
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-cog me-2"></i>Payment Settings</h2>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Existing Payment Methods -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>My Payment Methods</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($paymentMethods)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You haven't added any payment methods yet. Add one below to receive payments.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Method</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paymentMethods as $method): ?>
                                    <tr>
                                        <td>
                                            <i class="fas <?php 
                                                echo $method['method_type'] === 'bank' ? 'fa-university' : 
                                                    ($method['method_type'] === 'paypal' ? 'fa-paypal' : 
                                                    ($method['method_type'] === 'stripe' ? 'fa-stripe' : 'fa-mobile-alt'));
                                            ?> fa-2x me-2 text-primary"></i>
                                            <strong><?php echo ucfirst($method['method_type']); ?></strong>
                                            <?php if ($method['is_default']): ?>
                                                <span class="badge bg-primary ms-1">Default</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($method['method_type'] === 'bank' && isset($method['details']['bank_name'])): ?>
                                                <small>
                                                    <strong><?php echo htmlspecialchars($method['details']['bank_name']); ?></strong><br>
                                                    <?php echo htmlspecialchars($method['details']['account_title']); ?><br>
                                                    <?php echo htmlspecialchars($method['details']['account_number']); ?>
                                                </small>
                                            <?php elseif ($method['method_type'] === 'paypal' && isset($method['details']['paypal_email'])): ?>
                                                <small><?php echo htmlspecialchars($method['details']['paypal_email']); ?></small>
                                            <?php elseif ($method['method_type'] === 'stripe'): ?>
                                                <small><span class="badge bg-success">Connected</span></small>
                                            <?php elseif (in_array($method['method_type'], ['easypaisa', 'jazzcash']) && isset($method['details']['mobile_number'])): ?>
                                                <small>
                                                    <?php echo htmlspecialchars($method['details']['mobile_number']); ?><br>
                                                    <?php echo htmlspecialchars($method['details']['account_title'] ?? ''); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">No details</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($method['is_verified']): ?>
                                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Verified</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$method['is_default']): ?>
                                                <a href="?set_default=<?php echo $method['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Set as Default">
                                                    <i class="fas fa-star"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="?delete=<?php echo $method['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this payment method?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Add New Method -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Add Payment Method</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="add_method" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Type</label>
                            <select name="method_type" id="methodType" class="form-select" required onchange="toggleFields()">
                                <option value="">Select method</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                                <option value="easypaisa">Easypaisa</option>
                                <option value="jazzcash">JazzCash</option>
                            </select>
                        </div>
                        
                        <!-- Bank Fields -->
                        <div id="bankFields" style="display: none;">
                            <div class="mb-2">
                                <label class="form-label">Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="e.g., HBL, MCB">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Account Title</label>
                                <input type="text" name="account_title" class="form-control" placeholder="Your name on account">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Account Number</label>
                                <input type="text" name="account_number" class="form-control" placeholder="IBAN or account number">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Routing Number (Optional)</label>
                                <input type="text" name="routing_number" class="form-control">
                            </div>
                        </div>
                        
                        <!-- PayPal Fields -->
                        <div id="paypalFields" style="display: none;">
                            <div class="mb-2">
                                <label class="form-label">PayPal Email</label>
                                <input type="email" name="paypal_email" class="form-control" placeholder="your@email.com">
                            </div>
                        </div>
                        
                        <!-- Mobile Money Fields -->
                        <div id="mobileFields" style="display: none;">
                            <div class="mb-2">
                                <label class="form-label">Mobile Number</label>
                                <input type="text" name="mobile_number" class="form-control" placeholder="03xxxxxxxxx">
                            </div>
                            <div class="mb-2">
                                <label class="form-label">Account Title (Optional)</label>
                                <input type="text" name="account_title" class="form-control" placeholder="Name on account">
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Your payment method will be verified by admin before receiving payments.
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-plus me-2"></i>Add Method
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFields() {
    const methodType = document.getElementById('methodType').value;
    
    document.getElementById('bankFields').style.display = 'none';
    document.getElementById('paypalFields').style.display = 'none';
    document.getElementById('mobileFields').style.display = 'none';
    
    if (methodType === 'bank') {
        document.getElementById('bankFields').style.display = 'block';
    } else if (methodType === 'paypal') {
        document.getElementById('paypalFields').style.display = 'block';
    } else if (methodType === 'easypaisa' || methodType === 'jazzcash') {
        document.getElementById('mobileFields').style.display = 'block';
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>

