<?php
// admin/orders/ajax/save-order-note.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$note_type = $_POST['note_type'] ?? '';
$note = trim($_POST['note'] ?? '');

if (!$order_id || !$note_type || !$note) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

try {
    $db = getDB();
    
    // Insert note
    $stmt = $db->prepare("
        INSERT INTO order_notes (order_id, user_id, note_type, note, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$order_id, $_SESSION['user_id'], $note_type, $note]);
    
    // Log activity
    logUserActivity($_SESSION['user_id'], 'order_note_added', 
        "Added {$note_type} note to order #{$order_id}");
    
    echo json_encode([
        'success' => true,
        'message' => 'Note saved successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}