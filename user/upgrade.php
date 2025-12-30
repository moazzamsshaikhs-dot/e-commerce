<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Admins cannot upgrade plans';
    redirect('admin/dashboard.php');
}

$page_title = 'Upgrade Plan';
require_once '../includes/header.php';

// Get subscription plans
$plans = getSubscriptionPlans();
$current_plan = $_SESSION['subscription_plan'];

// Handle plan upgrade
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = sanitize($_POST['plan_id']);
    
    try {
        $db = getDB();
        $user_id = $_SESSION['user_id'];
        
        // Get plan details
        $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_active = 1");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch();
        
        if ($plan) {
            // Calculate expiry date
            $expiry_date = date('Y-m-d', strtotime('+' . $plan['duration_days'] . ' days'));
            
            // Update user subscription
            $stmt = $db->prepare("
                UPDATE users 
                SET subscription_plan = ?, subscription_expiry = ? 
                WHERE id = ?
            ");
            $stmt->execute([$plan['name'], $expiry_date, $user_id]);
            
            // Update session
            $_SESSION['subscription_plan'] = $plan['name'];
            
            // Log activity
            logUserActivity($user_id, 'plan_upgrade', 'Upgraded to ' . $plan['name'] . ' plan');
            
            $_SESSION['success'] = 'Successfully upgraded to ' . $plan['name'] . ' plan!';
            redirect('dashboard.php');
        }
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Upgrade failed: ' . $e->getMessage();
    }
}

// Log upgrade page access
logUserActivity($_SESSION['user_id'], 'upgrade_page', 'Accessed upgrade page');
?>

<!-- Dashboard Layout -->
<div class="dashboard-container">
    <!-- Include Sidebar -->
    <?php include '../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <div class="dashboard-header bg-white shadow-sm p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Upgrade Your Plan</h1>
                    <p class="text-muted mb-0">Choose the perfect plan for your needs</p>
                </div>
                <div>
                    <span class="badge bg-<?php 
                        echo $current_plan == 'premium' ? 'warning' : 
                             ($current_plan == 'business' ? 'danger' : 'secondary'); 
                    ?>">
                        <i class="fas fa-crown me-1"></i> 
                        Current: <?php echo ucfirst($current_plan); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Plans -->
        <div class="row g-4 justify-content-center">
            <?php foreach($plans as $plan): ?>
                <?php 
                $is_current = (strtolower($plan['name']) === $current_plan);
                $is_free = ($plan['price'] == 0);
                $features = json_decode($plan['features'] ?? '[]', true);
                ?>
                
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100 <?php echo $is_current ? 'border-primary border-2' : ''; ?>">
                        <div class="card-body p-4">
                            <!-- Plan Header -->
                            <div class="text-center mb-4">
                                <div class="avatar-lg mx-auto mb-3 bg-<?php 
                                    echo $plan['name'] == 'Premium Plan' ? 'warning' : 
                                         ($plan['name'] == 'Business Plan' ? 'danger' : 'secondary'); 
                                ?> bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center"
                                     style="width: 80px; height: 80px;">
                                    <i class="fas fa-<?php 
                                        echo $plan['name'] == 'Premium Plan' ? 'crown' : 
                                             ($plan['name'] == 'Business Plan' ? 'building' : 'user'); 
                                    ?> fa-2x text-<?php 
                                        echo $plan['name'] == 'Premium Plan' ? 'warning' : 
                                             ($plan['name'] == 'Business Plan' ? 'danger' : 'secondary'); 
                                    ?>"></i>
                                </div>
                                <h4 class="mb-1"><?php echo $plan['name']; ?></h4>
                                <div class="mb-3">
                                    <span class="h2">$<?php echo number_format($plan['price'], 2); ?></span>
                                    <span class="text-muted">/<?php echo $plan['duration_days'] == 9999 ? 'Lifetime' : 'month'; ?></span>
                                </div>
                                
                                <?php if ($is_current): ?>
                                    <span class="badge bg-primary">Current Plan</span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Plan Description -->
                            <p class="text-muted text-center mb-4"><?php echo $plan['description']; ?></p>
                            
                            <!-- Features -->
                            <ul class="list-unstyled mb-4">
                                <?php if (is_array($features)): ?>
                                    <?php foreach($features as $feature): ?>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <?php echo $feature; ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            
                            <!-- Action Button -->
                            <div class="text-center mt-4">
                                <?php if ($is_current): ?>
                                    <button class="btn btn-outline-primary w-100" disabled>
                                        <i class="fas fa-check me-2"></i> Current Plan
                                    </button>
                                <?php elseif ($is_free): ?>
                                    <button class="btn btn-outline-secondary w-100" disabled>
                                        Free Plan
                                    </button>
                                <?php else: ?>
                                    <form method="POST" class="d-inline-block w-100">
                                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                        <button type="submit" class="btn btn-<?php 
                                            echo $plan['name'] == 'Premium Plan' ? 'warning' : 'danger'; 
                                        ?> w-100">
                                            <i class="fas fa-arrow-up me-2"></i> Upgrade Now
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Plan Footer -->
                        <div class="card-footer bg-transparent border-0 pt-0">
                            <div class="text-center">
                                <small class="text-muted">
                                    <?php echo $plan['duration_days'] ?> days access
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Comparison Table -->
        <div class="card border-0 shadow-sm mt-5">
            <div class="card-header bg-white">
                <h5 class="mb-0">Plan Comparison</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th class="text-center">Free</th>
                                <th class="text-center">Premium</th>
                                <th class="text-center">Business</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Number of Products</td>
                                <td class="text-center">5</td>
                                <td class="text-center">50</td>
                                <td class="text-center">Unlimited</td>
                            </tr>
                            <tr>
                                <td>Customer Support</td>
                                <td class="text-center">Email Only</td>
                                <td class="text-center">Priority</td>
                                <td class="text-center">24/7 Phone</td>
                            </tr>
                            <tr>
                                <td>Analytics Dashboard</td>
                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>API Access</td>
                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>Custom Domain</td>
                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                                <td class="text-center"><i class="fas fa-check text-success"></i></td>
                            </tr>
                            <tr>
                                <td>Price</td>
                                <td class="text-center">$0</td>
                                <td class="text-center">$9.99/month</td>
                                <td class="text-center">$29.99/month</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Payment Methods -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Payment Methods</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-4 text-center">
                        <div class="border rounded p-4">
                            <i class="fab fa-cc-stripe fa-3x text-primary mb-3"></i>
                            <h6>Credit/Debit Card</h6>
                            <p class="text-muted small">Visa, MasterCard, American Express</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="border rounded p-4">
                            <i class="fab fa-paypal fa-3x text-info mb-3"></i>
                            <h6>PayPal</h6>
                            <p class="text-muted small">Fast and secure payment</p>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="border rounded p-4">
                            <i class="fas fa-university fa-3x text-success mb-3"></i>
                            <h6>Bank Transfer</h6>
                            <p class="text-muted small">Direct bank transfer</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once '../includes/footer.php'; ?>