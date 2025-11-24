<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function update_and_get_order_status(array $row, mysqli $conn): array
{
    if (empty($row['created_at'])) {
        return $row;
    }

    if ($row['status'] === 'delivered' || $row['status'] === 'cancelled') {
        return $row;
    }

    $orderCreatedAt = new DateTime($row['created_at']);
    $now            = new DateTime();

    $prep = (int)($row['prep_minutes'] ?? 20);
    $ship = (int)($row['delivery_minutes'] ?? 20);

    $elapsedMinutes = ($now->getTimestamp() - $orderCreatedAt->getTimestamp()) / 60;

    $newStatus = $row['status'];

    if ($row['status'] === 'preparing' && $elapsedMinutes >= $prep && $elapsedMinutes < ($prep + $ship)) {
        $newStatus = 'delivering';
    }
    elseif ($row['status'] === 'delivering' && $elapsedMinutes >= ($prep + $ship)) {
        $newStatus = 'delivered';
    } else {
        return $row;
    }

    $sqlU  = "UPDATE orders SET status = ? WHERE order_id = ?";
    $stmtU = mysqli_prepare($conn, $sqlU);
    if ($stmtU) {
        mysqli_stmt_bind_param($stmtU, "si", $newStatus, $row['order_id']);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);

        $row['status'] = $newStatus;
    }

    return $row;
}


$userId = (int)$_SESSION['user_id'];

$sqlOrders = "
    SELECT order_id, total, shipping_fee, shipping_address, distance_km, status, 
           prep_minutes, delivery_minutes, estimated_delivery_time, created_at
    FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
";
$stmt = mysqli_prepare($conn, $sqlOrders);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$rsOrders = mysqli_stmt_get_result($stmt);

$orders = [];
$orderIds = [];

while ($row = mysqli_fetch_assoc($rsOrders)) {
    $row = update_and_get_order_status($row, $conn);

    $orders[$row['order_id']] = $row;
    $orderIds[] = $row['order_id'];
}
mysqli_stmt_close($stmt);

$itemsByOrder = [];

if (!empty($orderIds)) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $sqlItems = "
        SELECT oi.order_id, oi.product_id, oi.quantity, oi.price, p.product_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.order_id ASC, p.product_name ASC
    ";

    $stmt = mysqli_prepare($conn, $sqlItems);
    $types = str_repeat('i', count($orderIds));
    mysqli_stmt_bind_param($stmt, $types, ...$orderIds);
    mysqli_stmt_execute($stmt);
    $rsItems = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rsItems)) {
        $oid = $row['order_id'];
        if (!isset($itemsByOrder[$oid])) {
            $itemsByOrder[$oid] = [];
        }
        $itemsByOrder[$oid][] = $row;
    }
    mysqli_stmt_close($stmt);
}

include 'header.php';
?>

<h2 class="h4 mb-3">Đơn hàng của tôi</h2>

<?php if (empty($orders)): ?>

    <div class="alert alert-info">
        Bạn chưa có đơn hàng nào. 
        <a href="index.php" class="alert-link">Bắt đầu đặt món tại FoodBond</a>.
    </div>

<?php else: ?>

    <?php foreach ($orders as $oid => $o): ?>

        <?php
        $status = $o['status'];
        $badgeClass = 'bg-secondary';
        $label = $status;

        if ($status === 'preparing') {
            $badgeClass = 'bg-warning text-dark';
            $label = 'Đang chuẩn bị';
        } elseif ($status === 'delivering') {
            $badgeClass = 'bg-info text-dark';
            $label = 'Đang giao hàng';
        } elseif ($status === 'delivered') {
            $badgeClass = 'bg-success';
            $label = 'Đã giao';
        } elseif ($status === 'cancelled') {
            $badgeClass = 'bg-danger';
            $label = 'Đã huỷ';
        }
        ?>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Đơn #<?php echo $oid; ?></strong>
                    <span class="text-muted ms-2">
                        <?php echo date('d/m/Y H:i', strtotime($o['created_at'])); ?>
                    </span>
                </div>
                <div>
                    <span class="badge <?php echo $badgeClass; ?>">
                        <?php echo $label; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <p class="mb-1">
                            <strong>Địa chỉ giao:</strong><br>
                            <?php echo nl2br(htmlspecialchars($o['shipping_address'])); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Khoảng cách ước tính:</strong>
                            <?php echo number_format((float)$o['distance_km'], 1); ?> km
                        </p>
                        <p class="mb-1">
                            <strong>Thời gian chuẩn bị:</strong>
                            <?php echo (int)$o['prep_minutes']; ?> phút<br>
                            <strong>Thời gian giao hàng:</strong>
                            <?php echo (int)$o['delivery_minutes']; ?> phút
                        </p>
                        <p class="mb-1">
                            <strong>Phí giao hàng:</strong>
                            <?php echo number_format($o['shipping_fee'], 0, ',', '.'); ?> đ
                        </p>
                        <p class="mb-1">
                            <strong>Tổng tiền:</strong>
                            <span class="text-danger fw-bold">
                                <?php echo number_format($o['total'], 0, ',', '.'); ?> đ
                            </span>
                        </p>
                        <?php if (!empty($o['estimated_delivery_time'])): 
                            $eta = new DateTime($o['estimated_delivery_time']);
                        ?>
                            <p class="mb-1">
                                <strong>Thời gian giao dự kiến:</strong>
                                <?php echo $eta->format('H:i d/m/Y'); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-7 mb-3">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Món</th>
                                    <th class="text-center">SL</th>
                                    <th class="text-end">Đơn giá</th>
                                    <th class="text-end">Thành tiền</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (!empty($itemsByOrder[$oid])): ?>
                                    <?php foreach ($itemsByOrder[$oid] as $it): 
                                        $lineTotal = $it['price'] * $it['quantity'];
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                                            <td class="text-center"><?php echo (int)$it['quantity']; ?></td>
                                            <td class="text-end">
                                                <?php echo number_format($it['price'], 0, ',', '.'); ?> đ
                                            </td>
                                            <td class="text-end">
                                                <?php echo number_format($lineTotal, 0, ',', '.'); ?> đ
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-muted">
                                            (Không có dữ liệu món ăn cho đơn này)
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div> <!-- row -->
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<?php include 'footer.php'; ?>
