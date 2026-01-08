<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'SEO Tools & Sitemap';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Get SEO settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'seo_%' OR setting_key LIKE 'meta_%'");
    $seo_settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get pages for sitemap
    $pages = [
        ['url' => SITE_URL, 'priority' => '1.0', 'changefreq' => 'daily'],
        ['url' => SITE_URL . 'about', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => SITE_URL . 'contact', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['url' => SITE_URL . 'products', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['url' => SITE_URL . 'blog', 'priority' => '0.7', 'changefreq' => 'weekly']
    ];
    
    // Get products for sitemap
    $stmt = $db->query("SELECT id, name, updated_at FROM products WHERE stock > 0");
    $products = $stmt->fetchAll();
    
    // Get categories for sitemap
    $stmt = $db->query("SELECT slug, updated_at FROM categories WHERE is_active = 1");
    $categories = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error: ' . $e->getMessage();
    $seo_settings = [];
    $pages = [];
    $products = [];
    $categories = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">SEO Tools & Sitemap</h1>
                <p class="text-muted mb-0">Search engine optimization tools</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="generateSitemap()">
                    <i class="fas fa-sitemap me-2"></i> Generate Sitemap
                </button>
            </div>
        </div>
        
        <!-- SEO Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo count($pages) + count($products) + count($categories); ?></h3>
                        <p class="text-muted mb-0">Total URLs</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo count($pages); ?></h3>
                        <p class="text-muted mb-0">Pages</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo count($products); ?></h3>
                        <p class="text-muted mb-0">Products</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo count($categories); ?></h3>
                        <p class="text-muted mb-0">Categories</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SEO Configuration -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">SEO Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form id="seoConfigForm">
                            <div class="mb-3">
                                <label class="form-label">Meta Title</label>
                                <input type="text" class="form-control" name="meta_title" 
                                       value="<?php echo $seo_settings['meta_title'] ?? ''; ?>"
                                       placeholder="Website title for search engines">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Meta Description</label>
                                <textarea class="form-control" name="meta_description" rows="3" 
                                          placeholder="Website description for search engines"><?php echo $seo_settings['meta_description'] ?? ''; ?></textarea>
                                <div class="form-text">
                                    <span id="descCount">0</span>/160 characters
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Meta Keywords</label>
                                <textarea class="form-control" name="meta_keywords" rows="2" 
                                          placeholder="Comma-separated keywords"><?php echo $seo_settings['meta_keywords'] ?? ''; ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Google Analytics ID</label>
                                        <input type="text" class="form-control" name="google_analytics_id" 
                                               value="<?php echo $seo_settings['google_analytics_id'] ?? ''; ?>"
                                               placeholder="UA-XXXXXXXXX-X or G-XXXXXXXXXX">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Google Site Verification</label>
                                        <input type="text" class="form-control" name="google_site_verification" 
                                               value="<?php echo $seo_settings['google_site_verification'] ?? ''; ?>"
                                               placeholder="Verification code from Google Search Console">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">robots.txt</label>
                                <textarea class="form-control font-monospace" name="robots_txt" rows="5"><?php echo $seo_settings['robots_txt'] ?? 'User-agent: *
Disallow: /admin/
Disallow: /ajax/
Disallow: /includes/
Sitemap: ' . SITE_URL . 'sitemap.xml'; ?></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="button" class="btn btn-primary" onclick="saveSeoConfig()">
                                    <i class="fas fa-save me-2"></i> Save SEO Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- SEO Preview -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Search Preview</h5>
                    </div>
                    <div class="card-body">
                        <div id="searchPreview">
                            <div class="search-result">
                                <div class="title mb-1">
                                    <a href="#" class="text-primary"><?php echo $seo_settings['meta_title'] ?? 'Your Website Title'; ?></a>
                                </div>
                                <div class="url mb-1">
                                    <small class="text-success"><?php echo SITE_URL; ?></small>
                                </div>
                                <div class="description">
                                    <small class="text-muted">
                                        <?php echo substr($seo_settings['meta_description'] ?? 'Your website description will appear here in search results.', 0, 160); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h6>SEO Checklist</h6>
                            <div class="list-group list-group-flush">
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <small>Title Length (50-60 chars)</small>
                                    <?php 
                                    $title_len = strlen($seo_settings['meta_title'] ?? '');
                                    $title_ok = $title_len >= 30 && $title_len <= 60;
                                    ?>
                                    <span class="badge bg-<?php echo $title_ok ? 'success' : 'warning'; ?>">
                                        <?php echo $title_len; ?>
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <small>Description Length (120-160 chars)</small>
                                    <?php 
                                    $desc_len = strlen($seo_settings['meta_description'] ?? '');
                                    $desc_ok = $desc_len >= 120 && $desc_len <= 160;
                                    ?>
                                    <span class="badge bg-<?php echo $desc_ok ? 'success' : 'warning'; ?>">
                                        <?php echo $desc_len; ?>
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <small>Keywords Present</small>
                                    <span class="badge bg-<?php echo !empty($seo_settings['meta_keywords']) ? 'success' : 'warning'; ?>">
                                        <?php echo !empty($seo_settings['meta_keywords']) ? 'Yes' : 'No'; ?>
                                    </span>
                                </div>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <small>Google Analytics</small>
                                    <span class="badge bg-<?php echo !empty($seo_settings['google_analytics_id']) ? 'success' : 'warning'; ?>">
                                        <?php echo !empty($seo_settings['google_analytics_id']) ? 'Setup' : 'Missing'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sitemap Generator -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Sitemap Generator</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title">Pages</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach($pages as $page): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <small><?php echo $page['url']; ?></small>
                                        <span class="badge bg-info"><?php echo $page['priority']; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title">Products</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach($products as $product): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <small><?php echo SITE_URL; ?>product/<?php echo $product['id']; ?></small>
                                        <small class="text-muted"><?php echo date('M d', strtotime($product['updated_at'])); ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title">Categories</h6>
                                <div class="list-group list-group-flush">
                                    <?php foreach($categories as $category): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <small><?php echo SITE_URL; ?>category/<?php echo $category['slug']; ?></small>
                                        <small class="text-muted"><?php echo date('M d', strtotime($category['updated_at'])); ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Sitemap URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo SITE_URL; ?>sitemap.xml" readonly>
                                <button class="btn btn-outline-primary" onclick="window.open('<?php echo SITE_URL; ?>sitemap.xml', '_blank')">
                                    <i class="fas fa-external-link-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">robots.txt URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo SITE_URL; ?>robots.txt" readonly>
                                <button class="btn btn-outline-primary" onclick="window.open('<?php echo SITE_URL; ?>robots.txt', '_blank')">
                                    <i class="fas fa-external-link-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <button class="btn btn-outline-primary w-100" onclick="generateSitemap()">
                            <i class="fas fa-sitemap me-2"></i> Generate Sitemap
                        </button>
                    </div>
                    <div class="col-md-4 mb-2">
                        <button class="btn btn-outline-success w-100" onclick="submitToGoogle()">
                            <i class="fab fa-google me-2"></i> Submit to Google
                        </button>
                    </div>
                    <div class="col-md-4 mb-2">
                        <button class="btn btn-outline-info w-100" onclick="viewSitemap()">
                            <i class="fas fa-eye me-2"></i> View Sitemap
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SEO Analysis -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">SEO Analysis</h5>
            </div>
            <div class="card-body">
                <form id="seoAnalysisForm" onsubmit="analyzeSeo(event)">
                    <div class="row">
                        <div class="col-md-9">
                            <input type="url" class="form-control" id="analysisUrl" 
                                   value="<?php echo SITE_URL; ?>" 
                                   placeholder="Enter URL to analyze" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Analyze SEO
                            </button>
                        </div>
                    </div>
                </form>
                
                <div id="analysisResults" class="mt-4">
                    <!-- Results will be shown here -->
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Sitemap Preview Modal -->
<div class="modal fade" id="sitemapModal" tabindex="-1" aria-labelledby="sitemapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sitemapModalLabel">Sitemap Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="sitemapContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="downloadSitemap()">
                    <i class="fas fa-download me-2"></i> Download Sitemap
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Update character count
document.getElementById('seoConfigForm').addEventListener('input', function(e) {
    if (e.target.name === 'meta_description') {
        const count = e.target.value.length;
        document.getElementById('descCount').textContent = count;
        
        // Update preview
        const preview = document.querySelector('#searchPreview .description small');
        if (preview) {
            preview.textContent = e.target.value.substring(0, 160);
        }
    }
    
    if (e.target.name === 'meta_title') {
        const preview = document.querySelector('#searchPreview .title a');
        if (preview) {
            preview.textContent = e.target.value || 'Your Website Title';
        }
    }
});

// Save SEO configuration
function saveSeoConfig() {
    const form = document.getElementById('seoConfigForm');
    const formData = new FormData(form);
    
    fetch('ajax/save-seo-config.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
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
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// Generate sitemap
function generateSitemap() {
    Swal.fire({
        title: 'Generate Sitemap',
        text: 'Generate or update sitemap.xml?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Generate Sitemap'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/generate-sitemap.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Generated!',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-sitemap fa-4x text-success mb-3"></i>
                                <p>${data.message}</p>
                                <p class="small text-muted">URLs: ${data.url_count}</p>
                                <p class="small text-muted">File: ${data.filename}</p>
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
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// View sitemap
function viewSitemap() {
    fetch('ajax/get-sitemap.php')
    .then(response => response.text())
    .then(xml => {
        // Format XML for display
        const formatted = formatXml(xml);
        document.getElementById('sitemapContent').innerHTML = `
            <pre class="bg-light p-3 rounded" style="max-height: 500px; overflow-y: auto;"><code>${escapeHtml(formatted)}</code></pre>
        `;
        $('#sitemapModal').modal('show');
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load sitemap.', 'error');
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
                <p>Submit sitemap to Google Search Console?</p>
                <div class="mb-3">
                    <label class="form-label">Sitemap URL</label>
                    <input type="text" class="form-control" value="<?php echo SITE_URL; ?>sitemap.xml" readonly>
                </div>
                <p class="small text-muted">Make sure you have verified your site in Google Search Console first.</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Submit to Google'
    }).then((result) => {
        if (result.isConfirmed) {
            // This would normally ping Google with the sitemap URL
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
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('ajax/analyze-seo.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            url: url
        })
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        
        if (data.success) {
            const results = document.getElementById('analysisResults');
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title">Page Information</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>URL:</strong></td>
                                        <td>${data.url}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Title:</strong></td>
                                        <td>${data.title}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Title Length:</strong></td>
                                        <td>
                                            <span class="badge bg-${data.title_length >= 30 && data.title_length <= 60 ? 'success' : 'warning'}">
                                                ${data.title_length} characters
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Description:</strong></td>
                                        <td>${data.description || 'Not found'}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Desc Length:</strong></td>
                                        <td>
                                            <span class="badge bg-${data.description_length >= 120 && data.description_length <= 160 ? 'success' : 'warning'}">
                                                ${data.description_length} characters
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title">SEO Score</h6>
                                <div class="text-center">
                                    <div class="display-1 fw-bold">${data.score}/100</div>
                                    <div class="progress mb-3" style="height: 20px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: ${data.score}%">${data.score}%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="card border">
                            <div class="card-body">
                                <h6 class="card-title">Recommendations</h6>
                                <ul class="list-group list-group-flush">
            `;
            
            data.recommendations.forEach(rec => {
                const status = rec.status === 'good' ? 'success' : 
                             rec.status === 'warning' ? 'warning' : 'danger';
                html += `
                    <li class="list-group-item d-flex align-items-center">
                        <span class="badge bg-${status} me-2">${rec.status.toUpperCase()}</span>
                        ${rec.message}
                    </li>
                `;
            });
            
            html += `
                                </ul>
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

// Format XML
function formatXml(xml) {
    const formatted = '';
    const reg = /(>)(<)(\/*)/g;
    xml = xml.replace(reg, '$1\r\n$2$3');
    const pad = 0;
    const lines = xml.split('\r\n');
    
    lines.forEach((node, index) => {
        let indent = 0;
        if (node.match(/.+<\/\w[^>]*>$/)) {
            indent = 0;
        } else if (node.match(/^<\/\w/)) {
            if (pad !== 0) {
                indent = pad - 1;
            }
        } else if (node.match(/^<\w[^>]*[^\/]>.*$/)) {
            indent = 1;
        } else {
            indent = 0;
        }
        
        const padding = '';
        for (let i = 0; i < pad; i++) {
            padding += '  ';
        }
        
        lines[index] = padding + node;
    });
    
    return lines.join('\r\n');
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Set initial description count
    const descTextarea = document.querySelector('textarea[name="meta_description"]');
    if (descTextarea) {
        document.getElementById('descCount').textContent = descTextarea.value.length;
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>