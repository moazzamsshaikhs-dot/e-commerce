<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}
if ($_SESSION['user_type'] === 'vendor') {
    $_SESSION['error'] = 'Access denied. Please use vendor dashboard only.';
    redirect(SITE_URL . 'vendor/dashboard.php');
}

$page_title = 'Support & Help Center';
require_once '../../includes/header.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once SITE_URL . 'vendor/phpmailer/src/Exception.php';
require_once SITE_URL . 'vendor/phpmailer/src/PHPMailer.php';
require_once SITE_URL . 'vendor/phpmailer/src/SMTP.php';

// Contact information
$support_phone = '+923132842740';
$support_whatsapp = '+923132842740';
$support_email = 'shopeasepro2@gmail.com';
$support_hours = '24/7';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $subject = trim($_POST['subject']);
        $category = $_POST['category'];
        $message = trim($_POST['message']);
        $priority = $_POST['priority'];
        $phone = trim($_POST['phone'] ?? '');
        
        // Validation
        $errors = [];
        if (empty($name)) $errors[] = 'Name is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (empty($subject)) $errors[] = 'Subject is required';
        if (empty($message)) $errors[] = 'Message is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address';
        if (strlen($message) < 10) $errors[] = 'Message must be at least 10 characters';
        
        if (empty($errors)) {
            // Insert support ticket
            $stmt = $db->prepare("
                INSERT INTO support_tickets 
                (user_id, name, email, phone, subject, category, message, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $name,
                $email,
                $phone,
                $subject,
                $category,
                $message,
                $priority
            ]);
            
            $ticket_id = $db->lastInsertId();
            
            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; // Update with your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'shopeasepro2@gmail.com';
                $mail->Password   = 'your-app-password'; // Use app-specific password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('shopeasepro2@gmail.com', 'ShopEase Pro Support');
                $mail->addAddress($support_email, 'Support Team');
                $mail->addReplyTo($email, $name);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = "New Support Ticket: #$ticket_id - $subject";
                
                // Email body
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #4361ee, #3a0ca3); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: #f8f9fa; }
                        .ticket-info { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
                        .ticket-info p { margin: 8px 0; }
                        .message-box { background: #e9ecef; padding: 15px; border-radius: 8px; margin: 15px 0; }
                        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
                        .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
                        .badge-high { background: #ef476f; color: white; }
                        .badge-normal { background: #ffb703; color: white; }
                        .badge-low { background: #06d6a0; color: white; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>New Support Ticket Received</h2>
                        </div>
                        <div class='content'>
                            <h3>Ticket Details</h3>
                            <div class='ticket-info'>
                                <p><strong>Ticket ID:</strong> #$ticket_id</p>
                                <p><strong>From:</strong> $name</p>
                                <p><strong>Email:</strong> $email</p>
                                " . ($phone ? "<p><strong>Phone:</strong> $phone</p>" : "") . "
                                <p><strong>Category:</strong> " . ucfirst($category) . "</p>
                                <p><strong>Priority:</strong> <span class='badge badge-" . strtolower($priority) . "'>" . ucfirst($priority) . "</span></p>
                                <p><strong>Subject:</strong> $subject</p>
                            </div>
                            
                            <h3>Message</h3>
                            <div class='message-box'>
                                " . nl2br(htmlspecialchars($message)) . "
                            </div>
                            
                            <p style='margin-top: 20px;'>
                                <strong>Action Required:</strong> Please respond to this ticket within 24 hours.
                            </p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from ShopEase Pro Support System.</p>
                            <p>&copy; " . date('Y') . " ShopEase Pro. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $mail->AltBody = "New Support Ticket #$ticket_id\n\nFrom: $name ($email)\nCategory: $category\nPriority: $priority\nSubject: $subject\n\nMessage:\n$message";
                
                $mail->send();
                
                // Send confirmation email to user
                $mail->clearAddresses();
                $mail->addAddress($email, $name);
                $mail->Subject = "Support Ticket Received - #$ticket_id";
                $mail->Body = "
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #06d6a0, #0ca678); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: #f8f9fa; }
                        .ticket-info { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
                        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Thank You for Contacting Us!</h2>
                        </div>
                        <div class='content'>
                            <p>Dear $name,</p>
                            <p>Thank you for reaching out to ShopEase Pro Support. We have received your ticket and our support team will respond within 24 hours.</p>
                            
                            <div class='ticket-info'>
                                <h4>Ticket Details:</h4>
                                <p><strong>Ticket ID:</strong> #$ticket_id</p>
                                <p><strong>Subject:</strong> $subject</p>
                                <p><strong>Priority:</strong> " . ucfirst($priority) . "</p>
                            </div>
                            
                            <p>You can track the status of your ticket in your <a href='" . SITE_URL . "user/support/ticket-detail.php?id=$ticket_id'>Support Dashboard</a>.</p>
                            
                            <p>For immediate assistance, you can also contact us through:</p>
                            <ul>
                                <li>Phone: <strong>$support_phone</strong></li>
                                <li>WhatsApp: <strong>$support_whatsapp</strong></li>
                                <li>Email: <strong>$support_email</strong></li>
                            </ul>
                            
                            <p>Best regards,<br>ShopEase Pro Support Team</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated confirmation. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $mail->AltBody = "Thank you for contacting ShopEase Pro Support.\n\nTicket ID: #$ticket_id\nSubject: $subject\n\nOur team will respond within 24 hours.\n\nFor immediate assistance, contact us at:\nPhone: $support_phone\nWhatsApp: $support_whatsapp\nEmail: $support_email";
                
                $mail->send();
                
                $_SESSION['success'] = 'Your support ticket has been submitted successfully! A confirmation email has been sent. We\'ll get back to you soon.';
                
            } catch (Exception $e) {
                error_log("Mailer Error: " . $mail->ErrorInfo);
                $_SESSION['success'] = 'Your support ticket has been submitted successfully! We\'ll get back to you soon.';
            }
            
            // Log activity
            logUserActivity($_SESSION['user_id'], 'support_ticket_created', 'Created support ticket: ' . $subject);
            
            redirect('support.php');
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error submitting support request. Please try again.';
        error_log("Database Error: " . $e->getMessage());
    }
}

// Get FAQ categories
try {
    $db = getDB();
    
    // Get FAQ categories
    $stmt = $db->query("SELECT DISTINCT category FROM faqs WHERE is_active = 1 ORDER BY category");
    $faq_categories = $stmt->fetchAll();
    
    // Get user's support tickets
    $stmt = $db->prepare("
        SELECT * FROM support_tickets 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_tickets = $stmt->fetchAll();
    
    // Get FAQ questions for each category
    $faqs_by_category = [];
    foreach($faq_categories as $category) {
        $stmt = $db->prepare("
            SELECT * FROM faqs 
            WHERE category = ? AND is_active = 1 
            ORDER BY sort_order, id
        ");
        $stmt->execute([$category['category']]);
        $faqs_by_category[$category['category']] = $stmt->fetchAll();
    }
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading support data. Please refresh the page.';
    error_log("FAQ Error: " . $e->getMessage());
    $faq_categories = [];
    $user_tickets = [];
    $faqs_by_category = [];
}
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
    --success: #06d6a0;
    --warning: #ffb703;
    --danger: #ef476f;
    --info: #4cc9f0;
    --whatsapp: #25D366;
    --whatsapp-gradient: linear-gradient(135deg, #25D366, #128C7E);
    --phone: #4c9aff;
}

/* Contact Cards */
.contact-card {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s ease;
    border: 1px solid var(--gray-200);
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    cursor: pointer;
}

.contact-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.contact-icon {
    width: 70px;
    height: 70px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
}

.contact-card h5 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.contact-card .contact-detail {
    font-size: 0.9rem;
    color: var(--gray-600);
    margin-bottom: 1rem;
    word-break: break-all;
}

.contact-card .contact-btn {
    padding: 0.5rem 1rem;
    border-radius: 10px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.3s ease;
}

.contact-card .contact-btn i {
    font-size: 0.9rem;
}

/* WhatsApp specific */
.whatsapp-card .contact-icon {
    background: rgba(37, 211, 102, 0.1);
    color: var(--whatsapp);
}
.whatsapp-card .contact-btn {
    background: var(--whatsapp);
    color: white;
}
.whatsapp-card .contact-btn:hover {
    background: #128C7E;
    transform: scale(1.05);
}

/* Phone specific */
.phone-card .contact-icon {
    background: rgba(76, 154, 255, 0.1);
    color: var(--phone);
}
.phone-card .contact-btn {
    background: var(--phone);
    color: white;
}
.phone-card .contact-btn:hover {
    background: #3a7bc9;
    transform: scale(1.05);
}

/* Email specific */
.email-card .contact-icon {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
}
.email-card .contact-btn {
    background: var(--primary-gradient);
    color: white;
}
.email-card .contact-btn:hover {
    transform: scale(1.05);
}

/* Quick response badge */
.quick-response {
    background: linear-gradient(135deg, rgba(6,214,160,0.1), rgba(12,166,120,0.1));
    color: var(--success);
    padding: 0.25rem 0.75rem;
    border-radius: 50px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-block;
    margin-top: 0.5rem;
}
</style>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Hero Section -->
        <div class="hero-section bg-primary bg-gradient text-white p-5 rounded-4 mb-4 position-relative overflow-hidden">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">How can we help you?</h1>
                    <p class="lead mb-0">
                        We're here to help 24/7. Choose your preferred way to connect with us.
                    </p>
                    <div class="mt-3">
                        <span class="badge bg-white text-primary px-3 py-2 me-2">
                            <i class="fas fa-clock me-1"></i> 24/7 Support
                        </span>
                        <span class="badge bg-white text-primary px-3 py-2">
                            <i class="fas fa-bolt me-1"></i> Avg response: 2 hours
                        </span>
                    </div>
                </div>
                <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                    <div class="bg-white bg-opacity-25 rounded-4 p-4">
                        <i class="fas fa-headset fa-3x mb-3"></i>
                        <h5 class="text-white mb-0">Live Support</h5>
                        <p class="mb-0 text-white-50">Always here for you</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Cards - Live Options -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="contact-card whatsapp-card" onclick="openWhatsApp()">
                    <div class="contact-icon">
                        <i class="fab fa-whatsapp fa-3x"></i>
                    </div>
                    <h5>WhatsApp Support</h5>
                    <div class="contact-detail">
                        <i class="fas fa-phone-alt me-1"></i> <?php echo $support_whatsapp; ?>
                    </div>
                    <div class="contact-btn">
                        <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                    </div>
                    <div class="quick-response">
                        <i class="fas fa-bolt me-1"></i> Instant Response
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="contact-card phone-card" onclick="makePhoneCall()">
                    <div class="contact-icon">
                        <i class="fas fa-phone-alt fa-3x"></i>
                    </div>
                    <h5>Phone Support</h5>
                    <div class="contact-detail">
                        <i class="fas fa-phone me-1"></i> <?php echo $support_phone; ?>
                    </div>
                    <div class="contact-btn">
                        <i class="fas fa-phone"></i> Call Now
                    </div>
                    <div class="quick-response">
                        <i class="fas fa-clock me-1"></i> 24/7 Available
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="contact-card email-card" onclick="openEmail()">
                    <div class="contact-icon">
                        <i class="fas fa-envelope fa-3x"></i>
                    </div>
                    <h5>Email Support</h5>
                    <div class="contact-detail">
                        <i class="fas fa-envelope me-1"></i> <?php echo $support_email; ?>
                    </div>
                    <div class="contact-btn">
                        <i class="fas fa-envelope"></i> Send Email
                    </div>
                    <div class="quick-response">
                        <i class="fas fa-reply-all me-1"></i> 2-4 Hours Response
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row">
            <!-- Contact Form Section -->
            <div class="col-lg-8 mb-4" id="contactForm">
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h3 class="mb-0">
                            <i class="fas fa-paper-plane me-2 text-primary"></i>
                            Submit Support Ticket
                        </h3>
                        <p class="text-muted mb-0">Fill out the form and we'll get back to you within 24 hours</p>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST" action="" id="supportForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo $_SESSION['full_name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo $_SESSION['email'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number (Optional)</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           placeholder="+92 313 2842740">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Subject *</label>
                                    <input type="text" class="form-control" name="subject" 
                                           placeholder="Brief description of your issue" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Category *</label>
                                    <select class="form-select" name="category" required>
                                        <option value="">Select a category</option>
                                        <option value="account">Account Issues</option>
                                        <option value="billing">Billing & Payments</option>
                                        <option value="orders">Orders & Shipping</option>
                                        <option value="products">Products & Services</option>
                                        <option value="technical">Technical Issues</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Priority</label>
                                    <select class="form-select" name="priority">
                                        <option value="low">Low - General inquiry</option>
                                        <option value="normal" selected>Normal - Need help</option>
                                        <option value="high">High - Important issue</option>
                                        <option value="urgent">Urgent - Critical problem</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Message *</label>
                                    <textarea class="form-control" name="message" rows="6" 
                                              placeholder="Please describe your issue in detail..." required></textarea>
                                    <small class="text-muted" id="charCount">0 characters (minimum 10)</small>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg px-4">
                                        <i class="fas fa-paper-plane me-2"></i> Submit Ticket
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-undo me-1"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information & Tickets -->
            <div class="col-lg-4">
                <!-- Business Hours -->
                <div class="card border-0 shadow-sm rounded-4 mb-4">
                    <div class="card-body p-4">
                        <h5 class="mb-3">
                            <i class="fas fa-clock text-primary me-2"></i>
                            Support Hours
                        </h5>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Phone Support</span>
                                <span class="fw-bold">24/7</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>WhatsApp Support</span>
                                <span class="fw-bold">24/7</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Email Support</span>
                                <span class="fw-bold">24/7</span>
                            </div>
                        </div>
                        <hr>
                        <div class="text-center">
                            <i class="fas fa-headset fa-2x text-primary mb-2"></i>
                            <p class="mb-0 text-muted">Average response time: <strong class="text-primary">2 hours</strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Tickets -->
                <?php if(!empty($user_tickets)): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="mb-0">
                            <i class="fas fa-history text-primary me-2"></i>
                            Your Recent Tickets
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="list-group list-group-flush">
                            <?php foreach($user_tickets as $ticket): ?>
                            <a href="ticket-detail.php?id=<?php echo $ticket['id']; ?>" 
                               class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($ticket['subject']); ?></h6>
                                    <span class="badge bg-<?php 
                                        echo $ticket['status'] == 'open' ? 'warning' : 
                                             ($ticket['status'] == 'in-progress' ? 'info' : 
                                             ($ticket['status'] == 'resolved' ? 'success' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($ticket['status']); ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-0">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                    <span class="mx-1">•</span>
                                    <i class="fas fa-tag me-1"></i>
                                    <?php echo ucfirst($ticket['category']); ?>
                                </p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
// WhatsApp Chat
function openWhatsApp() {
    const phone = '923132842740';
    const message = encodeURIComponent('Hello! I need assistance with ShopEase Pro.');
    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
}

// Make Phone Call
function makePhoneCall() {
    const phone = '923132842740';
    if (confirm('Click OK to call our support team. Standard call rates may apply.')) {
        window.location.href = `tel:${phone}`;
    }
}

// Open Email
function openEmail() {
    const email = 'shopeasepro2@gmail.com';
    const subject = encodeURIComponent('Support Request - ShopEase Pro');
    const body = encodeURIComponent('Hello Support Team,\n\nI need assistance with:\n\n\nThank you.');
    window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
}

$(document).ready(function() {
    // Character counter for message
    $('textarea[name="message"]').on('input', function() {
        let length = $(this).val().length;
        $('#charCount').html(length + ' characters (minimum 10)');
        
        if (length < 10) {
            $('#charCount').addClass('text-danger').removeClass('text-success');
        } else {
            $('#charCount').addClass('text-success').removeClass('text-danger');
        }
    });
    
    // Form submission with loading state
    $('#supportForm').submit(function() {
        let message = $('textarea[name="message"]').val().trim();
        if (message.length < 10) {
            Swal.fire({
                icon: 'warning',
                title: 'Message Too Short',
                text: 'Please provide more details about your issue (minimum 10 characters).',
                confirmButtonColor: '#4361ee'
            });
            return false;
        }
        
        $('button[type="submit"]').html('<i class="fas fa-spinner fa-spin me-2"></i> Submitting...').prop('disabled', true);
    });
    
    // Smooth scroll
    $('a[href^="#"]').click(function(e) {
        e.preventDefault();
        let target = $(this).attr('href');
        if ($(target).length) {
            $('html, body').animate({
                scrollTop: $(target).offset().top - 100
            }, 800);
        }
    });
    
    // Auto-expand FAQ if there's a hash
    if (window.location.hash) {
        let hash = window.location.hash;
        if (hash.startsWith('#faq-')) {
            $(hash).collapse('show');
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 100
            }, 500);
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>