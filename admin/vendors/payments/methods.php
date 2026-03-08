<?php
// admin/vendors/payments/methods.php - Payment Methods Management
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';
require_once '../../includes/admin-access-check.php';
require_once '../../includes/payments/admin_payment_processor.php';

$page_title = 'Payment Methods Management';
require_once '../../includes/header.php';

$db = getDB();
$processor = new AdminPaymentProcessor($db);

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$methodId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Process verification/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_method'])) {
    $methodId = (int)$_POST['method_id'];
    $processAction = $_POST['process_action'];
    $adminId = $_SESSION['user_id'];
    $notes = $_POST['notes'] ?? '';
    
    if ($processAction === 'verify') {
        $result = $processor->verifyVendorMethod($methodId, $adminId);
    } elseif ($processAction === 'reject') {
        $result = $processor->rejectVendorMethod($methodId, $notes, $adminId);
    }
    
    if ($result['success']) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
            ' . $result['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
            ' . $result['message'] . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>';
    }
}

// Get all payment methods with vendor details
$stmt = $db->query("
    SELECT 
        vpm.*,
        u.full_name as vendor_name,
        u.email as vendor_email,
        u.username
    FROM vendors_payment_methods vpm
    JOIN users u ON vpm.vendor_id = u.id
    ORDER BY 
        CASE WHEN vpm.is_verified = 0 THEN 0 ELSE 1 END,
        vpm.created_at DESC
");
$methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get method details for each
foreach ($methods as &$method) {
    // Query method details based on type
    switch ($method['method_type']) {
        case 'bank':
            $stmt = $db->prepare("
                SELECT * FROM vendor_bank_accounts 
                WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                ORDER BY is_default DESC LIMIT 1
            ");
            $stmt->execute([$method['id'], $method['vendor_id']]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'paypal':
            $stmt = $db->prepare("
                SELECT * FROM vendor_paypal_accounts 
                WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                ORDER BY is_default DESC LIMIT 1
            ");
            $stmt->execute([$method['id'], $method['vendor_id']]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'stripe':
            $stmt = $db->prepare("
                SELECT * FROM vendor_stripe_accounts 
                WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                ORDER BY is_default DESC LIMIT 1
            ");
            $stmt->execute([$method['id'], $method['vendor_id']]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'easypaisa':
        case 'jazzcash':
            $stmt = $db->prepare("
                SELECT * FROM vendor_mobile_accounts 
                WHERE payment_method_id = ? OR (vendor_id = ? AND payment_method_id IS NULL)
                ORDER BY is_default DESC LIMIT 1
            ");
            $stmt->execute([$method['id'], $method['vendor_id']]);
            $method['details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        default:
            $method['details'] = null;
    }
}
unset($method);

// Get counts by status
$pendingCount = count(array_filter($methods, function($m) { return !$m['is_verified']; }));
$verifiedCount = count(array_filter($methods, function($m) { return $m['is_verified']; }));
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-credit-card me-2"></i>Payment Methods Management</h2>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6 class="text-uppercase">Pending Verification</h6>
                    <h3 class="mb-0"><?php echo $pendingCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Verified Methods</h6>
                    <h3 class="mb-0"><?php echo $verifiedCount; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="text-uppercase">Total Methods</h6>
                    <h3 class="mb-0"><?php echo count($methods); ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Methods Table -->
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Payment Methods</h5>
            <div>
                <button class="btn btn-light btn-sm" onclick="$('#filterStatus').val(''); $('#filterForm').submit();">
                    All
                </button>
                <button class="btn btn-warning btn-sm" onclick="$('#filterStatus').val('pending'); $('#filterForm').submit();">
                    Pending
                </button>
                <button class="btn btn-success btn-sm" onclick="$('#filterStatus').val('verified'); $('#filterForm').submit();">
                    Verified
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($methods)): ?>
                <div class="alert alert-info">No payment methods found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="methodsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vendor</th>
                                <th>Method Type</th>
                                <th>Details</th>
                                <th>Default</th>
                                <th>Status</th>
                                <th>Verified By</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($methods as $method): ?>
                            <tr>
                                <td>#<?php echo $method['id']; ?></td>
                                <td>
                                    <a href="../view-vendor.php?id=<?php echo $method['vendor_id']; ?>">
                                        <?php echo htmlspecialchars($method['vendor_name']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo $method['email']; ?></small>
                                </td>
                                <td>
                                    <?php
                                    $methodIcons = [
                                        'bank' => 'fa-university',
                                        'paypal' => 'fa-paypal',
                                        'stripe' => 'fa-stripe',
                                        'easypaisa' => 'fa-mobile-alt',
                                        'jazzcash' => 'fa-mobile-alt'
                                    ];
                                    $icon = $methodIcons[$method['method_type']] ?? 'fa-credit-card';
                                    ?>
                                    <i class="fas <?php echo $icon; ?> me-1"></i>
                                    <?php echo ucfirst($method['method_type']); ?>
                                </td>
                                <td>
                                    <?php if ($method['details']): ?>
                                        <?php if ($method['method_type'] === 'bank'): ?>
                                            <small>
                                                <?php echo htmlspecialchars($method['details']['bank_name']); ?><br>
                                                <?php echo htmlspecialchars($method['details']['account_number']); ?><br>
                                                <?php echo htmlspecialchars($method['details']['account_title']); ?>
                                            </small>
                                        <?php elseif ($method['method_type'] === 'paypal'): ?>
                                            <small><?php echo htmlspecialchars($method['details']['paypal_email']); ?></small>
                                        <?php elseif ($method['method_type'] === 'stripe'): ?>
                                            <small>Stripe Connected</small>
                                        <?php elseif (in_array($method['method_type'], ['easypaisa', 'jazzcash'])): ?>
                                            <small>
                                                <?php echo htmlspecialchars($method['details']['mobile_number']); ?><br>
                                                <?php echo htmlspecialchars($method['details']['account_title']); ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No details</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($method['is_default']): ?>
                                        <span class="badge bg-primary">Default</span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($method['is_verified']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i>Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-clock me-1"></i>Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($method['verified_by']): ?>
                                        <small><?php echo $method['verified_by']; ?></small>
                                        <br>
                                        <small class="text-muted"><?php echo $method['verified_at'] ? date('d M Y', strtotime($method['verified_at'])) : ''; ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo date('d M Y', strtotime($method['created_at'])); ?></small></td>
                                <td>
                                    <?php if (!$method['is_verified']): ?>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#verifyModal" 
                                                data-id="<?php echo $method['id']; ?>" 
                                                data-vendor="<?php echo htmlspecialchars($method['vendor_name']); ?>"
                                                data-action="verify">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#verifyModal"
                                                data-id="<?php echo $method['id']; ?>"
                                                data-vendor="<?php echo htmlspecialchars($method['vendor_name']); ?>"
                                                data-action="reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
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

<!-- Verify/Reject Modal -->
<div class="modal fade" id="verifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="verifyModalTitle">Process Payment Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="method_id" id="modalMethodId">
                    <input type="hidden" name="process_method" value="1">
                    <input type="hidden" name="process_action" id="modalProcessAction">
                    
                    <div class="mb-3">
                        <label class="form-label">Vendor</label>
                        <input type="text" class="form-control" id="modalVendorName" readonly>
                    </div>
                    
                    <div class="mb-3" id="notesField" style="display: none;">
                        <label class="form-label">Notes / Reason</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="modalSubmitBtn">
                        Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden form for filtering -->
<form id="filterForm" method="GET">
    <input type="hidden" id="filterStatus" name="status" value="">
</form>

<script>
$(document).ready(function() {
    $('#methodsTable').DataTable({
        "order": [[7, "desc"]],
        "pageLength": 25
    });
    
    // Handle modal
    $('#verifyModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const methodId = button.data('id');
        const vendorName = button.data('vendor');
        const action = button.data('action');
        
        $('#modalMethodId').val(methodId);
        $('#modalVendorName').val(vendorName);
        $('#modalProcessAction').val(action);
        
        if (action === 'verify') {
            $('#verifyModalTitle').text('Verify Payment Method');
            $('#modalSubmitBtn').removeClass('btn-danger').addClass('btn-success').text('Verify');
            $('#notesField').hide();
        } else {
            $('#verifyModalTitle').text('Reject Payment Method');
            $('#modalSubmitBtn').removeClass('btn-success').addClass('btn-danger').text('Reject');
            $('#notesField').show();
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>

