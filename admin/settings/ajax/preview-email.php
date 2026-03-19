<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    die('Access denied');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die('Invalid template ID');
}

try {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
    $stmt->execute([$id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        die('Template not found');
    }
    
    // Replace variables with sample data
    $subject = $template['subject'];
    $content = $template['content'];
    
    $variables = [
        '{{site_name}}' => 'ShopEase Pro',
        '{{site_url}}' => 'https://shopeasepro.com',
        '{{user_name}}' => 'John Doe',
        '{{user_email}}' => 'john.doe@example.com',
        '{{order_number}}' => 'ORD-2024-001',
        '{{order_total}}' => '$99.99',
        '{{reset_link}}' => 'https://shopeasepro.com/reset-password?token=sample123',
        '{{current_date}}' => date('F j, Y'),
        '{{current_year}}' => date('Y')
    ];
    
    foreach ($variables as $key => $value) {
        $subject = str_replace($key, $value, $subject);
        $content = str_replace($key, $value, $content);
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Email Preview: <?php echo htmlspecialchars($template['name']); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                background: #f5f5f5;
            }
            .container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #4361ee, #3a0ca3);
                color: white;
                padding: 20px;
                text-align: center;
            }
            .subject {
                background: #f8f9fa;
                padding: 15px 20px;
                border-bottom: 1px solid #dee2e6;
                font-weight: bold;
                color: #495057;
            }
            .content {
                padding: 20px;
            }
            .footer {
                background: #f8f9fa;
                padding: 15px 20px;
                text-align: center;
                color: #6c757d;
                font-size: 12px;
                border-top: 1px solid #dee2e6;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Email Preview</h2>
                <p><?php echo htmlspecialchars($template['name']); ?></p>
            </div>
            <div class="subject">
                <strong>Subject:</strong> <?php echo htmlspecialchars($subject); ?>
            </div>
            <div class="content">
                <?php echo $content; ?>
            </div>
            <div class="footer">
                This is a preview of your email template. Variables have been replaced with sample data.
            </div>
        </div>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}