<?php
// admin/logs/view-logs.php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    die('Access denied. Admin only.');
}

$log_file = dirname(__DIR__) . '/logs/update_uploads.log';
$log_content = '';

if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Uploads Log</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: #2d2d2d;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .log-content {
            background: #2d2d2d;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            white-space: pre-wrap;
            font-size: 12px;
            line-height: 1.5;
        }
        .timestamp {
            color: #6a9955;
        }
        .info {
            color: #569cd6;
        }
        .error {
            color: #f48771;
        }
        .success {
            color: #6a9955;
        }
        button {
            background: #007acc;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background: #005a9e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Update Uploads Log</h1>
            <p>File: <?php echo $log_file; ?></p>
            <p>Size: <?php echo file_exists($log_file) ? round(filesize($log_file) / 1024, 2) . ' KB' : '0 KB'; ?></p>
            <button onclick="window.location.reload()">🔄 Refresh</button>
            <button onclick="clearLog()">🗑️ Clear Log</button>
            <button onclick="window.location.href='../settings/system-updates.php'">← Back</button>
        </div>
        
        <div class="log-content">
            <?php 
            if (empty($log_content)) {
                echo "<p>No log entries found.</p>";
            } else {
                $lines = explode("\n", $log_content);
                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    
                    if (strpos($line, '#') === 0) {
                        echo "<span style='color: #858585;'>" . htmlspecialchars($line) . "</span><br>";
                    } elseif (strpos($line, 'error') !== false || strpos($line, 'failed') !== false) {
                        echo "<span class='error'>" . htmlspecialchars($line) . "</span><br>";
                    } elseif (strpos($line, 'success') !== false || strpos($line, 'uploaded successfully') !== false) {
                        echo "<span class='success'>" . htmlspecialchars($line) . "</span><br>";
                    } elseif (preg_match('/\[(.*?)\]/', $line, $matches)) {
                        echo "<span class='timestamp'>[" . htmlspecialchars($matches[1]) . "]</span>" . htmlspecialchars(substr($line, strlen($matches[0]))) . "<br>";
                    } else {
                        echo htmlspecialchars($line) . "<br>";
                    }
                }
            }
            ?>
        </div>
    </div>
    
    <script>
    function clearLog() {
        if (confirm('Are you sure you want to clear the log file?')) {
            fetch('ajax/clear-log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Log cleared successfully!');
                    window.location.reload();
                } else {
                    alert('Failed to clear log: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error);
            });
        }
    }
    </script>
</body>
</html>