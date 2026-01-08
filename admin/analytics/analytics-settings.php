<?php
// // admin/analytics-settings.php
// session_start();

require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . SITE_URL . 'login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // Update Google Analytics settings
        if (isset($_POST['google_analytics_id'])) {
            $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'google_analytics_id'");
            $stmt->execute([$_POST['google_analytics_id']]);
        }
        
        // Update other analytics settings
        $settings = [
            'analytics_tracking_enabled' => $_POST['tracking_enabled'] ?? '0',
            'analytics_data_retention' => $_POST['data_retention'] ?? '365',
            'analytics_auto_refresh' => $_POST['auto_refresh'] ?? '0',
            'analytics_email_reports' => $_POST['email_reports'] ?? '0',
            'analytics_report_frequency' => $_POST['report_frequency'] ?? 'weekly'
        ];
        
        foreach($settings as $key => $value) {
            $stmt = $db->prepare("
                INSERT INTO settings (setting_key, setting_value, setting_type, category) 
                VALUES (?, ?, 'text', 'analytics')
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");
            $stmt->execute([$key, $value, $value]);
        }
        
        $_SESSION['success'] = 'Analytics settings updated successfully!';
        header('Location: analytics-settings.php');
        exit();
        
    } catch(PDOException $e) {
        $_SESSION['error'] = 'Error updating settings: ' . $e->getMessage();
    }
}

// Load current settings
try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE category = 'analytics'");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Default values
    $settings = array_merge([
        'analytics_tracking_enabled' => '1',
        'analytics_data_retention' => '365',
        'analytics_auto_refresh' => '0',
        'analytics_email_reports' => '0',
        'analytics_report_frequency' => 'weekly',
        'google_analytics_id' => ''
    ], $settings);
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Error loading settings: ' . $e->getMessage();
    $settings = [];
}

$page_title = 'Analytics Settings';
require_once '../includes/header.php';
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Analytics Settings</h1>
                <p class="text-muted mb-0">Configure analytics and tracking settings</p>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success']); endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); endif; ?>
        
        <!-- Settings Form -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="POST">
                    <!-- Google Analytics -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-line me-2"></i>Google Analytics
                            </h5>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Google Analytics Tracking ID</label>
                            <input type="text" class="form-control" name="google_analytics_id" 
                                   value="<?= htmlspecialchars($settings['google_analytics_id'] ?? '') ?>" 
                                   placeholder="UA-XXXXX-Y or G-XXXXXXXXXX">
                            <small class="text-muted">Enter your Google Analytics tracking ID</small>
                        </div>
                    </div>
                    
                    <!-- Tracking Settings -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="mb-3">
                                <i class="fas fa-cog me-2"></i>Tracking Settings
                            </h5>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="tracking_enabled" value="1" 
                                       id="trackingEnabled" <?= ($settings['analytics_tracking_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="trackingEnabled">
                                    Enable Analytics Tracking
                                </label>
                            </div>
                            <small class="text-muted">Enable or disable analytics data collection</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Data Retention Period (days)</label>
                            <input type="number" class="form-control" name="data_retention" 
                                   value="<?= $settings['analytics_data_retention'] ?? '365' ?>" min="30" max="3650">
                            <small class="text-muted">How long to keep analytics data (30-3650 days)</small>
                        </div>
                    </div>
                    
                    <!-- Dashboard Settings -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="mb-3">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard Settings
                            </h5>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_refresh" value="1" 
                                       id="autoRefresh" <?= ($settings['analytics_auto_refresh'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="autoRefresh">
                                    Enable Auto-refresh
                                </label>
                            </div>
                            <small class="text-muted">Automatically refresh dashboard data every 30 seconds</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="email_reports" value="1" 
                                       id="emailReports" <?= ($settings['analytics_email_reports'] ?? '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="emailReports">
                                    Email Analytics Reports
                                </label>
                            </div>
                            <small class="text-muted">Send automated analytics reports via email</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Report Frequency</label>
                            <select class="form-select" name="report_frequency">
                                <option value="daily" <?= ($settings['analytics_report_frequency'] ?? 'weekly') == 'daily' ? 'selected' : '' ?>>Daily</option>
                                <option value="weekly" <?= ($settings['analytics_report_frequency'] ?? 'weekly') == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                <option value="monthly" <?= ($settings['analytics_report_frequency'] ?? 'weekly') == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                            </select>
                            <small class="text-muted">How often to generate analytics reports</small>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Data Management -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">
                    <i class="fas fa-database me-2"></i>Data Management
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-warning">
                            <div class="card-body">
                                <h6 class="card-title">Export Analytics Data</h6>
                                <p class="card-text small">Export all analytics data for backup or analysis</p>
                                <button class="btn btn-outline-warning btn-sm" onclick="exportAllData()">
                                    <i class="fas fa-download me-1"></i> Export All Data
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-danger">
                            <div class="card-body">
                                <h6 class="card-title">Clear Old Data</h6>
                                <p class="card-text small">Remove analytics data older than selected period</p>
                                <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#clearDataModal">
                                    <i class="fas fa-trash me-1"></i> Clear Old Data
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-info">
                            <div class="card-body">
                                <h6 class="card-title">Reset Analytics</h6>
                                <p class="card-text small">Reset all analytics counters and statistics</p>
                                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#resetAnalyticsModal">
                                    <i class="fas fa-redo me-1"></i> Reset Analytics
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Clear Data Modal -->
<div class="modal fade" id="clearDataModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Clear Old Analytics Data</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will permanently delete analytics data older than the selected period.</p>
                <div class="mb-3">
                    <label class="form-label">Delete data older than:</label>
                    <select class="form-select" id="clearPeriod">
                        <option value="30">30 days</option>
                        <option value="90">90 days</option>
                        <option value="180">6 months</option>
                        <option value="365">1 year</option>
                        <option value="all">All data</option>
                    </select>
                </div>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This action cannot be undone. Make sure you have a backup.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="clearOldData()">
                    <i class="fas fa-trash me-1"></i> Clear Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Analytics Modal -->
<div class="modal fade" id="resetAnalyticsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reset Analytics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>This will reset all analytics counters and statistics to zero.</p>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Warning: This will permanently delete all analytics data. This action cannot be undone.
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmReset">
                    <label class="form-check-label" for="confirmReset">
                        I understand this will delete all analytics data permanently
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="resetButton" disabled onclick="resetAnalytics()">
                    <i class="fas fa-redo me-1"></i> Reset Analytics
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Enable reset button when checkbox is checked
document.getElementById('confirmReset').addEventListener('change', function() {
    document.getElementById('resetButton').disabled = !this.checked;
});

// Export all data
function exportAllData() {
    window.open('export-analytics.php?format=excel&type=summary&export_all=1', '_blank');
}

// Clear old data
function clearOldData() {
    const period = document.getElementById('clearPeriod').value;
    
    fetch('analytics-actions.php?action=clear_data&period=' + period)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Data cleared successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
}

// Reset analytics
function resetAnalytics() {
    fetch('analytics-actions.php?action=reset_analytics')
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('Analytics reset successfully!');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error: ' + error);
        });
}
</script>

<?php require_once '../includes/footer.php'; ?>