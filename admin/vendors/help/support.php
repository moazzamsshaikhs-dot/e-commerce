<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

$vendor_id = $_SESSION['user_id'];

// Handle support ticket submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ticket'])) {
    $subject = $_POST['subject'] ?? '';
    $category = $_POST['category'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $message = $_POST['message'] ?? '';
    
    if (empty($subject) || empty($category) || empty($message)) {
        $_SESSION['error'] = 'Please fill all required fields';
    } else {
        // In a real system, you would save this to a support_tickets table
        // For now, we'll simulate saving it
        
        $ticket_data = [
            'vendor_id' => $vendor_id,
            'ticket_number' => 'TICKET-' . strtoupper(uniqid()),
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'message' => $message,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // You would save this to database here
        // For demonstration, we'll store in session
        if (!isset($_SESSION['support_tickets'])) {
            $_SESSION['support_tickets'] = [];
        }
        $_SESSION['support_tickets'][] = $ticket_data;
        
        logUserActivity($vendor_id, 'support_ticket_created', 
            "Created support ticket: {$subject}");
        
        $_SESSION['success'] = 'Support ticket submitted successfully! Ticket #: ' . $ticket_data['ticket_number'];
        
        // In real system, you would send email notification to admin
        // sendEmailToAdmin($ticket_data);
        
        redirect('support.php');
    }
}

// Get FAQ categories (simulated data)
$faq_categories = [
    'account' => 'Account & Profile',
    'products' => 'Products & Inventory',
    'orders' => 'Orders & Shipping',
    'payments' => 'Payments & Earnings',
    'technical' => 'Technical Issues'
];

$faqs = [
    'account' => [
        ['q' => 'How do I update my vendor profile?', 'a' => 'Go to Vendor Profile page and edit your information.'],
        ['q' => 'Can I change my store name?', 'a' => 'Yes, contact admin for store name changes.']
    ],
    'products' => [
        ['q' => 'How many products can I list?', 'a' => 'Depends on your subscription plan. Free: 5, Premium: 50, Business: Unlimited.'],
        ['q' => 'How long does product approval take?', 'a' => 'Usually 24-48 hours during business days.']
    ],
    'orders' => [
        ['q' => 'How do I update order status?', 'a' => 'Go to My Orders page and click on order to update status.'],
        ['q' => 'What shipping carriers are supported?', 'a' => 'All major carriers. Contact admin to add new carriers.']
    ]
];

$page_title = 'Vendor Support - Vendor Dashboard';
require_once '../../includes/header.php';
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h1 class="h3 mb-1 fw-bold text-primary">Vendor Support</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-headset me-1 text-info"></i>
                        Get help and support for your vendor account
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="knowledge-base.php" class="btn btn-outline-primary">
                        <i class="fas fa-book me-2"></i> Knowledge Base
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Support Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-primary mb-3">
                            <i class="fas fa-life-ring fa-3x"></i>
                        </div>
                        <h5 class="fw-bold">24/7 Support</h5>
                        <p class="text-muted small">Round-the-clock assistance for vendors</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-success mb-3">
                            <i class="fas fa-clock fa-3x"></i>
                        </div>
                        <h5 class="fw-bold">Quick Response</h5>
                        <p class="text-muted small">Average response time: 2-4 hours</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-warning mb-3">
                            <i class="fas fa-star fa-3x"></i>
                        </div>
                        <h5 class="fw-bold">Priority Support</h5>
                        <p class="text-muted small">Premium & Business plan vendors</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm text-center">
                    <div class="card-body">
                        <div class="text-info mb-3">
                            <i class="fas fa-comments fa-3x"></i>
                        </div>
                        <h5 class="fw-bold">Live Chat</h5>
                        <p class="text-muted small">Available 9AM-6PM (Mon-Fri)</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Support Ticket Form -->
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-ticket-alt me-2 text-primary"></i>
                            Submit Support Ticket
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Subject <span class="text-danger">*</span></label>
                                    <input type="text" name="subject" class="form-control" required 
                                           placeholder="Brief description of your issue">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <option value="account">Account & Profile</option>
                                        <option value="products">Products & Inventory</option>
                                        <option value="orders">Orders & Shipping</option>
                                        <option value="payments">Payments & Earnings</option>
                                        <option value="technical">Technical Issues</option>
                                        <option value="feature">Feature Request</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select">
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Attach File (Optional)</label>
                                    <input type="file" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                    <small class="text-muted">Max file size: 5MB</small>
                                </div>
                                
                                <div class="col-12">
                                    <label class="form-label">Detailed Message <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control" rows="6" required 
                                              placeholder="Please provide detailed information about your issue..."></textarea>
                                </div>
                                
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" required>
                                        <label class="form-check-label">
                                            I agree to share my account information with support team for troubleshooting
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <button type="submit" name="submit_ticket" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i> Submit Ticket
                                    </button>
                                    <button type="reset" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-redo me-2"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- My Tickets (if any) -->
                <?php if (!empty($_SESSION['support_tickets'])): ?>
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">My Recent Tickets</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <?php 
                            $recent_tickets = array_slice($_SESSION['support_tickets'], -3);
                            foreach(array_reverse($recent_tickets) as $ticket):
                            ?>
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($ticket['subject']); ?></h6>
                                        <small class="text-muted"><?php echo $ticket['ticket_number']; ?> • 
                                            <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge bg-<?php 
                                            echo $ticket['status'] == 'open' ? 'success' : 
                                                 ($ticket['status'] == 'pending' ? 'warning' : 'secondary');
                                        ?>">
                                            <?php echo ucfirst($ticket['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- FAQ & Resources -->
            <div class="col-lg-5">
                <!-- Quick Help -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-question-circle me-2 text-info"></i>
                            Quick Help
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="knowledge-base.php" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex">
                                    <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-book text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Knowledge Base</h6>
                                        <p class="text-muted small mb-0">Browse articles and guides</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="<?php echo SITE_URL; ?>admin/vendors/help/contact-admin.php" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex">
                                    <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-envelope text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Contact Admin</h6>
                                        <p class="text-muted small mb-0">Direct email to administrators</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="https://api.whatsapp.com/send?phone=<?php echo SITE_PHONE; ?>" target="_blank" 
                               class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex">
                                    <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fab fa-whatsapp text-success"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">WhatsApp Support</h6>
                                        <p class="text-muted small mb-0">Quick chat support</p>
                                    </div>
                                </div>
                            </a>
                            
                            <button class="list-group-item list-group-item-action border-0 px-0 py-3" 
                                    onclick="startLiveChat()">
                                <div class="d-flex">
                                    <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-comment-dots text-warning"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Live Chat</h6>
                                        <p class="text-muted small mb-0">Chat with support agent</p>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- FAQ -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-lightbulb me-2 text-warning"></i>
                            Frequently Asked Questions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            <?php 
                            $faq_count = 0;
                            foreach($faqs as $category => $category_faqs):
                                foreach($category_faqs as $faq):
                                    $faq_count++;
                            ?>
                            <div class="accordion-item border-0">
                                <h6 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#faq<?php echo $faq_count; ?>">
                                        <?php echo htmlspecialchars($faq['q']); ?>
                                    </button>
                                </h6>
                                <div id="faq<?php echo $faq_count; ?>" class="accordion-collapse collapse" 
                                     data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        <?php echo htmlspecialchars($faq['a']); ?>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="faq.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-list me-1"></i> View All FAQs
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Contact Information</h6>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0 py-2">
                                <small class="text-muted">Email</small>
                                <div class="fw-bold">support@<?php echo strtolower(SITE_NAME); ?>.com</div>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <small class="text-muted">Phone</small>
                                <div class="fw-bold">+1 (555) 123-4567</div>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <small class="text-muted">Hours</small>
                                <div class="fw-bold">Mon-Fri: 9AM-6PM EST</div>
                            </div>
                            <div class="list-group-item border-0 px-0 py-2">
                                <small class="text-muted">Emergency</small>
                                <div class="fw-bold">+1 (555) 987-6543</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
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
        <div class="message-bubble bg-primary text-white">
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
</script>

<?php require_once '../../includes/footer.php'; ?>