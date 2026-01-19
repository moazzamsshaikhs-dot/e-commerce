<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Vendor Guide - Vendor Dashboard';
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
                    <h1 class="h3 mb-1 fw-bold text-primary">Vendor Guide & Resources</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-file-pdf me-1 text-danger"></i>
                        Comprehensive guides and documentation
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="knowledge-base.php" class="btn btn-outline-primary">
                        <i class="fas fa-book me-2"></i> Knowledge Base
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Guide -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-book me-2 text-primary"></i>
                    Complete Vendor Guide
                </h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center mb-4">
                    <div class="col-md-8">
                        <h4 class="fw-bold mb-2">The Ultimate Vendor Success Guide</h4>
                        <p class="text-muted mb-3">
                            Everything you need to know to succeed as a vendor on our platform. 
                            From setting up your store to advanced marketing strategies.
                        </p>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>120+ pages</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Updated January 2026</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>30+ illustrations</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    <small>Print-ready PDF</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="guide-cover bg-light rounded p-4">
                            <i class="fas fa-book-open fa-4x text-primary mb-3"></i>
                            <h5 class="fw-bold">Vendor Guide</h5>
                            <p class="text-muted small">Version 3.0</p>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <button class="btn btn-primary" onclick="downloadGuide('complete')">
                        <i class="fas fa-download me-2"></i> Download Complete Guide (PDF)
                    </button>
                    <button class="btn btn-outline-primary" onclick="printGuide()">
                        <i class="fas fa-print me-2"></i> Print Guide
                    </button>
                    <button class="btn btn-outline-secondary" onclick="shareGuide()">
                        <i class="fas fa-share-alt me-2"></i> Share
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Chapter Overview -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-list-ol me-2 text-primary"></i>
                            Table of Contents
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <a href="#chapter1" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                1. Getting Started
                            </a>
                            <a href="#chapter2" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                2. Store Setup & Profile
                            </a>
                            <a href="#chapter3" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                3. Product Management
                            </a>
                            <a href="#chapter4" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                4. Order Processing
                            </a>
                            <a href="#chapter5" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                5. Shipping & Delivery
                            </a>
                            <a href="#chapter6" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                6. Customer Service
                            </a>
                            <a href="#chapter7" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                7. Marketing & Promotion
                            </a>
                            <a href="#chapter8" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                8. Analytics & Reports
                            </a>
                            <a href="#chapter9" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                9. Advanced Tips
                            </a>
                            <a href="#chapter10" class="list-group-item list-group-item-action border-0 px-0 py-2">
                                10. Policies & Compliance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-star me-2 text-warning"></i>
                            Quick Start Chapters
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Chapter 1 -->
                        <div class="chapter-card mb-4" id="chapter1">
                            <h6 class="fw-bold mb-3">
                                <span class="badge bg-primary me-2">1</span>
                                Getting Started
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold">Account Creation</h6>
                                            <p class="text-muted small mb-3">Setting up your vendor account step by step</p>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadChapter(1)">
                                                <i class="fas fa-download me-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold">Dashboard Tour</h6>
                                            <p class="text-muted small mb-3">Understanding your vendor dashboard layout</p>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadChapter(2)">
                                                <i class="fas fa-download me-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chapter 2 -->
                        <div class="chapter-card mb-4" id="chapter2">
                            <h6 class="fw-bold mb-3">
                                <span class="badge bg-success me-2">2</span>
                                Store Setup & Profile
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold">Profile Optimization</h6>
                                            <p class="text-muted small mb-3">Creating a compelling vendor profile</p>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadChapter(3)">
                                                <i class="fas fa-download me-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold">Store Policies</h6>
                                            <p class="text-muted small mb-3">Setting up shipping, return, and privacy policies</p>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadChapter(4)">
                                                <i class="fas fa-download me-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Chapter 3 -->
                        <div class="chapter-card" id="chapter3">
                            <h6 class="fw-bold mb-3">
                                <span class="badge bg-warning me-2">3</span>
                                Product Management
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold">Product Listing Guide</h6>
                                            <p class="text-muted small mb-3">Creating effective product listings that sell</p>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadChapter(5)">
                                                <i class="fas fa-download me-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border h-100">
                                        <div class="card-body">
                                            <h6 class="fw-bold">Image Guidelines</h6>
                                            <p class="text-muted small mb-3">Professional product photography tips</p>
                                            <button class="btn btn-sm btn-outline-primary" onclick="downloadChapter(6)">
                                                <i class="fas fa-download me-1"></i> Download
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Resources -->
        <div class="row g-4">
            <!-- Templates -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-file-alt me-2 text-success"></i>
                            Templates & Checklists
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-file-excel text-success"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Product Upload Template</h6>
                                            <small class="text-muted">Excel template for bulk product uploads</small>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-success" onclick="downloadTemplate('product-upload')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-file-word text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Store Policy Template</h6>
                                            <small class="text-muted">Editable policy templates for your store</small>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary" onclick="downloadTemplate('policy-template')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="list-group-item border-0 px-0 py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-clipboard-check text-warning"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-1">Product Launch Checklist</h6>
                                            <small class="text-muted">Step-by-step checklist for new products</small>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-warning" onclick="downloadTemplate('launch-checklist')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Reference -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-bookmark me-2 text-danger"></i>
                            Quick Reference Guides
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <div class="text-primary mb-2">
                                            <i class="fas fa-shipping-fast fa-2x"></i>
                                        </div>
                                        <h6 class="fw-bold">Shipping Guide</h6>
                                        <small class="text-muted d-block mb-2">Carriers, rates, packaging</small>
                                        <button class="btn btn-sm btn-outline-primary" onclick="downloadQuickGuide('shipping')">
                                            Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <div class="text-success mb-2">
                                            <i class="fas fa-chart-line fa-2x"></i>
                                        </div>
                                        <h6 class="fw-bold">SEO Checklist</h6>
                                        <small class="text-muted d-block mb-2">Product SEO optimization</small>
                                        <button class="btn btn-sm btn-outline-success" onclick="downloadQuickGuide('seo')">
                                            Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <div class="text-warning mb-2">
                                            <i class="fas fa-camera fa-2x"></i>
                                        </div>
                                        <h6 class="fw-bold">Photo Guide</h6>
                                        <small class="text-muted d-block mb-2">Product photography tips</small>
                                        <button class="btn btn-sm btn-outline-warning" onclick="downloadQuickGuide('photo')">
                                            Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="card border h-100">
                                    <div class="card-body text-center">
                                        <div class="text-info mb-2">
                                            <i class="fas fa-file-invoice-dollar fa-2x"></i>
                                        </div>
                                        <h6 class="fw-bold">Tax Guide</h6>
                                        <small class="text-muted d-block mb-2">Sales tax compliance</small>
                                        <button class="btn btn-sm btn-outline-info" onclick="downloadQuickGuide('tax')">
                                            Download
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Print Preview Modal -->
<div class="modal fade" id="printPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Print Preview - Vendor Guide</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <iframe id="printPreviewFrame" style="width: 100%; height: 500px; border: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="printPreviewContent()">
                    <i class="fas fa-print me-2"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.chapter-card {
    border-bottom: 1px solid #eee;
    padding-bottom: 20px;
}
.chapter-card:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.guide-cover {
    border: 2px solid #e9ecef;
    transition: transform 0.3s ease;
}
.guide-cover:hover {
    transform: scale(1.05);
    border-color: #4361ee;
}
</style>

<script>
function downloadGuide(type) {
    let fileName = 'vendor-guide-complete.pdf';
    let guideType = 'Complete Vendor Guide';
    
    if (type === 'quick') {
        fileName = 'vendor-quick-start.pdf';
        guideType = 'Quick Start Guide';
    }
    
    // Create a fake download link
    const link = document.createElement('a');
    link.href = '#'; // In real system, this would be actual PDF URL
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message
    alert(`${guideType} download started. In a real system, this would download the PDF.`);
    
    // Track download (in real system, this would be an AJAX call)
    logGuideDownload(type);
}

function downloadChapter(chapterNumber) {
    const chapterNames = [
        '', // 0-index
        'Getting Started - Account Creation',
        'Getting Started - Dashboard Tour',
        'Store Setup - Profile Optimization',
        'Store Setup - Store Policies',
        'Product Management - Listing Guide',
        'Product Management - Image Guidelines'
    ];
    
    const chapterName = chapterNames[chapterNumber] || `Chapter ${chapterNumber}`;
    alert(`Downloading: ${chapterName}\n\nIn a real system, this would download the chapter PDF.`);
    
    // In real system, this would initiate actual download
    // window.location.href = `download-chapter.php?chapter=${chapterNumber}`;
}

function downloadTemplate(templateType) {
    const templateNames = {
        'product-upload': 'Product Upload Template',
        'policy-template': 'Store Policy Template',
        'launch-checklist': 'Product Launch Checklist'
    };
    
    const fileName = templateNames[templateType] || 'Template';
    alert(`Downloading: ${fileName}\n\nIn a real system, this would download the template file.`);
}

function downloadQuickGuide(guideType) {
    const guideNames = {
        'shipping': 'Shipping Guide',
        'seo': 'SEO Checklist',
        'photo': 'Photo Guide',
        'tax': 'Tax Guide'
    };
    
    const fileName = guideNames[guideType] || 'Quick Guide';
    alert(`Downloading: ${fileName}\n\nIn a real system, this would download the quick guide.`);
}

function printGuide() {
    // Show print preview modal
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Vendor Guide - Print Preview</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .print-header { text-align: center; margin-bottom: 30px; }
                .print-header h1 { color: #4361ee; }
                .chapter { margin-bottom: 30px; page-break-inside: avoid; }
                .chapter h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
                .footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h1>Vendor Success Guide</h1>
                <p>Version 3.0 • January 2026</p>
                <p>Prepared for: <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Vendor'); ?></p>
            </div>
            
            <div class="chapter">
                <h2>1. Getting Started</h2>
                <p>Welcome to our vendor platform! This guide will help you get started and become a successful seller.</p>
                <h3>1.1 Account Setup</h3>
                <p>Complete your vendor profile with accurate information to build trust with customers.</p>
                <h3>1.2 Dashboard Overview</h3>
                <p>Learn to navigate your vendor dashboard for efficient store management.</p>
            </div>
            
            <div class="footer">
                <p><?php echo SITE_NAME; ?> Vendor Guide • Confidential • Printed: ${new Date().toLocaleString()}</p>
            </div>
        </body>
        </html>
    `;
    
    const previewFrame = document.getElementById('printPreviewFrame');
    previewFrame.contentDocument.write(printContent);
    previewFrame.contentDocument.close();
    
    const modal = new bootstrap.Modal(document.getElementById('printPreviewModal'));
    modal.show();
}

function printPreviewContent() {
    const previewFrame = document.getElementById('printPreviewFrame');
    previewFrame.contentWindow.print();
}

function shareGuide() {
    if (navigator.share) {
        navigator.share({
            title: 'Vendor Success Guide',
            text: 'Check out this comprehensive vendor guide from our platform',
            url: window.location.href
        });
    } else {
        // Fallback: Copy link to clipboard
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Guide link copied to clipboard!');
        });
    }
}

function logGuideDownload(type) {
    // In real system, this would be an AJAX call to track downloads
    fetch('log-guide-download.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            guide_type: type,
            user_id: <?php echo $_SESSION['user_id']; ?>,
            timestamp: new Date().toISOString()
        })
    });
}

// Smooth scroll to chapters
document.addEventListener('DOMContentLoaded', function() {
    const chapterLinks = document.querySelectorAll('a[href^="#chapter"]');
    chapterLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>