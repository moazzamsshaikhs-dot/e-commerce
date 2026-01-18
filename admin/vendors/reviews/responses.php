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
        $_SESSION['warning'] = 'Your vendor account needs approval.';
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    }
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error.';
    redirect(SITE_URL . 'admin/vendors/dashboard.php');
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
                   (SELECT COUNT(*) FROM review_likes WHERE review_id = r.id) as likes_count,
                   (SELECT COUNT(*) FROM review_reports WHERE review_id = r.id) as reports_count
            FROM reviews r
            JOIN products p ON r.product_id = p.id
            JOIN users u ON r.user_id = u.id
            LEFT JOIN vendor_responses vr ON r.id = vr.review_id
            $where_clause
            ORDER BY vr.created_at DESC, r.created_at DESC
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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response_id = $_POST['response_id'] ?? 0;
    
    try {
        if ($action === 'delete_response' && $response_id) {
            // Verify ownership
            $stmt = $db->prepare("
                DELETE vr FROM vendor_responses vr
                JOIN reviews r ON vr.review_id = r.id
                JOIN products p ON r.product_id = p.id
                WHERE vr.id = ? AND p.vendor_id = ?
            ");
            $stmt->execute([$response_id, $_SESSION['user_id']]);
            
            $_SESSION['success'] = 'Response deleted successfully!';
            redirect('responses.php');
            
        } elseif ($action === 'update_response') {
            $response_text = $_POST['response_text'] ?? '';
            $is_public = isset($_POST['is_public']) ? 1 : 0;
            $response_id = $_POST['response_id'] ?? 0;
            
            if ($response_text && $response_id) {
                $stmt = $db->prepare("
                    UPDATE vendor_responses vr
                    JOIN reviews r ON vr.review_id = r.id
                    JOIN products p ON r.product_id = p.id
                    SET vr.response_text = ?, vr.is_public = ?, vr.is_edited = 1, vr.updated_at = NOW()
                    WHERE vr.id = ? AND p.vendor_id = ?
                ");
                $stmt->execute([$response_text, $is_public, $response_id, $_SESSION['user_id']]);
                
                $_SESSION['success'] = 'Response updated successfully!';
                redirect('responses.php');
            }
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error processing action: ' . $e->getMessage();
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
                <div class="row g-3">
                    <div class="col-md-8">
                        <form method="GET" class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fas fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search reviews or responses..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <select name="filter" class="form-select" style="max-width: 200px;">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Reviews</option>
                                <option value="replied" <?php echo $filter === 'replied' ? 'selected' : ''; ?>>Replied Only</option>
                                <option value="unreplied" <?php echo $filter === 'unreplied' ? 'selected' : ''; ?>>Need Reply</option>
                            </select>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                        </form>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group">
                            <button class="btn btn-outline-secondary" onclick="exportResponses()">
                                <i class="fas fa-download me-2"></i> Export
                            </button>
                            <a href="reviews.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Reply to New Review
                            </a>
                        </div>
                    </div>
                </div>
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
                                                <img src="<?php echo SITE_URL; ?>uploads/products/<?php echo $response['product_image']; ?>" 
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
                                                        <strong>Customer:</strong> 
                                                        <?php echo $response['customer_fullname'] ?? $response['customer_name']; ?>
                                                    </div>
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
                                                    <div class="d-flex gap-2">
                                                        <small class="text-muted">
                                                            <i class="fas fa-thumbs-up me-1"></i> <?php echo $response['likes_count']; ?> likes
                                                        </small>
                                                        <?php if ($response['reports_count'] > 0): ?>
                                                        <small class="text-danger">
                                                            <i class="fas fa-flag me-1"></i> <?php echo $response['reports_count']; ?> reports
                                                        </small>
                                                        <?php endif; ?>
                                                    </div>
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
                                                        <form method="POST" class="response-form" 
                                                              data-response-id="<?php echo $response['response_id']; ?>">
                                                            <input type="hidden" name="action" value="update_response">
                                                            <input type="hidden" name="response_id" value="<?php echo $response['response_id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Your Response</label>
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
                                                            
                                                            <div class="d-flex justify-content-between">
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="fas fa-save me-1"></i> Update Response
                                                                </button>
                                                                
                                                                <button type="button" class="btn btn-outline-danger delete-response-btn" 
                                                                        data-response-id="<?php echo $response['response_id']; ?>">
                                                                    <i class="fas fa-trash me-1"></i> Delete Response
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
                                                        <form method="POST" action="../action/reply.php" class="new-response-form">
                                                            <input type="hidden" name="review_id" value="<?php echo $response['id']; ?>">
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Your Response</label>
                                                                <textarea name="response_text" class="form-control" rows="4" 
                                                                          placeholder="Thank the customer for their review and address any concerns..." 
                                                                          required></textarea>
                                                            </div>
                                                            
                                                            <div class="mb-3 form-check">
                                                                <input type="checkbox" name="is_public" class="form-check-input" 
                                                                       id="newPublic<?php echo $index; ?>" checked>
                                                                <label class="form-check-label" for="newPublic<?php echo $index; ?>">
                                                                    Make response public (visible to all customers)
                                                                </label>
                                                            </div>
                                                            
                                                            <div class="d-grid">
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="fas fa-paper-plane me-1"></i> Send Response
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <!-- Response Tips -->
                                            <div class="card border mt-3">
                                                <div class="card-body p-3">
                                                    <small class="text-muted">
                                                        <i class="fas fa-lightbulb me-1 text-warning"></i>
                                                        <strong>Response Tips:</strong> Be professional, thank customers, address concerns, and avoid arguments.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Response Stats -->
                    <div class="mt-4 pt-4 border-top">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Response Statistics</h6>
                                <div class="d-flex align-items-center mt-3">
                                    <div class="progress flex-grow-1 me-3" style="height: 20px;">
                                        <div class="progress-bar bg-success" 
                                             style="width: <?php echo $stats['total'] > 0 ? ($stats['replied'] / $stats['total']) * 100 : 0; ?>%">
                                            <?php echo $stats['total'] > 0 ? round(($stats['replied'] / $stats['total']) * 100, 1) : 0; ?>%
                                        </div>
                                    </div>
                                    <div class="text-end" style="min-width: 100px;">
                                        <small class="text-muted"><?php echo $stats['replied']; ?> of <?php echo $stats['total']; ?> replied</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <h6>Average Response Time</h6>
                                <?php
                                try {
                                    $stmt = $db->prepare("
                                        SELECT AVG(TIMESTAMPDIFF(HOUR, r.created_at, vr.created_at)) as avg_hours
                                        FROM vendor_responses vr
                                        JOIN reviews r ON vr.review_id = r.id
                                        JOIN products p ON r.product_id = p.id
                                        WHERE p.vendor_id = ?
                                    ");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $avg_time = $stmt->fetchColumn();
                                    
                                    if ($avg_time) {
                                        if ($avg_time < 24) {
                                            echo '<h3 class="text-success">' . round($avg_time, 1) . ' hours</h3>';
                                        } elseif ($avg_time < 168) {
                                            echo '<h3 class="text-warning">' . round($avg_time/24, 1) . ' days</h3>';
                                        } else {
                                            echo '<h3 class="text-danger">' . round($avg_time/168, 1) . ' weeks</h3>';
                                        }
                                    } else {
                                        echo '<p class="text-muted">No data available</p>';
                                    }
                                } catch(PDOException $e) {
                                    echo '<p class="text-muted">Error calculating</p>';
                                }
                                ?>
                                <small class="text-muted">Lower is better</small>
                            </div>
                        </div>
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
                <form id="deleteForm" method="POST" style="display: inline;">
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
    box-shadow: none;
}

.accordion-button:focus {
    box-shadow: none;
    border-color: #86b7fe;
}

.response-form textarea {
    min-height: 120px;
}

.avatar-sm {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-lg {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress {
    border-radius: 10px;
}

.progress-bar {
    border-radius: 10px;
    font-size: 0.75rem;
    line-height: 20px;
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
    
    // Update response form
    document.querySelectorAll('.response-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('responses.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        });
    });
    
    // New response form
    document.querySelectorAll('.new-response-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Response sent successfully!');
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
});

function exportResponses() {
    // This would typically call an export script
    window.location.href = 'action/export.php?type=responses';
}
</script>

<?php require_once '../../includes/footer.php'; ?>