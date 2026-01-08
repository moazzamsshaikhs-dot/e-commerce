<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect('index.php');
}

$page_title = 'Cache Management';
require_once '../includes/header.php';

// Safely check cache extensions
$cache_types = [
    'opcache' => [
        'name' => 'PHP OPcache',
        'enabled' => function_exists('opcache_get_status') || 
                    (extension_loaded('Zend OPcache') && ini_get('opcache.enable')),
        'description' => 'PHP bytecode cache for improved performance'
    ],
    'apc' => [
        'name' => 'APC Cache',
        'enabled' => function_exists('apc_cache_info') && 
                    extension_loaded('apc') && 
                    ini_get('apc.enabled'),
        'description' => 'Alternative PHP Cache for user data'
    ],
    'apcu' => [
        'name' => 'APCu Cache',
        'enabled' => function_exists('apcu_cache_info') && 
                    extension_loaded('apcu'),
        'description' => 'APC User Cache (no opcode caching)'
    ],
    'file_cache' => [
        'name' => 'File Cache',
        'enabled' => true,
        'description' => 'Application file cache'
    ],
    'database_cache' => [
        'name' => 'Database Cache',
        'enabled' => true,
        'description' => 'Database query results cache'
    ],
    'session_cache' => [
        'name' => 'Session Cache',
        'enabled' => true,
        'description' => 'User session data cache'
    ]
];

// Get cache statistics
$cache_stats = [];
$total_size = 0;

foreach ($cache_types as $key => $type) {
    if ($type['enabled']) {
        $size = 0;
        $items = 0;
        $hits = 0;
        $misses = 0;
        
        try {
            switch ($key) {
                case 'opcache':
                    if (function_exists('opcache_get_status')) {
                        $status = @opcache_get_status(false);
                        if ($status && is_array($status)) {
                            if (isset($status['memory_usage'])) {
                                $memory = $status['memory_usage'];
                                $size = ($memory['used_memory'] ?? 0) + ($memory['wasted_memory'] ?? 0);
                            }
                            if (isset($status['opcache_statistics'])) {
                                $stats = $status['opcache_statistics'];
                                $items = $stats['num_cached_scripts'] ?? 0;
                                $hits = $stats['hits'] ?? 0;
                                $misses = $stats['misses'] ?? 0;
                            }
                        }
                    }
                    break;
                    
                case 'apc':
                    if (function_exists('apc_cache_info')) {
                        $info = @apc_cache_info('user', true);
                        if ($info && is_array($info)) {
                            $size = $info['mem_size'] ?? 0;
                            $items = $info['num_entries'] ?? 0;
                            $hits = $info['num_hits'] ?? 0;
                            $misses = $info['num_misses'] ?? 0;
                        }
                    }
                    break;
                    
                case 'apcu':
                    if (function_exists('apcu_cache_info')) {
                        $info = @apcu_cache_info(true);
                        if ($info && is_array($info)) {
                            $size = $info['mem_size'] ?? 0;
                            $items = $info['num_entries'] ?? 0;
                            $hits = $info['num_hits'] ?? 0;
                            $misses = $info['num_misses'] ?? 0;
                        }
                    }
                    break;
                    
                case 'file_cache':
                    $cache_dir = '../cache/';
                    
                    if (is_dir($cache_dir)) {
                        // Use RecursiveDirectoryIterator carefully
                        try {
                            $iterator = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($cache_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::SELF_FIRST
                            );
                            
                            foreach ($iterator as $file) {
                                if ($file->isFile()) {
                                    $size += $file->getSize();
                                    $items++;
                                }
                            }
                        } catch (Exception $e) {
                            // Directory might not exist or have permissions issue
                            $size = 0;
                            $items = 0;
                        }
                    }
                    break;
                    
                default:
                    $size = 0;
                    $items = 0;
                    $hits = 0;
                    $misses = 0;
                    break;
            }
        } catch (Exception $e) {
            // Silently catch any errors
            $size = 0;
            $items = 0;
            $hits = 0;
            $misses = 0;
        }
        
        $cache_stats[$key] = [
            'size' => $size,
            'items' => $items,
            'hits' => $hits,
            'misses' => $misses
        ];
        $total_size += $size;
    }
}
?>

<div class="dashboard-container">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Cache Management</h1>
                <p class="text-muted mb-0">Manage system cache for optimal performance</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="clearAllCache()">
                    <i class="fas fa-broom me-2"></i> Clear All Cache
                </button>
            </div>
        </div>
        
        <!-- Cache Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo count($cache_stats); ?></h3>
                        <p class="text-muted mb-0">Cache Types</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="fw-bold"><?php echo formatBytes($total_size); ?></h3>
                        <p class="text-muted mb-0">Total Cache Size</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <?php
                        $total_items = 0;
                        foreach ($cache_stats as $stat) {
                            $total_items += $stat['items'];
                        }
                        ?>
                        <h3 class="fw-bold"><?php echo number_format($total_items); ?></h3>
                        <p class="text-muted mb-0">Total Items</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <?php
                        $enabled_count = 0;
                        foreach ($cache_types as $type) {
                            if ($type['enabled']) {
                                $enabled_count++;
                            }
                        }
                        ?>
                        <h3 class="fw-bold"><?php echo $enabled_count; ?>/<?php echo count($cache_types); ?></h3>
                        <p class="text-muted mb-0">Enabled Caches</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Cache Types -->
        <div class="row mb-4">
            <?php foreach($cache_types as $key => $type): 
                $stats = $cache_stats[$key] ?? ['size' => 0, 'items' => 0, 'hits' => 0, 'misses' => 0];
                $size = formatBytes($stats['size']);
                $hit_rate = ($stats['hits'] + $stats['misses']) > 0 ? 
                           round(($stats['hits'] / ($stats['hits'] + $stats['misses'])) * 100, 1) : 0;
            ?>
            <div class="col-md-6 mb-4">
                <div class="card border h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title mb-1">
                                    <?php echo $type['name']; ?>
                                    <?php if ($type['enabled']): ?>
                                    <span class="badge bg-success">Enabled</span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">Disabled</span>
                                    <?php endif; ?>
                                </h5>
                                <p class="text-muted small mb-0"><?php echo $type['description']; ?></p>
                            </div>
                            <?php if ($type['enabled']): ?>
                            <button class="btn btn-sm btn-outline-danger" 
                                    onclick="clearSpecificCache('<?php echo $key; ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="text-muted">Size:</small>
                                    <div class="fw-bold"><?php echo $size; ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="text-muted">Items:</small>
                                    <div class="fw-bold"><?php echo number_format($stats['items']); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($stats['hits'] > 0 || $stats['misses'] > 0): ?>
                        <div class="row">
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="text-muted">Hit Rate:</small>
                                    <div class="fw-bold"><?php echo $hit_rate; ?>%</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="text-muted">Total Requests:</small>
                                    <div class="fw-bold"><?php echo number_format($stats['hits'] + $stats['misses']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <?php if ($type['enabled']): ?>
                            <div class="progress" style="height: 10px;">
                                <?php 
                                $percentage = $total_size > 0 ? ($stats['size'] / $total_size) * 100 : 0;
                                ?>
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning py-2 mb-0 small">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo $type['name']; ?> is not available on your server
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Cache Control -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Cache Control</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-primary w-100" onclick="clearOpcache()">
                            <i class="fas fa-broom me-2"></i> Clear OPcache
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-success w-100" onclick="clearFileCache()">
                            <i class="fas fa-folder me-2"></i> Clear File Cache
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-info w-100" onclick="clearDatabaseCache()">
                            <i class="fas fa-database me-2"></i> Clear Database Cache
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-warning w-100" onclick="clearSessionCache()">
                            <i class="fas fa-user-clock me-2"></i> Clear Session Cache
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-secondary w-100" onclick="viewCacheSettings()">
                            <i class="fas fa-cog me-2"></i> View Cache Settings
                        </button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <button class="btn btn-outline-danger w-100" onclick="clearAllCache()">
                            <i class="fas fa-trash me-2"></i> Clear All Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Cache Settings -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Cache Configuration</h5>
            </div>
            <div class="card-body">
                <form id="cacheConfigForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cache Driver</label>
                                <select class="form-select" name="cache_driver">
                                    <option value="file" selected>File</option>
                                    <option value="database">Database</option>
                                    <?php if (function_exists('apcu_cache_info')): ?>
                                    <option value="apcu">APCu</option>
                                    <?php endif; ?>
                                    <?php if (extension_loaded('redis')): ?>
                                    <option value="redis">Redis</option>
                                    <?php endif; ?>
                                    <?php if (extension_loaded('memcached')): ?>
                                    <option value="memcached">Memcached</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Default Cache Time (seconds)</label>
                                <input type="number" class="form-control" name="cache_time" value="3600" min="0">
                                <div class="form-text">0 = never expire</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Cache Prefix</label>
                                <input type="text" class="form-control" name="cache_prefix" value="cache_">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Compression</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="compress" id="compressCache">
                                    <label class="form-check-label" for="compressCache">
                                        Compress cached data
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cache Directories</label>
                        <textarea class="form-control" name="cache_dirs" rows="3" readonly><?php 
                        $cache_dirs = [];
                        if (is_dir('../cache/')) {
                            $cache_dirs[] = '../cache/';
                        }
                        if (is_dir('../tmp/')) {
                            $cache_dirs[] = '../tmp/';
                        }
                        $temp_dir = sys_get_temp_dir();
                        if ($temp_dir) {
                            $cache_dirs[] = $temp_dir;
                        }
                        echo implode("\n", $cache_dirs);
                        ?></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary" onclick="saveCacheConfig()">
                            <i class="fas fa-save me-2"></i> Save Cache Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Cache Performance -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Cache Performance</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <canvas id="cachePerformanceChart" height="200"></canvas>
                    </div>
                    <div class="col-md-4">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>Total Cache Size</span>
                                <span class="badge bg-primary rounded-pill">
                                    <?php echo formatBytes($total_size); ?>
                                </span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>Cache Items</span>
                                <span class="badge bg-success rounded-pill">
                                    <?php 
                                    $total_items = 0;
                                    foreach ($cache_stats as $stat) {
                                        $total_items += $stat['items'];
                                    }
                                    echo number_format($total_items);
                                    ?>
                                </span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>Total Cache Hit Rate</span>
                                <span class="badge bg-info rounded-pill">
                                    <?php
                                    $total_hits = 0;
                                    $total_misses = 0;
                                    foreach ($cache_stats as $stat) {
                                        $total_hits += $stat['hits'];
                                        $total_misses += $stat['misses'];
                                    }
                                    $total_rate = ($total_hits + $total_misses) > 0 ? 
                                                round(($total_hits / ($total_hits + $total_misses)) * 100, 1) : 0;
                                    echo $total_rate . '%';
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Cache Settings Modal -->
<div class="modal fade" id="cacheSettingsModal" tabindex="-1" aria-labelledby="cacheSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cacheSettingsModalLabel">Cache System Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="cacheSettingsContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Clear all cache
function clearAllCache() {
    Swal.fire({
        title: 'Clear All Cache',
        text: 'This will clear all cache types. Continue?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Clear All Cache'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/clear-all-cache.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cleared!',
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

// Clear specific cache
function clearSpecificCache(cacheType) {
    let cacheName = '';
    switch(cacheType) {
        case 'opcache': cacheName = 'PHP OPcache'; break;
        case 'apc': cacheName = 'APC Cache'; break;
        case 'apcu': cacheName = 'APCu Cache'; break;
        case 'file_cache': cacheName = 'File Cache'; break;
        case 'database_cache': cacheName = 'Database Cache'; break;
        case 'session_cache': cacheName = 'Session Cache'; break;
        default: cacheName = 'Cache';
    }
    
    Swal.fire({
        title: `Clear ${cacheName}`,
        text: `Clear ${cacheName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: `Clear ${cacheName}`
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('../ajax/settings/clear-cache.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    cache_type: cacheType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cleared!',
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

// Clear OPcache
function clearOpcache() {
    clearSpecificCache('opcache');
}

// Clear file cache
function clearFileCache() {
    clearSpecificCache('file_cache');
}

// Clear database cache
function clearDatabaseCache() {
    clearSpecificCache('database_cache');
}

// Clear session cache
function clearSessionCache() {
    clearSpecificCache('session_cache');
}

// View cache settings
function viewCacheSettings() {
    fetch('../ajax/settings/get-cache-settings.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>PHP Cache Configuration</h6>
                        <table class="table table-sm table-borderless">
            `;
            
            // Add OPcache info if available
            if (data.opcache && data.opcache.enabled) {
                html += `
                    <tr>
                        <td><strong>OPcache Enabled:</strong></td>
                        <td>Yes</td>
                    </tr>
                    <tr>
                        <td><strong>OPcache Memory:</strong></td>
                        <td>${data.opcache.memory_consumption ? formatBytes(data.opcache.memory_consumption * 1024 * 1024) : 'Not set'}</td>
                    </tr>
                    <tr>
                        <td><strong>Max Files:</strong></td>
                        <td>${data.opcache.max_accelerated_files || 'Not set'}</td>
                    </tr>
                `;
            } else {
                html += `
                    <tr>
                        <td><strong>OPcache Enabled:</strong></td>
                        <td>No</td>
                    </tr>
                `;
            }
            
            // Add APCu info if available
            if (data.apcu && data.apcu.enabled) {
                html += `
                    <tr>
                        <td><strong>APCu Enabled:</strong></td>
                        <td>Yes</td>
                    </tr>
                    <tr>
                        <td><strong>APCu Memory:</strong></td>
                        <td>${data.apcu.shm_size ? formatBytes(data.apcu.shm_size * 1024 * 1024) : 'Not set'}</td>
                    </tr>
                `;
            }
            
            html += `
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Cache Directories</h6>
                        <div class="list-group list-group-flush">
            `;
            
            if (data.directories && data.directories.length > 0) {
                data.directories.forEach(dir => {
                    const writable = dir.writable ? 
                        '<span class="badge bg-success">Writable</span>' : 
                        '<span class="badge bg-danger">Not Writable</span>';
                    html += `
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <small>${dir.path}</small>
                            ${writable}
                        </div>
                    `;
                });
            } else {
                html += `<div class="text-muted">No cache directories found</div>`;
            }
            
            html += `
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('cacheSettingsContent').innerHTML = html;
            $('#cacheSettingsModal').modal('show');
        } else {
            Swal.fire('Error!', data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire('Error!', 'Failed to load cache settings.', 'error');
    });
}

// Save cache configuration
function saveCacheConfig() {
    const form = document.getElementById('cacheConfigForm');
    const formData = new FormData(form);
    
    fetch('../ajax/settings/save-cache-config.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
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

// Format bytes for JavaScript
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Initialize performance chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('cachePerformanceChart');
    if (ctx) {
        // Sample data - replace with real data
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Cache Hit Rate (%)',
                    data: [85, 88, 90, 92, 91, 93],
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 80,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });
    }
});
</script>

<?php 
function formatBytes($bytes, $decimals = 2) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $dm = $decimals < 0 ? 0 : $decimals;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return number_format($bytes / pow($k, $i), $dm) . ' ' . $sizes[$i];
}
require_once '../includes/footer.php'; 
?>