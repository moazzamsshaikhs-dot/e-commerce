<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Define SITE_URL if not defined


// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    header('Location: ' . SITE_URL . 'index.php');
    exit();
}

// Check if vendor is approved
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT vendor_status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $vendor_status = $stmt->fetchColumn();
    
    if ($vendor_status !== 'approved') {
        $_SESSION['warning'] = 'Your vendor account needs approval.';
        header('Location: ' . SITE_URL . 'admin/vendors/dashboard.php');
        exit();
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error.';
    header('Location: ' . SITE_URL . 'admin/vendors/dashboard.php');
    exit();
}

$page_title = 'Review Responses';
require_once '../../includes/header.php';

// Get parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query
$conditions = ["p.vendor_id = :vendor_id"];
$params = [':vendor_id' => $_SESSION['user_id']];

if ($search) {
    $conditions[] = "(p.name LIKE :search OR r.review_text LIKE :search OR u.username LIKE :search OR vr.response_text LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($filter === 'replied') {
    $conditions[] = "vr.id IS NOT NULL";
} elseif ($filter === 'unreplied') {
    $conditions[] = "vr.id IS NULL";
}

$where_clause = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

try {
    // Get vendor responses with reviews
    $sql = "SELECT r.*, 
                   p.name as product_name, p.image as product_image,
                   u.username as customer_name, u.full_name as customer_fullname,
                   vr.id as response_id, vr.response_text, vr.created_at as response_date,
                   vr.is_public, vr.is_edited,
                   DATE_FORMAT(r.created_at, '%d %b %Y %h:%i %p') as review_date_formatted,
                   DATE_FORMAT(vr.created_at, '%d %b %Y %h:%i %p') as response_date_formatted,
                   (SELECT COUNT(*) FROM review_likes WHERE review_id = r.id) as likes_count
            FROM reviews r
            JOIN products p ON r.product_id = p.id
            JOIN users u ON r.user_id = u.id
            LEFT JOIN vendor_responses vr ON r.id = vr.review_id
            $where_clause
            ORDER BY COALESCE(vr.created_at, r.created_at) DESC
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
    $responses = $stmt->fetchAll();
    
    // Count totals
    $count_sql = "SELECT 
                    COUNT(*) as total,
                    COUNT(vr.id) as replied,
                    SUM(CASE WHEN vr.id IS NULL THEN 1 ELSE 0 END) as unreplied
                  FROM reviews r
                  JOIN products p ON r.product_id = p.id
                  LEFT JOIN vendor_responses vr ON r.id = vr.review_id
                  WHERE p.vendor_id = ?";
    
    $stmt = $db->prepare($count_sql);
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading responses: ' . $e->getMessage();
    $responses = [];
    $stats = ['total' => 0, 'replied' => 0, 'unreplied' => 0];
}

// Function to log user activity
if (!function_exists('logUserActivity')) {
    function logUserActivity($user_id, $type, $description) {
        try {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$user_id, $type, $description]);
        } catch(Exception $e) {
            // Silent fail
        }
    }
}
?>

<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Review Responses</h1>
                <p class="text-muted mb-0">Manage your replies to customer reviews</p>
            </div>
            <a href="reviews.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Back to Reviews
            </a>
        </div>
        
        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg bg-primary bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="fas fa-comments text-primary fa-2x"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h2 class="mb-0 fw-bold"><?php echo $stats['total']; ?></h2>
                                <p class="text-muted mb-0">Total Reviews</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="fas fa-check-circle text-success fa-2x"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h2 class="mb-0 fw-bold"><?php echo $stats['replied']; ?></h2>
                                <p class="text-muted mb-0">Replied</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avatar-lg bg-warning bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center">
                                    <i class="fas fa-clock text-warning fa-2x"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h2 class="mb-0 fw-bold"><?php echo $stats['unreplied']; ?></h2>
                                <p class="text-muted mb-0">Need Reply</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter and Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search reviews or responses..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="filter" class="form-select">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
                            <option value="replied" <?php echo $filter === 'replied' ? 'selected' : ''; ?>>Replied Only</option>
                            <option value="unreplied" <?php echo $filter === 'unreplied' ? 'selected' : ''; ?>>Need Reply</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="responses.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Responses List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Review Responses</h5>
                <small class="text-muted"><?php echo count($responses); ?> items</small>
            </div>
            
            <div class="card-body">
                <?php if (empty($responses)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Reviews Found</h4>
                        <p class="text-muted mb-4">
                            <?php echo $filter === 'unreplied' ? 
                                'Great! You have replied to all reviews.' : 
                                'No reviews match your filter.'; ?>
                        </p>
                        <a href="reviews.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Reviews
                        </a>
                    </div>
                <?php else: ?>
                    <div class="accordion" id="responsesAccordion">
                        <?php foreach($responses as $index => $response): ?>
                        <div class="accordion-item border-0 mb-3">
                            <div class="accordion-header">
                                <button class="accordion-button collapsed p-4 shadow-sm rounded" 
                                        type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $index; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <?php if ($response['product_image']): ?>
                                                <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $response['product_image']; ?>" 
                                                     alt="Product" class="rounded-circle" width="40" height="40">
                                                <?php else: ?>
                                                    <i class="fas fa-box text-muted"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo $response['product_name']; ?></h6>
                                                <small class="text-muted">
                                                    Review by <?php echo $response['customer_fullname'] ?? $response['customer_name']; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="mb-1">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= $response['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                <?php endfor; ?>
                                                <span class="badge bg-warning text-dark ms-2"><?php echo $response['rating']; ?>/5</span>
                                            </div>
                                            <small class="text-muted">
                                                <?php if ($response['response_id']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i> Replied
                                                    </span>
                                                    <span class="ms-2"><?php echo $response['response_date_formatted']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock me-1"></i> Needs Reply
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </button>
                            </div>
                            
                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" 
                                 data-bs-parent="#responsesAccordion">
                                <div class="accordion-body pt-0">
                                    <div class="row">
                                        <!-- Review Content -->
                                        <div class="col-md-6">
                                            <div class="card border mb-3">
                                                <div class="card-header bg-light">
                                                    <h6 class="mb-0">
                                                        <i class="fas fa-user me-2 text-primary"></i>
                                                        Customer Review
                                                        <small class="text-muted float-end"><?php echo $response['review_date_formatted']; ?></small>
                                                    </h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="mb-3">
                                                        <strong>Rating:</strong>
                                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $response['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                        <?php endfor; ?>
                                                        (<?php echo $response['rating']; ?>/5)
                                                    </div>
                                                    <div class="mb-3">
                                                        <strong>Review:</strong>
                                                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($response['review_text'])); ?></p>
                                                    </div>
                                                    <?php if ($response['likes_count'] > 0): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-thumbs-up me-1"></i> <?php echo $response['likes_count']; ?> likes
                                                    </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Response Form -->
                                        <div class="col-md-6">
                                            <?php if ($response['response_id']): ?>
                                                <!-- Edit Existing Response -->
                                                <div class="card border">
                                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-reply me-2 text-success"></i>
                                                            Your Response
                                                            <?php if ($response['is_edited']): ?>
                                                                <small class="text-muted">(Edited)</small>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <small class="text-muted"><?php echo $response['response_date_formatted']; ?></small>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" action="responses.php" class="response-form">
                                                            <input type="hidden" name="action" value="update_response">
                                                            <input type="hidden" name="response_id" value="<?php echo $response['response_id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <textarea name="response_text" class="form-control" rows="4" required><?php echo htmlspecialchars($response['response_text']); ?></textarea>
                                                            </div>
                                                            
                                                            <div class="mb-3 form-check">
                                                                <input type="checkbox" name="is_public" class="form-check-input" 
                                                                       id="public<?php echo $index; ?>" 
                                                                       <?php echo $response['is_public'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="public<?php echo $index; ?>">
                                                                    Make response public
                                                                </label>
                                                            </div>
                                                            
                                                            <div class="d-flex gap-2">
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="fas fa-save me-1"></i> Update Response
                                                                </button>
                                                                
                                                                <button type="button" class="btn btn-outline-danger delete-response-btn" 
                                                                        data-response-id="<?php echo $response['response_id']; ?>">
                                                                    <i class="fas fa-trash me-1"></i> Delete
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <!-- New Response Form -->
                                                <div class="card border">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">
                                                            <i class="fas fa-reply me-2 text-warning"></i>
                                                            Reply to This Review
                                                        </h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <form method="POST" action="action/reply.php" class="new-response-form">
                                                            <input type="hidden" name="review_id" value="<?php echo $response['id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <textarea name="response_text" class="form-control" rows="4" 
                                                                          placeholder="Thank the customer for their review and address any concerns..." 
                                                                          required></textarea>
                                                            </div>
                                                            
                                                            <div class="mb-3 form-check">
                                                                <input type="checkbox" name="is_public" class="form-check-input" 
                                                                       id="newPublic<?php echo $index; ?>" checked>
                                                                <label class="form-check-label" for="newPublic<?php echo $index; ?>">
                                                                    Make response public
                                                                </label>
                                                            </div>
                                                            
                                                            <button type="submit" class="btn btn-success w-100">
                                                                <i class="fas fa-paper-plane me-1"></i> Send Response
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Response</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this response? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" action="responses.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete_response">
                    <input type="hidden" name="response_id" id="deleteResponseId">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.accordion-button {
    background: #f8f9fa;
    font-weight: 500;
}

.accordion-button:not(.collapsed) {
    background: #e7f1ff;
    color: #0d6efd;
}

.accordion-button:focus {
    box-shadow: none;
    border-color: #86b7fe;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    overflow: hidden;
}

.avatar-lg {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete response button
    document.querySelectorAll('.delete-response-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const responseId = this.getAttribute('data-response-id');
            document.getElementById('deleteResponseId').value = responseId;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        });
    });
    
    // Response form submission
    document.querySelectorAll('.response-form, .new-response-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const action = this.action || 'responses.php';
            
            fetch(action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                location.reload();
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>