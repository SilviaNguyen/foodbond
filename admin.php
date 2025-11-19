<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

// CHỈ CHO ADMIN
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header("Location: index.php");
    exit;
}

// Hàm auto cập nhật trạng thái theo thời gian
function update_and_get_order_status(array $row, mysqli $conn): array
{
    if (empty($row['created_at'])) {
        return $row;
    }

    $created = new DateTime($row['created_at']);
    $now     = new DateTime();

    $prep    = isset($row['prep_minutes']) ? (int)$row['prep_minutes'] : 20;
    $ship    = isset($row['delivery_minutes']) ? (int)$row['delivery_minutes'] : 20;
    $elapsedMinutes = (int) floor(($now->getTimestamp() - $created->getTimestamp()) / 60);

    $newStatus = $row['status'];

    if ($elapsedMinutes < 0) {
        $newStatus = 'preparing';
    } elseif ($elapsedMinutes < $prep) {
        $newStatus = 'preparing';
    } elseif ($elapsedMinutes < $prep + $ship) {
        $newStatus = 'delivering';
    } else {
        $newStatus = 'delivered';
    }

    if ($newStatus !== $row['status']) {
        $sqlU = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmtU = mysqli_prepare($conn, $sqlU);
        mysqli_stmt_bind_param($stmtU, "si", $newStatus, $row['order_id']);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);
        $row['status'] = $newStatus;
    }

    return $row;
}

// TÓM TẮT ĐƠN HÀNG & DOANH THU
$sqlSummary = "
    SELECT 
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END)   AS preparing_orders,
        SUM(CASE WHEN status = 'delivering' THEN 1 ELSE 0 END)  AS delivering_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END)   AS delivered_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END)   AS cancelled_orders,
        SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) AS revenue
    FROM orders
";
$rsSummary = mysqli_query($conn, $sqlSummary);
$summary = mysqli_fetch_assoc($rsSummary);

$totalOrders      = (int)($summary['total_orders'] ?? 0);
$preparingOrders  = (int)($summary['preparing_orders'] ?? 0);
$deliveringOrders = (int)($summary['delivering_orders'] ?? 0);
$deliveredOrders  = (int)($summary['delivered_orders'] ?? 0);
$cancelledOrders  = (int)($summary['cancelled_orders'] ?? 0);
$revenue          = (float)($summary['revenue'] ?? 0);

// Lấy danh sách đơn chưa hoàn thành (preparing + delivering)
$sqlInProgress = "
    SELECT o.*, u.fullname, u.phone
    FROM orders o
    LEFT JOIN users u ON u.user_id = o.user_id
    WHERE o.status IN ('preparing','delivering')
    ORDER BY o.created_at ASC
";
$rsInProgressRaw = mysqli_query($conn, $sqlInProgress);
$inProgress = [];
while ($row = mysqli_fetch_assoc($rsInProgressRaw)) {
    $inProgress[] = update_and_get_order_status($row, $conn);
}

// Lấy danh sách đơn đã giao gần đây
$sqlDelivered = "
    SELECT o.*, u.fullname, u.phone
    FROM orders o
    LEFT JOIN users u ON u.user_id = o.user_id
    WHERE o.status = 'delivered'
    ORDER BY o.created_at DESC
    LIMIT 10
";
$rsDeliveredRaw = mysqli_query($conn, $sqlDelivered);
$deliveredList = [];
while ($row = mysqli_fetch_assoc($rsDeliveredRaw)) {
    $deliveredList[] = update_and_get_order_status($row, $conn);
}

include 'header.php';
?>

<h2 class="h4 mb-3">Bảng điều khiển Admin - FoodBond</h2>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body">
                <h6 class="card-title text-muted mb-1">Tổng doanh thu</h6>
                <div class="fs-5 fw-bold text-success">
                    <?php echo number_format($revenue, 0, ',', '.'); ?> đ
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body">
                <h6 class="card-title text-muted mb-1">Tổng số đơn</h6>
                <div class="fs-5 fw-bold text-primary">
                    <?php echo $totalOrders; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body">
                <h6 class="card-title text-muted mb-1">Đang chuẩn bị</h6>
                <div class="fs-5 fw-bold text-warning">
                    <?php echo $preparingOrders; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body">
                <h6 class="card-title text-muted mb-1">Đang giao hàng</h6>
                <div class="fs-5 fw-bold text-info">
                    <?php echo $deliveringOrders; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ĐƠN ĐANG XỬ LÝ -->
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        Đơn đang xử lý (chuẩn bị / giao hàng)
    </div>
    <div class="card-body p-0">
        <?php if (!empty($inProgress)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>SĐT</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Tạo lúc</th>
                            <th>ETA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inProgress as $row): ?>
                            <tr>
                                <td>#<?php echo $row['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname'] ?? 'Khách lẻ'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                                <td><?php echo number_format($row['total'], 0, ',', '.'); ?> đ</td>
                                <td>
                                    <?php
                                    $st = $row['status'];
                                    $label = 'Không rõ';
                                    $cls   = 'badge bg-secondary';
                                    if ($st === 'preparing') {
                                        $label = 'Đang chuẩn bị';
                                        $cls   = 'badge bg-warning text-dark';
                                    } elseif ($st === 'delivering') {
                                        $label = 'Đang giao hàng';
                                        $cls   = 'badge bg-info text-dark';
                                    }
                                    ?>
                                    <span class="<?php echo $cls; ?>"><?php echo $label; ?></span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <?php if (!empty($row['estimated_delivery_time'])): 
                                        $eta = new DateTime($row['estimated_delivery_time']);
                                        echo $eta->format('H:i d/m');
                                    endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="p-3 mb-0">Hiện không có đơn nào đang chuẩn bị / giao hàng.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ĐƠN ĐÃ GIAO GẦN ĐÂY -->
<div class="card mb-4">
    <div class="card-header bg-success text-white">
        Các đơn đã giao gần đây
    </div>
    <div class="card-body p-0">
        <?php if (!empty($deliveredList)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>SĐT</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Thời gian giao (ETA)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveredList as $row): ?>
                            <tr>
                                <td>#<?php echo $row['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($row['fullname'] ?? 'Khách lẻ'); ?></td>
                                <td><?php echo htmlspecialchars($row['phone'] ?? ''); ?></td>
                                <td><?php echo number_format($row['total'], 0, ',', '.'); ?> đ</td>
                                <td><span class="badge bg-success">Đã giao</span></td>
                                <td>
                                    <?php if (!empty($row['estimated_delivery_time'])): 
                                        $eta = new DateTime($row['estimated_delivery_time']);
                                        echo $eta->format('H:i d/m/Y');
                                    endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="p-3 mb-0">Chưa có đơn nào được giao.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
