<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

// Knowledge base articles (simulated data)
$knowledge_base = [
    'getting-started' => [
        'title' => 'Getting Started as a Vendor',
        'category' => 'Account',
        'content' => '<h4>Welcome to our Vendor Platform!</h4>
                     <p>As a new vendor, here are the first steps to get started:</p>
                     <ol>
                         <li>Complete your vendor profile with all required information</li>
                         <li>Set up your store settings and policies</li>
                         <li>Add your first product with proper images and descriptions</li>
                         <li>Wait for product approval (usually 24-48 hours)</li>
                         <li>Start promoting your products once approved</li>
                     </ol>',
        'related' => ['profile-setup', 'product-creation']
    ],
    'profile-setup' => [
        'title' => 'Setting Up Your Vendor Profile',
        'category' => 'Account',
        'content' => '<h4>Complete Your Vendor Profile</h4>
                     <p>A complete profile helps build trust with customers:</p>
                     <ul>
                         <li><strong>Profile Picture:</strong> Use a professional logo or photo</li>
                         <li><strong>Store Description:</strong> Tell your story and what makes you unique</li>
                         <li><strong>Contact Information:</strong> Ensure all details are accurate</li>
                         <li><strong>Social Media:</strong> Link your business social accounts</li>
                         <li><strong>Store Policies:</strong> Set clear shipping, return, and privacy policies</li>
                     </ul>',
        'related' => ['getting-started', 'store-settings']
    ],
    'product-creation' => [
        'title' => 'Creating and Managing Products',
        'category' => 'Products',
        'content' => '<h4>Product Creation Guidelines</h4>
                     <p>Follow these best practices for product listings:</p>
                     <h5>Product Images:</h5>
                     <ul>
                         <li>Use high-quality images (minimum 800x800px)</li>
                         <li>Show product from multiple angles</li>
                         <li>Use white background for main image</li>
                         <li>Include lifestyle images showing product in use</li>
                     </ul>
                     <h5>Product Description:</h5>
                     <ul>
                         <li>Write clear, detailed descriptions</li>
                         <li>Include specifications and dimensions</li>
                         <li>Highlight key features and benefits</li>
                         <li>Use bullet points for easy reading</li>
                     </ul>',
        'related' => ['inventory-management', 'product-approval']
    ],
    'inventory-management' => [
        'title' => 'Inventory Management Tips',
        'category' => 'Products',
        'content' => '<h4>Effective Inventory Management</h4>
                     <p>Keep your inventory organized and avoid stockouts:</p>
                     <h5>Stock Management:</h5>
                     <ul>
                         <li>Set up low stock alerts (recommended: alert at 10 units)</li>
                         <li>Regularly update stock levels after sales</li>
                         <li>Use bulk update tools for efficiency</li>
                         <li>Keep accurate records of incoming stock</li>
                     </ul>
                     <h5>Best Practices:</h5>
                     <ul>
                         <li>Maintain safety stock for popular items</li>
                         <li>Regularly review slow-moving products</li>
                         <li>Consider seasonal demand patterns</li>
                         <li>Use inventory reports to track performance</li>
                     </ul>',
        'related' => ['product-creation', 'sales-reports']
    ],
    'order-management' => [
        'title' => 'Processing Orders and Shipping',
        'category' => 'Orders',
        'content' => '<h4>Order Processing Workflow</h4>
                     <p>Efficient order processing leads to happy customers:</p>
                     <ol>
                         <li><strong>Order Received:</strong> Check new orders daily</li>
                         <li><strong>Order Processing:</strong> Prepare items for shipping within 24 hours</li>
                         <li><strong>Shipping:</strong> Choose reliable carriers and update tracking info</li>
                         <li><strong>Delivery:</strong> Monitor delivery status</li>
                         <li><strong>Follow-up:</strong> Send delivery confirmation and request reviews</li>
                     </ol>
                     <h5>Shipping Tips:</h5>
                     <ul>
                         <li>Package items securely to prevent damage</li>
                         <li>Include thank you notes or small gifts</li>
                         <li>Use branded packaging when possible</li>
                         <li>Track all shipments until delivered</li>
                     </ul>',
        'related' => ['customer-service', 'payment-settings']
    ]
];

$selected_article = $_GET['article'] ?? 'getting-started';
$search_query = $_GET['search'] ?? '';

// Filter articles if search query exists
if (!empty($search_query)) {
    $filtered_articles = array_filter($knowledge_base, function($article) use ($search_query) {
        return stripos($article['title'], $search_query) !== false || 
               stripos($article['content'], $search_query) !== false;
    });
} else {
    $filtered_articles = $knowledge_base;
}

$page_title = 'Knowledge Base - Vendor Dashboard';
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
                    <h1 class="h3 mb-1 fw-bold text-primary">Knowledge Base</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-book me-1 text-info"></i>
                        Help articles and guides for vendors
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
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" 
                                   placeholder="Search for help articles..." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i> Search
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($search_query)): ?>
                <div class="mt-3">
                    <small class="text-muted">
                        Found <?php echo count($filtered_articles); ?> article(s) for "<?php echo htmlspecialchars($search_query); ?>"
                        <a href="knowledge-base.php" class="ms-2">Clear search</a>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Categories Sidebar -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">Categories</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="?category=all" class="list-group-item list-group-item-action border-0 px-0 py-3 active">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-th-large me-3 text-primary"></i>
                                        <span class="fw-bold">All Articles</span>
                                    </div>
                                    <span class="badge bg-primary rounded-pill"><?php echo count($knowledge_base); ?></span>
                                </div>
                            </a>
                            
                            <a href="?category=account" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user me-3 text-success"></i>
                                        <span>Account & Profile</span>
                                    </div>
                                    <span class="badge bg-success rounded-pill">2</span>
                                </div>
                            </a>
                            
                            <a href="?category=products" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-box me-3 text-warning"></i>
                                        <span>Products & Inventory</span>
                                    </div>
                                    <span class="badge bg-warning rounded-pill">2</span>
                                </div>
                            </a>
                            
                            <a href="?category=orders" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-shopping-cart me-3 text-info"></i>
                                        <span>Orders & Shipping</span>
                                    </div>
                                    <span class="badge bg-info rounded-pill">1</span>
                                </div>
                            </a>
                            
                            <a href="?category=payments" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-dollar-sign me-3 text-danger"></i>
                                        <span>Payments & Earnings</span>
                                    </div>
                                    <span class="badge bg-danger rounded-pill">0</span>
                                </div>
                            </a>
                            
                            <a href="?category=marketing" class="list-group-item list-group-item-action border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-bullhorn me-3 text-secondary"></i>
                                        <span>Marketing & Promotion</span>
                                    </div>
                                    <span class="badge bg-secondary rounded-pill">0</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Quick Links</h6>
                        <div class="list-group list-group-flush">
                            <a href="faq.php" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                <i class="fas fa-question-circle me-2 text-primary"></i>
                                FAQ
                            </a>
                            <a href="video-tutorials.php" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                <i class="fas fa-play-circle me-2 text-success"></i>
                                Video Tutorials
                            </a>
                            <a href="webinars.php" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                <i class="fas fa-chalkboard-teacher me-2 text-warning"></i>
                                Webinars
                            </a>
                            <a href="vendor-guide.pdf" target="_blank" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                <i class="fas fa-file-pdf me-2 text-danger"></i>
                                Vendor Guide (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Articles Content -->
            <div class="col-lg-8">
                <!-- Article List -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Help Articles</h5>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="fas fa-sort me-1"></i> Sort By
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="?sort=recent">Most Recent</a></li>
                                <li><a class="dropdown-item" href="?sort=popular">Most Popular</a></li>
                                <li><a class="dropdown-item" href="?sort=title">Title A-Z</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($filtered_articles)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No articles found</h5>
                                <p class="text-muted">Try a different search term or browse categories</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($filtered_articles as $slug => $article): ?>
                                <a href="?article=<?php echo $slug; ?>" 
                                   class="list-group-item list-group-item-action border-0 px-0 py-3 
                                          <?php echo $selected_article == $slug ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($article['title']); ?></h6>
                                            <small class="text-muted">
                                                <span class="badge bg-secondary"><?php echo $article['category']; ?></span>
                                                • Last updated: 2 days ago
                                            </small>
                                        </div>
                                        <div>
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Selected Article -->
                <?php if (isset($knowledge_base[$selected_article])): 
                    $article = $knowledge_base[$selected_article];
                ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="knowledge-base.php">Knowledge Base</a></li>
                                <li class="breadcrumb-item"><a href="?category=<?php echo strtolower($article['category']); ?>">
                                    <?php echo $article['category']; ?>
                                </a></li>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($article['title']); ?></li>
                            </ol>
                        </nav>
                    </div>
                    <div class="card-body">
                        <div class="article-content">
                            <h2 class="mb-4"><?php echo htmlspecialchars($article['title']); ?></h2>
                            
                            <div class="article-meta mb-4">
                                <span class="badge bg-primary"><?php echo $article['category']; ?></span>
                                <span class="text-muted ms-3">
                                    <i class="far fa-clock me-1"></i> 5 min read
                                </span>
                                <span class="text-muted ms-3">
                                    <i class="far fa-eye me-1"></i> 245 views
                                </span>
                                <span class="text-muted ms-3">
                                    <i class="far fa-calendar me-1"></i> Updated: Jan 15, 2026
                                </span>
                            </div>
                            
                            <div class="article-body">
                                <?php echo $article['content']; ?>
                            </div>
                            
                            <!-- Article Actions -->
                            <div class="article-actions mt-5 pt-4 border-top">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3">Was this article helpful?</h6>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-success" onclick="rateArticle('helpful')">
                                                <i class="fas fa-thumbs-up me-2"></i> Yes
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" onclick="rateArticle('not-helpful')">
                                                <i class="fas fa-thumbs-down me-2"></i> No
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <button class="btn btn-outline-primary" onclick="printArticle()">
                                            <i class="fas fa-print me-2"></i> Print
                                        </button>
                                        <button class="btn btn-outline-secondary ms-2" onclick="shareArticle()">
                                            <i class="fas fa-share-alt me-2"></i> Share
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Related Articles -->
                            <?php if (!empty($article['related'])): ?>
                            <div class="related-articles mt-5 pt-4 border-top">
                                <h6 class="fw-bold mb-3">Related Articles</h6>
                                <div class="row">
                                    <?php foreach($article['related'] as $related_slug): 
                                        if (isset($knowledge_base[$related_slug])):
                                            $related = $knowledge_base[$related_slug];
                                    ?>
                                    <div class="col-md-6">
                                        <div class="card border mb-3">
                                            <div class="card-body">
                                                <h6><?php echo htmlspecialchars($related['title']); ?></h6>
                                                <p class="text-muted small mb-2"><?php echo $related['category']; ?></p>
                                                <a href="?article=<?php echo $related_slug; ?>" class="btn btn-sm btn-outline-primary">
                                                    Read Article <i class="fas fa-arrow-right ms-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<style>
.article-content {
    line-height: 1.8;
}
.article-content h4 {
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    color: #4361ee;
}
.article-content h5 {
    margin-top: 1.2rem;
    margin-bottom: 0.8rem;
    color: #495057;
}
.article-content ul, .article-content ol {
    padding-left: 1.5rem;
}
.article-content li {
    margin-bottom: 0.5rem;
}
.article-body {
    font-size: 1.05rem;
}
</style>

<script>
function rateArticle(rating) {
    const articleTitle = '<?php echo addslashes($article['title'] ?? ''); ?>';
    
    fetch('rate-article.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            article: articleTitle,
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

function printArticle() {
    const articleContent = document.querySelector('.article-content').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title><?php echo htmlspecialchars($article['title'] ?? 'Knowledge Base'); ?></title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h2 { color: #4361ee; }
                    .article-meta { margin-bottom: 30px; color: #666; }
                    .article-body { line-height: 1.8; }
                    .print-footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                ${articleContent}
                <div class="print-footer">
                    <p>Knowledge Base - <?php echo SITE_NAME; ?> &copy; <?php echo date('Y'); ?></p>
                    <p>Printed: ${new Date().toLocaleString()}</p>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        window.onafterprint = function() {
                            window.close();
                        };
                    };
                <\/script>
            </body>
        </html>
    `);
    
    printWindow.document.close();
}

function shareArticle() {
    const articleTitle = '<?php echo addslashes($article['title'] ?? ''); ?>';
    const articleUrl = window.location.href;
    
    if (navigator.share) {
        navigator.share({
            title: articleTitle,
            text: 'Check out this helpful article from Vendor Knowledge Base',
            url: articleUrl
        });
    } else {
        // Fallback for browsers that don't support Web Share API
        navigator.clipboard.writeText(articleUrl).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>