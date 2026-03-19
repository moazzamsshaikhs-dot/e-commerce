<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = getDB();
    
    // Create backup before reset
    $backup = $db->query("SELECT * FROM email_templates")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($backup)) {
        $backup_dir = '../../backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        $backup_file = $backup_dir . 'email_templates_backup_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($backup_file, json_encode($backup, JSON_PRETTY_PRINT));
    }
    
    // Delete all existing templates
    $db->exec("DELETE FROM email_templates");
    
    // Insert default templates
    $default_templates = [
        [
            'template_key' => 'welcome_email',
            'name' => 'Welcome Email',
            'subject' => 'Welcome to {{site_name}}!',
            'content' => '<h2>Welcome {{user_name}}!</h2><p>Thank you for joining {{site_name}}. We\'re excited to have you!</p>',
            'variables' => '["site_name", "user_name"]',
            'is_active' => 1
        ],
        [
            'template_key' => 'order_confirmation',
            'name' => 'Order Confirmation',
            'subject' => 'Order #{{order_number}} Confirmation',
            'content' => '<h2>Thank you for your order!</h2><p>Your order #{{order_number}} has been confirmed.</p><p>Total: {{order_total}}</p>',
            'variables' => '["order_number", "order_total", "user_name"]',
            'is_active' => 1
        ],
        [
            'template_key' => 'password_reset',
            'name' => 'Password Reset',
            'subject' => 'Reset Your Password',
            'content' => '<h2>Password Reset Request</h2><p>Click the link below to reset your password:</p><p><a href="{{reset_link}}">Reset Password</a></p>',
            'variables' => '["reset_link", "user_name"]',
            'is_active' => 1
        ]
    ];
    
    $insert_stmt = $db->prepare("
        INSERT INTO email_templates (template_key, name, subject, content, variables, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    foreach ($default_templates as $template) {
        $insert_stmt->execute([
            $template['template_key'],
            $template['name'],
            $template['subject'],
            $template['content'],
            $template['variables'],
            $template['is_active']
        ]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Templates reset to default successfully',
        'backup_created' => !empty($backup)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}