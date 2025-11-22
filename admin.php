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

    $orderCreatedAt = new DateTime($row['created_at']);
    $now            = new DateTime();

    // thời gian chuẩn bị và giao hàng (phút)
    $prep = (int)($row['prep_minutes'] ?? 20);
    $ship = (int)($row['delivery_minutes'] ?? 20);

    // tổng phút đã trôi qua từ khi tạo đơn
    $elapsedMinutes = ($now->getTimestamp() - $orderCreatedAt->getTimestamp()) / 60;

    // Nếu đã quá thời gian chuẩn bị + giao hàng mà vẫn là 'delivering' thì auto chuyển sang 'delivered'
    if ($row['status'] === 'delivering' && $elapsedMinutes >= ($prep + $ship)) {
        $newStatus = 'delivered';
        $sqlU = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmtU = mysqli_prepare($conn, $sqlU);
        mysqli_stmt_bind_param($stmtU, "si", $newStatus, $row['order_id']);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);
        $row['status'] = $newStatus;
    }
    // Nếu đã quá thời gian chuẩn bị mà vẫn là 'preparing' thì auto chuyển sang 'delivering'
    elseif ($row['status'] === 'preparing' && $elapsedMinutes >= $prep && $elapsedMinutes < ($prep + $ship)) {
        $newStatus = 'delivering';
        $sqlU = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmtU = mysqli_prepare($conn, $sqlU);
        mysqli_stmt_bind_param($stmtU, "si", $newStatus, $row['order_id']);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);
        $row['status'] = $newStatus;
    }

    return $row;
}


// QUẢN LÝ SẢN PHẨM: THÊM / XÓA
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Thêm sản phẩm mới
    if (isset($_POST['add_product'])) {
        $name        = trim($_POST['product_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        $image       = trim($_POST['image'] ?? '');

        if ($name === '' || $category_id <= 0 || $price <= 0 || $image === '') {
            $message = 'Vui lòng nhập đầy đủ thông tin sản phẩm.';
            $messageType = 'danger';
        } else {
            $sqlInsert = "INSERT INTO products (product_name, category_id, description, price, image) VALUES (?,?,?,?,?)";
            $stmtIns   = mysqli_prepare($conn, $sqlInsert);
            if ($stmtIns) {
                mysqli_stmt_bind_param($stmtIns, "sisds", $name, $category_id, $description, $price, $image);
                mysqli_stmt_execute($stmtIns);
                if (mysqli_stmt_affected_rows($stmtIns) > 0) {
                    $message = 'Thêm sản phẩm mới thành công.';
                    $messageType = 'success';
                } else {
                    $message = 'Không thể thêm sản phẩm. Vui lòng thử lại.';
                    $messageType = 'danger';
                }
                mysqli_stmt_close($stmtIns);
            } else {
                $message = 'Lỗi hệ thống khi thêm sản phẩm.';
                $messageType = 'danger';
            }
        }
    }

    // Xóa sản phẩm
    if (isset($_POST['delete_product_id'])) {
        $productId = (int)$_POST['delete_product_id'];
        if ($productId > 0) {
            $sqlDel = "DELETE FROM products WHERE product_id = ?";
            $stmtDel = mysqli_prepare($conn, $sqlDel);
            if ($stmtDel) {
                mysqli_stmt_bind_param($stmtDel, "i", $productId);
                mysqli_stmt_execute($stmtDel);
                if (mysqli_stmt_affected_rows($stmtDel) > 0) {
                    $message = 'Xóa sản phẩm thành công.';
                    $messageType = 'success';
                } else {
                    $message = 'Không thể xóa sản phẩm (có thể sản phẩm không tồn tại).';
                    $messageType = 'danger';
                }
                mysqli_stmt_close($stmtDel);
            } else {
                $message = 'Lỗi hệ thống khi xóa sản phẩm.';
                $messageType = 'danger';
            }
        }
    }
}

// Lấy danh mục và danh sách sản phẩm
$categories = [];
$sqlCategories = "SELECT category_id, category_name FROM categories ORDER BY category_name";
$rsCategories = mysqli_query($conn, $sqlCategories);
if ($rsCategories) {
    while ($row = mysqli_fetch_assoc($rsCategories)) {
        $categories[] = $row;
    }
}

$products = [];
$sqlProducts = "SELECT p.*, c.category_name FROM products p LEFT JOIN categories c ON c.category_id = p.category_id ORDER BY p.product_id ASC";
$rsProducts = mysqli_query($conn, $sqlProducts);
if ($rsProducts) {
    while ($row = mysqli_fetch_assoc($rsProducts)) {
        $products[] = $row;
    }
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

<h2 class="h4 mb-3">Admin - FoodBond</h2>


<?php if (!empty($message)): ?>
    <div id="flash-message" class="alert alert-<?php echo ($messageType === 'danger') ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <script>
        setTimeout(function () {
            var el = document.getElementById('flash-message');
            if (!el) return;
            if (window.bootstrap && bootstrap.Alert) {
                var alert = new bootstrap.Alert(el);
                alert.close();
            } else {
                el.style.display = 'none';
            }
        }, 3000);
    </script>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                Quản lý sản phẩm
            </div>
            <div class="card-body">
                <h6 class="mb-3">Thêm sản phẩm mới</h6>
                <form method="post">
                    <div class="mb-3">
                        <label for="product_name" class="form-label">Tên sản phẩm</label>
                        <input type="text" class="form-control" id="product_name" name="product_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Danh mục</label>
                        <select class="form-select" id="category_id" name="category_id" required>
                            <option value="">-- Chọn danh mục --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Giá (VNĐ)</label>
                        <input type="number" class="form-control" id="price" name="price" min="0" step="1000" required>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Tên file ảnh (trong thư mục images/)</label>
                        <input type="text" class="form-control" id="image" name="image" placeholder="vd: ga_ran.jpg" required>
                    </div>
                    <button type="submit" name="add_product" class="btn btn-primary">Thêm sản phẩm</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">
                Danh sách sản phẩm hiện có
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-striped table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên</th>
                                <th>Danh mục</th>
                                <th>Giá</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td><?php echo $p['product_id']; ?></td>
                                        <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($p['category_name'] ?? ''); ?></td>
                                        <td><?php echo number_format((float)$p['price'], 0, ',', '.'); ?> đ</td>
                                        <td>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">
                                                <input type="hidden" name="delete_product_id" value="<?php echo $p['product_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3">Chưa có sản phẩm nào.</td>
                                </tr>
                            <?php endif; ?>
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
                            <th>Đặt lúc</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inProgress as $o): ?>
                            <tr>
                                <td><?php echo $o['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($o['fullname'] ?? 'Khách lẻ'); ?></td>
                                <td><?php echo htmlspecialchars($o['phone'] ?? ''); ?></td>
                                <td><?php echo number_format($o['total'] + $o['shipping_fee'], 0, ',', '.'); ?> đ</td>
                                <td>
                                    <?php if ($o['status'] === 'preparing'): ?>
                                        <span class="badge bg-warning text-dark">Đang chuẩn bị</span>
                                    <?php elseif ($o['status'] === 'delivering'): ?>
                                        <span class="badge bg-info text-dark">Đang giao hàng</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Khác</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('H:i d/m', strtotime($o['created_at'])); ?></td>
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
                            <th>Đặt lúc</th>
                            <th>Giao lúc</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deliveredList as $o): ?>
                            <tr>
                                <td><?php echo $o['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($o['fullname'] ?? 'Khách lẻ'); ?></td>
                                <td><?php echo htmlspecialchars($o['phone'] ?? ''); ?></td>
                                <td><?php echo number_format($o['total'] + $o['shipping_fee'], 0, ',', '.'); ?> đ</td>
                                <td><?php echo date('H:i d/m', strtotime($o['created_at'])); ?></td>
                                <td>
                                    <?php if (!empty($o['updated_at'])): ?>
                                        <?php echo date('H:i d/m', strtotime($o['updated_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
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
