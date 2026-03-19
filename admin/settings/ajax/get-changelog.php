<?php
// ajax/get-changelog.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$version = isset($_GET['version']) ? $_GET['version'] : '';

if (empty($version)) {
    echo json_encode(['success' => false, 'message' => 'Version not specified']);
    exit;
}

try {
    $db = getDB();
    
    // Get changelog from database
    $stmt = $db->prepare("SELECT * FROM system_updates WHERE version = ?");
    $stmt->execute([$version]);
    $update = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($update) {
        $changelog_html = generateChangelogHTML($update);
        echo json_encode([
            'success' => true,
            'html' => $changelog_html,
            'version' => $update['version'],
            'release_date' => $update['release_date'],
            'changelog' => $update['changelog']
        ]);
        exit;
    }
    
    // If not found in database, check remote
    $remote_changelog = getRemoteChangelog($version);
    if ($remote_changelog) {
        echo json_encode([
            'success' => true,
            'html' => $remote_changelog['html'],
            'version' => $version
        ]);
        exit;
    }
    
    // Fallback - generate demo changelog
    $demo_html = generateDemoChangelog($version);
    echo json_encode([
        'success' => true,
        'html' => $demo_html,
        'version' => $version
    ]);
    
} catch(PDOException $e) {
    error_log("Get changelog error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Generate changelog HTML from database record
 */
function generateChangelogHTML($update) {
    $date = date('F d, Y', strtotime($update['release_date']));
    $changelog = $update['changelog'] ?? '';
    
    // Parse changelog sections
    $sections = parseChangelog($changelog);
    
    $html = <<<HTML
<div class="changelog-content">
    <h4 class="mb-3">Version {$update['version']}</h4>
    <p class="text-muted mb-4">Released on {$date}</p>
HTML;
    
    if (!empty($sections)) {
        foreach ($sections as $title => $items) {
            if (empty($items)) continue;
            
            $icon = '';
            $color = '';
            switch(strtolower($title)) {
                case 'new':
                case 'features':
                case 'new features':
                    $icon = 'fas fa-plus-circle';
                    $color = 'success';
                    break;
                case 'fixed':
                case 'bug fixes':
                case 'fixes':
                    $icon = 'fas fa-bug';
                    $color = 'danger';
                    break;
                case 'improved':
                case 'improvements':
                case 'enhancements':
                    $icon = 'fas fa-chart-line';
                    $color = 'info';
                    break;
                case 'security':
                    $icon = 'fas fa-shield-alt';
                    $color = 'warning';
                    break;
                default:
                    $icon = 'fas fa-check-circle';
                    $color = 'primary';
            }
            
            $html .= <<<HTML
    <div class="mb-4">
        <h5 class="mb-3">
            <i class="{$icon} text-{$color} me-2"></i>
            {$title}
        </h5>
        <ul class="list-group list-group-flush">
HTML;
            
            foreach ($items as $item) {
                $html .= <<<HTML
            <li class="list-group-item">
                <i class="fas fa-check-circle text-success me-2"></i>
                {$item}
            </li>
HTML;
            }
            
            $html .= <<<HTML
        </ul>
    </div>
HTML;
        }
    } else {
        // If no structured changelog, show raw text
        $html .= <<<HTML
    <div class="alert alert-info">
        {$changelog}
    </div>
HTML;
    }
    
    $html .= <<<HTML
</div>
HTML;
    
    return $html;
}

/**
 * Parse changelog into sections
 */
function parseChangelog($changelog) {
    $sections = [];
    $lines = explode("\n", $changelog);
    $current_section = 'General';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check if line is a section header
        if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
            $current_section = trim($matches[1]);
            $sections[$current_section] = [];
        } elseif (preg_match('/^[-*•]/', $line)) {
            // Bullet point
            $item = preg_replace('/^[-*•]\s*/', '', $line);
            if (!isset($sections[$current_section])) {
                $sections[$current_section] = [];
            }
            $sections[$current_section][] = $item;
        } elseif (!empty($line) && !isset($sections[$current_section])) {
            // Regular line
            $sections[$current_section][] = $line;
        }
    }
    
    return $sections;
}

/**
 * Get remote changelog
 */
function getRemoteChangelog($version) {
    // Example implementation
    // $url = "https://updates.yourdomain.com/api/changelog.php?version={$version}";
    // $response = @file_get_contents($url);
    // if ($response) {
    //     return json_decode($response, true);
    // }
    return null;
}

/**
 * Generate demo changelog (fallback)
 */
function generateDemoChangelog($version) {
    $html = <<<HTML
<div class="changelog-content">
    <h4 class="mb-3">Version {$version}</h4>
    <p class="text-muted mb-4">Release information</p>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        Complete changelog will be available after installation.
    </div>
    
    <div class="mb-4">
        <h5 class="mb-3">
            <i class="fas fa-plus-circle text-success me-2"></i>
            New Features
        </h5>
        <ul class="list-group list-group-flush">
            <li class="list-group-item">
                <i class="fas fa-check-circle text-success me-2"></i>
                Enhanced security features
            </li>
            <li class="list-group-item">
                <i class="fas fa-check-circle text-success me-2"></i>
                Improved performance
            </li>
            <li class="list-group-item">
                <i class="fas fa-check-circle text-success me-2"></i>
                New payment gateway integration
            </li>
        </ul>
    </div>
    
    <div class="mb-4">
        <h5 class="mb-3">
            <i class="fas fa-bug text-danger me-2"></i>
            Bug Fixes
        </h5>
        <ul class="list-group list-group-flush">
            <li class="list-group-item">
                <i class="fas fa-check-circle text-success me-2"></i>
                Fixed login redirect issues
            </li>
            <li class="list-group-item">
                <i class="fas fa-check-circle text-success me-2"></i>
                Resolved email template caching problems
            </li>
        </ul>
    </div>
</div>
HTML;
    
    return $html;
}
?>