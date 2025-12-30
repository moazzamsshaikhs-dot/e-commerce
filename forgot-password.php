<?php
require_once 'includes/config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('user/dashboard.php');
}

$errors = [];
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    } else {
        try {
            $db = getDB();
            
            // Check if email exists
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store reset token
                $stmt = $db->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at) 
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user['id'], $reset_token, $expiry]);
                
                // Send reset email (simulated)
                $reset_link = SITE_URL . "reset-password.php?token=$reset_token";
                error_log("Reset link for $email: $reset_link");
                
                $success = 'Password reset link has been sent to your email.';
                
            } else {
                // For security, don't reveal if email exists
                $success = 'If your email is registered, you will receive a reset link.';
            }
            
        } catch(PDOException $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

$page_title = 'Forgot Password';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <div class="mb-3">
                <i class="fas fa-key fa-3x text-primary"></i>
            </div>
            <h2 class="fw-bold">Forgot Password</h2>
            <p class="text-muted">Enter your email to reset your password</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <!-- Email -->
            <div class="mb-4">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-1"></i> Email Address *
                </label>
                <input type="email" class="form-control" id="email" name="email" required>
                <div class="form-text">
                    We'll send a password reset link to this email
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i> Send Reset Link
                </button>
            </div>
            
            <!-- Back to login -->
            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>