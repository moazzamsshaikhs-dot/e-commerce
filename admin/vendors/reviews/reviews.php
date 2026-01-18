<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['warning'] = 'Your vendor account needs approval to access reviews.';
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error.';
    redirect(SITE_URL . 'admin/vendors/dashboard.php');
}

$page_title = 'Product Reviews';
require_once '../../includes/header.php';

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_rating = $_GET['rating'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$conditions = ["p.vendor_id = :vendor_id"];
$params = [':vendor_id' => $_SESSION['user_id']];

if ($search) {
    $conditions[] = "(p.name LIKE :search OR r.review_text LIKE :search OR u.username LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter_status === 'approved') {
    $conditions[] = "r.is_approved = 1";
} elseif ($filter_status === 'pending') {
    $conditions[] = "r.is_approved = 0";
}

if (is_numeric($filter_rating) && $filter_rating > 0 && $filter_rating <= 5) {
    $conditions[] = "r.rating = :rating";
    $params[':rating'] = $filter_rating;
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

// Get reviews
try {
    // Total count for pagination
    $count_sql = "SELECT COUNT(*) as total 
                  FROM reviews r
                  JOIN products p ON r.product_id = p.id
                  JOIN users u ON r.user_id = u.id
                  $where_clause";
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_reviews = $stmt->fetch()['total'];
    $total_pages = ceil($total_reviews / $limit);
    
    // Get reviews data
    $sql = "SELECT r.*, p.name as product_name, p.image as product_image,
                   u.username, u.full_name, u.profile_pic,
                   DATE_FORMAT(r.created_at, '%d %b %Y') as review_date
            FROM reviews r
            JOIN products p ON r.product_id = p.id
            JOIN users u ON r.user_id = u.id
            $where_clause
            ORDER BY r.created_at DESC
            LIMIT :offset, :limit";
    
    $stmt = $db->prepare($sql);
    $params[':offset'] = $offset;
    $params[':limit'] = $limit;
    
    foreach($params as $key => $value) {
        if ($key === ':offset' || $key === ':limit') {
            $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $reviews = $stmt->fetchAll();
    
    // Get summary stats
    $stats_sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN r.is_approved = 1 THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN r.is_approved = 0 THEN 1 ELSE 0 END) as pending,
                    AVG(r.rating) as avg_rating,
                    SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as one_star
                  FROM reviews r
                  JOIN products p ON r.product_id = p.id
                  WHERE p.vendor_id = ?";
    
    $stmt = $db->prepare($stats_sql);
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading reviews: ' . $e->getMessage();
    $reviews = [];
    $stats = ['total' => 0, 'avg_rating' => 0];
}
?>

<div class="dashboard-container">
    <!-- Include Vendor Sidebar -->
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Product Reviews</h1>
                <p class="text-muted mb-0">Manage customer reviews for your products</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="fas fa-filter me-2"></i> Filter
                </button>
                <a href="responses.php" class="btn btn-primary">
                    <i class="fas fa-reply me-2"></i> Review Responses
                </a>
                <a href="ratings.php" class="btn btn-primary">
                    <i class="fas fa-star me-2"></i> Ratings
                </a>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold text-primary mb-2"><?php echo $stats['total'] ?? 0; ?></h2>
                        <p class="text-muted mb-0">Total Reviews</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold text-warning mb-2"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?>/5</h2>
                        <p class="text-muted mb-0">Average Rating</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold text-success mb-2"><?php echo $stats['approved'] ?? 0; ?></h2>
                        <p class="text-muted mb-0">Approved</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h2 class="fw-bold text-info mb-2"><?php echo $stats['pending'] ?? 0; ?></h2>
                        <p class="text-muted mb-0">Pending</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rating Distribution -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">Rating Distribution</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php for($i = 5; $i >= 1; $i--): 
                        $count = $stats[$i.'_star'] ?? 0;
                        $percentage = $stats['total'] > 0 ? ($count / $stats['total']) * 100 : 0;
                    ?>
                    <div class="col-md-12 mb-3">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <span class="text-warning">
                                    <?php for($j = 1; $j <= 5; $j++): ?>
                                        <i class="fas fa-star <?php echo $j <= $i ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <div class="progress flex-grow-1 me-3" style="height: 12px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="text-end" style="min-width: 60px;">
                                <small class="text-muted"><?php echo $count; ?> (<?php echo round($percentage, 1); ?>%)</small>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <!-- Search and Filter Bar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search reviews, products or customers..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="approved" <?php echo $filter_status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <select name="rating" class="form-select">
                                <option value="all" <?php echo $filter_rating == 'all' ? 'selected' : ''; ?>>All Ratings</option>
                                <option value="5" <?php echo $filter_rating == '5' ? 'selected' : ''; ?>>5 Stars</option>
                                <option value="4" <?php echo $filter_rating == '4' ? 'selected' : ''; ?>>4 Stars</option>
                                <option value="3" <?php echo $filter_rating == '3' ? 'selected' : ''; ?>>3 Stars</option>
                                <option value="2" <?php echo $filter_rating == '2' ? 'selected' : ''; ?>>2 Stars</option>
                                <option value="1" <?php echo $filter_rating == '1' ? 'selected' : ''; ?>>1 Star</option>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="reviews.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Reviews List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Customer Reviews (<?php echo $total_reviews; ?>)</h5>
                <?php if ($total_reviews > 0): ?>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-3">Showing <?php echo count($reviews); ?> of <?php echo $total_reviews; ?></small>
                    <a href="#" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download me-2"></i> Export
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card-body">
                <?php if (empty($reviews)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Reviews Found</h4>
                        <p class="text-muted mb-4">You haven't received any reviews yet.</p>
                        <a href="../products/products.php" class="btn btn-primary">
                            <i class="fas fa-boxes me-2"></i> View Your Products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($reviews as $review): ?>
                        <div class="list-group-item border-0 px-0 py-4">
                            <div class="row g-3">
                                <!-- Customer Info -->
                                <div class="col-lg-3 col-md-4">
                                    <div class="d-flex">
                                        <div class="flex-shrink-0">
                                            <div class="avatar-lg bg-light rounded-circle d-flex align-items-center justify-content-center">
                                                <?php if ($review['profile_pic'] && $review['profile_pic'] !== 'default.png'): ?>
                                                <img src="<?php echo SITE_URL; ?>assets/images/profiles/<?php echo $review['profile_pic']; ?>" 
                                                     alt="Profile" class="rounded-circle" width="60" height="60"
                                                     onerror="this.src='<?php echo SITE_URL; ?>assets/images/avatars/default.png'">
                                                <?php else: ?>
                                                    <i class="fas fa-user text-muted fa-2x"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-1"><?php echo $review['full_name'] ?? $review['username']; ?></h6>
                                            <small class="text-muted d-block">@<?php echo $review['username']; ?></small>
                                            <small class="text-muted d-block"><?php echo $review['review_date']; ?></small>
                                            <div class="mt-2">
                                                <span class="badge bg-<?php echo $review['is_approved'] ? 'success' : 'warning'; ?>">
                                                    <i class="fas fa-<?php echo $review['is_approved'] ? 'check' : 'clock'; ?> me-1"></i>
                                                    <?php echo $review['is_approved'] ? 'Approved' : 'Pending'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Review Content -->
                                <div class="col-lg-6 col-md-5">
                                    <!-- Product Info -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-3">
                                                <?php if ($review['product_image']): ?>
                                                <img src="<?php echo SITE_URL; ?>uploads/products/<?php echo $review['product_image']; ?>" 
                                                     alt="Product" class="rounded" width="50" height="50"
                                                     style="object-fit: cover;">
                                                <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                     style="width: 50px; height: 50px;">
                                                    <i class="fas fa-box text-muted"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $review['product_name']; ?></h6>
                                                <small class="text-muted">Product</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Rating -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="text-warning me-3">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="badge bg-warning text-dark">
                                                <?php echo $review['rating']; ?>/5
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Review Text -->
                                    <div class="mb-3">
                                        <p class="mb-0 text-dark"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary reply-btn" 
                                                data-review-id="<?php echo $review['id']; ?>"
                                                data-customer="<?php echo $review['username']; ?>">
                                            <i class="fas fa-reply me-1"></i> Reply
                                        </button>
                                        
                                        <?php if (!$review['is_approved']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success approve-btn"
                                                data-review-id="<?php echo $review['id']; ?>">
                                            <i class="fas fa-check me-1"></i> Approve
                                        </button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary unapprove-btn"
                                                data-review-id="<?php echo $review['id']; ?>">
                                            <i class="fas fa-times me-1"></i> Unapprove
                                        </button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                                data-review-id="<?php echo $review['id']; ?>">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Product Actions -->
                                <div class="col-lg-3 col-md-3">
                                    <div class="d-flex flex-column gap-2">
                                        <a href="../products/edit.php?id=<?php echo $review['product_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary w-100">
                                            <i class="fas fa-edit me-1"></i> Edit Product
                                        </a>
                                        <a href="ratings.php?product=<?php echo $review['product_id']; ?>" 
                                           class="btn btn-sm btn-outline-info w-100">
                                            <i class="fas fa-chart-bar me-1"></i> View Ratings
                                        </a>
                                        <a href="#" class="btn btn-sm btn-outline-secondary w-100" 
                                           data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $review['id']; ?>">
                                            <i class="fas fa-eye me-1"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Review Details Modal -->
                            <div class="modal fade" id="reviewModal<?php echo $review['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Review Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <!-- Modal content here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&<?php echo http_build_query($_GET); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == 1 || $i == $total_pages || ($i >= $page-2 && $i <= $page+2)): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query($_GET); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php elseif ($i == $page-3 || $i == $page+3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&<?php echo http_build_query($_GET); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Reviews</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="filterForm" method="GET">
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <div class="row g-2">
                            <div class="col">
                                <input type="date" name="date_from" class="form-control">
                            </div>
                            <div class="col">
                                <input type="date" name="date_to" class="form-control">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rating</label>
                        <select name="rating_filter" class="form-select">
                            <option value="all">All Ratings</option>
                            <option value="5">5 Stars Only</option>
                            <option value="4-5">4 & 5 Stars</option>
                            <option value="1-3">1-3 Stars</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_filter[]" value="approved" id="approvedCheck">
                            <label class="form-check-label" for="approvedCheck">
                                Approved
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="status_filter[]" value="pending" id="pendingCheck">
                            <label class="form-check-label" for="pendingCheck">
                                Pending
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="filterForm" class="btn btn-primary">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reply to Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="replyForm" method="POST" action="action/reply.php">
                <input type="hidden" name="review_id" id="replyReviewId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Customer</label>
                        <input type="text" id="replyCustomer" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Your Reply</label>
                        <textarea name="reply_text" class="form-control" rows="4" 
                                  placeholder="Type your response here..." required></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_public" id="isPublic" checked>
                        <label class="form-check-label" for="isPublic">
                            Make reply public (visible to all customers)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reviews CSS -->
<style>
/* Reviews specific styles */
.review-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.review-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08) !important;
}

.star-rating .fa-star {
    font-size: 1.2rem;
}

.avatar-lg {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    overflow: hidden;
}

.review-actions .btn {
    font-size: 0.85rem;
    padding: 0.25rem 0.75rem;
}

.progress {
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
}
</style>

<!-- Reviews JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reply button handler
    document.querySelectorAll('.reply-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            const customer = this.getAttribute('data-customer');
            
            document.getElementById('replyReviewId').value = reviewId;
            document.getElementById('replyCustomer').value = customer;
            
            const replyModal = new bootstrap.Modal(document.getElementById('replyModal'));
            replyModal.show();
        });
    });
    
    // Approve review
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            if (confirm('Are you sure you want to approve this review?')) {
                approveReview(reviewId, true);
            }
        });
    });
    
    // Unapprove review
    document.querySelectorAll('.unapprove-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            if (confirm('Are you sure you want to unapprove this review?')) {
                approveReview(reviewId, false);
            }
        });
    });
    
    // Delete review
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-review-id');
            if (confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
                deleteReview(reviewId);
            }
        });
    });
    
    // Reply form submission
    document.getElementById('replyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('action/reply.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reply sent successfully!');
                bootstrap.Modal.getInstance(document.getElementById('replyModal')).hide();
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Network error: ' + error);
        });
    });
});

function approveReview(reviewId, approve) {
    const formData = new FormData();
    formData.append('review_id', reviewId);
    formData.append('action', approve ? 'approve' : 'unapprove');
    
    fetch('action/approve.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(approve ? 'Review approved!' : 'Review unapproved!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Network error: ' + error);
    });
}

function deleteReview(reviewId) {
    const formData = new FormData();
    formData.append('review_id', reviewId);
    
    fetch('action/delete.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Review deleted!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Network error: ' + error);
    });
}
</script>

<?php require_once '../../includes/footer.php'; ?>