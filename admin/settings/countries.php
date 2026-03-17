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
    
    // Total countries count
    $stmt = $db->query("SELECT COUNT(*) as total_countries FROM countries");
    $total_countries = $stmt->fetch()['total_countries'];
    
    // Active countries count
    $stmt = $db->query("SELECT COUNT(*) as active_countries FROM countries WHERE is_active = 1");
    $active_countries = $stmt->fetch()['active_countries'];
    
    // List all countries alphabetically with additional info
    $stmt = $db->query("SELECT code, name, currency_code, currency_symbol, phone_code, is_active FROM countries ORDER BY name");
    $countries = $stmt->fetchAll();
    
    // Get continent groups (optional - you can add continent column to countries table)
    $continents = [
        'Asia' => ['PK', 'IN', 'BD', 'AE', 'SA', 'MY', 'SG', 'ID', 'AF', 'CN', 'JP', 'KR'],
        'Europe' => ['GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'CH', 'SE', 'NO', 'DK'],
        'North America' => ['US', 'CA', 'MX'],
        'Oceania' => ['AU', 'NZ'],
        'South America' => ['BR', 'AR', 'CL', 'CO'],
        'Africa' => ['ZA', 'NG', 'EG', 'KE']
    ];
    
} catch(PDOException $e) {
    $error = $e->getMessage();
}
?>

<style>
:root {
    --primary: #4361ee;
    --primary-dark: #3a0ca3;
    --primary-light: #4895ef;
    --primary-gradient: linear-gradient(135deg, #4361ee, #3a0ca3);
    
    --success: #06d6a0;
    --success-dark: #0ca678;
    --success-light: #80ffdb;
    --success-gradient: linear-gradient(135deg, #06d6a0, #0ca678);
    
    --warning: #ffb703;
    --warning-dark: #f77f00;
    --warning-light: #ffe066;
    --warning-gradient: linear-gradient(135deg, #ffb703, #f77f00);
    
    --danger: #ef476f;
    --danger-dark: #d62828;
    --danger-light: #ffafcc;
    --danger-gradient: linear-gradient(135deg, #ef476f, #d62828);
    
    --info: #4cc9f0;
    --info-dark: #0096c7;
    --info-light: #a2d6f9;
    --info-gradient: linear-gradient(135deg, #4cc9f0, #0096c7);
    
    --dark: #2b2d42;
    --dark-light: #4a4e69;
    --light: #f8f9fa;
    
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-300: #dee2e6;
    --gray-400: #ced4da;
    --gray-500: #adb5bd;
    --gray-600: #6c757d;
    --gray-700: #495057;
    --gray-800: #343a40;
    --gray-900: #212529;
    
    --shadow-sm: 0 2px 4px rgba(0,0,0,0.02);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.05);
    --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
    --shadow-xl: 0 20px 25px rgba(0,0,0,0.15);
    --shadow-2xl: 0 25px 50px rgba(0,0,0,0.2);
    
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-bounce: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    
    --border-radius-sm: 8px;
    --border-radius-md: 12px;
    --border-radius-lg: 16px;
    --border-radius-xl: 20px;
    --border-radius-2xl: 24px;
    --border-radius-full: 9999px;
}

/* Page Container */
.page-container {
    padding: 2rem;
    background: var(--gray-100);
    min-height: 100vh;
}

/* Page Header */
.page-header {
    background: white;
    border-radius: var(--border-radius-xl);
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-md);
    border: 1px solid var(--gray-200);
    position: relative;
    overflow: hidden;
    animation: slideIn 0.5s ease;
}

.page-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary-gradient);
    border-radius: var(--border-radius-full);
}

.page-header h1 {
    font-size: 2rem;
    font-weight: 800;
    background: var(--primary-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.page-header h1 i {
    font-size: 2rem;
    color: var(--primary);
    -webkit-text-fill-color: initial;
}

.page-header p {
    color: var(--gray-600);
    font-size: 1rem;
    margin-bottom: 0;
}

/* Stat Cards */
.stat-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
    transition: var(--transition);
    animation: slideIn 0.5s ease;
    animation-fill-mode: both;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary);
}

.stat-card .stat-icon {
    width: 70px;
    height: 70px;
    border-radius: var(--border-radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    flex-shrink: 0;
}

.stat-card .stat-content {
    flex: 1;
}

.stat-card .stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--gray-800);
    margin-bottom: 0.25rem;
    line-height: 1.2;
}

.stat-card .stat-label {
    color: var(--gray-600);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.stat-card .stat-footer {
    margin-top: 0.5rem;
    font-size: 0.85rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Countries Card */
.countries-card {
    background: white;
    border-radius: var(--border-radius-xl);
    border: 1px solid var(--gray-200);
    overflow: hidden;
    box-shadow: var(--shadow-lg);
    animation: slideIn 0.5s ease;
}

.countries-card .card-header {
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, var(--gray-100), white);
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.countries-card .card-header h5 {
    font-weight: 700;
    color: var(--gray-800);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.countries-card .card-header h5 i {
    color: var(--primary);
}

.countries-card .card-header .header-actions {
    display: flex;
    gap: 0.75rem;
}

.countries-card .card-body {
    padding: 2rem;
}

/* Search and Filter Bar */
.search-filter-bar {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

.search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.search-box i {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray-500);
}

.search-box input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    font-size: 0.95rem;
    transition: var(--transition);
}

.search-box input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
    outline: none;
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 0.75rem 1.5rem;
    border: 2px solid var(--gray-200);
    border-radius: var(--border-radius-lg);
    background: white;
    color: var(--gray-700);
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.filter-btn.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

/* Countries Table */
.countries-table-container {
    overflow-x: auto;
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
}

.countries-table {
    width: 100%;
    border-collapse: collapse;
}

.countries-table th {
    padding: 1rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: var(--gray-700);
    background: var(--gray-100);
    border-bottom: 2px solid var(--gray-300);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.countries-table td {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--gray-200);
    color: var(--gray-600);
    transition: var(--transition);
}

.countries-table tbody tr {
    transition: var(--transition);
    animation: fadeIn 0.3s ease;
}

.countries-table tbody tr:hover {
    background: linear-gradient(135deg, var(--gray-100), white);
}

.countries-table tbody tr:hover td {
    color: var(--gray-800);
}

/* Country Code Badge */
.country-code {
    display: inline-block;
    padding: 0.35rem 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--primary);
    border: 1px solid var(--gray-200);
}

/* Currency Info */
.currency-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.currency-badge {
    padding: 0.25rem 0.75rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-md);
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--gray-700);
}

.currency-symbol {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--success);
}

/* Phone Code */
.phone-code {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: var(--info-dark);
}

/* Status Badges */
.status-badge {
    padding: 0.35rem 1rem;
    border-radius: var(--border-radius-full);
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.status-badge.active {
    background: rgba(6, 214, 160, 0.15);
    color: var(--success);
    border: 1px solid rgba(6, 214, 160, 0.3);
}

.status-badge.inactive {
    background: rgba(239, 71, 111, 0.15);
    color: var(--danger);
    border: 1px solid rgba(239, 71, 111, 0.3);
}

.status-badge i {
    font-size: 0.6rem;
}

/* Continent Badge */
.continent-badge {
    padding: 0.25rem 0.75rem;
    border-radius: var(--border-radius-full);
    font-size: 0.7rem;
    font-weight: 600;
    background: var(--gray-100);
    color: var(--gray-600);
}

/* Action Buttons */
.action-btn {
    padding: 0.5rem;
    border-radius: var(--border-radius-md);
    border: 1px solid var(--gray-200);
    background: white;
    color: var(--gray-600);
    transition: var(--transition);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
}

.action-btn:hover {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(67, 97, 238, 0.3);
}

.action-btn.edit:hover {
    background: linear-gradient(135deg, var(--warning), var(--warning-dark));
}

.action-btn.delete:hover {
    background: linear-gradient(135deg, var(--danger), var(--danger-dark));
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
}

.pagination-btn {
    padding: 0.5rem 1rem;
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius-md);
    background: white;
    color: var(--gray-700);
    transition: var(--transition);
    cursor: pointer;
    min-width: 40px;
}

.pagination-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    background: rgba(67, 97, 238, 0.05);
}

.pagination-btn.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

/* Continent Filter */
.continent-filter {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--gray-100);
    border-radius: var(--border-radius-lg);
}

.continent-tag {
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-full);
    background: white;
    border: 1px solid var(--gray-200);
    color: var(--gray-600);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.continent-tag:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.continent-tag.active {
    background: var(--primary-gradient);
    color: white;
    border-color: transparent;
}

/* Modal Styles */
.modal-content {
    border: none;
    border-radius: var(--border-radius-xl);
    overflow: hidden;
    box-shadow: var(--shadow-2xl);
}

.modal-header {
    background: var(--primary-gradient);
    color: white;
    border-bottom: none;
    padding: 1.5rem 2rem;
    position: relative;
    overflow: hidden;
}

.modal-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

.modal-header .modal-title {
    font-weight: 700;
    font-size: 1.25rem;
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.8;
    transition: var(--transition);
    position: relative;
    z-index: 1;
}

.modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

.modal-footer {
    border-top: 1px solid var(--gray-200);
    padding: 1.5rem 2rem;
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

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .page-container {
        padding: 1rem;
    }
    
    .page-header {
        padding: 1.5rem;
    }
    
    .page-header h1 {
        font-size: 1.5rem;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .stat-card .stat-icon {
        margin: 0 auto;
    }
    
    .search-filter-bar {
        flex-direction: column;
    }
    
    .filter-buttons {
        justify-content: stretch;
    }
    
    .filter-btn {
        flex: 1;
    }
    
    .countries-table th,
    .countries-table td {
        padding: 0.75rem;
    }
    
    .continent-filter {
        justify-content: center;
    }
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: var(--border-radius-full);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    border-radius: var(--border-radius-full);
    border: 2px solid var(--gray-100);
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}
</style>

<div class="page-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1>
                    <i class="fas fa-globe-asia"></i>
                    Manage Countries
                </h1>
                <p class="mb-0">View and manage all countries with their currency and phone information</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button class="btn btn-primary" onclick="exportCountries()">
                    <i class="fas fa-download me-2"></i>Export List
                </button>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark));">
                    <i class="fas fa-flag"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($total_countries); ?></div>
                    <div class="stat-label">Total Countries</div>
                    <div class="stat-footer">
                        <i class="fas fa-globe me-1"></i> Worldwide coverage
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, var(--success), var(--success-dark));">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($active_countries); ?></div>
                    <div class="stat-label">Active Countries</div>
                    <div class="stat-footer">
                        <i class="fas fa-percentage me-1"></i> 
                        <?php echo $total_countries > 0 ? round(($active_countries / $total_countries) * 100) : 0; ?>% active
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Countries Card -->
    <div class="countries-card">
        <div class="card-header">
            <h5>
                <i class="fas fa-list"></i>
                All Countries
                <span class="badge bg-primary ms-2"><?php echo count($countries); ?> Records</span>
            </h5>
            <div class="header-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="refreshList()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Search and Filter Bar -->
            <div class="search-filter-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search countries..." onkeyup="filterCountries()">
                </div>
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterByStatus('all')" id="filterAll">
                        <i class="fas fa-globe"></i> All
                    </button>
                    <button class="filter-btn" onclick="filterByStatus('active')" id="filterActive">
                        <i class="fas fa-check-circle"></i> Active
                    </button>
                    <button class="filter-btn" onclick="filterByStatus('inactive')" id="filterInactive">
                        <i class="fas fa-times-circle"></i> Inactive
                    </button>
                </div>
            </div>
            
            <!-- Continent Filter -->
            <div class="continent-filter">
                <span class="continent-tag active" onclick="filterByContinent('all')">All Continents</span>
                <?php foreach(array_keys($continents) as $continent): ?>
                <span class="continent-tag" onclick="filterByContinent('<?php echo $continent; ?>')"><?php echo $continent; ?></span>
                <?php endforeach; ?>
            </div>
            
            <!-- Countries Table -->
            <div class="countries-table-container">
                <table class="countries-table" id="countriesTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Country Name</th>
                            <th>Currency</th>
                            <th>Phone Code</th>
                            <th>Continent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($countries as $country): 
                            // Determine continent (simplified logic)
                            $continent = 'Other';
                            foreach($continents as $cont => $codes) {
                                if(in_array($country['code'], $codes)) {
                                    $continent = $cont;
                                    break;
                                }
                            }
                        ?>
                        <tr data-code="<?php echo $country['code']; ?>" 
                            data-name="<?php echo strtolower($country['name']); ?>"
                            data-status="<?php echo $country['is_active'] ? 'active' : 'inactive'; ?>"
                            data-continent="<?php echo $continent; ?>">
                            <td>
                                <span class="country-code"><?php echo $country['code']; ?></span>
                            </td>
                            <td>
                                <strong><?php echo $country['name']; ?></strong>
                            </td>
                            <td>
                                <div class="currency-info">
                                    <span class="currency-symbol"><?php echo $country['currency_symbol'] ?? '$'; ?></span>
                                    <span class="currency-badge"><?php echo $country['currency_code'] ?? 'USD'; ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="phone-code"><?php echo $country['phone_code'] ?? 'N/A'; ?></span>
                            </td>
                            <td>
                                <span class="continent-badge"><?php echo $continent; ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $country['is_active'] ? 'active' : 'inactive'; ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo $country['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="action-btn edit" onclick="editCountry('<?php echo $country['code']; ?>')" title="Edit Country">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn" onclick="toggleStatus('<?php echo $country['code']; ?>', <?php echo $country['is_active'] ? 0 : 1; ?>)" title="Toggle Status">
                                    <i class="fas fa-<?php echo $country['is_active'] ? 'pause' : 'play'; ?>"></i>
                                </button>
                                <button class="action-btn delete" onclick="deleteCountry('<?php echo $country['code']; ?>')" title="Delete Country">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination" id="pagination">
                <!-- Pagination will be populated by JavaScript -->
            </div>
            
            <!-- Table Info -->
            <div class="text-center mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Showing <span id="showingCount"><?php echo count($countries); ?></span> of <?php echo count($countries); ?> countries
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Edit Country Modal -->
<div class="modal fade" id="editCountryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Country
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editCountryForm">
                    <input type="hidden" name="code" id="editCode">
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-tag text-primary"></i>
                            Country Code
                        </label>
                        <input type="text" class="form-control" id="editCodeDisplay" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-flag text-success"></i>
                            Country Name
                        </label>
                        <input type="text" class="form-control" name="name" id="editName" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-money-bill text-warning"></i>
                                Currency Code
                            </label>
                            <input type="text" class="form-control" name="currency_code" id="editCurrencyCode" maxlength="3">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-coins text-info"></i>
                                Currency Symbol
                            </label>
                            <input type="text" class="form-control" name="currency_symbol" id="editCurrencySymbol">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-phone text-primary"></i>
                            Phone Code
                        </label>
                        <input type="text" class="form-control" name="phone_code" id="editPhoneCode" placeholder="+1">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt text-danger"></i>
                            Continent
                        </label>
                        <select class="form-select" name="continent" id="editContinent">
                            <option value="">Select Continent</option>
                            <option value="Asia">Asia</option>
                            <option value="Europe">Europe</option>
                            <option value="North America">North America</option>
                            <option value="South America">South America</option>
                            <option value="Africa">Africa</option>
                            <option value="Oceania">Oceania</option>
                        </select>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="editIsActive">
                        <label class="form-check-label">Active Country</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="saveCountry()">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let currentPage = 1;
let rowsPerPage = 15;
let currentFilter = 'all';
let currentContinent = 'all';
let searchTerm = '';

// Filter countries by search
function filterCountries() {
    searchTerm = document.getElementById('searchInput').value.toLowerCase();
    applyFilters();
}

// Filter by status
function filterByStatus(status) {
    currentFilter = status;
    
    // Update active button
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById(`filter${status.charAt(0).toUpperCase() + status.slice(1)}`).classList.add('active');
    
    applyFilters();
}

// Filter by continent
function filterByContinent(continent) {
    currentContinent = continent;
    
    // Update active continent tag
    document.querySelectorAll('.continent-tag').forEach(tag => tag.classList.remove('active'));
    event.target.classList.add('active');
    
    applyFilters();
}

// Apply all filters
function applyFilters() {
    const rows = document.querySelectorAll('#countriesTable tbody tr');
    let visibleCount = 0;
    
    rows.forEach((row, index) => {
        const name = row.dataset.name;
        const code = row.dataset.code.toLowerCase();
        const status = row.dataset.status;
        const continent = row.dataset.continent;
        
        // Check search
        const matchesSearch = searchTerm === '' || 
                            name.includes(searchTerm) || 
                            code.includes(searchTerm);
        
        // Check status filter
        const matchesStatus = currentFilter === 'all' || status === currentFilter;
        
        // Check continent filter
        const matchesContinent = currentContinent === 'all' || continent === currentContinent;
        
        if (matchesSearch && matchesStatus && matchesContinent) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById('showingCount').textContent = visibleCount;
    setupPagination();
}

// Setup pagination
function setupPagination() {
    const rows = document.querySelectorAll('#countriesTable tbody tr:not([style*="display: none"])');
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    
    if (totalPages <= 1) {
        document.getElementById('pagination').innerHTML = '';
        return;
    }
    
    let paginationHtml = '';
    
    // Previous button
    paginationHtml += `<button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
        <i class="fas fa-chevron-left"></i>
    </button>`;
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            paginationHtml += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            paginationHtml += `<span class="pagination-btn">...</span>`;
        }
    }
    
    // Next button
    paginationHtml += `<button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
        <i class="fas fa-chevron-right"></i>
    </button>`;
    
    document.getElementById('pagination').innerHTML = paginationHtml;
    
    // Show only current page rows
    rows.forEach((row, index) => {
        const pageIndex = Math.floor(index / rowsPerPage) + 1;
        row.style.display = pageIndex === currentPage ? '' : 'none';
    });
}

// Change page
function changePage(page) {
    currentPage = page;
    setupPagination();
}

// Export countries list
function exportCountries() {
    Swal.fire({
        title: 'Export Countries',
        text: 'Choose export format',
        icon: 'question',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '📄 CSV',
        denyButtonText: '📋 JSON',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#3085d6',
        denyButtonColor: '#1cc88a'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'export-countries.php?format=csv';
            showToast('success', 'Exporting countries as CSV...');
        } else if (result.isDenied) {
            window.location.href = 'export-countries.php?format=json';
            showToast('success', 'Exporting countries as JSON...');
        }
    });
}

// Edit country
function editCountry(code) {
    // Find country data
    const row = document.querySelector(`tr[data-code="${code}"]`);
    const name = row.querySelector('td:nth-child(2) strong').textContent;
    const currencyCode = row.querySelector('.currency-badge')?.textContent || 'USD';
    const currencySymbol = row.querySelector('.currency-symbol')?.textContent || '$';
    const phoneCode = row.querySelector('.phone-code')?.textContent || '';
    const continent = row.querySelector('.continent-badge')?.textContent || '';
    const isActive = row.querySelector('.status-badge')?.classList.contains('active');
    
    document.getElementById('editCode').value = code;
    document.getElementById('editCodeDisplay').value = code;
    document.getElementById('editName').value = name;
    document.getElementById('editCurrencyCode').value = currencyCode;
    document.getElementById('editCurrencySymbol').value = currencySymbol;
    document.getElementById('editPhoneCode').value = phoneCode;
    document.getElementById('editContinent').value = continent;
    document.getElementById('editIsActive').checked = isActive;
    
    new bootstrap.Modal(document.getElementById('editCountryModal')).show();
}

// Save country changes
function saveCountry() {
    const formData = new FormData(document.getElementById('editCountryForm'));
    
    Swal.fire({
        title: 'Save Changes',
        text: 'Update country information?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, save'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('../ajax/settings/update-country.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Country updated successfully');
                    $('#editCountryModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'An error occurred');
            });
        }
    });
}

// Toggle country status
function toggleStatus(code, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    
    Swal.fire({
        title: `${action.charAt(0).toUpperCase() + action.slice(1)} Country`,
        text: `Are you sure you want to ${action} this country?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: newStatus ? '#28a745' : '#ffc107',
        cancelButtonColor: '#d33',
        confirmButtonText: `Yes, ${action}`
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('../ajax/settings/toggle-country.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code, status: newStatus })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', `Country ${action}d successfully`);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'An error occurred');
            });
        }
    });
}

// Delete country
function deleteCountry(code) {
    Swal.fire({
        title: 'Delete Country',
        html: `
            <div class="text-center">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <p>Are you sure you want to delete this country?</p>
                <p class="text-muted small">This action cannot be undone.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            
            fetch('../ajax/settings/delete-country.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code })
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showToast('success', 'Country deleted successfully');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                showToast('error', 'An error occurred');
            });
        }
    });
}

// Refresh list
function refreshList() {
    showLoading();
    setTimeout(() => location.reload(), 500);
}

// Show loading spinner
function showLoading() {
    // You can implement a loading spinner overlay here
}

function hideLoading() {
    // Hide loading spinner
}

// Show toast notification
function showToast(type, message) {
    // Simple alert for now
    Swal.fire({
        icon: type,
        title: message,
        timer: 2000,
        showConfirmButton: false
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    setupPagination();
    
    // Add animation to rows
    document.querySelectorAll('#countriesTable tbody tr').forEach((row, index) => {
        row.style.animation = `fadeIn 0.3s ease ${index * 0.02}s forwards`;
        row.style.opacity = '0';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>