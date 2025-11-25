<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'admin') {
    header("Location: index.php");
    exit;
}

$sqlOrders = "
    SELECT 
        o.order_id,
        o.user_id,
        o.total,
        o.shipping_fee,
        o.shipping_address,
        o.distance_km,
        o.prep_minutes,
        o.delivery_minutes,
        o.status,
        o.estimated_delivery_time,
        o.created_at,
        u.fullname,
        u.phone
    FROM orders o
    LEFT JOIN users u ON u.user_id = o.user_id
    ORDER BY o.created_at DESC
";
$rsOrders = mysqli_query($conn, $sqlOrders);
$orders = [];
while ($row = mysqli_fetch_assoc($rsOrders)) {
    $orders[] = $row;
}

$sqlProductStats = "
    SELECT 
        p.product_id,
        p.product_name,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.price) as total_revenue,
        COUNT(DISTINCT oi.order_id) as order_count
    FROM order_items oi
    INNER JOIN products p ON p.product_id = oi.product_id
    INNER JOIN orders o ON o.order_id = oi.order_id
    WHERE o.status = 'delivered'
    GROUP BY p.product_id, p.product_name
    ORDER BY total_revenue DESC
";
$rsProductStats = mysqli_query($conn, $sqlProductStats);
$productStats = [];
while ($row = mysqli_fetch_assoc($rsProductStats)) {
    $productStats[] = $row;
}

include 'header.php';
?>

<h2 class="h4 mb-3">
    Báo cáo Đơn hàng & Doanh số
    <a href="admin.php" class="btn btn-outline-primary btn-sm float-end">
        <i class="bi bi-arrow-left"></i> Quay lại Admin
    </a>
</h2>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                Doanh số theo Sản phẩm
            </div>
            <div class="card-body">
                <canvas id="quantityChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                Doanh thu theo Sản phẩm
            </div>
            <div class="card-body">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        Thống kê Chi tiết Sản phẩm
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên Sản phẩm</th>
                        <th>Số lượng Bán</th>
                        <th>Số Đơn</th>
                        <th>Doanh thu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($productStats)): ?>
                        <?php foreach ($productStats as $ps): ?>
                            <tr>
                                <td><?php echo $ps['product_id']; ?></td>
                                <td><?php echo htmlspecialchars($ps['product_name']); ?></td>
                                <td><?php echo number_format($ps['total_quantity'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($ps['order_count'], 0, ',', '.'); ?></td>
                                <td class="fw-bold text-success">
                                    <?php echo number_format($ps['total_revenue'], 0, ',', '.'); ?> đ
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-3">Chưa có dữ liệu</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        Danh sách Đơn hàng
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Khách hàng</th>
                        <th>SĐT</th>
                        <th>Địa chỉ</th>
                        <th>Tổng tiền</th>
                        <th>Phí ship</th>
                        <th>Khoảng cách</th>
                        <th>Trạng thái</th>
                        <th>Ngày đặt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $o): ?>
                            <tr style="cursor: pointer;" onclick="showOrderDetail(<?php echo $o['order_id']; ?>)">
                                <td><?php echo $o['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($o['fullname'] ?? 'Khách lẻ'); ?></td>
                                <td><?php echo htmlspecialchars($o['phone'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($o['shipping_address']); ?></td>
                                <td><?php echo number_format($o['total'], 0, ',', '.'); ?> đ</td>
                                <td><?php echo number_format($o['shipping_fee'], 0, ',', '.'); ?> đ</td>
                                <td><?php echo number_format($o['distance_km'], 2, ',', '.'); ?> km</td>
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
                                <td><?php echo date('H:i d/m/Y', strtotime($o['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-3">Chưa có đơn hàng nào</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="orderDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết Đơn hàng #<span id="modalOrderId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Đang tải...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const productData = <?php echo json_encode($productStats); ?>;

const labels = productData.map(p => p.product_name);
const quantities = productData.map(p => parseInt(p.total_quantity));
const revenues = productData.map(p => parseFloat(p.total_revenue));

const quantityCtx = document.getElementById('quantityChart');
new Chart(quantityCtx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Số lượng bán',
            data: quantities,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

const revenueCtx = document.getElementById('revenueChart');
new Chart(revenueCtx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Doanh thu (VNĐ)',
            data: revenues,
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('vi-VN') + ' đ';
                    }
                }
            }
        }
    }
});

function showOrderDetail(orderId) {
    const modal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    document.getElementById('modalOrderId').textContent = orderId;
    document.getElementById('orderDetailContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Đang tải...</span>
            </div>
        </div>
    `;
    modal.show();
    
    fetch('get_order_detail.php?order_id=' + orderId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let itemsHtml = '';
                data.items.forEach(item => {
                    itemsHtml += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td class="text-center">${item.quantity}</td>
                            <td class="text-end">${parseInt(item.price).toLocaleString('vi-VN')} đ</td>
                            <td class="text-end">${(item.quantity * item.price).toLocaleString('vi-VN')} đ</td>
                        </tr>
                    `;
                });
                
                document.getElementById('orderDetailContent').innerHTML = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Thông tin Khách hàng</h6>
                            <p class="mb-1"><strong>Tên:</strong> ${data.order.fullname || 'Khách lẻ'}</p>
                            <p class="mb-1"><strong>SĐT:</strong> ${data.order.phone || 'N/A'}</p>
                            <p class="mb-1"><strong>Địa chỉ:</strong> ${data.order.shipping_address}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Thông tin Đơn hàng</h6>
                            <p class="mb-1"><strong>Trạng thái:</strong> 
                                ${data.order.status === 'preparing' ? '<span class="badge bg-warning text-dark">Đang chuẩn bị</span>' : 
                                  data.order.status === 'delivering' ? '<span class="badge bg-info text-dark">Đang giao hàng</span>' :
                                  data.order.status === 'delivered' ? '<span class="badge bg-success">Đã giao</span>' :
                                  '<span class="badge bg-danger">Đã hủy</span>'}
                            </p>
                            <p class="mb-1"><strong>Khoảng cách:</strong> ${parseFloat(data.order.distance_km).toFixed(2)} km</p>
                            <p class="mb-1"><strong>Ngày đặt:</strong> ${new Date(data.order.created_at).toLocaleString('vi-VN')}</p>
                        </div>
                    </div>
                    
                    <h6>Chi tiết Sản phẩm</h6>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th class="text-center" width="100">Số lượng</th>
                                <th class="text-end" width="150">Đơn giá</th>
                                <th class="text-end" width="150">Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Tổng tiền món:</strong></td>
                                <td class="text-end"><strong>${parseInt(data.order.total).toLocaleString('vi-VN')} đ</strong></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Phí ship:</strong></td>
                                <td class="text-end"><strong>${parseInt(data.order.shipping_fee).toLocaleString('vi-VN')} đ</strong></td>
                            </tr>
                            <tr class="table-success">
                                <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                                <td class="text-end"><strong>${(parseInt(data.order.total) + parseInt(data.order.shipping_fee)).toLocaleString('vi-VN')} đ</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                `;
            } else {
                document.getElementById('orderDetailContent').innerHTML = `
                    <div class="alert alert-danger">Không thể tải thông tin đơn hàng</div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('orderDetailContent').innerHTML = `
                <div class="alert alert-danger">Lỗi: ${error.message}</div>
            `;
        });
}
</script>

<?php include 'footer.php'; ?>