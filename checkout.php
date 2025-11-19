<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

// Toạ độ cửa hàng (bạn tra đúng rồi điền vào đây)
$SHOP_LAT = 10.86566425561328;    
$SHOP_LON = 106.61627746661038;  

// Nếu chưa đăng nhập -> về login
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Nếu giỏ hàng trống -> về trang chủ
$cart = $_SESSION['cart'] ?? [];
if (empty($cart) || !is_array($cart)) {
    header("Location: index.php");
    exit;
}

// ====== CẤU HÌNH THỜI GIAN ======

// Chuẩn bị: 10 phút / món (theo yêu cầu ban đầu)
const PREP_PER_ITEM_MIN = 10;

// Giao hàng: theo khoảng cách (km)
function estimate_delivery_minutes(float $distanceKm): int {
    if ($distanceKm <= 3)  return 10;
    if ($distanceKm <= 7)  return 15;
    if ($distanceKm <= 12) return 20;
    return 25; // xa hơn thì 25p
}

// Hàm tính ETA từ tổng số phút
function calculate_eta_from_minutes(int $totalMinutes): DateTime {
    $now = new DateTime(); // đã dùng timezone Asia/Ho_Chi_Minh trong config.php
    $eta = clone $now;
    $eta->add(new DateInterval('PT' . $totalMinutes . 'M'));
    return $eta;
}

// Hàm tính phí giao hàng
function calculate_shipping_fee(float $distanceKm): int {
    $baseFee = 20000; // 20.000đ
    if ($distanceKm <= 5) {
        return $baseFee;
    }
    $extraDistance = max(0, $distanceKm - 5);
    $extraBlocks   = ceil($extraDistance / 5); // mỗi block 5km
    $extraFee      = $extraBlocks * 5000;
    return $baseFee + $extraFee;
}

// ====== LẤY SẢN PHẨM TRONG GIỎ ======
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

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[$row['product_id']] = $row;
}
mysqli_stmt_close($stmt);

if (empty($products)) {
    header("Location: cart.php");
    exit;
}

// Tính subtotal + tổng số món
$subtotal   = 0;
$itemsCount = 0;

foreach ($cart as $pid => $qty) {
    if (!isset($products[$pid])) continue;
    $price = (float)$products[$pid]['price'];
    $qty   = (int)$qty;
    $subtotal   += $price * $qty;
    $itemsCount += $qty;
}

// Thời gian chuẩn bị dự kiến (chỉ phụ thuộc số món, nên có thể show luôn)
$prepEstimateMinutes = max(PREP_PER_ITEM_MIN * $itemsCount, PREP_PER_ITEM_MIN);

$errors = [];
$orderCreated = false;
$orderId = null;
$eta = null;
$shippingFee = 0;
$total = 0;
$address = '';
$distanceKm = 0;
$prepMinutes = 0;
$deliveryMinutes = 0;

// ====== SUBMIT ĐƠN HÀNG ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address    = trim($_POST['address'] ?? '');
    $distanceKm = (float)($_POST['distance_km'] ?? 0);

    if ($address === '') {
        $errors[] = "Vui lòng nhập địa chỉ giao hàng.";
    }
    if ($distanceKm <= 0) {
        $errors[] = "Khoảng cách chưa được tính. Vui lòng bấm nút 'Tính khoảng cách & phí giao hàng (OSM)'.";
    }

    if (empty($errors)) {
        $shippingFee = calculate_shipping_fee($distanceKm);

        // Tính thời gian chuẩn bị & giao thực tế cho đơn này
        $prepMinutes     = max(PREP_PER_ITEM_MIN * $itemsCount, PREP_PER_ITEM_MIN);
        $deliveryMinutes = estimate_delivery_minutes($distanceKm);
        $totalMinutes    = $prepMinutes + $deliveryMinutes;

        $eta       = calculate_eta_from_minutes($totalMinutes);
        $etaString = $eta->format('Y-m-d H:i:s');

        $total = $subtotal + $shippingFee;

        // Insert vào bảng orders 
        // (có prep_minutes, delivery_minutes, status, estimated_delivery_time)
        $sqlOrder = "INSERT INTO orders 
                        (user_id, total, shipping_fee, shipping_address, distance_km, 
                         prep_minutes, delivery_minutes, status, estimated_delivery_time) 
                     VALUES 
                        (?, ?, ?, ?, ?, ?, ?, 'preparing', ?)";
        $stmt = mysqli_prepare($conn, $sqlOrder);
        mysqli_stmt_bind_param(
            $stmt,
            "idisdiis",
            $_SESSION['user_id'], // i
            $total,               // d
            $shippingFee,         // i
            $address,             // s
            $distanceKm,          // d
            $prepMinutes,         // i
            $deliveryMinutes,     // i
            $etaString            // s
        );

        if (mysqli_stmt_execute($stmt)) {
            $orderId = mysqli_insert_id($conn);
            $orderCreated = true;

            // Insert từng món
            $sqlItem = "INSERT INTO order_items (order_id, product_id, quantity, price)
                        VALUES (?, ?, ?, ?)";
            $stmtItem = mysqli_prepare($conn, $sqlItem);

            foreach ($cart as $pid => $qty) {
                if (!isset($products[$pid])) continue;
                $price = (float)$products[$pid]['price'];
                $qty   = (int)$qty;
                mysqli_stmt_bind_param($stmtItem, "iiid", $orderId, $pid, $qty, $price);
                mysqli_stmt_execute($stmtItem);
            }
            mysqli_stmt_close($stmtItem);

            // Xoá giỏ hàng
            unset($_SESSION['cart']);
        } else {
            $errors[] = "Không thể tạo đơn hàng. Vui lòng thử lại.";
        }

        mysqli_stmt_close($stmt);
    }
}

include 'header.php';
?>

<h2 class="h4 mb-3">Xác nhận đơn hàng</h2>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div>- <?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($orderCreated && $orderId): ?>

    <?php
    $etaFrom = clone $eta;
    $etaTo   = clone $eta;
    $etaFrom->sub(new DateInterval('PT5M'));
    $etaTo->add(new DateInterval('PT5M'));
    ?>

    <div class="alert alert-success">
        <h5 class="alert-heading">Đặt hàng thành công!</h5>
        <p>Mã đơn của bạn: <strong>#<?php echo $orderId; ?></strong></p>
        <p>Địa chỉ giao hàng: <strong><?php echo htmlspecialchars($address); ?></strong></p>
        <p>
            Khoảng cách ước tính: 
            <strong><?php echo number_format($distanceKm, 1); ?> km</strong><br>
            Thời gian chuẩn bị: <strong><?php echo $prepMinutes; ?> phút</strong><br>
            Thời gian giao hàng: <strong><?php echo $deliveryMinutes; ?> phút</strong><br>
            Phí giao hàng: 
            <strong><?php echo number_format($shippingFee, 0, ',', '.'); ?> đ</strong>
        </p>
        <p>
            Dự kiến giao từ 
            <strong><?php echo $etaFrom->format('H:i'); ?></strong> 
            đến 
            <strong><?php echo $etaTo->format('H:i'); ?></strong>
            ngày <?php echo $eta->format('d/m/Y'); ?>.
        </p>
        <p>
            Tổng tiền: <strong><?php echo number_format($total, 0, ',', '.'); ?> đ</strong> 
            (đã bao gồm phí giao hàng).
        </p>
        <p>
            Bạn có thể xem lại đơn trong mục <a href="my_orders.php">Đơn hàng</a>.
        </p>
        <hr>
        <a href="index.php" class="btn btn-primary">Tiếp tục đặt món</a>
    </div>

<?php else: ?>

    <div class="row">
        <div class="col-md-7 mb-3">
            <div class="card">
                <div class="card-header">
                    Giỏ hàng của bạn
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Món</th>
                                    <th class="text-center">SL</th>
                                    <th class="text-end">Đơn giá</th>
                                    <th class="text-end">Thành tiền</th>
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
                                    <td class="text-center"><?php echo $qty; ?></td>
                                    <td class="text-end">
                                        <?php echo number_format($p['price'], 0, ',', '.'); ?> đ
                                    </td>
                                    <td class="text-end">
                                        <?php echo number_format($lineTotal, 0, ',', '.'); ?> đ
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
                                </tr>
                                <tr>
                                    <th colspan="3" class="text-end">Tổng số món</th>
                                    <th class="text-end">
                                        <?php echo $itemsCount; ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Thông tin giao hàng -->
        <div class="col-md-5 mb-3">
            <div class="card">
                <div class="card-header">
                    Thông tin giao hàng
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        Cửa hàng: <strong>72 Tô Ký, Quận 12, TP.HCM</strong><br>
                        Thời gian chuẩn bị dự kiến: 
                        <strong><?php echo $prepEstimateMinutes; ?> phút</strong>
                        (khoảng 10 phút / món).<br>
                        Thời gian giao hàng sẽ được ước lượng theo khoảng cách của bạn.
                    </p>

                    <form method="post" id="checkoutForm">
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ giao hàng của bạn</label>
                            <input type="text" name="address" id="addressInput"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($address); ?>" required>
                        </div>

                        <!-- distance_km: input ẩn, JS sẽ tự fill -->
                        <input type="hidden" name="distance_km" id="distance_km" 
                               value="<?php echo htmlspecialchars($distanceKm ?: ''); ?>">

                        <div class="mb-2">
                            <button type="button" class="btn btn-outline-secondary w-100"
                                    id="btnCalcDistance">
                                Tính khoảng cách &amp; phí giao hàng (OSM)
                            </button>
                        </div>

                        <div class="mb-3">
                            <small id="shippingPreview" class="text-muted">
                                Phí giao hàng: 20.000đ trong 5km đầu, 
                                sau đó mỗi 5km + 5.000đ. Khoảng cách sẽ được tự tính bằng OpenStreetMap.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-danger w-100">
                            Xác nhận đặt hàng
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- JS OSM như bạn đang dùng, không đổi phần logic chính -->
<script>
const storeLat = <?php echo json_encode($SHOP_LAT); ?>;
const storeLon = <?php echo json_encode($SHOP_LON); ?>;

// Geocoding địa chỉ -> lat/lon bằng Nominatim (cố gắng 2 lần, nhưng đều là dữ liệu thật)
// Geocoding địa chỉ -> lat/lon bằng Nominatim
async function geocodeAddressOSM(rawAddress) {
    const tries = [];

    const addr = rawAddress.trim();

    // 1. Nguyên câu người dùng nhập
    if (addr) {
        tries.push(addr);
    }

    // 2. Thay "Thành phố Hồ Chí Minh" / "TP HCM" bằng "Ho Chi Minh City, Vietnam"
    let normalized = addr
        .replace(/thành phố hồ chí minh/ig, '')
        .replace(/tp\.?\s*hcm/ig, '')
        .replace(/tp\.?\s*hồ chí minh/ig, '')
        .trim();

    if (normalized) {
        tries.push(normalized + ', Ho Chi Minh City, Vietnam');
    }

    // 3. Chỉ lấy phần "số nhà + tên đường" rồi thêm city cố định
    const firstComma = addr.indexOf(',');
    if (firstComma !== -1) {
        const short = addr.slice(0, firstComma).trim();
        if (short) {
            tries.push(short + ', Ho Chi Minh City, Vietnam');
        }
    }

    // 4. Thêm ", Vietnam" nếu thiếu
    tries.push(addr + ', Vietnam');

    // Loại bỏ trùng lặp
    const uniqueTries = [...new Set(tries)];

    console.log('OSM geocode tries:', uniqueTries);

    for (const q of uniqueTries) {
        const url = "https://nominatim.openstreetmap.org/search?format=json&limit=1&q="
            + encodeURIComponent(q);

        console.log('Geocoding with:', url);

        const res = await fetch(url, { headers: { "Accept": "application/json" } });
        if (!res.ok) {
            console.warn('Geocoding HTTP error', res.status, 'for query', q);
            continue;
        }

        const data = await res.json();
        if (data && data.length) {
            console.log('Geocode success for', q, '=>', data[0]);
            return {
                lat: parseFloat(data[0].lat),
                lon: parseFloat(data[0].lon)
            };
        }
    }

    throw new Error("Không tìm thấy địa chỉ phù hợp trên OSM");
}


// Tính khoảng cách đường đi bằng OSRM (dữ liệu thật)
async function calcRouteDistanceOSM(fromLat, fromLon, toLat, toLon) {
    const url = "https://router.project-osrm.org/route/v1/driving/"
        + fromLon + "," + fromLat + ";" + toLon + "," + toLat
        + "?overview=false";

    const res = await fetch(url);
    if (!res.ok) throw new Error("OSRM HTTP " + res.status);
    const data = await res.json();
    if (!data.routes || !data.routes.length) {
        throw new Error("Không tìm được lộ trình lái xe trên OSM");
    }
    const meters = data.routes[0].distance; // mét
    return meters / 1000.0; // km
}

document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById('btnCalcDistance');
    const addrInput = document.getElementById('addressInput');
    const distanceInput = document.getElementById('distance_km');
    const preview = document.getElementById('shippingPreview');

    if (!btn) return;

    btn.addEventListener('click', async function () {
        const addr = addrInput.value.trim();
        if (!addr) {
            alert("Vui lòng nhập địa chỉ giao hàng trước.");
            addrInput.focus();
            return;
        }
        if (!storeLat || !storeLon) {
            alert("Chưa cấu hình toạ độ cửa hàng (storeLat/storeLon).");
            return;
        }

        btn.disabled = true;
        btn.textContent = "Đang tính khoảng cách...";
        preview.textContent = "Đang tính khoảng cách bằng OpenStreetMap...";

        try {
            // 1) Geocode địa chỉ khách trên OSM
            const dest = await geocodeAddressOSM(addr);

            // 2) Tính khoảng cách đường đi bằng OSRM
            const km = await calcRouteDistanceOSM(
                storeLat, storeLon,
                dest.lat, dest.lon
            );

            const kmRounded = Math.round(km * 10) / 10;
            distanceInput.value = kmRounded;

            // 3) Tính phí ship giống PHP (chỉ để hiển thị cho khách)
            let fee = 20000;
            if (kmRounded > 5) {
                const extraBlocks = Math.ceil((kmRounded - 5) / 5);
                fee += extraBlocks * 5000;
            }

            preview.textContent =
                "Khoảng cách ước tính từ OSM: khoảng " + kmRounded.toFixed(1) + " km, "
                + "phí giao hàng tạm tính: " + fee.toLocaleString('vi-VN') + " đ.";

            btn.textContent = "Đã tính xong, có thể đặt hàng";
        } catch (err) {
            console.error(err);
            alert("Không tính được khoảng cách từ OpenStreetMap: " + err.message
                  + "\nVui lòng kiểm tra lại địa chỉ (ghi rõ quận, thành phố).");

            preview.textContent = "Không tính được khoảng cách, vui lòng nhập lại địa chỉ cho chính xác.";
            distanceInput.value = "";

            btn.textContent = "Tính khoảng cách & phí giao hàng (OSM)";
        } finally {
            btn.disabled = false;
        }
    });
});
</script>


<?php include 'footer.php'; ?>
