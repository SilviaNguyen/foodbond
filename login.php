<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = "Vui lòng nhập email và mật khẩu.";
    } else {
        $sql = "SELECT user_id, fullname, password, role FROM users WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role']     = $user['role']; 
            $_SESSION['address']   = $user['address'];
            header("Location: index.php");
            exit;
        } else {
            $errors[] = "Email hoặc mật khẩu không đúng.";
        }
    }
}

include 'header.php';
?>

<h1 class="h4 mb-3">Đăng nhập FoodBond</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div>- <?php echo htmlspecialchars($e); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" class="row g-3">

    <div class="col-12">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control"
               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
    </div>

    <div class="col-12">
        <label class="form-label">Mật khẩu</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="col-12 d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary">Đăng nhập</button>
        <a href="register.php">Chưa có tài khoản? Đăng ký</a>
    </div>

</form>

<?php include 'footer.php'; ?>
