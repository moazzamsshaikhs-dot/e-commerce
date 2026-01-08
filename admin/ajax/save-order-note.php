<?php
require_once '../includes/config.php';
require_once '../includes/auth-check.php';

if ($_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_id = (int)$_POST['order_id'];
$note_type = $_POST['note_type'];
$note = trim($_POST['note']);

if (empty($note)) {
    echo json_encode(['success' => false, 'message' => 'Note cannot be empty']);
    exit;
}

try {
    $db = getDB();
    
    // Check if order exists
    $stmt = $db->prepare("SELECT id FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }
    
    // Save note
    $stmt = $db->prepare("INSERT INTO order_notes (order_id, user_id, note_type, note) 
                          VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $_SESSION['user_id'], $note_type, $note]);
    
    echo json_encode(['success' => true, 'message' => 'Note added successfully']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>