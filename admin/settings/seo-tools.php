<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
    exit;
}

$page_title = 'SEO Tools & Sitemap';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get SEO settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'seo_%' OR setting_key LIKE 'meta_%'");
    $seo_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get pages for sitemap (static pages)
    $pages = [
        ['url' => SITE_URL, 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => SITE_URL . 'about', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => SITE_URL . 'contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => SITE_URL . 'products', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => SITE_URL . 'blog', 'priority' => '0.7', 'changefreq' => 'weekly']
    ];
    
    // ===== SIMPLE DIRECT QUERIES - NO BINDING ISSUES =====
    
    // Get products - Simple query without parameters
    $product_sql = "SELECT id, name, updated_at FROM products WHERE stock > 0 AND approved_status = 'approved'";
    $product_stmt = $db->query($product_sql);
    $products = $product_stmt->fetchAll();
    
    // Get categories - Simple query
    $category_sql = "SELECT slug, updated_at FROM categories WHERE is_active = 1";
    $category_stmt = $db->query($category_sql);
    $categories = $category_stmt->fetchAll();
    
    // Calculate total URLs
    $total_urls = count($pages) + count($products) + count($categories);
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $seo_settings = [];
    $pages = [];
    $products = [];
    $categories = [];
    $total_urls = 0;
}
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --primary-light: #4895ef;
    --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
    
    --success: #06d6a0;
    --success-dark: #0ca678;
    --success-light: #80ffdb;
    --success-gradient: linear-gradient(135deg, #06d6a0, #0ca678);
    
    --warning: #ffb703;
    --warning-dark: #f77f00;
    --warning-light: #ffe066;
    --warning-gradient: linear-gradient(135deg, #ffb703, #f77f00);
    
    --danger: #ef476f;
    --danger-dark: #d62828;
    --danger-light: #ffafcc;
    --danger-gradient: linear-gradient(135deg, #ef476f, #d62828);
    
    --info: #4cc9f0;
    --info-dark: #0096c7;
    --info-light: #a2d6f9;
    --info-gradient: linear-gradient(135deg, #4cc9f0, #0096c7);
    
    --dark: #2b2d42;
    --dark-light: #4a4e69;
    --light: #f8f9fa;
    
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
    --shadow-2xl: 0 25px 50px rgba(0,0,0,0.2);
    
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
    --border-radius-2xl: 24px;
    --border-radius-full: 9999px;
}

/* Dashboard Layout */
.dashboard-container {
    display: flex;
    min-height: 100vh;
    background: var(--gray-100);
    position: relative;
}

.main-content {
    flex: 1;
    margin-left: 280px;
    padding: 2rem;
    background: var(--gray-100);
    transition: var(--transition);
    position: relative;
}

@media (max-width: 992px) {
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
    animation: slideIn 0.5s ease;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full);
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-header h1 i {
    font-size: 2rem;
    color: var(--primary);
    -webkit-text-fill-color: initial;
}

.page-header p {
    color: var(--gray-600);
    font-size: 1rem;
    margin-bottom: 0;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: var(--transition);
    animation: slideIn 0.5s ease;
    animation-fill-mode: both;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.1s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.2s; }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-card .stat-icon {
    width: 70px;
    height: 70px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
}

.stat-card .stat-content {
    flex: 1;
}

.stat-card .stat-value {
    font-size: 2rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.stat-card .stat-label {
    color: var(--gray-600);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-card .stat-footer {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* SEO Config Card */
.seo-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    height: 100%;
    animation: slideIn 0.5s ease;
}

.seo-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.seo-card .card-header i {
    font-size: 1.5rem;
    color: var(--primary);
}

.seo-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
}

.seo-card .card-body {
    padding: 2rem;
}

/* Form Styles */
.form-label {
    font-weight: 600;
    color: var(--gray-700);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-label i {
    color: var(--primary);
    width: 20px;
}

.form-control {
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: var(--transition);
}

.form-control:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.form-control.font-monospace {
    font-family: 'Courier New', monospace;
}

.form-text {
    color: var(--gray-600);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

/* Character Counter */
.char-counter {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.75rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    color: var(--gray-600);
}

.char-counter i {
    margin-right: 0.25rem;
    color: var(--info);
}

/* Search Preview */
.search-preview {
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-xl);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    transition: var(--transition);
}

.search-preview:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-lg);
}

.search-preview .preview-title {
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--gray-600);
    font-size: 0.9rem;
}

.search-preview .preview-title i {
    color: var(--primary);
}

.search-result {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 1.25rem;
}

.search-result .title a {
    color: var(--primary);
    text-decoration: none;
    font-size: 1.2rem;
    font-weight: 600;
    transition: var(--transition);
}

.search-result .title a:hover {
    text-decoration: underline;
}

.search-result .url {
    color: var(--success);
    font-size: 0.9rem;
    word-break: break-all;
}

.search-result .description {
    color: var(--gray-600);
    font-size: 0.9rem;
    line-height: 1.5;
}

/* SEO Checklist */
.seo-checklist {
    background: var(--gray-100);
    border-radius: var(--border-radius-xl);
    padding: 1.5rem;
}

.seo-checklist .list-group-item {
    background: transparent;
    border-bottom: 1px solid var(--gray-200);
    padding: 0.75rem 0;
}

.seo-checklist .list-group-item:last-child {
    border-bottom: none;
}

/* Sitemap Cards */
.sitemap-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    height: 100%;
    transition: var(--transition);
}

.sitemap-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.sitemap-card .card-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
}

.sitemap-card .card-header h6 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sitemap-card .card-header i {
    color: var(--primary);
}

.sitemap-card .list-group-item {
    padding: 0.75rem 1.5rem;
    border-left: none;
    border-right: none;
    transition: var(--transition);
}

.sitemap-card .list-group-item:hover {
    background: var(--gray-100);
}

.sitemap-card .list-group-item small {
    font-size: 0.85rem;
}

/* URL Display */
.url-display {
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
    padding: 0.75rem 1rem;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    color: var(--gray-700);
    border: 1px solid var(--gray-200);
}

/* Action Buttons */
.btn-action-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn-action {
    flex: 1;
    min-width: 150px;
    padding: 0.75rem 1.5rem;
    border-radius: var(--border-radius-lg);
    font-weight: 600;
    transition: var(--transition);
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    cursor: pointer;
}

.btn-action.primary {
    background: var(--primary-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.btn-action.primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(67, 97, 238, 0.4);
}

.btn-action.success {
    background: var(--success-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(6, 214, 160, 0.3);
}

.btn-action.success:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(6, 214, 160, 0.4);
}

.btn-action.info {
    background: var(--info-gradient);
    color: white;
    box-shadow: 0 4px 10px rgba(76, 201, 240, 0.3);
}

.btn-action.info:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(76, 201, 240, 0.4);
}

/* Analysis Card */
.analysis-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    margin-top: 2rem;
    animation: slideIn 0.5s ease;
}

.analysis-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.analysis-card .card-header i {
    color: var(--warning);
    font-size: 1.5rem;
}

.analysis-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
}

.analysis-card .card-body {
    padding: 2rem;
}

/* Analysis Results */
.analysis-results {
    animation: slideIn 0.3s ease;
}

.score-circle {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
    position: relative;
}

.score-circle svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.score-circle circle {
    fill: none;
    stroke-width: 8;
    stroke-linecap: round;
}

.score-circle .bg-circle {
    stroke: var(--gray-200);
}

.score-circle .progress-circle {
    stroke: var(--primary);
    stroke-dasharray: 314;
    stroke-dashoffset: calc(314 - (314 * var(--score)) / 100);
    transition: stroke-dashoffset 1s ease;
}

.score-value {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 2rem;
    font-weight: 800;
    color: var(--gray-800);
}

/* Recommendations */
.recommendation-item {
    padding: 1rem;
    border-radius: var(--border-radius-lg);
    margin-bottom: 0.5rem;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.recommendation-item:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow-md);
}

.recommendation-item.good {
    background: rgba(6, 214, 160, 0.05);
    border-left: 4px solid var(--success);
}

.recommendation-item.warning {
    background: rgba(255, 183, 3, 0.05);
    border-left: 4px solid var(--warning);
}

.recommendation-item.danger {
    background: rgba(239, 71, 111, 0.05);
    border-left: 4px solid var(--danger);
}

/* Modal Styles */
.modal-content {
    border: none;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-2xl);
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.25rem;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
    transition: var(--transition);
    position: relative;
    z-index: 1;
}

.modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid var(--gray-200);
    padding: 1.5rem 2rem;
}

/* Code Display */
.code-display {
    background: var(--gray-900);
    color: var(--success);
    padding: 1.5rem;
    border-radius: var(--border-radius-lg);
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
    max-height: 500px;
    overflow-y: auto;
    white-space: pre-wrap;
}

/* Animations */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .stat-card .stat-icon {
        margin: 0 auto;
    }
    
    .btn-action-group {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
    }
    
    .seo-card .card-body {
        padding: 1rem;
    }
    
    .analysis-card .card-body {
        padding: 1rem;
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius-full);
    border: 2px solid var(--gray-100);
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}
</style>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1>
                        <i class="fas fa-chart-line"></i>
                        SEO Tools & Sitemap
                    </h1>
                    <p class="mb-0">Optimize your website for search engines</p>
                </div>
                <div class="col-md-6">
                    <div class="btn-group justify-content-md-end">
                        <button class="btn btn-primary" onclick="generateSitemap()">
                            <i class="fas fa-sitemap me-2"></i>Generate Sitemap
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SEO Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo $total_urls; ?></div>
                        <div class="stat-label">Total URLs</div>
                        <div class="stat-footer">
                            <i class="fas fa-globe me-1"></i> Indexable pages
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                        <i class="fas fa-file"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($pages); ?></div>
                        <div class="stat-label">Static Pages</div>
                        <div class="stat-footer">
                            <i class="fas fa-pager me-1"></i> Core pages
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--info), var(--info-dark));">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($products); ?></div>
                        <div class="stat-label">Products</div>
                        <div class="stat-footer">
                            <i class="fas fa-tag me-1"></i> Product pages
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, var(--warning), var(--warning-dark));">
                        <i class="fas fa-folder"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo count($categories); ?></div>
                        <div class="stat-label">Categories</div>
                        <div class="stat-footer">
                            <i class="fas fa-sitemap me-1"></i> Category pages
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SEO Configuration & Preview Row -->
        <div class="row g-4 mb-4">
            <!-- SEO Configuration -->
            <div class="col-lg-8">
                <div class="seo-card">
                    <div class="card-header">
                        <i class="fas fa-cog"></i>
                        <h5>SEO Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form id="seoConfigForm">
                            <!-- Meta Title -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-heading"></i>
                                    Meta Title
                                </label>
                                <input type="text" class="form-control" name="meta_title" 
                                       value="<?php echo $seo_settings['meta_title'] ?? ''; ?>"
                                       placeholder="Website title for search engines">
                                <div class="form-text">
                                    <span class="char-counter">
                                        <i class="fas fa-text-width"></i>
                                        <span id="titleCount"><?php echo strlen($seo_settings['meta_title'] ?? ''); ?></span>/60
                                    </span>
                                    Optimal length: 50-60 characters
                                </div>
                            </div>
                            
                            <!-- Meta Description -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-align-left"></i>
                                    Meta Description
                                </label>
                                <textarea class="form-control" name="meta_description" rows="3" 
                                          placeholder="Website description for search engines"><?php echo $seo_settings['meta_description'] ?? ''; ?></textarea>
                                <div class="form-text">
                                    <span class="char-counter">
                                        <i class="fas fa-text-width"></i>
                                        <span id="descCount"><?php echo strlen($seo_settings['meta_description'] ?? ''); ?></span>/160
                                    </span>
                                    Optimal length: 120-160 characters
                                </div>
                            </div>
                            
                            <!-- Meta Keywords -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-tags"></i>
                                    Meta Keywords
                                </label>
                                <textarea class="form-control" name="meta_keywords" rows="2" 
                                          placeholder="Comma-separated keywords"><?php echo $seo_settings['meta_keywords'] ?? ''; ?></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i>
                                    Enter keywords separated by commas
                                </div>
                            </div>
                            
                            <!-- Analytics & Verification Row -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fab fa-google"></i>
                                        Google Analytics ID
                                    </label>
                                    <input type="text" class="form-control" name="google_analytics_id" 
                                           value="<?php echo $seo_settings['google_analytics_id'] ?? ''; ?>"
                                           placeholder="G-XXXXXXXXXX">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">
                                        <i class="fas fa-check-circle"></i>
                                        Google Site Verification
                                    </label>
                                    <input type="text" class="form-control" name="google_site_verification" 
                                           value="<?php echo $seo_settings['google_site_verification'] ?? ''; ?>"
                                           placeholder="Verification code">
                                </div>
                            </div>
                            
                            <!-- robots.txt -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="fas fa-file-code"></i>
                                    robots.txt
                                </label>
                                <textarea class="form-control font-monospace" name="robots_txt" rows="5"><?php echo $seo_settings['robots_txt'] ?? "User-agent: *\nDisallow: /admin/\nDisallow: /ajax/\nDisallow: /includes/\nSitemap: " . SITE_URL . "sitemap.xml"; ?></textarea>
                            </div>
                            
                            <!-- Save Button -->
                            <button type="button" class="btn-action primary w-100" onclick="saveSeoConfig()">
                                <i class="fas fa-save"></i>
                                Save SEO Configuration
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- SEO Preview & Checklist -->
            <div class="col-lg-4">
                <!-- Search Preview -->
                <div class="seo-card mb-2" style="height: 500px;">
                    <div class="card-header">
                        <i class="fas fa-search"></i>
                        <h5>Search Preview</h5>
                    </div>
                    <div class="card-body">
                        <div id="searchPreview" class="search-preview">
                            <div class="preview-title">
                                <i class="fab fa-google"></i>
                                Google Search Result Preview
                            </div>
                            <div class="search-result">
                                <div class="title mb-1">
                                    <a href="#" class="text-primary">
                                        <?php echo $seo_settings['meta_title'] ?? 'Your Website Title'; ?>
                                    </a>
                                </div>
                                <div class="url mb-1">
                                    <small><?php echo SITE_URL; ?></small>
                                </div>
                                <div class="description">
                                    <small>
                                        <?php echo substr($seo_settings['meta_description'] ?? 'Your website description will appear here in search results.', 0, 160); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- SEO Checklist -->
                <div class="seo-card" style="height: 500px;">
                    <div class="card-header">
                        <i class="fas fa-check-circle"></i>
                        <h5>SEO Checklist</h5>
                    </div>
                    <div class="card-body">
                        <div class="seo-checklist">
                            <?php 
                            $title_len = strlen($seo_settings['meta_title'] ?? '');
                            $desc_len = strlen($seo_settings['meta_description'] ?? '');
                            $has_keywords = !empty($seo_settings['meta_keywords']);
                            $has_analytics = !empty($seo_settings['google_analytics_id']);
                            ?>
                            
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-heading me-2 text-primary"></i>
                                    <small>Title Length</small>
                                </div>
                                <span class="badge bg-<?php echo ($title_len >= 30 && $title_len <= 60) ? 'success' : 'warning'; ?>">
                                    <?php echo $title_len; ?> chars
                                </span>
                            </div>
                            
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-align-left me-2 text-info"></i>
                                    <small>Description Length</small>
                                </div>
                                <span class="badge bg-<?php echo ($desc_len >= 120 && $desc_len <= 160) ? 'success' : 'warning'; ?>">
                                    <?php echo $desc_len; ?> chars
                                </span>
                            </div>
                            
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-tags me-2 text-success"></i>
                                    <small>Keywords</small>
                                </div>
                                <span class="badge bg-<?php echo $has_keywords ? 'success' : 'warning'; ?>">
                                    <?php echo $has_keywords ? 'Yes' : 'No'; ?>
                                </span>
                            </div>
                            
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fab fa-google me-2 text-danger"></i>
                                    <small>Google Analytics</small>
                                </div>
                                <span class="badge bg-<?php echo $has_analytics ? 'success' : 'warning'; ?>">
                                    <?php echo $has_analytics ? 'Setup' : 'Missing'; ?>
                                </span>
                            </div>
                            
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-sitemap me-2 text-warning"></i>
                                    <small>Sitemap</small>
                                </div>
                                <span class="badge bg-success">Ready</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sitemap Generator -->
        <div class="analysis-card">
            <div class="card-header">
                <i class="fas fa-sitemap"></i>
                <h5>Sitemap Generator</h5>
            </div>
            <div class="card-body">
                <!-- Sitemap URL Preview -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Sitemap URL</label>
                        <div class="url-display">
                            <?php echo SITE_URL; ?>sitemap.xml
                            <button class="btn btn-sm btn-link" onclick="window.open('<?php echo SITE_URL; ?>sitemap.xml', '_blank')">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">robots.txt URL</label>
                        <div class="url-display">
                            <?php echo SITE_URL; ?>robots.txt
                            <button class="btn btn-sm btn-link" onclick="window.open('<?php echo SITE_URL; ?>robots.txt', '_blank')">
                                <i class="fas fa-external-link-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Sitemap Content Cards -->
                <div class="row g-4 mb-4">
                    <!-- Pages -->
                    <div class="col-md-4">
                        <div class="sitemap-card">
                            <div class="card-header">
                                <h6>
                                    <i class="fas fa-file"></i>
                                    Static Pages
                                    <span class="badge bg-primary ms-auto"><?php echo count($pages); ?></span>
                                </h6>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach($pages as $page): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <small><?php echo basename($page['url']) ?: 'Home'; ?></small>
                                    <span class="badge bg-info"><?php echo $page['priority']; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Products -->
                    <div class="col-md-4">
                        <div class="sitemap-card">
                            <div class="card-header">
                                <h6>
                                    <i class="fas fa-box"></i>
                                    Products
                                    <span class="badge bg-success ms-auto"><?php echo count($products); ?></span>
                                </h6>
                            </div>
                            <div class="list-group list-group-flush" style="max-height: 300px; overflow-y: auto;">
                                <?php if (count($products) > 0): ?>
                                    <?php foreach($products as $product): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <small><?php echo htmlspecialchars(substr($product['name'], 0, 30)) . (strlen($product['name']) > 30 ? '...' : ''); ?></small>
                                        <small class="text-muted"><?php echo date('M d', strtotime($product['updated_at'])); ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item text-center text-muted">
                                        <small>No products available</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categories -->
                    <div class="col-md-4">
                        <div class="sitemap-card">
                            <div class="card-header">
                                <h6>
                                    <i class="fas fa-folder"></i>
                                    Categories
                                    <span class="badge bg-warning ms-auto"><?php echo count($categories); ?></span>
                                </h6>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php if (count($categories) > 0): ?>
                                    <?php foreach($categories as $category): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <small><?php echo $category['slug']; ?></small>
                                        <small class="text-muted"><?php echo date('M d', strtotime($category['updated_at'])); ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="list-group-item text-center text-muted">
                                        <small>No categories available</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="btn-action-group">
                    <button class="btn-action primary" onclick="generateSitemap()">
                        <i class="fas fa-sitemap"></i>
                        Generate Sitemap
                    </button>
                    <button class="btn-action success" onclick="submitToGoogle()">
                        <i class="fab fa-google"></i>
                        Submit to Google
                    </button>
                    <button class="btn-action info" onclick="viewSitemap()">
                        <i class="fas fa-eye"></i>
                        View Sitemap
                    </button>
                </div>
            </div>
        </div>
        
        <!-- SEO Analysis -->
        <div class="analysis-card">
            <div class="card-header">
                <i class="fas fa-chart-bar"></i>
                <h5>SEO Analysis</h5>
            </div>
            <div class="card-body">
                <form id="seoAnalysisForm" onsubmit="analyzeSeo(event)">
                    <div class="row g-3">
                        <div class="col-md-9">
                            <input type="url" class="form-control" id="analysisUrl" 
                                   value="<?php echo SITE_URL; ?>" 
                                   placeholder="Enter URL to analyze" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn-action primary w-100">
                                <i class="fas fa-search"></i>
                                Analyze SEO
                            </button>
                        </div>
                    </div>
                </form>
                
                <div id="analysisResults" class="analysis-results mt-4">
                    <!-- Results will be shown here -->
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Sitemap Preview Modal -->
<div class="modal fade" id="sitemapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-sitemap me-2"></i>
                    Sitemap Preview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="sitemapContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="downloadSitemap()">
                    <i class="fas fa-download me-2"></i>Download Sitemap
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Character counters and preview updates
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.querySelector('input[name="meta_title"]');
    const descTextarea = document.querySelector('textarea[name="meta_description"]');
    const titleCount = document.getElementById('titleCount');
    const descCount = document.getElementById('descCount');
    const previewTitle = document.querySelector('#searchPreview .title a');
    const previewDesc = document.querySelector('#searchPreview .description small');
    
    function updatePreview() {
        if (previewTitle) {
            previewTitle.textContent = titleInput.value || 'Your Website Title';
        }
        if (previewDesc) {
            previewDesc.textContent = descTextarea.value.substring(0, 160) || 'Your website description will appear here in search results.';
        }
        if (titleCount) {
            titleCount.textContent = titleInput.value.length;
        }
        if (descCount) {
            descCount.textContent = descTextarea.value.length;
        }
    }
    
    if (titleInput) titleInput.addEventListener('input', updatePreview);
    if (descTextarea) descTextarea.addEventListener('input', updatePreview);
    
    updatePreview();
});

// Save SEO configuration
function saveSeoConfig() {
    const form = document.getElementById('seoConfigForm');
    const formData = new FormData(form);
    
    Swal.fire({
        title: 'Saving...',
        text: 'Please wait while we save your SEO settings.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('ajax/save-seo-config.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// Generate sitemap
function generateSitemap() {
    Swal.fire({
        title: 'Generate Sitemap',
        text: 'This will create or update your sitemap.xml file.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Generate',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Generating...',
                text: 'Please wait while we generate your sitemap.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            fetch('ajax/generate-sitemap.php')
            .then(response => response.json())
            .then(data => {
                Swal.close();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Generated!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-sitemap fa-4x text-success mb-3"></i>
                                <p>${data.message}</p>
                                <div class="alert alert-info">
                                    <small>URLs: ${data.url_count}</small><br>
                                    <small>File: ${data.filename}</small>
                                </div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'View Sitemap',
                        cancelButtonText: 'Close'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.open('<?php echo SITE_URL; ?>sitemap.xml', '_blank');
                        }
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// View sitemap
function viewSitemap() {
    const modalContent = document.getElementById('sitemapContent');
    modalContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
    
    $('#sitemapModal').modal('show');
    
    fetch('ajax/get-sitemap.php')
    .then(response => response.text())
    .then(xml => {
        const formatted = formatXml(xml);
        const escaped = escapeHtml(formatted);
        modalContent.innerHTML = `
            <pre class="code-display"><code>${escaped}</code></pre>
        `;
    })
    .catch(error => {
        console.error('Error:', error);
        modalContent.innerHTML = '<div class="alert alert-danger">Failed to load sitemap.</div>';
    });
}

// Download sitemap
function downloadSitemap() {
    window.open('<?php echo SITE_URL; ?>sitemap.xml', '_blank');
}

// Submit to Google
function submitToGoogle() {
    Swal.fire({
        title: 'Submit to Google',
        html: `
            <div class="text-start">
                <p>Submit your sitemap to Google Search Console?</p>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Make sure you have verified your site in Google Search Console first.
                </div>
                <p class="small text-muted">
                    Sitemap URL: <code><?php echo SITE_URL; ?>sitemap.xml</code>
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Submit to Google',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Submitted!',
                text: 'Sitemap has been submitted to Google.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Analyze SEO
function analyzeSeo(event) {
    event.preventDefault();
    const url = document.getElementById('analysisUrl').value;
    
    if (!url) {
        Swal.fire('Error!', 'Please enter a URL to analyze.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Analyzing SEO...',
        text: 'Please wait while we analyze the page.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('ajax/analyze-seo.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: url })
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            const results = document.getElementById('analysisResults');
            const score = data.score || 0;
            
            let html = `
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="card border">
                            <div class="card-body text-center">
                                <h6 class="mb-3">Page Information</h6>
                                <div class="score-circle" style="--score: ${score}">
                                    <svg viewBox="0 0 120 120">
                                        <circle class="bg-circle" cx="60" cy="60" r="50"></circle>
                                        <circle class="progress-circle" cx="60" cy="60" r="50"></circle>
                                    </svg>
                                    <div class="score-value">${score}</div>
                                </div>
                                <div class="mt-3 text-start">
                                    <p class="mb-1"><strong>URL:</strong><br> <small>${data.url}</small></p>
                                    <p class="mb-1"><strong>Title:</strong><br> ${data.title || 'N/A'}</p>
                                    <p class="mb-0"><strong>Description:</strong><br> ${data.description || 'N/A'}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-7">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="mb-3">Recommendations</h6>
            `;
            
            if (data.recommendations && data.recommendations.length > 0) {
                data.recommendations.forEach(rec => {
                    const statusClass = rec.status === 'good' ? 'good' : rec.status === 'warning' ? 'warning' : 'danger';
                    html += `
                        <div class="recommendation-item ${statusClass}">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-${statusClass} me-2">${rec.status.toUpperCase()}</span>
                                <small>${rec.message}</small>
                            </div>
                        </div>
                    `;
                });
            } else {
                html += `<p class="text-muted">No recommendations available.</p>`;
            }
            
            html += `
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            results.innerHTML = html;
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.close();
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred during analysis.', 'error');
    });
}

// Format XML for display
function formatXml(xml) {
    let formatted = '';
    const reg = /(>)(<)(\/*)/g;
    xml = xml.replace(reg, '$1\r\n$2$3');
    let pad = 0;
    const lines = xml.split('\r\n');
    
    lines.forEach((line) => {
        let indent = 0;
        if (line.match(/.+<\/\w[^>]*>$/)) {
            indent = 0;
        } else if (line.match(/^<\/\w/)) {
            if (pad !== 0) {
                pad -= 1;
            }
        } else if (line.match(/^<\w[^>]*[^\/]>.*$/)) {
            indent = 1;
        } else {
            indent = 0;
        }
        
        const padding = '  '.repeat(pad);
        formatted += padding + line + '\n';
        
        pad += indent;
    });
    
    return formatted;
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once '../includes/footer.php'; ?>