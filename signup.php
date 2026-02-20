<?php
// signup.php
require_once 'includes/config.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}

$errors = [];
$success = '';

// Initialize variables
$register_as_vendor = false;
$form_data = [
    'username' => '',
    'email' => '',
    'phone' => '',
    'full_name' => '',
    'country' => '',
    'vendor_category' => '',
    'vendor_bio' => ''
];

// Get categories from database for vendor dropdown
try {
    $db = getDB();
    
    // Get product categories for vendor
    $stmt = $db->query("SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
    
    // Get countries for dropdown
    $stmt = $db->query("
        SELECT code, name FROM countries 
        WHERE is_active = 1 
        ORDER BY CASE 
            WHEN code IN ('US', 'GB', 'CA', 'AU', 'PK', 'IN', 'AE') THEN 0 
            ELSE 1 
        END, name
    ");
    $countries = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $categories = [];
    $countries = [];
    error_log("Signup page error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check if vendor option is selected - FIXED: Check after form submission
    $register_as_vendor = isset($_POST['register_as_vendor']) && $_POST['register_as_vendor'] === '1';
    
    // Store form data for repopulation
    $form_data = [
        'username' => sanitize($_POST['username'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'phone' => sanitize($_POST['phone'] ?? ''),
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'country' => sanitize($_POST['country'] ?? ''),
        'vendor_category' => sanitize($_POST['vendor_category'] ?? ''),
        'vendor_bio' => sanitize($_POST['vendor_bio'] ?? '')
    ];

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']);

    // Validation
    if (empty($form_data['username'])) {
        $errors[] = 'Username is required';
    } elseif (strlen($form_data['username']) < 3) {
        $errors[] = 'Username must be at least 3 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $form_data['username'])) {
        $errors[] = 'Username can only contain letters, numbers and underscores';
    }

    if (empty($form_data['email'])) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (empty($form_data['phone'])) {
        $errors[] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9+\-\s()]{10,}$/', $form_data['phone'])) {
        $errors[] = 'Invalid phone number format';
    }

    if (empty($form_data['full_name'])) {
        $errors[] = 'Full name is required';
    }

    if (empty($form_data['country'])) {
        $errors[] = 'Please select your country';
    }

    // Vendor-specific validation - ONLY if vendor is selected
    if ($register_as_vendor) {
        if (empty($form_data['vendor_category'])) {
            $errors[] = 'Please select a vendor category';
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
            $stmt->execute([$form_data['username']]);
            if ($stmt->fetch()) {
                $errors[] = 'Username already taken';
            }

            // Check email
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$form_data['email']]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already registered';
            }

            // Check phone
            $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$form_data['phone']]);
            if ($stmt->fetch()) {
                $errors[] = 'Phone number already registered';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
            error_log("Signup check error: " . $e->getMessage());
        }
    }

    // If no errors, create user
    if (empty($errors)) {
        try {
            $db = getDB();

            // Generate OTP
            $otp = generateOTP();
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));

            // Determine user type and status
            $user_type = $register_as_vendor ? 'vendor' : 'user';
            $vendor_status = $register_as_vendor ? 'pending' : null;

            // Insert user
            $stmt = $db->prepare("
                INSERT INTO users (
                    username, email, phone, full_name, password, country,
                    otp_code, otp_expiry, user_type, vendor_status,
                    vendor_category, vendor_bio, created_at, updated_at
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            $hashed_password = hashPassword($password);
            $result = $stmt->execute([
                $form_data['username'],
                $form_data['email'],
                $form_data['phone'],
                $form_data['full_name'],
                $hashed_password,
                $form_data['country'],
                $otp,
                $otp_expiry,
                $user_type,
                $vendor_status,
                $register_as_vendor ? $form_data['vendor_category'] : null,
                $register_as_vendor ? $form_data['vendor_bio'] : null,
            ]);

            if (!$result) {
                throw new Exception('Failed to create user account');
            }

            $user_id = $db->lastInsertId();

            // Insert OTP record
            $stmt = $db->prepare("
                INSERT INTO otp_verification (user_id, otp_code, otp_type, expires_at, created_at) 
                VALUES (?, ?, 'email', ?, NOW())
            ");
            $stmt->execute([$user_id, $otp, $otp_expiry]);

            // Send OTP
            sendOTPEmail($form_data['email'], $otp);
            sendOTPSMS($form_data['phone'], $otp);

            // If vendor, send admin notification
            if ($register_as_vendor) {
                sendVendorRegistrationAlert($user_id);
            }

            // Store user ID in session for OTP verification
            $_SESSION['temp_user_id'] = $user_id;
            $_SESSION['temp_email'] = $form_data['email'];
            $_SESSION['temp_phone'] = $form_data['phone'];
            $_SESSION['register_as_vendor'] = $register_as_vendor;

            // Log activity
            logUserActivity($user_id, 'registration', 'User registered as ' . $user_type);

            // Set success message
            $_SESSION['success'] = 'Account created successfully! Please verify your email with OTP.';

            // Redirect to OTP verification
            redirect('verify-otp.php');
            
        } catch (Exception $e) {
            $errors[] = 'Error creating account: ' . $e->getMessage();
            error_log("Signup error: " . $e->getMessage());
        }
    }
} else {
    // On initial page load, set register_as_vendor to false
    $register_as_vendor = false;
}

$page_title = 'Sign Up';
require_once 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="auth-container">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">Create Your Account</h2>
                    <p class="text-muted">Join our e-commerce platform today</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="signupForm" class="needs-validation" novalidate>
                    <!-- Account Type Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="fas fa-user-tag me-1"></i> I want to register as:
                        </label>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check card p-3 border-2 account-type-card <?php echo !$register_as_vendor ? 'border-primary active-card' : ''; ?>" 
                                     data-type="customer">
                                    <input class="form-check-input account-type-radio" type="radio" name="register_as_vendor"
                                        id="register_customer" value="0" <?php echo !$register_as_vendor ? 'checked' : ''; ?>>
                                    <div class="text-center">
                                        <i class="fas fa-shopping-cart fa-3x mb-2 text-primary"></i>
                                        <h5 class="mb-1">Customer</h5>
                                        <small class="text-muted">Shop and purchase products</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check card p-3 border-2 account-type-card <?php echo $register_as_vendor ? 'border-primary active-card' : ''; ?>"
                                     data-type="vendor">
                                    <input class="form-check-input account-type-radio" type="radio" name="register_as_vendor"
                                        id="register_vendor" value="1" <?php echo $register_as_vendor ? 'checked' : ''; ?>>
                                    <div class="text-center">
                                        <i class="fas fa-store fa-3x mb-2 text-success"></i>
                                        <h5 class="mb-1">Vendor</h5>
                                        <small class="text-muted">Sell your products on our platform</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Common Fields for ALL users -->
                    <div class="common-fields">
                        <h5 class="mb-3"><i class="fas fa-user me-2 text-primary"></i> Account Information</h5>
                        
                        <div class="row">
                            <!-- Username -->
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label fw-bold">
                                    <i class="fas fa-user me-1"></i> Username *
                                </label>
                                <input type="text" class="form-control" id="username" name="username"
                                    value="<?php echo htmlspecialchars($form_data['username']); ?>"
                                    required minlength="3" pattern="[a-zA-Z0-9_]+">
                                <div class="form-text username-feedback"></div>
                            </div>

                            <!-- Full Name -->
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label fw-bold">
                                    <i class="fas fa-id-card me-1"></i> Full Name *
                                </label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                    value="<?php echo htmlspecialchars($form_data['full_name']); ?>"
                                    required>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">
                                <i class="fas fa-envelope me-1"></i> Email Address *
                            </label>
                            <input type="email" class="form-control" id="email" name="email"
                                value="<?php echo htmlspecialchars($form_data['email']); ?>"
                                required>
                        </div>

                        <!-- Phone -->
                        <div class="mb-3">
                            <label for="phone" class="form-label fw-bold">
                                <i class="fas fa-phone me-1"></i> Phone Number *
                            </label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                required pattern="[0-9+\-\s()]{10,}">
                            <div class="form-text">Format: +1234567890 or 03001234567</div>
                        </div>

                        <!-- Country -->
                        <div class="mb-3">
                            <label for="country" class="form-label fw-bold">
                                <i class="fas fa-globe me-1"></i> Country *
                            </label>
                            <select name="country" id="country" class="form-select" required>
                                <option value="">-- Select Country --</option>
                                <?php if (!empty($countries)): ?>
                                    <?php foreach ($countries as $country_item): ?>
                                        <option value="<?php echo $country_item['code']; ?>"
                                            <?php echo ($form_data['country'] == $country_item['code']) ? 'selected' : ''; ?>>
                                            <?php echo $country_item['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Vendor Fields -->
                    <div id="vendorFields" style="display: <?php echo $register_as_vendor ? 'block' : 'none'; ?>;">
                        <div class="card p-3 mb-4 bg-light border-0">
                            <h5 class="mb-3"><i class="fas fa-store-alt me-2 text-primary"></i> Vendor Information</h5>

                            <!-- Vendor Category -->
                            <div class="mb-3">
                                <label for="vendor_category" class="form-label fw-bold">
                                    <i class="fas fa-tags me-1"></i> What do you want to sell? *
                                </label>
                                <select class="form-select" id="vendor_category" name="vendor_category"
                                    <?php echo $register_as_vendor ? 'required' : ''; ?>>
                                    <option value="">-- Select Category --</option>
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['slug']); ?>"
                                                <?php echo ($form_data['vendor_category'] == $cat['slug']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">Choose the category that best describes your products</div>
                            </div>

                            <!-- Vendor Bio -->
                            <div class="mb-3">
                                <label for="vendor_bio" class="form-label fw-bold">
                                    <i class="fas fa-info-circle me-1"></i> Tell us about your business
                                </label>
                                <textarea class="form-control" id="vendor_bio" name="vendor_bio"
                                    rows="3" placeholder="Brief description of your products or services..."><?php echo htmlspecialchars($form_data['vendor_bio']); ?></textarea>
                                <div class="form-text">This will be displayed on your vendor profile</div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> Vendor accounts require admin approval before you can start selling.
                                You will receive an email once your account is approved.
                            </div>
                        </div>
                    </div>

                    <!-- Password Fields -->
                    <div class="password-fields">
                        <h5 class="mb-3"><i class="fas fa-lock me-2 text-primary"></i> Security</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-1"></i> Password *
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                        required minlength="6">
                                    <span class="input-group-text password-toggle" style="cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <div class="form-text">At least 6 characters</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label fw-bold">
                                    <i class="fas fa-lock me-1"></i> Confirm Password *
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password" required>
                                    <span class="input-group-text password-toggle" style="cursor: pointer;">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Password Strength Meter -->
                        <div class="mb-3">
                            <div class="progress" style="height: 5px;">
                                <div class="progress-bar" id="passwordStrength" style="width: 0%;"></div>
                            </div>
                            <small class="text-muted" id="passwordStrengthText"></small>
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
                        <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                            <i class="fas fa-user-plus me-2"></i> Create Account
                        </button>
                    </div>

                    <!-- Already have account -->
                    <div class="text-center mt-4 auth-links">
                        <p>Already have an account? <a href="login.php" class="text-decoration-none">Login here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.auth-container {
    max-width: 650px;
    margin: 0 auto;
    padding: 30px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.account-type-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #dee2e6;
    height: 100%;
}

.account-type-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.account-type-card.border-primary.active-card {
    border-color: #4361ee !important;
    background: linear-gradient(135deg, #f0f7ff 0%, #e6f0ff 100%);
}

.account-type-card .fa-shopping-cart,
.account-type-card .fa-store {
    transition: transform 0.3s ease;
}

.account-type-card:hover .fa-shopping-cart,
.account-type-card:hover .fa-store {
    transform: scale(1.1);
}

.account-type-radio {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.password-toggle:hover {
    background-color: #e9ecef;
}

/* Animation for vendor fields */
#vendorFields {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .auth-container {
        padding: 20px;
        margin: 10px;
    }
}
</style>

<script>
// FIXED: Toggle vendor fields based on selection
function toggleVendorFields(show) {
    const vendorFields = document.getElementById('vendorFields');
    const vendorCategory = document.getElementById('vendor_category');

    if (show) {
        vendorFields.style.display = 'block';
        if (vendorCategory) vendorCategory.required = true;
    } else {
        vendorFields.style.display = 'none';
        if (vendorCategory) vendorCategory.required = false;
    }
}

// Add event listeners to radio buttons
document.addEventListener('DOMContentLoaded', function() {
    const customerRadio = document.getElementById('register_customer');
    const vendorRadio = document.getElementById('register_vendor');
    const customerCard = document.querySelector('[data-type="customer"]');
    const vendorCard = document.querySelector('[data-type="vendor"]');
    
    // Function to update active card styling
    function updateActiveCard() {
        if (vendorRadio.checked) {
            customerCard.classList.remove('border-primary', 'active-card');
            vendorCard.classList.add('border-primary', 'active-card');
            toggleVendorFields(true);
        } else {
            vendorCard.classList.remove('border-primary', 'active-card');
            customerCard.classList.add('border-primary', 'active-card');
            toggleVendorFields(false);
        }
    }
    
    // Add click handlers to cards
    customerCard.addEventListener('click', function() {
        customerRadio.checked = true;
        updateActiveCard();
    });
    
    vendorCard.addEventListener('click', function() {
        vendorRadio.checked = true;
        updateActiveCard();
    });
    
    // Add change handlers to radios
    customerRadio.addEventListener('change', updateActiveCard);
    vendorRadio.addEventListener('change', updateActiveCard);
    
    // Set initial state
    updateActiveCard();
});

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

// Password strength meter
const passwordInput = document.getElementById('password');
if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let text = '';

        if (password.length >= 6) strength += 25;
        if (password.match(/[a-z]+/)) strength += 25;
        if (password.match(/[A-Z]+/)) strength += 25;
        if (password.match(/[0-9]+/)) strength += 25;

        const bar = document.getElementById('passwordStrength');
        const textEl = document.getElementById('passwordStrengthText');

        bar.style.width = strength + '%';

        if (strength < 25) {
            bar.className = 'progress-bar bg-danger';
            text = 'Very Weak';
        } else if (strength < 50) {
            bar.className = 'progress-bar bg-warning';
            text = 'Weak';
        } else if (strength < 75) {
            bar.className = 'progress-bar bg-info';
            text = 'Good';
        } else {
            bar.className = 'progress-bar bg-success';
            text = 'Strong';
        }

        textEl.textContent = 'Password Strength: ' + text;
    });
}

// Form validation
document.getElementById('signupForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    const terms = document.getElementById('terms').checked;
    const submitBtn = document.getElementById('submitBtn');
    const isVendor = document.getElementById('register_vendor').checked;

    // Check if passwords match
    if (password !== confirm) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }

    // Check terms
    if (!terms) {
        e.preventDefault();
        alert('You must agree to the terms and conditions!');
        return false;
    }

    // If vendor, ensure category is selected
    if (isVendor) {
        const vendorCategory = document.getElementById('vendor_category');
        if (!vendorCategory.value) {
            e.preventDefault();
            alert('Please select a vendor category');
            vendorCategory.focus();
            return false;
        }
    }

    // Show loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Creating Account...';
    submitBtn.disabled = true;

    return true;
});

// Username availability check
let usernameTimeout;
document.getElementById('username').addEventListener('input', function() {
    clearTimeout(usernameTimeout);
    const username = this.value;
    const feedbackDiv = this.nextElementSibling;
    
    if (username.length >= 3) {
        feedbackDiv.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Checking...</span>';
        
        usernameTimeout = setTimeout(() => {
            fetch('check-username.php?username=' + encodeURIComponent(username))
                .then(response => response.json())
                .then(data => {
                    if (data.available) {
                        feedbackDiv.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i> Username available</span>';
                    } else {
                        feedbackDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i> Username taken</span>';
                    }
                })
                .catch(() => {
                    feedbackDiv.innerHTML = '';
                });
        }, 500);
    } else {
        feedbackDiv.innerHTML = '';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>