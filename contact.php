<?php
require_once 'includes/config.php';
require_once 'includes/auth-check.php';

$page_title = 'Contact Us';
require_once 'includes/header.php';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $user_type = $_POST['user_type'] ?? 'guest';
    
    // Basic validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $_SESSION['error'] = 'Please fill all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address';
    } else {
        // In a real system, you would:
        // 1. Save to database
        // 2. Send email to admin
        // 3. Send confirmation to user
        
        // For demo, we'll just show success message
        $_SESSION['success'] = 'Thank you for your message! We will get back to you within 24 hours.';
        
        // Clear form
        $name = $email = $subject = $message = '';
        
        // Log activity if user is logged in
        if (isset($_SESSION['user_id'])) {
            logUserActivity($_SESSION['user_id'], 'contact_form', 'Submitted contact form');
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Page Header -->
            <div class="text-center mb-5">
                <h1 class="display-5 fw-bold text-primary mb-3">Contact Us</h1>
                <p class="lead text-muted">
                    Have questions? We're here to help. Get in touch with our support team.
                </p>
            </div>
            
            <!-- Contact Information -->
            <div class="row mb-5">
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 text-center">
                        <div class="card-body p-4">
                            <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Our Office</h5>
                            <p class="text-muted mb-0">
                                123 Business Street<br>
                                Suite 100<br>
                                Karachi, Pakistan
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 text-center">
                        <div class="card-body p-4">
                            <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                <i class="fas fa-phone fa-2x text-success"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Call Us</h5>
                            <p class="text-muted mb-2">+92 312 123 4567</p>
                            <small class="text-muted">Mon-Fri, 9AM-6PM</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 text-center">
                        <div class="card-body p-4">
                            <div class="avatar-lg bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                <i class="fas fa-envelope fa-2x text-warning"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Email Us</h5>
                            <p class="text-muted mb-0">
                                support@<?php echo strtolower(SITE_NAME); ?>.com<br>
                                sales@<?php echo strtolower(SITE_NAME); ?>.com
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contact Form -->
            <div class="card border-0 shadow-lg">
                <div class="card-body p-5">
                    <div class="row">
                        <div class="col-lg-8">
                            <h3 class="fw-bold mb-4">Send us a Message</h3>
                            
                            <form method="POST">
                                <input type="hidden" name="user_type" value="<?php echo $_SESSION['user_type'] ?? 'guest'; ?>">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control" required 
                                               value="<?php echo htmlspecialchars($name ?? ($_SESSION['full_name'] ?? '')); ?>">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" name="email" class="form-control" required 
                                               value="<?php echo htmlspecialchars($email ?? ($_SESSION['email'] ?? '')); ?>">
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                                        <input type="text" name="subject" class="form-control" required 
                                               value="<?php echo htmlspecialchars($subject ?? ''); ?>"
                                               placeholder="What is this regarding?">
                                    </div>
                                    
                                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor'): ?>
                                    <div class="col-12">
                                        <label class="form-label">Department</label>
                                        <select name="department" class="form-select">
                                            <option value="general">General Inquiry</option>
                                            <option value="vendor_support" selected>Vendor Support</option>
                                            <option value="technical">Technical Support</option>
                                            <option value="billing">Billing & Payments</option>
                                            <option value="account">Account Issues</option>
                                            <option value="feature_request">Feature Request</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Your Message <span class="text-danger">*</span></label>
                                        <textarea name="message" class="form-control" rows="6" required 
                                                  placeholder="Please provide detailed information about your inquiry..."><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                                    </div>
                                    
                                    <?php if (!isset($_SESSION['user_id'])): ?>
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" required>
                                            <label class="form-check-label">
                                                I'm not a robot
                                            </label>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-12">
                                        <button type="submit" name="submit_contact" class="btn btn-primary btn-lg px-5">
                                            <i class="fas fa-paper-plane me-2"></i> Send Message
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary ms-2">
                                            <i class="fas fa-redo me-2"></i> Clear Form
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Sidebar Info -->
                        <div class="col-lg-4">
                            <div class="bg-light rounded p-4 mt-4 mt-lg-0">
                                <h6 class="fw-bold mb-3">
                                    <i class="fas fa-info-circle me-2 text-primary"></i>
                                    Contact Information
                                </h6>
                                
                                <div class="mb-4">
                                    <small class="text-muted d-block">Response Time</small>
                                    <div class="fw-bold">Within 24 Hours</div>
                                    <small class="text-muted">For urgent matters, use phone support</small>
                                </div>
                                
                                <div class="mb-4">
                                    <small class="text-muted d-block">Business Hours</small>
                                    <div class="fw-bold">Monday - Friday</div>
                                    <small class="text-muted">9:00 AM - 6:00 PM (PKT)</small>
                                </div>
                                
                                <div class="mb-4">
                                    <small class="text-muted d-block">Emergency Support</small>
                                    <div class="fw-bold">+92 312 987 6543</div>
                                    <small class="text-muted">24/7 for critical issues</small>
                                </div>
                                
                                <hr>
                                
                                <h6 class="fw-bold mb-3">Quick Links</h6>
                                <div class="list-group list-group-flush">
                                    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'vendor'): ?>
                                    <a href="<?php echo SITE_URL; ?>admin/vendors/help/support.php" 
                                       class="list-group-item list-group-item-action border-0 px-0 py-2">
                                        <i class="fas fa-headset me-2 text-primary"></i>
                                        Vendor Support Center
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="faq.php" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                        <i class="fas fa-question-circle me-2 text-success"></i>
                                        Frequently Asked Questions
                                    </a>
                                    
                                    <a href="terms.php" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                        <i class="fas fa-file-contract me-2 text-warning"></i>
                                        Terms & Conditions
                                    </a>
                                    
                                    <a href="privacy.php" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                        <i class="fas fa-shield-alt me-2 text-info"></i>
                                        Privacy Policy
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Contact Options -->
            <div class="row mt-5">
                <div class="col-md-6 mb-4">
                    <div class="card border h-100">
                        <div class="card-body text-center p-4">
                            <div class="text-success mb-3">
                                <i class="fab fa-whatsapp fa-3x"></i>
                            </div>
                            <h5 class="fw-bold mb-2">WhatsApp Support</h5>
                            <p class="text-muted mb-3">Get quick answers via WhatsApp</p>
                            <a href="https://wa.me/923121234567" target="_blank" class="btn btn-success">
                                <i class="fab fa-whatsapp me-2"></i> Chat on WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card border h-100">
                        <div class="card-body text-center p-4">
                            <div class="text-primary mb-3">
                                <i class="fas fa-comments fa-3x"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Live Chat</h5>
                            <p class="text-muted mb-3">Chat with our support team in real-time</p>
                            <button class="btn btn-primary" onclick="startLiveChat()">
                                <i class="fas fa-comment-dots me-2"></i> Start Live Chat
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Live Chat Modal -->
<div class="modal fade" id="liveChatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-comment-dots me-2"></i> Live Chat Support
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="chatMessages" style="height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                    <div class="chat-message support">
                        <div class="message-bubble">
                            <strong>Support Agent:</strong> Hello! How can I help you today?
                        </div>
                        <small class="text-muted">Just now</small>
                    </div>
                </div>
                
                <div class="input-group">
                    <input type="text" id="chatInput" class="form-control" placeholder="Type your message...">
                    <button class="btn btn-primary" onclick="sendChatMessage()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Average response time: 2-3 minutes
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.chat-message {
    margin-bottom: 15px;
}
.message-bubble {
    background: #f1f3f4;
    padding: 10px 15px;
    border-radius: 15px;
    max-width: 80%;
    display: inline-block;
}
.chat-message.support .message-bubble {
    background: #e3f2fd;
}
.chat-message.user .message-bubble {
    background: #4361ee;
    color: white;
}
</style>

<script>
function startLiveChat() {
    const modal = new bootstrap.Modal(document.getElementById('liveChatModal'));
    modal.show();
    
    // Auto-focus on input
    setTimeout(() => {
        document.getElementById('chatInput').focus();
    }, 500);
}

function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // Add user message
    const chatContainer = document.getElementById('chatMessages');
    const userMessage = document.createElement('div');
    userMessage.className = 'chat-message user text-end';
    userMessage.innerHTML = `
        <div class="message-bubble">
            <strong>You:</strong> ${message}
        </div>
        <small class="text-muted">Just now</small>
    `;
    chatContainer.appendChild(userMessage);
    
    // Clear input
    input.value = '';
    
    // Auto-scroll to bottom
    chatContainer.scrollTop = chatContainer.scrollHeight;
    
    // Simulate auto-reply after 2 seconds
    setTimeout(() => {
        const replies = [
            "Thank you for your message. How can I assist you further?",
            "I understand your concern. Let me check that for you.",
            "Could you provide more details about this issue?",
            "I'll need to escalate this to our technical team.",
            "Is there anything else I can help you with?"
        ];
        
        const randomReply = replies[Math.floor(Math.random() * replies.length)];
        
        const replyMessage = document.createElement('div');
        replyMessage.className = 'chat-message support';
        replyMessage.innerHTML = `
            <div class="message-bubble">
                <strong>Support Agent:</strong> ${randomReply}
            </div>
            <small class="text-muted">Just now</small>
        `;
        chatContainer.appendChild(replyMessage);
        
        // Auto-scroll to bottom
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }, 2000);
}

// Allow sending message with Enter key
document.getElementById('chatInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendChatMessage();
    }
});

// Auto-fill form for logged in users
document.addEventListener('DOMContentLoaded', function() {
    const nameField = document.querySelector('input[name="name"]');
    const emailField = document.querySelector('input[name="email"]');
    
    if (nameField && !nameField.value && '<?php echo $_SESSION['full_name'] ?? ''; ?>') {
        nameField.value = '<?php echo $_SESSION['full_name']; ?>';
    }
    
    if (emailField && !emailField.value && '<?php echo $_SESSION['email'] ?? ''; ?>') {
        emailField.value = '<?php echo $_SESSION['email']; ?>';
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>