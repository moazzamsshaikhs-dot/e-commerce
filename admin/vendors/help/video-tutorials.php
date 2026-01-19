<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Video Tutorials - Vendor Dashboard';
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
                    <h1 class="h3 mb-1 fw-bold text-primary">Video Tutorials</h1>
                    <p class="text-muted mb-0">
                        <i class="fas fa-play-circle me-1 text-danger"></i>
                        Learn with step-by-step video guides
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="knowledge-base.php" class="btn btn-outline-primary">
                        <i class="fas fa-book me-2"></i> Knowledge Base
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Video Categories -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">Video Categories</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4">
                        <a href="#getting-started" class="text-decoration-none">
                            <div class="card border-0 bg-light h-100 hover-shadow">
                                <div class="card-body text-center p-4">
                                    <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-play-circle fa-2x text-primary"></i>
                                    </div>
                                    <h6 class="fw-bold">Getting Started</h6>
                                    <p class="text-muted small mb-0">8 videos • 45 min</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-4">
                        <a href="#product-management" class="text-decoration-none">
                            <div class="card border-0 bg-light h-100 hover-shadow">
                                <div class="card-body text-center p-4">
                                    <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-box fa-2x text-success"></i>
                                    </div>
                                    <h6 class="fw-bold">Product Management</h6>
                                    <p class="text-muted small mb-0">12 videos • 1.5 hours</p>
                                </div>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-4">
                        <a href="#order-processing" class="text-decoration-none">
                            <div class="card border-0 bg-light h-100 hover-shadow">
                                <div class="card-body text-center p-4">
                                    <div class="avatar-lg bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3">
                                        <i class="fas fa-shopping-cart fa-2x text-warning"></i>
                                    </div>
                                    <h6 class="fw-bold">Order Processing</h6>
                                    <p class="text-muted small mb-0">6 videos • 35 min</p>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Getting Started Videos -->
        <div class="card border-0 shadow-sm mb-4" id="getting-started">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-play-circle me-2 text-primary"></i>
                    Getting Started
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="video-thumbnail mb-3 position-relative">
                                    <div class="ratio ratio-16x9">
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center">
                                            <i class="fas fa-play-circle fa-3x text-primary"></i>
                                        </div>
                                    </div>
                                    <div class="badge bg-primary position-absolute top-0 end-0 m-2">5:30</div>
                                </div>
                                <h6 class="fw-bold">Welcome to Vendor Dashboard</h6>
                                <p class="text-muted small mb-3">Introduction to your vendor account and dashboard overview</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="far fa-eye me-1"></i> 1,245 views
                                    </small>
                                    <button class="btn btn-sm btn-outline-primary" onclick="playVideo('welcome')">
                                        <i class="fas fa-play me-1"></i> Play
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="video-thumbnail mb-3 position-relative">
                                    <div class="ratio ratio-16x9">
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center">
                                            <i class="fas fa-user-circle fa-3x text-success"></i>
                                        </div>
                                    </div>
                                    <div class="badge bg-primary position-absolute top-0 end-0 m-2">7:15</div>
                                </div>
                                <h6 class="fw-bold">Setting Up Your Profile</h6>
                                <p class="text-muted small mb-3">Complete guide to setting up your vendor profile and store settings</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="far fa-eye me-1"></i> 892 views
                                    </small>
                                    <button class="btn btn-sm btn-outline-primary" onclick="playVideo('profile-setup')">
                                        <i class="fas fa-play me-1"></i> Play
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Management Videos -->
        <div class="card border-0 shadow-sm mb-4" id="product-management">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-box me-2 text-success"></i>
                    Product Management
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="video-thumbnail mb-3 position-relative">
                                    <div class="ratio ratio-16x9">
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center">
                                            <i class="fas fa-plus-circle fa-3x text-warning"></i>
                                        </div>
                                    </div>
                                    <div class="badge bg-primary position-absolute top-0 end-0 m-2">10:45</div>
                                </div>
                                <h6 class="fw-bold">Adding Your First Product</h6>
                                <p class="text-muted small mb-3">Step-by-step guide to adding and optimizing product listings</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="far fa-eye me-1"></i> 2,134 views
                                    </small>
                                    <button class="btn btn-sm btn-outline-primary" onclick="playVideo('add-product')">
                                        <i class="fas fa-play me-1"></i> Play
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card border h-100">
                            <div class="card-body">
                                <div class="video-thumbnail mb-3 position-relative">
                                    <div class="ratio ratio-16x9">
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center">
                                            <i class="fas fa-images fa-3x text-info"></i>
                                        </div>
                                    </div>
                                    <div class="badge bg-primary position-absolute top-0 end-0 m-2">8:20</div>
                                </div>
                                <h6 class="fw-bold">Product Image Guidelines</h6>
                                <p class="text-muted small mb-3">Learn how to create professional product images that convert</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="far fa-eye me-1"></i> 1,567 views
                                    </small>
                                    <button class="btn btn-sm btn-outline-primary" onclick="playVideo('product-images')">
                                        <i class="fas fa-play me-1"></i> Play
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Video Player Modal -->
        <div class="modal fade" id="videoModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title" id="videoTitle">Video Tutorial</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="ratio ratio-16x9">
                            <div id="videoPlayer" class="bg-dark">
                                <!-- Video will be loaded here -->
                                <div class="d-flex align-items-center justify-content-center h-100 text-white">
                                    <div class="text-center">
                                        <i class="fas fa-play-circle fa-4x mb-3"></i>
                                        <p>Video player will load here</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="p-3">
                            <h6 id="videoDescription" class="fw-bold mb-2"></h6>
                            <p id="videoDetails" class="text-muted small mb-0"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile App Videos -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-mobile-alt me-2 text-info"></i>
                    Mobile App Tutorials
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg bg-info bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <i class="fas fa-mobile-alt fa-2x text-info"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-2">Manage Your Store on the Go</h6>
                                <p class="text-muted small mb-3">
                                    Download our mobile app to manage orders, update inventory, and communicate with customers from anywhere.
                                </p>
                                <div class="d-flex gap-2">
                                    <a href="#" class="btn btn-sm btn-dark">
                                        <i class="fab fa-apple me-1"></i> App Store
                                    </a>
                                    <a href="#" class="btn btn-sm btn-success">
                                        <i class="fab fa-google-play me-1"></i> Play Store
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="bg-light rounded p-3">
                                <i class="fas fa-qrcode fa-3x text-muted mb-2"></i>
                                <p class="small mb-0">Scan to download</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
.hover-shadow {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.hover-shadow:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
}
.video-thumbnail {
    cursor: pointer;
    transition: opacity 0.3s ease;
}
.video-thumbnail:hover {
    opacity: 0.9;
}
</style>

<script>
const videoLibrary = {
    'welcome': {
        title: 'Welcome to Vendor Dashboard',
        description: 'Introduction to your vendor account and dashboard overview',
        duration: '5:30',
        views: '1,245',
        date: 'Jan 10, 2026',
        src: 'https://www.youtube.com/embed/dQw4w9WgXcQ' // Example video
    },
    'profile-setup': {
        title: 'Setting Up Your Profile',
        description: 'Complete guide to setting up your vendor profile and store settings',
        duration: '7:15',
        views: '892',
        date: 'Jan 12, 2026',
        src: 'https://www.youtube.com/embed/dQw4w9WgXcQ'
    },
    'add-product': {
        title: 'Adding Your First Product',
        description: 'Step-by-step guide to adding and optimizing product listings',
        duration: '10:45',
        views: '2,134',
        date: 'Jan 15, 2026',
        src: 'https://www.youtube.com/embed/dQw4w9WgXcQ'
    },
    'product-images': {
        title: 'Product Image Guidelines',
        description: 'Learn how to create professional product images that convert',
        duration: '8:20',
        views: '1,567',
        date: 'Jan 18, 2026',
        src: 'https://www.youtube.com/embed/dQw4w9WgXcQ'
    }
};

function playVideo(videoKey) {
    const video = videoLibrary[videoKey];
    if (!video) return;
    
    document.getElementById('videoTitle').textContent = video.title;
    document.getElementById('videoDescription').textContent = video.description;
    document.getElementById('videoDetails').innerHTML = `
        <i class="far fa-clock me-1"></i> ${video.duration} • 
        <i class="far fa-eye me-1"></i> ${video.views} views • 
        <i class="far fa-calendar me-1"></i> ${video.date}
    `;
    
    // Load video player
    const videoPlayer = document.getElementById('videoPlayer');
    videoPlayer.innerHTML = `
        <iframe src="${video.src}" 
                frameborder="0" 
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                allowfullscreen 
                class="w-100 h-100">
        </iframe>
    `;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('videoModal'));
    modal.show();
    
    // Track video view (in real system, this would be an AJAX call)
    console.log(`Video played: ${video.title}`);
}

// Auto-play first video tutorial
document.addEventListener('DOMContentLoaded', function() {
    // You could add autoplay for new users here
    // playVideo('welcome');
});
</script>

<?php require_once '../../includes/footer.php'; ?>