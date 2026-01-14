<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'Settings';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get current tab
$tab = $_GET['tab'] ?? 'general';
$valid_tabs = ['general', 'security', 'notifications', 'billing'];
if (!in_array($tab, $valid_tabs)) {
    $tab = 'general';
}

$errors = [];
$success = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tab === 'security') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Get current password hash
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!password_verify($current_password, $user['password'])) {
        $errors[] = 'Current password is incorrect';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters long';
    } elseif ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    } elseif ($current_password === $new_password) {
        $errors[] = 'New password must be different from current password';
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$hashed_password, $user_id])) {
            $success = 'Password updated successfully!';
            logUserActivity($user_id, 'password_change', 'Changed password');
        } else {
            $errors[] = 'Error updating password';
        }
    }
}

// Log activity
logUserActivity($user_id, 'settings_view', 'Viewed settings: ' . $tab);
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Settings</h1>
                    <p class="text-muted mb-0">Manage your account settings</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Settings Sidebar -->
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="list-group list-group-flush">
                        <a href="?tab=general" 
                           class="list-group-item list-group-item-action border-0 <?php echo $tab === 'general' ? 'active' : ''; ?>">
                            <i class="fas fa-user me-2"></i> General
                        </a>
                        <a href="?tab=security" 
                           class="list-group-item list-group-item-action border-0 <?php echo $tab === 'security' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt me-2"></i> Security
                        </a>
                        <a href="?tab=notifications" 
                           class="list-group-item list-group-item-action border-0 <?php echo $tab === 'notifications' ? 'active' : ''; ?>">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </a>
                        <a href="?tab=billing" 
                           class="list-group-item list-group-item-action border-0 <?php echo $tab === 'billing' ? 'active' : ''; ?>">
                            <i class="fas fa-credit-card me-2"></i> Billing
                        </a>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-lg-9">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <?php foreach ($errors as $error): ?>
                            <div><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <!-- General Settings -->
                        <?php if ($tab === 'general'): ?>
                            <h4 class="mb-4">General Settings</h4>
                            <div class="mb-4">
                                <h5 class="mb-3">Account Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Account Type</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($_SESSION['user_type']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Subscription Plan</label>
                                        <input type="text" class="form-control" value="<?php echo ucfirst($_SESSION['subscription_plan'] ?? 'free'); ?> Plan" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="mb-3">Preferences</h5>
                                <div class="mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select">
                                        <option>UTC</option>
                                        <option selected>Asia/Karachi (PKT)</option>
                                        <option>America/New_York (EST)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="profile.php" class="btn btn-primary">
                                    <i class="fas fa-edit me-2"></i> Edit Profile
                                </a>
                            </div>

                        <!-- Security Settings -->
                        <?php elseif ($tab === 'security'): ?>
                            <h4 class="mb-4">Security Settings</h4>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                    <div class="form-text">Password must be at least 6 characters long</div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-3">Security Features</h5>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="twoFactorSwitch">
                                <label class="form-check-label" for="twoFactorSwitch">Two-Factor Authentication</label>
                            </div>

                        <!-- Notification Settings -->
                        <?php elseif ($tab === 'notifications'): ?>
                            <h4 class="mb-4">Notification Settings</h4>
                            <div class="mb-4">
                                <h5 class="mb-3">Email Notifications</h5>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="orderEmails" checked>
                                    <label class="form-check-label" for="orderEmails">
                                        Order updates and shipping notifications
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="promotionEmails">
                                    <label class="form-check-label" for="promotionEmails">
                                        Promotional emails and offers
                                    </label>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="newsletterEmails">
                                    <label class="form-check-label" for="newsletterEmails">
                                        Newsletter and updates
                                    </label>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="saveNotificationSettings()">
                                <i class="fas fa-save me-2"></i> Save Preferences
                            </button>

                        <!-- Billing Settings -->
                        <?php elseif ($tab === 'billing'): ?>
                            <h4 class="mb-4">Billing Settings</h4>
                            
                            <div class="alert alert-info mb-4">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-info-circle fa-2x me-3"></i>
                                    <div>
                                        <strong>Current Plan: <?php echo ucfirst($_SESSION['subscription_plan'] ?? 'free'); ?> Plan</strong>
                                        <p class="mb-0">Manage your subscription and billing information</p>
                                    </div>
                                </div>
                            </div>
                            
                            <?php 
                            // Get user subscription info
                            $stmt = $db->prepare("SELECT subscription_plan, subscription_expiry FROM users WHERE id = ?");
                            $stmt->execute([$user_id]);
                            $user_info = $stmt->fetch();
                            ?>
                            
                            <div class="mb-4">
                                <h5 class="mb-3">Subscription Details</h5>
                                <table class="table table-bordered">
                                    <tr>
                                        <th>Plan</th>
                                        <td><?php echo ucfirst($user_info['subscription_plan']); ?> Plan</td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge bg-success">Active</span>
                                        </td>
                                    </tr>
                                    <?php if ($user_info['subscription_expiry']): ?>
                                    <tr>
                                        <th>Renewal Date</th>
                                        <td><?php echo date('F d, Y', strtotime($user_info['subscription_expiry'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <a href="upgrade.php" class="btn btn-primary">
                                    <i class="fas fa-crown me-2"></i> Upgrade Plan
                                </a>
                                <?php if ($user_info['subscription_plan'] !== 'free'): ?>
                                    <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelSubscriptionModal">
                                        <i class="fas fa-times me-2"></i> Cancel Subscription
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Cancel Subscription Modal -->
<div class="modal fade" id="cancelSubscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancel Subscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to cancel your subscription?</p>
                <p class="text-muted small">Your access will continue until the end of your current billing period.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="cancelSubscription()">Cancel Subscription</button>
            </div>
        </div>
    </div>
</div>

<script>
function saveNotificationSettings() {
    const settings = {
        order_emails: document.getElementById('orderEmails').checked,
        promotion_emails: document.getElementById('promotionEmails').checked,
        newsletter_emails: document.getElementById('newsletterEmails').checked
    };
    
    // Here you would save to server via AJAX
    // For now, just show success message
    showToast('Notification preferences saved!', 'success');
}

function cancelSubscription() {
    if (confirm('Are you absolutely sure? You will lose access to premium features.')) {
        // AJAX call to cancel subscription
        fetch('cancel-subscription-ajax.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('cancelSubscriptionModal'));
                modal.hide();
                showToast('Subscription cancelled successfully', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showToast(data.message || 'Error cancelling subscription', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error', 'error');
        });
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php require_once '../../includes/footer.php'; ?>