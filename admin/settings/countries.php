<?php
// admin/settings/countries.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Sirf admin access
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    header('Location: ../index.php');
    exit();
}

$page_title = 'Manage Countries';
require_once '../includes/header.php';

try {
    $db = getDB();
    
    //  YAHAN QUERY LAGAO - Total countries count
    $stmt = $db->query("SELECT COUNT(*) as total_countries FROM countries");
    $total_countries = $stmt->fetch()['total_countries'];
    
    //  YAHAN QUERY LAGAO - Active countries count
    $stmt = $db->query("SELECT COUNT(*) as active_countries FROM countries WHERE is_active = 1");
    $active_countries = $stmt->fetch()['active_countries'];
    
    //  YAHAN QUERY LAGAO - List all countries alphabetically
    $stmt = $db->query("SELECT code, name, is_active FROM countries ORDER BY name");
    $countries = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<div class="container-fluid py-4">
    <h1>Manage Countries</h1>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Total Countries</h5>
                    <h2><?php echo $total_countries; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Active Countries</h5>
                    <h2><?php echo $active_countries; ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Countries Table -->
    <div class="card">
        <div class="card-header">
            <h5>All Countries</h5>
        </div>
        <div class="card-body">
            <table class="table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Country Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($countries as $country): ?>
                    <tr>
                        <td><?php echo $country['code']; ?></td>
                        <td><?php echo $country['name']; ?></td>
                        <td>
                            <?php if($country['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>