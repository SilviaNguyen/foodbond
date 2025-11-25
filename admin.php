<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header("Location: index.php");
    exit;
}

function update_and_get_order_status(array $row, mysqli $conn): array
{
    if (empty($row['created_at'])) {
        return $row;
    }

    $orderCreatedAt = new DateTime($row['created_at']);
    $now            = new DateTime();

    $prep = (int)($row['prep_minutes'] ?? 20);
    $ship = (int)($row['delivery_minutes'] ?? 20);

    $elapsedMinutes = ($now->getTimestamp() - $orderCreatedAt->getTimestamp()) / 60;

    if ($row['status'] === 'delivering' && $elapsedMinutes >= ($prep + $ship)) {
        $newStatus = 'delivered';
        $sqlU = "UPDATE orders SET status = ? WHERE order_id = ?";
        $stmtU = mysqli_prepare($conn, $sqlU);
        mysqli_stmt_bind_param($stmtU, "si", $newStatus, $row['order_id']);
        mysqli_stmt_execute($stmtU);
        mysqli_stmt_close($stmtU);
        $row['status'] = $newStatus;
    }
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

$message = '';
$messageType = 'success';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'success';
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name        = trim($_POST['product_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $price       = (float)($_POST['price'] ?? 0);
        $image       = trim($_POST['image'] ?? '');

        if ($name === '' || $category_id <= 0 || $price <= 0 || $image === '') {
            $_SESSION['message'] = 'Vui lòng nhập đầy đủ thông tin sản phẩm.';
            $_SESSION['messageType'] = 'danger';
        } else {
            $sqlInsert = "INSERT INTO products (product_name, category_id, description, price, image) VALUES (?,?,?,?,?)";
            $stmtIns   = mysqli_prepare($conn, $sqlInsert);
            if ($stmtIns) {
                mysqli_stmt_bind_param($stmtIns, "sisds", $name, $category_id, $description, $price, $image);
                mysqli_stmt_execute($stmtIns);
                if (mysqli_stmt_affected_rows($stmtIns) > 0) {
                    $_SESSION['message'] = 'Thêm sản phẩm mới thành công.';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Không thể thêm sản phẩm. Vui lòng thử lại.';
                    $_SESSION['messageType'] = 'danger';
                }
                mysqli_stmt_close($stmtIns);
            } else {
                $_SESSION['message'] = 'Lỗi hệ thống khi thêm sản phẩm.';
                $_SESSION['messageType'] = 'danger';
            }
        }
        header("Location: admin.php");
        exit;
    }

    if (isset($_POST['delete_product_id'])) {
        $productId = (int)$_POST['delete_product_id'];
        if ($productId > 0) {
            $sqlUpdate = "UPDATE products SET product_name = CONCAT(product_name, ' (Đã xóa)'), category_id = NULL WHERE product_id = ? AND product_name NOT LIKE '%(Đã xóa)%'";
            $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
            if ($stmtUpdate) {
                mysqli_stmt_bind_param($stmtUpdate, "i", $productId);
                mysqli_stmt_execute($stmtUpdate);
                if (mysqli_stmt_affected_rows($stmtUpdate) > 0) {
                    $_SESSION['message'] = 'Đánh dấu sản phẩm đã xóa thành công.';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Sản phẩm đã được đánh dấu xóa trước đó.';
                    $_SESSION['messageType'] = 'info';
                }
                mysqli_stmt_close($stmtUpdate);
            } else {
                $_SESSION['message'] = 'Lỗi hệ thống khi cập nhật sản phẩm.';
                $_SESSION['messageType'] = 'danger';
            }
        }
        header("Location: admin.php");
        exit;
    }
    
    if (isset($_POST['restore_product_id'])) {
        $productId = (int)$_POST['restore_product_id'];
        $categoryId = (int)$_POST['restore_category_id'];
        
        if ($productId > 0 && $categoryId > 0) {
            $sqlRestore = "UPDATE products SET product_name = REPLACE(product_name, ' (Đã xóa)', ''), category_id = ? WHERE product_id = ?";
            $stmtRestore = mysqli_prepare($conn, $sqlRestore);
            if ($stmtRestore) {
                mysqli_stmt_bind_param($stmtRestore, "ii", $categoryId, $productId);
                mysqli_stmt_execute($stmtRestore);
                if (mysqli_stmt_affected_rows($stmtRestore) > 0) {
                    $_SESSION['message'] = 'Khôi phục sản phẩm thành công.';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Không thể khôi phục sản phẩm.';
                    $_SESSION['messageType'] = 'danger';
                }
                mysqli_stmt_close($stmtRestore);
            } else {
                $_SESSION['message'] = 'Lỗi hệ thống khi khôi phục sản phẩm.';
                $_SESSION['messageType'] = 'danger';
            }
        }
        header("Location: admin.php");
        exit;
    }

    if (isset($_POST['update_order_status'])) {
        $orderId   = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        $allowedStatuses = ['preparing', 'delivering', 'delivered', 'cancelled'];

        if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
            $sqlU = "UPDATE orders SET status = ? WHERE order_id = ?";
            $stmtU = mysqli_prepare($conn, $sqlU);
            if ($stmtU) {
                mysqli_stmt_bind_param($stmtU, "si", $newStatus, $orderId);
                mysqli_stmt_execute($stmtU);

                if (mysqli_stmt_affected_rows($stmtU) > 0) {
                    $_SESSION['message'] = 'Cập nhật trạng thái đơn hàng thành công.';
                    $_SESSION['messageType'] = 'success';
                } else {
                    $_SESSION['message'] = 'Không thể cập nhật trạng thái (đơn không tồn tại hoặc trạng thái không đổi).';
                    $_SESSION['messageType'] = 'danger';
                }

                mysqli_stmt_close($stmtU);
            } else {
                $_SESSION['message'] = 'Lỗi hệ thống khi cập nhật trạng thái đơn hàng.';
                $_SESSION['messageType'] = 'danger';
            }
        } else {
            $_SESSION['message'] = 'Dữ liệu cập nhật trạng thái không hợp lệ.';
            $_SESSION['messageType'] = 'danger';
        }
        header("Location: admin.php");
        exit;
    }
}
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
<h2 class="h4 mb-3">
    <a href="report.php" class="btn btn-outline-success btn-sm float-end">
        <i class="bi bi-graph-up"></i> Xem Báo cáo
    </a>
</h2>
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
                        <label for="image" class="form-label">Tên file ảnh (images/)</label>
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
                                            <?php if (strpos($p['product_name'], '(Đã xóa)') !== false): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="restore_product_id" value="<?php echo $p['product_id']; ?>">
                                                    <select name="restore_category_id" class="form-select form-select-sm d-inline-block" style="width: auto;" required>
                                                        <option value="">Chọn danh mục</option>
                                                        <?php foreach ($categories as $cat): ?>
                                                            <option value="<?php echo $cat['category_id']; ?>">
                                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Khôi phục
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa sản phẩm này?');">
                                                    <input type="hidden" name="delete_product_id" value="<?php echo $p['product_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Xóa</button>
                                                </form>
                                            <?php endif; ?>
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
                            <th>Hành động</th>
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
                                <?php elseif ($o['status'] === 'delivered'): ?>
                                    <span class="badge bg-success">Đã giao</span>
                                <?php elseif ($o['status'] === 'cancelled'): ?>
                                    <span class="badge bg-danger">Đã hủy</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Khác</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('H:i d/m', strtotime($o['created_at'])); ?></td>

                            <td>
                                <!-- Từ chuẩn bị -> giao hàng -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?php echo $o['order_id']; ?>">
                                    <input type="hidden" name="new_status" value="delivering">
                                    <button type="submit"
                                            name="update_order_status"
                                            class="btn btn-sm btn-outline-info"
                                            <?php echo $o['status'] !== 'preparing' ? 'disabled' : ''; ?>>
                                        Cho giao hàng
                                    </button>
                                </form>

                                <!-- Đánh dấu đã giao -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?php echo $o['order_id']; ?>">
                                    <input type="hidden" name="new_status" value="delivered">
                                    <button type="submit"
                                            name="update_order_status"
                                            class="btn btn-sm btn-outline-success"
                                            <?php echo $o['status'] === 'delivered' ? 'disabled' : ''; ?>>
                                        Đã giao
                                    </button>
                                </form>

                                <!-- Hủy đơn -->
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="order_id" value="<?php echo $o['order_id']; ?>">
                                    <input type="hidden" name="new_status" value="cancelled">
                                    <button type="submit"
                                            name="update_order_status"
                                            class="btn btn-sm btn-outline-danger"
                                            <?php echo $o['status'] === 'cancelled' ? 'disabled' : ''; ?>>
                                        Hủy
                                    </button>
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
