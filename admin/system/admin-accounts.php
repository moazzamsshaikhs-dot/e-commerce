<?php
// admin/system/admin-accounts.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';
require_once '../includes/admin-access-check.php';

// Special check for system administrator
requireSystemAdmin();

$page_title = 'Manage Admin Accounts';
require_once '../includes/header.php';

$db = getDB();
$message = '';
$message_type = 'success';

// Handle add/edit account
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add' || $action === 'edit') {
            $account_type = $_POST['account_type'];
            $account_name = $_POST['account_name'];
            $account_email = $_POST['account_email'] ?? null;
            $account_number = $_POST['account_number'] ?? null;
            $account_holder = $_POST['account_holder'] ?? null;
            $bank_name = $_POST['bank_name'] ?? null;
            $swift_code = $_POST['swift_code'] ?? null;
            $phone_number = $_POST['phone_number'] ?? null;
            $is_default = isset($_POST['is_default']) ? 1 : 0;
            
            if ($action === 'add') {
                $stmt = $db->prepare("
                    INSERT INTO admin_accounts 
                    (account_type, account_name, account_email, account_number, account_holder, bank_name, swift_code, phone_number, is_default, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$account_type, $account_name, $account_email, $account_number, $account_holder, $bank_name, $swift_code, $phone_number, $is_default, $_SESSION['user_id']]);
                $message = "Account added successfully!";
            } else {
                $id = $_POST['id'];
                $stmt = $db->prepare("
                    UPDATE admin_accounts SET
                        account_type = ?, 
                        account_name = ?, 
                        account_email = ?, 
                        account_number = ?, 
                        account_holder = ?, 
                        bank_name = ?, 
                        swift_code = ?, 
                        phone_number = ?, 
                        is_default = ?
                    WHERE id = ?
                ");
                $stmt->execute([$account_type, $account_name, $account_email, $account_number, $account_holder, $bank_name, $swift_code, $phone_number, $is_default, $id]);
                $message = "Account updated successfully!";
            }
        }
        
        if ($action === 'delete') {
            $id = $_POST['id'];
            $stmt = $db->prepare("DELETE FROM admin_accounts WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Account deleted successfully!";
        }
        
        if ($action === 'toggle_status') {
            $id = $_POST['id'];
            $stmt = $db->prepare("UPDATE admin_accounts SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Account status toggled!";
        }
        
    } catch(Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
        error_log("Admin accounts error: " . $e->getMessage());
    }
}

// Get all accounts
try {
    $accounts = $db->query("
        SELECT * FROM admin_accounts 
        ORDER BY is_default DESC, is_active DESC, account_type
    ")->fetchAll();
} catch(Exception $e) {
    $accounts = [];
    if ($e->getCode() == '42S02') { // Table doesn't exist
        $message = "Admin accounts table doesn't exist yet. Please run the database setup first.";
    } else {
        $message = "Error loading accounts: " . $e->getMessage();
    }
    $message_type = 'warning';
}

// Group by type
$grouped = [];
foreach ($accounts as $acc) {
    $grouped[$acc['account_type']][] = $acc;
}
?>

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

.accounts-container {
    padding: 30px;
    background: #f4f7fc;
    min-height: 100vh;
}

.page-header {
    background: white;
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.account-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.03);
    border-left: 4px solid transparent;
    transition: all 0.3s ease;
}

.account-card:hover {
    transform: translateX(5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.account-card.paypal { border-left-color: #003087; }
.account-card.stripe { border-left-color: #635bff; }
.account-card.easypaisa { border-left-color: #27aae1; }
.account-card.jazzcash { border-left-color: #ed1c24; }
.account-card.visa { border-left-color: #1a1f71; }
.account-card.mastercard { border-left-color: #eb001b; }
.account-card.bank { border-left-color: var(--success); }

.account-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 15px;
}

.account-type {
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 10px;
    border-radius: 20px;
    background: var(--light);
}

.account-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 10px;
}

.account-detail {
    font-size: 13px;
    color: #6c757d;
    margin-bottom: 5px;
}

.account-detail i {
    width: 20px;
    color: var(--primary);
}

.default-badge {
    background: var(--success);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 8px;
}

.btn-add {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-add:hover {
    background: #3651c4;
    transform: translateY(-2px);
}
</style>

<div class="accounts-container">
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-credit-card me-2 text-primary"></i>
                Admin Payment Accounts
            </h1>
            <p class="text-muted mb-0">Manage all payment collection accounts</p>
        </div>
        <div class="flex-1 gap-2 d-flex justify-content-end">
            <a class="btn btn-outline-primary text-decoration-none" href="accept-vendor-accounts.php">
            <i class="fas fa-bank me-2"></i> vendor accounts
        </a>
        <a class="btn btn-outline-primary text-decoration-none" href="withdrawal-management.php">
    <i class="fas fa-hand-holding-usd me-2"></i> Withdrawals
</a>
            <a class="btn btn-outline-primary text-decoration-none" href="dashboard.php">
            <i class="fas fa-home me-2"></i> back
        </a>
        <button class="btn-add" data-bs-toggle="modal" data-bs-target="#accountModal">
            <i class="fas fa-plus-circle me-2"></i> Add New Account
        </button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Account Groups -->
    <div class="row">
        <?php 
        $types = [
            'paypal' => ['PayPal', 'fab fa-paypal', '#003087'],
            'stripe' => ['Stripe', 'fab fa-stripe', '#635bff'],
            'easypaisa' => ['Easypaisa', 'fas fa-mobile-alt', '#27aae1'],
            'jazzcash' => ['JazzCash', 'fas fa-mobile-alt', '#ed1c24'],
            'visa' => ['Visa', 'fab fa-cc-visa', '#1a1f71'],
            'mastercard' => ['Mastercard', 'fab fa-cc-mastercard', '#eb001b'],
            'bank' => ['Bank Transfer', 'fas fa-university', '#10b981']
        ];
        
        foreach ($types as $type => $info):
            $accounts_list = $grouped[$type] ?? [];
        ?>
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="<?php echo $info[1]; ?>" style="color: <?php echo $info[2]; ?>"></i>
                        <?php echo $info[0]; ?> Accounts
                        <span class="badge bg-secondary ms-2"><?php echo count($accounts_list); ?></span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($accounts_list)): ?>
                        <p class="text-muted text-center py-3">No <?php echo $info[0]; ?> accounts added</p>
                    <?php else: ?>
                        <?php foreach ($accounts_list as $acc): ?>
                        <div class="account-card <?php echo $type; ?>">
                            <div class="account-header">
                                <div>
                                    <span class="account-type"><?php echo $info[0]; ?></span>
                                    <?php if ($acc['is_default']): ?>
                                        <span class="default-badge">DEFAULT</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="badge bg-<?php echo $acc['is_active'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $acc['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <h6 class="account-name"><?php echo htmlspecialchars($acc['account_name']); ?></h6>
                            
                            <?php if ($acc['account_email']): ?>
                                <div class="account-detail">
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($acc['account_email']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($acc['account_number']): ?>
                                <div class="account-detail">
                                    <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($acc['account_number']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($acc['account_holder']): ?>
                                <div class="account-detail">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($acc['account_holder']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" onclick='editAccount(<?php echo json_encode($acc); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning" onclick="toggleStatus(<?php echo $acc['id']; ?>)">
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteAccount(<?php echo $acc['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Add/Edit Account Modal -->
<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" id="form_action" value="add">
                <input type="hidden" name="id" id="account_id">
                
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalTitle">Add New Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account Type</label>
                            <select name="account_type" class="form-select" required id="account_type">
                                <option value="">Select Type</option>
                                <option value="paypal">PayPal</option>
                                <option value="stripe">Stripe</option>
                                <option value="easypaisa">Easypaisa</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="visa">Visa Card</option>
                                <option value="mastercard">Mastercard</option>
                                <option value="bank">Bank Account</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Account Name</label>
                            <input type="text" name="account_name" class="form-control" required id="account_name">
                        </div>
                        
                        <div class="col-md-6" id="email_field">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="account_email" class="form-control" id="account_email">
                        </div>
                        
                        <div class="col-md-6" id="phone_field">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" id="phone_number">
                        </div>
                        
                        <div class="col-md-6" id="account_number_field">
                            <label class="form-label">Account Number</label>
                            <input type="text" name="account_number" class="form-control" id="account_number">
                        </div>
                        
                        <div class="col-md-6" id="account_holder_field">
                            <label class="form-label">Account Holder Name</label>
                            <input type="text" name="account_holder" class="form-control" id="account_holder">
                        </div>
                        
                        <div class="col-md-6" id="bank_name_field">
                            <label class="form-label">Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" id="bank_name">
                        </div>
                        
                        <div class="col-md-6" id="swift_field">
                            <label class="form-label">SWIFT Code</label>
                            <input type="text" name="swift_code" class="form-control" id="swift_code">
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_default" class="form-check-input" id="is_default">
                                <label class="form-check-label" for="is_default">
                                    Set as default account for this payment type
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editAccount(account) {
    document.getElementById('form_action').value = 'edit';
    document.getElementById('account_id').value = account.id;
    document.getElementById('modalTitle').textContent = 'Edit Account';
    
    document.getElementById('account_type').value = account.account_type;
    document.getElementById('account_name').value = account.account_name;
    document.getElementById('account_email').value = account.account_email || '';
    document.getElementById('phone_number').value = account.phone_number || '';
    document.getElementById('account_number').value = account.account_number || '';
    document.getElementById('account_holder').value = account.account_holder || '';
    document.getElementById('bank_name').value = account.bank_name || '';
    document.getElementById('swift_code').value = account.swift_code || '';
    document.getElementById('is_default').checked = account.is_default == 1;
    
    // Trigger change to show correct fields
    document.getElementById('account_type').dispatchEvent(new Event('change'));
    
    new bootstrap.Modal(document.getElementById('accountModal')).show();
}

function toggleStatus(id) {
    if (confirm('Toggle account status?')) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_status">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteAccount(id) {
    if (confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Show/hide fields based on account type
document.getElementById('account_type').addEventListener('change', function() {
    let type = this.value;
    
    // Hide all optional fields first
    document.querySelectorAll('#email_field, #phone_field, #account_number_field, #account_holder_field, #bank_name_field, #swift_field').forEach(el => {
        el.style.display = 'none';
    });
    
    if (type === 'paypal' || type === 'stripe') {
        document.getElementById('email_field').style.display = 'block';
    } else if (type === 'easypaisa' || type === 'jazzcash') {
        document.getElementById('phone_field').style.display = 'block';
        document.getElementById('account_holder_field').style.display = 'block';
    } else if (type === 'visa' || type === 'mastercard') {
        document.getElementById('account_number_field').style.display = 'block';
        document.getElementById('account_holder_field').style.display = 'block';
    } else if (type === 'bank') {
        document.getElementById('account_holder_field').style.display = 'block';
        document.getElementById('bank_name_field').style.display = 'block';
        document.getElementById('account_number_field').style.display = 'block';
        document.getElementById('swift_field').style.display = 'block';
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>