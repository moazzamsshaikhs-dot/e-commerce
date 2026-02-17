<?php
// admin/vendors/help/knowledge-base.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor dashboard only.';
    redirect(SITE_URL . 'index.php');
}

// Knowledge base articles (enhanced with more details)
$knowledge_base = [
    'getting-started' => [
        'title' => ' Getting Started as a Vendor',
        'category' => 'Account',
        'icon' => 'rocket',
        'color' => 'primary',
        'views' => 1245,
        'likes' => 89,
        'updated' => '2 days ago',
        'read_time' => 5,
        'content' => '
            <div class="welcome-section bg-primary text-white p-4 rounded-3 mb-4">
                <h4 class="mb-3"><i class="fas fa-store-alt me-2"></i> Welcome to Our Vendor Platform!</h4>
                <p class="mb-0">Start your journey to successful selling with these simple steps</p>
            </div>
            
            <div class="steps-timeline mb-5">
                <div class="step-item">
                    <div class="step-number bg-primary text-white">1</div>
                    <div class="step-content">
                        <h5>Complete Your Profile</h5>
                        <p>Fill in all vendor details including business information, contact details, and store policies</p>
                        <div class="step-progress">
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-success" style="width: 25%"></div>
                            </div>
                            <small class="text-muted">Step 1 of 5</small>
                        </div>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number bg-info text-white">2</div>
                    <div class="step-content">
                        <h5>Configure Store Settings</h5>
                        <p>Set up shipping methods, tax rates, and payment preferences</p>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number bg-warning text-white">3</div>
                    <div class="step-content">
                        <h5>Add Your First Product</h5>
                        <p>Upload high-quality images and write compelling descriptions</p>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number bg-secondary text-white">4</div>
                    <div class="step-content">
                        <h5>Get Verified</h5>
                        <p>Submit required documents for vendor verification (24-48 hours)</p>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number bg-success text-white">5</div>
                    <div class="step-content">
                        <h5>Start Selling!</h5>
                        <p>Your products go live after approval - start promoting your store</p>
                    </div>
                </div>
            </div>
            
            <div class="quick-tips bg-light p-4 rounded-3">
                <h5><i class="fas fa-lightbulb text-warning me-2"></i> Pro Tips for New Vendors:</h5>
                <div class="row g-3 mt-2">
                    <div class="col-md-6">
                        <div class="tip-card p-3 bg-white rounded-3">
                            <i class="fas fa-camera text-primary fa-2x mb-2"></i>
                            <h6>High-Quality Images</h6>
                            <small class="text-muted">Products with 3+ images sell 2x faster</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tip-card p-3 bg-white rounded-3">
                            <i class="fas fa-tag text-success fa-2x mb-2"></i>
                            <h6>Competitive Pricing</h6>
                            <small class="text-muted">Research market prices before listing</small>
                        </div>
                    </div>
                </div>
            </div>
        ',
        'related' => ['profile-setup', 'product-creation']
    ],
    
    'profile-setup' => [
        'title' => ' Setting Up Your Vendor Profile',
        'category' => 'Account',
        'icon' => 'user',
        'color' => 'success',
        'views' => 987,
        'likes' => 67,
        'updated' => '5 days ago',
        'read_time' => 4,
        'content' => '
            <div class="profile-showcase mb-4">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="avatar-preview text-center p-4 bg-light rounded-3">
                            <div class="avatar-circle mx-auto mb-3">
                                <i class="fas fa-store fa-3x text-primary"></i>
                            </div>
                            <h6>Store Avatar</h6>
                            <span class="badge bg-primary">Recommended 200x200px</span>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="profile-stats bg-white p-4 rounded-3 border">
                            <h5 class="mb-3"><i class="fas fa-chart-line me-2 text-primary"></i> Complete Profile Benefits</h5>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <div class="stat-item text-center p-3 bg-light rounded-3">
                                        <div class="display-6 text-success">+30%</div>
                                        <small>More Sales</small>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="stat-item text-center p-3 bg-light rounded-3">
                                        <div class="display-6 text-info">+45%</div>
                                        <small>Customer Trust</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <h5 class="mb-3"><i class="fas fa-clipboard-check me-2 text-primary"></i> Required Information</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="info-card p-3 border rounded-3 h-100">
                        <i class="fas fa-building text-primary mb-2"></i>
                        <h6>Business Details</h6>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Store name</li>
                            <li>Business address</li>
                            <li>Tax ID (optional)</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card p-3 border rounded-3 h-100">
                        <i class="fas fa-address-card text-success mb-2"></i>
                        <h6>Contact Info</h6>
                        <ul class="small text-muted ps-3 mb-0">
                            <li>Email address</li>
                            <li>Phone number</li>
                            <li>Social media links</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="verification-progress bg-white p-4 rounded-3 border">
                <h6 class="mb-3"><i class="fas fa-shield-alt me-2 text-warning"></i> Verification Status</h6>
                <div class="progress mb-3" style="height: 25px;">
                    <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" style="width: 60%">
                        3 of 5 steps completed
                    </div>
                </div>
                <div class="d-flex justify-content-between text-muted small">
                    <span><i class="fas fa-check-circle text-success"></i> Email</span>
                    <span><i class="fas fa-check-circle text-success"></i> Phone</span>
                    <span><i class="fas fa-spinner text-warning"></i> Documents</span>
                    <span><i class="far fa-circle text-secondary"></i> Business</span>
                </div>
            </div>
        ',
        'related' => ['getting-started', 'store-settings']
    ],
    
    'product-creation' => [
        'title' => ' Creating and Managing Products',
        'category' => 'Products',
        'icon' => 'box',
        'color' => 'warning',
        'views' => 1567,
        'likes' => 123,
        'updated' => '1 week ago',
        'read_time' => 7,
        'content' => '
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Did you know?</strong> Products with professional photos sell 3x faster!
            </div>
            
            <div class="image-guidelines mb-5">
                <h5 class="mb-3"><i class="fas fa-camera-retro me-2 text-primary"></i> Image Requirements</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="guideline-card text-center p-3 border rounded-3 bg-light">
                            <div class="display-4 mb-2">📸</div>
                            <h6>Resolution</h6>
                            <small class="text-muted">800x800px minimum</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="guideline-card text-center p-3 border rounded-3 bg-light">
                            <div class="display-4 mb-2">🎨</div>
                            <h6>Background</h6>
                            <small class="text-muted">Pure white (#FFFFFF)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="guideline-card text-center p-3 border rounded-3 bg-light">
                            <div class="display-4 mb-2">📏</div>
                            <h6>Format</h6>
                            <small class="text-muted">JPG or PNG (max 5MB)</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="product-showcase mb-5">
                <h5 class="mb-3"><i class="fas fa-star me-2 text-warning"></i> Best Practice Examples</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="example-card bg-white p-4 rounded-3 border">
                            <div class="d-flex gap-3">
                                <div class="example-good">
                                    <div class="bg-success bg-opacity-10 p-3 rounded-3">
                                        <i class="fas fa-check-circle text-success fa-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <h6> Good Description</h6>
                                    <p class="small text-muted mb-0">
                                        "Premium cotton t-shirt, 100% organic, available in 5 colors. 
                                        Machine washable, pre-shrunk fabric, sizes S-XXL."
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="example-card bg-white p-4 rounded-3 border">
                            <div class="d-flex gap-3">
                                <div class="example-bad">
                                    <div class="bg-danger bg-opacity-10 p-3 rounded-3">
                                        <i class="fas fa-times-circle text-danger fa-2x"></i>
                                    </div>
                                </div>
                                <div>
                                    <h6> Bad Description</h6>
                                    <p class="small text-muted mb-0">
                                        "Nice shirt, good quality, fits well, buy now!"
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="pricing-tips bg-white p-4 rounded-3 border">
                <h5 class="mb-3"><i class="fas fa-chart-pie me-2 text-primary"></i> Pricing Strategy</h5>
                <canvas id="pricingChart" height="200"></canvas>
            </div>
        ',
        'related' => ['inventory-management', 'product-approval']
    ],
    
    'inventory-management' => [
        'title' => ' Inventory Management Tips',
        'category' => 'Products',
        'icon' => 'chart-line',
        'color' => 'info',
        'views' => 876,
        'likes' => 54,
        'updated' => '3 days ago',
        'read_time' => 6,
        'content' => '
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Low Stock Alert!</strong> Set up notifications when stock falls below 10 units.
            </div>
            
            <div class="inventory-dashboard mb-5">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="stats-card bg-primary text-white p-3 rounded-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small>Total Products</small>
                                    <h3 class="mb-0">245</h3>
                                </div>
                                <i class="fas fa-boxes fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-success text-white p-3 rounded-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small>In Stock</small>
                                    <h3 class="mb-0">189</h3>
                                </div>
                                <i class="fas fa-check-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-warning text-white p-3 rounded-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small>Low Stock</small>
                                    <h3 class="mb-0">42</h3>
                                </div>
                                <i class="fas fa-exclamation-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card bg-danger text-white p-3 rounded-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <small>Out of Stock</small>
                                    <h3 class="mb-0">14</h3>
                                </div>
                                <i class="fas fa-times-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <h5 class="mb-3"><i class="fas fa-clipboard-list me-2 text-primary"></i> Inventory Checklist</h5>
            <div class="checklist mb-4">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check1" checked disabled>
                    <label class="form-check-label" for="check1">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Regular stock counts (weekly)
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check2" checked disabled>
                    <label class="form-check-label" for="check2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Set reorder points
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="check3">
                    <label class="form-check-label" for="check3">
                        <i class="far fa-circle me-2"></i>
                        ABC analysis
                    </label>
                </div>
            </div>
            
            <div class="reorder-calculator bg-light p-4 rounded-3">
                <h6><i class="fas fa-calculator me-2 text-primary"></i> Reorder Point Calculator</h6>
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Daily Sales</label>
                        <input type="number" class="form-control" id="dailySales" value="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Lead Time (days)</label>
                        <input type="number" class="form-control" id="leadTime" value="5">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Safety Stock</label>
                        <input type="number" class="form-control" id="safetyStock" value="20">
                    </div>
                </div>
                <div class="mt-3 p-3 bg-white rounded-3">
                    <strong>Reorder Point:</strong> 
                    <span class="h4 text-primary" id="reorderPoint">70</span> units
                </div>
            </div>
        ',
        'related' => ['product-creation', 'sales-reports']
    ],
    
    'order-management' => [
        'title' => ' Processing Orders and Shipping',
        'category' => 'Orders',
        'icon' => 'truck',
        'color' => 'danger',
        'views' => 2345,
        'likes' => 178,
        'updated' => '1 day ago',
        'read_time' => 8,
        'content' => '
            <div class="order-timeline mb-5">
                <h5 class="mb-4"><i class="fas fa-clock me-2 text-primary"></i> Order Lifecycle</h5>
                
                <div class="timeline-wrapper">
                    <div class="timeline-step completed">
                        <div class="step-icon bg-success">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="step-content">
                            <h6>Order Placed</h6>
                            <small class="text-muted">Customer completes purchase</small>
                        </div>
                    </div>
                    
                    <div class="timeline-step active">
                        <div class="step-icon bg-primary">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <div class="step-content">
                            <h6>Processing</h6>
                            <small class="text-muted">Prepare items for shipping</small>
                        </div>
                    </div>
                    
                    <div class="timeline-step">
                        <div class="step-icon bg-warning">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="step-content">
                            <h6>Shipped</h6>
                            <small class="text-muted">Add tracking number</small>
                        </div>
                    </div>
                    
                    <div class="timeline-step">
                        <div class="step-icon bg-info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="step-content">
                            <h6>Delivered</h6>
                            <small class="text-muted">Order completed</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="shipping-calculator bg-white p-4 rounded-3 border mb-4">
                <h6><i class="fas fa-truck me-2 text-primary"></i> Shipping Calculator</h6>
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" id="weight" value="1">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Distance (km)</label>
                        <input type="number" class="form-control" id="distance" value="100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Shipping Method</label>
                        <select class="form-select" id="shippingMethod">
                            <option value="standard">Standard (3-5 days)</option>
                            <option value="express">Express (1-2 days)</option>
                            <option value="overnight">Overnight</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="calculateShipping()">
                        <i class="fas fa-calculator me-2"></i> Calculate
                    </button>
                    <span class="ms-3 h5" id="shippingCost">Estimated cost: $12.50</span>
                </div>
            </div>
            
            <div class="packaging-tips">
                <h6><i class="fas fa-box me-2 text-success"></i> Packaging Checklist</h6>
                <div class="row g-3 mt-2">
                    <div class="col-md-3">
                        <div class="tip-box text-center p-3 border rounded-3">
                            <i class="fas fa-box text-primary fa-2x mb-2"></i>
                            <small>Right-sized box</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="tip-box text-center p-3 border rounded-3">
                            <i class="fas fa-shield-alt text-success fa-2x mb-2"></i>
                            <small>Bubble wrap</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="tip-box text-center p-3 border rounded-3">
                            <i class="fas fa-tape text-warning fa-2x mb-2"></i>
                            <small>Packing tape</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="tip-box text-center p-3 border rounded-3">
                            <i class="fas fa-tag text-info fa-2x mb-2"></i>
                            <small>Shipping label</small>
                        </div>
                    </div>
                </div>
            </div>
        ',
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base - Vendor Dashboard</title>
    <!-- Load CSS once, properly -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            --info-gradient: linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            background: #f8f9fa;
            padding: 20px;
        }

        .stats-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }

        .category-list .list-group-item {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .category-list .list-group-item:hover {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-left-color: #667eea;
            transform: translateX(5px);
        }
        .category-list .list-group-item.active {
            background: var(--primary-gradient);
            border-left-color: #fff;
            color: white;
        }
        .category-list .list-group-item.active .badge {
            background: white;
            color: #667eea;
        }

        .article-card {
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }
        .article-card:hover {
            transform: translateX(5px);
            border-left: 4px solid #667eea;
            background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
        }
        .article-card.active {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            border-left: 4px solid #667eea;
        }

        .gradient-bg-primary { background: var(--primary-gradient); }
        .gradient-bg-success { background: var(--success-gradient); }
        .gradient-bg-info { background: var(--info-gradient); }
        .gradient-bg-warning { background: var(--warning-gradient); }
        .gradient-bg-danger { background: var(--danger-gradient); }

        .article-content {
            line-height: 1.8;
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .step-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        .step-item:hover {
            transform: translateX(10px);
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1.5rem;
        }
        .step-content { flex: 1; }

        .tip-card {
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
        }
        .tip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .timeline-wrapper {
            position: relative;
            padding: 20px 0;
        }
        .timeline-step {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            position: relative;
            padding-left: 60px;
        }
        .timeline-step:before {
            content: '';
            position: absolute;
            left: 25px;
            top: 0;
            bottom: -2rem;
            width: 2px;
            background: var(--primary-gradient);
        }
        .timeline-step:last-child:before { display: none; }
        .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            position: absolute;
            left: 0;
            transition: all 0.3s ease;
        }
        .timeline-step:hover .step-icon {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .step-content {
            flex: 1;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .print-btn {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
            color: white;
        }

        .helpfulness-badge {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 2rem;
            border-radius: 15px;
        }

        @media print {
            .no-print { display: none !important; }
            .main-content { padding: 0; }
            .article-content { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_once '../../includes/vendor-sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header bg-white shadow-sm p-4 mb-4 rounded-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h1 class="h2 mb-1 fw-bold" style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            <i class="fas fa-book me-2"></i> Knowledge Base
                        </h1>
                        <p class="text-muted mb-0">
                            <i class="fas fa-lightbulb me-1 text-warning"></i>
                            Learn, grow, and succeed with our comprehensive guides
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="support.php" class="btn btn-outline-primary">
                            <i class="fas fa-headset me-2"></i> Contact Support
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Print Page
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stats-card bg-white p-4 rounded-3 border-0 shadow-sm">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3 gradient-bg-primary">
                                <i class="fas fa-book-open text-white fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold"><?php echo count($knowledge_base); ?></h3>
                                <small class="text-muted">Total Articles</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card bg-white p-4 rounded-3 border-0 shadow-sm">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3 gradient-bg-success">
                                <i class="fas fa-eye text-white fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold">8,245</h3>
                                <small class="text-muted">Total Views</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card bg-white p-4 rounded-3 border-0 shadow-sm">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3 gradient-bg-warning">
                                <i class="fas fa-thumbs-up text-white fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold">511</h3>
                                <small class="text-muted">Helpful Votes</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card bg-white p-4 rounded-3 border-0 shadow-sm">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3 gradient-bg-danger">
                                <i class="fas fa-clock text-white fa-2x"></i>
                            </div>
                            <div>
                                <h3 class="mb-0 fw-bold">24/7</h3>
                                <small class="text-muted">Support Available</small>
                            </div>
                        </div>
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
                                    <i class="fas fa-search text-primary"></i>
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
                    <div class="mt-3 p-3 bg-light rounded-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Found <?php echo count($filtered_articles); ?> article(s) for "<?php echo htmlspecialchars($search_query); ?>"
                            <a href="knowledge-base.php" class="ms-2 text-decoration-none">
                                <i class="fas fa-times"></i> Clear search
                            </a>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Categories Sidebar -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-folder me-2 text-primary"></i>
                                Categories
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="category-list list-group list-group-flush">
                                <a href="?category=all" class="list-group-item list-group-item-action border-0 px-4 py-3 <?php echo !isset($_GET['category']) ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-th-large me-3"></i>
                                            <span class="fw-bold">All Articles</span>
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?php echo count($knowledge_base); ?></span>
                                    </div>
                                </a>
                                
                                <a href="?category=account" class="list-group-item list-group-item-action border-0 px-4 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-user me-3 text-success"></i>
                                            <span>Account & Profile</span>
                                        </div>
                                        <span class="badge bg-success rounded-pill">2</span>
                                    </div>
                                </a>
                                
                                <a href="?category=products" class="list-group-item list-group-item-action border-0 px-4 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-box me-3 text-warning"></i>
                                            <span>Products & Inventory</span>
                                        </div>
                                        <span class="badge bg-warning rounded-pill">2</span>
                                    </div>
                                </a>
                                
                                <a href="?category=orders" class="list-group-item list-group-item-action border-0 px-4 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-shopping-cart me-3 text-info"></i>
                                            <span>Orders & Shipping</span>
                                        </div>
                                        <span class="badge bg-info rounded-pill">1</span>
                                    </div>
                                </a>
                                
                                <a href="?category=payments" class="list-group-item list-group-item-action border-0 px-4 py-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-dollar-sign me-3 text-danger"></i>
                                            <span>Payments & Earnings</span>
                                        </div>
                                        <span class="badge bg-danger rounded-pill">0</span>
                                    </div>
                                </a>
                                
                                <a href="?category=marketing" class="list-group-item list-group-item-action border-0 px-4 py-3">
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

                    <!-- Featured Article -->
                    <div class="card border-0 shadow-sm mt-4 gradient-bg-primary text-white">
                        <div class="card-body p-4">
                            <h6 class="mb-3">
                                <i class="fas fa-star me-2"></i>
                                Featured Article
                            </h6>
                            <h5 class="mb-3"> Boost Your Sales</h5>
                            <p class="small opacity-75 mb-3">Learn proven strategies to increase your store's conversion rate</p>
                            <a href="?article=getting-started" class="btn btn-light btn-sm">
                                Read Now <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-link me-2 text-primary"></i>
                                Quick Links
                            </h6>
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
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-newspaper me-2 text-primary"></i>
                                Help Articles
                            </h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-sort me-1"></i> Sort By
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="?sort=recent"><i class="fas fa-clock me-2"></i> Most Recent</a></li>
                                    <li><a class="dropdown-item" href="?sort=popular"><i class="fas fa-fire me-2"></i> Most Popular</a></li>
                                    <li><a class="dropdown-item" href="?sort=title"><i class="fas fa-sort-alpha-down me-2"></i> Title A-Z</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($filtered_articles)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-search fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No articles found</h5>
                                    <p class="text-muted">Try a different search term or browse categories</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach($filtered_articles as $slug => $article): ?>
                                    <a href="?article=<?php echo $slug; ?>" 
                                       class="list-group-item list-group-item-action border-0 px-0 py-3 article-card
                                              <?php echo $selected_article == $slug ? 'active' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);">
                                                    <i class="fas fa-<?php echo $article['icon']; ?> text-primary"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo $article['title']; ?></h6>
                                                    <div class="d-flex gap-3">
                                                        <small class="text-muted">
                                                            <i class="fas fa-folder me-1"></i> <?php echo $article['category']; ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-eye me-1"></i> <?php echo number_format($article['views']); ?>
                                                        </small>
                                                        <small class="text-muted">
                                                            <i class="fas fa-clock me-1"></i> <?php echo $article['read_time']; ?> min read
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="badge bg-primary rounded-pill"><?php echo $article['likes']; ?> <i class="fas fa-thumbs-up ms-1"></i></span>
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
                        <div class="card-header bg-white py-3">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="knowledge-base.php" class="text-decoration-none">KB Home</a></li>
                                    <li class="breadcrumb-item"><a href="?category=<?php echo strtolower($article['category']); ?>" class="text-decoration-none">
                                        <?php echo $article['category']; ?>
                                    </a></li>
                                    <li class="breadcrumb-item active"><?php echo $article['title']; ?></li>
                                </ol>
                            </nav>
                        </div>
                        <div class="card-body">
                            <div class="article-content">
                                <div class="d-flex justify-content-between align-items-start mb-4">
                                    <h2 class="mb-0"><?php echo $article['title']; ?></h2>
                                    <span class="badge bg-<?php echo $article['color']; ?> p-2">
                                        <i class="fas fa-clock me-1"></i> <?php echo $article['read_time']; ?> min read
                                    </span>
                                </div>
                                
                                <div class="article-meta mb-4 p-3 bg-light rounded-3">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <i class="fas fa-eye text-primary me-2"></i> <?php echo number_format($article['views']); ?> views
                                        </div>
                                        <div class="col-md-4">
                                            <i class="fas fa-thumbs-up text-success me-2"></i> <?php echo $article['likes']; ?> found helpful
                                        </div>
                                        <div class="col-md-4">
                                            <i class="fas fa-calendar text-info me-2"></i> Updated: <?php echo $article['updated']; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="article-body">
                                    <?php echo $article['content']; ?>
                                </div>
                                
                                <!-- Article Actions -->
                                <div class="helpfulness-badge mt-5">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold mb-2">Was this article helpful?</h6>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-success" onclick="rateArticle('helpful')">
                                                    <i class="fas fa-thumbs-up me-2"></i> Yes (<?php echo $article['likes']; ?>)
                                                </button>
                                                <button type="button" class="btn btn-danger" onclick="rateArticle('not-helpful')">
                                                    <i class="fas fa-thumbs-down me-2"></i> No
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 text-md-end mt-3 mt-md-0">
                                            <button class="print-btn" onclick="printArticle()">
                                                <i class="fas fa-print me-2"></i> Print Article
                                            </button>
                                            <button class="btn btn-outline-secondary ms-2" onclick="shareArticle()">
                                                <i class="fas fa-share-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Related Articles -->
                                <?php if (!empty($article['related'])): ?>
                                <div class="mt-5 pt-4 border-top">
                                    <h6 class="fw-bold mb-3">
                                        <i class="fas fa-link me-2 text-primary"></i>
                                        Related Articles
                                    </h6>
                                    <div class="row g-3">
                                        <?php foreach($article['related'] as $related_slug): 
                                            if (isset($knowledge_base[$related_slug])):
                                                $related = $knowledge_base[$related_slug];
                                        ?>
                                        <div class="col-md-6">
                                            <div class="card border h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas fa-<?php echo $related['icon']; ?> fa-2x me-3 text-primary"></i>
                                                        <div>
                                                            <h6 class="mb-0"><?php echo $related['title']; ?></h6>
                                                            <small class="text-muted"><?php echo $related['category']; ?></small>
                                                        </div>
                                                    </div>
                                                    <a href="?article=<?php echo $related_slug; ?>" class="stretched-link"></a>
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

    <script>
    // Chart initialization - FIXED: Only initialize once
    let pricingChart = null;
    
    function initPricingChart() {
        if (!document.getElementById('pricingChart')) return;
        
        // Destroy existing chart if it exists
        if (pricingChart) {
            pricingChart.destroy();
        }
        
        const ctx = document.getElementById('pricingChart').getContext('2d');
        pricingChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Your Price',
                    data: [29, 32, 35, 33, 38, 42],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Market Average',
                    data: [30, 31, 33, 35, 36, 38],
                    borderColor: '#fda085',
                    backgroundColor: 'rgba(253, 160, 133, 0.1)',
                    tension: 0.4,
                    borderDash: [5, 5],
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // Reorder calculator
    function calculateReorder() {
        const dailySales = document.getElementById('dailySales')?.value || 10;
        const leadTime = document.getElementById('leadTime')?.value || 5;
        const safetyStock = document.getElementById('safetyStock')?.value || 20;
        const reorderPoint = (parseInt(dailySales) * parseInt(leadTime)) + parseInt(safetyStock);
        document.getElementById('reorderPoint').textContent = reorderPoint;
    }

    // Shipping calculator
    function calculateShipping() {
        const weight = document.getElementById('weight')?.value || 1;
        const distance = document.getElementById('distance')?.value || 100;
        const method = document.getElementById('shippingMethod')?.value || 'standard';
        
        let baseRate = method === 'standard' ? 5 : (method === 'express' ? 10 : 20);
        let cost = baseRate + (parseFloat(weight) * 2) + (parseFloat(distance) * 0.1);
        
        document.getElementById('shippingCost').innerHTML = `Estimated cost: $${cost.toFixed(2)}`;
    }

    // Rate article function
    function rateArticle(rating) {
        const articleTitle = '<?php echo addslashes($article['title'] ?? ''); ?>';
        
        fetch('rate-article.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ article: articleTitle, rating: rating })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Thank you for your feedback!');
            }
        });
    }

    // Print article function
    function printArticle() {
        const articleContent = document.querySelector('.article-content').innerHTML;
        const articleTitle = '<?php echo addslashes($article['title'] ?? ''); ?>';
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${articleTitle} - Knowledge Base</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; background: #f5f7fa; }
                    .print-container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 20px; }
                    .print-header { text-align: center; margin-bottom: 30px; }
                    .print-header h1 { color: #667eea; }
                    .article-body { line-height: 1.8; }
                    .print-footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class="print-container">
                    <div class="print-header">
                        <h1 class="display-5 mb-3">${articleTitle}</h1>
                        <div class="text-muted">Generated: ${new Date().toLocaleString()}</div>
                    </div>
                    <div class="article-body">${articleContent}</div>
                    <div class="print-footer">
                        <p>${SITE_NAME} Knowledge Base</p>
                    </div>
                </div>
                <script>window.onload = function() { window.print(); window.onafterprint = function() { window.close(); }; }<\\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Share article function
    function shareArticle() {
        const articleUrl = window.location.href;
        if (navigator.share) {
            navigator.share({ title: document.title, url: articleUrl }).catch(() => {
                copyToClipboard(articleUrl);
            });
        } else {
            copyToClipboard(articleUrl);
        }
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Link copied to clipboard!');
        }).catch(() => {
            prompt('Copy this link:', text);
        });
    }

    // Initialize everything when DOM is ready - FIXED: Only run once
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize chart if needed
        if (document.getElementById('pricingChart')) {
            initPricingChart();
        }
        
        // Add event listeners - remove old ones first
        const dailySales = document.getElementById('dailySales');
        const leadTime = document.getElementById('leadTime');
        const safetyStock = document.getElementById('safetyStock');
        const weight = document.getElementById('weight');
        const distance = document.getElementById('distance');
        const shippingMethod = document.getElementById('shippingMethod');
        
        if (dailySales) {
            dailySales.removeEventListener('input', calculateReorder);
            dailySales.addEventListener('input', calculateReorder);
        }
        if (leadTime) {
            leadTime.removeEventListener('input', calculateReorder);
            leadTime.addEventListener('input', calculateReorder);
        }
        if (safetyStock) {
            safetyStock.removeEventListener('input', calculateReorder);
            safetyStock.addEventListener('input', calculateReorder);
        }
        if (weight) {
            weight.removeEventListener('input', calculateShipping);
            weight.addEventListener('input', calculateShipping);
        }
        if (distance) {
            distance.removeEventListener('input', calculateShipping);
            distance.addEventListener('input', calculateShipping);
        }
        if (shippingMethod) {
            shippingMethod.removeEventListener('change', calculateShipping);
            shippingMethod.addEventListener('change', calculateShipping);
        }
        
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // FIXED: Remove any interval timers that might be running
    if (window.intervalId) {
        clearInterval(window.intervalId);
    }
    </script>
</body>
</html>
<?php require_once '../../includes/footer.php'; ?>