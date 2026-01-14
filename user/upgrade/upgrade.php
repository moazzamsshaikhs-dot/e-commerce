<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is not admin
if ($_SESSION['user_type'] === 'admin') {
    $_SESSION['error'] = 'Access denied. User dashboard only.';
    redirect(SITE_URL . 'admin/dashboard.php');
}

$page_title = 'Upgrade Plan';
require_once '../../includes/header.php';

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get subscription plans
$stmt = $db->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price ASC");
$stmt->execute();
$plans = $stmt->fetchAll();

// Get user's current subscription
$stmt = $db->prepare("SELECT subscription_plan, subscription_expiry FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();

// Log activity
logUserActivity($user_id, 'upgrade_page', 'Accessed upgrade page');
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>
    
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
                        echo $user_info['subscription_plan'] == 'premium' ? 'warning' : 
                             ($user_info['subscription_plan'] == 'business' ? 'danger' : 'secondary'); 
                    ?> fs-6">
                        Current: <?php echo ucfirst($user_info['subscription_plan']); ?> Plan
                    </span>
                </div>
            </div>
        </div>

        <!-- Current Plan Status -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-2"><?php echo ucfirst($user_info['subscription_plan']); ?> Plan</h4>
                        <p class="text-muted mb-3">
                            <?php if ($user_info['subscription_expiry']): ?>
                                Your plan renews on <?php echo date('F d, Y', strtotime($user_info['subscription_expiry'])); ?>
                            <?php else: ?>
                                Lifetime access
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($user_info['subscription_plan'] !== 'business'): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#comparePlansModal">
                                <i class="fas fa-chart-bar me-2"></i> Compare Plans
                            </button>
                        <?php else: ?>
                            <span class="text-success">
                                <i class="fas fa-check-circle me-2"></i> You have the highest plan
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pricing Plans -->
        <div class="row g-4">
            <?php foreach ($plans as $plan): 
                $is_current_plan = $plan['slug'] === $user_info['subscription_plan'] . '-plan';
                $features = json_decode($plan['features'] ?? '[]', true);
            ?>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100 <?php echo $plan['is_popular'] ? 'border-primary border-2' : ''; ?>">
                        <?php if ($plan['is_popular']): ?>
                            <div class="card-header bg-primary text-white text-center py-3">
                                <span class="badge bg-white text-primary px-3 py-2">
                                    <i class="fas fa-crown me-2"></i> Most Popular
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column p-4">
                            <?php if ($is_current_plan): ?>
                                <span class="badge bg-success position-absolute" style="top: 15px; right: 15px;">
                                    Current Plan
                                </span>
                            <?php endif; ?>
                            
                            <div class="text-center mb-4">
                                <h3 class="card-title"><?php echo $plan['name']; ?></h3>
                                <div class="price display-4 fw-bold my-3">
                                    <?php if ($plan['price'] == 0): ?>
                                        FREE
                                    <?php else: ?>
                                        $<?php echo number_format($plan['price'], 2); ?>
                                        <small class="text-muted fs-6">/month</small>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted"><?php echo $plan['description']; ?></p>
                            </div>
                            
                            <ul class="list-unstyled mb-4">
                                <?php if (is_array($features)): ?>
                                    <?php foreach ($features as $feature): ?>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>
                                            <?php echo htmlspecialchars($feature); ?>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            
                            <div class="mt-auto">
                                <?php if ($is_current_plan): ?>
                                    <button class="btn btn-outline-primary w-100" disabled>
                                        <i class="fas fa-check me-2"></i> Current Plan
                                    </button>
                                <?php else: ?>
                                    <?php if ($plan['price'] == 0 && $user_info['subscription_plan'] !== 'free'): ?>
                                        <button class="btn btn-outline-secondary w-100" 
                                                onclick="downgradePlan('<?php echo $plan['slug']; ?>')">
                                            <i class="fas fa-download me-2"></i> Downgrade
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary w-100" 
                                                onclick="upgradePlan('<?php echo $plan['slug']; ?>', <?php echo $plan['price']; ?>)">
                                            <i class="fas fa-arrow-up me-2"></i> 
                                            <?php echo $user_info['subscription_plan'] === 'free' ? 'Upgrade Now' : 'Switch Plan'; ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- FAQ Section -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h4 class="mb-0">Frequently Asked Questions</h4>
            </div>
            <div class="card-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                Can I change my plan at any time?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, you can upgrade or downgrade your plan at any time. When upgrading, you'll get immediate access to new features. When downgrading, changes take effect at the end of your billing cycle.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                What payment methods do you accept?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We accept all major credit cards (Visa, MasterCard, American Express), PayPal, and bank transfers.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Is there a free trial?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes! All paid plans come with a 14-day free trial. No credit card required to start your trial.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Compare Plans Modal -->
<div class="modal fade" id="comparePlansModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Compare All Plans</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Features</th>
                                <?php foreach ($plans as $plan): ?>
                                    <th class="text-center">
                                        <?php echo $plan['name']; ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo $plan['price'] == 0 ? 'FREE' : '$' . number_format($plan['price'], 2) . '/mo'; ?>
                                        </small>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Maximum Products</td>
                                <?php foreach ($plans as $plan): ?>
                                    <td class="text-center">
                                        <?php echo $plan['slug'] === 'free-plan' ? '5' : 
                                              ($plan['slug'] === 'premium-plan' ? '50' : 'Unlimited'); ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td>Priority Support</td>
                                <?php foreach ($plans as $plan): ?>
                                    <td class="text-center">
                                        <i class="fas fa-<?php echo $plan['slug'] === 'free-plan' ? 'times text-danger' : 'check text-success'; ?>"></i>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <td>Analytics Dashboard</td>
                                <?php foreach ($plans as $plan): ?>
                                    <td class="text-center">
                                        <i class="fas fa-<?php echo $plan['slug'] === 'free-plan' ? 'times text-danger' : 'check text-success'; ?>"></i>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function upgradePlan(planSlug, price) {
    if (confirm(`Upgrade to ${planSlug} plan for $${price}/month?`)) {
        // Show loading
        showToast('Processing your request...', 'info');
        
        fetch('upgrade-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'upgrade',
                plan_slug: planSlug
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Plan upgraded successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showToast(data.message || 'Error upgrading plan', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error', 'error');
        });
    }
}

function downgradePlan(planSlug) {
    if (confirm('Are you sure you want to downgrade to free plan?')) {
        fetch('upgrade-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'downgrade',
                plan_slug: planSlug
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Plan downgraded successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                showToast(data.message || 'Error downgrading plan', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Network error', 'error');
        });
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.top = '20px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<style>
.price {
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.accordion-button:not(.collapsed) {
    background-color: rgba(37, 117, 252, 0.1);
    color: #2575fc;
}
</style>

<?php require_once '../../includes/footer.php'; ?>