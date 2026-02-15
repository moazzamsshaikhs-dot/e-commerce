<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';



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

$page_title = 'Ratings Analytics';
require_once '../../includes/header.php';

// Get time filter
$time_filter = $_GET['time'] ?? 'month';

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
    
    // ============================================
    // 1. Overall rating stats
    // ============================================
    $stats_sql = "SELECT 
                    COUNT(*) as total_reviews,
                    COALESCE(AVG(r.rating), 0) as avg_rating,
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
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stats) {
        $stats = [
            'total_reviews' => 0,
            'avg_rating' => 0,
            'five_star' => 0,
            'four_star' => 0,
            'three_star' => 0,
            'two_star' => 0,
            'one_star' => 0
        ];
    }
    
    // ============================================
    // 2. MONTHLY TREND - Static Data Display
    // ============================================
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
    $monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 3. DAILY RATINGS - Static Data Display
    // ============================================
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
    $daily_ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 4. Customer sentiment
    // ============================================
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
    $sentiment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 5. Product-wise ratings
    // ============================================
    $products_sql = "SELECT 
                        p.id, p.name, p.image,
                        COUNT(r.id) as review_count,
                        COALESCE(AVG(r.rating), 0) as avg_rating,
                        SUM(CASE WHEN r.rating = 5 THEN 1 ELSE 0 END) as five_star,
                        SUM(CASE WHEN r.rating = 4 THEN 1 ELSE 0 END) as four_star,
                        SUM(CASE WHEN r.rating = 3 THEN 1 ELSE 0 END) as three_star,
                        SUM(CASE WHEN r.rating = 2 THEN 1 ELSE 0 END) as two_star,
                        SUM(CASE WHEN r.rating = 1 THEN 1 ELSE 0 END) as one_star
                     FROM products p
                     LEFT JOIN reviews r ON p.id = r.product_id AND r.is_approved = 1
                     WHERE p.vendor_id = ?
                     GROUP BY p.id
                     HAVING review_count > 0
                     ORDER BY avg_rating DESC, review_count DESC";
    
    $stmt = $db->prepare($products_sql);
    $stmt->execute([$vendor_id]);
    $product_ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 6. TIMELINE - Animation Ready
    // ============================================
    $timeline_sql = "SELECT 
                      r.id, r.rating, r.review_text,
                      p.name as product_name,
                      u.username,
                      DATE_FORMAT(r.created_at, '%d %b %Y %h:%i %p') as review_date
                   FROM reviews r
                   JOIN products p ON r.product_id = p.id
                   JOIN users u ON r.user_id = u.id
                   WHERE p.vendor_id = ?
                   ORDER BY r.created_at DESC
                   LIMIT 10";
    
    $stmt = $db->prepare($timeline_sql);
    $stmt->execute([$vendor_id]);
    $timeline_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    error_log("Ratings Analytics Error: " . $e->getMessage());
    $stats = [
        'total_reviews' => 0,
        'avg_rating' => 0,
        'five_star' => 0,
        'four_star' => 0,
        'three_star' => 0,
        'two_star' => 0,
        'one_star' => 0
    ];
    $monthly_trend = [];
    $daily_ratings = [];
    $sentiment = [];
    $product_ratings = [];
    $timeline_reviews = [];
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
                <select id="timeFilter" class="form-select" style="width: 150px;">
                    <option value="week" <?php echo $time_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $time_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="quarter" <?php echo $time_filter === 'quarter' ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="year" <?php echo $time_filter === 'year' ? 'selected' : ''; ?>>Last Year</option>
                </select>
                <a href="reviews.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>
        
        <!-- Stats Cards with Hover Animation -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card">
                    <div class="card-body text-center">
                        <h2 class="fw-bold text-warning mb-1"><?php echo number_format($stats['avg_rating'], 1); ?>/5</h2>
                        <div class="mb-2">
                            <?php 
                            $avg = $stats['avg_rating'];
                            for($i = 1; $i <= 5; $i++): 
                                if ($i <= floor($avg)) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                } elseif ($i == ceil($avg) && $avg - floor($avg) >= 0.5) {
                                    echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                } else {
                                    echo '<i class="far fa-star text-warning"></i>';
                                }
                            endfor; 
                            ?>
                        </div>
                        <p class="text-muted mb-0">Average Rating</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card">
                    <div class="card-body text-center">
                        <h2 class="fw-bold text-primary mb-1"><?php echo $stats['total_reviews']; ?></h2>
                        <p class="text-muted mb-0">Total Reviews</p>
                        <small class="text-muted"><?php echo date('M d', strtotime($start_date)); ?> - <?php echo date('M d', strtotime($end_date)); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card">
                    <div class="card-body text-center">
                        <h2 class="fw-bold text-success mb-1">
                            <?php 
                            $positive = ($stats['five_star'] + $stats['four_star']);
                            $total = max($stats['total_reviews'], 1);
                            echo round(($positive / $total) * 100) . '%';
                            ?>
                        </h2>
                        <p class="text-muted mb-0">Positive (4-5★)</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm stat-card">
                    <div class="card-body text-center">
                        <h2 class="fw-bold text-info mb-1"><?php echo count($product_ratings); ?></h2>
                        <p class="text-muted mb-0">Rated Products</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CHARTS ROW with Toggles -->
        <div class="row g-4 mb-4">
            <!-- Rating Distribution Chart with Toggle -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-chart-bar me-2 text-primary"></i>
                            <span id="chartTitle">Rating Distribution</span>
                        </h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary active" id="showBarChart">
                                <i class="fas fa-chart-bar"></i> Bar
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="showPieChart">
                                <i class="fas fa-chart-pie"></i> Pie
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="showLineChart">
                                <i class="fas fa-chart-line"></i> Line
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="ratingChart" style="height: 300px; width: 100%;"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Sentiment Analysis -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-smile me-2 text-success"></i>
                            Customer Sentiment
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($sentiment)): ?>
                            <?php foreach($sentiment as $item): 
                                $total_sentiment = array_sum(array_column($sentiment, 'count'));
                                $percentage = $total_sentiment > 0 ? round(($item['count'] / $total_sentiment) * 100) : 0;
                                $bg_class = $item['sentiment'] === 'Positive' ? 'bg-success' : 
                                           ($item['sentiment'] === 'Neutral' ? 'bg-warning' : 'bg-danger');
                            ?>
                            <div class="mb-3 sentiment-item">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-bold"><?php echo $item['sentiment']; ?></span>
                                    <span><?php echo $item['count']; ?> reviews</span>
                                </div>
                                <div class="progress sentiment-progress" style="height: 25px;">
                                    <div class="progress-bar <?php echo $bg_class; ?> progress-bar-striped progress-bar-animated" 
                                         style="width: <?php echo $percentage; ?>%">
                                        <?php echo $percentage; ?>%
                                    </div>
                                </div>
                                <small class="text-muted">Avg: <?php echo number_format($item['avg_rating'], 1); ?>/5</small>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-pie fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No sentiment data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- MONTHLY TREND - Static Cards -->
        <?php if (!empty($monthly_trend)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-calendar-alt me-2 text-info"></i>
                    Monthly Trend (Last 12 Months)
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach(array_slice($monthly_trend, 0, 6) as $trend): ?>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="text-center p-3 border rounded monthly-card">
                            <div class="fw-bold text-primary"><?php echo date('M Y', strtotime($trend['month'].'-01')); ?></div>
                            <div class="h3 mb-0 text-dark"><?php echo $trend['review_count']; ?></div>
                            <small class="text-muted">reviews</small>
                            <div class="text-warning mt-2">
                                <?php echo number_format($trend['avg_rating'], 1); ?> 
                                <i class="fas fa-star fa-xs"></i>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- DAILY RATINGS - Static Table -->
        <?php if (!empty($daily_ratings)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-calendar-day me-2 text-success"></i>
                    Daily Activity
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover daily-table">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Reviews</th>
                                <th>Average Rating</th>
                                <th>Rating Stars</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach(array_slice($daily_ratings, 0, 7) as $daily): ?>
                            <tr class="daily-row">
                                <td><i class="far fa-calendar me-2 text-muted"></i><?php echo date('M d, Y', strtotime($daily['date'])); ?></td>
                                <td><span class="badge bg-primary rounded-pill"><?php echo $daily['count']; ?></span></td>
                                <td>
                                    <span class="badge bg-warning text-dark">
                                        <?php echo number_format($daily['avg_rating'], 1); ?> ★
                                    </span>
                                </td>
                                <td>
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($daily['avg_rating']) ? 'text-warning' : 'text-muted'; ?> fa-xs"></i>
                                    <?php endfor; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Product Ratings Table -->
        <?php if (!empty($product_ratings)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-boxes me-2 text-warning"></i>
                    Product Ratings
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 product-table">
                        <thead class="bg-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Reviews</th>
                                <th class="text-center">Rating</th>
                                <th>Distribution</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($product_ratings as $product): ?>
                            <tr class="product-row">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($product['image']): ?>
                                        <img src="<?php echo SITE_URL; ?>assets/images/products/<?php echo $product['image']; ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="rounded me-2 product-image" style="width: 40px; height: 40px; object-fit: cover;">
                                        <?php else: ?>
                                        <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center product-image"
                                             style="width: 40px; height: 40px;">
                                            <i class="fas fa-box text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                            <small class="text-muted">ID: <?php echo $product['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <span class="badge bg-info rounded-pill"><?php echo $product['review_count']; ?></span>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="text-warning rating-stars">
                                        <?php 
                                        $avg = $product['avg_rating'];
                                        for($i = 1; $i <= 5; $i++): 
                                            if ($i <= floor($avg)) {
                                                echo '<i class="fas fa-star"></i>';
                                            } elseif ($i == ceil($avg) && $avg - floor($avg) >= 0.5) {
                                                echo '<i class="fas fa-star-half-alt"></i>';
                                            } else {
                                                echo '<i class="far fa-star"></i>';
                                            }
                                        endfor; 
                                        ?>
                                    </div>
                                    <small class="fw-bold"><?php echo number_format($avg, 1); ?></small>
                                </td>
                                <td style="min-width: 200px;">
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
                                    <?php for($i = 5; $i >= 1; $i--): 
                                        $percentage = $total > 0 ? round(($stars[$i] / $total) * 100) : 0;
                                    ?>
                                    <div class="d-flex align-items-center mb-1 distribution-bar" style="font-size: 11px;">
                                        <span style="width: 25px;"><?php echo $i; ?>★</span>
                                        <div class="progress flex-grow-1 mx-1" style="height: 6px;">
                                            <div class="progress-bar bg-warning" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <span style="width: 30px;"><?php echo $stars[$i]; ?></span>
                                    </div>
                                    <?php endfor; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <a href="../products/edit.php?id=<?php echo $product['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary action-btn" title="Edit Product">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="reviews.php?product=<?php echo $product['id']; ?>" 
                                       class="btn btn-sm btn-outline-info action-btn" title="View Reviews">
                                        <i class="fas fa-comments"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- TIMELINE with Animation -->
        <?php if (!empty($timeline_reviews)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-history me-2 text-secondary"></i>
                    Recent Reviews Timeline
                </h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <?php foreach($timeline_reviews as $index => $review): ?>
                    <div class="timeline-item animate-timeline" style="animation-delay: <?php echo $index * 0.1; ?>s">
                        <div class="timeline-marker">
                            <div class="timeline-icon bg-<?php 
                                echo $review['rating'] >= 4 ? 'success' : 
                                     ($review['rating'] == 3 ? 'warning' : 'danger');
                            ?>">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($review['product_name']); ?></h6>
                                <small class="text-muted"><?php echo $review['review_date']; ?></small>
                            </div>
                            <div class="text-warning mb-1">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <small class="text-dark ms-2">by @<?php echo htmlspecialchars($review['username']); ?></small>
                            </div>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars(substr($review['review_text'], 0, 150))); ?>...</p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </main>
</div>

<!-- Timeline & Animation CSS -->
<style>
/* Chart Toggle Buttons */
.btn-group .btn-outline-primary {
    border-color: #dee2e6;
}
.btn-group .btn-outline-primary.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: transparent;
    color: white;
}

/* Stats Card Animations */
.stat-card {
    transition: all 0.3s ease;
    cursor: pointer;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

/* Monthly Cards */
.monthly-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
    border-radius: 8px;
}
.monthly-card:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    border-color: #667eea;
}

/* Daily Table Rows */
.daily-row {
    transition: background-color 0.3s ease;
}
.daily-row:hover {
    background-color: #f0f7ff !important;
}

/* Product Table Rows */
.product-row {
    transition: all 0.3s ease;
}
.product-row:hover {
    background-color: #f8f9fa;
    transform: translateX(5px);
}
.product-image {
    transition: transform 0.3s ease;
}
.product-row:hover .product-image {
    transform: scale(1.1);
}
.action-btn {
    transition: all 0.3s ease;
}
.action-btn:hover {
    transform: translateY(-2px);
}
.distribution-bar {
    transition: all 0.3s ease;
}
.distribution-bar:hover .progress-bar {
    filter: brightness(1.2);
}
.rating-stars i {
    transition: all 0.3s ease;
}
.rating-stars:hover i {
    transform: scale(1.2);
    margin: 0 2px;
}

/* Sentiment Animations */
.sentiment-item {
    transition: all 0.3s ease;
}
.sentiment-item:hover {
    transform: translateX(5px);
}
.sentiment-progress {
    transition: all 0.3s ease;
}
.sentiment-item:hover .progress-bar {
    filter: brightness(1.1);
}

/* Timeline Animation */
.timeline {
    position: relative;
    padding: 20px 0;
}
.timeline-item {
    position: relative;
    padding-left: 50px;
    margin-bottom: 30px;
    opacity: 0;
    animation: slideIn 0.5s ease forwards;
}
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
.timeline-item:before {
    content: '';
    position: absolute;
    left: 20px;
    top: 0;
    bottom: -30px;
    width: 2px;
    background: linear-gradient(to bottom, #667eea, #764ba2);
    animation: growLine 1s ease forwards;
    transform-origin: top;
    transform: scaleY(0);
}
@keyframes growLine {
    to {
        transform: scaleY(1);
    }
}
.timeline-item:last-child:before {
    display: none;
}
.timeline-marker {
    position: absolute;
    left: 10px;
    top: 0;
    width: 22px;
    text-align: center;
    z-index: 1;
}
.timeline-icon {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
    animation: pulseIcon 2s infinite;
}
@keyframes pulseIcon {
    0% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
    }
}
.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #667eea;
    transition: all 0.3s ease;
}
.timeline-content:hover {
    transform: translateX(5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

/* Progress Bars */
.progress {
    background-color: #e9ecef;
    border-radius: 20px;
    overflow: hidden;
}
.progress-bar {
    border-radius: 20px;
    font-size: 12px;
    line-height: 25px;
    transition: width 1s ease;
}

/* Table Styles */
.table td {
    vertical-align: middle;
}

/* Chart Container */
#ratingChart {
    min-height: 300px;
    max-height: 300px;
}
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// CHART TOGGLES - With Proper Cleanup
document.addEventListener('DOMContentLoaded', function() {
    // Time filter change
    const timeFilter = document.getElementById('timeFilter');
    if (timeFilter) {
        timeFilter.addEventListener('change', function() {
            window.location.href = 'ratings.php?time=' + this.value;
        });
    }
    
    // Chart data
    const chartData = {
        labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
        datasets: [{
            label: 'Number of Reviews',
            data: [
                <?php echo (int)($stats['five_star'] ?? 0); ?>,
                <?php echo (int)($stats['four_star'] ?? 0); ?>,
                <?php echo (int)($stats['three_star'] ?? 0); ?>,
                <?php echo (int)($stats['two_star'] ?? 0); ?>,
                <?php echo (int)($stats['one_star'] ?? 0); ?>
            ],
            backgroundColor: [
                'rgba(34, 197, 94, 0.8)',
                'rgba(59, 130, 246, 0.8)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(249, 115, 22, 0.8)',
                'rgba(239, 68, 68, 0.8)'
            ],
            borderColor: [
                '#22c55e',
                '#3b82f6',
                '#f59e0b',
                '#f97316',
                '#ef4444'
            ],
            borderWidth: 1
        }]
    };
    
    // Get chart canvas
    const ctx = document.getElementById('ratingChart')?.getContext('2d');
    if (!ctx) return;
    
    let currentChart = null;
    
    // Function to create chart with proper cleanup
    function createChart(type) {
        // PROPER CLEANUP - Destroy existing chart
        if (currentChart) {
            currentChart.destroy();
        }
        
        const options = {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: type === 'pie' || type === 'doughnut' ? true : false,
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: type === 'bar' ? 'Bar Chart - Rating Distribution' : 
                          (type === 'pie' ? 'Pie Chart - Rating Distribution' : 
                          'Line Chart - Rating Distribution'),
                    font: {
                        size: 14
                    }
                }
            }
        };
        
        if (type === 'bar') {
            currentChart = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    ...options,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            }
                        }
                    }
                }
            });
            document.getElementById('chartTitle').innerHTML = '<i class="fas fa-chart-bar me-2 text-primary"></i>Bar Chart - Rating Distribution';
        } 
        else if (type === 'pie') {
            currentChart = new Chart(ctx, {
                type: 'pie',
                data: chartData,
                options: options
            });
            document.getElementById('chartTitle').innerHTML = '<i class="fas fa-chart-pie me-2 text-primary"></i>Pie Chart - Rating Distribution';
        }
        else if (type === 'line') {
            // For line chart, we need to reorganize data
            const lineData = {
                labels: ['5★', '4★', '3★', '2★', '1★'],
                datasets: [{
                    label: 'Number of Reviews',
                    data: chartData.datasets[0].data,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            };
            currentChart = new Chart(ctx, {
                type: 'line',
                data: lineData,
                options: {
                    ...options,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                precision: 0
                            }
                        }
                    }
                }
            });
            document.getElementById('chartTitle').innerHTML = '<i class="fas fa-chart-line me-2 text-primary"></i>Line Chart - Rating Distribution';
        }
    }
    
    // Initialize with bar chart
    createChart('bar');
    
    // Chart toggle buttons with active state
    document.getElementById('showBarChart').addEventListener('click', function() {
        document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        createChart('bar');
    });
    
    document.getElementById('showPieChart').addEventListener('click', function() {
        document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        createChart('pie');
    });
    
    document.getElementById('showLineChart').addEventListener('click', function() {
        document.querySelectorAll('.btn-group .btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        createChart('line');
    });
    
    // Add animation to progress bars
    setTimeout(() => {
        document.querySelectorAll('.progress-bar').forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    }, 500);
});
</script>

<?php require_once '../../includes/footer.php'; ?>