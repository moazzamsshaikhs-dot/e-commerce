<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$db = getDB();

$stmt = $db->prepare("
    SELECT 
        pt.*,
        o.order_number,
        o.total_amount as order_total,
        o.payment_status as order_payment_status,
        o.shipping_address,
        u.full_name as user_name,
        u.email as user_email,
        u.phone as user_phone
    FROM payment_transactions pt
    LEFT JOIN users u ON pt.user_id = u.id
    LEFT JOIN orders o ON o.transaction_id = pt.gateway_transaction_id 
                       OR o.transaction_id = pt.id
    WHERE pt.id = ?
");
$stmt->execute([$id]);
$txn = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$txn) {
    echo '<p class="text-danger">Transaction not found</p>';
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Transaction Information</h6>
        <table class="table table-sm">
            <tr>
                <th>Transaction ID:</th>
                <td><?php echo $txn['gateway_transaction_id'] ?? $txn['id']; ?></td>
            </tr>
            <tr>
                <th>Type:</th>
                <td><?php echo ucfirst($txn['transaction_type'] ?? 'payment'); ?></td>
            </tr>
            <tr>
                <th>Gateway:</th>
                <td><?php echo ucfirst($txn['gateway']); ?></td>
            </tr>
            <tr>
                <th>Amount:</th>
                <td class="fw-bold text-primary">$<?php echo number_format($txn['amount'], 2); ?></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <span class="badge bg-<?php 
                        echo $txn['status'] == 'completed' ? 'success' : 
                            ($txn['status'] == 'pending' ? 'warning' : 'danger'); 
                    ?>">
                        <?php echo ucfirst($txn['status']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Date:</th>
                <td><?php echo date('d M Y H:i:s', strtotime($txn['created_at'])); ?></td>
            </tr>
            <?php if (!empty($txn['description'])): ?>
            <tr>
                <th>Description:</th>
                <td><?php echo htmlspecialchars($txn['description']); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Customer Information</h6>
        <table class="table table-sm">
            <tr>
                <th>Name:</th>
                <td><?php echo htmlspecialchars($txn['user_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>Email:</th>
                <td><?php echo $txn['user_email'] ?? 'N/A'; ?></td>
            </tr>
            <tr>
                <th>Phone:</th>
                <td><?php echo $txn['user_phone'] ?? 'N/A'; ?></td>
            </tr>
        </table>
        
        <?php if (!empty($txn['order_number'])): ?>
        <h6 class="border-bottom pb-2 mt-3">Order Information</h6>
        <table class="table table-sm">
            <tr>
                <th>Order #:</th>
                <td>
                    <a href="../orders/view.php?id=<?php echo $txn['order_id']; ?>" target="_blank">
                        <?php echo $txn['order_number']; ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th>Order Total:</th>
                <td>$<?php echo number_format($txn['order_total'], 2); ?></td>
            </tr>
            <tr>
                <th>Payment Status:</th>
                <td><?php echo ucfirst($txn['order_payment_status']); ?></td>
            </tr>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($txn['metadata'])): ?>
<div class="row mt-3">
    <div class="col-12">
        <h6 class="border-bottom pb-2">Additional Data</h6>
        <pre class="bg-light p-3 rounded" style="max-height: 200px; overflow: auto;">
<?php 
$metadata = json_decode($txn['metadata'], true);
if ($metadata) {
    print_r($metadata);
} else {
    echo $txn['metadata'];
}
?>
        </pre>
    </div>
</div>
<?php endif; ?>