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
        
        // Validation
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $_SESSION['error'] = 'All required fields must be filled';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Please enter a valid email address';
        } else {
            // Insert support ticket
            $stmt = $db->prepare("
                INSERT INTO support_tickets 
                (user_id, name, email, subject, category, message, priority, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'open')
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $name,
                $email,
                $subject,
                $category,
                $message,
                $priority
            ]);
            
            $_SESSION['success'] = 'Your support ticket has been submitted successfully! We\'ll get back to you soon.';
            
            // Send email notification
            try {
                $admin_email = getSetting('admin_email') ?? 'admin@shopease.com';
                $site_name = getSetting('site_name') ?? 'ShopEase Pro';
                
                $email_subject = "New Support Ticket: $subject";
                $email_body = "
                <h2>New Support Ticket Created</h2>
                <p><strong>From:</strong> $name ($email)</p>
                <p><strong>Category:</strong> " . ucfirst($category) . "</p>
                <p><strong>Priority:</strong> " . ucfirst($priority) . "</p>
                <p><strong>Message:</strong></p>
                <div style='background:#f8f9fa; padding:15px; border-radius:5px;'>
                    $message
                </div>
                <hr>
                <p>Please respond to this ticket within 24 hours.</p>
                ";
                
                // sendEmail($admin_email, $email_subject, $email_body);
                
            } catch (Exception $e) {
                // Email sending failed, but ticket is still saved
                error_log('Email sending failed: ' . $e->getMessage());
            }
            
            // Log activity
            logUserActivity($_SESSION['user_id'], 'support_ticket_created', 'Created support ticket: ' . $subject);
            
            redirect('support.php');
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error submitting support request: ' . $e->getMessage();
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
    $_SESSION['error'] = 'Error loading support data: ' . $e->getMessage();
    $faq_categories = [];
    $user_tickets = [];
    $faqs_by_category = [];
}
?>

<!-- Support Page -->
<div class="support-page">
    <!-- Hero Section -->
    <div class="bg-primary bg-gradient py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="text-white mb-3">How can we help you?</h1>
                    <p class="text-white-50 mb-0">
                        We're here to help. Browse our FAQs or submit a support ticket.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="card shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                            <h5 class="mb-2">24/7 Support</h5>
                            <p class="text-muted small mb-0">Average response time: 2 hours</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container py-5">
        <!-- Quick Help Cards -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-question-circle fa-2x text-primary"></i>
                        </div>
                        <h5 class="mb-3">FAQs</h5>
                        <p class="text-muted mb-3">
                            Find quick answers to common questions
                        </p>
                        <a href="#faqSection" class="btn btn-outline-primary">Browse FAQs</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-ticket-alt fa-2x text-success"></i>
                        </div>
                        <h5 class="mb-3">Submit Ticket</h5>
                        <p class="text-muted mb-3">
                            Can't find what you need? Submit a ticket
                        </p>
                        <a href="#contactForm" class="btn btn-outline-success">Create Ticket</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card border-0 shadow-sm h-100 text-center">
                    <div class="card-body p-4">
                        <div class="avatar-lg bg-info bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
                             style="width: 80px; height: 80px;">
                            <i class="fas fa-phone-alt fa-2x text-info"></i>
                        </div>
                        <h5 class="mb-3">Contact Us</h5>
                        <p class="text-muted mb-3">
                            Get in touch with our support team
                        </p>
                        <a href="tel:+1234567890" class="btn btn-outline-info">
                            <i class="fas fa-phone me-2"></i> Call Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Form Section -->
        <div class="row mb-5" id="contactForm">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pb-0">
                        <h3 class="mb-0">Submit Support Ticket</h3>
                        <p class="text-muted">We'll respond within 24 hours</p>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="supportForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="name" 
                                           name="name" 
                                           value="<?php echo $_SESSION['full_name'] ?? ''; ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo $_SESSION['email'] ?? ''; ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="subject" class="form-label">Subject *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="subject" 
                                           name="subject" 
                                           placeholder="Brief description of your issue"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category *</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select a category</option>
                                        <option value="account">Account Issues</option>
                                        <option value="billing">Billing & Payments</option>
                                        <option value="orders">Orders & Shipping</option>
                                        <option value="products">Products & Services</option>
                                        <option value="technical">Technical Issues</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Priority</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low">Low</option>
                                        <option value="normal" selected>Normal</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                    <small class="text-muted">Urgent tickets are for critical issues only</small>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="message" class="form-label">Message *</label>
                                    <textarea class="form-control" 
                                              id="message" 
                                              name="message" 
                                              rows="6" 
                                              placeholder="Please describe your issue in detail..."
                                              required></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i> Submit Ticket
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary">
                                        Clear Form
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Contact Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-4">
                            <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                 style="width: 40px; height: 40px;">
                                <i class="fas fa-phone text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Phone Support</h6>
                                <p class="text-muted mb-0">
                                    <a href="tel:+1234567890" class="text-decoration-none">+1 (234) 567-890</a><br>
                                    <small>Mon-Fri, 9AM-6PM EST</small>
                                </p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-start mb-4">
                            <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                 style="width: 40px; height: 40px;">
                                <i class="fas fa-envelope text-success"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Email Support</h6>
                                <p class="text-muted mb-0">
                                    <a href="mailto:support@shopease.com" class="text-decoration-none">support@shopease.com</a><br>
                                    <small>24/7 email support</small>
                                </p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-start">
                            <div class="avatar-sm bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3"
                                 style="width: 40px; height: 40px;">
                                <i class="fas fa-comments text-info"></i>
                            </div>
                            <div>
                                <h6 class="mb-1">Live Chat</h6>
                                <p class="text-muted mb-0">
                                    <a href="#" class="text-decoration-none">Start Live Chat</a><br>
                                    <small>Available 24/7</small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Tickets -->
                <?php if(!empty($user_tickets)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Your Recent Tickets</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php foreach($user_tickets as $ticket): ?>
                            <a href="ticket-detail.php?id=<?php echo $ticket['id']; ?>" 
                               class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0"><?php echo $ticket['subject']; ?></h6>
                                    <span class="badge bg-<?php 
                                        echo $ticket['status'] == 'open' ? 'warning' : 
                                             ($ticket['status'] == 'closed' ? 'secondary' : 'info'); 
                                    ?>">
                                        <?php echo ucfirst($ticket['status']); ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-0">
                                    <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                    • <?php echo ucfirst($ticket['category']); ?>
                                </p>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- FAQ Section -->
        <div class="row" id="faqSection">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">Frequently Asked Questions</h3>
                        <p class="text-muted">Find answers to common questions</p>
                    </div>
                    <div class="card-body">
                        <?php if(empty($faqs_by_category)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No FAQs available at the moment.</p>
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="faqAccordion">
                                <?php 
                                $faq_index = 0;
                                foreach($faqs_by_category as $category => $faqs): 
                                    $category_slug = strtolower(str_replace(' ', '-', $category));
                                ?>
                                    <div class="mb-4">
                                        <h4 class="mb-3">
                                            <i class="fas fa-folder me-2 text-primary"></i>
                                            <?php echo $category; ?>
                                        </h4>
                                        
                                        <?php foreach($faqs as $faq): ?>
                                        <div class="accordion-item border-0 mb-2">
                                            <h5 class="accordion-header">
                                                <button class="accordion-button collapsed bg-light" 
                                                        type="button" 
                                                        data-bs-toggle="collapse" 
                                                        data-bs-target="#faq-<?php echo $faq['id']; ?>">
                                                    <i class="fas fa-question-circle text-primary me-3"></i>
                                                    <?php echo $faq['question']; ?>
                                                </button>
                                            </h5>
                                            <div id="faq-<?php echo $faq['id']; ?>" 
                                                 class="accordion-collapse collapse" 
                                                 data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                                    
                                                    <?php if(!empty($faq['helpful_links'])): ?>
                                                    <div class="mt-3">
                                                        <strong>Helpful Links:</strong>
                                                        <div class="mt-2">
                                                            <?php 
                                                            $links = json_decode($faq['helpful_links'], true);
                                                            if(is_array($links)):
                                                                foreach($links as $link): ?>
                                                                <a href="<?php echo $link['url']; ?>" 
                                                                   class="badge bg-info text-decoration-none me-2"
                                                                   target="_blank">
                                                                    <i class="fas fa-external-link-alt me-1"></i>
                                                                    <?php echo $link['text']; ?>
                                                                </a>
                                                            <?php endforeach; 
                                                            endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php 
                                        $faq_index++;
                                        endforeach; 
                                        ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Resources -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h4 class="mb-4">Additional Resources</h4>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-book fa-2x text-primary me-3 mt-1"></i>
                                    <div>
                                        <h5>Documentation</h5>
                                        <p class="text-muted mb-0">
                                            Detailed guides and tutorials
                                        </p>
                                        <a href="#" class="small">View Documentation →</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-video fa-2x text-success me-3 mt-1"></i>
                                    <div>
                                        <h5>Video Tutorials</h5>
                                        <p class="text-muted mb-0">
                                            Step-by-step video guides
                                        </p>
                                        <a href="#" class="small">Watch Tutorials →</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-users fa-2x text-info me-3 mt-1"></i>
                                    <div>
                                        <h5>Community Forum</h5>
                                        <p class="text-muted mb-0">
                                            Connect with other users
                                        </p>
                                        <a href="#" class="small">Join Community →</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Support Page CSS -->
<style>
.support-page .card {
    transition: transform 0.3s ease;
}

.support-page .card:hover {
    transform: translateY(-2px);
}

.accordion-button:not(.collapsed) {
    background-color: #e7f1ff;
    color: #0c63e4;
    box-shadow: inset 0 -1px 0 rgba(0,0,0,.125);
}

.accordion-button:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}
</style>

<!-- JavaScript for Support Page -->
<script>
$(document).ready(function() {
    // Form submission
    $('#supportForm').submit(function(e) {
        // Add loading state
        $('button[type="submit"]').html('<i class="fas fa-spinner fa-spin me-2"></i> Submitting...').prop('disabled', true);
        
        // Validation
        let message = $('#message').val().trim();
        if (message.length < 10) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Message Too Short',
                text: 'Please provide more details about your issue (minimum 10 characters).'
            });
            $('button[type="submit"]').html('<i class="fas fa-paper-plane me-2"></i> Submit Ticket').prop('disabled', false);
        }
    });
    
    // FAQ search
    $('#faqSearch').on('input', function() {
        let searchTerm = $(this).val().toLowerCase();
        
        $('.accordion-button').each(function() {
            let question = $(this).text().toLowerCase();
            let accordionItem = $(this).closest('.accordion-item');
            
            if (question.includes(searchTerm)) {
                accordionItem.show();
                
                // Expand if contains search term
                if (searchTerm.length > 2) {
                    let collapseId = $(this).data('bs-target');
                    $(collapseId).collapse('show');
                }
            } else {
                accordionItem.hide();
            }
        });
    });
    
    // Smooth scroll to sections
    $('a[href^="#"]').click(function(e) {
        e.preventDefault();
        let target = $(this).attr('href');
        if ($(target).length) {
            $('html, body').animate({
                scrollTop: $(target).offset().top - 80
            }, 1000);
        }
    });
    
    // Character counter for message
    $('#message').on('input', function() {
        let length = $(this).val().length;
        $('#charCount').text(length + ' characters');
        
        if (length < 10) {
            $('#charCount').addClass('text-danger').removeClass('text-success');
        } else {
            $('#charCount').addClass('text-success').removeClass('text-danger');
        }
    });
    
    // Priority indicator
    $('#priority').change(function() {
        let priority = $(this).val();
        let badgeClass = {
            'low': 'bg-secondary',
            'normal': 'bg-info',
            'high': 'bg-warning',
            'urgent': 'bg-danger'
        }[priority];
        
        $('#priorityIndicator').removeClass().addClass('badge ' + badgeClass).text(priority.toUpperCase());
    }).trigger('change');
});
</script>

<?php require_once '../../includes/footer.php'; ?>