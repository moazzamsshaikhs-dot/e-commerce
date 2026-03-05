<?php
// admin/ajax/bulk-order-action.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$order_ids = isset($input['order_ids']) ? $input['order_ids'] : [];
$action = isset($input['action']) ? $input['action'] : '';

if (empty($order_ids) || !$action) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate action
$valid_actions = ['processing', 'shipped', 'delivered', 'cancelled', 'delete'];
if (!in_array($action, $valid_actions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

    if ($action == 'delete') {
        // Delete orders
        $stmt = $db->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
        $stmt->execute($order_ids);
        $message = count($order_ids) . ' order(s) deleted successfully';
    } else {
        // Update order status
        $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
        $params = array_merge([$action], $order_ids);
        $stmt->execute($params);

        // Add to status history for each order
        $history_stmt = $db->prepare("
            INSERT INTO order_status_history (order_id, status, changed_by, created_at)
            VALUES (?, ?, ?, NOW())
        ");

        foreach ($order_ids as $order_id) {
            $history_stmt->execute([$order_id, $action, $_SESSION['user_id']]);
        }

        $message = count($order_ids) . ' order(s) marked as ' . $action;
    }

    // Log activity
    logUserActivity($_SESSION['user_id'], 'bulk_order_action', 
        "Applied {$action} to " . count($order_ids) . " orders");

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'affected' => count($order_ids)
    ]);

} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}