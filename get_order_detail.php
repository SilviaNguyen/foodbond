<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$sqlOrder = "
    SELECT o.*, u.fullname, u.phone
    FROM orders o
    LEFT JOIN users u ON u.user_id = o.user_id
    WHERE o.order_id = ?
";
$stmtOrder = mysqli_prepare($conn, $sqlOrder);
mysqli_stmt_bind_param($stmtOrder, "i", $orderId);
mysqli_stmt_execute($stmtOrder);
$rsOrder = mysqli_stmt_get_result($stmtOrder);
$order = mysqli_fetch_assoc($rsOrder);
mysqli_stmt_close($stmtOrder);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$sqlItems = "
    SELECT oi.*, p.product_name
    FROM order_items oi
    INNER JOIN products p ON p.product_id = oi.product_id
    WHERE oi.order_id = ?
    ORDER BY oi.item_id
";
$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param($stmtItems, "i", $orderId);
mysqli_stmt_execute($stmtItems);
$rsItems = mysqli_stmt_get_result($stmtItems);
$items = [];
while ($row = mysqli_fetch_assoc($rsItems)) {
    $items[] = $row;
}
mysqli_stmt_close($stmtItems);

echo json_encode([
    'success' => true,
    'order' => $order,
    'items' => $items
]);
?>