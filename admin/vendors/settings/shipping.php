<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Shipping Settings';
require_once '../../includes/header.php';

// Get vendor shipping settings
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];

    // Get vendor shipping settings from vendor_settings table
    $stmt = $db->prepare("
        SELECT 
            vs.shipping_policy,
            vs.free_shipping_threshold,
            vs.store_address,
            vs.min_order_amount
        FROM vendor_settings vs
        WHERE vs.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $shipping_settings = $stmt->fetch() ?? [];

    // Get shipping zones and rates
    $stmt = $db->prepare("
        SELECT * FROM vendor_shipping_zones 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $shipping_zones = $stmt->fetchAll();

    // Get shipping methods
    $stmt = $db->prepare("
        SELECT * FROM vendor_shipping_methods 
        WHERE vendor_id = ? 
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$vendor_id]);
    $shipping_methods = $stmt->fetchAll();

    // Get supported countries
    $stmt = $db->prepare("SELECT * FROM countries WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $countries = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $shipping_settings = [];
    $shipping_zones = [];
    $shipping_methods = [];
    $countries = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_shipping_settings') {
            $shipping_policy = trim($_POST['shipping_policy'] ?? '');
            $free_shipping_threshold = floatval($_POST['free_shipping_threshold'] ?? 0);
            $processing_time = intval($_POST['processing_time'] ?? 1);
            $handling_fee = floatval($_POST['handling_fee'] ?? 0);
            $package_dimensions = json_encode([
                'weight_unit' => $_POST['weight_unit'] ?? 'kg',
                'dimension_unit' => $_POST['dimension_unit'] ?? 'cm',
                'default_weight' => floatval($_POST['default_weight'] ?? 1),
                'default_length' => floatval($_POST['default_length'] ?? 30),
                'default_width' => floatval($_POST['default_width'] ?? 20),
                'default_height' => floatval($_POST['default_height'] ?? 10)
            ]);

            // Update vendor_settings
            $stmt = $db->prepare("
                UPDATE vendor_settings 
                SET shipping_policy = ?, free_shipping_threshold = ?,
                    updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([$shipping_policy, $free_shipping_threshold, $vendor_id]);

            // Save other settings
            $stmt = $db->prepare("
                INSERT INTO vendor_shipping_settings 
                (vendor_id, processing_time, handling_fee, package_dimensions, created_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                processing_time = ?, handling_fee = ?, package_dimensions = ?, updated_at = NOW()
            ");
            $stmt->execute([
                $vendor_id,
                $processing_time,
                $handling_fee,
                $package_dimensions,
                $processing_time,
                $handling_fee,
                $package_dimensions
            ]);

            $_SESSION['success'] = 'Shipping settings updated successfully!';
            redirect('shipping.php');
        } elseif ($action === 'add_shipping_zone') {
            $zone_name = trim($_POST['zone_name'] ?? '');
            $countries = $_POST['countries'] ?? [];
            $states = $_POST['states'] ?? [];
            $postal_codes = trim($_POST['postal_codes'] ?? '');
            $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;

            if (empty($zone_name)) {
                throw new Exception('Zone name is required.');
            }

            $zone_data = json_encode([
                'countries' => $countries,
                'states' => $states,
                'postal_codes' => $postal_codes
            ]);

            $stmt = $db->prepare("
                INSERT INTO vendor_shipping_zones 
                (vendor_id, zone_name, zone_data, is_enabled, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$vendor_id, $zone_name, $zone_data, $is_enabled]);

            $_SESSION['success'] = 'Shipping zone added successfully!';
            redirect('shipping.php');
        } elseif ($action === 'add_shipping_method') {
            $method_name = trim($_POST['method_name'] ?? '');
            $method_type = $_POST['method_type'] ?? 'flat_rate';
            $cost = floatval($_POST['cost'] ?? 0);
            $free_shipping = isset($_POST['free_shipping']) ? 1 : 0;
            $min_order_amount = floatval($_POST['min_order_amount'] ?? 0);
            $max_order_amount = floatval($_POST['max_order_amount'] ?? 0);
            $estimated_days = intval($_POST['estimated_days'] ?? 3);
            $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;

            if (empty($method_name)) {
                throw new Exception('Method name is required.');
            }

            $method_data = json_encode([
                'type' => $method_type,
                'cost' => $cost,
                'free_shipping' => $free_shipping,
                'min_order_amount' => $min_order_amount,
                'max_order_amount' => $max_order_amount,
                'estimated_days' => $estimated_days
            ]);

            $stmt = $db->prepare("
                INSERT INTO vendor_shipping_methods 
                (vendor_id, method_name, method_data, is_enabled, sort_order, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$vendor_id, $method_name, $method_data, $is_enabled]);

            $_SESSION['success'] = 'Shipping method added successfully!';
            redirect('shipping.php');
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Shipping Settings</h1>
                <p class="text-muted mb-0">Configure shipping options and policies</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Shipping Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="shippingTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="settings-tab" data-bs-toggle="tab"
                            data-bs-target="#settings" type="button">
                            <i class="fas fa-cog me-2"></i> General Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="zones-tab" data-bs-toggle="tab"
                            data-bs-target="#zones" type="button">
                            <i class="fas fa-globe me-2"></i> Shipping Zones
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="methods-tab" data-bs-toggle="tab"
                            data-bs-target="#methods" type="button">
                            <i class="fas fa-shipping-fast me-2"></i> Shipping Methods
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="packaging-tab" data-bs-toggle="tab"
                            data-bs-target="#packaging" type="button">
                            <i class="fas fa-box me-2"></i> Packaging
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Shipping Content -->
        <div class="tab-content" id="shippingTabContent">
            <!-- General Settings Tab -->
            <div class="tab-pane fade show active" id="settings" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-cog me-2"></i> Shipping Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="shippingSettingsForm">
                            <input type="hidden" name="action" value="update_shipping_settings">

                            <div class="row g-4">
                                <!-- Processing Time -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Order Processing Time *</label>
                                        <div class="input-group">
                                            <input type="number" name="processing_time" class="form-control"
                                                min="1" max="30" value="1" required>
                                            <span class="input-group-text">Business Days</span>
                                        </div>
                                        <small class="text-muted">Time taken to process and pack orders before shipping</small>
                                    </div>
                                </div>

                                <!-- Handling Fee -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Handling Fee (Optional)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="handling_fee" class="form-control"
                                                step="0.01" min="0" value="0">
                                        </div>
                                        <small class="text-muted">Additional fee per order for packaging</small>
                                    </div>
                                </div>

                                <!-- Free Shipping Threshold -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Free Shipping Threshold</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="free_shipping_threshold" class="form-control"
                                                step="0.01" min="0"
                                                value="<?php echo $shipping_settings['free_shipping_threshold'] ?? 0; ?>">
                                        </div>
                                        <small class="text-muted">Minimum order amount for free shipping (0 to disable)</small>
                                    </div>
                                </div>

                                <!-- Shipping Policy -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Shipping Policy</label>
                                        <textarea name="shipping_policy" class="form-control" rows="6"
                                            placeholder="Describe your shipping methods, delivery times, and costs..."><?php echo htmlspecialchars($shipping_settings['shipping_policy'] ?? ''); ?></textarea>
                                        <small class="text-muted">This will be displayed on your store page</small>
                                    </div>
                                </div>

                                <!-- Shipping From Address -->
                                <?php
                                // admin/vendors/settings/shipping.php - Shipping dropdown ke liye

                                try {
                                    $db = getDB();

                                    // 🔴 YAHAN QUERY LAGAO - Shipping dropdown ke liye active countries
                                    $stmt = $db->query("SELECT code, name FROM countries WHERE is_active = 1 ORDER BY name");
                                    $shipping_countries = $stmt->fetchAll();
                                } catch (PDOException $e) {
                                    $shipping_countries = [];
                                }
                                ?>

                                <!-- Shipping Form Mein Dropdown -->
                                <div class="mb-3">
                                    <label class="form-label">Select Country</label>
                                    <select name="country" class="form-select">
                                        <option value="">Select Country</option>
                                        <?php foreach ($shipping_countries as $country): ?>
                                            <option value="<?php echo $country['code']; ?>">
                                                <?php echo $country['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Shipping Information -->
                        <div class="alert alert-info mt-4">
                            <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> Shipping Information</h6>
                            <ul class="mb-0">
                                <li>Shipping rates are calculated based on weight, destination, and shipping method</li>
                                <li>You can set up different shipping zones for different regions</li>
                                <li>Free shipping can be enabled for orders above a certain amount</li>
                                <li>Handling fees are added to all orders regardless of shipping method</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Zones Tab -->
            <div class="tab-pane fade" id="zones" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-globe me-2"></i> Shipping Zones
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addZoneModal">
                            <i class="fas fa-plus me-2"></i> Add Zone
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shipping_zones)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-globe fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Shipping Zones</h5>
                                <p class="text-muted">Create shipping zones to define where you ship and your shipping rates</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addZoneModal">
                                    <i class="fas fa-plus me-2"></i> Create Your First Zone
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Zone Name</th>
                                            <th>Countries</th>
                                            <th>States/Regions</th>
                                            <th>Postal Codes</th>
                                            <th>Status</th>
                                            <th>Methods</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($shipping_zones as $zone):
                                            $zone_data = json_decode($zone['zone_data'], true);
                                            $country_count = count($zone_data['countries'] ?? []);
                                            $state_count = count($zone_data['states'] ?? []);
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($zone['zone_name']); ?></strong>
                                                    <?php if ($zone['is_default']): ?>
                                                        <span class="badge bg-success ms-2">Default</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($country_count > 0): ?>
                                                        <span class="badge bg-primary"><?php echo $country_count; ?> countries</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">All countries</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($state_count > 0): ?>
                                                        <span class="badge bg-info"><?php echo $state_count; ?> states</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">All states</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($zone_data['postal_codes'])): ?>
                                                        <small class="text-muted">Custom range</small>
                                                    <?php else: ?>
                                                        <span class="text-muted">All codes</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($zone['is_enabled']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i> Enabled
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-times-circle me-1"></i> Disabled
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Count methods for this zone
                                                    $method_count = 0;
                                                    foreach ($shipping_methods as $method) {
                                                        $method_data = json_decode($method['method_data'], true);
                                                        if (in_array($zone['id'], $method_data['zones'] ?? [])) {
                                                            $method_count++;
                                                        }
                                                    }
                                                    ?>
                                                    <span class="badge bg-light text-dark"><?php echo $method_count; ?> methods</span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary"
                                                            onclick="editZone(<?php echo $zone['id']; ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editZoneModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-success"
                                                            onclick="manageZoneMethods(<?php echo $zone['id']; ?>)">
                                                            <i class="fas fa-shipping-fast"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger"
                                                            onclick="deleteZone(<?php echo $zone['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Zone Information -->
                            <div class="alert alert-warning mt-4">
                                <h6 class="fw-bold"><i class="fas fa-lightbulb me-2"></i> How Shipping Zones Work</h6>
                                <ul class="mb-0">
                                    <li>Create zones for different regions (e.g., Local, Domestic, International)</li>
                                    <li>Assign shipping methods to each zone</li>
                                    <li>Customers will see shipping options based on their location</li>
                                    <li>You can set different rates for different zones</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Shipping Methods Tab -->
            <div class="tab-pane fade" id="methods" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-shipping-fast me-2"></i> Shipping Methods
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMethodModal">
                            <i class="fas fa-plus me-2"></i> Add Method
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shipping_methods)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-truck fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Shipping Methods</h5>
                                <p class="text-muted">Create shipping methods like Standard, Express, or Free Shipping</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMethodModal">
                                    <i class="fas fa-plus me-2"></i> Add Shipping Method
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($shipping_methods as $method):
                                    $method_data = json_decode($method['method_data'], true);
                                    $type_icons = [
                                        'flat_rate' => 'dollar-sign',
                                        'free_shipping' => 'gift',
                                        'local_pickup' => 'store',
                                        'weight_based' => 'weight',
                                        'price_based' => 'tag'
                                    ];
                                    $type_colors = [
                                        'flat_rate' => 'primary',
                                        'free_shipping' => 'success',
                                        'local_pickup' => 'warning',
                                        'weight_based' => 'info',
                                        'price_based' => 'danger'
                                    ];
                                ?>
                                    <div class="col-md-6">
                                        <div class="card border shadow-sm h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($method['method_name']); ?></h5>
                                                        <span class="badge bg-<?php echo $type_colors[$method_data['type']] ?? 'secondary'; ?>">
                                                            <i class="fas fa-<?php echo $type_icons[$method_data['type']] ?? 'shipping-fast'; ?> me-1"></i>
                                                            <?php echo ucwords(str_replace('_', ' ', $method_data['type'])); ?>
                                                        </span>
                                                    </div>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox"
                                                            <?php echo $method['is_enabled'] ? 'checked' : ''; ?>
                                                            onchange="toggleMethod(<?php echo $method['id']; ?>, this)">
                                                    </div>
                                                </div>

                                                <!-- Method Details -->
                                                <div class="mb-3">
                                                    <?php if ($method_data['type'] === 'flat_rate'): ?>
                                                        <p class="mb-1">
                                                            <strong>Cost:</strong>
                                                            $<?php echo number_format($method_data['cost'], 2); ?>
                                                        </p>
                                                    <?php elseif ($method_data['type'] === 'free_shipping'): ?>
                                                        <p class="mb-1">
                                                            <strong>Minimum Order:</strong>
                                                            $<?php echo number_format($method_data['min_order_amount'], 2); ?>
                                                        </p>
                                                    <?php elseif ($method_data['type'] === 'weight_based'): ?>
                                                        <p class="mb-1">
                                                            <strong>Weight Based:</strong>
                                                            Calculated based on product weight
                                                        </p>
                                                    <?php endif; ?>

                                                    <?php if ($method_data['estimated_days']): ?>
                                                        <p class="mb-1">
                                                            <strong>Delivery Time:</strong>
                                                            <?php echo $method_data['estimated_days']; ?> business days
                                                        </p>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Assigned Zones -->
                                                <div class="mb-3">
                                                    <h6 class="fw-bold">Assigned Zones:</h6>
                                                    <?php
                                                    $zone_names = [];
                                                    foreach ($shipping_zones as $zone) {
                                                        if (in_array($zone['id'], $method_data['zones'] ?? [])) {
                                                            $zone_names[] = $zone['zone_name'];
                                                        }
                                                    }
                                                    ?>
                                                    <?php if (!empty($zone_names)): ?>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php foreach ($zone_names as $zone_name): ?>
                                                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($zone_name); ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No zones assigned</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Actions -->
                                                <div class="d-flex justify-content-between">
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="editMethod(<?php echo $method['id']; ?>)"
                                                        data-bs-toggle="modal" data-bs-target="#editMethodModal">
                                                        <i class="fas fa-edit me-1"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-success"
                                                        onclick="assignZones(<?php echo $method['id']; ?>)">
                                                        <i class="fas fa-globe me-1"></i> Assign Zones
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteMethod(<?php echo $method['id']; ?>)">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Method Types Info -->
                        <div class="row mt-4 g-4">
                            <div class="col-md-4">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">
                                        <i class="fas fa-dollar-sign text-primary me-2"></i> Flat Rate
                                    </h6>
                                    <p class="text-muted small mb-0">Fixed shipping cost regardless of order size</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">
                                        <i class="fas fa-gift text-success me-2"></i> Free Shipping
                                    </h6>
                                    <p class="text-muted small mb-0">Free shipping for orders above minimum amount</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3">
                                    <h6 class="fw-bold">
                                        <i class="fas fa-weight text-info me-2"></i> Weight Based
                                    </h6>
                                    <p class="text-muted small mb-0">Shipping cost based on total order weight</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Packaging Tab -->
            <div class="tab-pane fade" id="packaging" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-box me-2"></i> Package Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="packagingForm">
                            <input type="hidden" name="action" value="update_packaging">

                            <div class="row g-4">
                                <!-- Units -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Weight Unit *</label>
                                        <select name="weight_unit" class="form-select" required>
                                            <option value="kg">Kilograms (kg)</option>
                                            <option value="lb">Pounds (lb)</option>
                                            <option value="g">Grams (g)</option>
                                            <option value="oz">Ounces (oz)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Dimension Unit *</label>
                                        <select name="dimension_unit" class="form-select" required>
                                            <option value="cm">Centimeters (cm)</option>
                                            <option value="in">Inches (in)</option>
                                            <option value="m">Meters (m)</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Default Package Size -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-3">Default Package Size</h6>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Default Weight</label>
                                                <div class="input-group">
                                                    <input type="number" name="default_weight" class="form-control"
                                                        step="0.01" min="0.01" value="1" required>
                                                    <span class="input-group-text">kg</span>
                                                </div>
                                                <small class="text-muted">Used when product weight is not specified</small>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Length</label>
                                                <div class="input-group">
                                                    <input type="number" name="default_length" class="form-control"
                                                        step="0.1" min="1" value="30" required>
                                                    <span class="input-group-text">cm</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Width</label>
                                                <div class="input-group">
                                                    <input type="number" name="default_width" class="form-control"
                                                        step="0.1" min="1" value="20" required>
                                                    <span class="input-group-text">cm</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <div class="mb-3">
                                                <label class="form-label">Height</label>
                                                <div class="input-group">
                                                    <input type="number" name="default_height" class="form-control"
                                                        step="0.1" min="1" value="10" required>
                                                    <span class="input-group-text">cm</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Package Types -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-3">Package Types</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="border rounded p-3 text-center">
                                                <i class="fas fa-box fa-2x text-primary mb-3"></i>
                                                <h6>Small Package</h6>
                                                <p class="text-muted small">Up to 1kg, 30x20x10cm</p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" checked>
                                                    <label class="form-check-label">Enabled</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="border rounded p-3 text-center">
                                                <i class="fas fa-box-open fa-2x text-warning mb-3"></i>
                                                <h6>Medium Package</h6>
                                                <p class="text-muted small">1-5kg, 50x40x30cm</p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" checked>
                                                    <label class="form-check-label">Enabled</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="border rounded p-3 text-center">
                                                <i class="fas fa-pallet fa-2x text-danger mb-3"></i>
                                                <h6>Large Package</h6>
                                                <p class="text-muted small">5-20kg, 100x80x60cm</p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox">
                                                    <label class="form-check-label">Enabled</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Packaging Instructions -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Packaging Instructions</label>
                                        <textarea name="packaging_instructions" class="form-control" rows="4"
                                            placeholder="Special instructions for packaging..."></textarea>
                                        <small class="text-muted">Internal notes for your team</small>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Packaging Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Packaging Tips -->
                        <div class="alert alert-info mt-4">
                            <h6 class="fw-bold"><i class="fas fa-lightbulb me-2"></i> Packaging Tips</h6>
                            <ul class="mb-0">
                                <li>Accurate weight and dimensions help calculate correct shipping costs</li>
                                <li>Use proper packaging materials to prevent damage during transit</li>
                                <li>Consider insurance for high-value items</li>
                                <li>Include packing slips and return labels in packages</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Zone Modal -->
<div class="modal fade" id="addZoneModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i> Add Shipping Zone
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addZoneForm">
                <input type="hidden" name="action" value="add_shipping_zone">

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Zone Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Zone Name *</label>
                                <input type="text" name="zone_name" class="form-control"
                                    placeholder="e.g., Domestic, International, Local" required>
                            </div>
                        </div>

                        <!-- Countries -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Countries</label>
                                <select name="countries[]" class="form-select" multiple size="5">
                                    <option value="">All Countries</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['code']; ?>">
                                            <?php echo htmlspecialchars($country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Hold Ctrl/Cmd to select multiple countries. Leave empty for all countries.</small>
                            </div>
                        </div>

                        <!-- States -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">States/Regions (Optional)</label>
                                <input type="text" name="states[]" class="form-control"
                                    placeholder="e.g., California, New York (comma separated)">
                                <small class="text-muted">Leave empty for all states in selected countries</small>
                            </div>
                        </div>

                        <!-- Postal Codes -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Postal Codes (Optional)</label>
                                <textarea name="postal_codes" class="form-control" rows="3"
                                    placeholder="e.g., 10001-10005, 90210 or 100*, 902*"></textarea>
                                <small class="text-muted">Comma separated list or ranges. Use * for wildcards.</small>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="zoneEnabled" checked>
                                <label class="form-check-label fw-bold" for="zoneEnabled">
                                    Enable this shipping zone
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Create Zone
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Method Modal -->
<div class="modal fade" id="addMethodModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i> Add Shipping Method
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addMethodForm">
                <input type="hidden" name="action" value="add_shipping_method">

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Method Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Method Name *</label>
                                <input type="text" name="method_name" class="form-control"
                                    placeholder="e.g., Standard Shipping, Express Delivery" required>
                            </div>
                        </div>

                        <!-- Method Type -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Method Type *</label>
                                <select name="method_type" class="form-select" id="methodType" required>
                                    <option value="flat_rate">Flat Rate</option>
                                    <option value="free_shipping">Free Shipping</option>
                                    <option value="local_pickup">Local Pickup</option>
                                    <option value="weight_based">Weight Based</option>
                                    <option value="price_based">Price Based</option>
                                </select>
                            </div>
                        </div>

                        <!-- Cost (for flat rate) -->
                        <div class="col-md-6" id="costField">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Cost *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="cost" class="form-control"
                                        step="0.01" min="0" value="5.00">
                                </div>
                            </div>
                        </div>

                        <!-- Min Order Amount (for free shipping) -->
                        <div class="col-md-6 d-none" id="minOrderField">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Minimum Order Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="min_order_amount" class="form-control"
                                        step="0.01" min="0" value="50.00">
                                </div>
                            </div>
                        </div>

                        <!-- Max Order Amount -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Maximum Order Amount (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="max_order_amount" class="form-control"
                                        step="0.01" min="0">
                                </div>
                                <small class="text-muted">Leave empty for no maximum</small>
                            </div>
                        </div>

                        <!-- Estimated Days -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Estimated Delivery Time *</label>
                                <div class="input-group">
                                    <input type="number" name="estimated_days" class="form-control"
                                        min="1" max="60" value="3" required>
                                    <span class="input-group-text">Business Days</span>
                                </div>
                            </div>
                        </div>

                        <!-- Free Shipping Option -->
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="free_shipping" id="freeShipping">
                                <label class="form-check-label fw-bold" for="freeShipping">
                                    Offer as free shipping option
                                </label>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_enabled" id="methodEnabled" checked>
                                <label class="form-check-label fw-bold" for="methodEnabled">
                                    Enable this shipping method
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Create Method
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Shipping JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tabs
        const triggerTabList = [].slice.call(document.querySelectorAll('#shippingTab button'));
        triggerTabList.forEach(function(triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);

            triggerEl.addEventListener('click', function(event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });

        // Method type toggle
        const methodType = document.getElementById('methodType');
        const costField = document.getElementById('costField');
        const minOrderField = document.getElementById('minOrderField');

        if (methodType && costField && minOrderField) {
            methodType.addEventListener('change', function() {
                if (this.value === 'free_shipping') {
                    costField.classList.add('d-none');
                    minOrderField.classList.remove('d-none');
                } else {
                    costField.classList.remove('d-none');
                    minOrderField.classList.add('d-none');
                }
            });
        }

        // Form submissions
        const forms = ['shippingSettingsForm', 'addZoneForm', 'addMethodForm', 'packagingForm'];
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

    function editZone(zoneId) {
        // AJAX call to get zone details
        fetch('action/get-zone.php?id=' + zoneId)
            .then(response => response.json())
            .then(data => {
                // Populate edit form
                // Implementation depends on your modal structure
            });
    }

    function deleteZone(zoneId) {
        if (confirm('Are you sure you want to delete this shipping zone?')) {
            fetch('action/delete-zone.php', {
                    method: 'POST',
                    body: new FormData(document.getElementById('deleteForm_' + zoneId))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
        }
    }

    function toggleMethod(methodId, checkbox) {
        const enabled = checkbox.checked ? 1 : 0;

        fetch('action/toggle-method.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    method_id: methodId,
                    enabled: enabled
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    checkbox.checked = !checkbox.checked;
                    alert('Error: ' + data.message);
                }
            });
    }

    function editMethod(methodId) {
        // AJAX call to get method details
        fetch('action/get-method.php?id=' + methodId)
            .then(response => response.json())
            .then(data => {
                // Populate edit form
                // Implementation depends on your modal structure
            });
    }

    function deleteMethod(methodId) {
        if (confirm('Are you sure you want to delete this shipping method?')) {
            fetch('action/delete-method.php', {
                    method: 'POST',
                    body: new FormData(document.getElementById('deleteForm_' + methodId))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
        }
    }

    function assignZones(methodId) {
        // Show zone assignment modal
        const modal = new bootstrap.Modal(document.getElementById('assignZonesModal'));
        modal.show();

        // Load zones for this method
        fetch('action/get-method-zones.php?id=' + methodId)
            .then(response => response.json())
            .then(data => {
                // Populate zone checkboxes
            });
    }

    function manageZoneMethods(zoneId) {
        // Show method management for this zone
        window.location.href = 'shipping-methods.php?zone_id=' + zoneId;
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
    /* Shipping method cards */
    .card:hover {
        transform: translateY(-2px);
        transition: transform 0.2s ease;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1) !important;
    }

    /* Zone badges */
    .badge {
        font-size: 0.75em;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>