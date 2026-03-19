<?php
// ajax/check-updates.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// define('CURRENT_VERSION', '1.0.0');

try {
    $db = getDB();
    
    // Check for pending updates in database
    $stmt = $db->prepare("SELECT * FROM system_updates WHERE is_applied = 0 ORDER BY release_date DESC LIMIT 1");
    $stmt->execute();
    $pending_update = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pending_update) {
        $release_notes = explode("\n", $pending_update['changelog'] ?? '');
        
        echo json_encode([
            'success' => true,
            'update_available' => true,
            'version' => $pending_update['version'],
            'release_date' => date('F d, Y', strtotime($pending_update['release_date'])),
            'notes' => array_filter($release_notes, function($note) { return trim($note) != ''; }),
            'changelog' => $pending_update['changelog'],
            'source' => 'database'
        ]);
        exit;
    }
    
    // Check remote update server (optional)
    $remote_update = checkRemoteUpdates();
    if ($remote_update && version_compare($remote_update['version'], CURRENT_VERSION, '>')) {
        echo json_encode([
            'success' => true,
            'update_available' => true,
            'version' => $remote_update['version'],
            'release_date' => $remote_update['release_date'],
            'notes' => $remote_update['notes'],
            'changelog' => $remote_update['changelog'] ?? '',
            'source' => 'remote',
            'download_url' => $remote_update['download_url'] ?? null
        ]);
        exit;
    }
    
    // No updates available
    echo json_encode([
        'success' => true,
        'update_available' => false,
        'current_version' => CURRENT_VERSION
    ]);
    
} catch(PDOException $e) {
    error_log("Check updates error: " . $e->getMessage());
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
 * Check remote update server
 */
function checkRemoteUpdates() {
    // Example implementation - replace with your update server URL
    $update_url = SITE_URL . 'api/check.php?version=' . CURRENT_VERSION . '&domain=' . urlencode($_SERVER['HTTP_HOST']);
    
    // For demonstration, return null (no remote updates)
    // In production, you would make an HTTP request to your update server
    
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $update_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        return json_decode($response, true);
    }
    
    
    return null;
}
?>