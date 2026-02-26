<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Vendor Settings';
require_once '../../includes/header.php';

// Get vendor details
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];

    $stmt = $db->prepare("
        SELECT 
            u.id, u.username, u.email, u.full_name, u.phone,
            u.vendor_status, u.vendor_verified, u.vendor_since,
            u.vendor_rating, u.total_products, u.total_sales,
            u.country, u.city, u.address,
            vs.store_name, vs.store_description, vs.store_logo, vs.store_banner,
            vs.store_address, vs.store_phone, vs.store_email, vs.store_website,
            vs.store_social_facebook, vs.store_social_instagram, vs.store_social_twitter,
            vs.store_social_linkedin, vs.store_social_youtube, vs.store_social_pinterest,
            vs.store_policy, vs.return_policy, vs.shipping_policy, 
            vs.payment_methods, vs.store_currency, vs.store_timezone, vs.store_language,
            vs.business_hours, vs.min_order_amount, vs.free_shipping_threshold,
            vs.low_stock_notify, vs.auto_hide_out_of_stock, vs.allow_backorders,
            vs.api_key, vs.webhook_url,
            DATE_FORMAT(u.vendor_since, '%d %b %Y') as vendor_since_formatted
        FROM users u
        LEFT JOIN vendor_settings vs ON u.id = vs.vendor_id
        WHERE u.id = ?
    ");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        $_SESSION['error'] = 'Vendor not found.';
        redirect(SITE_URL . 'admin/vendors/dashboard.php');
    }

    // Calculate total products if not set
    if (!isset($vendor['total_products']) || $vendor['total_products'] === null) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
        $stmt->execute([$vendor_id]);
        $vendor['total_products'] = $stmt->fetchColumn();
    }

    // Get vendor categories
    $stmt = $db->prepare("
        SELECT vc.* 
        FROM vendor_categories vc
        ORDER BY vc.name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();

    // Decode JSON fields
    $business_hours = !empty($vendor['business_hours']) ? json_decode($vendor['business_hours'], true) : [];
    $payment_methods = !empty($vendor['payment_methods']) ? json_decode($vendor['payment_methods'], true) : [];
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $vendor = [];
    $categories = [];
    $business_hours = [];
    $payment_methods = [];
}

// After fetching vendor, calculate total products
$stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
$stmt->execute([$vendor_id]);
$vendor['total_products'] = $stmt->fetchColumn();
?>

<!-- Custom CSS for Enhanced UI -->
<style>
    :root {
        --primary-color: #4361ee;
        --success-color: #06d6a0;
        --warning-color: #ffb703;
        --danger-color: #ef476f;
        --dark-color: #2b2d42;
        --light-color: #f8f9fa;
    }

    /* Dashboard Container */
    .dashboard-container {
        display: flex;
        /* min-height: 100vh; */
        /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
    }

    .main-content {
        flex: 1;
        padding: 30px;
        background: #f8f9fa;
        margin: 20px;
        border-radius: 20px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
    }

    /* Header Section */
    .settings-header {
        background: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .settings-header::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 300px;
        height: 100%;
        background: linear-gradient(135deg, transparent 0%, rgba(67, 97, 238, 0.05) 100%);
        border-radius: 50% 0 0 50%;
    }

    .settings-header h1 {
        font-size: 2.2rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 10px;
    }

    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, transparent 0%, rgba(67, 97, 238, 0.1) 100%);
        border-radius: 50%;
        transform: translate(30px, -30px);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(67, 97, 238, 0.15);
    }

    .stat-card .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 20px;
    }

    .stat-card .stat-label {
        font-size: 14px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }

    .stat-card .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--dark-color);
    }

    .stat-card .stat-trend {
        font-size: 13px;
        margin-top: 10px;
        color: #28a745;
    }

    /* Tabs Navigation */
    .settings-tabs {
        background: white;
        border-radius: 12px;
        padding: 10px;
        margin-bottom: 30px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    }

    .settings-tabs .nav-item {
        margin: 0 5px;
    }

    .settings-tabs .nav-link {
        border: none;
        padding: 15px 25px;
        color: #fff;
        font-weight: 600;
        border-radius: 10px;
        transition: all 0.3s ease;
        position: relative;
    }

    .settings-tabs .nav-link i {
        margin-right: 10px;
        font-size: 1.2rem;
    }

    .settings-tabs .nav-link:hover {
        color: white;
        background: rgba(67, 97, 238, 0.05);
        transform: translateY(-2px);
    }

    .settings-tabs .nav-link.active {
        background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        color: var(--light-color);
        box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
    }

    .settings-tabs .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-top: 8px solid var(--primary-color);
    }

    /* Cards */
    .settings-card {
        background: white;
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .settings-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
        border-bottom: 2px solid rgba(67, 97, 238, 0.1);
        padding: 25px 30px;
    }

    .settings-card .card-header h5 {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--dark-color);
        margin: 0;
    }

    .settings-card .card-header h5 i {
        color: var(--primary-color);
        margin-right: 12px;
        font-size: 1.5rem;
    }

    .settings-card .card-body {
        padding: 30px;
    }

    /* Form Elements */
    .form-group {
        margin-bottom: 25px;
    }

    .form-label {
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 10px;
        font-size: 0.95rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-label i {
        color: var(--primary-color);
        margin-right: 8px;
        width: 20px;
    }

    .form-control,
    .form-select {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 12px 18px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        outline: none;
    }

    .form-control.is-invalid {
        border-color: var(--danger-color);
        background-image: none;
    }

    .form-text {
        font-size: 0.85rem;
        color: #6c757d;
        margin-top: 8px;
        display: flex;
        align-items: center;
    }

    .form-text i {
        margin-right: 5px;
        font-size: 1rem;
    }

    /* Input Groups */
    .input-group {
        border-radius: 12px;
        overflow: hidden;
    }

    .input-group-text {
        background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 12px 20px;
        font-weight: 600;
    }

    .input-group .form-control {
        border-left: none;
    }

    /* Buttons */
    .btn-save {
        background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        color: white;
        border: none;
        padding: 14px 35px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1.1rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(67, 97, 238, 0.4);
        color: white;
    }

    .btn-save i {
        margin-right: 10px;
        transition: transform 0.3s ease;
    }

    .btn-save:hover i {
        transform: scale(1.2);
    }

    .btn-outline-primary-custom {
        background: transparent;
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        padding: 12px 25px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-outline-primary-custom:hover {
        background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        color: white;
        border-color: transparent;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(67, 97, 238, 0.2);
    }

    /* Business Hours Table */
    .business-hours-table {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    }

    .business-hours-table th {
        background: linear-gradient(135deg, var(--primary-color) 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
        padding: 15px;
        border: none;
    }

    .business-hours-table td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid #e9ecef;
    }

    .business-hours-table tr:last-child td {
        border-bottom: none;
    }

    .business-hours-table .form-control,
    .business-hours-table .form-select {
        border-radius: 8px;
        padding: 8px 12px;
    }

    /* Switch Toggle */
    .form-switch {
        padding-left: 3rem;
    }

    .form-switch .form-check-input {
        width: 3rem;
        height: 1.5rem;
        margin-left: -3rem;
        border-radius: 3rem;
        cursor: pointer;
    }

    .form-switch .form-check-input:checked {
        background-color: var(--success-color);
        border-color: var(--success-color);
    }

    /* Media Upload */
    .media-preview {
        background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
        border-radius: 15px;
        padding: 25px;
        text-align: center;
        border: 2px dashed #dee2e6;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .media-preview:hover {
        border-color: var(--primary-color);
        background: rgba(67, 97, 238, 0.02);
    }

    .media-preview img {
        max-width: 100%;
        max-height: 200px;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .media-upload-btn {
        position: relative;
        overflow: hidden;
        margin-top: 15px;
    }

    .media-upload-btn input[type="file"] {
        position: absolute;
        top: 0;
        right: 0;
        min-width: 100%;
        min-height: 100%;
        font-size: 100px;
        text-align: right;
        filter: alpha(opacity=0);
        opacity: 0;
        outline: none;
        background: white;
        cursor: pointer;
        display: block;
    }

    /* Social Media Preview */
    .social-preview {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 20px;
    }

    .social-preview-item {
        display: inline-flex;
        align-items: center;
        padding: 12px 25px;
        background: linear-gradient(135deg, #f8f9fa 0%, white 100%);
        border: 2px solid #e9ecef;
        border-radius: 50px;
        text-decoration: none;
        color: var(--dark-color);
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }

    .social-preview-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(67, 97, 238, 0.15);
        border-color: var(--primary-color);
        color: var(--primary-color);
    }

    .social-preview-item i {
        font-size: 1.5rem;
        margin-right: 10px;
    }

    .social-preview-item.facebook i {
        color: #1877f2;
    }

    .social-preview-item.instagram i {
        color: #e4405f;
    }

    .social-preview-item.twitter i {
        color: #1da1f2;
    }

    .social-preview-item.linkedin i {
        color: #0077b5;
    }

    .social-preview-item.youtube i {
        color: #ff0000;
    }

    .social-preview-item.pinterest i {
        color: #bd081c;
    }

    /* Danger Zone */
    .danger-zone {
        background: linear-gradient(135deg, #fff5f5 0%, white 100%);
        border: 2px solid var(--danger-color);
        border-radius: 20px;
        padding: 30px;
        margin-top: 30px;
    }

    .danger-zone h6 {
        color: var(--danger-color);
        font-weight: 700;
        font-size: 1.2rem;
        margin-bottom: 20px;
    }

    .danger-zone h6 i {
        margin-right: 10px;
    }

    .danger-zone .btn-outline-danger {
        border: 2px solid var(--danger-color);
        color: var(--danger-color);
        padding: 12px 25px;
        border-radius: 12px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .danger-zone .btn-outline-danger:hover {
        background: var(--danger-color);
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(239, 71, 111, 0.3);
    }

    /* Animations */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .tab-pane {
        animation: slideIn 0.5s ease;
    }
    /* Quick access card styling */
.text-white-50 {
    color: rgba(255, 255, 255, 0.7) !important;
}

.opacity-75 {
    opacity: 0.75;
}

/* Bank tab styling */
#bank .text-center {
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

    /* Responsive Design */
    @media (max-width: 992px) {
        .main-content {
            margin: 10px;
            padding: 20px;
        }

        .settings-header h1 {
            font-size: 1.8rem;
        }

        .settings-tabs .nav-link {
            padding: 12px 15px;
            font-size: 0.9rem;
        }

        .settings-tabs .nav-link i {
            margin-right: 5px;
        }
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .settings-tabs .nav-item {
            width: 100%;
            margin: 5px 0;
        }

        .settings-tabs .nav-link {
            width: 100%;
            text-align: left;
        }

        .settings-tabs .nav-link.active::after {
            display: none;
        }

        .business-hours-table {
            font-size: 0.9rem;
        }

        .business-hours-table td {
            padding: 10px;
        }
    }

    /* Loading Spinner */
    .spinner-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.9);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        backdrop-filter: blur(5px);
    }

    .spinner {
        width: 50px;
        height: 50px;
        border: 4px solid var(--primary-color);
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Toast Notifications */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
    }

    .toast-custom {
        background: white;
        border-radius: 12px;
        padding: 15px 25px;
        margin-bottom: 10px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        animation: slideInRight 0.3s ease;
        border-left: 4px solid var(--primary-color);
    }

    .toast-custom.success {
        border-left-color: var(--success-color);
    }

    .toast-custom.error {
        border-left-color: var(--danger-color);
    }

    .toast-custom.warning {
        border-left-color: var(--warning-color);
    }

    .toast-custom i {
        font-size: 1.5rem;
        margin-right: 15px;
    }

    .toast-custom.success i {
        color: var(--success-color);
    }

    .toast-custom.error i {
        color: var(--danger-color);
    }

    .toast-custom.warning i {
        color: var(--warning-color);
    }

    .toast-custom .toast-content {
        flex: 1;
    }

    .toast-custom .toast-title {
        font-weight: 700;
        margin-bottom: 5px;
    }

    .toast-custom .toast-message {
        font-size: 0.9rem;
        color: #6c757d;
    }

    .toast-custom .toast-close {
        cursor: pointer;
        color: #6c757d;
        transition: color 0.3s ease;
    }

    .toast-custom .toast-close:hover {
        color: var(--danger-color);
    }

    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100%);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
</style>

<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>

    <main class="main-content">
        <!-- Loading Spinner -->
        <div class="spinner-overlay" id="spinner">
            <div class="spinner"></div>
        </div>

        <!-- Header Section -->
        <div class="settings-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-cog me-3"></i>Store Settings</h1>
                    <p class="text-muted mb-0">Customize and manage your store preferences</p>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn-save" onclick="saveAllSettings()">
                        <i class="fas fa-save"></i> Save All Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <!-- Store Status Card -->
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, rgba(67, 97, 238, 0.1) 0%, rgba(67, 97, 238, 0.2) 100%); color: var(--primary-color);">
                    <i class="fas fa-store"></i>
                </div>
                <div class="stat-label">Store Status</div>
                <div class="stat-value">
                    <?php
                    // Safe way to get vendor status with default
                    $vendor_status = $vendor['vendor_status'] ?? 'pending';

                    // Set color and icon based on status
                    $status_color = 'secondary';
                    $status_icon = 'circle';

                    if ($vendor_status === 'approved') {
                        $status_color = 'success';
                        $status_icon = 'check-circle';
                    } elseif ($vendor_status === 'pending') {
                        $status_color = 'warning';
                        $status_icon = 'clock';
                    } elseif ($vendor_status === 'rejected') {
                        $status_color = 'danger';
                        $status_icon = 'times-circle';
                    } elseif ($vendor_status === 'suspended') {
                        $status_color = 'dark';
                        $status_icon = 'ban';
                    }
                    ?>
                    <span class="badge bg-<?php echo $status_color; ?> px-3 py-2">
                        <i class="fas fa-<?php echo $status_icon; ?> me-2"></i>
                        <?php echo ucfirst($vendor_status); ?>
                    </span>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-calendar me-1"></i> Since <?php echo $vendor['vendor_since_formatted'] ?? 'N/A'; ?>
                </div>
            </div>

            <!-- Store Rating Card -->
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, rgba(6, 214, 160, 0.1) 0%, rgba(6, 214, 160, 0.2) 100%); color: var(--success-color);">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-label">Store Rating</div>
                <div class="stat-value">
                    <?php
                    $rating = floatval($vendor['vendor_rating'] ?? 0);
                    echo number_format($rating, 1);
                    ?>
                </div>
                <div class="stat-trend">
                    <?php
                    $full_stars = floor($rating);
                    $half_star = ($rating - $full_stars) >= 0.5;

                    for ($i = 1; $i <= 5; $i++):
                        if ($i <= $full_stars) {
                            $starClass = 'fas fa-star';
                        } elseif ($i == $full_stars + 1 && $half_star) {
                            $starClass = 'fas fa-star-half-alt';
                        } else {
                            $starClass = 'far fa-star';
                        }
                    ?>
                        <i class="<?php echo $starClass; ?>" style="color: #ffc107;"></i>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Total Products Card -->
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, rgba(255, 183, 3, 0.1) 0%, rgba(255, 183, 3, 0.2) 100%); color: var(--warning-color);">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-label">Total Products</div>
                <div class="stat-value">
                    <?php
                    // If total_products not in vendor array, calculate it
                    if (!isset($vendor['total_products']) && isset($db) && isset($vendor_id)) {
                        try {
                            $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
                            $stmt->execute([$vendor_id]);
                            $vendor['total_products'] = $stmt->fetchColumn();
                        } catch (Exception $e) {
                            $vendor['total_products'] = 0;
                        }
                    }
                    echo $vendor['total_products'] ?? 0;
                    ?>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up text-success"></i> Active products
                </div>
            </div>

            <!-- Total Sales Card -->
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 71, 111, 0.1) 0%, rgba(239, 71, 111, 0.2) 100%); color: var(--danger-color);">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-label">Total Sales</div>
                <div class="stat-value">
                    $<?php echo number_format(floatval($vendor['total_sales'] ?? 0), 2); ?>
                </div>
                <div class="stat-trend">
                    <i class="fas fa-calendar me-1"></i> Lifetime earnings
                </div>
            </div>
        </div>

        
<!-- Quick Access Card -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #4361ee 0%, #764ba2 100%);">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center">
                            <div class="me-4">
                                <i class="fas fa-university fa-3x text-white opacity-75"></i>
                            </div>
                            <div>
                                <h5 class="text-white mb-1">Payment Methods & Withdrawals</h5>
                                <p class="text-white-50 mb-0">
                                    Configure your bank accounts, Easypaisa, JazzCash, PayPal, and cards
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="../earnings/withdraw.php" class="btn btn-light btn-lg px-4">
                            <i class="fas fa-university me-2"></i> Bank Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Content (with Bank tab) -->
<div class="tab-content" id="settingsTabContent">
    <!-- ... existing tabs (general, store, policies, media, social, advanced) ... -->
    
    <!-- Bank Tab -->
    <div class="tab-pane fade" id="bank" role="tabpanel">
        <div class="settings-card">
            <div class="card-header">
                <h5><i class="fas fa-university"></i> Bank & Payment Settings</h5>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="fas fa-university fa-4x text-primary mb-3"></i>
                    <h4 class="mb-3">Manage Your Payment Methods</h4>
                    <p class="text-muted mb-4">
                        Configure bank accounts, Easypaisa, JazzCash, PayPal, Stripe, and credit cards
                    </p>
                    <a href="bank.php" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-arrow-right me-2"></i> Open Bank Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

        <!-- Tabs Navigation -->
        <div class="settings-tabs">
            <ul class="nav nav-pills" id="settingsTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="pill"
                        data-bs-target="#general" type="button">
                        <i class="fas fa-store"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="store-tab" data-bs-toggle="pill"
                        data-bs-target="#store" type="button">
                        <i class="fas fa-building"></i> Store
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="policies-tab" data-bs-toggle="pill"
                        data-bs-target="#policies" type="button">
                        <i class="fas fa-file-contract"></i> Policies
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="media-tab" data-bs-toggle="pill"
                        data-bs-target="#media" type="button">
                        <i class="fas fa-images"></i> Media
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="social-tab" data-bs-toggle="pill"
                        data-bs-target="#social" type="button">
                        <i class="fas fa-share-alt"></i> Social
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="advanced-tab" data-bs-toggle="pill"
                        data-bs-target="#advanced" type="button">
                        <i class="fas fa-cogs"></i> Advanced
                    </button>
                </li>
            </ul>
        </div>

        <!-- Tab Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- General Settings Tab -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-store"></i> General Store Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="generalForm" action="action/settings/update-general.php">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-store"></i> Store Name *
                                        </label>
                                        <input type="text" name="store_name" class="form-control"
                                            value="<?php echo htmlspecialchars($vendor['store_name'] ?? ''); ?>"
                                            placeholder="Enter your store name" required>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> This will be displayed to customers
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-tag"></i> Store Category *
                                        </label>
                                        <select name="store_category" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['slug']; ?>"
                                                    <?php echo ($vendor['vendor_category'] ?? '') === $cat['slug'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> Main category for your store
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-align-left"></i> Store Description
                                        </label>
                                        <textarea name="store_description" class="form-control" rows="4"
                                            placeholder="Tell customers about your store..."><?php echo htmlspecialchars($vendor['store_description'] ?? ''); ?></textarea>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> Brief description shown on your store page
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-phone"></i> Store Phone
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            <input type="tel" name="store_phone" class="form-control"
                                                value="<?php echo htmlspecialchars($vendor['store_phone'] ?? ''); ?>"
                                                placeholder="+1 234 567 890">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-envelope"></i> Store Email *
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" name="store_email" class="form-control"
                                                value="<?php echo htmlspecialchars($vendor['store_email'] ?? $vendor['email']); ?>"
                                                placeholder="store@example.com" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-globe"></i> Website URL
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">https://</span>
                                            <input type="url" name="store_website" class="form-control"
                                                value="<?php echo htmlspecialchars($vendor['store_website'] ?? ''); ?>"
                                                placeholder="yourstore.com">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-money-bill"></i> Currency
                                        </label>
                                        <select name="store_currency" class="form-select">
                                            <?php
                                            $currencies = [
                                                'USD' => 'US Dollar ($)',
                                                'EUR' => 'Euro (€)',
                                                'GBP' => 'British Pound (£)',
                                                'INR' => 'Indian Rupee (₹)',
                                                'PKR' => 'Pakistani Rupee (₨)',
                                                'CAD' => 'Canadian Dollar (C$)',
                                                'AUD' => 'Australian Dollar (A$)',
                                                'JPY' => 'Japanese Yen (¥)'
                                            ];
                                            foreach ($currencies as $code => $name): ?>
                                                <option value="<?php echo $code; ?>"
                                                    <?php echo ($vendor['store_currency'] ?? 'USD') === $code ? 'selected' : ''; ?>>
                                                    <?php echo $name; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-clock"></i> Timezone
                                        </label>
                                        <select name="store_timezone" class="form-select">
                                            <?php
                                            $timezones = [
                                                'UTC' => 'UTC',
                                                'America/New_York' => 'Eastern Time',
                                                'America/Chicago' => 'Central Time',
                                                'America/Denver' => 'Mountain Time',
                                                'America/Los_Angeles' => 'Pacific Time',
                                                'Europe/London' => 'London',
                                                'Europe/Paris' => 'Paris',
                                                'Asia/Kolkata' => 'India (Kolkata)',
                                                'Asia/Karachi' => 'Pakistan (Karachi)',
                                                'Asia/Tokyo' => 'Tokyo',
                                                'Australia/Sydney' => 'Sydney'
                                            ];
                                            foreach ($timezones as $tz => $label): ?>
                                                <option value="<?php echo $tz; ?>"
                                                    <?php echo ($vendor['store_timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>>
                                                    <?php echo $label; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fas fa-language"></i> Store Language
                                        </label>
                                        <select name="store_language" class="form-select">
                                            <option value="en" <?php echo ($vendor['store_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="hi" <?php echo ($vendor['store_language'] ?? 'en') === 'hi' ? 'selected' : ''; ?>>Hindi</option>
                                            <option value="es" <?php echo ($vendor['store_language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                            <option value="fr" <?php echo ($vendor['store_language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>French</option>
                                            <option value="ur" <?php echo ($vendor['store_language'] ?? 'en') === 'ur' ? 'selected' : ''; ?>>Urdu</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Save General Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Store Settings Tab -->
            <div class="tab-pane fade" id="store" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-building"></i> Store Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="storeForm" action="action/settings/update-store.php">
                            <div class="row g-4">
                                <!-- Business Hours -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-4" style="color: var(--primary-color);">
                                        <i class="fas fa-clock me-2"></i> Business Hours
                                    </h6>

                                    <div class="table-responsive">
                                        <table class="table business-hours-table">
                                            <thead>
                                                <tr>
                                                    <th>Day</th>
                                                    <th>Opening Time</th>
                                                    <th>Closing Time</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $days = [
                                                    'monday' => 'Monday',
                                                    'tuesday' => 'Tuesday',
                                                    'wednesday' => 'Wednesday',
                                                    'thursday' => 'Thursday',
                                                    'friday' => 'Friday',
                                                    'saturday' => 'Saturday',
                                                    'sunday' => 'Sunday'
                                                ];
                                                foreach ($days as $key => $day):
                                                    $hours = $business_hours[$key] ?? ['open' => '', 'close' => ''];
                                                ?>
                                                    <tr>
                                                        <td><strong><?php echo $day; ?></strong></td>
                                                        <td>
                                                            <input type="time" name="business_hours[<?php echo $key; ?>][open]"
                                                                class="form-control" value="<?php echo $hours['open']; ?>">
                                                        </td>
                                                        <td>
                                                            <input type="time" name="business_hours[<?php echo $key; ?>][close]"
                                                                class="form-control" value="<?php echo $hours['close']; ?>">
                                                        </td>
                                                        <td>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox"
                                                                    name="business_hours[<?php echo $key; ?>][enabled]"
                                                                    <?php echo !empty($hours['open']) ? 'checked' : ''; ?>>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Store Address -->
                                <div class="col-12 mt-4">
                                    <h6 class="fw-bold mb-4" style="color: var(--primary-color);">
                                        <i class="fas fa-map-marker-alt me-2"></i> Store Address
                                    </h6>
                                    <div class="form-group">
                                        <textarea name="store_address" class="form-control" rows="3"
                                            placeholder="Enter your complete store address"><?php echo htmlspecialchars($vendor['store_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <!-- Order Settings -->
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-4" style="color: var(--primary-color);">
                                        <i class="fas fa-shopping-cart me-2"></i> Order Settings
                                    </h6>
                                    <div class="form-group">
                                        <label class="form-label">Minimum Order Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="min_order_amount" class="form-control"
                                                step="0.01" min="0" value="<?php echo $vendor['min_order_amount'] ?? '0.00'; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-4" style="color: var(--primary-color);">
                                        <i class="fas fa-truck me-2"></i> Free Shipping
                                    </h6>
                                    <div class="form-group">
                                        <label class="form-label">Free Shipping Threshold</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="free_shipping_threshold" class="form-control"
                                                step="0.01" min="0" value="<?php echo $vendor['free_shipping_threshold'] ?? '0.00'; ?>">
                                        </div>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> Orders above this amount get free shipping
                                        </div>
                                    </div>
                                </div>

                                <!-- Inventory Settings -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-4" style="color: var(--primary-color);">
                                        <i class="fas fa-boxes me-2"></i> Inventory Settings
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="low_stock_notify"
                                                    id="lowStockNotify" <?php echo ($vendor['low_stock_notify'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="lowStockNotify">
                                                    <i class="fas fa-bell text-warning me-2"></i>
                                                    Low Stock Notifications
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="auto_hide_out_of_stock"
                                                    id="autoHideOutOfStock" <?php echo ($vendor['auto_hide_out_of_stock'] ?? 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="autoHideOutOfStock">
                                                    <i class="fas fa-eye-slash text-info me-2"></i>
                                                    Auto-hide Out of Stock Products
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="allow_backorders"
                                                    id="allowBackorders" <?php echo ($vendor['allow_backorders'] ?? 0) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="allowBackorders">
                                                    <i class="fas fa-clock text-success me-2"></i>
                                                    Allow Backorders
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Save Store Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Policies Tab -->
            <div class="tab-pane fade" id="policies" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-file-contract"></i> Store Policies</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="policiesForm" action="action/settings/update-policies.php">
                            <!-- Store Policy -->
                            <div class="form-group mb-4">
                                <label class="form-label">
                                    <i class="fas fa-store"></i> Store Policy / Terms of Service
                                </label>
                                <textarea name="store_policy" class="form-control" rows="6"
                                    placeholder="Describe your store policies, terms of service, and general guidelines..."><?php echo htmlspecialchars($vendor['store_policy'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> This will be displayed on your store page
                                </div>
                            </div>

                            <!-- Return Policy -->
                            <div class="form-group mb-4">
                                <label class="form-label">
                                    <i class="fas fa-undo-alt"></i> Return & Refund Policy
                                </label>
                                <textarea name="return_policy" class="form-control" rows="6"
                                    placeholder="Describe your return and refund policy..."><?php echo htmlspecialchars($vendor['return_policy'] ?? ''); ?></textarea>
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i> Important for customer trust
                                </div>
                            </div>

                            <!-- Shipping Policy -->
                            <div class="form-group mb-4">
                                <label class="form-label">
                                    <i class="fas fa-truck"></i> Shipping Policy
                                </label>
                                <textarea name="shipping_policy" class="form-control" rows="6"
                                    placeholder="Describe your shipping methods, costs, and delivery times..."><?php echo htmlspecialchars($vendor['shipping_policy'] ?? ''); ?></textarea>
                            </div>

                            <!-- Payment Methods -->
                            <div class="form-group mb-4">
                                <label class="form-label">
                                    <i class="fas fa-credit-card"></i> Accepted Payment Methods
                                </label>
                                <div class="row g-3">
                                    <?php
                                    $all_methods = [
                                        'cod' => ['name' => 'Cash on Delivery', 'icon' => 'money-bill-wave'],
                                        'credit_card' => ['name' => 'Credit/Debit Card', 'icon' => 'credit-card'],
                                        'paypal' => ['name' => 'PayPal', 'icon' => 'paypal'],
                                        'bank_transfer' => ['name' => 'Bank Transfer', 'icon' => 'university'],
                                        'stripe' => ['name' => 'Stripe', 'icon' => 'stripe'],
                                        'easypaisa' => ['name' => 'Easypaisa', 'icon' => 'mobile-alt'],
                                        'jazzcash' => ['name' => 'JazzCash', 'icon' => 'mobile-alt']
                                    ];
                                    ?>
                                    <?php foreach ($all_methods as $key => $method): ?>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                    name="payment_methods[]" value="<?php echo $key; ?>"
                                                    id="payment_<?php echo $key; ?>"
                                                    <?php echo in_array($key, $payment_methods) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="payment_<?php echo $key; ?>">
                                                    <i class="fas fa-<?php echo $method['icon']; ?> me-2"></i>
                                                    <?php echo $method['name']; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Save Policies
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Media Tab -->
            <div class="tab-pane fade" id="media" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-images"></i> Store Media</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Store Logo -->
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-4">
                                            <i class="fas fa-image text-primary me-2"></i> Store Logo
                                        </h6>

                                        <div class="media-preview mb-3">
                                            <?php if (!empty($vendor['store_logo'])): ?>
                                                <img src="<?php echo SITE_URL; ?>uploads/vendors/<?php echo $vendor['store_logo']; ?>"
                                                    alt="Store Logo" id="logoPreview">
                                            <?php else: ?>
                                                <div class="py-5">
                                                    <i class="fas fa-store fa-4x text-muted mb-3"></i>
                                                    <p class="text-muted">No Logo Uploaded</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <form method="POST" enctype="multipart/form-data" id="logoForm" action="action/settings/upload-logo.php">
                                            <div class="media-upload-btn">
                                                <button type="button" class="btn-outline-primary-custom w-100" onclick="document.getElementById('logoInput').click()">
                                                    <i class="fas fa-upload me-2"></i> Choose Logo
                                                </button>
                                                <input type="file" name="store_logo" id="logoInput"
                                                    accept="image/*" style="display: none;">
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                <i class="fas fa-info-circle"></i> Recommended: 300x300px, PNG or JPG, max 5MB
                                            </small>

                                            <div class="d-flex gap-2 mt-3">
                                                <button type="submit" class="btn-save flex-grow-1">
                                                    <i class="fas fa-upload"></i> Upload Logo
                                                </button>

                                                <?php if (!empty($vendor['store_logo'])): ?>
                                                    <button type="button" class="btn-outline-primary-custom"
                                                        onclick="deleteLogo()">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Store Banner -->
                            <div class="col-lg-6 mb-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-4">
                                            <i class="fas fa-image text-success me-2"></i> Store Banner
                                        </h6>

                                        <div class="media-preview mb-3">
                                            <?php if (!empty($vendor['store_banner'])): ?>
                                                <img src="<?php echo SITE_URL; ?>uploads/vendors/<?php echo $vendor['store_banner']; ?>"
                                                    alt="Store Banner" id="bannerPreview">
                                            <?php else: ?>
                                                <div class="py-5">
                                                    <i class="fas fa-image fa-4x text-muted mb-3"></i>
                                                    <p class="text-muted">No Banner Uploaded</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <form method="POST" enctype="multipart/form-data" id="bannerForm" action="action/settings/upload-banner.php">
                                            <div class="media-upload-btn">
                                                <button type="button" class="btn-outline-primary-custom w-100" onclick="document.getElementById('bannerInput').click()">
                                                    <i class="fas fa-upload me-2"></i> Choose Banner
                                                </button>
                                                <input type="file" name="store_banner" id="bannerInput"
                                                    accept="image/*" style="display: none;">
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                <i class="fas fa-info-circle"></i> Recommended: 1200x400px, PNG or JPG, max 5MB
                                            </small>

                                            <div class="d-flex gap-2 mt-3">
                                                <button type="submit" class="btn-save flex-grow-1">
                                                    <i class="fas fa-upload"></i> Upload Banner
                                                </button>

                                                <?php if (!empty($vendor['store_banner'])): ?>
                                                    <button type="button" class="btn-outline-primary-custom"
                                                        onclick="deleteBanner()">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Media Guidelines -->
                        <div class="alert alert-info mt-3" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: none; border-radius: 15px;">
                            <h6 class="fw-bold"><i class="fas fa-info-circle me-2"></i> Media Guidelines</h6>
                            <ul class="mb-0">
                                <li>Logo should be square (1:1 ratio) for best display</li>
                                <li>Banner should be wide (3:1 ratio) for store header</li>
                                <li>Use high-quality images for professional appearance</li>
                                <li>Maximum file size: 5MB per image</li>
                                <li>Accepted formats: JPG, PNG, GIF, WebP</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Social Media Tab -->
            <div class="tab-pane fade" id="social" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-share-alt"></i> Social Media Links</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="socialForm" action="action/settings/update-social.php">
                            <div class="row g-4">
                                <!-- Facebook -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fab fa-facebook text-primary me-2"></i> Facebook Page
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">facebook.com/</span>
                                            <input type="text" name="social_facebook" class="form-control"
                                                placeholder="yourpagename"
                                                value="<?php echo htmlspecialchars($vendor['store_social_facebook'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Instagram -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fab fa-instagram text-danger me-2"></i> Instagram
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">instagram.com/</span>
                                            <input type="text" name="social_instagram" class="form-control"
                                                placeholder="yourusername"
                                                value="<?php echo htmlspecialchars($vendor['store_social_instagram'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Twitter -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fab fa-twitter text-info me-2"></i> Twitter
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">twitter.com/</span>
                                            <input type="text" name="social_twitter" class="form-control"
                                                placeholder="yourusername"
                                                value="<?php echo htmlspecialchars($vendor['store_social_twitter'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- LinkedIn -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fab fa-linkedin text-primary me-2"></i> LinkedIn
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">linkedin.com/company/</span>
                                            <input type="text" name="social_linkedin" class="form-control"
                                                placeholder="yourcompany"
                                                value="<?php echo htmlspecialchars($vendor['store_social_linkedin'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- YouTube -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fab fa-youtube text-danger me-2"></i> YouTube
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">youtube.com/</span>
                                            <input type="text" name="social_youtube" class="form-control"
                                                placeholder="channel/UCXXXXXX"
                                                value="<?php echo htmlspecialchars($vendor['store_social_youtube'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Pinterest -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">
                                            <i class="fab fa-pinterest text-danger me-2"></i> Pinterest
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">pinterest.com/</span>
                                            <input type="text" name="social_pinterest" class="form-control"
                                                placeholder="yourusername"
                                                value="<?php echo htmlspecialchars($vendor['store_social_pinterest'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Social Media Preview -->
                            <div class="mt-4">
                                <h6 class="fw-bold mb-3">Preview</h6>
                                <div class="social-preview" id="socialPreview">
                                    <!-- Preview will be generated by JavaScript -->
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i> Save Social Links
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings Tab -->
            <div class="tab-pane fade" id="advanced" role="tabpanel">
                <div class="settings-card">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> Advanced Settings</h5>
                    </div>
                    <div class="card-body">
                        <!-- Danger Zone -->
                        <div class="danger-zone">
                            <h6><i class="fas fa-exclamation-triangle"></i> Danger Zone</h6>

                            <div class="row g-3">
                                <!-- Deactivate Store -->
                                <div class="col-md-6">
                                    <div class="border rounded p-4" style="background: white;">
                                        <h6 class="fw-bold">Deactivate Store</h6>
                                        <p class="text-muted small mb-3">
                                            Temporarily hide your store from customers. Products will not be visible.
                                        </p>
                                        <button type="button" class="btn-outline-primary-custom w-100"
                                            data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                            <i class="fas fa-eye-slash me-2"></i> Deactivate Store
                                        </button>
                                    </div>
                                </div>

                                <!-- Delete Store -->
                                <div class="col-md-6">
                                    <div class="border rounded p-4" style="background: white;">
                                        <h6 class="fw-bold">Delete Store</h6>
                                        <p class="text-muted small mb-3">
                                            Permanently delete your vendor account and all data. This action cannot be undone.
                                        </p>
                                        <button type="button" class="btn btn-outline-danger w-100"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash me-2"></i> Delete Store
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- API Settings -->
                        <div class="border rounded p-4 mt-4" style="background: white;">
                            <h6 class="fw-bold mb-4">
                                <i class="fas fa-code text-primary me-2"></i> API & Integration
                            </h6>

                            <!-- API Key -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">API Key</label>
                                <div class="input-group">
                                    <input type="text" class="form-control"
                                        value="<?php echo $vendor['api_key'] ?? 'sk_live_' . substr(md5($vendor_id), 0, 16) . '...'; ?>"
                                        readonly id="apiKey">
                                    <button class="btn btn-outline-primary" type="button" onclick="copyApiKey(this)">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" type="button" onclick="regenerateApiKey()">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Use this key for API integration
                                </small>
                            </div>

                            <!-- Webhooks -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Webhook URL</label>
                                <div class="input-group">
                                    <input type="url" class="form-control"
                                        placeholder="https://yourdomain.com/webhook"
                                        value="<?php echo htmlspecialchars($vendor['webhook_url'] ?? ''); ?>"
                                        id="webhookUrl">
                                    <button class="btn btn-primary" type="button" onclick="saveWebhook()">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Receive real-time notifications
                                </small>
                            </div>
                        </div>

                        <!-- Export Data -->
                        <div class="border rounded p-4 mt-4" style="background: white;">
                            <h6 class="fw-bold mb-4">
                                <i class="fas fa-download text-success me-2"></i> Data Export
                            </h6>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-center p-4 border rounded" style="background: linear-gradient(135deg, #f8f9fa 0%, white 100%);">
                                        <i class="fas fa-boxes fa-3x text-primary mb-3"></i>
                                        <h6 class="fw-bold">Products Data</h6>
                                        <p class="small text-muted mb-3">Export all your products</p>
                                        <button class="btn-outline-primary-custom w-100"
                                            onclick="exportData('products')">
                                            <i class="fas fa-download me-2"></i> Export CSV
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="text-center p-4 border rounded" style="background: linear-gradient(135deg, #f8f9fa 0%, white 100%);">
                                        <i class="fas fa-shopping-cart fa-3x text-success mb-3"></i>
                                        <h6 class="fw-bold">Orders Data</h6>
                                        <p class="small text-muted mb-3">Export your order history</p>
                                        <button class="btn-outline-primary-custom w-100"
                                            onclick="exportData('orders')">
                                            <i class="fas fa-download me-2"></i> Export CSV
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="text-center p-4 border rounded" style="background: linear-gradient(135deg, #f8f9fa 0%, white 100%);">
                                        <i class="fas fa-chart-line fa-3x text-info mb-3"></i>
                                        <h6 class="fw-bold">Analytics Data</h6>
                                        <p class="small text-muted mb-3">Export your analytics</p>
                                        <button class="btn-outline-primary-custom w-100"
                                            onclick="exportData('analytics')">
                                            <i class="fas fa-download me-2"></i> Export CSV
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Deactivate Store Modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="border-bottom: 2px solid var(--warning-color);">
                <h5 class="modal-title text-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> Deactivate Store
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p>Are you sure you want to deactivate your store?</p>
                <ul class="text-muted small">
                    <li><i class="fas fa-times-circle text-danger me-2"></i>Your products will be hidden from customers</li>
                    <li><i class="fas fa-times-circle text-danger me-2"></i>You will not receive new orders</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i>Existing orders will continue to process</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i>You can reactivate anytime</li>
                </ul>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="confirmDeactivate">
                    <label class="form-check-label" for="confirmDeactivate">
                        I understand and want to deactivate my store
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="deactivateBtn" disabled>
                    <i class="fas fa-eye-slash me-2"></i> Deactivate Store
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Store Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="border-bottom: 2px solid var(--danger-color);">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-trash me-2"></i> Delete Store
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-danger fw-bold">Warning: This action cannot be undone!</p>
                <p>All your store data will be permanently deleted:</p>
                <ul class="text-muted small">
                    <li><i class="fas fa-times-circle text-danger me-2"></i>All products and inventory</li>
                    <li><i class="fas fa-times-circle text-danger me-2"></i>Order history and earnings</li>
                    <li><i class="fas fa-times-circle text-danger me-2"></i>Customer reviews and ratings</li>
                    <li><i class="fas fa-times-circle text-danger me-2"></i>Store settings and configurations</li>
                </ul>
                <div class="mb-3 mt-4">
                    <label class="form-label">Type <strong>"DELETE"</strong> to confirm</label>
                    <input type="text" class="form-control" id="deleteConfirm"
                        placeholder="Type DELETE here">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="deleteBtn" disabled>
                    <i class="fas fa-trash me-2"></i> Permanently Delete
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Enhanced JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tabs with smooth transition
        const triggerTabList = [].slice.call(document.querySelectorAll('#settingsTab button'));
        triggerTabList.forEach(function(triggerEl) {
            const tabTrigger = new bootstrap.Tab(triggerEl);

            triggerEl.addEventListener('click', function(event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });

        // Form submissions with AJAX
        const forms = ['generalForm', 'storeForm', 'policiesForm', 'socialForm'];
        forms.forEach(formId => {
            const form = document.getElementById(formId);
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitForm(this);
                });
            }
        });

        // Image preview for logo
        const logoInput = document.getElementById('logoInput');
        if (logoInput) {
            logoInput.addEventListener('change', function() {
                previewImage(this, '#logoPreview');
            });
        }

        // Image preview for banner
        const bannerInput = document.getElementById('bannerInput');
        if (bannerInput) {
            bannerInput.addEventListener('change', function() {
                previewImage(this, '#bannerPreview');
            });
        }

        // Social media preview
        updateSocialPreview();

        // Deactivate store confirmation
        const confirmDeactivate = document.getElementById('confirmDeactivate');
        const deactivateBtn = document.getElementById('deactivateBtn');
        if (confirmDeactivate && deactivateBtn) {
            confirmDeactivate.addEventListener('change', function() {
                deactivateBtn.disabled = !this.checked;
            });
        }

        // Delete store confirmation
        const deleteConfirm = document.getElementById('deleteConfirm');
        const deleteBtn = document.getElementById('deleteBtn');
        if (deleteConfirm && deleteBtn) {
            deleteConfirm.addEventListener('input', function() {
                deleteBtn.disabled = this.value !== 'DELETE';
            });
        }

        // Deactivate store action
        if (deactivateBtn) {
            deactivateBtn.addEventListener('click', function() {
                deactivateStore();
            });
        }

        // Delete store action
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                deleteStore();
            });
        }
    });

    function showSpinner() {
        document.getElementById('spinner').style.display = 'flex';
    }

    function hideSpinner() {
        document.getElementById('spinner').style.display = 'none';
    }

    function showToast(type, message, title = null) {
        const toastContainer = document.getElementById('toastContainer');
        const toastId = 'toast-' + Date.now();

        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };

        const titles = {
            success: 'Success!',
            error: 'Error!',
            warning: 'Warning!',
            info: 'Info'
        };

        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `toast-custom ${type}`;
        toast.innerHTML = `
        <i class="fas fa-${icons[type]}"></i>
        <div class="toast-content">
            <div class="toast-title">${title || titles[type]}</div>
            <div class="toast-message">${message}</div>
        </div>
        <div class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </div>
    `;

        toastContainer.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            const toastEl = document.getElementById(toastId);
            if (toastEl) {
                toastEl.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toastEl.remove(), 300);
            }
        }, 5000);
    }

    function submitForm(form) {
        showSpinner();

        const formData = new FormData(form);

        // Log form data for debugging
        console.log('Submitting form to:', form.action);
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                // First get the text to see raw response
                return response.text().then(text => {
                    console.log('Raw response:', text);

                    // Try to parse as JSON
                    try {
                        const data = JSON.parse(text);
                        return data;
                    } catch (e) {
                        console.error('Failed to parse JSON:', e);
                        throw new Error('Server returned invalid JSON. Check console for raw response.');
                    }
                });
            })
            .then(data => {
                hideSpinner();

                if (data.success) {
                    showToast('success', data.message);
                    // Update any CSRF token if returned
                    if (data.csrf_token) {
                        document.querySelectorAll('input[name="csrf_token"]').forEach(input => {
                            input.value = data.csrf_token;
                        });
                    }
                    // Optionally reload after success
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast('error', data.message || 'An error occurred');
                }
            })
            .catch(error => {
                hideSpinner();
                console.error('Fetch error:', error);
                showToast('error', 'Error: ' + error.message);

                // Create a debug div to show the error
                const debugDiv = document.createElement('div');
                debugDiv.className = 'alert alert-danger mt-3';
                debugDiv.innerHTML = '<strong>Debug Info:</strong><br>' + error.message;
                document.querySelector('.main-content').prepend(debugDiv);
            });
    }

    function previewImage(input, previewSelector) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.querySelector(previewSelector);
                if (preview) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
            }
            reader.readAsDataURL(file);
        }
    }

    function updateSocialPreview() {
        const preview = document.getElementById('socialPreview');
        if (!preview) return;

        const socialFields = [{
                id: 'social_facebook',
                icon: 'fab fa-facebook',
                class: 'facebook',
                label: 'Facebook'
            },
            {
                id: 'social_instagram',
                icon: 'fab fa-instagram',
                class: 'instagram',
                label: 'Instagram'
            },
            {
                id: 'social_twitter',
                icon: 'fab fa-twitter',
                class: 'twitter',
                label: 'Twitter'
            },
            {
                id: 'social_linkedin',
                icon: 'fab fa-linkedin',
                class: 'linkedin',
                label: 'LinkedIn'
            },
            {
                id: 'social_youtube',
                icon: 'fab fa-youtube',
                class: 'youtube',
                label: 'YouTube'
            },
            {
                id: 'social_pinterest',
                icon: 'fab fa-pinterest',
                class: 'pinterest',
                label: 'Pinterest'
            }
        ];

        let html = '';
        socialFields.forEach(social => {
            const input = document.querySelector(`[name="${social.id}"]`);
            if (input && input.value) {
                html += `
                <a href="https://${social.id === 'social_linkedin' ? 'linkedin.com/company/' : social.id.replace('social_', '') + '.com/'}${input.value}" 
                   target="_blank" class="social-preview-item ${social.class}">
                    <i class="${social.icon}"></i>
                    <span>${social.label}</span>
                </a>
            `;
            }
        });

        preview.innerHTML = html || '<p class="text-muted">No social links added yet. Add some above to see preview.</p>';
    }

    // Update preview when social inputs change
    document.querySelectorAll('#socialForm input').forEach(input => {
        input.addEventListener('input', updateSocialPreview);
    });

    function deleteLogo() {
        if (confirm('Are you sure you want to remove the store logo?')) {
            showSpinner();

            fetch('action/settings/delete-logo.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    hideSpinner();
                    if (data.success) {
                        showToast('success', 'Logo deleted successfully!');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    hideSpinner();
                    showToast('error', 'Network error: ' + error.message);
                });
        }
    }

    function deleteBanner() {
        if (confirm('Are you sure you want to remove the store banner?')) {
            showSpinner();

            fetch('action/settings/delete-banner.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    hideSpinner();
                    if (data.success) {
                        showToast('success', 'Banner deleted successfully!');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    hideSpinner();
                    showToast('error', 'Network error: ' + error.message);
                });
        }
    }

    function copyApiKey(button) {
        const apiKey = document.getElementById('apiKey');
        apiKey.select();
        apiKey.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(apiKey.value);

        // Show success feedback
        const originalHtml = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i>';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-primary');

        showToast('success', 'API key copied to clipboard!');

        setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    }

    function regenerateApiKey() {
        if (confirm('Are you sure you want to regenerate your API key? This will break existing integrations.')) {
            showSpinner();

            fetch('action/settings/regenerate-api.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    hideSpinner();
                    if (data.success) {
                        document.getElementById('apiKey').value = data.api_key;
                        showToast('success', 'API key regenerated successfully!');
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    hideSpinner();
                    showToast('error', 'Network error: ' + error.message);
                });
        }
    }

    function saveWebhook() {
        const webhookUrl = document.getElementById('webhookUrl').value;

        showSpinner();

        fetch('action/settings/save-webhook.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'webhook_url=' + encodeURIComponent(webhookUrl)
            })
            .then(response => response.json())
            .then(data => {
                hideSpinner();
                if (data.success) {
                    showToast('success', 'Webhook URL saved successfully!');
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideSpinner();
                showToast('error', 'Network error: ' + error.message);
            });
    }

    function deactivateStore() {
        showSpinner();

        fetch('action/settings/deactivate-store.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                hideSpinner();
                if (data.success) {
                    showToast('success', 'Store deactivated successfully!');
                    setTimeout(() => {
                        window.location.href = '<?php echo SITE_URL; ?>';
                    }, 2000);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideSpinner();
                showToast('error', 'Network error: ' + error.message);
            });
    }

    function deleteStore() {
        showSpinner();

        fetch('action/settings/delete-store.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                hideSpinner();
                if (data.success) {
                    showToast('success', 'Store deleted successfully!');
                    setTimeout(() => {
                        window.location.href = '<?php echo SITE_URL; ?>';
                    }, 2000);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideSpinner();
                showToast('error', 'Network error: ' + error.message);
            });
    }

    function exportData(type) {
        window.location.href = `action/settings/export.php?type=${type}`;
    }

    function saveAllSettings() {
        showToast('info', 'This feature will save all settings at once. Coming soon!');
    }
</script>

<?php require_once '../../includes/footer.php'; ?>