<?php
require 'config.php';

$fullname = 'Admin FoodBond';
$email    = 'admin@foodbond.local'; // email đăng nhập admin
$phone    = '0000000000';
$address  = 'FoodBond HQ';
$password_plain = '6h50';         // mật khẩu muốn dùng

// Kiểm tra xem đã có admin này chưa
$sqlCheck = "SELECT user_id FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sqlCheck);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);

if (mysqli_stmt_num_rows($stmt) > 0) {
    echo "Tài khoản admin đã tồn tại. Không tạo thêm.";
    exit;
}
mysqli_stmt_close($stmt);

// Tạo hash mật khẩu
$hash = password_hash($password_plain, PASSWORD_DEFAULT);

// Insert admin
$sqlInsert = "INSERT INTO users (fullname, email, phone, address, password, role)
              VALUES (?, ?, ?, ?, ?, 'admin')";
$stmt = mysqli_prepare($conn, $sqlInsert);
mysqli_stmt_bind_param($stmt, "sssss", $fullname, $email, $phone, $address, $hash);

if (mysqli_stmt_execute($stmt)) {
    echo "Tạo admin thành công!<br>";
    echo "Email: $email<br>";
    echo "Mật khẩu: $password_plain";
} else {
    echo "Lỗi khi tạo admin: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
