<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'API & Integrations';
require_once '../../includes/header.php';

// Get vendor integration settings
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get API keys
    $stmt = $db->prepare("
        SELECT * FROM vendor_api_keys 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $api_keys = $stmt->fetchAll();
    
    // Get webhooks
    $stmt = $db->prepare("
        SELECT * FROM vendor_webhooks 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $webhooks = $stmt->fetchAll();
    
    // Get integrations
    $stmt = $db->prepare("
        SELECT * FROM vendor_integrations 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $integrations = $stmt->fetchAll();
    
    // Get integration logs
    $stmt = $db->prepare("
        SELECT * FROM vendor_integration_logs 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$vendor_id]);
    $logs = $stmt->fetchAll();
    
    // Get available integrations
    $stmt = $db->prepare("SELECT * FROM integration_templates WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $available_integrations = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $api_keys = [];
    $webhooks = [];
    $integrations = [];
    $logs = [];
    $available_integrations = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'generate_api_key') {
            $key_name = trim($_POST['key_name'] ?? '');
            $permissions = $_POST['permissions'] ?? [];
            $expiry_days = intval($_POST['expiry_days'] ?? 0);
            
            if (empty($key_name)) {
                throw new Exception('API key name is required.');
            }
            
            // Generate API key
            $api_key = 'sk_live_' . bin2hex(random_bytes(24));
            $api_secret = 'sk_' . bin2hex(random_bytes(32));
            
            // Calculate expiry date
            $expiry_date = null;
            if ($expiry_days > 0) {
                $expiry_date = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));
            }
            
            $stmt = $db->prepare("
                INSERT INTO vendor_api_keys 
                (vendor_id, key_name, api_key, api_secret, permissions, expiry_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $vendor_id, $key_name, $api_key, $api_secret,
                json_encode($permissions), $expiry_date
            ]);
            
            $_SESSION['success'] = 'API key generated successfully!';
            $_SESSION['new_api_key'] = $api_key;
            $_SESSION['new_api_secret'] = $api_secret;
            
            redirect('integrations.php');
            
        } elseif ($action === 'create_webhook') {
            $webhook_name = trim($_POST['webhook_name'] ?? '');
            $webhook_url = trim($_POST['webhook_url'] ?? '');
            $events = $_POST['events'] ?? [];
            $secret_key = trim($_POST['secret_key'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validation
            if (empty($webhook_name) || empty($webhook_url)) {
                throw new Exception('Webhook name and URL are required.');
            }
            
            if (!filter_var($webhook_url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid webhook URL format.');
            }
            
            // Generate secret key if not provided
            if (empty($secret_key)) {
                $secret_key = 'whsk_' . bin2hex(random_bytes(16));
            }
            
            $stmt = $db->prepare("
                INSERT INTO vendor_webhooks 
                (vendor_id, webhook_name, webhook_url, events, secret_key, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $vendor_id, $webhook_name, $webhook_url,
                json_encode($events), $secret_key, $is_active
            ]);
            
            $_SESSION['success'] = 'Webhook created successfully!';
            redirect('integrations.php');
            
        } elseif ($action === 'setup_integration') {
            $integration_id = intval($_POST['integration_id'] ?? 0);
            $api_key = trim($_POST['api_key'] ?? '');
            $api_secret = trim($_POST['api_secret'] ?? '');
            $additional_config = $_POST['additional_config'] ?? [];
            
            // Get integration template
            $stmt = $db->prepare("SELECT * FROM integration_templates WHERE id = ?");
            $stmt->execute([$integration_id]);
            $template = $stmt->fetch();
            
            if (!$template) {
                throw new Exception('Invalid integration template.');
            }
            
            // Check if already integrated
            $stmt = $db->prepare("SELECT id FROM vendor_integrations WHERE vendor_id = ? AND integration_type = ?");
            $stmt->execute([$vendor_id, $template['integration_type']]);
            
            if ($stmt->fetch()) {
                throw new Exception('This integration is already set up.');
            }
            
            // Validate credentials based on integration type
            $is_valid = validateIntegrationCredentials($template['integration_type'], $api_key, $api_secret);
            
            if (!$is_valid) {
                throw new Exception('Invalid integration credentials.');
            }
            
            $config = [
                'api_key' => $api_key,
                'api_secret' => $api_secret,
                'additional_config' => $additional_config
            ];
            
            $stmt = $db->prepare("
                INSERT INTO vendor_integrations 
                (vendor_id, integration_name, integration_type, config, is_active, created_at)
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $vendor_id, $template['name'], $template['integration_type'],
                json_encode($config)
            ]);
            
            $_SESSION['success'] = 'Integration set up successfully!';
            redirect('integrations.php');
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Helper function to validate integration credentials
function validateIntegrationCredentials($type, $api_key, $api_secret) {
    // In production, you would make actual API calls to validate credentials
    // This is a simplified version
    switch($type) {
        case 'paypal':
            return !empty($api_key) && strlen($api_key) > 10;
        case 'stripe':
            return strpos($api_key, 'sk_live_') === 0 || strpos($api_key, 'sk_test_') === 0;
        case 'razorpay':
            return !empty($api_key) && !empty($api_secret);
        default:
            return true;
    }
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">API & Integrations</h1>
                <p class="text-muted mb-0">Connect with third-party services and APIs</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Integration Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">API Keys</h6>
                                <h3 class="fw-bold text-primary"><?php echo count($api_keys); ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-key fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Webhooks</h6>
                                <h3 class="fw-bold text-success"><?php echo count($webhooks); ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-broadcast-tower fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Integrations</h6>
                                <h3 class="fw-bold text-warning"><?php echo count($integrations); ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-plug fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Active</h6>
                                <h3 class="fw-bold text-info">
                                    <?php
                                    $active_count = 0;
                                    foreach($integrations as $integration) {
                                        if ($integration['is_active']) $active_count++;
                                    }
                                    echo $active_count;
                                    ?>
                                </h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="fas fa-check-circle fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Integration Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="integrationTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="api-tab" data-bs-toggle="tab" 
                                data-bs-target="#api" type="button">
                            <i class="fas fa-key me-2"></i> API Keys
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="webhooks-tab" data-bs-toggle="tab" 
                                data-bs-target="#webhooks" type="button">
                            <i class="fas fa-broadcast-tower me-2"></i> Webhooks
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="services-tab" data-bs-toggle="tab" 
                                data-bs-target="#services" type="button">
                            <i class="fas fa-plug me-2"></i> Third-Party Services
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="endpoints-tab" data-bs-toggle="tab" 
                                data-bs-target="#endpoints" type="button">
                            <i class="fas fa-code me-2"></i> API Endpoints
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" 
                                data-bs-target="#logs" type="button">
                            <i class="fas fa-history me-2"></i> Integration Logs
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Integration Content -->
        <div class="tab-content" id="integrationTabContent">
            <!-- API Keys Tab -->
            <div class="tab-pane fade show active" id="api" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-key me-2"></i> API Keys Management
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#generateKeyModal">
                            <i class="fas fa-plus me-2"></i> Generate Key
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['new_api_key'])): ?>
                        <!-- New API Key Alert -->
                        <div class="alert alert-success">
                            <h6 class="fw-bold">
                                <i class="fas fa-key me-2"></i> New API Key Generated
                            </h6>
                            <p class="mb-2">Save these credentials securely. You won't be able to see the secret key again.</p>
                            <div class="bg-dark text-light p-3 rounded mb-2">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>API Key:</strong><br>
                                        <code class="text-light"><?php echo $_SESSION['new_api_key']; ?></code>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>API Secret:</strong><br>
                                        <code class="text-light"><?php echo $_SESSION['new_api_secret']; ?></code>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-outline-light btn-sm" onclick="copyApiCredentials()">
                                <i class="fas fa-copy me-2"></i> Copy Credentials
                            </button>
                            <button class="btn btn-outline-light btn-sm ms-2" onclick="downloadApiCredentials()">
                                <i class="fas fa-download me-2"></i> Download
                            </button>
                        </div>
                        <?php 
                        unset($_SESSION['new_api_key']);
                        unset($_SESSION['new_api_secret']);
                        endif; ?>
                        
                        <?php if (empty($api_keys)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-key fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No API Keys</h5>
                            <p class="text-muted">Generate API keys to connect with external services</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateKeyModal">
                                <i class="fas fa-plus me-2"></i> Generate Your First API Key
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Key Name</th>
                                        <th>API Key</th>
                                        <th>Permissions</th>
                                        <th>Created</th>
                                        <th>Expires</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($api_keys as $key): 
                                        $permissions = json_decode($key['permissions'] ?? '[]', true);
                                        $is_expired = $key['expiry_date'] && strtotime($key['expiry_date']) < time();
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($key['key_name']); ?></strong>
                                            <?php if ($key['last_used']): ?>
                                            <small class="d-block text-muted">
                                                Last used: <?php echo date('d M, H:i', strtotime($key['last_used'])); ?>
                                            </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <code class="text-truncate" style="max-width: 150px; display: inline-block;">
                                                <?php echo substr($key['api_key'], 0, 20); ?>...
                                            </code>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php 
                                                $permission_labels = [
                                                    'read' => ['label' => 'Read', 'color' => 'primary'],
                                                    'write' => ['label' => 'Write', 'color' => 'success'],
                                                    'products' => ['label' => 'Products', 'color' => 'info'],
                                                    'orders' => ['label' => 'Orders', 'color' => 'warning'],
                                                    'customers' => ['label' => 'Customers', 'color' => 'danger']
                                                ];
                                                foreach($permissions as $perm):
                                                    if (isset($permission_labels[$perm])):
                                                ?>
                                                <span class="badge bg-<?php echo $permission_labels[$perm]['color']; ?>">
                                                    <?php echo $permission_labels[$perm]['label']; ?>
                                                </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($key['created_at'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($key['expiry_date']): ?>
                                            <?php echo date('d M Y', strtotime($key['expiry_date'])); ?>
                                            <?php else: ?>
                                            <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($is_expired): ?>
                                            <span class="badge bg-danger">
                                                <i class="fas fa-times-circle me-1"></i> Expired
                                            </span>
                                            <?php elseif ($key['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i> Active
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-ban me-1"></i> Disabled
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="viewApiKey(<?php echo $key['id']; ?>)"
                                                        data-bs-toggle="modal" data-bs-target="#viewKeyModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" 
                                                        onclick="regenerateKey(<?php echo $key['id']; ?>)">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="revokeKey(<?php echo $key['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- API Security Tips -->
                        <div class="alert alert-warning mt-4">
                            <h6 class="fw-bold"><i class="fas fa-shield-alt me-2"></i> API Security Tips</h6>
                            <ul class="mb-0">
                                <li>Never share your API keys publicly</li>
                                <li>Use environment variables to store keys in production</li>
                                <li>Regenerate keys periodically</li>
                                <li>Restrict permissions to minimum required</li>
                                <li>Monitor API usage logs regularly</li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Webhooks Tab -->
            <div class="tab-pane fade" id="webhooks" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-broadcast-tower me-2"></i> Webhooks
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createWebhookModal">
                            <i class="fas fa-plus me-2"></i> Create Webhook
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($webhooks)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-broadcast-tower fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Webhooks</h5>
                            <p class="text-muted">Create webhooks to receive real-time notifications</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWebhookModal">
                                <i class="fas fa-plus me-2"></i> Create Webhook
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Webhook Name</th>
                                        <th>URL</th>
                                        <th>Events</th>
                                        <th>Status</th>
                                        <th>Last Delivery</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($webhooks as $webhook): 
                                        $events = json_decode($webhook['events'] ?? '[]', true);
                                        $last_delivery = $webhook['last_delivered'] ? 
                                            date('d M, H:i', strtotime($webhook['last_delivered'])) : 'Never';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($webhook['webhook_name']); ?></strong>
                                        </td>
                                        <td>
                                            <small class="text-truncate d-block" style="max-width: 200px;">
                                                <?php echo htmlspecialchars($webhook['webhook_url']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php 
                                                $event_labels = [
                                                    'order.created' => ['label' => 'Order Created', 'color' => 'success'],
                                                    'order.updated' => ['label' => 'Order Updated', 'color' => 'warning'],
                                                    'payment.received' => ['label' => 'Payment Received', 'color' => 'primary'],
                                                    'product.updated' => ['label' => 'Product Updated', 'color' => 'info'],
                                                    'customer.created' => ['label' => 'Customer Created', 'color' => 'danger']
                                                ];
                                                foreach($events as $event):
                                                    if (isset($event_labels[$event])):
                                                ?>
                                                <span class="badge bg-<?php echo $event_labels[$event]['color']; ?>">
                                                    <?php echo $event_labels[$event]['label']; ?>
                                                </span>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($webhook['is_active']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i> Active
                                            </span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-ban me-1"></i> Inactive
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo $last_delivery; ?></small>
                                            <?php if ($webhook['delivery_success'] === 0): ?>
                                            <span class="badge bg-danger ms-1">Failed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="testWebhook(<?php echo $webhook['id']; ?>)">
                                                    <i class="fas fa-bolt"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" 
                                                        onclick="editWebhook(<?php echo $webhook['id']; ?>)"
                                                        data-bs-toggle="modal" data-bs-target="#editWebhookModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="deleteWebhook(<?php echo $webhook['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Webhook Testing -->
                        <div class="border rounded p-3 bg-light mt-4">
                            <h6 class="fw-bold mb-3">Webhook Testing</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Test URL</label>
                                        <input type="url" class="form-control" id="testWebhookUrl" 
                                               placeholder="https://example.com/webhook">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Test Event</label>
                                        <select class="form-select" id="testWebhookEvent">
                                            <option value="order.created">Order Created</option>
                                            <option value="payment.received">Payment Received</option>
                                            <option value="product.updated">Product Updated</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button class="btn btn-outline-primary" onclick="sendTestWebhook()">
                                <i class="fas fa-paper-plane me-2"></i> Send Test Webhook
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Third-Party Services Tab -->
            <div class="tab-pane fade" id="services" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-plug me-2"></i> Third-Party Services
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Available Integrations -->
                        <h6 class="fw-bold mb-3">Available Integrations</h6>
                        <div class="row g-4">
                            <?php foreach($available_integrations as $integration): 
                                $is_setup = false;
                                foreach($integrations as $v_int) {
                                    if ($v_int['integration_type'] === $integration['integration_type']) {
                                        $is_setup = true;
                                        break;
                                    }
                                }
                            ?>
                            <div class="col-md-4">
                                <div class="card border h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="me-3">
                                                <div class="bg-<?php echo $integration['color'] ?? 'primary'; ?> 
                                                             bg-opacity-10 p-3 rounded">
                                                    <i class="fas fa-<?php echo $integration['icon'] ?? 'plug'; ?> 
                                                               fa-2x text-<?php echo $integration['color'] ?? 'primary'; ?>"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($integration['name']); ?></h5>
                                                <p class="text-muted small mb-0"><?php echo htmlspecialchars($integration['description']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block">Features:</small>
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                <?php 
                                                $features = json_decode($integration['features'] ?? '[]', true);
                                                foreach($features as $feature):
                                                ?>
                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($feature); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <?php if ($is_setup): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i> Connected
                                            </span>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="manageIntegration('<?php echo $integration['integration_type']; ?>')">
                                                <i class="fas fa-cog"></i> Manage
                                            </button>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-plug me-1"></i> Not Connected
                                            </span>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="setupIntegration(<?php echo $integration['id']; ?>)"
                                                    data-bs-toggle="modal" data-bs-target="#setupIntegrationModal">
                                                <i class="fas fa-link me-1"></i> Connect
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Active Integrations -->
                        <?php if (!empty($integrations)): ?>
                        <div class="mt-5">
                            <h6 class="fw-bold mb-3">Active Integrations</h6>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Connected Since</th>
                                            <th>Last Sync</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($integrations as $integration): 
                                            $config = json_decode($integration['config'] ?? '{}', true);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($integration['integration_name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?php echo ucfirst($integration['integration_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($integration['is_active']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle me-1"></i> Active
                                                </span>
                                                <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-exclamation-triangle me-1"></i> Inactive
                                                </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($integration['created_at'])); ?>
                                            </td>
                                            <td>
                                                <?php echo $integration['last_sync'] ? date('d M, H:i', strtotime($integration['last_sync'])) : 'Never'; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="syncIntegration(<?php echo $integration['id']; ?>)">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <button class="btn btn-outline-warning" 
                                                            onclick="editIntegration(<?php echo $integration['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="disconnectIntegration(<?php echo $integration['id']; ?>)">
                                                        <i class="fas fa-unlink"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- API Endpoints Tab -->
            <div class="tab-pane fade" id="endpoints" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-code me-2"></i> API Endpoints & Documentation
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- API Documentation -->
                        <div class="mb-5">
                            <h6 class="fw-bold mb-3">API Documentation</h6>
                            <div class="border rounded p-4 bg-light">
                                <p>Base URL: <code><?php echo SITE_URL; ?>api/v1/</code></p>
                                <p>All API requests must include the following headers:</p>
                                <pre class="bg-dark text-light p-3 rounded"><code>Authorization: Bearer YOUR_API_KEY
Content-Type: application/json</code></pre>
                            </div>
                        </div>
                        
                        <!-- Available Endpoints -->
                        <h6 class="fw-bold mb-3">Available Endpoints</h6>
                        <div class="accordion" id="endpointsAccordion">
                            <!-- Products Endpoints -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#productsEndpoints">
                                        <i class="fas fa-box me-2"></i> Products API
                                    </button>
                                </h2>
                                <div id="productsEndpoints" class="accordion-collapse collapse show" 
                                     data-bs-parent="#endpointsAccordion">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Method</th>
                                                        <th>Endpoint</th>
                                                        <th>Description</th>
                                                        <th>Permissions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td><span class="badge bg-primary">GET</span></td>
                                                        <td><code>/products</code></td>
                                                        <td>List all products</td>
                                                        <td><span class="badge bg-info">read</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><span class="badge bg-success">POST</span></td>
                                                        <td><code>/products</code></td>
                                                        <td>Create new product</td>
                                                        <td><span class="badge bg-success">write</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><span class="badge bg-primary">GET</span></td>
                                                        <td><code>/products/{id}</code></td>
                                                        <td>Get product details</td>
                                                        <td><span class="badge bg-info">read</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><span class="badge bg-warning">PUT</span></td>
                                                        <td><code>/products/{id}</code></td>
                                                        <td>Update product</td>
                                                        <td><span class="badge bg-success">write</span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><span class="badge bg-danger">DELETE</span></td>
                                                        <td><code>/products/{id}</code></td>
                                                        <td>Delete product</td>
                                                        <td><span class="badge bg-success">write</span></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Orders Endpoints -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#ordersEndpoints">
                                        <i class="fas fa-shopping-cart me-2"></i> Orders API
                                    </button>
                                </h2>
                                <div id="ordersEndpoints" class="accordion-collapse collapse" 
                                     data-bs-parent="#endpointsAccordion">
                                    <div class="accordion-body">
                                        <!-- Similar table for orders endpoints -->
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Customers Endpoints -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#customersEndpoints">
                                        <i class="fas fa-users me-2"></i> Customers API
                                    </button>
                                </h2>
                                <div id="customersEndpoints" class="accordion-collapse collapse" 
                                     data-bs-parent="#endpointsAccordion">
                                    <div class="accordion-body">
                                        <!-- Similar table for customers endpoints -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- API Testing -->
                        <div class="border rounded p-3 mt-4">
                            <h6 class="fw-bold mb-3">API Testing</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Method</label>
                                        <select class="form-select" id="testMethod">
                                            <option value="GET">GET</option>
                                            <option value="POST">POST</option>
                                            <option value="PUT">PUT</option>
                                            <option value="DELETE">DELETE</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Endpoint</label>
                                        <input type="text" class="form-control" id="testEndpoint" 
                                               value="/products">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <button class="btn btn-primary w-100" onclick="testApiEndpoint()">
                                            <i class="fas fa-bolt me-2"></i> Test
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Request Body (JSON)</label>
                                <textarea class="form-control" id="testRequestBody" rows="3"></textarea>
                            </div>
                            <div>
                                <label class="form-label">Response</label>
                                <pre class="bg-dark text-light p-3 rounded" id="testResponse" style="min-height: 200px;">{
  "status": "ready"
}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Integration Logs Tab -->
            <div class="tab-pane fade" id="logs" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2"></i> Integration Logs
                        </h5>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()">
                                <i class="fas fa-sync-alt me-2"></i> Refresh
                            </button>
                            <button class="btn btn-sm btn-outline-danger ms-2" onclick="clearLogs()">
                                <i class="fas fa-trash me-2"></i> Clear Logs
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Log Filters -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Integration Type</label>
                                    <select class="form-select" id="logType">
                                        <option value="">All Types</option>
                                        <option value="api">API</option>
                                        <option value="webhook">Webhook</option>
                                        <option value="payment">Payment Gateway</option>
                                        <option value="shipping">Shipping</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" id="logStatus">
                                        <option value="">All Status</option>
                                        <option value="success">Success</option>
                                        <option value="error">Error</option>
                                        <option value="warning">Warning</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">From Date</label>
                                    <input type="date" class="form-control" id="logFromDate" 
                                           value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">To Date</label>
                                    <input type="date" class="form-control" id="logToDate" 
                                           value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logs Table -->
                        <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-history fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Logs</h5>
                            <p class="text-muted">Integration logs will appear here</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Integration</th>
                                        <th>Action</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>Duration</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($logs as $log): 
                                        $status_colors = [
                                            'success' => 'success',
                                            'error' => 'danger',
                                            'warning' => 'warning',
                                            'info' => 'info'
                                        ];
                                        $status_icons = [
                                            'success' => 'check-circle',
                                            'error' => 'times-circle',
                                            'warning' => 'exclamation-triangle',
                                            'info' => 'info-circle'
                                        ];
                                    ?>
                                    <tr>
                                        <td>
                                            <small><?php echo date('d M, H:i:s', strtotime($log['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo ucfirst($log['integration_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($log['action']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_colors[$log['status']] ?? 'secondary'; ?>">
                                                <i class="fas fa-<?php echo $status_icons[$log['status']] ?? 'question-circle'; ?> me-1"></i>
                                                <?php echo ucfirst($log['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-truncate d-block" style="max-width: 200px;">
                                                <?php echo htmlspecialchars($log['message']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small><?php echo $log['duration_ms'] ?? 'N/A'; ?> ms</small>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" 
                                                    onclick="viewLogDetails(<?php echo $log['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Log Statistics -->
                        <div class="row mt-4 g-4">
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-2">Total Logs</h6>
                                        <h3 class="fw-bold text-primary"><?php echo count($logs); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-2">Success Rate</h6>
                                        <h3 class="fw-bold text-success">
                                            <?php
                                            $success_count = 0;
                                            foreach($logs as $log) {
                                                if ($log['status'] === 'success') $success_count++;
                                            }
                                            echo count($logs) > 0 ? round(($success_count / count($logs)) * 100) . '%' : '0%';
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-2">Errors</h6>
                                        <h3 class="fw-bold text-danger">
                                            <?php
                                            $error_count = 0;
                                            foreach($logs as $log) {
                                                if ($log['status'] === 'error') $error_count++;
                                            }
                                            echo $error_count;
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="text-muted mb-2">Avg Duration</h6>
                                        <h3 class="fw-bold text-warning">
                                            <?php
                                            $total_duration = 0;
                                            $duration_count = 0;
                                            foreach($logs as $log) {
                                                if ($log['duration_ms']) {
                                                    $total_duration += $log['duration_ms'];
                                                    $duration_count++;
                                                }
                                            }
                                            echo $duration_count > 0 ? round($total_duration / $duration_count) . ' ms' : 'N/A';
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Generate API Key Modal -->
<div class="modal fade" id="generateKeyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-key me-2"></i> Generate New API Key
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="generateKeyForm">
                <input type="hidden" name="action" value="generate_api_key">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Key Name *</label>
                        <input type="text" name="key_name" class="form-control" 
                               placeholder="e.g., Production Key, Development Key" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Permissions *</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="read" id="permRead" checked>
                                    <label class="form-check-label" for="permRead">
                                        <i class="fas fa-eye me-1"></i> Read Access
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="write" id="permWrite">
                                    <label class="form-check-label" for="permWrite">
                                        <i class="fas fa-edit me-1"></i> Write Access
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="products" id="permProducts" checked>
                                    <label class="form-check-label" for="permProducts">
                                        <i class="fas fa-box me-1"></i> Products
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="orders" id="permOrders" checked>
                                    <label class="form-check-label" for="permOrders">
                                        <i class="fas fa-shopping-cart me-1"></i> Orders
                                    </label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="customers" id="permCustomers">
                                    <label class="form-check-label" for="permCustomers">
                                        <i class="fas fa-users me-1"></i> Customers
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Expiry (Optional)</label>
                        <select name="expiry_days" class="form-select">
                            <option value="0" selected>Never expire</option>
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                            <option value="90">90 days</option>
                            <option value="365">1 year</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Security Notice</h6>
                        <p class="mb-0 small">
                            Save the API key and secret securely. You won't be able to see the secret key again.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i> Generate Key
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Webhook Modal -->
<div class="modal fade" id="createWebhookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-broadcast-tower me-2"></i> Create Webhook
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="createWebhookForm">
                <input type="hidden" name="action" value="create_webhook">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Webhook Name *</label>
                                <input type="text" name="webhook_name" class="form-control" 
                                       placeholder="e.g., Order Notifications, Payment Webhook" required>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Webhook URL *</label>
                                <input type="url" name="webhook_url" class="form-control" 
                                       placeholder="https://your-server.com/webhook" required>
                                <small class="text-muted">Must be HTTPS for security</small>
                            </div>
                        </div>
                        
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Events to Listen For *</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="events[]" value="order.created" id="eventOrderCreated" checked>
                                            <label class="form-check-label" for="eventOrderCreated">
                                                Order Created
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="events[]" value="order.updated" id="eventOrderUpdated">
                                            <label class="form-check-label" for="eventOrderUpdated">
                                                Order Updated
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="events[]" value="payment.received" id="eventPaymentReceived" checked>
                                            <label class="form-check-label" for="eventPaymentReceived">
                                                Payment Received
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="events[]" value="product.updated" id="eventProductUpdated">
                                            <label class="form-check-label" for="eventProductUpdated">
                                                Product Updated
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="events[]" value="customer.created" id="eventCustomerCreated">
                                            <label class="form-check-label" for="eventCustomerCreated">
                                                Customer Created
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Secret Key</label>
                                <input type="text" name="secret_key" class="form-control" 
                                       placeholder="Leave empty to generate automatically">
                                <small class="text-muted">Used to verify webhook signatures</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">&nbsp;</label>
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="webhookActive" checked>
                                    <label class="form-check-label fw-bold" for="webhookActive">
                                        Enable Webhook
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Webhook Payload Example -->
                    <div class="alert alert-info">
                        <h6 class="fw-bold"><i class="fas fa-code me-2"></i> Webhook Payload Example</h6>
                        <pre class="mb-0 small"><code>{
  "event": "order.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "order_id": "ORD-12345",
    "customer_email": "customer@example.com",
    "total_amount": 99.99
  }
}</code></pre>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Create Webhook
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Setup Integration Modal -->
<div class="modal fade" id="setupIntegrationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plug me-2"></i> Setup Integration
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="setupIntegrationForm">
                <input type="hidden" name="action" value="setup_integration">
                <input type="hidden" name="integration_id" id="integrationId">
                
                <div class="modal-body">
                    <div id="integrationFormContent">
                        <!-- Dynamic content will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-link me-2"></i> Connect
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Integration JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const triggerTabList = [].slice.call(document.querySelectorAll('#integrationTab button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Form submissions
    const forms = ['generateKeyForm', 'createWebhookForm', 'setupIntegrationForm'];
    forms.forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                submitForm(this);
            });
        }
    });
});

function copyApiCredentials() {
    const apiKey = '<?php echo $_SESSION['new_api_key'] ?? ''; ?>';
    const apiSecret = '<?php echo $_SESSION['new_api_secret'] ?? ''; ?>';
    const text = `API Key: ${apiKey}\nAPI Secret: ${apiSecret}`;
    
    navigator.clipboard.writeText(text).then(function() {
        alert('Credentials copied to clipboard!');
    });
}

function downloadApiCredentials() {
    const apiKey = '<?php echo $_SESSION['new_api_key'] ?? ''; ?>';
    const apiSecret = '<?php echo $_SESSION['new_api_secret'] ?? ''; ?>';
    const text = `API Key: ${apiKey}\nAPI Secret: ${apiSecret}`;
    
    const blob = new Blob([text], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'api-credentials.txt';
    a.click();
    window.URL.revokeObjectURL(url);
}

function viewApiKey(keyId) {
    // AJAX call to get key details
    fetch('action/get-api-key.php?id=' + keyId)
    .then(response => response.json())
    .then(data => {
        // Show key details modal
        // Implementation depends on your modal structure
    });
}

function regenerateKey(keyId) {
    if (confirm('Regenerate this API key? Existing integrations using this key will stop working.')) {
        fetch('action/regenerate-api-key.php', {
            method: 'POST',
            body: new FormData(document.getElementById('regenerateForm_' + keyId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function revokeKey(keyId) {
    if (confirm('Revoke this API key? This action cannot be undone.')) {
        fetch('action/revoke-api-key.php', {
            method: 'POST',
            body: new FormData(document.getElementById('revokeForm_' + keyId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function testWebhook(webhookId) {
    if (confirm('Send a test webhook?')) {
        fetch('action/test-webhook.php', {
            method: 'POST',
            body: new FormData(document.getElementById('testForm_' + webhookId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test webhook sent successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function editWebhook(webhookId) {
    // AJAX call to get webhook details
    fetch('action/get-webhook.php?id=' + webhookId)
    .then(response => response.json())
    .then(data => {
        // Populate edit form
        // Implementation depends on your modal structure
    });
}

function deleteWebhook(webhookId) {
    if (confirm('Delete this webhook?')) {
        fetch('action/delete-webhook.php', {
            method: 'POST',
            body: new FormData(document.getElementById('deleteForm_' + webhookId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function sendTestWebhook() {
    const url = document.getElementById('testWebhookUrl').value;
    const event = document.getElementById('testWebhookEvent').value;
    
    if (!url) {
        alert('Please enter a test URL.');
        return;
    }
    
    fetch('action/send-test-webhook.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            url: url,
            event: event
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Test webhook sent successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function setupIntegration(integrationId) {
    document.getElementById('integrationId').value = integrationId;
    
    // Load integration form
    fetch('action/get-integration-form.php?id=' + integrationId)
    .then(response => response.text())
    .then(html => {
        document.getElementById('integrationFormContent').innerHTML = html;
    });
}

function manageIntegration(integrationType) {
    // Redirect to integration management page
    window.location.href = 'integration-' + integrationType + '.php';
}

function syncIntegration(integrationId) {
    if (confirm('Sync data with this integration?')) {
        fetch('action/sync-integration.php', {
            method: 'POST',
            body: new FormData(document.getElementById('syncForm_' + integrationId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sync completed successfully!');
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function editIntegration(integrationId) {
    // AJAX call to get integration details
    fetch('action/get-integration.php?id=' + integrationId)
    .then(response => response.json())
    .then(data => {
        // Show edit modal
        // Implementation depends on your modal structure
    });
}

function disconnectIntegration(integrationId) {
    if (confirm('Disconnect this integration?')) {
        fetch('action/disconnect-integration.php', {
            method: 'POST',
            body: new FormData(document.getElementById('disconnectForm_' + integrationId))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function testApiEndpoint() {
    const method = document.getElementById('testMethod').value;
    const endpoint = document.getElementById('testEndpoint').value;
    const body = document.getElementById('testRequestBody').value;
    
    // In production, make actual API call
    // This is a simplified example
    const response = {
        "status": "success",
        "data": {
            "message": "Test request successful",
            "method": method,
            "endpoint": endpoint,
            "timestamp": new Date().toISOString()
        }
    };
    
    document.getElementById('testResponse').textContent = JSON.stringify(response, null, 2);
}

function refreshLogs() {
    const type = document.getElementById('logType').value;
    const status = document.getElementById('logStatus').value;
    const fromDate = document.getElementById('logFromDate').value;
    const toDate = document.getElementById('logToDate').value;
    
    fetch('action/refresh-logs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            type: type,
            status: status,
            from_date: fromDate,
            to_date: toDate
        })
    })
    .then(response => response.json())
    .then(data => {
        // Reload logs section
        // Implementation depends on your structure
    });
}

function clearLogs() {
    if (confirm('Clear all integration logs? This action cannot be undone.')) {
        fetch('action/clear-logs.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            }
        });
    }
}

function viewLogDetails(logId) {
    // AJAX call to get log details
    fetch('action/get-log-details.php?id=' + logId)
    .then(response => response.json())
    .then(data => {
        // Show log details modal
        // Implementation depends on your modal structure
    });
}

function submitForm(form) {
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}
</script>

<style>
/* Integration card styles */
.card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
}

/* Badge colors */
.bg-opacity-10 {
    opacity: 0.1;
}

/* Code blocks */
pre code {
    font-size: 0.9em;
}
</style>

<?php require_once '../../includes/footer.php'; ?>