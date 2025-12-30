<?php
require_once 'includes/config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('user/dashboard.php');
    }
}

$errors = [];
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $full_name = sanitize($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (empty($phone)) {
        $errors[] = 'Phone number is required';
    }
    
    if (empty($full_name)) {
        $errors[] = 'Full name is required';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!$terms) {
        $errors[] = 'You must agree to the terms and conditions';
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Check username
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Username already taken';
            }
            
            // Check email
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Email already registered';
            }
            
            // Check phone
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Phone number already registered';
            }
            
        } catch(PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // If no errors, create user
    if (empty($errors)) {
        try {
            $db = getDB();
            
            // Generate OTP
            $otp = generateOTP();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
            
            // Insert user
            $stmt = $db->prepare("
                INSERT INTO users (username, email, phone, full_name, password, otp_code, otp_expiry) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $hashed_password = hashPassword($password);
            $stmt->execute([
                $username, 
                $email, 
                $phone, 
                $full_name, 
                $hashed_password, 
                $otp, 
                $otp_expiry
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Insert OTP record
            $stmt = $db->prepare("
                INSERT INTO otp_verification (user_id, otp_code, otp_type, expires_at) 
                VALUES (?, ?, 'email', ?)
            ");
            $stmt->execute([$user_id, $otp, $otp_expiry]);
            
            // Send OTP (simulated)
            sendOTPEmail($email, $otp);
            sendOTPSMS($phone, $otp);
            
            // Store user ID in session for OTP verification
            $_SESSION['temp_user_id'] = $user_id;
            $_SESSION['temp_email'] = $email;
            $_SESSION['temp_phone'] = $phone;
            
            // Set success message
            $_SESSION['success'] = 'Account created successfully! Please verify your email with OTP.';
            
            // Redirect to OTP verification
            redirect('verify-otp.php');
            
        } catch(PDOException $e) {
            $errors[] = 'Error creating account: ' . $e->getMessage();
        }
    }
}

$page_title = 'Sign Up';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Create Your Account</h2>
            <p class="text-muted">Join our e-commerce platform today</p>
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
        
        <form method="POST" action="" id="signupForm">
            <!-- Username -->
            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="fas fa-user me-1"></i> Username *
                </label>
                <input type="text" class="form-control" id="username" name="username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                       required minlength="3">
                <div class="form-text">At least 3 characters</div>
            </div>
            
            <!-- Email -->
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-1"></i> Email Address *
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required>
            </div>
            
            <!-- Phone -->
            <div class="mb-3">
                <label for="phone" class="form-label">
                    <i class="fas fa-phone me-1"></i> Phone Number *
                </label>
                <input type="tel" class="form-control" id="phone" name="phone" 
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                       required pattern="[0-9+\-\s()]{10,}">
                <div class="form-text">Format: +1234567890</div>
            </div>
            
            <!-- Full Name -->
            <div class="mb-3">
                <label for="full_name" class="form-label">
                    <i class="fas fa-id-card me-1"></i> Full Name *
                </label>
                <input type="text" class="form-control" id="full_name" name="full_name" 
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                       required>
            </div>
            
            <!-- Password -->
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-1"></i> Password *
                </label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" 
                           required minlength="6">
                    <span class="input-group-text password-toggle">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
                <div class="form-text">At least 6 characters</div>
            </div>
            
            <!-- Confirm Password -->
            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    <i class="fas fa-lock me-1"></i> Confirm Password *
                </label>
                <div class="input-group">
                    <input type="password" class="form-control" id="confirm_password" 
                           name="confirm_password" required>
                    <span class="input-group-text password-toggle">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <!-- Terms and Conditions -->
            <div class="mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a> 
                        and <a href="privacy.php" target="_blank">Privacy Policy</a> *
                    </label>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-user-plus me-2"></i> Create Account
                </button>
            </div>
            
            <!-- Already have account -->
            <div class="text-center mt-4 auth-links">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </form>
        
        <!-- Social Signup (Optional) -->
        <div class="mt-4">
            <div class="text-center mb-3">
                <hr>
                <span class="bg-white px-3 text-muted">Or sign up with</span>
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