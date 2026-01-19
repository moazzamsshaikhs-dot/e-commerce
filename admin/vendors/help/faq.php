<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

// FAQ data (in a real system, this would come from database)
$faq_categories = [
    'account' => [
        'name' => 'Account & Profile',
        'icon' => 'fas fa-user',
        'color' => 'primary'
    ],
    'products' => [
        'name' => 'Products & Inventory',
        'icon' => 'fas fa-box',
        'color' => 'success'
    ],
    'orders' => [
        'name' => 'Orders & Shipping',
        'icon' => 'fas fa-shopping-cart',
        'color' => 'warning'
    ],
    'payments' => [
        'name' => 'Payments & Earnings',
        'icon' => 'fas fa-dollar-sign',
        'color' => 'info'
    ],
    'technical' => [
        'name' => 'Technical Issues',
        'icon' => 'fas fa-cog',
        'color' => 'danger'
    ]
];

$faqs = [
    'account' => [
        [
            'question' => 'How do I update my vendor profile information?',
            'answer' => 'Go to Vendor Profile page from your dashboard. Click the "Edit Profile" button to update your information. Remember to save changes after editing.',
            'tags' => ['profile', 'update', 'settings']
        ],
        [
            'question' => 'Can I change my store name after registration?',
            'answer' => 'Yes, but you need to contact admin for store name changes. Go to Support page and submit a ticket with your request.',
            'tags' => ['store', 'name', 'change']
        ],
        [
            'question' => 'How do I reset my password?',
            'answer' => 'Click "Forgot Password" on the login page. Enter your email address and follow the instructions sent to your email.',
            'tags' => ['password', 'security', 'login']
        ]
    ],
    'products' => [
        [
            'question' => 'How many products can I list?',
            'answer' => 'It depends on your subscription plan: Free Plan - 5 products, Premium Plan - 50 products, Business Plan - Unlimited products.',
            'tags' => ['limits', 'subscription', 'products']
        ],
        [
            'question' => 'How long does product approval take?',
            'answer' => 'Product approval usually takes 24-48 hours during business days. Our team reviews each product for quality and compliance.',
            'tags' => ['approval', 'waiting', 'time']
        ],
        [
            'question' => 'What are the image requirements for products?',
            'answer' => 'Minimum 800x800 pixels, maximum 5MB per image. Use white background for main image. JPG or PNG format only.',
            'tags' => ['images', 'requirements', 'upload']
        ]
    ],
    'orders' => [
        [
            'question' => 'How do I update order status?',
            'answer' => 'Go to My Orders page, click on the order, and use the status dropdown to update. Don\'t forget to save changes.',
            'tags' => ['orders', 'status', 'update']
        ],
        [
            'question' => 'What shipping carriers are supported?',
            'answer' => 'We support all major carriers including FedEx, UPS, DHL, and USPS. Contact admin to add new carriers.',
            'tags' => ['shipping', 'carriers', 'delivery']
        ],
        [
            'question' => 'How do I print shipping labels?',
            'answer' => 'After marking an order as "Ready to Ship", you can generate and print shipping labels from the order details page.',
            'tags' => ['labels', 'shipping', 'print']
        ]
    ],
    'payments' => [
        [
            'question' => 'When will I receive my earnings?',
            'answer' => 'Earnings are processed on the 15th of each month for the previous month\'s sales. Minimum withdrawal: $50.',
            'tags' => ['earnings', 'payment', 'schedule']
        ],
        [
            'question' => 'What payment methods are available for withdrawals?',
            'answer' => 'Bank transfer, PayPal, and Stripe. Add your payment details in the Earnings section.',
            'tags' => ['withdrawal', 'methods', 'payment']
        ],
        [
            'question' => 'How do commissions work?',
            'answer' => 'We charge a 10% commission on each sale. This covers platform fees, payment processing, and customer support.',
            'tags' => ['commission', 'fees', 'charges']
        ]
    ],
    'technical' => [
        [
            'question' => 'The page is not loading properly. What should I do?',
            'answer' => 'Try clearing your browser cache (Ctrl+F5). If problem persists, contact support with browser and error details.',
            'tags' => ['loading', 'technical', 'browser']
        ],
        [
            'question' => 'How do I report a bug or issue?',
            'answer' => 'Go to Support page and submit a ticket with "Technical Issue" category. Include steps to reproduce the issue.',
            'tags' => ['bugs', 'report', 'issues']
        ],
        [
            'question' => 'Is there a mobile app for vendors?',
            'answer' => 'Yes! Download our vendor app from App Store or Google Play. Search for "<?php echo SITE_NAME; ?> Vendor".',
            'tags' => ['mobile', 'app', 'download']
        ]
    ]
];

$selected_category = $_GET['category'] ?? 'account';
$search_query = $_GET['search'] ?? '';

$page_title = 'Frequently Asked Questions - Vendor Dashboard';
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
                    <h1 class="h3 mb-1 fw-bold text-primary">Frequently Asked Questions</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-question-circle me-1 text-info"></i>
                        Quick answers to common questions
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="support.php" class="btn btn-outline-primary">
                        <i class="fas fa-headset me-2"></i> Contact Support
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" 
                                   placeholder="Search FAQs..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <input type="hidden" name="category" value="<?php echo $selected_category; ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Search FAQs
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row">
            <!-- Categories -->
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">Categories</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach($faq_categories as $slug => $category): 
                                $is_active = $selected_category == $slug;
                                $faq_count = count($faqs[$slug] ?? []);
                            ?>
                            <a href="?category=<?php echo $slug; ?>" 
                               class="list-group-item list-group-item-action border-0 px-4 py-3 
                                      <?php echo $is_active ? 'active' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-<?php echo $category['color']; ?> bg-opacity-10 
                                                rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <i class="<?php echo $category['icon']; ?> text-<?php echo $category['color']; ?>"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold"><?php echo $category['name']; ?></div>
                                        <small class="<?php echo $is_active ? 'text-white-75' : 'text-muted'; ?>">
                                            <?php echo $faq_count; ?> questions
                                        </small>
                                    </div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Popular Tags -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Popular Tags</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <?php 
                            // Get all tags
                            $all_tags = [];
                            foreach($faqs as $category_faqs) {
                                foreach($category_faqs as $faq) {
                                    $all_tags = array_merge($all_tags, $faq['tags'] ?? []);
                                }
                            }
                            $tag_counts = array_count_values($all_tags);
                            arsort($tag_counts);
                            $top_tags = array_slice(array_keys($tag_counts), 0, 12);
                            
                            foreach($top_tags as $tag):
                            ?>
                            <a href="?search=<?php echo urlencode($tag); ?>" class="badge bg-light text-dark text-decoration-none">
                                <?php echo $tag; ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Content -->
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold">
                                <?php echo $faq_categories[$selected_category]['name'] ?? 'FAQs'; ?>
                            </h5>
                            <small class="text-muted">
                                <?php echo count($faqs[$selected_category] ?? []); ?> questions in this category
                            </small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-download me-1"></i> Export
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="exportFAQs('pdf')">PDF Document</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportFAQs('csv')">CSV File</a></li>
                                <li><a class="dropdown-item" href="#" onclick="printFAQs()">Print</a></li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($search_query)): 
                            // Filter FAQs by search query
                            $filtered_faqs = [];
                            foreach($faqs as $category_slug => $category_faqs) {
                                foreach($category_faqs as $faq) {
                                    if (stripos($faq['question'], $search_query) !== false || 
                                        stripos($faq['answer'], $search_query) !== false) {
                                        $filtered_faqs[] = [
                                            'category' => $faq_categories[$category_slug]['name'],
                                            'question' => $faq['question'],
                                            'answer' => $faq['answer']
                                        ];
                                    }
                                }
                            }
                        ?>
                        
                        <?php if (empty($filtered_faqs)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No FAQs found</h5>
                                <p class="text-muted">No FAQs found for "<?php echo htmlspecialchars($search_query); ?>"</p>
                                <a href="faq.php" class="btn btn-outline-primary">View All FAQs</a>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <h6>Search Results for "<?php echo htmlspecialchars($search_query); ?>"</h6>
                                <small class="text-muted">Found <?php echo count($filtered_faqs); ?> results</small>
                            </div>
                            
                            <div class="accordion" id="searchAccordion">
                                <?php foreach($filtered_faqs as $index => $faq): ?>
                                <div class="accordion-item border-0">
                                    <h6 class="accordion-header">
                                        <button class="accordion-button collapsed bg-light" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#searchFaq<?php echo $index; ?>">
                                            <?php echo htmlspecialchars($faq['question']); ?>
                                            <span class="badge bg-secondary ms-2"><?php echo $faq['category']; ?></span>
                                        </button>
                                    </h6>
                                    <div id="searchFaq<?php echo $index; ?>" class="accordion-collapse collapse" 
                                         data-bs-parent="#searchAccordion">
                                        <div class="accordion-body">
                                            <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php else: // No search query, show category FAQs ?>
                        
                        <?php if (empty($faqs[$selected_category])): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No FAQs in this category</h5>
                                <p class="text-muted">Check back soon for new FAQs</p>
                            </div>
                        <?php else: ?>
                            <div class="accordion" id="faqAccordion">
                                <?php foreach($faqs[$selected_category] as $index => $faq): ?>
                                <div class="accordion-item border-0 mb-2">
                                    <h6 class="accordion-header">
                                        <button class="accordion-button collapsed bg-light" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#faq<?php echo $index; ?>">
                                            <?php echo htmlspecialchars($faq['question']); ?>
                                        </button>
                                    </h6>
                                    <div id="faq<?php echo $index; ?>" class="accordion-collapse collapse" 
                                         data-bs-parent="#faqAccordion">
                                        <div class="accordion-body">
                                            <div class="faq-answer">
                                                <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                            </div>
                                            
                                            <?php if (!empty($faq['tags'])): ?>
                                            <div class="mt-3">
                                                <small class="text-muted">Tags: </small>
                                                <?php foreach($faq['tags'] as $tag): ?>
                                                <a href="?search=<?php echo urlencode($tag); ?>" 
                                                   class="badge bg-light text-dark text-decoration-none ms-1">
                                                    <?php echo $tag; ?>
                                                </a>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    Was this helpful? 
                                                    <button class="btn btn-sm btn-outline-success ms-2" 
                                                            onclick="rateFAQ('<?php echo $selected_category; ?>', <?php echo $index; ?>, 'yes')">
                                                        <i class="fas fa-thumbs-up"></i> Yes
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger ms-1" 
                                                            onclick="rateFAQ('<?php echo $selected_category; ?>', <?php echo $index; ?>, 'no')">
                                                        <i class="fas fa-thumbs-down"></i> No
                                                    </button>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- FAQ Statistics -->
                            <div class="row mt-4 pt-4 border-top">
                                <div class="col-md-4 text-center">
                                    <div class="fw-bold text-primary"><?php echo count($faqs[$selected_category]); ?></div>
                                    <small class="text-muted">Questions</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="fw-bold text-success">85%</div>
                                    <small class="text-muted">Helpful Rate</small>
                                </div>
                                <div class="col-md-4 text-center">
                                    <div class="fw-bold text-warning">24/7</div>
                                    <small class="text-muted">Updated Regularly</small>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Still Need Help? -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body text-center py-5">
                        <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-4">
                            <i class="fas fa-headset fa-2x text-primary"></i>
                        </div>
                        <h4 class="fw-bold mb-3">Still Need Help?</h4>
                        <p class="text-muted mb-4">Can't find what you're looking for? Our support team is here to help</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="support.php" class="btn btn-primary px-4">
                                <i class="fas fa-ticket-alt me-2"></i> Submit Ticket
                            </a>
                            <a href="knowledge-base.php" class="btn btn-outline-primary px-4">
                                <i class="fas fa-book me-2"></i> Knowledge Base
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.accordion-button {
    font-weight: 500;
    border-radius: 8px !important;
    padding: 15px 20px;
}
.accordion-button:not(.collapsed) {
    background-color: #e7f1ff;
    color: #4361ee;
    box-shadow: none;
}
.accordion-body {
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 0 0 8px 8px;
}
.faq-answer {
    line-height: 1.8;
}
</style>

<script>
function rateFAQ(category, index, rating) {
    const question = document.querySelector(`#faq${index} .accordion-button`).textContent;
    
    fetch('rate-faq.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            category: category,
            index: index,
            question: question,
            rating: rating
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Thank you for your feedback!');
        }
    });
}

function exportFAQs(format) {
    const category = '<?php echo $selected_category; ?>';
    const categoryName = '<?php echo $faq_categories[$selected_category]['name'] ?? "FAQs"; ?>';
    
    if (format === 'pdf') {
        alert('PDF export coming soon!');
        // In real implementation, you would make an AJAX call to generate PDF
    } else if (format === 'csv') {
        // Generate CSV content
        const faqs = <?php echo json_encode($faqs[$selected_category] ?? []); ?>;
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "Question,Answer,Tags\n";
        
        faqs.forEach(faq => {
            const row = [
                `"${faq.question}"`,
                `"${faq.answer.replace(/"/g, '""')}"`,
                `"${faq.tags.join(', ')}"`
            ];
            csvContent += row.join(',') + "\n";
        });
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `faqs-${category}-${new Date().toISOString().split('T')[0]}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

function printFAQs() {
    const printContent = document.querySelector('.col-lg-9').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>FAQs - <?php echo $faq_categories[$selected_category]['name'] ?? 'FAQs'; ?></title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h1 { color: #4361ee; }
                    .accordion-button { background: #f8f9fa; border: 1px solid #dee2e6; padding: 10px; margin-bottom: 5px; }
                    .accordion-body { padding: 15px; background: #f8f9fa; margin-bottom: 10px; }
                    .print-footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <h1>FAQs: <?php echo $faq_categories[$selected_category]['name'] ?? 'FAQs'; ?></h1>
                <p>Generated: ${new Date().toLocaleString()}</p>
                ${printContent}
                <div class="print-footer">
                    <p><?php echo SITE_NAME; ?> Vendor Support &copy; <?php echo date('Y'); ?></p>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                    };
                <\/script>
            </body>
        </html>
    `);
    
    printWindow.document.close();
}

// Auto-expand FAQ if URL has hash
document.addEventListener('DOMContentLoaded', function() {
    const urlHash = window.location.hash;
    if (urlHash) {
        const targetElement = document.querySelector(urlHash);
        if (targetElement) {
            const accordion = new bootstrap.Collapse(targetElement, {
                toggle: true
            });
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>