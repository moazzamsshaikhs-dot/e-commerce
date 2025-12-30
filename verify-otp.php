<?php
require_once 'includes/config.php';

// Check if user is coming from signup/login
if (!isset($_SESSION['temp_user_id'])) {
    $_SESSION['error'] = 'Invalid access to OTP verification';
    redirect('signup.php');
}

$errors = [];
$resend_cooldown = 60; // 60 seconds cooldown for resend
$last_resend = isset($_SESSION['last_otp_resend']) ? $_SESSION['last_otp_resend'] : 0;
$can_resend = (time() - $last_resend) > $resend_cooldown;

// Process OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : 'verify';
    
    if ($action === 'verify') {
        $otp = sanitize($_POST['otp']);
        
        if (empty($otp)) {
            $errors[] = 'OTP is required';
        } elseif (strlen($otp) != 6) {
            $errors[] = 'OTP must be 6 digits';
        } else {
            try {
                $db = getDB();
                $user_id = $_SESSION['temp_user_id'];
                
                // Check OTP
                $stmt = $db->prepare("
                    SELECT otp_code, otp_expiry 
                    FROM users 
                    WHERE id = ? AND otp_code = ?
                ");
                $stmt->execute([$user_id, $otp]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Check if OTP is expired
                    if (strtotime($user['otp_expiry']) < time()) {
                        $errors[] = 'OTP has expired. Please request a new one.';
                    } else {
                        // Verify user
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET email_verified = 1, otp_code = NULL, otp_expiry = NULL 
                            WHERE id = ?
                        ");
                        $stmt->execute([$user_id]);
                        
                        // Update OTP record
                        $stmt = $db->prepare("
                            UPDATE otp_verification 
                            SET verified = 1 
                            WHERE user_id = ? AND otp_code = ?
                        ");
                        $stmt->execute([$user_id, $otp]);
                        
                        // Get user details for auto login
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        // Auto login user
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['user_type'] = $user['user_type'];
                        $_SESSION['login_time'] = time();
                        
                        // Clear temp session
                        unset($_SESSION['temp_user_id']);
                        unset($_SESSION['temp_email']);
                        unset($_SESSION['temp_phone']);
                        
                        $_SESSION['success'] = 'Email verified successfully! Welcome to ShopEase Pro.';
                        
                        // Redirect to dashboard
                        if ($user['user_type'] === 'admin') {
                            redirect('admin/dashboard.php');
                        } else {
                            redirect('user/dashboard.php');
                        }
                    }
                } else {
                    $errors[] = 'Invalid OTP code';
                }
                
            } catch(PDOException $e) {
                $errors[] = 'Verification error: ' . $e->getMessage();
            }
        }
    } 
    elseif ($action === 'resend') {
        // Resend OTP
        if (!$can_resend) {
            $wait_time = $resend_cooldown - (time() - $last_resend);
            $errors[] = "Please wait $wait_time seconds before requesting new OTP";
        } else {
            try {
                $db = getDB();
                $user_id = $_SESSION['temp_user_id'];
                $email = $_SESSION['temp_email'];
                
                // Generate new OTP
                $new_otp = generateOTP();
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
                
                // Update OTP in database
                $stmt = $db->prepare("
                    UPDATE users 
                    SET otp_code = ?, otp_expiry = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$new_otp, $otp_expiry, $user_id]);
                
                // Insert new OTP record
                $stmt = $db->prepare("
                    INSERT INTO otp_verification (user_id, otp_code, otp_type, expires_at) 
                    VALUES (?, ?, 'email', ?)
                ");
                $stmt->execute([$user_id, $new_otp, $otp_expiry]);
                
                // Send OTP
                sendOTPEmail($email, $new_otp);
                
                // Update last resend time
                $_SESSION['last_otp_resend'] = time();
                $can_resend = false;
                
                $_SESSION['success'] = 'New OTP has been sent to your email!';
                redirect('verify-otp.php');
                
            } catch(PDOException $e) {
                $errors[] = 'Error resending OTP: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'OTP Verification';
require_once 'includes/header.php';
?>

<div class="container">
    <div class="auth-container">
        <div class="text-center mb-4">
            <div class="mb-3">
                <i class="fas fa-shield-alt fa-3x text-primary"></i>
            </div>
            <h2 class="fw-bold">Verify Your Email</h2>
            <p class="text-muted">
                Enter the 6-digit OTP sent to 
                <strong><?php echo $_SESSION['temp_email'] ?? ''; ?></strong>
            </p>
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
        
        <form method="POST" action="" id="otpForm">
            <!-- OTP Input -->
            <div class="mb-4">
                <label for="otp" class="form-label">6-Digit OTP Code *</label>
                <div class="d-flex justify-content-center gap-2">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <input type="text" class="form-control text-center otp-input" 
                               maxlength="1" style="width: 50px; height: 60px; font-size: 24px;"
                               onkeyup="moveToNext(this, <?php echo $i; ?>)" 
                               onkeypress="return isNumber(event)">
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="otp" id="otp">
                <div class="form-text text-center mt-2">
                    Enter the code sent to your email/phone
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="d-grid gap-2 mb-3">
                <button type="submit" name="action" value="verify" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle me-2"></i> Verify OTP
                </button>
            </div>
            
            <!-- Resend OTP -->
            <div class="text-center">
                <p class="text-muted">
                    Didn't receive the code? 
                    <?php if ($can_resend): ?>
                        <button type="submit" name="action" value="resend" 
                                class="btn btn-link p-0 text-decoration-none">
                            Resend OTP
                        </button>
                    <?php else: ?>
                        <?php
                            $wait_time = $resend_cooldown - (time() - $last_resend);
                            echo "Resend available in $wait_time seconds";
                        ?>
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Back to signup -->
            <div class="text-center mt-4">
                <a href="signup.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Sign Up
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    // OTP input handling
    function moveToNext(current, position) {
        if (current.value.length === 1) {
            if (position < 6) {
                const nextInput = current.parentElement.querySelectorAll('.otp-input')[position];
                nextInput.focus();
            }
            updateOTP();
        } else if (current.value.length === 0 && position > 1) {
            const prevInput = current.parentElement.querySelectorAll('.otp-input')[position - 2];
            prevInput.focus();
        }
        updateOTP();
    }
    
    function isNumber(evt) {
        const charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            return false;
        }
        return true;
    }
    
    function updateOTP() {
        const otpInputs = document.querySelectorAll('.otp-input');
        let otp = '';
        otpInputs.forEach(input => {
            otp += input.value;
        });
        document.getElementById('otp').value = otp;
    }
    
    // Auto-focus first OTP input
    document.addEventListener('DOMContentLoaded', function() {
        const firstInput = document.querySelector('.otp-input');
        if (firstInput) {
            firstInput.focus();
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>