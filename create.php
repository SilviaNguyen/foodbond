<?php
require 'config.php';

$fullname = 'Admin FoodBond';
$email    = 'admin@foodbond.local'; 
$phone    = '0000000000';
$address  = 'FoodBond HQ';
$password_plain = '6h50';         

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


$hash = password_hash($password_plain, PASSWORD_DEFAULT);


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
