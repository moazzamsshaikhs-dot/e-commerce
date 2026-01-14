<?php
require_once 'includes/config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}

$errors = [];
$success = '';

// Add this new field for vendor registration
$register_as_vendor = isset($_POST['register_as_vendor']) ? true : false;

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
    
    // NEW: Vendor-specific fields
    $vendor_category = isset($_POST['vendor_category']) ? sanitize($_POST['vendor_category']) : '';
    $vendor_bio = isset($_POST['vendor_bio']) ? sanitize($_POST['vendor_bio']) : '';
    
    // Validation (same as before with additions)
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
    
    // Vendor-specific validation
    if ($register_as_vendor) {
        if (empty($vendor_category)) {
            $errors[] = 'Vendor category is required';
        }
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
            
            // Determine user type
            $user_type = $register_as_vendor ? 'vendor' : 'user';
            $vendor_status = $register_as_vendor ? 'pending' : NULL;
            
            // Insert user
            $stmt = $db->prepare("
                INSERT INTO users (
                    username, email, phone, full_name, password, 
                    otp_code, otp_expiry, user_type, vendor_status,
                    vendor_category, vendor_bio
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $hashed_password = hashPassword($password);
            $stmt->execute([
                $username, 
                $email, 
                $phone, 
                $full_name, 
                $hashed_password, 
                $otp, 
                $otp_expiry,
                $user_type,
                $vendor_status,
                $vendor_category,
                $vendor_bio
            ]);
            
            $user_id = $db->lastInsertId();
            
            // Insert OTP record
            $stmt = $db->prepare("
                INSERT INTO otp_verification (user_id, otp_code, otp_type, expires_at) 
                VALUES (?, ?, 'email', ?)
            ");
            $stmt->execute([$user_id, $otp, $otp_expiry]);
            
            // Send OTP
            sendOTPEmail($email, $otp);
            sendOTPSMS($phone, $otp);
            
            // If vendor, send admin notification
            if ($register_as_vendor) {
                // Send email to admin about new vendor registration
                sendVendorRegistrationAlert($user_id);
            }
            
            // Store user ID in session for OTP verification
            $_SESSION['temp_user_id'] = $user_id;
            $_SESSION['temp_email'] = $email;
            $_SESSION['temp_phone'] = $phone;
            $_SESSION['register_as_vendor'] = $register_as_vendor;
            
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
            <!-- Account Type Selection -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="fas fa-user-tag me-1"></i> Register As:
                </label>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-check card p-3">
                            <input class="form-check-input" type="radio" name="register_as_vendor" 
                                   id="register_customer" value="0" checked onclick="toggleVendorFields(false)">
                            <label class="form-check-label" for="register_customer">
                                <h5 class="mb-1"><i class="fas fa-shopping-cart me-2"></i> Customer</h5>
                                <small class="text-muted">Shop and purchase products</small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check card p-3">
                            <input class="form-check-input" type="radio" name="register_as_vendor" 
                                   id="register_vendor" value="1" onclick="toggleVendorFields(true)">
                            <label class="form-check-label" for="register_vendor">
                                <h5 class="mb-1"><i class="fas fa-store me-2"></i> Vendor</h5>
                                <small class="text-muted">Sell your products on our platform</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vendor Fields (Initially Hidden) -->
            <div id="vendorFields" style="display: none;">
                <div class="card p-3 mb-4 bg-light">
                    <h5 class="mb-3"><i class="fas fa-store-alt me-2"></i> Vendor Information</h5>
                    
                    <!-- Vendor Category -->
                    <div class="mb-3">
                        <label for="vendor_category" class="form-label">
                            <i class="fas fa-tags me-1"></i> What do you want to sell? *
                        </label>
                        <select class="form-select" id="vendor_category" name="vendor_category">
                            <option value="">Select Category</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Fashion">Fashion & Clothing</option>
                            <option value="Home & Living">Home & Living</option>
                            <option value="Books">Books & Stationery</option>
                            <option value="Sports">Sports & Fitness</option>
                            <option value="Beauty">Beauty & Cosmetics</option>
                            <option value="Food">Food & Beverages</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <!-- Vendor Bio -->
                    <div class="mb-3">
                        <label for="vendor_bio" class="form-label">
                            <i class="fas fa-info-circle me-1"></i> Tell us about your business
                        </label>
                        <textarea class="form-control" id="vendor_bio" name="vendor_bio" 
                                  rows="3" placeholder="Brief description of your products or services..."><?php echo isset($_POST['vendor_bio']) ? htmlspecialchars($_POST['vendor_bio']) : ''; ?></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Vendor accounts require admin approval before you can start selling.
                    </div>
                </div>
            </div>
            
            <!-- Common Fields (same as before) -->
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
        
        <!-- JavaScript to toggle vendor fields -->
        <script>
        function toggleVendorFields(show) {
            const vendorFields = document.getElementById('vendorFields');
            const vendorCategory = document.getElementById('vendor_category');
            
            if (show) {
                vendorFields.style.display = 'block';
                vendorCategory.required = true;
            } else {
                vendorFields.style.display = 'none';
                vendorCategory.required = false;
            }
        }
        
        // Password toggle functionality
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.parentNode.querySelector('input');
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        </script>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>