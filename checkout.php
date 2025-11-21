<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

$SHOP_LON = 106.61635256938862;    
$SHOP_LAT = 10.865558890717343;  

// Nếu chưa đăng nhập -> về login
if (empty($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) || !is_array($cart)) {
    header("Location: index.php");
    exit;
}

const PREP_BASE_MIN        = 15; 
const PREP_ITEMS_PER_BATCH = 5;  
const PREP_PER_BATCH_MIN   = 5;  
const PREP_MAX_MIN         = 45; 

function estimate_prep_minutes(int $itemsCount): int {
    if ($itemsCount <= 0) {
        return PREP_BASE_MIN;
    }

    $batches = (int) ceil($itemsCount / PREP_ITEMS_PER_BATCH);
    $minutes = PREP_BASE_MIN + ($batches - 1) * PREP_PER_BATCH_MIN;

    return min($minutes, PREP_MAX_MIN);
}

function estimate_delivery_minutes(float $distanceKm): int {
    if ($distanceKm <= 3)  return 10;
    if ($distanceKm <= 7)  return 15;
    if ($distanceKm <= 12) return 20;
    return 25; 
}

function calculate_eta_from_minutes(int $totalMinutes): DateTime {
    $now = new DateTime(); 
    $eta = clone $now;
    $eta->add(new DateInterval('PT' . $totalMinutes . 'M'));
    return $eta;
}

function calculate_shipping_fee(float $distanceKm): int {
    $baseFee = 20000; 
    if ($distanceKm <= 5) {
        return $baseFee;
    }
    $extraDistance = max(0, $distanceKm - 5);
    $extraBlocks   = ceil($extraDistance / 5); 
    $extraFee      = $extraBlocks * 5000;
    return $baseFee + $extraFee;
}

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

$subtotal   = 0;
$itemsCount = 0;

foreach ($cart as $pid => $qty) {
    if (!isset($products[$pid])) continue;
    $price = (float)$products[$pid]['price'];
    $qty   = (int)$qty;
    $subtotal   += $price * $qty;
    $itemsCount += $qty;
}

$prepEstimateMinutes = estimate_prep_minutes($itemsCount);

$errors = [];
$orderCreated = false;
$orderId = null;
$eta = null;
$shippingFee = 0;
$total = 0;
$address = '';
$distanceKm = 0;
$prepMinutes     = estimate_prep_minutes($itemsCount);
$deliveryMinutes = estimate_delivery_minutes($distanceKm);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address    = trim($_POST['address'] ?? '');
    $distanceKm = (float)($_POST['distance_km'] ?? 0);

    // Validation
    if (empty($address)) {
        $errors[] = "Vui lòng nhập địa chỉ giao hàng.";
    }
    if ($distanceKm <= 0) {
        $errors[] = "Không tính được khoảng cách. Vui lòng nhập địa chỉ rõ hơn.";
    }

    if (empty($errors)) {
        $shippingFee = calculate_shipping_fee($distanceKm);
        $prepMinutes     = estimate_prep_minutes($itemsCount);
        $deliveryMinutes = estimate_delivery_minutes($distanceKm);
        $totalMinutes    = $prepMinutes + $deliveryMinutes;

        $eta       = calculate_eta_from_minutes($totalMinutes);
        $etaString = $eta->format('Y-m-d H:i:s');

        $total = $subtotal + $shippingFee;

        // Insert vào bảng orders 
        $sqlOrder = "INSERT INTO orders 
                        (user_id, total, shipping_fee, shipping_address, distance_km, 
                         prep_minutes, delivery_minutes, status, estimated_delivery_time) 
                     VALUES 
                        (?, ?, ?, ?, ?, ?, ?, 'preparing', ?)";
        $stmt = mysqli_prepare($conn, $sqlOrder);
        mysqli_stmt_bind_param(
            $stmt,
            "idisdiis",
            $_SESSION['user_id'], 
            $total,               
            $shippingFee,         
            $address,             
            $distanceKm,          
            $prepMinutes,         
            $deliveryMinutes,     
            $etaString            
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
                        <br>
                        Thời gian giao hàng sẽ được ước lượng theo khoảng cách của bạn.
                    </p>

                    <form method="post" id="checkoutForm">
                        <div class="mb-3">
                            <label class="form-label">Địa chỉ giao hàng của bạn</label>
                            <input type="text" name="address" id="addressInput"
                                   class="form-control"
                                   placeholder="Ví dụ: 123 Nguyễn Văn Linh, Quận 7"
                                   value="<?php echo htmlspecialchars($address); ?>" required>
                            <small class="form-text text-muted">
                                Nhập địa chỉ chi tiết (số nhà, tên đường, quận/huyện)
                            </small>
                        </div>

                        <input type="hidden" name="distance_km" id="distance_km" 
                               value="<?php echo htmlspecialchars($distanceKm ?: ''); ?>">

                        <div class="mb-3">
                            <small id="shippingPreview" class="text-muted">
                                Phí giao hàng: 20.000đ trong 5km đầu, 
                                sau đó mỗi 5km + 5.000đ.
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

<script>
const storeLat = <?php echo json_encode($SHOP_LAT); ?>;
const storeLon = <?php echo json_encode($SHOP_LON); ?>;

console.log('cửa hàng:', { lat: storeLat, lon: storeLon });

const geocodeCache = new Map();
let abortController = null; 

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function geocodeAddressOSM(rawAddress, signal) {
    const addr = (rawAddress || "").trim();
    if (!addr) throw new Error("Địa chỉ trống");

    const cacheKey = addr.toLowerCase();
    if (geocodeCache.has(cacheKey)) {
        console.log('Lấy từ cache:', geocodeCache.get(cacheKey));
        return geocodeCache.get(cacheKey);
    }
    const tries = [
        addr + ", Ho Chi Minh City, Vietnam",
        addr + ", Hồ Chí Minh, Vietnam",
        addr + ", Sài Gòn, Vietnam",
        addr + ", Vietnam"
    ];

    const unique = [...new Set(tries)];

    for (let i = 0; i < unique.length; i++) {
        if (signal && signal.aborted) {
            throw new Error('Request đã bị hủy');
        }

        const q = unique[i];
        console.log(`Thử geocode (${i + 1}/${unique.length}):`, q);
        
        try {
            const url = "https://nominatim.openstreetmap.org/search?format=json&limit=3&q="
                + encodeURIComponent(q);

            const res = await fetch(url, {
                signal: signal,
                headers: {
                    "Accept-Language": "vi,en;q=0.8",
                    "User-Agent": "FoodBondCheckout/1.0"
                }
            });

            if (!res.ok) {
                console.warn(`HTTP ${res.status} cho query: ${q}`);
                await delay(1500);
                continue;
            }

            const data = await res.json();
            console.log(`Kết quả cho "${q}":`, data);
            
            if (Array.isArray(data) && data.length > 0) {
                let bestMatch = data[0];
                for (const item of data) {
                    if (item.display_name.includes('Hồ Chí Minh') || 
                        item.display_name.includes('Ho Chi Minh') ||
                        item.display_name.includes('Sài Gòn')) {
                        bestMatch = item;
                        break;
                    }
                }
                const result = {
                    lat: parseFloat(bestMatch.lat),
                    lon: parseFloat(bestMatch.lon),
                    display_name: bestMatch.display_name
                };
                
                console.log('Tìm thấy tọa độ:', result);
                
                geocodeCache.set(cacheKey, result);
                
                return result;
            }

            await delay(1500);

        } catch (err) {
            if (err.name === 'AbortError') {
                console.log('Request bị hủy');
                throw err;
            }
            console.error(`Lỗi khi geocode "${q}":`, err);
            await delay(1500);
        }
    }

    throw new Error("Không tìm thấy địa chỉ phù hợp. Vui lòng nhập địa chỉ chi tiết hơn (số nhà, tên đường, quận).");
}

async function calcRouteDistanceOSM(fromLat, fromLon, toLat, toLon) {
    const url = "https://router.project-osrm.org/route/v1/driving/"
        + fromLon + "," + fromLat + ";"  
        + toLon + "," + toLat            
        + "?overview=false&steps=false";

    console.log('Đang tính khoảng cách:', url);

    const res = await fetch(url);
    if (!res.ok) throw new Error("OSRM HTTP " + res.status);

    const data = await res.json();
    console.log('OSRM response:', data);

    if (!data.routes || !data.routes.length) {
        throw new Error("Không tìm được đường lái xe");
    }

    const distanceKm = data.routes[0].distance / 1000;
    const durationMin = data.routes[0].duration / 60;
    
    console.log('Kết quả:', {
        distance: distanceKm.toFixed(2) + ' km',
        duration: durationMin.toFixed(1) + ' phút'
    });

    return distanceKm;
}

document.addEventListener("DOMContentLoaded", function () {
    const addrInput = document.getElementById('addressInput');
    const distanceInput = document.getElementById('distance_km');
    const preview = document.getElementById('shippingPreview');

    if (!addrInput) return;

    async function autoCalcShipping() {
        const addr = addrInput.value.trim();
        if (!addr) {
            preview.innerHTML = '<span class="text-muted">Hãy nhập địa chỉ để hệ thống tự tính phí giao hàng.</span>';
            distanceInput.value = "";
            return;
        }

        preview.innerHTML = '<span class="text-info">...</span>';
        
        try {
            const dest = await geocodeAddressOSM(addr);
            const km = await calcRouteDistanceOSM(storeLat, storeLon, dest.lat, dest.lon);
            const kmRounded = Math.round(km * 10) / 10;

            distanceInput.value = kmRounded;
            let fee = 20000;
            if (kmRounded > 5) {
                fee += Math.ceil((kmRounded - 5) / 5) * 5000;
            }

            let deliveryTime = 10;
            if (kmRounded > 3 && kmRounded <= 7) deliveryTime = 15;
            else if (kmRounded > 7 && kmRounded <= 12) deliveryTime = 20;
            else if (kmRounded > 12) deliveryTime = 25;

            preview.innerHTML = `
                Khoảng cách: <strong>${kmRounded.toFixed(1)} km</strong><br>
                hời gian giao: <strong>~${deliveryTime} phút</strong><br>
                Phí giao hàng: <strong>${fee.toLocaleString('vi-VN')} đ</strong>
            `;

        } catch (err) {
            console.error('Lỗi tính khoảng cách:', err);
            preview.innerHTML = `
                <span class="text-danger">
                    ${err.message}<br>
                    Vui lòng nhập địa chỉ rõ hơn (số nhà, tên đường, quận).
                </span>
            `;
            distanceInput.value = "";
        }
    }

    let timer;
    const DELAY = 3000;

    addrInput.addEventListener("input", function () {
        clearTimeout(timer);
        preview.innerHTML = '<span class="text-muted">Đang chờ bạn nhập xong...</span>';
        timer = setTimeout(autoCalcShipping, DELAY);
    });

    // Tính lại khi blur (rời ô)
    addrInput.addEventListener("blur", autoCalcShipping);

    // Validate khi submit
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const distance = parseFloat(distanceInput.value);
        if (!distance || distance <= 0) {
            e.preventDefault();
            alert('Vui lòng đợi hệ thống tính khoảng cách hoặc nhập địa chỉ rõ hơn.');
            addrInput.focus();
        }
    });
});
</script>

<?php include 'footer.php'; ?>