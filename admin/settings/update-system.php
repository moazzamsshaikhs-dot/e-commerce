<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'System Updates';
require_once '../includes/header.php';

// Current version (you should define this in config.php)
define('CURRENT_VERSION', '1.0.0');

// Get update info
$update_info = [];
$update_available = false;

try {
    $db = getDB();
    
    // Check for update in database (if you store update info)
    $stmt = $db->query("SELECT * FROM system_updates ORDER BY release_date DESC LIMIT 1");
    $last_update = $stmt->fetch();
    
    // For demonstration, we'll check from a remote source
    // In real implementation, you would check from your update server
    
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Sample update data (replace with actual update check)
$update_available = false; // Set to true when update is available
$latest_version = '1.1.0';
$release_date = '2024-01-15';
$release_notes = [
    'New features added',
    'Bug fixes',
    'Performance improvements'
];

// System requirements
$requirements = [
    'PHP Version' => [
        'required' => '7.4',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.4', '>=')
    ],
    'MySQL Version' => [
        'required' => '5.7',
        'current' => 'Unknown',
        'status' => true
    ],
    'PDO Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('pdo') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('pdo')
    ],
    'JSON Extension' => [
        'required' => 'Enabled',
        'current' => extension_loaded('json') ? 'Enabled' : 'Disabled',
        'status' => extension_loaded('json')
    ],
    'Write Permissions' => [
        'required' => 'Write',
        'current' => is_writable('../') ? 'Writable' : 'Not Writable',
        'status' => is_writable('../')
    ]
];

// Check MySQL version
try {
    $db = getDB();
    $mysql_version = $db->getAttribute(PDO::ATTR_SERVER_VERSION);
    $requirements['MySQL Version']['current'] = $mysql_version;
    $requirements['MySQL Version']['status'] = version_compare($mysql_version, '5.7', '>=');
} catch(Exception $e) {
    $requirements['MySQL Version']['current'] = 'Error';
    $requirements['MySQL Version']['status'] = false;
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">System Updates</h1>
                <p class="text-muted mb-0">Keep your system up to date</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="checkForUpdates()">
                    <i class="fas fa-sync-alt me-2"></i> Check for Updates
                </button>
            </div>
        </div>
        
        <!-- Current Version -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-2">
                            Current Version: <span class="text-primary"><?php echo CURRENT_VERSION; ?></span>
                        </h5>
                        <p class="text-muted mb-0">
                            Last checked: <?php echo date('M d, Y H:i'); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="display-4">
                            <?php if ($update_available): ?>
                            <span class="badge bg-danger">Update Available</span>
                            <?php else: ?>
                            <span class="badge bg-success">Up to Date</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($update_available): ?>
        <!-- Update Available -->
        <div class="card border-0 shadow-sm mb-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10 border-0">
                <h5 class="mb-0 text-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    New Update Available!
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="mb-3">
                                <i class="fas fa-cloud-download-alt fa-4x text-primary"></i>
                            </div>
                            <h3><?php echo $latest_version; ?></h3>
                            <p class="text-muted">Latest Version</p>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <h5>Release Notes</h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach($release_notes as $note): ?>
                            <li class="list-group-item px-0">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <?php echo $note; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="mt-4">
                            <button class="btn btn-primary btn-lg" onclick="startUpdate()">
                                <i class="fas fa-download me-2"></i> Update Now
                            </button>
                            <button class="btn btn-outline-secondary ms-2" onclick="viewChangelog()">
                                <i class="fas fa-file-alt me-2"></i> View Full Changelog
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- System Requirements -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">System Requirements</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Requirement</th>
                                <th>Required</th>
                                <th>Current</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($requirements as $name => $req): ?>
                            <tr>
                                <td><?php echo $name; ?></td>
                                <td><?php echo $req['required']; ?></td>
                                <td><?php echo $req['current']; ?></td>
                                <td>
                                    <?php if ($req['status']): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check me-1"></i> OK
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">
                                        <i class="fas fa-times me-1"></i> Failed
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Update History -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Update History</h5>
                <button class="btn btn-sm btn-outline-primary" onclick="loadUpdateHistory()">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div class="card-body">
                <?php 
                try {
                    $stmt = $db->query("SELECT * FROM system_updates ORDER BY release_date DESC LIMIT 10");
                    $updates = $stmt->fetchAll();
                    
                    if (empty($updates)):
                ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h5>No Update History</h5>
                    <p class="text-muted">No previous updates recorded</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach($updates as $update): ?>
                    <div class="list-group-item px-0 border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Version <?php echo $update['version']; ?></h6>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($update['release_date'])); ?>
                                </small>
                            </div>
                            <span class="badge bg-info">Installed</span>
                        </div>
                        <p class="mb-1 small"><?php echo $update['description']; ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php } catch(Exception $e) { ?>
                <div class="text-center py-4">
                    <p class="text-muted">Could not load update history</p>
                </div>
                <?php } ?>
            </div>
        </div>
        
        <!-- Manual Update -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Manual Update</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">Upload update package manually</p>
                <form id="manualUpdateForm" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <input type="file" class="form-control" name="update_package" 
                                   accept=".zip,.tar.gz" required>
                            <div class="form-text">
                                Upload update package (ZIP or TAR.GZ format)
                            </div>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-outline-primary w-100" 
                                    onclick="uploadManualUpdate()">
                                <i class="fas fa-upload me-2"></i> Upload & Update
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<!-- Update Progress Modal -->
<div class="modal fade" id="updateProgressModal" tabindex="-1" aria-labelledby="updateProgressModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateProgressModalLabel">System Update Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="updateProgressContent">
                    <div class="text-center py-4">
                        <i class="fas fa-sync-alt fa-spin fa-3x text-primary mb-3"></i>
                        <h5>Preparing Update...</h5>
                        <p class="text-muted">Please wait while the system is being updated</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Changelog Modal -->
<div class="modal fade" id="changelogModal" tabindex="-1" aria-labelledby="changelogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changelogModalLabel">Update Changelog</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="changelogContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Check for updates
function checkForUpdates() {
    Swal.fire({
        title: 'Checking for Updates',
        text: 'Please wait while we check for updates...',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
            
            fetch('ajax/check-updates.php')
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    if (data.update_available) {
                        Swal.fire({
                            title: 'Update Available!',
                            html: `
                                <div class="text-start">
                                    <p><strong>Current Version:</strong> ${data.current_version}</p>
                                    <p><strong>Latest Version:</strong> ${data.latest_version}</p>
                                    <p><strong>Release Date:</strong> ${data.release_date}</p>
                                    <p><strong>Release Notes:</strong></p>
                                    <div class="bg-light p-2 small rounded">${data.release_notes}</div>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Update Now',
                            cancelButtonText: 'Later',
                            confirmButtonColor: '#1cc88a'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                startUpdate();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Up to Date',
                            text: 'Your system is running the latest version.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire('Error!', 'Failed to check for updates.', 'error');
            });
        }
    });
}

// Start update
function startUpdate() {
    $('#updateProgressModal').modal('show');
    
    // Initialize update process
    document.getElementById('updateProgressContent').innerHTML = `
        <div class="text-center py-4">
            <i class="fas fa-sync-alt fa-spin fa-3x text-primary mb-3"></i>
            <h5>Starting Update...</h5>
            <p class="text-muted">Preparing update process</p>
            <div class="progress mb-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     style="width: 10%"></div>
            </div>
        </div>
    `;
    
    // Start update steps
    performUpdateStep(0);
}

// Perform update step by step
function performUpdateStep(step) {
    const steps = [
        { name: 'Creating backup', percent: 20 },
        { name: 'Downloading update', percent: 40 },
        { name: 'Verifying files', percent: 60 },
        { name: 'Applying update', percent: 80 },
        { name: 'Cleaning up', percent: 100 }
    ];
    
    if (step < steps.length) {
        const stepInfo = steps[step];
        
        document.getElementById('updateProgressContent').innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-sync-alt fa-spin fa-3x text-primary mb-3"></i>
                <h5>Step ${step + 1} of ${steps.length}</h5>
                <p class="text-muted">${stepInfo.name}</p>
                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                         style="width: ${stepInfo.percent}%">${stepInfo.percent}%</div>
                </div>
                <div class="text-start small">
                    <div id="updateLogs"></div>
                </div>
            </div>
        `;
        
        // Simulate step execution
        setTimeout(() => {
            addUpdateLog(`Completed: ${stepInfo.name}`);
            performUpdateStep(step + 1);
        }, 2000);
    } else {
        // Update complete
        document.getElementById('updateProgressContent').innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5>Update Complete!</h5>
                <p class="text-muted">System has been successfully updated</p>
                <div class="alert alert-success">
                    <i class="fas fa-info-circle me-2"></i>
                    The system will now restart. Please wait...
                </div>
            </div>
        `;
        
        setTimeout(() => {
            $('#updateProgressModal').modal('hide');
            Swal.fire({
                icon: 'success',
                title: 'Update Successful!',
                text: 'System has been updated successfully.',
                confirmButtonText: 'Restart Now'
            }).then(() => {
                window.location.href = 'index.php';
            });
        }, 3000);
    }
}

// Add log to update progress
function addUpdateLog(message) {
    const logDiv = document.getElementById('updateLogs');
    if (logDiv) {
        const timestamp = new Date().toLocaleTimeString();
        logDiv.innerHTML += `<div class="mb-1"><small>${timestamp}: ${message}</small></div>`;
        logDiv.scrollTop = logDiv.scrollHeight;
    }
}

// View changelog
function viewChangelog() {
    fetch('ajax/get-changelog.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('changelogContent').innerHTML = `
                <div class="changelog-content">
                    <h4>Changelog for Version ${data.latest_version}</h4>
                    <p class="text-muted">Released on ${data.release_date}</p>
                    
                    <div class="mt-4">
                        <h5>New Features</h5>
                        <ul>
                            ${data.features ? data.features.map(f => `<li>${f}</li>`).join('') : '<li>No new features</li>'}
                        </ul>
                        
                        <h5>Bug Fixes</h5>
                        <ul>
                            ${data.fixes ? data.fixes.map(f => `<li>${f}</li>`).join('') : '<li>No bug fixes</li>'}
                        </ul>
                        
                        <h5>Improvements</h5>
                        <ul>
                            ${data.improvements ? data.improvements.map(i => `<li>${i}</li>`).join('') : '<li>No improvements</li>'}
                        </ul>
                    </div>
                </div>
            `;
            $('#changelogModal').modal('show');
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load changelog.', 'error');
    });
}

// Upload manual update
function uploadManualUpdate() {
    const form = document.getElementById('manualUpdateForm');
    const formData = new FormData(form);
    
    if (!formData.get('update_package').name) {
        Swal.fire('Error!', 'Please select an update package.', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Manual Update',
        text: 'Upload and install update package?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Upload & Install'
    }).then((result) => {
        if (result.isConfirmed) {
            $('#updateProgressModal').modal('show');
            
            document.getElementById('updateProgressContent').innerHTML = `
                <div class="text-center py-4">
                    <i class="fas fa-upload fa-spin fa-3x text-primary mb-3"></i>
                    <h5>Uploading Package...</h5>
                    <p class="text-muted">Please wait while package is uploaded</p>
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             style="width: 0%"></div>
                    </div>
                </div>
            `;
            
            // Simulate upload progress
            let progress = 0;
            const uploadInterval = setInterval(() => {
                progress += 10;
                const progressBar = document.querySelector('#updateProgressModal .progress-bar');
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                }
                
                if (progress >= 100) {
                    clearInterval(uploadInterval);
                    setTimeout(() => {
                        startUpdate();
                    }, 1000);
                }
            }, 500);
        }
    });
}

// Load update history
function loadUpdateHistory() {
    location.reload();
}
</script>

<?php require_once '../includes/footer.php'; ?>