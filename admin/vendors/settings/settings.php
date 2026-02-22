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
            u.*,
            vs.store_name, vs.store_description, vs.store_logo, vs.store_banner,
            vs.store_address, vs.store_phone, vs.store_email, vs.store_website,
            vs.store_social_facebook, vs.store_social_instagram, vs.store_social_twitter,
            vs.store_social_linkedin, vs.store_policy, vs.return_policy,
            vs.shipping_policy, vs.payment_methods, vs.store_currency,
            vs.store_timezone, vs.store_language, vs.business_hours,
            vs.min_order_amount, vs.free_shipping_threshold,
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
    
    // Get vendor categories
    $stmt = $db->prepare("
        SELECT vc.* 
        FROM vendor_categories vc
        ORDER BY vc.name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    $vendor = [];
    $categories = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_general') {
            // Update general settings
            $store_name = trim($_POST['store_name'] ?? '');
            $store_description = trim($_POST['store_description'] ?? '');
            $store_category = $_POST['store_category'] ?? '';
            $store_phone = trim($_POST['store_phone'] ?? '');
            $store_email = trim($_POST['store_email'] ?? '');
            $store_website = trim($_POST['store_website'] ?? '');
            $store_currency = $_POST['store_currency'] ?? 'USD';
            $store_timezone = $_POST['store_timezone'] ?? 'UTC';
            $store_language = $_POST['store_language'] ?? 'en';
            
            // Validate inputs
            if (empty($store_name)) {
                throw new Exception('Store name is required.');
            }
            
            if (!filter_var($store_email, FILTER_VALIDATE_EMAIL) && !empty($store_email)) {
                throw new Exception('Invalid email format.');
            }
            
            // Check if vendor_settings record exists
            $stmt = $db->prepare("SELECT vendor_id FROM vendor_settings WHERE vendor_id = ?");
            $stmt->execute([$vendor_id]);
            
            if ($stmt->fetch()) {
                // Update existing
                $sql = "UPDATE vendor_settings SET 
                        store_name = ?, store_description = ?, vendor_category = ?,
                        store_phone = ?, store_email = ?, store_website = ?,
                        store_currency = ?, store_timezone = ?, store_language = ?,
                        updated_at = NOW()
                        WHERE vendor_id = ?";
            } else {
                // Insert new
                $sql = "INSERT INTO vendor_settings 
                        (vendor_id, store_name, store_description, vendor_category,
                         store_phone, store_email, store_website, store_currency,
                         store_timezone, store_language, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $store_name, $store_description, $store_category,
                $store_phone, $store_email, $store_website,
                $store_currency, $store_timezone, $store_language,
                $vendor_id
            ]);
            
            // Also update user table if email changed
            if ($store_email && $store_email !== $vendor['email']) {
                $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$store_email, $vendor_id]);
                $_SESSION['email'] = $store_email;
            }
            
            $_SESSION['success'] = 'General settings updated successfully!';
            redirect('settings.php');
            
        } elseif ($action === 'upload_logo') {
            // Handle logo upload
            if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] === UPLOAD_ERR_OK) {
                $logo = uploadVendorImage($_FILES['store_logo'], 'logo');
                
                $stmt = $db->prepare("
                    UPDATE vendor_settings 
                    SET store_logo = ?, updated_at = NOW()
                    WHERE vendor_id = ?
                ");
                $stmt->execute([$logo, $vendor_id]);
                
                $_SESSION['success'] = 'Store logo uploaded successfully!';
                redirect('settings.php');
            }
            
        } elseif ($action === 'upload_banner') {
            // Handle banner upload
            if (isset($_FILES['store_banner']) && $_FILES['store_banner']['error'] === UPLOAD_ERR_OK) {
                $banner = uploadVendorImage($_FILES['store_banner'], 'banner');
                
                $stmt = $db->prepare("
                    UPDATE vendor_settings 
                    SET store_banner = ?, updated_at = NOW()
                    WHERE vendor_id = ?
                ");
                $stmt->execute([$banner, $vendor_id]);
                
                $_SESSION['success'] = 'Store banner uploaded successfully!';
                redirect('settings.php');
            }
            
        } elseif ($action === 'update_policies') {
            // Update store policies
            $store_policy = trim($_POST['store_policy'] ?? '');
            $return_policy = trim($_POST['return_policy'] ?? '');
            $shipping_policy = trim($_POST['shipping_policy'] ?? '');
            $payment_methods = json_encode($_POST['payment_methods'] ?? []);
            
            $stmt = $db->prepare("
                UPDATE vendor_settings 
                SET store_policy = ?, return_policy = ?, 
                    shipping_policy = ?, payment_methods = ?,
                    updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([
                $store_policy, $return_policy,
                $shipping_policy, $payment_methods,
                $vendor_id
            ]);
            
            $_SESSION['success'] = 'Store policies updated successfully!';
            redirect('settings.php');
            
        } elseif ($action === 'update_social') {
            // Update social media links
            $social_facebook = trim($_POST['social_facebook'] ?? '');
            $social_instagram = trim($_POST['social_instagram'] ?? '');
            $social_twitter = trim($_POST['social_twitter'] ?? '');
            $social_linkedin = trim($_POST['social_linkedin'] ?? '');
            
            // Validate URLs
            $urls = [
                'facebook' => $social_facebook,
                'instagram' => $social_instagram,
                'twitter' => $social_twitter,
                'linkedin' => $social_linkedin
            ];
            
            foreach ($urls as $platform => $url) {
                if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                    throw new Exception("Invalid $platform URL format.");
                }
            }
            
            $stmt = $db->prepare("
                UPDATE vendor_settings 
                SET store_social_facebook = ?, store_social_instagram = ?,
                    store_social_twitter = ?, store_social_linkedin = ?,
                    updated_at = NOW()
                WHERE vendor_id = ?
            ");
            $stmt->execute([
                $social_facebook, $social_instagram,
                $social_twitter, $social_linkedin,
                $vendor_id
            ]);
            
            $_SESSION['success'] = 'Social media links updated!';
            redirect('settings.php');
        }
        
    } catch(Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Helper function for image upload
function uploadVendorImage($file, $type = 'logo') {
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_error = $file['error'];
    
    // Check for errors
    if ($file_error !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error: ' . $file_error);
    }
    
    // Check file size
    if ($file_size > $max_size) {
        throw new Exception('File size too large. Maximum size is 5MB.');
    }
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Check extension
    if (!in_array($file_ext, $allowed_ext)) {
        throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WebP');
    }
    
    // Generate unique filename
    $new_filename = 'vendor_' . $_SESSION['user_id'] . '_' . $type . '_' . time() . '.' . $file_ext;
    $upload_path = SITE_URL . 'uploads/vendors/' . $new_filename;
    
    // Create directory if not exists
    $upload_dir = dirname($upload_path);
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Move uploaded file
     if (!move_uploaded_file($file_tmp, $upload_path)) {
         throw new Exception('Failed to upload file.');
    }
    
    // Resize image if needed
    if ($type === 'logo') {
        resizeImage($upload_path, 300, 300);
    } elseif ($type === 'banner') {
        resizeImage($upload_path, 1200, 400);
    }
    
    return $new_filename;
}

function resizeImage($path, $max_width, $max_height) {
    $info = getimagesize($path);
    $type = $info[2];
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($path);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($path);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($path);
            break;
        default:
            return false;
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Calculate new dimensions
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = floor($width * $ratio);
    $new_height = floor($height * $ratio);
    
    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagecolortransparent($new_image, imagecolorallocatealpha($new_image, 0, 0, 0, 127));
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }
    
    // Resize
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, 
                       $new_width, $new_height, $width, $height);
    
    // Save resized image
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($new_image, $path, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($new_image, $path, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($new_image, $path);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($new_image, $path, 90);
            break;
    }
    
    // Free memory
    imagedestroy($image);
    imagedestroy($new_image);
    
    return true;
}
?>
<div class="dashboard-container">
    <?php include_once '../../includes/vendor-sidebar.php'; ?>
    
    <main class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 fw-bold">Vendor Settings</h1>
                <p class="text-muted mb-0">Manage your store settings and preferences</p>
            </div>
            <div class="btn-group">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                </a>
                <button type="button" class="btn btn-primary" onclick="saveAllSettings()">
                    <i class="fas fa-save me-2"></i> Save All Changes
                </button>
            </div>
        </div>
        
        <!-- Settings Navigation -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs settings-tabs" id="settingsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" 
                                data-bs-target="#general" type="button">
                            <i class="fas fa-store me-2"></i> General
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="store-tab" data-bs-toggle="tab" 
                                data-bs-target="#store" type="button">
                            <i class="fas fa-building me-2"></i> Store
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="policies-tab" data-bs-toggle="tab" 
                                data-bs-target="#policies" type="button">
                            <i class="fas fa-file-contract me-2"></i> Policies
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="media-tab" data-bs-toggle="tab" 
                                data-bs-target="#media" type="button">
                            <i class="fas fa-images me-2"></i> Media
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="social-tab" data-bs-toggle="tab" 
                                data-bs-target="#social" type="button">
                            <i class="fas fa-share-alt me-2"></i> Social
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" 
                                data-bs-target="#advanced" type="button">
                            <i class="fas fa-cogs me-2"></i> Advanced
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Settings Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- General Settings Tab -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-store me-2"></i> General Store Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="generalForm">
                            <input type="hidden" name="action" value="update_general">
                            
                            <div class="row g-4">
                                <!-- Store Name -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Store Name *</label>
                                        <input type="text" name="store_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($vendor['store_name'] ?? ''); ?>"
                                               required>
                                        <small class="text-muted">This will be displayed to customers</small>
                                    </div>
                                </div>
                                
                                <!-- Store Category -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Store Category *</label>
                                        <select name="store_category" class="form-select" required>
                                            <option value="">Select Category</option>
                                            <?php foreach($categories as $cat): ?>
                                            <option value="<?php echo $cat['slug']; ?>" 
                                                <?php echo ($vendor['vendor_category'] ?? '') === $cat['slug'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Main category for your store</small>
                                    </div>
                                </div>
                                
                                <!-- Store Description -->
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Store Description</label>
                                        <textarea name="store_description" class="form-control" rows="4"
                                                  placeholder="Tell customers about your store..."><?php echo htmlspecialchars($vendor['store_description'] ?? ''); ?></textarea>
                                        <small class="text-muted">Brief description shown on your store page</small>
                                    </div>
                                </div>
                                
                                <!-- Contact Info -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Store Phone</label>
                                        <input type="tel" name="store_phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($vendor['store_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Store Email *</label>
                                        <input type="email" name="store_email" class="form-control" 
                                               value="<?php echo htmlspecialchars($vendor['store_email'] ?? $vendor['email']); ?>"
                                               required>
                                    </div>
                                </div>
                                
                                <!-- Website -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Website URL</label>
                                        <div class="input-group">
                                            <span class="input-group-text">https://</span>
                                            <input type="url" name="store_website" class="form-control" 
                                                   value="<?php echo htmlspecialchars($vendor['store_website'] ?? ''); ?>"
                                                   placeholder="yourstore.com">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Currency -->
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Currency</label>
                                        <select name="store_currency" class="form-select">
                                            <?php
                                            $currencies = [
                                                'USD' => 'US Dollar ($)',
                                                'EUR' => 'Euro (€)',
                                                'GBP' => 'British Pound (£)',
                                                'INR' => 'Indian Rupee (₹)',
                                                'CAD' => 'Canadian Dollar (C$)',
                                                'AUD' => 'Australian Dollar (A$)',
                                                'JPY' => 'Japanese Yen (¥)'
                                            ];
                                            foreach($currencies as $code => $name): ?>
                                            <option value="<?php echo $code; ?>"
                                                <?php echo ($vendor['store_currency'] ?? 'USD') === $code ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Timezone -->
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Timezone</label>
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
                                                'Asia/Tokyo' => 'Tokyo',
                                                'Australia/Sydney' => 'Sydney'
                                            ];
                                            foreach($timezones as $tz => $label): ?>
                                            <option value="<?php echo $tz; ?>"
                                                <?php echo ($vendor['store_timezone'] ?? 'UTC') === $tz ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Language -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Store Language</label>
                                        <select name="store_language" class="form-select">
                                            <option value="en" <?php echo ($vendor['store_language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="hi" <?php echo ($vendor['store_language'] ?? 'en') === 'hi' ? 'selected' : ''; ?>>Hindi</option>
                                            <option value="es" <?php echo ($vendor['store_language'] ?? 'en') === 'es' ? 'selected' : ''; ?>>Spanish</option>
                                            <option value="fr" <?php echo ($vendor['store_language'] ?? 'en') === 'fr' ? 'selected' : ''; ?>>French</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save General Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Store Statistics -->
                        <div class="mt-5 pt-4 border-top">
                            <h6 class="fw-bold mb-3">Store Statistics</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Vendor Since</small>
                                        <strong class="d-block"><?php echo $vendor['vendor_since_formatted'] ?? 'N/A'; ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Account Status</small>
                                        <span class="badge bg-<?php 
                                            echo $vendor['vendor_status'] === 'approved' ? 'success' : 
                                                 ($vendor['vendor_status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($vendor['vendor_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Store Rating</small>
                                        <div class="text-warning">
                                            <?php 
                                            $rating = $vendor['vendor_rating'] ?? 0;
                                            for($i = 1; $i <= 5; $i++): 
                                                $starClass = $i <= floor($rating) ? 'fas fa-star' : 
                                                            ($i <= ceil($rating) ? 'fas fa-star-half-alt' : 'far fa-star');
                                            ?>
                                                <i class="<?php echo $starClass; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="text-dark ms-1"><?php echo number_format($rating, 1); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Total Products</small>
                                        <strong class="d-block"><?php echo $vendor['total_products'] ?? 0; ?></strong>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Bank</small>
                                        <p>Select your bank and withdrawal request</p>
                                        <a href="bank.php" class="text-decoration-none p-2 btn btn-warning text-white">View Bank Details</a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">integration</small>
                                        <p>Select your integration</p>
                                        <a href="integrations.php" class="text-decoration-none p-2 btn btn-warning text-white">View Integration Details</a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">notification</small>
                                        <p>Select your notification system</p>
                                        <a href="notifications.php" class="text-decoration-none p-2 btn btn-warning text-white">View Notification Details</a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Security</small>
                                        <p>Select your security settings</p>
                                        <a href="security.php" class="text-decoration-none p-2 btn btn-warning text-white">View Security Details</a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Shipping</small>
                                        <p>Select your shipping settings</p>
                                        <a href="shipping.php" class="text-decoration-none p-2 btn btn-warning text-white">View Shipping Details</a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">Store</small>
                                        <p>Select your store settings</p>
                                        <a href="store.php" class="text-decoration-none p-2 btn btn-warning text-white">View Store Details</a>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted d-block">profile</small>
                                        <p>Select your profile settings</p>
                                        <a href="profile.php" class="text-decoration-none p-2 btn btn-warning text-white">View Profile Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Store Settings Tab -->
            <div class="tab-pane fade" id="store" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-building me-2"></i> Store Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="storeForm">
                            <input type="hidden" name="action" value="update_store">
                            
                            <div class="row g-4">
                                <!-- Business Hours -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-3">Business Hours</h6>
                                    <?php
                                    $business_hours = $vendor['business_hours'] ? json_decode($vendor['business_hours'], true) : 
                                        [
                                            'monday' => ['open' => '09:00', 'close' => '18:00'],
                                            'tuesday' => ['open' => '09:00', 'close' => '18:00'],
                                            'wednesday' => ['open' => '09:00', 'close' => '18:00'],
                                            'thursday' => ['open' => '09:00', 'close' => '18:00'],
                                            'friday' => ['open' => '09:00', 'close' => '18:00'],
                                            'saturday' => ['open' => '10:00', 'close' => '16:00'],
                                            'sunday' => ['open' => '', 'close' => '']
                                        ];
                                    ?>
                                    <div class="table-responsive">
                                        <table class="table">
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
                                                foreach($days as $key => $day): 
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
                                                            <label class="form-check-label">Open</label>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Store Address -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-3 mt-4">Store Address</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Full Address</label>
                                        <textarea name="store_address" class="form-control" rows="3"><?php echo htmlspecialchars($vendor['store_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <!-- Order Settings -->
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 mt-4">Order Settings</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Minimum Order Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="min_order_amount" class="form-control" 
                                                   step="0.01" min="0"
                                                   value="<?php echo $vendor['min_order_amount'] ?? '0.00'; ?>">
                                        </div>
                                        <small class="text-muted">Minimum amount for order placement</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h6 class="fw-bold mb-3 mt-4">Free Shipping</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Free Shipping Threshold</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" name="free_shipping_threshold" class="form-control" 
                                                   step="0.01" min="0"
                                                   value="<?php echo $vendor['free_shipping_threshold'] ?? '0.00'; ?>">
                                        </div>
                                        <small class="text-muted">Order amount for free shipping</small>
                                    </div>
                                </div>
                                
                                <!-- Inventory Settings -->
                                <div class="col-12">
                                    <h6 class="fw-bold mb-3 mt-4">Inventory Settings</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="low_stock_notify" 
                                                       id="lowStockNotify" checked>
                                                <label class="form-check-label" for="lowStockNotify">
                                                    Low Stock Notifications
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="auto_hide_out_of_stock" 
                                                       id="autoHideOutOfStock" checked>
                                                <label class="form-check-label" for="autoHideOutOfStock">
                                                    Auto-hide Out of Stock Products
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="allow_backorders" 
                                                       id="allowBackorders">
                                                <label class="form-check-label" for="allowBackorders">
                                                    Allow Backorders
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Store Settings
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Policies Tab -->
            <div class="tab-pane fade" id="policies" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-file-contract me-2"></i> Store Policies
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="policiesForm">
                            <input type="hidden" name="action" value="update_policies">
                            
                            <!-- Store Policy -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Store Policy / Terms of Service</label>
                                <textarea name="store_policy" class="form-control" rows="6"
                                          placeholder="Describe your store policies, terms of service, and general guidelines..."><?php echo htmlspecialchars($vendor['store_policy'] ?? ''); ?></textarea>
                                <small class="text-muted">This will be displayed on your store page</small>
                            </div>
                            
                            <!-- Return Policy -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Return & Refund Policy</label>
                                <textarea name="return_policy" class="form-control" rows="6"
                                          placeholder="Describe your return and refund policy..."><?php echo htmlspecialchars($vendor['return_policy'] ?? ''); ?></textarea>
                                <small class="text-muted">Important for customer trust</small>
                            </div>
                            
                            <!-- Shipping Policy -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Shipping Policy</label>
                                <textarea name="shipping_policy" class="form-control" rows="6"
                                          placeholder="Describe your shipping methods, costs, and delivery times..."><?php echo htmlspecialchars($vendor['shipping_policy'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Payment Methods -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Accepted Payment Methods</label>
                                <div class="row g-3">
                                    <?php
                                    $payment_methods = $vendor['payment_methods'] ? json_decode($vendor['payment_methods'], true) : [];
                                    $all_methods = [
                                        'cod' => 'Cash on Delivery',
                                        'credit_card' => 'Credit/Debit Card',
                                        'paypal' => 'PayPal',
                                        'bank_transfer' => 'Bank Transfer',
                                        'upi' => 'UPI (India)',
                                        'stripe' => 'Stripe',
                                        'razorpay' => 'Razorpay'
                                    ];
                                    ?>
                                    <?php foreach($all_methods as $key => $method): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="payment_methods[]" value="<?php echo $key; ?>"
                                                   id="payment_<?php echo $key; ?>"
                                                   <?php echo in_array($key, $payment_methods) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="payment_<?php echo $key; ?>">
                                                <i class="fas fa-credit-card me-2"></i><?php echo $method; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Policies
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Media Tab -->
            <div class="tab-pane fade" id="media" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-images me-2"></i> Store Media
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Store Logo -->
                        <div class="mb-5">
                            <h6 class="fw-bold mb-3">Store Logo</h6>
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="store-logo-preview mb-3">
                                        <?php if (!empty($vendor['store_logo'])): ?>
                                        <img src="<?php echo SITE_URL; ?>uploads/vendors/<?php echo $vendor['store_logo']; ?>" 
                                             alt="Store Logo" class="img-thumbnail" style="max-height: 200px;">
                                        <?php else: ?>
                                        <div class="border rounded d-flex align-items-center justify-content-center" 
                                             style="height: 200px; background: #f8f9fa;">
                                            <div class="text-center">
                                                <i class="fas fa-store fa-4x text-muted mb-3"></i>
                                                <p class="text-muted">No Logo Uploaded</p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <form method="POST" enctype="multipart/form-data" id="logoForm">
                                        <input type="hidden" name="action" value="upload_logo">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Upload Logo</label>
                                            <input type="file" name="store_logo" class="form-control" 
                                                   accept="image/*" id="logoInput">
                                            <small class="text-muted">
                                                Recommended: 300x300px, PNG or JPG, max 5MB
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="progress d-none" id="logoProgress">
                                                <div class="progress-bar" role="progressbar"></div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-upload me-2"></i> Upload Logo
                                        </button>
                                        
                                        <?php if (!empty($vendor['store_logo'])): ?>
                                        <button type="button" class="btn btn-outline-danger ms-2" 
                                                onclick="deleteLogo()">
                                            <i class="fas fa-trash me-2"></i> Remove Logo
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Store Banner -->
                        <div class="mb-5">
                            <h6 class="fw-bold mb-3">Store Banner</h6>
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="store-banner-preview mb-3">
                                        <?php if (!empty($vendor['store_banner'])): ?>
                                        <img src="<?php echo SITE_URL; ?>uploads/vendors/<?php echo $vendor['store_banner']; ?>" 
                                             alt="Store Banner" class="img-fluid rounded" style="max-height: 300px;">
                                        <?php else: ?>
                                        <div class="border rounded d-flex align-items-center justify-content-center" 
                                             style="height: 200px; background: #f8f9fa;">
                                            <div class="text-center">
                                                <i class="fas fa-image fa-4x text-muted mb-3"></i>
                                                <p class="text-muted">No Banner Uploaded</p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <form method="POST" enctype="multipart/form-data" id="bannerForm">
                                        <input type="hidden" name="action" value="upload_banner">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Upload Banner</label>
                                            <input type="file" name="store_banner" class="form-control" 
                                                   accept="image/*" id="bannerInput">
                                            <small class="text-muted">
                                                Recommended: 1200x400px, PNG or JPG, max 5MB
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="progress d-none" id="bannerProgress">
                                                <div class="progress-bar" role="progressbar"></div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-upload me-2"></i> Upload Banner
                                        </button>
                                        
                                        <?php if (!empty($vendor['store_banner'])): ?>
                                        <button type="button" class="btn btn-outline-danger w-100 mt-2" 
                                                onclick="deleteBanner()">
                                            <i class="fas fa-trash me-2"></i> Remove Banner
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Media Guidelines -->
                        <div class="alert alert-info">
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
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-share-alt me-2"></i> Social Media Links
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="socialForm">
                            <input type="hidden" name="action" value="update_social">
                            
                            <div class="row g-4">
                                <!-- Facebook -->
                                <div class="col-md-6">
                                    <div class="mb-3">
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
                                    <div class="mb-3">
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
                                    <div class="mb-3">
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
                                    <div class="mb-3">
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
                                    <div class="mb-3">
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
                                    <div class="mb-3">
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
                                
                                <!-- Social Media Preview -->
                                <div class="col-12 mt-4">
                                    <h6 class="fw-bold mb-3">Preview</h6>
                                    <div class="border rounded p-4 bg-light">
                                        <div class="d-flex flex-wrap gap-3" id="socialPreview">
                                            <!-- Preview will be generated by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i> Save Social Links
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Advanced Settings Tab -->
            <div class="tab-pane fade" id="advanced" role="tabpanel">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-cogs me-2"></i> Advanced Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Danger Zone -->
                        <div class="border border-danger rounded p-4 mb-4">
                            <h6 class="text-danger fw-bold mb-3">
                                <i class="fas fa-exclamation-triangle me-2"></i> Danger Zone
                            </h6>
                            
                            <div class="row g-3">
                                <!-- Deactivate Store -->
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold">Deactivate Store</h6>
                                        <p class="text-muted small mb-3">
                                            Temporarily hide your store from customers. Products will not be visible.
                                        </p>
                                        <button type="button" class="btn btn-outline-warning" 
                                                data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                            <i class="fas fa-eye-slash me-2"></i> Deactivate Store
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Delete Store -->
                                <div class="col-md-6">
                                    <div class="border rounded p-3">
                                        <h6 class="fw-bold">Delete Store</h6>
                                        <p class="text-muted small mb-3">
                                            Permanently delete your vendor account and all data. This action cannot be undone.
                                        </p>
                                        <button type="button" class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" data-bs-target="#deleteModal">
                                            <i class="fas fa-trash me-2"></i> Delete Store
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- API Settings -->
                        <div class="border rounded p-4 mb-4">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-code me-2"></i> API & Integration
                            </h6>
                            
                            <!-- API Key -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">API Key</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" 
                                           value="sk_live_<?php echo substr(md5($_SESSION['user_id']), 0, 16); ?>..." 
                                           readonly id="apiKey">
                                    <button class="btn btn-outline-secondary" type="button" onclick="copyApiKey()">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" type="button" onclick="regenerateApiKey()">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Use this key for API integration</small>
                            </div>
                            
                            <!-- Webhooks -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">Webhook URL</label>
                                <div class="input-group">
                                    <input type="url" class="form-control" 
                                           placeholder="https://yourdomain.com/webhook"
                                           value="<?php echo htmlspecialchars($vendor['webhook_url'] ?? ''); ?>">
                                    <button class="btn btn-outline-primary" type="button">Save</button>
                                </div>
                                <small class="text-muted">Receive real-time notifications</small>
                            </div>
                        </div>
                        
                        <!-- Export Data -->
                        <div class="border rounded p-4">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-download me-2"></i> Data Export
                            </h6>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded">
                                        <i class="fas fa-boxes fa-2x text-primary mb-3"></i>
                                        <h6>Products Data</h6>
                                        <button class="btn btn-sm btn-outline-primary mt-2" 
                                                onclick="exportData('products')">
                                            <i class="fas fa-download me-1"></i> Export CSV
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded">
                                        <i class="fas fa-shopping-cart fa-2x text-success mb-3"></i>
                                        <h6>Orders Data</h6>
                                        <button class="btn btn-sm btn-outline-success mt-2" 
                                                onclick="exportData('orders')">
                                            <i class="fas fa-download me-1"></i> Export CSV
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="text-center p-3 border rounded">
                                        <i class="fas fa-chart-line fa-2x text-info mb-3"></i>
                                        <h6>Analytics Data</h6>
                                        <button class="btn btn-sm btn-outline-info mt-2" 
                                                onclick="exportData('analytics')">
                                            <i class="fas fa-download me-1"></i> Export CSV
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
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i> Deactivate Store
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate your store?</p>
                <ul class="text-muted small">
                    <li>Your products will be hidden from customers</li>
                    <li>You will not receive new orders</li>
                    <li>Existing orders will continue to process</li>
                    <li>You can reactivate anytime</li>
                </ul>
                <div class="form-check mb-3">
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
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-trash me-2"></i> Delete Store
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger fw-bold">Warning: This action cannot be undone!</p>
                <p>All your store data will be permanently deleted:</p>
                <ul class="text-muted small">
                    <li>All products and inventory</li>
                    <li>Order history and earnings</li>
                    <li>Customer reviews and ratings</li>
                    <li>Store settings and configurations</li>
                </ul>
                <div class="mb-3">
                    <label class="form-label">Type "DELETE" to confirm</label>
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

<!-- Settings CSS -->
<style>
.settings-tabs .nav-link {
    border: none;
    padding: 1rem 1.5rem;
    color: #6c757d;
    font-weight: 500;
    border-bottom: 2px solid transparent;
}

.settings-tabs .nav-link:hover {
    color: #495057;
    border-bottom-color: #dee2e6;
}

.settings-tabs .nav-link.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background: transparent;
}

.store-logo-preview img,
.store-banner-preview img {
    object-fit: contain;
    width: 100%;
}

/* Form styling */
.form-label.fw-bold {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #495057;
}

/* Progress bar */
.progress {
    height: 8px;
    border-radius: 4px;
}

.progress-bar {
    border-radius: 4px;
    background-color: #0d6efd;
    transition: width 0.3s ease;
}

/* Social preview */
.social-preview-item {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 50px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s ease;
}

.social-preview-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    color: #0d6efd;
}

.social-preview-item i {
    font-size: 1.2rem;
    margin-right: 8px;
}

/* Danger zone */
.border-danger {
    border-width: 2px !important;
}
</style>

<!-- Settings JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tabs
    const triggerTabList = [].slice.call(document.querySelectorAll('#settingsTab button'));
    triggerTabList.forEach(function (triggerEl) {
        const tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
    
    // Form submissions
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
            previewImage(this, '.store-logo-preview');
        });
    }
    
    // Image preview for banner
    const bannerInput = document.getElementById('bannerInput');
    if (bannerInput) {
        bannerInput.addEventListener('change', function() {
            previewImage(this, '.store-banner-preview');
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

function submitForm(form) {
    const formData = new FormData(form);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Reload page to show success message
        window.location.reload();
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}

function previewImage(input, previewSelector) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector(previewSelector);
            if (preview) {
                preview.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-height: 200px;">`;
            }
        }
        reader.readAsDataURL(file);
    }
}

function updateSocialPreview() {
    const preview = document.getElementById('socialPreview');
    if (!preview) return;
    
    const socialFields = [
        { id: 'social_facebook', icon: 'fab fa-facebook', color: 'text-primary', label: 'Facebook' },
        { id: 'social_instagram', icon: 'fab fa-instagram', color: 'text-danger', label: 'Instagram' },
        { id: 'social_twitter', icon: 'fab fa-twitter', color: 'text-info', label: 'Twitter' },
        { id: 'social_linkedin', icon: 'fab fa-linkedin', color: 'text-primary', label: 'LinkedIn' },
        { id: 'social_youtube', icon: 'fab fa-youtube', color: 'text-danger', label: 'YouTube' },
        { id: 'social_pinterest', icon: 'fab fa-pinterest', color: 'text-danger', label: 'Pinterest' }
    ];
    
    let html = '';
    socialFields.forEach(social => {
        const input = document.querySelector(`[name="${social.id}"]`);
        if (input && input.value) {
            html += `
                <a href="https://${input.value}" target="_blank" class="social-preview-item">
                    <i class="${social.icon} ${social.color}"></i>
                    ${social.label}
                </a>
            `;
        }
    });
    
    preview.innerHTML = html || '<p class="text-muted">No social links added yet.</p>';
}

// Update preview when social inputs change
document.querySelectorAll('#socialForm input').forEach(input => {
    input.addEventListener('input', updateSocialPreview);
});

function deleteLogo() {
    if (confirm('Are you sure you want to remove the store logo?')) {
        fetch('action/delete-logo.php', {
            method: 'POST',
            body: new FormData()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function deleteBanner() {
    if (confirm('Are you sure you want to remove the store banner?')) {
        fetch('action/delete-banner.php', {
            method: 'POST',
            body: new FormData()
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function copyApiKey() {
    const apiKey = document.getElementById('apiKey');
    apiKey.select();
    apiKey.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(apiKey.value);
    
    // Show success message
    const originalText = event.target.innerHTML;
    event.target.innerHTML = '<i class="fas fa-check"></i> Copied!';
    event.target.classList.add('btn-success');
    
    setTimeout(() => {
        event.target.innerHTML = originalText;
        event.target.classList.remove('btn-success');
    }, 2000);
}

function regenerateApiKey() {
    if (confirm('Are you sure you want to regenerate your API key? This will break existing integrations.')) {
        fetch('action/regenerate-api.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('API key regenerated successfully!');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function deactivateStore() {
    fetch('action/deactivate-store.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Store deactivated successfully!');
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteStore() {
    fetch('action/delete-store.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Store deleted successfully!');
            window.location.href = '<?php echo SITE_URL; ?>';
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function exportData(type) {
    window.location.href = `action/export.php?type=${type}`;
}

function saveAllSettings() {
    // This would save all forms at once
    alert('Save all feature would be implemented here.');
}
</script>

<?php require_once '../../includes/footer.php'; ?>