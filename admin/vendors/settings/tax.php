<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Tax Settings';
require_once '../../includes/header.php';

// Get vendor tax settings
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];

    // Get vendor tax settings
    $stmt = $db->prepare("
        SELECT * FROM vendor_tax_settings 
        WHERE vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $tax_settings = $stmt->fetch() ?? [
        'tax_enabled' => 1,
        'prices_include_tax' => 0,
        'tax_based_on' => 'billing',
        'shipping_taxable' => 1,
        'tax_rounding' => 'round'
    ];

    // Get tax classes
    $stmt = $db->prepare("
        SELECT * FROM vendor_tax_classes 
        WHERE vendor_id = ? 
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$vendor_id]);
    $tax_classes = $stmt->fetchAll();

    // Get tax rates
    $stmt = $db->prepare("
        SELECT tr.*, tc.class_name 
        FROM vendor_tax_rates tr
        LEFT JOIN vendor_tax_classes tc ON tr.tax_class_id = tc.id
        WHERE tr.vendor_id = ? 
        ORDER BY tr.country, tr.state, tr.rate DESC
    ");
    $stmt->execute([$vendor_id]);
    $tax_rates = $stmt->fetchAll();

    // Get tax exemptions
    $stmt = $db->prepare("
        SELECT * FROM vendor_tax_exemptions 
        WHERE vendor_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$vendor_id]);
    $tax_exemptions = $stmt->fetchAll();

    // Get countries for dropdown
    $stmt = $db->prepare("SELECT * FROM countries WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $countries = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $tax_settings = [];
    $tax_classes = [];
    $tax_rates = [];
    $tax_exemptions = [];
    $countries = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_tax_settings') {
            $tax_enabled = isset($_POST['tax_enabled']) ? 1 : 0;
            $prices_include_tax = isset($_POST['prices_include_tax']) ? 1 : 0;
            $tax_based_on = $_POST['tax_based_on'] ?? 'billing';
            $shipping_taxable = isset($_POST['shipping_taxable']) ? 1 : 0;
            $tax_rounding = $_POST['tax_rounding'] ?? 'round';
            $tax_number = trim($_POST['tax_number'] ?? '');
            $tax_display = $_POST['tax_display'] ?? 'excl';

            // Update tax settings
            $stmt = $db->prepare("
                INSERT INTO vendor_tax_settings 
                (vendor_id, tax_enabled, prices_include_tax, tax_based_on, 
                 shipping_taxable, tax_rounding, tax_number, tax_display, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                tax_enabled = ?, prices_include_tax = ?, tax_based_on = ?,
                shipping_taxable = ?, tax_rounding = ?, tax_number = ?, 
                tax_display = ?, updated_at = NOW()
            ");
            $stmt->execute([
                $vendor_id,
                $tax_enabled,
                $prices_include_tax,
                $tax_based_on,
                $shipping_taxable,
                $tax_rounding,
                $tax_number,
                $tax_display,
                $tax_enabled,
                $prices_include_tax,
                $tax_based_on,
                $shipping_taxable,
                $tax_rounding,
                $tax_number,
                $tax_display
            ]);

            $_SESSION['success'] = 'Tax settings updated successfully!';
            redirect('tax.php');
        } elseif ($action === 'add_tax_class') {
            $class_name = trim($_POST['class_name'] ?? '');
            $class_description = trim($_POST['class_description'] ?? '');
            $sort_order = intval($_POST['sort_order'] ?? 0);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($class_name)) {
                throw new Exception('Tax class name is required.');
            }

            // Check if class already exists
            $stmt = $db->prepare("SELECT id FROM vendor_tax_classes WHERE vendor_id = ? AND class_name = ?");
            $stmt->execute([$vendor_id, $class_name]);

            if ($stmt->fetch()) {
                throw new Exception('Tax class with this name already exists.');
            }

            $stmt = $db->prepare("
                INSERT INTO vendor_tax_classes 
                (vendor_id, class_name, class_description, sort_order, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$vendor_id, $class_name, $class_description, $sort_order, $is_active]);

            $_SESSION['success'] = 'Tax class added successfully!';
            redirect('tax.php');
        } elseif ($action === 'add_tax_rate') {
            $tax_class_id = intval($_POST['tax_class_id'] ?? 0);
            $country = $_POST['country'] ?? '';
            $state = trim($_POST['state'] ?? '');
            $postcode = trim($_POST['postcode'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $rate = floatval($_POST['rate'] ?? 0);
            $rate_name = trim($_POST['rate_name'] ?? '');
            $priority = intval($_POST['priority'] ?? 1);
            $compound = isset($_POST['compound']) ? 1 : 0;
            $shipping = isset($_POST['shipping']) ? 1 : 0;

            // Validation
            if (empty($country)) {
                throw new Exception('Country is required.');
            }

            if ($rate <= 0) {
                throw new Exception('Tax rate must be greater than 0.');
            }

            if (empty($rate_name)) {
                $rate_name = "Tax ($rate%)";
            }

            // Check if rate already exists for this combination
            $stmt = $db->prepare("
                SELECT id FROM vendor_tax_rates 
                WHERE vendor_id = ? AND tax_class_id = ? AND country = ? 
                AND state = ? AND city = ? AND postcode = ?
            ");
            $stmt->execute([$vendor_id, $tax_class_id, $country, $state, $city, $postcode]);

            if ($stmt->fetch()) {
                throw new Exception('Tax rate for this location already exists.');
            }

            $stmt = $db->prepare("
                INSERT INTO vendor_tax_rates 
                (vendor_id, tax_class_id, country, state, city, postcode, 
                 rate, rate_name, priority, compound, shipping, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $vendor_id,
                $tax_class_id,
                $country,
                $state,
                $city,
                $postcode,
                $rate,
                $rate_name,
                $priority,
                $compound,
                $shipping
            ]);

            $_SESSION['success'] = 'Tax rate added successfully!';
            redirect('tax.php');
        } elseif ($action === 'add_tax_exemption') {
            $customer_name = trim($_POST['customer_name'] ?? '');
            $customer_email = trim($_POST['customer_email'] ?? '');
            $tax_number = trim($_POST['tax_number'] ?? '');
            $country = $_POST['country'] ?? '';
            $exemption_type = $_POST['exemption_type'] ?? 'wholesale';
            $valid_from = $_POST['valid_from'] ?? null;
            $valid_to = $_POST['valid_to'] ?? null;
            $notes = trim($_POST['notes'] ?? '');

            if (empty($customer_name) || empty($customer_email)) {
                throw new Exception('Customer name and email are required.');
            }

            if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format.');
            }

            $stmt = $db->prepare("
                INSERT INTO vendor_tax_exemptions 
                (vendor_id, customer_name, customer_email, tax_number, 
                 country, exemption_type, valid_from, valid_to, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $vendor_id,
                $customer_name,
                $customer_email,
                $tax_number,
                $country,
                $exemption_type,
                $valid_from,
                $valid_to,
                $notes
            ]);

            $_SESSION['success'] = 'Tax exemption added successfully!';
            redirect('tax.php');
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
                <h1 class="h3 mb-1 fw-bold">Tax Settings</h1>
                <p class="text-muted mb-0">Configure tax rates and exemptions</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Tax Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="text-muted mb-2">Tax Status</h6>
                                <h6 class="fw-bold">
                                    <span class="badge bg-<?php echo ($tax_settings['tax_enabled'] ?? 1) ? 'success' : 'danger'; ?>">
                                        <?php echo ($tax_settings['tax_enabled'] ?? 1) ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </h6>
                            </div>
                            <div class="bg-<?php echo ($tax_settings['tax_enabled'] ?? 1) ? 'success' : 'danger'; ?> bg-opacity-10 p-3 rounded">
                                <i class="fas fa-percentage fa-2x text-<?php echo ($tax_settings['tax_enabled'] ?? 1) ? 'success' : 'danger'; ?>"></i>
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
                                <h6 class="text-muted mb-2">Tax Classes</h6>
                                <h3 class="fw-bold text-primary"><?php echo count($tax_classes); ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="fas fa-layer-group fa-2x text-primary"></i>
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
                                <h6 class="text-muted mb-2">Tax Rates</h6>
                                <h3 class="fw-bold text-success"><?php echo count($tax_rates); ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="fas fa-map-marker-alt fa-2x text-success"></i>
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
                                <h6 class="text-muted mb-2">Exemptions</h6>
                                <h3 class="fw-bold text-warning"><?php echo count($tax_exemptions); ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="fas fa-user-shield fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tax Tabs -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="taxTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="settings-tab" data-bs-toggle="tab"
                            data-bs-target="#settings" type="button">
                            <i class="fas fa-cog me-2"></i> Tax Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="classes-tab" data-bs-toggle="tab"
                            data-bs-target="#classes" type="button">
                            <i class="fas fa-layer-group me-2"></i> Tax Classes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rates-tab" data-bs-toggle="tab"
                            data-bs-target="#rates" type="button">
                            <i class="fas fa-percentage me-2"></i> Tax Rates
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="exemptions-tab" data-bs-toggle="tab"
                            data-bs-target="#exemptions" type="button">
                            <i class="fas fa-user-shield me-2"></i> Tax Exemptions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab"
                            data-bs-target="#reports" type="button">
                            <i class="fas fa-chart-bar me-2"></i> Tax Reports
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tax Content -->
        <div class="tab-content" id="taxTabContent">
            <!-- Tax Settings Tab -->
            <div class="tab-pane fade show active" id="settings" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-cog me-2"></i> Tax Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="taxSettingsForm">
                            <input type="hidden" name="action" value="update_tax_settings">

                            <div class="row g-4">
                                <!-- Tax Options -->
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3">Tax Options</h6>

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox"
                                                name="tax_enabled" id="taxEnabled"
                                                <?php echo ($tax_settings['tax_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="taxEnabled">
                                                Enable taxes
                                            </label>
                                            <small class="text-muted d-block">Calculate taxes on orders</small>
                                        </div>

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox"
                                                name="prices_include_tax" id="pricesIncludeTax"
                                                <?php echo ($tax_settings['prices_include_tax'] ?? 0) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="pricesIncludeTax">
                                                Prices entered with tax
                                            </label>
                                            <small class="text-muted d-block">Product prices include tax</small>
                                        </div>

                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox"
                                                name="shipping_taxable" id="shippingTaxable"
                                                <?php echo ($tax_settings['shipping_taxable'] ?? 1) ? 'checked' : ''; ?>>
                                            <label class="form-check-label fw-bold" for="shippingTaxable">
                                                Charge tax on shipping
                                            </label>
                                            <small class="text-muted d-block">Apply tax to shipping costs</small>
                                        </div>
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
                                    </div>
                                </div>

                                <!-- Tax Calculation -->
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3">Tax Calculation</h6>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Calculate Tax Based On</label>
                                            <select name="tax_based_on" class="form-select">
                                                <option value="billing" <?php echo ($tax_settings['tax_based_on'] ?? 'billing') === 'billing' ? 'selected' : ''; ?>>
                                                    Customer billing address
                                                </option>
                                                <option value="shipping" <?php echo ($tax_settings['tax_based_on'] ?? 'billing') === 'shipping' ? 'selected' : ''; ?>>
                                                    Customer shipping address
                                                </option>
                                                <option value="base" <?php echo ($tax_settings['tax_based_on'] ?? 'billing') === 'base' ? 'selected' : ''; ?>>
                                                    Shop base address
                                                </option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Rounding</label>
                                            <select name="tax_rounding" class="form-select">
                                                <option value="round" <?php echo ($tax_settings['tax_rounding'] ?? 'round') === 'round' ? 'selected' : ''; ?>>
                                                    Round at subtotal
                                                </option>
                                                <option value="per_item" <?php echo ($tax_settings['tax_rounding'] ?? 'round') === 'per_item' ? 'selected' : ''; ?>>
                                                    Round per item
                                                </option>
                                                <option value="none" <?php echo ($tax_settings['tax_rounding'] ?? 'round') === 'none' ? 'selected' : ''; ?>>
                                                    No rounding
                                                </option>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Prices Displayed</label>
                                            <select name="tax_display" class="form-select">
                                                <option value="excl" <?php echo ($tax_settings['tax_display'] ?? 'excl') === 'excl' ? 'selected' : ''; ?>>
                                                    Excluding tax
                                                </option>
                                                <option value="incl" <?php echo ($tax_settings['tax_display'] ?? 'excl') === 'incl' ? 'selected' : ''; ?>>
                                                    Including tax
                                                </option>
                                                <option value="both" <?php echo ($tax_settings['tax_display'] ?? 'excl') === 'both' ? 'selected' : ''; ?>>
                                                    Both (including and excluding tax)
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tax Registration -->
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3">Tax Registration</h6>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Tax/VAT Number</label>
                                            <input type="text" name="tax_number" class="form-control"
                                                value="<?php echo htmlspecialchars($tax_settings['tax_number'] ?? ''); ?>"
                                                placeholder="e.g., GB123456789">
                                            <small class="text-muted">Your business tax registration number</small>
                                        </div>

                                        <div class="alert alert-info">
                                            <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> Tax Compliance</h6>
                                            <p class="mb-0 small">
                                                Ensure you comply with tax regulations in your country and
                                                the countries you sell to. Consult with a tax professional
                                                for specific advice.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tax Display Examples -->
                                <div class="col-md-6">
                                    <div class="mb-4">
                                        <h6 class="fw-bold mb-3">Display Examples</h6>

                                        <div class="border rounded p-3 bg-light">
                                            <h6>Example Product</h6>
                                            <div class="mb-2">
                                                <strong>Price (excluding tax):</strong> $100.00
                                            </div>
                                            <div class="mb-2">
                                                <strong>Tax (10%):</strong> $10.00
                                            </div>
                                            <div>
                                                <strong>Price (including tax):</strong> $110.00
                                            </div>

                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    Based on current settings, customers will see:
                                                </small>
                                                <div class="mt-2">
                                                    <?php if (($tax_settings['tax_display'] ?? 'excl') === 'excl'): ?>
                                                        <span class="badge bg-info">$100.00 (excl. tax)</span>
                                                    <?php elseif (($tax_settings['tax_display'] ?? 'excl') === 'incl'): ?>
                                                        <span class="badge bg-info">$110.00 (incl. tax)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">$100.00 (excl. tax)</span>
                                                        <span class="badge bg-secondary ms-2">$110.00 (incl. tax)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Tax Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tax Classes Tab -->
            <div class="tab-pane fade" id="classes" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-layer-group me-2"></i> Tax Classes
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
                            <i class="fas fa-plus me-2"></i> Add Class
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tax_classes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-layer-group fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Tax Classes</h5>
                                <p class="text-muted">Create tax classes to apply different tax rates to products</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                                    <i class="fas fa-plus me-2"></i> Create Tax Class
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Class Name</th>
                                            <th>Description</th>
                                            <th>Rates</th>
                                            <th>Sort Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tax_classes as $class):
                                            // Count rates for this class
                                            $rate_count = 0;
                                            foreach ($tax_rates as $rate) {
                                                if ($rate['tax_class_id'] == $class['id']) {
                                                    $rate_count++;
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                                    <?php if ($class['is_default']): ?>
                                                        <span class="badge bg-success ms-2">Default</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo htmlspecialchars($class['class_description'] ?? 'No description'); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $rate_count; ?> rates</span>
                                                </td>
                                                <td><?php echo $class['sort_order']; ?></td>
                                                <td>
                                                    <?php if ($class['is_active']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i> Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-times-circle me-1"></i> Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary"
                                                            onclick="editClass(<?php echo $class['id']; ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editClassModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if (!$class['is_default']): ?>
                                                            <button class="btn btn-outline-success"
                                                                onclick="setDefaultClass(<?php echo $class['id']; ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button class="btn btn-outline-danger"
                                                                onclick="deleteClass(<?php echo $class['id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Default Classes Info -->
                            <div class="row mt-4 g-4">
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold">
                                            <i class="fas fa-box text-primary me-2"></i> Standard Rate
                                        </h6>
                                        <p class="text-muted small mb-0">Most products fall under standard tax rates</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold">
                                            <i class="fas fa-book text-success me-2"></i> Reduced Rate
                                        </h6>
                                        <p class="text-muted small mb-0">Books, food, children's clothing, etc.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold">
                                            <i class="fas fa-gift text-warning me-2"></i> Zero Rate
                                        </h6>
                                        <p class="text-muted small mb-0">Essential goods, exports, donations</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tax Rates Tab -->
            <div class="tab-pane fade" id="rates" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-percentage me-2"></i> Tax Rates
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRateModal">
                            <i class="fas fa-plus me-2"></i> Add Rate
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tax_rates)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-percentage fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Tax Rates</h5>
                                <p class="text-muted">Create tax rates for different locations and tax classes</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRateModal">
                                    <i class="fas fa-plus me-2"></i> Add Tax Rate
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tax Class</th>
                                            <th>Country</th>
                                            <th>State/Region</th>
                                            <th>City</th>
                                            <th>Postcode</th>
                                            <th>Rate</th>
                                            <th>Name</th>
                                            <th>Priority</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tax_rates as $rate): ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo htmlspecialchars($rate['class_name'] ?? 'Standard'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $country_name = $rate['country'];
                                                    foreach ($countries as $c) {
                                                        if ($c['code'] === $rate['country']) {
                                                            $country_name = $c['name'];
                                                            break;
                                                        }
                                                    }
                                                    echo htmlspecialchars($country_name);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo !empty($rate['state']) ? htmlspecialchars($rate['state']) : '<span class="text-muted">Any</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php echo !empty($rate['city']) ? htmlspecialchars($rate['city']) : '<span class="text-muted">Any</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php echo !empty($rate['postcode']) ? htmlspecialchars($rate['postcode']) : '<span class="text-muted">Any</span>'; ?>
                                                </td>
                                                <td>
                                                    <strong class="<?php echo $rate['rate'] > 0 ? 'text-success' : 'text-warning'; ?>">
                                                        <?php echo number_format($rate['rate'], 2); ?>%
                                                    </strong>
                                                    <?php if ($rate['compound']): ?>
                                                        <span class="badge bg-info ms-1">C</span>
                                                    <?php endif; ?>
                                                    <?php if ($rate['shipping']): ?>
                                                        <span class="badge bg-warning ms-1">S</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($rate['rate_name']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo $rate['priority']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary"
                                                            onclick="editRate(<?php echo $rate['id']; ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editRateModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger"
                                                            onclick="deleteRate(<?php echo $rate['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Rate Import/Export -->
                            <div class="border rounded p-3 bg-light mt-4">
                                <h6 class="fw-bold mb-3">Bulk Operations</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#importRatesModal">
                                            <i class="fas fa-file-import me-2"></i> Import Rates (CSV)
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <a href="action/export-tax-rates.php" class="btn btn-outline-success w-100">
                                            <i class="fas fa-file-export me-2"></i> Export Rates (CSV)
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <!-- Tax Rate Calculator -->
                            <div class="border rounded p-3 mt-4">
                                <h6 class="fw-bold mb-3">Tax Calculator</h6>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" class="form-control" id="calcAmount" value="100">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Tax Rate</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="calcRate" value="10">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Tax Amount</label>
                                            <input type="text" class="form-control" id="calcTaxAmount" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Total</label>
                                            <input type="text" class="form-control" id="calcTotal" readonly>
                                        </div>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" onclick="calculateTax()">
                                    <i class="fas fa-calculator me-2"></i> Calculate
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tax Exemptions Tab -->
            <div class="tab-pane fade" id="exemptions" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-user-shield me-2"></i> Tax Exemptions
                        </h5>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExemptionModal">
                            <i class="fas fa-plus me-2"></i> Add Exemption
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tax_exemptions)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-user-shield fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Tax Exemptions</h5>
                                <p class="text-muted">Add customers who are exempt from paying taxes</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExemptionModal">
                                    <i class="fas fa-plus me-2"></i> Add Tax Exemption
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Email</th>
                                            <th>Tax Number</th>
                                            <th>Country</th>
                                            <th>Exemption Type</th>
                                            <th>Valid From</th>
                                            <th>Valid To</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tax_exemptions as $exemption):
                                            $is_valid = true;
                                            if ($exemption['valid_from'] && strtotime($exemption['valid_from']) > time()) {
                                                $is_valid = false;
                                            }
                                            if ($exemption['valid_to'] && strtotime($exemption['valid_to']) < time()) {
                                                $is_valid = false;
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($exemption['customer_name']); ?></strong>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($exemption['customer_email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo !empty($exemption['tax_number']) ? htmlspecialchars($exemption['tax_number']) : '<span class="text-muted">N/A</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $country_name = $exemption['country'];
                                                    foreach ($countries as $c) {
                                                        if ($c['code'] === $exemption['country']) {
                                                            $country_name = $c['name'];
                                                            break;
                                                        }
                                                    }
                                                    echo htmlspecialchars($country_name);
                                                    ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php
                                                                            echo $exemption['exemption_type'] === 'wholesale' ? 'primary' : ($exemption['exemption_type'] === 'nonprofit' ? 'success' : 'info');
                                                                            ?>">
                                                        <?php echo ucfirst($exemption['exemption_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $exemption['valid_from'] ? date('d M Y', strtotime($exemption['valid_from'])) : '<span class="text-muted">Always</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php echo $exemption['valid_to'] ? date('d M Y', strtotime($exemption['valid_to'])) : '<span class="text-muted">Never</span>'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($is_valid): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i> Valid
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-clock me-1"></i> Expired
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary"
                                                            onclick="editExemption(<?php echo $exemption['id']; ?>)"
                                                            data-bs-toggle="modal" data-bs-target="#editExemptionModal">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger"
                                                            onclick="deleteExemption(<?php echo $exemption['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Exemption Statistics -->
                            <div class="row mt-4 g-4">
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="text-muted mb-2">Wholesale</h6>
                                            <h3 class="fw-bold text-primary">
                                                <?php
                                                $wholesale_count = 0;
                                                foreach ($tax_exemptions as $ex) {
                                                    if ($ex['exemption_type'] === 'wholesale') $wholesale_count++;
                                                }
                                                echo $wholesale_count;
                                                ?>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="text-muted mb-2">Non-Profit</h6>
                                            <h3 class="fw-bold text-success">
                                                <?php
                                                $nonprofit_count = 0;
                                                foreach ($tax_exemptions as $ex) {
                                                    if ($ex['exemption_type'] === 'nonprofit') $nonprofit_count++;
                                                }
                                                echo $nonprofit_count;
                                                ?>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <h6 class="text-muted mb-2">Government</h6>
                                            <h3 class="fw-bold text-warning">
                                                <?php
                                                $govt_count = 0;
                                                foreach ($tax_exemptions as $ex) {
                                                    if ($ex['exemption_type'] === 'government') $govt_count++;
                                                }
                                                echo $govt_count;
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

            <!-- Tax Reports Tab -->
            <div class="tab-pane fade" id="reports" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-chart-bar me-2"></i> Tax Reports
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Report Filters -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Report Type</label>
                                    <select class="form-select" id="reportType">
                                        <option value="sales_tax">Sales Tax Summary</option>
                                        <option value="tax_by_country">Tax by Country</option>
                                        <option value="tax_by_class">Tax by Tax Class</option>
                                        <option value="exemption_report">Exemption Report</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Period</label>
                                    <select class="form-select" id="reportPeriod">
                                        <option value="today">Today</option>
                                        <option value="yesterday">Yesterday</option>
                                        <option value="this_week" selected>This Week</option>
                                        <option value="last_week">Last Week</option>
                                        <option value="this_month">This Month</option>
                                        <option value="last_month">Last Month</option>
                                        <option value="this_quarter">This Quarter</option>
                                        <option value="last_quarter">Last Quarter</option>
                                        <option value="this_year">This Year</option>
                                        <option value="last_year">Last Year</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">From Date</label>
                                    <input type="date" class="form-control" id="fromDate"
                                        value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">To Date</label>
                                    <input type="date" class="form-control" id="toDate"
                                        value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Generate Report Button -->
                        <div class="text-center mb-4">
                            <button class="btn btn-primary" onclick="generateTaxReport()">
                                <i class="fas fa-chart-bar me-2"></i> Generate Report
                            </button>
                            <button class="btn btn-outline-primary ms-2" onclick="exportTaxReport()">
                                <i class="fas fa-download me-2"></i> Export as PDF
                            </button>
                            <button class="btn btn-outline-success ms-2" onclick="exportTaxReportCSV()">
                                <i class="fas fa-file-csv me-2"></i> Export as CSV
                            </button>
                        </div>

                        <!-- Report Placeholder -->
                        <div id="taxReportContainer">
                            <div class="text-center py-5">
                                <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">Tax Report</h5>
                                <p class="text-muted">Select filters and generate a tax report</p>
                            </div>
                        </div>

                        <!-- Tax Summary Cards -->
                        <div class="row g-4 mt-4">
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="text-muted mb-2">Total Tax Collected</h6>
                                                <h3 class="fw-bold text-success">$0.00</h3>
                                            </div>
                                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                                <i class="fas fa-money-bill-wave text-success fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="text-muted mb-2">Taxable Sales</h6>
                                                <h3 class="fw-bold text-primary">$0.00</h3>
                                            </div>
                                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                                <i class="fas fa-shopping-cart text-primary fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="text-muted mb-2">Average Tax Rate</h6>
                                                <h3 class="fw-bold text-warning">0.00%</h3>
                                            </div>
                                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                                <i class="fas fa-percentage text-warning fa-2x"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tax Compliance Notice -->
                        <div class="alert alert-warning mt-4">
                            <h6 class="fw-bold"><i class="fas fa-exclamation-triangle me-2"></i> Tax Compliance Notice</h6>
                            <p class="mb-0">
                                These reports are for informational purposes only. Consult with a tax professional
                                to ensure compliance with all tax regulations. You are responsible for filing
                                and paying taxes to the appropriate authorities.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Tax Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i> Add Tax Class
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addClassForm">
                <input type="hidden" name="action" value="add_tax_class">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Class Name *</label>
                        <input type="text" name="class_name" class="form-control"
                            placeholder="e.g., Standard, Reduced, Zero" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description (Optional)</label>
                        <textarea name="class_description" class="form-control" rows="3"
                            placeholder="Describe this tax class..."></textarea>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control"
                                    min="0" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">&nbsp;</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="classActive" checked>
                                    <label class="form-check-label" for="classActive">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Create Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Tax Rate Modal -->
<div class="modal fade" id="addRateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i> Add Tax Rate
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addRateForm">
                <input type="hidden" name="action" value="add_tax_rate">

                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Tax Class -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tax Class *</label>
                                <select name="tax_class_id" class="form-select" required>
                                    <option value="">Select Tax Class</option>
                                    <?php foreach ($tax_classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Country -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Country *</label>
                                <select name="country" class="form-select" required>
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['code']; ?>">
                                            <?php echo htmlspecialchars($country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- State -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">State/Region (Optional)</label>
                                <input type="text" name="state" class="form-control"
                                    placeholder="e.g., California, NY">
                                <small class="text-muted">Leave empty for all states</small>
                            </div>
                        </div>

                        <!-- City -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">City (Optional)</label>
                                <input type="text" name="city" class="form-control"
                                    placeholder="e.g., New York, Los Angeles">
                                <small class="text-muted">Leave empty for all cities</small>
                            </div>
                        </div>

                        <!-- Postcode -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Postcode (Optional)</label>
                                <input type="text" name="postcode" class="form-control"
                                    placeholder="e.g., 10001, 90210">
                                <small class="text-muted">Leave empty for all postcodes</small>
                            </div>
                        </div>

                        <!-- Rate -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Tax Rate (%) *</label>
                                <div class="input-group">
                                    <input type="number" name="rate" class="form-control"
                                        step="0.01" min="0" max="100" value="10.00" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Rate Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Rate Name *</label>
                                <input type="text" name="rate_name" class="form-control"
                                    value="Tax" required>
                            </div>
                        </div>

                        <!-- Priority -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Priority</label>
                                <input type="number" name="priority" class="form-control"
                                    min="1" value="1">
                                <small class="text-muted">Higher priority rates override lower ones</small>
                            </div>
                        </div>

                        <!-- Options -->
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">&nbsp;</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="compound" id="compoundTax">
                                    <label class="form-check-label" for="compoundTax">
                                        Compound Tax
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="shipping" id="shippingTax" checked>
                                    <label class="form-check-label" for="shippingTax">
                                        Apply to Shipping
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Create Rate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Tax Exemption Modal -->
<div class="modal fade" id="addExemptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i> Add Tax Exemption
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="addExemptionForm">
                <input type="hidden" name="action" value="add_tax_exemption">

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer Name *</label>
                        <input type="text" name="customer_name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Customer Email *</label>
                        <input type="email" name="customer_email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tax/VAT Number (Optional)</label>
                        <input type="text" name="tax_number" class="form-control"
                            placeholder="e.g., GB123456789">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Country</label>
                                <select name="country" class="form-select">
                                    <option value="">Select Country</option>
                                    <?php foreach ($countries as $country): ?>
                                        <option value="<?php echo $country['code']; ?>">
                                            <?php echo htmlspecialchars($country['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Exemption Type</label>
                                <select name="exemption_type" class="form-select">
                                    <option value="wholesale">Wholesale</option>
                                    <option value="nonprofit">Non-Profit</option>
                                    <option value="government">Government</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Valid From (Optional)</label>
                                <input type="date" name="valid_from" class="form-control">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Valid To (Optional)</label>
                                <input type="date" name="valid_to" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Create Exemption
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tax JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tabs
        const triggerTabList = [].slice.call(document.querySelectorAll('#taxTab button'));
        triggerTabList.forEach(function(triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);

            triggerEl.addEventListener('click', function(event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });

        // Form submissions
        const forms = ['taxSettingsForm', 'addClassForm', 'addRateForm', 'addExemptionForm'];
        forms.forEach(formId => {
            const form = document.getElementById(formId);
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitForm(this);
                });
            }
        });

        // Period selector toggle
        const reportPeriod = document.getElementById('reportPeriod');
        const fromDate = document.getElementById('fromDate');
        const toDate = document.getElementById('toDate');

        if (reportPeriod) {
            reportPeriod.addEventListener('change', function() {
                if (this.value === 'custom') {
                    fromDate.disabled = false;
                    toDate.disabled = false;
                } else {
                    fromDate.disabled = true;
                    toDate.disabled = true;
                }
            });
        }
    });

    function calculateTax() {
        const amount = parseFloat(document.getElementById('calcAmount').value) || 0;
        const rate = parseFloat(document.getElementById('calcRate').value) || 0;

        const taxAmount = (amount * rate) / 100;
        const total = amount + taxAmount;

        document.getElementById('calcTaxAmount').value = '$' + taxAmount.toFixed(2);
        document.getElementById('calcTotal').value = '$' + total.toFixed(2);
    }

    function generateTaxReport() {
        const reportType = document.getElementById('reportType').value;
        const period = document.getElementById('reportPeriod').value;
        const from = document.getElementById('fromDate').value;
        const to = document.getElementById('toDate').value;

        // Show loading
        const container = document.getElementById('taxReportContainer');
        container.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p>Generating report...</p>
        </div>
    `;

        // AJAX call to generate report
        fetch('action/generate-tax-report.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    report_type: reportType,
                    period: period,
                    from_date: from,
                    to_date: to
                })
            })
            .then(response => response.json())
            .then(data => {
                // Display report
                displayTaxReport(data);
            });
    }

    function displayTaxReport(data) {
        const container = document.getElementById('taxReportContainer');

        if (data.success) {
            container.innerHTML = `
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">${data.report_title}</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    ${data.headers.map(header => `<th>${header}</th>`).join('')}
                                </tr>
                            </thead>
                            <tbody>
                                ${data.rows.map(row => `
                                    <tr>
                                        ${row.map(cell => `<td>${cell}</td>`).join('')}
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <strong>Total Tax:</strong> $${data.total_tax}
                    </div>
                </div>
            </div>
        `;
        } else {
            container.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                Error generating report: ${data.message}
            </div>
        `;
        }
    }

    function exportTaxReport() {
        // Generate PDF report
        alert('PDF export feature would be implemented here.');
    }

    function exportTaxReportCSV() {
        // Export as CSV
        window.location.href = 'action/export-tax-csv.php';
    }

    function editClass(classId) {
        // AJAX call to get class details
        fetch('action/get-tax-class.php?id=' + classId)
            .then(response => response.json())
            .then(data => {
                // Populate edit form
                // Implementation depends on your modal structure
            });
    }

    function deleteClass(classId) {
        if (confirm('Are you sure you want to delete this tax class?')) {
            fetch('action/delete-tax-class.php', {
                    method: 'POST',
                    body: new FormData(document.getElementById('deleteClassForm_' + classId))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
        }
    }

    function setDefaultClass(classId) {
        if (confirm('Set this tax class as default?')) {
            fetch('action/set-default-class.php', {
                    method: 'POST',
                    body: new FormData(document.getElementById('defaultClassForm_' + classId))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
        }
    }

    function editRate(rateId) {
        // AJAX call to get rate details
        fetch('action/get-tax-rate.php?id=' + rateId)
            .then(response => response.json())
            .then(data => {
                // Populate edit form
                // Implementation depends on your modal structure
            });
    }

    function deleteRate(rateId) {
        if (confirm('Are you sure you want to delete this tax rate?')) {
            fetch('action/delete-tax-rate.php', {
                    method: 'POST',
                    body: new FormData(document.getElementById('deleteRateForm_' + rateId))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
        }
    }

    function editExemption(exemptionId) {
        // AJAX call to get exemption details
        fetch('action/get-tax-exemption.php?id=' + exemptionId)
            .then(response => response.json())
            .then(data => {
                // Populate edit form
                // Implementation depends on your modal structure
            });
    }

    function deleteExemption(exemptionId) {
        if (confirm('Are you sure you want to delete this tax exemption?')) {
            fetch('action/delete-tax-exemption.php', {
                    method: 'POST',
                    body: new FormData(document.getElementById('deleteExemptionForm_' + exemptionId))
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    }
                });
        }
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
    /* Tax rate badges */
    .badge {
        font-size: 0.75em;
    }

    /* Report container */
    #taxReportContainer {
        min-height: 300px;
    }

    /* Calculator styling */
    #calcTaxAmount,
    #calcTotal {
        background-color: #f8f9fa;
    }
</style>

<?php require_once '../../includes/footer.php'; ?>