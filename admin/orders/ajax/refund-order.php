<?php
// admin/orders/ajax/refund-order.php
require_once '../../includes/config.php';
require_once '../../includes/auth-check.php';

// Check if user is admin
if ($_SESSION['user_type'] !== 'admin') {
    $_SESSION['error'] = 'Access denied. Admin only.';
    redirect(SITE_URL . 'index.php');
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    $_SESSION['error'] = 'Order ID required';
    redirect('../orders.php');
}

// Redirect to refund page
header('Location: ../refund.php?order_id=' . $order_id);
exit;