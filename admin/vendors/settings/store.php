<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is vendor
if ($_SESSION['user_type'] !== 'vendor') {
    $_SESSION['error'] = 'Access denied. Vendor only.';
    redirect(SITE_URL . 'index.php');
}

$page_title = 'Store Settings';
require_once '../../includes/header.php';

// Get vendor store settings
try {
    $db = getDB();
    $vendor_id = $_SESSION['user_id'];
    
    // Get vendor store settings
    $stmt = $db->prepare("
        SELECT 
            vs.*,
            DATE_FORMAT(u.created_at, '%d %b %Y') as store_created
        FROM vendor_settings vs
        LEFT JOIN users u ON vs.vendor_id = u.id
        WHERE vs.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $store_settings = $stmt->fetch() ?? [];
    
    // Get store statistics
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM products WHERE vendor_id = ?) as total_products,
            (SELECT COUNT(*) FROM products WHERE vendor_id = ? AND featured = 1) as featured_products,
            (SELECT COUNT(*) FROM products WHERE vendor_id = ? AND stock = 0) as out_of_stock,
            (SELECT COUNT(*) FROM reviews r 
             JOIN products p ON r.product_id = p.id 
             WHERE p.vendor_id = ?) as total_reviews
    ");
    $stmt->execute([$vendor_id, $vendor_id, $vendor_id, $vendor_id]);
    $store_stats = $stmt->fetch();
    
    // Get store categories
    $stmt = $db->prepare("
        SELECT vc.*, 
               (SELECT COUNT(*) FROM products p 
                WHERE p.vendor_id = ? AND p.category = vc.slug) as product_count
        FROM vendor_categories vc
        WHERE vc.is_active = 1
        ORDER BY vc.name
    ");
    $stmt->execute([$vendor_id]);
    $store_categories = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $store_settings = [];
    $store_stats = [];
    $store_categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_store_appearance') {
            $store_theme = $_POST['store_theme'] ?? 'light';
            $color_scheme = $_POST['color_scheme'] ?? 'default';
            $font_family = $_POST['font_family'] ?? 'system';
            $show_banner = isset($_POST['show_banner']) ? 1 : 0;
            $show_featured = isset($_POST['show_featured']) ? 1 : 0;
            $show_reviews = isset($_POST['show_reviews']) ? 1 : 0;
            $products_per_page = intval($_POST['products_per_page'] ?? 12);
            $default_sort = $_POST['default_sort'] ?? 'newest';
            
            $appearance_settings = json_encode([
                'theme' => $store_theme,
                'color_scheme' => $color_scheme,
                'font_family' => $font_family,
                'show_banner' => $show_banner,
                'show_featured' => $show_featured,
                'show_reviews' => $show_reviews,
                'products_per_page' => $products_per_page,
                'default_sort' => $default_sort
            ]);
            
            $stmt = $db->prepare("
                UPDATE vendor_settings 
                SET appearance_settings = ?, updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([$appearance_settings, $vendor_id]);
            
            $_SESSION['success'] = 'Store appearance updated!';
            redirect('store.php');
            
        } elseif ($action === 'update_display_settings') {
            $currency_display = $_POST['currency_display'] ?? 'symbol';
            $price_format = $_POST['price_format'] ?? 'decimal';
            $show_prices_with_tax = isset($_POST['show_prices_with_tax']) ? 1 : 0;
            $show_stock_status = isset($_POST['show_stock_status']) ? 1 : 0;
            $show_product_codes = isset($_POST['show_product_codes']) ? 1 : 0;
            $show_weight_dimensions = isset($_POST['show_weight_dimensions']) ? 1 : 0;
            $show_related_products = isset($_POST['show_related_products']) ? 1 : 0;
            $enable_zoom = isset($_POST['enable_zoom']) ? 1 : 0;
            
            $display_settings = json_encode([
                'currency_display' => $currency_display,
                'price_format' => $price_format,
                'show_prices_with_tax' => $show_prices_with_tax,
                'show_stock_status' => $show_stock_status,
                'show_product_codes' => $show_product_codes,
                'show_weight_dimensions' => $show_weight_dimensions,
                'show_related_products' => $show_related_products,
                'enable_zoom' => $enable_zoom
            ]);
            
            $stmt = $db->prepare("
                UPDATE vendor_settings 
                SET display_settings = ?, updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([$display_settings, $vendor_id]);
            
            $_SESSION['success'] = 'Display settings updated!';
            redirect('store.php');
            
        } elseif ($action === 'update_maintenance_settings') {
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            $maintenance_message = trim($_POST['maintenance_message'] ?? '');
            $allowed_ips = trim($_POST['allowed_ips'] ?? '');
            
            $maintenance_settings = json_encode([
                'maintenance_mode' => $maintenance_mode,
                'maintenance_message' => $maintenance_message,
                'allowed_ips' => $allowed_ips
            ]);
            
            $stmt = $db->prepare("
                UPDATE vendor_settings 
                SET maintenance_settings = ?, updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([$maintenance_settings, $vendor_id]);
            
            $_SESSION['success'] = 'Maintenance settings updated!';
            redirect('store.php');
            
        } elseif ($action === 'update_store_hours') {
            $store_hours = [];
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            foreach($days as $day) {
                $store_hours[$day] = [
                    'open' => $_POST[$day . '_open'] ?? '',
                    'close' => $_POST[$day . '_close'] ?? '',
                    'closed' => isset($_POST[$day . '_closed']) ? 1 : 0
                ];
            }
            
            $stmt = $db->prepare("
                UPDATE vendor_settings 
                SET business_hours = ?, updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([json_encode($store_hours), $vendor_id]);
            
            $_SESSION['success'] = 'Store hours updated!';
            redirect('store.php');
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Parse existing settings
$appearance_settings = isset($store_settings['appearance_settings']) ? 
    json_decode($store_settings['appearance_settings'], true) : [];
$display_settings = isset($store_settings['display_settings']) ? 
    json_decode($store_settings['display_settings'], true) : [];
$maintenance_settings = isset($store_settings['maintenance_settings']) ?
    json_decode($store_settings['maintenance_settings'], true) : [];
$business_hours = isset($store_settings['business_hours']) ?
    json_decode($store_settings['business_hours'], true) : [];
?>
<div class="container mt-4">
    <h1 class="mb-4"><?php echo htmlspecialchars($page_title); ?></h1>
    
    <?php
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    ?>
    
    <!-- Store Settings Forms Here -->
    <!-- Example: Store Appearance Form -->
    <form method="POST" action="store.php">
        <input type="hidden" name="action" value="update_store_appearance">
        <h3>Store Appearance</h3>
        <!-- Form fields for store appearance settings -->
        <div class="form-group
">
            <label for="store_theme">Store Theme</label>
            <select name="store_theme" id="store_theme" class="form-control">
                <option value="light" <?php echo (isset($appearance_settings['theme']) && $appearance_settings['theme'] === 'light') ? 'selected' : ''; ?>>Light</option>
                <option value="dark" <?php echo (isset($appearance_settings['theme']) && $appearance_settings['theme'] === 'dark') ? 'selected' : ''; ?>>Dark</option>
            </select>
        </div>
        <!-- Add other appearance settings fields here -->
        <button type="submit" class="btn btn-primary mt-3">Save Appearance Settings</button>
    </form>
    <!-- Example: Store Display Form -->
    <form method="POST" action="store.php" class="mt-5">
        <input type="hidden" name="action" value="update_display_settings">
        <h3>Store Display Settings</h3>
        <!-- Form fields for store display settings -->
        <div class="form-group">
            <label for="currency_display">Currency Display</label>
            <select name="currency_display" id="currency_display" class="form-control">
                <option value="symbol" <?php echo (isset($display_settings['currency_display']) && $display_settings['currency_display'] === 'symbol') ? 'selected' : ''; ?>>Symbol</option>
                <option value="code" <?php echo (isset($display_settings['currency_display']) && $display_settings['currency_display'] === 'code') ? 'selected' : ''; ?>>Code</option>
            </select>
        </div>
        <!-- Add other display settings fields here -->
        <button type="submit" class="btn btn-primary mt-3">Save Display Settings</button>
    </form>
    <!-- Example: Maintenance Settings Form -->
    <form method="POST" action="store.php" class="mt-5">
        <input type="hidden" name="action" value="update_maintenance_settings">
        <h3>Maintenance Settings</h3>
        <!-- Form fields for maintenance settings -->
        <div class="form-group">
            <label for="maintenance_mode">Maintenance Mode</label>
            <input type="checkbox" name="maintenance_mode" id="maintenance_mode" <?php echo (isset($maintenance_settings['maintenance_mode']) && $maintenance_settings['maintenance_mode']) ? 'checked' : ''; ?>>
        </div>
        <!-- Add other maintenance settings fields here -->
        <button type="submit" class="btn btn-primary mt-3">Save Maintenance Settings</button>
    </form>
    <!-- Example: Store Hours Form -->
    <form method="POST" action="store.php" class="mt-5">
        <input type="hidden" name="action" value="update_store_hours">
        <h3>Store Hours</h3>
        <!-- Form fields for store hours -->
        <?php
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            $open = $business_hours[$day]['open'] ?? '';
            $close = $business_hours[$day]['close'] ?? '';
            $closed = isset($business_hours[$day]['closed']) ? $business_hours[$day]['closed'] : 0;
            ?>
            <div class="form-group">
                <label><?php echo ucfirst($day); ?></label>
                <input type="time" name="<?php echo $day; ?>_open" value="<?php echo htmlspecialchars($open); ?>" <?php echo $closed ? 'disabled' : ''; ?>>
                to
                <input type="time" name="<?php echo $day; ?>_close" value="<?php echo htmlspecialchars($close); ?>" <?php echo $closed ? 'disabled' : ''; ?>>
                <label>
                    <input type="checkbox" name="<?php echo $day; ?>_closed" <?php echo $closed ? 'checked' : ''; ?> onchange="this.checked ? (this.previousElementSibling.previousElementSibling.disabled = true, this.previousElementSibling.disabled = true) : (this.previousElementSibling.previousElementSibling.disabled = false, this.previousElementSibling.disabled = false);">
                    Closed
                </label>
            </div>
            <?php
        }
        ?>
        <button type="submit" class="btn btn-primary mt-3">Save Store Hours</button>
    </form>
</div>
<?php
require_once '../../includes/footer.php';
?>