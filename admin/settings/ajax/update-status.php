<?php
// ajax/update-status.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

// Get session ID from query
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : null;

try {
    $db = getDB();
    
    // Get update session
    $stmt = $db->prepare("SELECT * FROM update_sessions ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode([
            'success' => false,
            'message' => 'No update session found'
        ]);
        exit;
    }
    
    // Get logs
    $logs = json_decode($session['logs'], true) ?: [];
    $progress = (int)$session['progress'];
    
    // If update is still running, we might simulate progress
    if ($session['status'] === 'running') {
        // Simulate update steps (in production, this would be actual update process)
        $steps = [
            20 => 'Creating database backup...',
            40 => 'Downloading update files...',
            60 => 'Verifying file integrity...',
            80 => 'Applying database updates...',
            100 => 'Cleaning up and finalizing...'
        ];
        
        $current_step = min(array_keys($steps, max(array_keys($steps, $progress))));
        
        if ($progress < 100) {
            // Add new log if step changed
            $new_progress = min($progress + 10, 100);
            if ($new_progress > $progress) {
                $logs[] = $steps[$new_progress] ?? 'Processing update...';
                $progress = $new_progress;
                
                // Update session
                $stmt = $db->prepare("UPDATE update_sessions 
                                      SET progress = ?, logs = ?, updated_at = NOW() 
                                      WHERE id = ?");
                $stmt->execute([$progress, json_encode($logs), $session['id']]);
            }
        }
        
        // If update is complete
        if ($progress >= 100) {
            $stmt = $db->prepare("UPDATE update_sessions 
                                  SET status = 'completed', completed_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$session['id']]);
            
            // Mark system update as applied
            $stmt = $db->prepare("UPDATE system_updates SET is_applied = 1, applied_at = NOW() WHERE version = ?");
            $stmt->execute([$session['version']]);
            
            echo json_encode([
                'success' => true,
                'complete' => true,
                'progress' => 100,
                'logs' => $logs,
                'version' => $session['version']
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'complete' => false,
            'progress' => $progress,
            'logs' => $logs,
            'status' => 'running'
        ]);
        exit;
    }
    
    // If update failed
    if ($session['status'] === 'failed') {
        echo json_encode([
            'success' => true,
            'complete' => false,
            'failed' => true,
            'error' => $session['error_message'] ?? 'Update failed',
            'logs' => $logs
        ]);
        exit;
    }
    
    // If update completed
    if ($session['status'] === 'completed') {
        echo json_encode([
            'success' => true,
            'complete' => true,
            'progress' => 100,
            'logs' => $logs,
            'version' => $session['version']
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'complete' => false,
        'progress' => $session['progress'],
        'logs' => $logs,
        'status' => $session['status']
    ]);
    
} catch(PDOException $e) {
    error_log("Update status error: " . $e->getMessage());
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
?>