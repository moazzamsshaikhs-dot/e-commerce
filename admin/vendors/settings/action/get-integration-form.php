<?php
session_start();
require_once '../../../includes/config.php';
require_once '../../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$integration_id = intval($_GET['id'] ?? 0);

if ($integration_id <= 0) {
    echo '<div class="alert alert-danger">Invalid integration ID</div>';
    exit;
}

try {
    $db = getDB();
    
    // Get integration template
    $stmt = $db->prepare("SELECT * FROM integration_templates WHERE id = ?");
    $stmt->execute([$integration_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        echo '<div class="alert alert-danger">Integration template not found</div>';
        exit;
    }
    
    // Generate form based on integration type
    echo generateIntegrationForm($template);
    
} catch(Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

function generateIntegrationForm($template) {
    $html = '<div class="mb-3">';
    $html .= '<h6 class="fw-bold">Setup ' . htmlspecialchars($template['name']) . '</h6>';
    $html .= '<p class="text-muted small">' . htmlspecialchars($template['description']) . '</p>';
    $html .= '</div>';
    
    switch($template['integration_type']) {
        case 'paypal':
            $html .= '
                <div class="mb-3">
                    <label class="form-label fw-bold">PayPal Client ID *</label>
                    <input type="text" name="api_key" class="form-control" 
                           placeholder="AY...XQ" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">PayPal Client Secret *</label>
                    <input type="password" name="api_secret" class="form-control" 
                           placeholder="EC...W8" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Environment</label>
                    <select name="additional_config[environment]" class="form-select">
                        <option value="sandbox" selected>Sandbox (Testing)</option>
                        <option value="live">Live (Production)</option>
                    </select>
                </div>
                <div class="alert alert-info">
                    <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> PayPal Integration</h6>
                    <p class="mb-0 small">
                        1. Log in to your PayPal Developer account<br>
                        2. Create a new REST API app<br>
                        3. Copy your Client ID and Client Secret<br>
                        4. Set up webhooks for payment notifications
                    </p>
                </div>
            ';
            break;
            
        case 'stripe':
            $html .= '
                <div class="mb-3">
                    <label class="form-label fw-bold">Stripe Secret Key *</label>
                    <input type="password" name="api_key" class="form-control" 
                           placeholder="sk_live_..." required>
                    <small class="text-muted">Use test key (sk_test_...) for testing</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Stripe Publishable Key</label>
                    <input type="text" name="api_secret" class="form-control" 
                           placeholder="pk_live_...">
                    <small class="text-muted">Optional for frontend integrations</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Webhook Secret</label>
                    <input type="password" name="additional_config[webhook_secret]" class="form-control" 
                           placeholder="whsec_...">
                    <small class="text-muted">For Stripe webhook verification</small>
                </div>
                <div class="alert alert-info">
                    <h6 class="fw-bold"><i class="fas fa-credit-card me-2"></i> Stripe Integration</h6>
                    <p class="mb-0 small">
                        Get your API keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard → Developers → API Keys</a>
                    </p>
                </div>
            ';
            break;
            
        case 'razorpay':
            $html .= '
                <div class="mb-3">
                    <label class="form-label fw-bold">Razorpay Key ID *</label>
                    <input type="text" name="api_key" class="form-control" 
                           placeholder="rzp_test_..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Razorpay Key Secret *</label>
                    <input type="password" name="api_secret" class="form-control" 
                           placeholder="..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Webhook Secret</label>
                    <input type="password" name="additional_config[webhook_secret]" class="form-control">
                </div>
            ';
            break;
            
        case 'shiprocket':
            $html .= '
                <div class="mb-3">
                    <label class="form-label fw-bold">Shiprocket Email *</label>
                    <input type="email" name="api_key" class="form-control" 
                           placeholder="your@email.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Shiprocket Password *</label>
                    <input type="password" name="api_secret" class="form-control" required>
                </div>
            ';
            break;
            
        default:
            $html .= '
                <div class="mb-3">
                    <label class="form-label fw-bold">API Key *</label>
                    <input type="text" name="api_key" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">API Secret</label>
                    <input type="password" name="api_secret" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Configuration (JSON)</label>
                    <textarea name="additional_config[json_config]" class="form-control" rows="3" 
                              placeholder="Any additional configuration in JSON format"></textarea>
                </div>
            ';
    }
    
    return $html;
}
?>