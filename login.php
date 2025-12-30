<?php
// require_once 'includes/config.php';

// // If user is already logged in, redirect to dashboard
// if (isLoggedIn()) {
//     if (isAdmin()) {
//         redirect('admin/dashboard.php');
//     } else {
//         redirect('user/dashboard.php');
//     }
// }

// $errors = [];
// $success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
// if (isset($_SESSION['success'])) unset($_SESSION['success']);

// // Process form submission
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     $username = sanitize($_POST['username']);
//     $password = $_POST['password'];
//     $remember = isset($_POST['remember']) ? true : false;
    
//     // Validation
//     if (empty($username)) {
//         $errors[] = 'Username or Email is required';
//     }
    
//     if (empty($password)) {
//         $errors[] = 'Password is required';
//     }
    
//     // If no errors, attempt login
//     if (empty($errors)) {
//         try {
//             $db = getDB();
            
//             // Find user by username or email
//             $stmt = $db->prepare("
//                 SELECT * FROM users 
//                 WHERE (username = ? OR email = ?) 
//                 AND email_verified = 1
//             ");
//             $stmt->execute([$username, $username]);
//             $user = $stmt->fetch();
            
//             if ($user && verifyPassword($password, $user['password'])) {
//                 // Check if account is verified
//                 if (!$user['email_verified']) {
//                     // Store user ID for OTP verification
//                     $_SESSION['temp_user_id'] = $user['id'];
//                     $_SESSION['temp_email'] = $user['email'];
                    
//                     // Generate and send new OTP
//                     $otp = generateOTP();
//                     $otp_expiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
                    
//                     $stmt = $db->prepare("
//                         UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?
//                     ");
//                     $stmt->execute([$otp, $otp_expiry, $user['id']]);
                    
//                     // Send OTP
//                     sendOTPEmail($user['email'], $otp);
                    
//                     $_SESSION['error'] = 'Please verify your email first. OTP has been sent again.';
//                     redirect('verify-otp.php');
//                 }
                
//                 // Set session variables
//                 $_SESSION['user_id'] = $user['id'];
//                 $_SESSION['username'] = $user['username'];
//                 $_SESSION['email'] = $user['email'];
//                 $_SESSION['full_name'] = $user['full_name'];
//                 $_SESSION['user_type'] = $user['user_type'];
//                 $_SESSION['profile_pic'] = $user['profile_pic'];
//                 $_SESSION['login_time'] = time();
                
//                 // Remember me functionality (30 days)
//                 if ($remember) {
//                     $token = bin2hex(random_bytes(32));
//                     $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
//                     $stmt = $db->prepare("
//                         INSERT INTO user_sessions (user_id, token, expires_at) 
//                         VALUES (?, ?, ?)
//                     ");
//                     $stmt->execute([$user['id'], $token, $expiry]);
                    
//                     setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/');
//                 }
                
//                 // Update last login
//                 $stmt = $db->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
//                 $stmt->execute([$user['id']]);
                
//                 // Set success message
//                 $_SESSION['success'] = 'Welcome back, ' . $user['full_name'] . '!';
                
//                 // Redirect based on user type
//                 if ($user['user_type'] === 'admin') {
//                     redirect('admin/dashboard.php');
//                 } else {
//                     redirect('user/dashboard.php');
//                 }
                
//             } else {
//                 $errors[] = 'Invalid username/email or password';
//             }
            
//         } catch(PDOException $e) {
//             $errors[] = 'Login error: ' . $e->getMessage();
//         }
//     }
// }

// $page_title = 'Login';
// require_once 'includes/header.php';


require_once 'includes/config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}

// Security: Check if IP is blocked
$user_ip = getUserIP();
if (isIPBlocked($user_ip)) {
    $_SESSION['error'] = 'Too many failed login attempts. Please try again after ' . LOGIN_TIMEOUT_MINUTES . ' minutes.';
    trackLoginAttempt('', $user_ip, false);
    // Don't redirect, show error on same page
}

$errors = [];
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
if (isset($_SESSION['success'])) unset($_SESSION['success']);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isIPBlocked($user_ip)) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username or Email is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    }
    
    // If no errors, attempt login
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Find user by username or email
            $stmt = $db->prepare("
                SELECT * FROM users 
                WHERE (username = ? OR email = ?) 
                AND account_status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password'])) {
                // Check if account is verified
                if (!$user['email_verified']) {
                    // Store user ID for OTP verification
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['temp_email'] = $user['email'];
                    
                    // Generate and send new OTP
                    $otp = generateOTP();
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
                    
                    $stmt = $db->prepare("
                        UPDATE users SET otp_code = ?, otp_expiry = ? WHERE id = ?
                    ");
                    $stmt->execute([$otp, $otp_expiry, $user['id']]);
                    
                    // Send OTP
                    sendOTPEmail($user['email'], $otp);
                    
                    // Track successful attempt (before OTP)
                    trackLoginAttempt($username, $user_ip, true);
                    
                    $_SESSION['error'] = 'Please verify your email first. OTP has been sent again.';
                    redirect('verify-otp.php');
                }
                
                // Generate session token
                $session_token = generateSecureToken();
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['profile_pic'] = $user['profile_pic'];
                $_SESSION['subscription_plan'] = $user['subscription_plan'];
                $_SESSION['session_token'] = $session_token;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Create session record in database
                createUserSession(
                    $user['id'], 
                    $session_token, 
                    $user_ip, 
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                );
                
                // Update user last login and count
                $stmt = $db->prepare("
                    UPDATE users 
                    SET last_login = NOW(), login_count = login_count + 1 
                    WHERE id = ?
                ");
                $stmt->execute([$user['id']]);
                
                // Remember me functionality (30 days)
                if ($remember) {
                    $remember_token = generateSecureToken();
                    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = $db->prepare("
                        INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$user['id'], $remember_token, $user_ip, $_SERVER['HTTP_USER_AGENT'] ?? '']);
                    
                    setcookie('remember_token', $remember_token, time() + (30 * 24 * 60 * 60), '/');
                    setcookie('user_id', $user['id'], time() + (30 * 24 * 60 * 60), '/');
                }
                
                // Log successful login activity
                logUserActivity($user['id'], 'login', 'User logged in successfully');
                
                // Send security alert email
                sendSecurityAlert($user['id'], 'login');
                
                // Track successful attempt
                trackLoginAttempt($username, $user_ip, true);
                
                // Set success message
                $_SESSION['success'] = 'Welcome back, ' . $user['full_name'] . '!';
                
                // Check if subscription is active
                if (!isSubscriptionActive($user['id']) && $user['subscription_plan'] !== 'free') {
                    $_SESSION['warning'] = 'Your subscription has expired. You have been downgraded to Free plan.';
                }
                
                // Redirect based on user type
                redirectToDashboard();
                
            } else {
                // Invalid credentials
                $errors[] = 'Invalid username/email or password';
                
                // Track failed attempt
                trackLoginAttempt($username, $user_ip, false);
                
                // Log failed login attempt
                logUserActivity(0, 'failed_login', 'Failed login attempt for username: ' . $username);
            }
            
        } catch(PDOException $e) {
            $errors[] = 'Login error: ' . $e->getMessage();
            trackLoginAttempt($username, $user_ip, false);
        }
    }
}

// Function to redirect to appropriate dashboard
function redirectToDashboard() {
    global $user;
    if ($_SESSION['user_type'] === 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}

$page_title = 'Login';
require_once 'includes/header.php';
?>

<!-- // Rest of the login form remains same... -->


<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Welcome Back</h2>
            <p class="text-muted">Login to your account</p>
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
        
        <form method="POST" action="" id="loginForm">
            <!-- Username/Email -->
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="fas fa-user me-1"></i> Username or Email *
                </label>
                <input type="text" class="form-control" id="username" name="username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                       required>
            </div>
            
            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-1"></i> Password *
                </label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <span class="input-group-text password-toggle">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <!-- Remember me & Forgot password -->
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Remember me
                    </label>
                </div>
                <a href="forgot-password.php" class="text-decoration-none">
                    Forgot Password?
                </a>
            </div>
            
            <!-- Submit Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i> Login
                </button>
            </div>
            
            <!-- Don't have account -->
            <div class="text-center mt-4 auth-links">
                <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
            </div>
        </form>
        
        <!-- Social Login (Optional) -->
        <div class="mt-4">
            <div class="text-center mb-3">
                <hr>
                <span class="bg-white px-3 text-muted">Or login with</span>
            </div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-primary">
                    <i class="fab fa-google me-2"></i> Google
                </button>
                <button type="button" class="btn btn-outline-primary">
                    <i class="fab fa-facebook me-2"></i> Facebook
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>