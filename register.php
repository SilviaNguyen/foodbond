<?php
require 'config.php';

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Kiểm tra đơn giản
    if ($fullname === '' || $email === '' || $password === '') {
        $errors[] = "Vui lòng nhập đầy đủ họ tên, email và mật khẩu.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }

    if ($password !== $confirm) {
        $errors[] = "Mật khẩu xác nhận không khớp.";
    }

    // Nếu chưa có lỗi -> kiểm tra email trùng
    if (empty($errors)) {
        $sqlCheck = "SELECT user_id FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sqlCheck);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $errors[] = "Email này đã được đăng ký. Vui lòng dùng email khác.";
        }
        mysqli_stmt_close($stmt);
    }

    // Nếu OK hết -> insert
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sqlInsert = "INSERT INTO users (fullname, email, phone, address, password) 
                      VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sqlInsert);
        mysqli_stmt_bind_param($stmt, "sssss", $fullname, $email, $phone, $address, $hash);

        if (mysqli_stmt_execute($stmt)) {
            // Tự động đăng nhập luôn
            $_SESSION['user_id'] = mysqli_insert_id($conn);
            $_SESSION['fullname'] = $fullname;
            $success = "Đăng ký thành công! Đang chuyển về trang chủ...";
            // Redirect sau 1s
            header("Refresh: 1; url=index.php");
        } else {
            $errors[] = "Có lỗi xảy ra, vui lòng thử lại.";
        }

        mysqli_stmt_close($stmt);
    }
}

include 'header.php';
?>

<h1 class="h4 mb-3">Đăng ký tài khoản FoodBond</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div>- <?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<form method="post" class="row g-3">

    <div class="col-12">
        <label class="form-label">Họ và tên</label>
        <input type="text" name="fullname" class="form-control"
               value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" required>
    </div>

    <div class="col-12">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">Số điện thoại</label>
        <input type="text" name="phone" class="form-control"
               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label">Địa chỉ</label>
        <input type="text" name="address" class="form-control"
               value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
    </div>

    <div class="col-md-6">
        <label class="form-label">Mật khẩu</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">Xác nhận mật khẩu</label>
        <input type="password" name="confirm_password" class="form-control" required>
    </div>

    <div class="col-12 d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-danger">Đăng ký</button>
        <a href="login.php">Đã có tài khoản? Đăng nhập</a>
    </div>

</form>

<?php include 'footer.php'; ?>
