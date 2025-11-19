<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($conn)) {
    include 'config.php';
}

if (isset($_GET['logout']) && $_GET['logout'] == 1) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cartCount += (int)$qty; // cộng dồn số lượng
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>FoodBond - Đồ ăn nhanh giao tận nơi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        html, body {
            height: 100%;
        }
        body {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
        }
        main.page-content {
            flex: 1 0 auto;
        }
        footer {
            flex-shrink: 0;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        .brand-tagline {
            font-size: 0.9rem;
            color: #ffc107;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">

        <a class="navbar-brand" href="index.php">
            FoodBond
            <span class="brand-tagline">| Fast food, fast bond</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">

            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Trang chủ</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="cart.php">
                        Giỏ hàng
                        <?php if ($cartCount > 0): ?>
                            <span class="badge rounded-pill bg-danger ms-1">
                                <?php echo $cartCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
               <?php if (!empty($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="my_orders.php">Đơn hàng</a>
                    </li>
                <?php endif; ?>
                 

                <?php if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? 'user') === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">Admin</a>
                    </li>
                <?php endif; ?>

                <?php if (!empty($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <span class="nav-link">
                            Xin chào, <?php echo htmlspecialchars($_SESSION['fullname']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?logout=1">Đăng xuất</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Đăng nhập</a>
                    </li>
                <?php endif; ?>
            </ul>

        </div>
    </div>
</nav>

<main class="page-content">
    <div class="container mt-4">
