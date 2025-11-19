<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

// Khởi tạo giỏ nếu chưa có
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = &$_SESSION['cart']; // tham chiếu cho tiện

// Xử lý các action: add, remove, update, clear
$action = $_GET['action'] ?? '';

// Thêm vào giỏ: cart.php?action=add&id=...
if ($action === 'add' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        if (!isset($cart[$id])) {
            $cart[$id] = 0;
        }
        $cart[$id]++; // mỗi lần click +1
    }
    header("Location: cart.php");
    exit;
}

// Xóa 1 món: cart.php?action=remove&id=...
if ($action === 'remove' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if (isset($cart[$id])) {
        unset($cart[$id]);
    }
    header("Location: cart.php");
    exit;
}

// Xóa toàn bộ giỏ: cart.php?action=clear
if ($action === 'clear') {
    $cart = [];
    header("Location: cart.php");
    exit;
}

// Cập nhật số lượng (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $qtys = $_POST['qty'] ?? [];
    foreach ($qtys as $pid => $qty) {
        $pid = (int)$pid;
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($cart[$pid]);
        } else {
            $cart[$pid] = $qty;
        }
    }
    header("Location: cart.php");
    exit;
}

// Sau khi xử lý action, chuẩn bị data để hiển thị
$cart = $_SESSION['cart']; // reload
$products = [];
$subtotal = 0;
$totalItems = 0;

if (!empty($cart)) {
    $productIds = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $sql = "SELECT product_id, product_name, price 
            FROM products 
            WHERE product_id IN ($placeholders)";
    $stmt = mysqli_prepare($conn, $sql);

    $types = str_repeat('i', count($productIds));
    mysqli_stmt_bind_param($stmt, $types, ...$productIds);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $products[$row['product_id']] = $row;
    }
    mysqli_stmt_close($stmt);

    foreach ($cart as $pid => $qty) {
        if (!isset($products[$pid])) continue;
        $price = (float)$products[$pid]['price'];
        $qty   = (int)$qty;
        $lineTotal = $price * $qty;
        $subtotal += $lineTotal;
        $totalItems += $qty;
    }
}

include 'header.php';
?>

<h2 class="h4 mb-3">Giỏ hàng của bạn</h2>

<?php if (empty($cart)): ?>

    <div class="alert alert-info">
        Giỏ hàng hiện đang trống. 
        <a href="index.php" class="alert-link">Tiếp tục chọn món</a>.
    </div>

<?php else: ?>

    <form method="post">
        <div class="table-responsive mb-3">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Món</th>
                        <th class="text-center" style="width:120px;">Số lượng</th>
                        <th class="text-end">Đơn giá</th>
                        <th class="text-end">Thành tiền</th>
                        <th class="text-center" style="width:80px;">Xóa</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cart as $pid => $qty): 
                    if (!isset($products[$pid])) continue;
                    $p = $products[$pid];
                    $qty = (int)$qty;
                    $lineTotal = $p['price'] * $qty;
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                        <td class="text-center">
                            <input type="number" name="qty[<?php echo $pid; ?>]" 
                                   value="<?php echo $qty; ?>" min="1" 
                                   class="form-control form-control-sm text-center">
                        </td>
                        <td class="text-end">
                            <?php echo number_format($p['price'], 0, ',', '.'); ?> đ
                        </td>
                        <td class="text-end">
                            <?php echo number_format($lineTotal, 0, ',', '.'); ?> đ
                        </td>
                        <td class="text-center">
                            <a href="cart.php?action=remove&id=<?php echo $pid; ?>" 
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Xóa món này khỏi giỏ?');">
                                X
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Tạm tính</th>
                        <th class="text-end">
                            <?php echo number_format($subtotal, 0, ',', '.'); ?> đ
                        </th>
                        <th></th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Tổng số món</th>
                        <th class="text-end"><?php echo $totalItems; ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="d-flex justify-content-between gap-2 mb-3">
            <button type="submit" name="update_cart" class="btn btn-outline-secondary">
                Cập nhật giỏ hàng
            </button>

            <a href="cart.php?action=clear" 
               class="btn btn-outline-danger"
               onclick="return confirm('Bạn có chắc muốn xóa toàn bộ giỏ hàng?');">
                Xóa hết giỏ hàng
            </a>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="btn btn-light">
                ← Tiếp tục chọn món
            </a>
            <a href="checkout.php" class="btn btn-danger">
                Tiến hành đặt hàng
            </a>
        </div>
    </form>

<?php endif; ?>

<?php include 'footer.php'; ?>
