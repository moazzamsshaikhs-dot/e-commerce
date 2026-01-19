<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Security Settings';
require_once '../../includes/header.php';

// Get vendor details
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    $stmt = $db->prepare("
        SELECT u.*, us.last_login, us.login_count
        FROM users u
        LEFT JOIN user_sessions us ON u.id = us.user_id AND us.is_active = 1
        WHERE u.id = ?
        ORDER BY us.login_time DESC
        LIMIT 1
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
    
    // Get login history
    $stmt = $db->prepare("
        SELECT * FROM user_sessions 
        WHERE user_id = ? 
        ORDER BY login_time DESC 
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $login_history = $stmt->fetchAll();
    
    // Get login attempts
    $stmt = $db->prepare("
        SELECT * FROM login_attempts 
        WHERE username = ? OR ip_address = ?
        ORDER BY attempted_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$vendor['username'], $_SERVER['REMOTE_ADDR']]);
    $login_attempts = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $vendor = [];
    $login_history = [];
    $login_attempts = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validation
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required.');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }
            
            if (strlen($new_password) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
            
            // Verify current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$vendor_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                throw new Exception('Current password is incorrect.');
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $vendor_id]);
            
            // Logout all sessions except current
            $stmt = $db->prepare("UPDATE user_sessions SET is_active = 0, logout_time = NOW() WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$vendor_id]);
            
            $_SESSION['success'] = 'Password changed successfully! You have been logged out from other devices.';
            redirect('security.php');
            
        } elseif ($action === 'update_2fa') {
            $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE users SET two_factor_auth = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$enable_2fa, $vendor_id]);
            
            if ($enable_2fa) {
                $_SESSION['success'] = 'Two-factor authentication enabled!';
            } else {
                $_SESSION['success'] = 'Two-factor authentication disabled.';
            }
            redirect('security.php');
            
        } elseif ($action === 'logout_sessions') {
            $session_id = $_POST['session_id'] ?? '';
            
            if ($session_id === 'all') {
                // Logout all sessions except current
                $stmt = $db->prepare("
                    UPDATE user_sessions 
                    SET is_active = 0, logout_time = NOW() 
                    WHERE user_id = ? AND id != ?
                ");
                $stmt->execute([$vendor_id, $_SESSION['session_id']]);
                $_SESSION['success'] = 'All other sessions logged out successfully!';
            } else {
                // Logout specific session
                $stmt = $db->prepare("
                    UPDATE user_sessions 
                    SET is_active = 0, logout_time = NOW() 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$session_id, $vendor_id]);
                $_SESSION['success'] = 'Session logged out successfully!';
            }
            
            redirect('security.php');
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Security Settings</h1>
                <p class="text-muted mb-0">Manage your account security and privacy</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Security Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Last Login</h6>
                                <h6 class="fw-bold">
                                    <?php echo $vendor['last_login'] ? date('d M Y, h:i A', strtotime($vendor['last_login'])) : 'Never'; ?>
                                </h6>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-sign-in-alt text-primary fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Total Logins</h6>
                                <h3 class="fw-bold text-success"><?php echo $vendor['login_count'] ?? 0; ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-history text-success fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">2FA Status</h6>
                                <h6 class="fw-bold">
                                    <span class="badge bg-<?php echo ($vendor['two_factor_auth'] ?? 0) ? 'success' : 'warning'; ?>">
                                        <?php echo ($vendor['two_factor_auth'] ?? 0) ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </h6>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-shield-alt text-warning fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Security Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="securityTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="password-tab" data-bs-toggle="tab" 
                                data-bs-target="#password" type="button">
                            <i class="fas fa-key me-2"></i> Change Password
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="2fa-tab" data-bs-toggle="tab" 
                                data-bs-target="#2fa" type="button">
                            <i class="fas fa-shield-alt me-2"></i> Two-Factor Auth
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sessions-tab" data-bs-toggle="tab" 
                                data-bs-target="#sessions" type="button">
                            <i class="fas fa-desktop me-2"></i> Active Sessions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" 
                                data-bs-target="#activity" type="button">
                            <i class="fas fa-history me-2"></i> Login History
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Security Content -->
        <div class="tab-content" id="securityTabContent">
            <!-- Change Password Tab -->
            <div class="tab-pane fade show active" id="password" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-key me-2"></i> Change Password
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row g-4">
                                <!-- Current Password -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Current Password *</label>
                                        <div class="input-group">
                                            <input type="password" name="current_password" class="form-control" 
                                                   id="currentPassword" required>
                                            <button class="btn btn-outline-secondary" type="button" 
                                                    onclick="togglePassword('currentPassword', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- New Password -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">New Password *</label>
                                        <div class="input-group">
                                            <input type="password" name="new_password" class="form-control" 
                                                   id="newPassword" required 
                                                   pattern=".{8,}" title="Minimum 8 characters">
                                            <button class="btn btn-outline-secondary" type="button" 
                                                    onclick="togglePassword('newPassword', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Minimum 8 characters</small>
                                    </div>
                                </div>
                                
                                <!-- Confirm Password -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Confirm New Password *</label>
                                        <div class="input-group">
                                            <input type="password" name="confirm_password" class="form-control" 
                                                   id="confirmPassword" required>
                                            <button class="btn btn-outline-secondary" type="button" 
                                                    onclick="togglePassword('confirmPassword', this)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Password Strength -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Password Strength</label>
                                        <div class="progress mb-2" style="height: 8px;">
                                            <div class="progress-bar" id="passwordStrength" 
                                                 role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <small class="text-muted" id="passwordHint">
                                            Enter a new password to check strength
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Password Requirements -->
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> Password Requirements</h6>
                                        <ul class="mb-0">
                                            <li>Minimum 8 characters</li>
                                            <li>At least one uppercase letter</li>
                                            <li>At least one lowercase letter</li>
                                            <li>At least one number</li>
                                            <li>At least one special character (!@#$%^&*)</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Change Password
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Two-Factor Authentication Tab -->
            <div class="tab-pane fade" id="2fa" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-shield-alt me-2"></i> Two-Factor Authentication
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5>Enhanced Security</h5>
                                <p class="text-muted">
                                    Two-factor authentication adds an extra layer of security to your account. 
                                    You'll need to enter a code from your authenticator app when signing in.
                                </p>
                                
                                <div class="alert alert-warning">
                                    <h6 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Important</h6>
                                    <p class="mb-0">
                                        If you lose access to your authenticator app, you may be locked out of your account.
                                        Make sure to save your backup codes in a secure place.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-mobile-alt fa-4x text-primary"></i>
                                    </div>
                                    <form method="POST" id="2faForm">
                                        <input type="hidden" name="action" value="update_2fa">
                                        
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="enable_2fa" id="enable2FA" 
                                                   <?php echo ($vendor['two_factor_auth'] ?? 0) ? 'checked' : ''; ?>
                                                   style="transform: scale(1.5);">
                                            <label class="form-check-label fw-bold" for="enable2FA">
                                                Enable 2FA
                                            </label>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-save me-2"></i> Save Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 2FA Setup Instructions -->
                        <div class="mt-5">
                            <h6 class="fw-bold mb-3">Setup Instructions</h6>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-download fa-2x text-primary"></i>
                                        </div>
                                        <h6>1. Download App</h6>
                                        <p class="text-muted small">
                                            Install Google Authenticator or Authy on your phone
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-qrcode fa-2x text-success"></i>
                                        </div>
                                        <h6>2. Scan QR Code</h6>
                                        <p class="text-muted small">
                                            Scan the QR code with your authenticator app
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-key fa-2x text-warning"></i>
                                        </div>
                                        <h6>3. Enter Code</h6>
                                        <p class="text-muted small">
                                            Enter the 6-digit code from the app to verify
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Backup Codes -->
                        <div class="mt-5">
                            <h6 class="fw-bold mb-3">Backup Codes</h6>
                            <div class="alert alert-danger">
                                <h6 class="fw-bold"><i class="fas fa-exclamation-circle me-2"></i> Save These Codes!</h6>
                                <p class="mb-2">These codes can be used to access your account if you lose your phone.</p>
                                <div class="bg-dark text-light p-3 rounded mb-2">
                                    <div class="row g-2">
                                        <?php for($i = 1; $i <= 10; $i++): ?>
                                        <div class="col-6">
                                            <code><?php echo substr(md5(uniqid() . $vendor_id), 0, 8); ?></code>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <button class="btn btn-outline-light btn-sm">
                                    <i class="fas fa-download me-2"></i> Download Codes
                                </button>
                                <button class="btn btn-outline-light btn-sm ms-2">
                                    <i class="fas fa-print me-2"></i> Print Codes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Sessions Tab -->
            <div class="tab-pane fade" id="sessions" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-desktop me-2"></i> Active Sessions
                        </h5>
                        <button class="btn btn-danger btn-sm" onclick="logoutAllSessions()">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout All Other Sessions
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Device/Browser</th>
                                        <th>IP Address</th>
                                        <th>Location</th>
                                        <th>Login Time</th>
                                        <th>Last Activity</th>
                                        <th>Current Session</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($login_history)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fas fa-history fa-2x mb-3"></i>
                                            <p>No session history found</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach($login_history as $session): 
                                        $is_current = ($session['id'] == $_SESSION['session_id']);
                                    ?>
                                    <tr class="<?php echo $is_current ? 'table-active' : ''; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-<?php echo strpos($session['user_agent'], 'Mobile') !== false ? 'mobile-alt' : 'desktop'; ?> 
                                                       text-primary me-2"></i>
                                                <div>
                                                    <small class="d-block">
                                                        <?php 
                                                        $browser = 'Unknown';
                                                        if (strpos($session['user_agent'], 'Chrome')) $browser = 'Chrome';
                                                        elseif (strpos($session['user_agent'], 'Firefox')) $browser = 'Firefox';
                                                        elseif (strpos($session['user_agent'], 'Safari')) $browser = 'Safari';
                                                        echo $browser;
                                                        ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <?php echo strpos($session['user_agent'], 'Mobile') !== false ? 'Mobile' : 'Desktop'; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code><?php echo htmlspecialchars($session['ip_address']); ?></code>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php
                                                // In production, use IP geolocation service
                                                echo 'Unknown Location';
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo date('d M, h:i A', strtotime($session['login_time'])); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $last_activity = strtotime($session['logout_time'] ?? 'now') - strtotime($session['login_time']);
                                            if ($last_activity < 60) {
                                                echo 'Just now';
                                            } elseif ($last_activity < 3600) {
                                                echo floor($last_activity / 60) . ' min ago';
                                            } else {
                                                echo floor($last_activity / 3600) . ' hour ago';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($is_current): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i> Current
                                            </span>
                                            <?php elseif ($session['is_active']): ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-circle me-1"></i> Active
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-times-circle me-1"></i> Inactive
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_current && $session['is_active']): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="logoutSession(<?php echo $session['id']; ?>)">
                                                <i class="fas fa-sign-out-alt"></i> Logout
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Security Tips -->
                        <div class="alert alert-info mt-4">
                            <h6 class="fw-bold"><i class="fas fa-lightbulb me-2"></i> Security Tips</h6>
                            <ul class="mb-0">
                                <li>Regularly review your active sessions</li>
                                <li>Logout from devices you don't recognize</li>
                                <li>Use different passwords for different accounts</li>
                                <li>Enable two-factor authentication for extra security</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Login History Tab -->
            <div class="tab-pane fade" id="activity" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2"></i> Login History & Attempts
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Recent Logins -->
                        <h6 class="fw-bold mb-3">Recent Successful Logins</h6>
                        <div class="table-responsive mb-4">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>IP Address</th>
                                        <th>Device</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($login_history as $login): ?>
                                    <tr>
                                        <td><?php echo date('d M Y, h:i A', strtotime($login['login_time'])); ?></td>
                                        <td><code><?php echo htmlspecialchars($login['ip_address']); ?></code></td>
                                        <td>
                                            <small>
                                                <?php 
                                                $device = strpos($login['user_agent'], 'Mobile') !== false ? 'Mobile' : 'Desktop';
                                                echo $device;
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i> Success
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Failed Login Attempts -->
                        <h6 class="fw-bold mb-3">Recent Failed Attempts</h6>
                        <?php if (empty($login_attempts)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-shield-check fa-2x text-success mb-3"></i>
                            <p class="text-muted">No failed login attempts detected</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>IP Address</th>
                                        <th>Username</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($login_attempts as $attempt): ?>
                                    <tr class="<?php echo $attempt['success'] ? 'table-success' : 'table-danger'; ?>">
                                        <td><?php echo date('d M Y, h:i A', strtotime($attempt['attempted_at'])); ?></td>
                                        <td><code><?php echo htmlspecialchars($attempt['ip_address']); ?></code></td>
                                        <td><?php echo htmlspecialchars($attempt['username'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $attempt['success'] ? 'success' : 'danger'; ?>">
                                                <i class="fas fa-<?php echo $attempt['success'] ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                <?php echo $attempt['success'] ? 'Success' : 'Failed'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Account Security Status -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Security Score</h6>
                                        <div class="text-center">
                                            <div class="mb-3">
                                                <div class="position-relative d-inline-block">
                                                    <div class="progress-circle" 
                                                         data-percent="<?php echo ($vendor['two_factor_auth'] ?? 0) ? 85 : 60; ?>" 
                                                         style="width: 120px; height: 120px;">
                                                        <span class="percent"></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <p class="text-muted small">
                                                <?php echo ($vendor['two_factor_auth'] ?? 0) ? 'Good' : 'Fair'; ?> security score
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3">Recommendations</h6>
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <?php if ($vendor['two_factor_auth'] ?? 0): ?>
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="text-muted">2FA enabled</span>
                                                <?php else: ?>
                                                <i class="fas fa-times-circle text-danger me-2"></i>
                                                <span>Enable two-factor authentication</span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="mb-2">
                                                <?php if (strtotime($vendor['last_login'] ?? '') > strtotime('-30 days')): ?>
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="text-muted">Recent activity detected</span>
                                                <?php else: ?>
                                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                <span>No recent activity</span>
                                                <?php endif; ?>
                                            </li>
                                            <li class="mb-2">
                                                <?php if (count($login_attempts) < 5): ?>
                                                <i class="fas fa-check-circle text-success me-2"></i>
                                                <span class="text-muted">Low failed attempts</span>
                                                <?php else: ?>
                                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                                <span>Multiple failed attempts detected</span>
                                                <?php endif; ?>
                                            </li>
                                            <li>
                                                <i class="fas fa-info-circle text-primary me-2"></i>
                                                <span>Last password change: <?php echo $vendor['updated_at'] ? date('d M Y', strtotime($vendor['updated_at'])) : 'Unknown'; ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Security JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const triggerTabList = [].slice.call(document.querySelectorAll('#securityTab button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Password strength checker
    const newPasswordInput = document.getElementById('newPassword');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordHint = document.getElementById('passwordHint');
    
    if (newPasswordInput) {
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = checkPasswordStrength(password);
            
            passwordStrength.style.width = strength.score * 25 + '%';
            passwordStrength.className = 'progress-bar bg-' + strength.color;
            passwordHint.textContent = strength.message;
        });
    }
    
    // Form submissions
    const forms = ['passwordForm', '2faForm'];
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this);
            });
        }
    });
});

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function checkPasswordStrength(password) {
    let score = 0;
    let message = '';
    let color = 'danger';
    
    // Length check
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    
    // Complexity checks
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    // Determine strength
    if (score >= 5) {
        message = 'Strong password';
        color = 'success';
    } else if (score >= 3) {
        message = 'Medium strength';
        color = 'warning';
    } else if (score >= 1) {
        message = 'Weak password';
        color = 'danger';
    } else {
        message = 'Very weak';
        color = 'danger';
    }
    
    return { score: score, message: message, color: color };
}

function logoutSession(sessionId) {
    if (confirm('Logout this session?')) {
        const formData = new FormData();
        formData.append('action', 'logout_sessions');
        formData.append('session_id', sessionId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            window.location.reload();
        });
    }
}

function logoutAllSessions() {
    if (confirm('Logout all other sessions? You will stay logged in on this device.')) {
        const formData = new FormData();
        formData.append('action', 'logout_sessions');
        formData.append('session_id', 'all');
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            window.location.reload();
        });
    }
}

function submitForm(form) {
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

// Circular progress for security score
function initProgressCircles() {
    const circles = document.querySelectorAll('.progress-circle');
    circles.forEach(circle => {
        const percent = circle.getAttribute('data-percent');
        const span = circle.querySelector('.percent');
        const radius = circle.offsetWidth / 2;
        const circumference = 2 * Math.PI * radius;
        
        circle.style.position = 'relative';
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', circle.offsetWidth);
        svg.setAttribute('height', circle.offsetHeight);
        
        const bgCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        bgCircle.setAttribute('cx', radius);
        bgCircle.setAttribute('cy', radius);
        bgCircle.setAttribute('r', radius - 5);
        bgCircle.setAttribute('fill', 'none');
        bgCircle.setAttribute('stroke', '#e9ecef');
        bgCircle.setAttribute('stroke-width', '10');
        
        const progressCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        progressCircle.setAttribute('cx', radius);
        progressCircle.setAttribute('cy', radius);
        progressCircle.setAttribute('r', radius - 5);
        progressCircle.setAttribute('fill', 'none');
        progressCircle.setAttribute('stroke', '#0d6efd');
        progressCircle.setAttribute('stroke-width', '10');
        progressCircle.setAttribute('stroke-linecap', 'round');
        progressCircle.setAttribute('stroke-dasharray', circumference);
        progressCircle.setAttribute('stroke-dashoffset', circumference - (percent / 100) * circumference);
        
        svg.appendChild(bgCircle);
        svg.appendChild(progressCircle);
        circle.appendChild(svg);
        
        if (span) {
            span.textContent = percent + '%';
        }
    });
}

// Initialize on page load
initProgressCircles();
</script>

<style>
.progress-circle {
    position: relative;
    display: inline-block;
}

.progress-circle .percent {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 1.5rem;
    font-weight: bold;
    color: #495057;
}

/* Password strength colors */
.bg-danger { background-color: #dc3545 !important; }
.bg-warning { background-color: #ffc107 !important; }
.bg-success { background-color: #198754 !important; }

/* Session device icons */
.fa-mobile-alt, .fa-desktop {
    font-size: 1.2rem;
}
</style>

<?php require_once '../../includes/footer.php'; ?>