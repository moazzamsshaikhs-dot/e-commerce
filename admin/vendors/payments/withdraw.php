<?php
// admin/vendors/payments/withdraw.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/admin-access-check.php';
require_once '../../includes/payments/admin_payment_processor.php';

$page_title = 'Withdrawal Requests';
require_once '../../includes/header.php';

$db = getDB();
$processor = new AdminPaymentProcessor($db);

// Get withdrawal requests
$stmt = $db->prepare("
    SELECT 
        vwr.*,
        u.full_name as vendor_name,
        u.email as vendor_email,
        u.vendor_rating
    FROM vendor_withdrawal_requests vwr
    JOIN users u ON vwr.vendor_id = u.id
    ORDER BY 
        CASE vwr.status 
            WHEN 'pending' THEN 1 
            WHEN 'processing' THEN 2 
            ELSE 3 
        END,
        vwr.created_at DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Vendor Withdrawal Requests</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <?php
        $pendingCount = 0;
        $pendingTotal = 0;
        foreach ($requests as $req) {
            if ($req['status'] == 'pending') {
                $pendingCount++;
                $pendingTotal += $req['request_amount'];
            }
        }
        ?>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6>Pending Requests</h6>
                    <h3><?php echo $pendingCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Pending Amount</h6>
                    <h3>$<?php echo number_format($pendingTotal, 2); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Requests Table -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">All Withdrawal Requests</h5>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">No withdrawal requests found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vendor</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>#<?php echo $request['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($request['vendor_name']); ?><br>
                                    <small class="text-muted"><?php echo $request['vendor_email']; ?></small>
                                </td>
                                <td>
                                    <strong>$<?php echo number_format($request['request_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo ucfirst($request['withdrawal_method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'completed' => 'success',
                                        'rejected' => 'danger',
                                        'cancelled' => 'secondary'
                                    ][$request['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <?php if ($request['status'] == 'pending'): ?>
                                        <button class="btn btn-sm btn-success process-request" 
                                                data-id="<?php echo $request['id']; ?>"
                                                data-action="approve">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger process-request"
                                                data-id="<?php echo $request['id']; ?>"
                                                data-action="reject">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($request['status'] == 'processing'): ?>
                                        <span class="badge bg-info">Processing</span>
                                    <?php elseif ($request['status'] == 'completed'): ?>
                                        <span class="badge bg-success">Paid</span>
                                        <?php if ($request['transaction_id']): ?>
                                            <br>
                                            <small class="text-muted"><?php echo $request['transaction_id']; ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
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

<!-- Processing Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Process Withdrawal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-3">
                    <div class="spinner-border text-primary" id="processSpinner" style="display: none;"></div>
                    <div id="processMessage"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.process-request').click(function() {
        const requestId = $(this).data('id');
        const action = $(this).data('action');
        
        if (!confirm('Are you sure you want to ' + action + ' this withdrawal request?')) {
            return;
        }
        
        $('#processModal').modal('show');
        $('#processMessage').html('<p>Processing...</p>');
        $('#processSpinner').show();
        
        $.ajax({
            url: '../includes/payments/process_payment.php',
            method: 'POST',
            data: {
                action: 'process_withdrawal',
                request_id: requestId,
                process_action: action
            },
            dataType: 'json',
            success: function(response) {
                $('#processSpinner').hide();
                
                if (response.success) {
                    $('#processMessage').html(`
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> ${response.message}
                        </div>
                    `);
                    
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $('#processMessage').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> ${response.message}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#processSpinner').hide();
                $('#processMessage').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Error processing request
                    </div>
                `);
            }
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>