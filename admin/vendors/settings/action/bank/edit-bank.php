<?php
// action/bank/edit-bank.php
session_start();
require_once '../../../../includes/config.php';
require_once '../../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied';
    header('Location: ' . SITE_URL . 'index.php');
    exit;
}

$vendor_id = $_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error'] = 'Invalid account ID';
    header('Location: ../../bank.php');
    exit;
}

try {
    $db = getDB();
    
    // Simple direct query - columns exist karte hain
    $stmt = $db->prepare("
        SELECT 
            id,
            vendor_id,
            account_holder_name,
            bank_name,
            account_number,
            routing_number,
            swift_code,
            ifsc_code,
            iban,
            branch_name,
            branch_code,
            account_type,
            is_default,
            is_verified,
            created_at,
            updated_at
        FROM vendor_bank_accounts 
        WHERE id = ? AND vendor_id = ?
    ");
    
    $stmt->execute([$id, $vendor_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        $_SESSION['error'] = 'Account not found';
        header('Location: ../../bank.php');
        exit;
    }
    
} catch(PDOException $e) {
    error_log("Edit bank error: " . $e->getMessage());
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: ../../bank.php');
    exit;
}

$page_title = 'Edit Bank Account';
require_once '../../../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Edit Bank Account</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" id="editBankForm" action="update-bank.php">
                        <input type="hidden" name="id" value="<?php echo $account['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Account Holder Name *</label>
                                <input type="text" name="account_holder_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['account_holder_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Bank Name *</label>
                                <input type="text" name="bank_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['bank_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Account Number</label>
                                <input type="text" class="form-control" 
                                       value="****<?php echo substr($account['account_number'], -4); ?>" readonly>
                                <small class="text-muted">Account number cannot be changed for security</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Account Type</label>
                                <select name="account_type" class="form-select">
                                    <option value="savings" <?php echo ($account['account_type'] ?? 'savings') == 'savings' ? 'selected' : ''; ?>>Savings</option>
                                    <option value="current" <?php echo ($account['account_type'] ?? '') == 'current' ? 'selected' : ''; ?>>Current</option>
                                </select>
                            </div>
                            
                            <!-- Routing Number -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Routing Number</label>
                                <input type="text" name="routing_number" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['routing_number'] ?? ''); ?>">
                            </div>
                            
                            <!-- SWIFT Code -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">SWIFT Code</label>
                                <input type="text" name="swift_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['swift_code'] ?? ''); ?>">
                            </div>
                            
                            <!-- IFSC Code -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">IFSC Code</label>
                                <input type="text" name="ifsc_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['ifsc_code'] ?? ''); ?>">
                            </div>
                            
                            <!-- IBAN -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">IBAN</label>
                                <input type="text" name="iban" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['iban'] ?? ''); ?>">
                            </div>
                            
                            <!-- Branch Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Branch Name</label>
                                <input type="text" name="branch_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['branch_name'] ?? ''); ?>">
                            </div>
                            
                            <!-- Branch Code -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Branch Code</label>
                                <input type="text" name="branch_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($account['branch_code'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" id="is_default" 
                                           <?php echo ($account['is_default'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_default">
                                        Set as default account
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-3">
                                <button type="submit" class="btn btn-primary">Update Account</button>
                                <a href="../../bank.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('editBankForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('update-bank.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Account updated successfully');
            window.location.href = '../../bank.php';
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred');
    });
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>