<?php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get form data
$template_id = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
$template_key = $_POST['template_key'] ?? '';
$name = $_POST['name'] ?? '';
$subject = $_POST['subject'] ?? '';
$content = $_POST['content'] ?? '';
$variables = $_POST['variables'] ?? '';

// Validate required fields
if (empty($template_key) && $template_id == 0) {
    echo json_encode(['success' => false, 'message' => 'Template key is required']);
    exit;
}

if (empty($name)) {
    echo json_encode(['success' => false, 'message' => 'Template name is required']);
    exit;
}

if (empty($subject)) {
    echo json_encode(['success' => false, 'message' => 'Email subject is required']);
    exit;
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Email content is required']);
    exit;
}

// Validate template key format for new templates
if ($template_id == 0 && !preg_match('/^[a-z0-9_]+$/', $template_key)) {
    echo json_encode(['success' => false, 'message' => 'Template key must contain only lowercase letters, numbers, and underscores']);
    exit;
}

// Validate variables JSON if provided
if (!empty($variables)) {
    $decoded = json_decode($variables, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON format for variables']);
        exit;
    }
}

try {
    $db = getDB();
    
    // Check if template key already exists (for new templates)
    if ($template_id == 0) {
        $check_stmt = $db->prepare("SELECT id FROM email_templates WHERE template_key = ?");
        $check_stmt->execute([$template_key]);
        if ($check_stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Template key already exists']);
            exit;
        }
    }
    
    $db->beginTransaction();
    
    if ($template_id > 0) {
        // Update existing template
        $stmt = $db->prepare("
            UPDATE email_templates 
            SET name = ?, subject = ?, content = ?, variables = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $subject, $content, $variables, $template_id]);
        $message = 'Template updated successfully';
    } else {
        // Insert new template
        $stmt = $db->prepare("
            INSERT INTO email_templates (template_key, name, subject, content, variables, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$template_key, $name, $subject, $content, $variables]);
        $template_id = $db->lastInsertId();
        $message = 'Template created successfully';
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'template_id' => $template_id
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}