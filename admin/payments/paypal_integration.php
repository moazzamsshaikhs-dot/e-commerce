<?php
// admin/payments/paypal_integration.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';
require_once '../../includes/payments/PayPalGateway.php';

requireSystemAdmin();

$page_title = 'PayPal Integration';
require_once '../includes/header.php';

$db = getDB();
$gateway = new PayPalGateway();

// Handle test connection
if (isset($_POST['test_connection'])) {
    $result = $gateway->getBalance();
    if ($result['success']) {
        $_SESSION['success'] = "PayPal connection successful! Balance: $" . number_format($result['balance'], 2);
    } else {
        $_SESSION['error'] = "Connection failed: " . ($result['error'] ?? 'Unknown error');
    }
    redirect('paypal_integration.php');
}

// Get PayPal accounts
$accounts = $db->query("
    SELECT * FROM admin_accounts 
    WHERE account_type = 'paypal' 
    ORDER BY is_default DESC
")->fetchAll();

// Get recent transactions
$transactions = $db->query("
    SELECT * FROM payment_transactions 
    WHERE gateway = 'paypal' 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetchAll();
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">PayPal Integration</h4>
                </div>
                <div class="card-body">
                    
                    <!-- Accounts List -->
                    <h5>Connected Accounts</h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Account Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Default</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($accounts as $acc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($acc['account_name']); ?></td>
                                    <td><?php echo htmlspecialchars($acc['account_email']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $acc['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $acc['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($acc['is_default']): ?>
                                            <span class="badge bg-warning">Default</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info test-account" 
                                                data-id="<?php echo $acc['id']; ?>">
                                            <i class="fas fa-plug"></i> Test
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Test Connection -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0">Test Connection</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <button type="submit" name="test_connection" class="btn btn-info">
                                            <i class="fas fa-plug me-2"></i> Test PayPal Connection
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Transactions -->
                    <h5>Recent Transactions</h5>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $txn): ?>
                                <tr>
                                    <td><?php echo $txn['id']; ?></td>
                                    <td>$<?php echo number_format($txn['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $txn['status'] == 'completed' ? 'success' : 
                                                ($txn['status'] == 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($txn['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($txn['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-details" 
                                                data-txn='<?php echo json_encode($txn); ?>'>
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.test-account').forEach(btn => {
    btn.addEventListener('click', function() {
        alert('Testing account ' + this.dataset.id);
    });
});

document.querySelectorAll('.view-details').forEach(btn => {
    btn.addEventListener('click', function() {
        const txn = JSON.parse(this.dataset.txn);
        alert(JSON.stringify(txn.metadata, null, 2));
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>