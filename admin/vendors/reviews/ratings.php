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

$page_title = 'Ratings Analytics';
require_once '../../includes/header.php';

// Get time filter
$time_filter = $_GET['time'] ?? 'month';
$product_filter = $_GET['product'] ?? 'all';

// Calculate date ranges
$end_date = date('Y-m-d');
switch($time_filter) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'quarter':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
}

try {
    $vendor_id = $_SESSION['user_id'];
    
    // Overall rating stats
    $stats_sql = "SELECT 
                    COUNT(*) as total_reviews,
                    AVG(r.rating) as avg_rating,
                    MIN(r.rating) as min_rating,
                    MAX(r.rating) as max_rating,
                    STDDEV(r.rating) as rating_deviation,
                    SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star,
                    SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as four_star,
                    SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as three_star,
                    SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as two_star,
                    SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as one_star
                  FROM reviews r
                  JOIN products p ON r.product_id = p.id
                  WHERE p.vendor_id = ? AND r.created_at BETWEEN ? AND ?";
    
    $stmt = $db->prepare($stats_sql);
    $stmt->execute([$vendor_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $stats = $stmt->fetch();
    
    // Monthly trend
    $trend_sql = "SELECT 
                    DATE_FORMAT(r.created_at, '%Y-%m') as month,
                    COUNT(*) as review_count,
                    AVG(r.rating) as avg_rating
                  FROM reviews r
                  JOIN products p ON r.product_id = p.id
                  WHERE p.vendor_id = ?
                  GROUP BY DATE_FORMAT(r.created_at, '%Y-%m')
                  ORDER BY month DESC
                  LIMIT 12";
    
    $stmt = $db->prepare($trend_sql);
    $stmt->execute([$vendor_id]);
    $monthly_trend = $stmt->fetchAll();
    
    // Product-wise ratings
    $products_sql = "SELECT 
                        p.id, p.name, p.image,
                        COUNT(r.id) as review_count,
                        AVG(r.rating) as avg_rating,
                        SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star,
                        SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as four_star,
                        SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as three_star,
                        SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as two_star,
                        SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as one_star
                     FROM products p
                     LEFT JOIN reviews r ON p.id = r.product_id
                     WHERE p.vendor_id = ?
                     GROUP BY p.id
                     HAVING review_count > 0
                     ORDER BY avg_rating DESC, review_count DESC";
    
    $stmt = $db->prepare($products_sql);
    $stmt->execute([$vendor_id]);
    $product_ratings = $stmt->fetchAll();
    
    // Rating over time (for chart)
    $daily_sql = "SELECT 
                    DATE(r.created_at) as date,
                    COUNT(*) as count,
                    AVG(r.rating) as avg_rating
                  FROM reviews r
                  JOIN products p ON r.product_id = p.id
                  WHERE p.vendor_id = ? AND r.created_at BETWEEN ? AND ?
                  GROUP BY DATE(r.created_at)
                  ORDER BY date";
    
    $stmt = $db->prepare($daily_sql);
    $stmt->execute([$vendor_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $daily_ratings = $stmt->fetchAll();
    
    // Customer sentiment
    $sentiment_sql = "SELECT 
                        CASE 
                            WHEN r.rating >= 4 THEN 'Positive'
                            WHEN r.rating = 3 THEN 'Neutral'
                            ELSE 'Negative'
                        END as sentiment,
                        COUNT(*) as count,
                        AVG(r.rating) as avg_rating
                      FROM reviews r
                      JOIN products p ON r.product_id = p.id
                      WHERE p.vendor_id = ? AND r.created_at BETWEEN ? AND ?
                      GROUP BY sentiment
                      ORDER BY sentiment";
    
    $stmt = $db->prepare($sentiment_sql);
    $stmt->execute([$vendor_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $sentiment = $stmt->fetchAll();
    
    // Recent reviews for timeline
    $recent_sql = "SELECT 
                      r.*, p.name as product_name, u.username,
                      DATE_FORMAT(r.created_at, '%d %b %Y %h:%i %p') as review_date
                   FROM reviews r
                   JOIN products p ON r.product_id = p.id
                   JOIN users u ON r.user_id = u.id
                   WHERE p.vendor_id = ?
                   ORDER BY r.created_at DESC
                   LIMIT 10";
    
    $stmt = $db->prepare($recent_sql);
    $stmt->execute([$vendor_id]);
    $recent_reviews = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading analytics: ' . $e->getMessage();
    $stats = ['total_reviews' => 0, 'avg_rating' => 0];
    $product_ratings = [];
    $daily_ratings = [];
    $sentiment = [];
}
?>

<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Ratings Analytics</h1>
                <p class="text-muted mb-0">Detailed analysis of your product ratings and reviews</p>
            </div>
            <div class="d-flex gap-2">
                <select id="timeFilter" class="form-select" style="width: auto;">
                    <option value="week" <?php echo $time_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $time_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="quarter" <?php echo $time_filter === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="year" <?php echo $time_filter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                </select>
                <a href="reviews.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Reviews
                </a>
            </div>
        </div>
        
        <!-- Overall Stats -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h1 class="display-5 fw-bold text-warning mb-2">
                            <?php echo number_format($stats['avg_rating'] ?? 0, 1); ?>/5
                        </h1>
                        <div class="mb-3">
                            <?php 
                            $avg = $stats['avg_rating'] ?? 0;
                            for($i = 1; $i <= 5; $i++): 
                                $starClass = $i <= floor($avg) ? 'fas fa-star' : 
                                            ($i <= ceil($avg) ? 'fas fa-star-half-alt' : 'far fa-star');
                            ?>
                                <i class="<?php echo $starClass; ?> text-warning"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted mb-0">Average Rating</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h1 class="display-5 fw-bold text-primary mb-2"><?php echo $stats['total_reviews'] ?? 0; ?></h1>
                        <p class="text-muted mb-0">Total Reviews</p>
                        <small class="text-muted">
                            <?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d', strtotime($end_date)); ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h1 class="display-5 fw-bold text-success mb-2">
                            <?php 
                            $positive = ($stats['five_star'] ?? 0) + ($stats['four_star'] ?? 0);
                            $total = $stats['total_reviews'] ?? 1;
                            echo $total > 0 ? round(($positive / $total) * 100, 0) : 0;
                            ?>%
                        </h1>
                        <p class="text-muted mb-0">Positive Reviews (4+ stars)</p>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-4">
                        <h1 class="display-5 fw-bold text-info mb-2"><?php echo count($product_ratings); ?></h1>
                        <p class="text-muted mb-0">Rated Products</p>
                        <small class="text-muted">Products with reviews</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <!-- Rating Distribution Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Rating Distribution</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-secondary active" data-chart="distribution">
                                Distribution
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-chart="sentiment">
                                Sentiment
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Chart will be inserted here by JavaScript -->
                        <canvas id="ratingChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Sentiment Analysis -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">Customer Sentiment</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($sentiment)): ?>
                            <div class="sentiment-list">
                                <?php foreach($sentiment as $item): ?>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="fw-bold">
                                            <?php echo $item['sentiment']; ?>
                                        </span>
                                        <span class="text-muted"><?php echo $item['count']; ?> reviews</span>
                                    </div>
                                    <div class="progress" style="height: 20px;">
                                        <?php 
                                        $total_sentiment = array_sum(array_column($sentiment, 'count'));
                                        $percentage = $total_sentiment > 0 ? ($item['count'] / $total_sentiment) * 100 : 0;
                                        $bg_class = $item['sentiment'] === 'Positive' ? 'bg-success' : 
                                                   ($item['sentiment'] === 'Neutral' ? 'bg-warning' : 'bg-danger');
                                        ?>
                                        <div class="progress-bar <?php echo $bg_class; ?>" 
                                             style="width: <?php echo $percentage; ?>%">
                                            <?php echo round($percentage, 1); ?>%
                                        </div>
                                    </div>
                                    <small class="text-muted mt-1 d-block">
                                        Avg: <?php echo number_format($item['avg_rating'], 1); ?>/5
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No sentiment data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product-wise Ratings -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">Product Ratings</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($product_ratings)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Reviews</th>
                                    <th>Average Rating</th>
                                    <th>Rating Distribution</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($product_ratings as $product): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm me-3">
                                                <?php if ($product['image']): ?>
                                                <img src="<?php echo SITE_URL; ?>uploads/products/<?php echo $product['image']; ?>" 
                                                     alt="Product" class="rounded" width="40" height="40">
                                                <?php else: ?>
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-box text-muted"></i>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div><?php echo $product['name']; ?></div>
                                                <small class="text-muted">ID: <?php echo $product['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <h5 class="mb-0"><?php echo $product['review_count']; ?></h5>
                                        <small class="text-muted">reviews</small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="text-warning me-2">
                                                <?php 
                                                $avg = $product['avg_rating'] ?? 0;
                                                for($i = 1; $i <= 5; $i++): 
                                                    $starClass = $i <= floor($avg) ? 'fas fa-star' : 
                                                                ($i <= ceil($avg) ? 'fas fa-star-half-alt' : 'far fa-star');
                                                ?>
                                                    <i class="<?php echo $starClass; ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="fw-bold"><?php echo number_format($avg, 1); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php 
                                            $total = $product['review_count'];
                                            $stars = [
                                                5 => $product['five_star'],
                                                4 => $product['four_star'],
                                                3 => $product['three_star'],
                                                2 => $product['two_star'],
                                                1 => $product['one_star']
                                            ];
                                            ?>
                                            <div style="width: 150px;">
                                                <?php for($i = 5; $i >= 1; $i--): 
                                                    $percentage = $total > 0 ? ($stars[$i] / $total) * 100 : 0;
                                                ?>
                                                    <div class="d-flex align-items-center mb-1">
                                                        <small class="text-muted me-2" style="width: 20px;"><?php echo $i; ?>★</small>
                                                        <div class="progress flex-grow-1" style="height: 8px;">
                                                            <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                                        </div>
                                                        <small class="text-muted ms-2" style="width: 30px;"><?php echo $stars[$i]; ?></small>
                                                    </div>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="../products/edit.php?id=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="reviews.php?product=<?php echo $product['id']; ?>" 
                                               class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-comments"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-star fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted mb-3">No Product Ratings Yet</h4>
                        <p class="text-muted">Your products haven't received any ratings yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Reviews Timeline -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">Recent Reviews Timeline</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_reviews)): ?>
                    <div class="timeline">
                        <?php foreach($recent_reviews as $review): ?>
                        <div class="timeline-item mb-4">
                            <div class="timeline-marker">
                                <div class="timeline-icon bg-<?php 
                                    echo $review['rating'] >= 4 ? 'success' : 
                                         ($review['rating'] == 3 ? 'warning' : 'danger');
                                ?>">
                                    <i class="fas fa-star"></i>
                                </div>
                            </div>
                            <div class="timeline-content">
                                <div class="card border">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h6 class="mb-1"><?php echo $review['product_name']; ?></h6>
                                                <small class="text-muted">by @<?php echo $review['username']; ?></small>
                                            </div>
                                            <div class="text-end">
                                                <div class="text-warning mb-1">
                                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small class="text-muted"><?php echo $review['review_date']; ?></small>
                                            </div>
                                        </div>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars(substr($review['review_text'], 0, 200))); ?>...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-history fa-4x text-muted mb-3"></i>
                        <p class="text-muted">No recent reviews</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Timeline CSS -->
<style>
.timeline {
    position: relative;
    padding-left: 40px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -40px;
    top: 15px;
    width: 40px;
    text-align: center;
}

.timeline-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.timeline-content {
    position: relative;
}
</style>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Time filter change
    document.getElementById('timeFilter').addEventListener('change', function() {
        const time = this.value;
        window.location.href = `ratings.php?time=${time}`;
    });
    
    // Chart data
    const ratingData = {
        labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
        datasets: [{
            label: 'Number of Reviews',
            data: [
                <?php echo $stats['five_star'] ?? 0; ?>,
                <?php echo $stats['four_star'] ?? 0; ?>,
                <?php echo $stats['three_star'] ?? 0; ?>,
                <?php echo $stats['two_star'] ?? 0; ?>,
                <?php echo $stats['one_star'] ?? 0; ?>
            ],
            backgroundColor: [
                '#22c55e',
                '#3b82f6',
                '#f59e0b',
                '#f97316',
                '#ef4444'
            ],
            borderColor: '#fff',
            borderWidth: 1
        }]
    };
    
    const sentimentData = {
        labels: <?php echo json_encode(array_column($sentiment, 'sentiment')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($sentiment, 'count')); ?>,
            backgroundColor: [
                '#22c55e', // Positive
                '#f59e0b', // Neutral
                '#ef4444'  // Negative
            ]
        }]
    };
    
    // Initialize chart
    const ctx = document.getElementById('ratingChart').getContext('2d');
    let currentChart = new Chart(ctx, {
        type: 'bar',
        data: ratingData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.parsed.y;
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Chart type toggle
    document.querySelectorAll('[data-chart]').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('[data-chart]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const chartType = this.getAttribute('data-chart');
            
            currentChart.destroy();
            
            if (chartType === 'sentiment') {
                currentChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: sentimentData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            } else {
                currentChart = new Chart(ctx, {
                    type: 'bar',
                    data: ratingData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }
        });
    });
    
    // Export data
    document.getElementById('exportBtn')?.addEventListener('click', function() {
        window.location.href = 'action/export.php?type=ratings&time=' + document.getElementById('timeFilter').value;
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>