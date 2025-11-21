<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header("Location: index.php");
    exit;
}

$adminMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_product') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $product_name = trim($_POST['product_name'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $price        = (float)($_POST['price'] ?? 0);
        $image        = trim($_POST['image'] ?? '');

        if ($category_id <= 0 || $product_name === '' || $price <= 0) {
            $adminMessage = 'Vui lòng nhập đầy đủ tên sản phẩm, danh mục và giá.';
        } else {
            $sql = "INSERT INTO products (category_id, product_name, description, price, image)
                    VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "issds",
                $category_id,
                $product_name,
                $description,
                $price,
                $image
            );
            if (mysqli_stmt_execute($stmt)) {
                $adminMessage = 'Thêm sản phẩm mới thành công.';
            } else {
                $adminMessage = 'Lỗi thêm sản phẩm: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    elseif ($action === 'update_status') {
        $order_id   = (int)($_POST['order_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';

        $allowed = ['preparing', 'delivering', 'delivered', 'cancelled'];
        if ($order_id > 0 && in_array($new_status, $allowed, true)) {
            $sql = "UPDATE orders SET status = ? WHERE order_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $new_status, $order_id);
            if (mysqli_stmt_execute($stmt)) {
                $adminMessage = 'Cập nhật trạng thái đơn hàng thành công.';
            } else {
                $adminMessage = 'Lỗi cập nhật trạng thái: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    elseif ($action === 'cancel_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        if ($order_id > 0) {
            $sql = "UPDATE orders SET status = 'cancelled' WHERE order_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $order_id);
            if (mysqli_stmt_execute($stmt)) {
                $adminMessage = 'Đơn hàng đã được hủy.';
            } else {
                $adminMessage = 'Lỗi hủy đơn: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }

    elseif ($action === 'delete_order') {
        $order_id = (int)($_POST['order_id'] ?? 0);
        if ($order_id > 0) {
            mysqli_begin_transaction($conn);
            try {
                $sqlItems = "DELETE FROM order_items WHERE order_id = ?";
                $stmt1 = mysqli_prepare($conn, $sqlItems);
                mysqli_stmt_bind_param($stmt1, "i", $order_id);
                mysqli_stmt_execute($stmt1);
                mysqli_stmt_close($stmt1);

                $sqlOrder = "DELETE FROM orders WHERE order_id = ?";
                $stmt2 = mysqli_prepare($conn, $sqlOrder);
                mysqli_stmt_bind_param($stmt2, "i", $order_id);
                mysqli_stmt_execute($stmt2);
                mysqli_stmt_close($stmt2);

                mysqli_commit($conn);
                $adminMessage = 'Đã xóa đơn hàng khỏi hệ thống.';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $adminMessage = 'Lỗi xóa đơn: ' . $e->getMessage();
            }
        }
    }
}

function update_and_get_order_status(array $row, mysqli $conn): array
{
    if (empty($row['created_at'])) {
        return $row;
    }

    if ($row['status'] === 'delivered' || $row['status'] === 'cancelled') {
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

$sqlAdminCats = "SELECT * FROM categories ORDER BY category_name";
$rsAdminCats  = mysqli_query($conn, $sqlAdminCats);

$sqlAdminProducts = "
    SELECT p.*, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    ORDER BY p.product_id ASC
";
$rsAdminProducts = mysqli_query($conn, $sqlAdminProducts);

include 'header.php';
?>

<h2 class="h4 mb-3">Bảng điều khiển Admin - FoodBond</h2>

<?php if ($adminMessage !== ''): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($adminMessage); ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <strong>Quản lý sản phẩm</strong>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-5">
                <h6>Thêm sản phẩm mới</h6>
                <form method="post">
                    <input type="hidden" name="action" value="add_product">

                    <div class="mb-2">
                        <label class="form-label">Tên sản phẩm</label>
                        <input type="text" name="product_name" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Danh mục</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php while ($cat = mysqli_fetch_assoc($rsAdminCats)): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Mô tả</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Giá (VNĐ)</label>
                        <input type="number" name="price" class="form-control" min="0" step="1000" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Tên file ảnh (trong thư mục images/)</label>
                        <input type="text" name="image" class="form-control" placeholder="vd: ga_ran.jpg">
                    </div>

                    <button type="submit" class="btn btn-primary mt-2">Thêm sản phẩm</button>
                </form>
            </div>

            <!-- Bảng liệt kê sản phẩm -->
            <div class="col-md-7">
                <h6>Danh sách sản phẩm hiện có</h6>
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-sm table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên</th>
                                <th>Danh mục</th>
                                <th>Giá</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($p = mysqli_fetch_assoc($rsAdminProducts)): ?>
                                <tr>
                                    <td><?php echo $p['product_id']; ?></td>
                                    <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                                    <td><?php echo number_format($p['price'], 0, ',', '.'); ?> đ</td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

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
                            <th>Hành động</th>
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
                                <td>
                                    <form method="post" class="d-flex gap-1 mb-1">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        <select name="status" class="form-select form-select-sm">
                                            <option value="preparing"  <?php if ($row['status'] === 'preparing')  echo 'selected'; ?>>Đang chuẩn bị</option>
                                            <option value="delivering" <?php if ($row['status'] === 'delivering') echo 'selected'; ?>>Đang giao</option>
                                            <option value="delivered"  <?php if ($row['status'] === 'delivered')  echo 'selected'; ?>>Đã giao</option>
                                            <option value="cancelled"  <?php if ($row['status'] === 'cancelled')  echo 'selected'; ?>>Đã hủy</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">Cập nhật</button>
                                    </form>

                                    <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn HỦY đơn này?');">
                                        <input type="hidden" name="action" value="cancel_order">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">Hủy</button>
                                    </form>

                                    <form method="post" class="d-inline" onsubmit="return confirm('XÓA hoàn toàn đơn này (cả chi tiết)?');">
                                        <input type="hidden" name="action" value="delete_order">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                                    </form>
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
                            <th>Hành động</th>
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
                                <td>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Xóa đơn hàng này khỏi lịch sử?');">
                                        <input type="hidden" name="action" value="delete_order">
                                        <input type="hidden" name="order_id" value="<?php echo $row['order_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                                    </form>
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
