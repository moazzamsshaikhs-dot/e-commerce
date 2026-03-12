<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

$db = getDB();

// Handle verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_payment'])) {
    $proof_id = $_POST['proof_id'];
    $action = $_POST['action']; // approve or reject
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    try {
        $db->beginTransaction();
        
        // Get payment proof details
        $stmt = $db->prepare("
            SELECT pp.*, o.order_number, o.user_id, o.total_amount 
            FROM payment_proofs pp
            JOIN orders o ON pp.order_id = o.id
            WHERE pp.id = ?
        ");
        $stmt->execute([$proof_id]);
        $proof = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($action === 'approve') {
            // Update payment proof status
            $stmt = $db->prepare("
                UPDATE payment_proofs SET 
                status = 'approved', 
                verified_by = ?, 
                verified_at = NOW(),
                admin_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $admin_notes, $proof_id]);
            
            // Update order payment status
            $stmt = $db->prepare("
                UPDATE orders SET 
                payment_status = 'completed',
                updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$proof['order_id']]);
            
            // Update admin account balance
            $stmt = $db->prepare("
                UPDATE admin_accounts 
                SET current_balance = current_balance + ?,
                    total_credited = total_credited + ?,
                    last_transaction_at = NOW()
                WHERE account_type = 'bank' AND is_default = 1
            ");
            $stmt->execute([$proof['total_amount'], $proof['total_amount']]);
            
            // Add to account balance history
            $stmt = $db->prepare("
                INSERT INTO account_balance_history 
                (admin_account_id, balance, change_amount, change_type, reference_id, reference_type, notes)
                SELECT id, current_balance, ?, 'credit', ?, 'order', ?
                FROM admin_accounts WHERE account_type = 'bank' AND is_default = 1
            ");
            $stmt->execute([$proof['total_amount'], $proof['order_id'], "Payment for order #{$proof['order_number']}"]);
            
            $message = "Payment verified successfully. Order #{$proof['order_number']} marked as paid.";
            $notification_title = "Payment Approved";
            $notification_message = "Your payment for order #{$proof['order_number']} has been verified.";
            
        } else {
            // Reject payment proof
            $stmt = $db->prepare("
                UPDATE payment_proofs SET 
                status = 'rejected', 
                verified_by = ?, 
                verified_at = NOW(),
                admin_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $admin_notes, $proof_id]);
            
            $message = "Payment proof rejected.";
            $notification_title = "Payment Rejected";
            $notification_message = "Your payment for order #{$proof['order_number']} was rejected. Reason: " . $admin_notes;
        }
        
        // Notify user
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type)
            VALUES (?, ?, ?, ?)
        ");
        $type = ($action === 'approve') ? 'success' : 'error';
        $stmt->execute([$proof['user_id'], $notification_title, $notification_message, $type]);
        
        $db->commit();
        $_SESSION['success'] = $message;
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
    
    redirect('verify-bank-transfers.php');
}

// Get pending payment proofs
$stmt = $db->prepare("
    SELECT pp.*, o.order_number, o.total_amount, u.full_name, u.email
    FROM payment_proofs pp
    JOIN orders o ON pp.order_id = o.id
    JOIN users u ON pp.user_id = u.id
    WHERE pp.status = 'pending'
    ORDER BY pp.created_at DESC
");
$stmt->execute();
$pending_proofs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Verify Bank Transfers';
require_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Pending Bank Transfer Verifications</h5>
                </div>
                <div class="card-body">
                    
                    <?php if (empty($pending_proofs)): ?>
                        <div class="alert alert-info">
                            No pending bank transfer verifications.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Transfer Date</th>
                                        <th>Bank Details</th>
                                        <th>Slip</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_proofs as $proof): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $proof['order_number']; ?></strong>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($proof['full_name']); ?><br>
                                            <small><?php echo htmlspecialchars($proof['email']); ?></small>
                                        </td>
                                        <td>
                                            <strong>$<?php echo number_format($proof['transfer_amount'], 2); ?></strong>
                                            <br><small>(Order: $<?php echo number_format($proof['total_amount'], 2); ?>)</small>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($proof['transfer_date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($proof['bank_name']); ?></strong><br>
                                            A/C: <?php echo htmlspecialchars($proof['account_number']); ?><br>
                                            Holder: <?php echo htmlspecialchars($proof['account_holder']); ?>
                                            <?php if (!empty($proof['reference_number'])): ?>
                                                <br>Ref: <?php echo htmlspecialchars($proof['reference_number']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($proof['slip_path'])): ?>
                                                <a href="<?php echo '../../' . $proof['slip_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No Slip</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y H:i', strtotime($proof['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="showVerifyModal(<?php echo $proof['id']; ?>, 'approve')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="showVerifyModal(<?php echo $proof['id']; ?>, 'reject')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
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
    </div>
</div>

<!-- Verification Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="verifyModalTitle">Verify Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="proof_id" id="proof_id">
                    <input type="hidden" name="action" id="action">
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="admin_notes" class="form-control" rows="3" 
                                  placeholder="Add notes for the customer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="verify_payment" class="btn btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showVerifyModal(proofId, action) {
    document.getElementById('proof_id').value = proofId;
    document.getElementById('action').value = action;
    
    if (action === 'approve') {
        document.getElementById('verifyModalTitle').innerHTML = 'Approve Payment';
    } else {
        document.getElementById('verifyModalTitle').innerHTML = 'Reject Payment';
    }
    
    new bootstrap.Modal(document.getElementById('verifyModal')).show();
}
</script>

<?php require_once '../includes/footer.php'; ?>