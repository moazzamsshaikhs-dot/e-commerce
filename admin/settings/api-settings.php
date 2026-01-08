<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'API Configuration';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    // Create api_keys table if not exists
    $table_exists = $db->query("SHOW TABLES LIKE 'api_keys'")->fetch();
    if (!$table_exists) {
        $db->exec("CREATE TABLE api_keys (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            api_key VARCHAR(64) UNIQUE NOT NULL,
            api_secret VARCHAR(64) NOT NULL,
            user_id INT,
            permissions TEXT,
            rate_limit INT DEFAULT 100,
            requests_today INT DEFAULT 0,
            total_requests INT DEFAULT 0,
            last_used DATETIME,
            expires_at DATETIME,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");
        
        // Create api_logs table
        $db->exec("CREATE TABLE api_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            api_key_id INT,
            endpoint VARCHAR(255),
            method VARCHAR(10),
            status_code INT,
            response_time FLOAT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
        )");
    }
    
    // Get API keys
    $stmt = $db->query("SELECT ak.*, u.username, u.email 
                        FROM api_keys ak 
                        LEFT JOIN users u ON ak.user_id = u.id 
                        ORDER BY ak.created_at DESC");
    $api_keys = $stmt->fetchAll();
    
    // Get API statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_keys,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_keys,
        SUM(requests_today) as today_requests,
        SUM(total_requests) as total_requests
        FROM api_keys";
    $stats = $db->query($stats_sql)->fetch();
    
    // Get recent API logs
    $logs_sql = "SELECT al.*, ak.name as api_name 
                 FROM api_logs al 
                 JOIN api_keys ak ON al.api_key_id = ak.id 
                 ORDER BY al.created_at DESC 
                 LIMIT 10";
    $recent_logs = $db->query($logs_sql)->fetchAll();
    
    // Get users for dropdown
    $users = $db->query("SELECT id, username, email FROM users WHERE user_type = 'admin' OR user_type = 'user' ORDER BY username")->fetchAll();
    
} catch(PDOException $e) {
    $error = 'Error loading API settings: ' . $e->getMessage();
    $api_keys = [];
    $stats = ['total_keys' => 0, 'active_keys' => 0, 'today_requests' => 0, 'total_requests' => 0];
    $recent_logs = [];
    $users = [];
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">API Configuration</h1>
                <p class="text-muted mb-0">Manage API keys and access</p>
            </div>
            <div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApiKeyModal">
                    <i class="fas fa-plus me-2"></i> Add API Key
                </button>
            </div>
        </div>
        
        <!-- API Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo $stats['total_keys']; ?></h3>
                        <p class="text-muted mb-0">Total API Keys</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold text-success"><?php echo $stats['active_keys']; ?></h3>
                        <p class="text-muted mb-0">Active Keys</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold text-primary"><?php echo $stats['today_requests']; ?></h3>
                        <p class="text-muted mb-0">Today's Requests</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo $stats['total_requests']; ?></h3>
                        <p class="text-muted mb-0">Total Requests</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- API Keys List -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    API Keys (<?php echo count($api_keys); ?>)
                </h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshApiKeys()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($api_keys)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-key fa-3x text-muted mb-3"></i>
                    <h5>No API Keys</h5>
                    <p class="text-muted">Create your first API key</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>API Key</th>
                                <th>User</th>
                                <th>Rate Limit</th>
                                <th>Requests</th>
                                <th>Last Used</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($api_keys as $key): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $key['name']; ?></strong><br>
                                    <small class="text-muted">Created: <?php echo date('M d, Y', strtotime($key['created_at'])); ?></small>
                                </td>
                                <td>
                                    <code><?php echo substr($key['api_key'], 0, 8); ?>...</code><br>
                                    <button class="btn btn-sm btn-outline-info btn-copy" 
                                            data-clipboard-text="<?php echo $key['api_key']; ?>"
                                            title="Copy API Key">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </td>
                                <td>
                                    <?php if ($key['username']): ?>
                                    <div><?php echo $key['username']; ?></div>
                                    <small class="text-muted"><?php echo $key['email']; ?></small>
                                    <?php else: ?>
                                    <em class="text-muted">System</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $key['rate_limit']; ?>/hour
                                </td>
                                <td>
                                    <div><?php echo $key['requests_today']; ?> today</div>
                                    <small class="text-muted"><?php echo $key['total_requests']; ?> total</small>
                                </td>
                                <td>
                                    <?php if ($key['last_used']): ?>
                                    <?php echo date('M d, H:i', strtotime($key['last_used'])); ?>
                                    <?php else: ?>
                                    <em class="text-muted">Never</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input api-key-toggle" 
                                               type="checkbox" 
                                               data-id="<?php echo $key['id']; ?>"
                                               <?php echo $key['is_active'] ? 'checked' : ''; ?>
                                               onchange="toggleApiKey(this, <?php echo $key['id']; ?>)">
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="viewApiKey(<?php echo $key['id']; ?>)"
                                                title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" 
                                                onclick="editApiKey(<?php echo $key['id']; ?>)"
                                                title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-danger" 
                                                onclick="deleteApiKey(<?php echo $key['id']; ?>)"
                                                title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent API Activity -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent API Activity</h5>
                <a href="api-logs.php" class="btn btn-sm btn-outline-primary">View All Logs</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_logs)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-list fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No recent API activity</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>API Key</th>
                                <th>Endpoint</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Response Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_logs as $log): ?>
                            <tr>
                                <td>
                                    <?php echo date('H:i:s', strtotime($log['created_at'])); ?><br>
                                    <small class="text-muted"><?php echo date('M d', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <small><?php echo $log['api_name']; ?></small>
                                </td>
                                <td>
                                    <code><?php echo $log['endpoint']; ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                    switch($log['method']) {
                                        case 'GET': echo 'primary'; break;
                                        case 'POST': echo 'success'; break;
                                        case 'PUT': echo 'warning'; break;
                                        case 'DELETE': echo 'danger'; break;
                                        default: echo 'secondary';
                                    }
                                    ?>">
                                        <?php echo $log['method']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status_color = $log['status_code'] >= 200 && $log['status_code'] < 300 ? 'success' : 
                                                  ($log['status_code'] >= 400 && $log['status_code'] < 500 ? 'warning' : 'danger');
                                    ?>
                                    <span class="badge bg-<?php echo $status_color; ?>">
                                        <?php echo $log['status_code']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo round($log['response_time'], 2); ?> ms
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- API Documentation -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">API Documentation</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title">Base URL</h6>
                                <code><?php echo SITE_URL; ?>api/v1/</code>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title">Authentication</h6>
                                <p class="small">Use API Key in header:</p>
                                <code>X-API-Key: your_api_key</code>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border h-100">
                            <div class="card-body">
                                <h6 class="card-title">Rate Limit</h6>
                                <p class="small">Default: 100 requests/hour</p>
                                <p class="small">Header: X-RateLimit-Limit</p>
                                <p class="small">Remaining: X-RateLimit-Remaining</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add API Key Modal -->
<div class="modal fade" id="addApiKeyModal" tabindex="-1" aria-labelledby="addApiKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addApiKeyModalLabel">Generate API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addApiKeyForm">
                    <div class="mb-3">
                        <label class="form-label">Key Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" required 
                               placeholder="e.g., Mobile App, Webhook, Integration">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Assigned User</label>
                        <select class="form-select" name="user_id">
                            <option value="">System (No user)</option>
                            <?php foreach($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo $user['username']; ?> (<?php echo $user['email']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rate Limit (requests/hour)</label>
                        <input type="number" class="form-control" name="rate_limit" value="100" min="1" max="10000">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expiration Date</label>
                        <input type="date" class="form-control" name="expires_at">
                        <div class="form-text">Leave empty for no expiration</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="form-control" style="height: 150px; overflow-y: auto;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="read" id="permRead" checked>
                                <label class="form-check-label" for="permRead">
                                    Read Access
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="write" id="permWrite">
                                <label class="form-check-label" for="permWrite">
                                    Write Access
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="delete" id="permDelete">
                                <label class="form-check-label" for="permDelete">
                                    Delete Access
                                </label>
                            </div>
                            <hr class="my-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="users.read" id="permUsersRead">
                                <label class="form-check-label" for="permUsersRead">
                                    Read Users
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="users.write" id="permUsersWrite">
                                <label class="form-check-label" for="permUsersWrite">
                                    Write Users
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="products.read" id="permProductsRead">
                                <label class="form-check-label" for="permProductsRead">
                                    Read Products
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="products.write" id="permProductsWrite">
                                <label class="form-check-label" for="permProductsWrite">
                                    Write Products
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="orders.read" id="permOrdersRead">
                                <label class="form-check-label" for="permOrdersRead">
                                    Read Orders
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="orders.write" id="permOrdersWrite">
                                <label class="form-check-label" for="permOrdersWrite">
                                    Write Orders
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="generateApiKey()">Generate Key</button>
            </div>
        </div>
    </div>
</div>

<!-- View API Key Modal -->
<div class="modal fade" id="viewApiKeyModal" tabindex="-1" aria-labelledby="viewApiKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewApiKeyModalLabel">API Key Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="apiKeyDetailsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" onclick="revokeApiKey()">
                    <i class="fas fa-ban me-2"></i> Revoke Key
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/clipboard@2/dist/clipboard.min.js"></script>
<script>
let currentApiKeyId = null;
let clipboard = null;

// Initialize clipboard
document.addEventListener('DOMContentLoaded', function() {
    clipboard = new ClipboardJS('.btn-copy');
    clipboard.on('success', function(e) {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'API key copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
        e.clearSelection();
    });
});

// Toggle API key
function toggleApiKey(checkbox, apiKeyId) {
    const isActive = checkbox.checked ? 1 : 0;
    
    fetch('../ajax/settings/toggle-api-key.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            api_key_id: apiKeyId,
            is_active: isActive
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            checkbox.checked = !checkbox.checked;
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        checkbox.checked = !checkbox.checked;
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// Generate API key
function generateApiKey() {
    const form = document.getElementById('addApiKeyForm');
    const formData = new FormData(form);
    
    // Get permissions
    const permissions = [];
    form.querySelectorAll('input[name="permissions[]"]:checked').forEach(cb => {
        permissions.push(cb.value);
    });
    
    fetch('../ajax/settings/generate-api-key.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            name: formData.get('name'),
            user_id: formData.get('user_id') || null,
            rate_limit: formData.get('rate_limit'),
            expires_at: formData.get('expires_at'),
            permissions: permissions
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            $('#addApiKeyModal').modal('hide');
            
            Swal.fire({
                title: 'API Key Generated!',
                html: `
                    <div class="text-center">
                        <i class="fas fa-key fa-4x text-success mb-3"></i>
                        <p><strong>${data.name}</strong></p>
                        <div class="alert alert-info text-start">
                            <p><strong>API Key:</strong></p>
                            <code>${data.api_key}</code>
                            <p class="mt-2"><strong>API Secret:</strong></p>
                            <code>${data.api_secret}</code>
                            <div class="mt-3">
                                <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('${data.api_key}')">
                                    <i class="fas fa-copy me-1"></i> Copy Key
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('${data.api_secret}')">
                                    <i class="fas fa-copy me-1"></i> Copy Secret
                                </button>
                            </div>
                        </div>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Save these credentials now! They won't be shown again.</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'I have saved the credentials',
                cancelButtonText: 'Show again',
                confirmButtonColor: '#1cc88a'
            }).then((result) => {
                if (result.isConfirmed) {
                    location.reload();
                }
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'An error occurred.', 'error');
    });
}

// View API key
function viewApiKey(apiKeyId) {
    currentApiKeyId = apiKeyId;
    
    fetch(`../ajax/settings/get-api-key.php?id=${apiKeyId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const apiKey = data.api_key;
            const permissions = apiKey.permissions ? JSON.parse(apiKey.permissions) : [];
            
            let html = `
                <div class="mb-3">
                    <h6>Key Information</h6>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Name:</strong></td>
                            <td>${apiKey.name}</td>
                        </tr>
                        <tr>
                            <td><strong>API Key:</strong></td>
                            <td><code>${apiKey.api_key}</code></td>
                        </tr>
                        <tr>
                            <td><strong>User:</strong></td>
                            <td>${apiKey.username || 'System'}</td>
                        </tr>
                        <tr>
                            <td><strong>Rate Limit:</strong></td>
                            <td>${apiKey.rate_limit} requests/hour</td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td>
                                <span class="badge bg-${apiKey.is_active ? 'success' : 'danger'}">
                                    ${apiKey.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="mb-3">
                    <h6>Usage Statistics</h6>
                    <table class="table table-sm table-borderless">
                        <tr>
                            <td><strong>Requests Today:</strong></td>
                            <td>${apiKey.requests_today}</td>
                        </tr>
                        <tr>
                            <td><strong>Total Requests:</strong></td>
                            <td>${apiKey.total_requests}</td>
                        </tr>
                        <tr>
                            <td><strong>Last Used:</strong></td>
                            <td>${apiKey.last_used ? new Date(apiKey.last_used).toLocaleString() : 'Never'}</td>
                        </tr>
                        <tr>
                            <td><strong>Expires:</strong></td>
                            <td>${apiKey.expires_at ? new Date(apiKey.expires_at).toLocaleDateString() : 'Never'}</td>
                        </tr>
                    </table>
                </div>
            `;
            
            if (permissions.length > 0) {
                html += `
                    <div class="mb-3">
                        <h6>Permissions</h6>
                        <div class="d-flex flex-wrap">
                            ${permissions.map(perm => `<span class="badge bg-primary me-1 mb-1">${perm}</span>`).join('')}
                        </div>
                    </div>
                `;
            }
            
            document.getElementById('apiKeyDetailsContent').innerHTML = html;
            $('#viewApiKeyModal').modal('show');
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load API key details.', 'error');
    });
}

// Edit API key
function editApiKey(apiKeyId) {
    fetch(`../ajax/settings/get-api-key.php?id=${apiKeyId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const apiKey = data.api_key;
            
            Swal.fire({
                title: 'Edit API Key',
                html: `
                    <div class="text-start">
                        <div class="mb-3">
                            <label class="form-label">Key Name</label>
                            <input type="text" class="form-control" id="editName" value="${apiKey.name}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rate Limit</label>
                            <input type="number" class="form-control" id="editRateLimit" value="${apiKey.rate_limit}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiration Date</label>
                            <input type="date" class="form-control" id="editExpiresAt" 
                                   value="${apiKey.expires_at ? apiKey.expires_at.slice(0,10) : ''}">
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="editIsActive" ${apiKey.is_active ? 'checked' : ''}>
                            <label class="form-check-label">Active</label>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Save Changes',
                preConfirm: () => {
                    return {
                        name: document.getElementById('editName').value,
                        rate_limit: document.getElementById('editRateLimit').value,
                        expires_at: document.getElementById('editExpiresAt').value,
                        is_active: document.getElementById('editIsActive').checked
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = result.value;
                    formData.api_key_id = apiKeyId;
                    
                    fetch('../ajax/settings/update-api-key.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(formData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire('Error!', 'An error occurred.', 'error');
                    });
                }
            });
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load API key.', 'error');
    });
}

// Delete API key
function deleteApiKey(apiKeyId) {
    Swal.fire({
        title: 'Delete API Key',
        text: 'Are you sure you want to delete this API key?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/delete-api-key.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    api_key_id: apiKeyId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Revoke API key
function revokeApiKey() {
    if (!currentApiKeyId) return;
    
    Swal.fire({
        title: 'Revoke API Key',
        text: 'This will immediately disable the API key. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Revoke'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/revoke-api-key.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    api_key_id: currentApiKeyId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Revoked!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        $('#viewApiKeyModal').modal('hide');
                        location.reload();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'An error occurred.', 'error');
            });
        }
    });
}

// Copy to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Text copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    });
}

// Refresh API keys
function refreshApiKeys() {
    location.reload();
}
</script>

<?php require_once '../includes/footer.php'; ?>