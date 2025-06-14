<?php
session_start();
require 'includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            // Chuyển hướng theo vai trò
            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } 
            else if ($user['role'] === 'staff_recruitment') {
                header("Location: recruitment_management.php");
            }
            else if ($user['role'] === 'staff_candidate') {
                header("Location: candidate_management.php");
            }
            else if ($user['role'] === 'candidate'){
                header("Location: candidate.php");
            }
            else {
                header("Location: recruiter.php");
            }
            exit();
        } else {
            $error = "Email hoặc mật khẩu không chính xác";
        }
    } catch (PDOException $e) {
        $error = "Login failed: " . $e->getMessage();
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php
// Kiểm tra tham số success từ URL
$success = isset($_GET['success']) && $_GET['success'] == 1;
?>

<div class="container-fluid vh-100 p-0">
    <div class="row vh-100">
        <!-- Cột bên trái: Form đăng nhập -->
        <div class="col-md-6 d-flex justify-content-center align-items-center bg-light">
            <div class="w-75">
                <h3 class="text-success">ĐĂNG NHẬP</h3>

                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                <?php if ($success) echo "<div id='successAlert' class='alert alert-success'>Đăng ký thành công! Vui lòng đăng nhập.</div>"; ?>

                <form method="POST" id="loginForm" class="mt-4">
                    <div class="form-group mb-3">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="Nhập email" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 mb-3">Đăng nhập</button>

                    <p class="text-center mt-3">Bạn chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
                </form>
            </div>
        </div>

        <!-- Cột bên phải: Hình ảnh -->
        <div class="col-md-6 d-none d-md-block vh-100" style="background: linear-gradient(0deg,rgb(46, 108, 93),rgb(37, 61, 78));">
            <div class="h-100 d-flex flex-column justify-content-center text-white p-5">
                <h1>TalentHub</h1>
                <h3>Kết nối nhân tài, kiến tạo tương lai</h3>
            </div>
        </div>
    </div>
</div>

<!-- Thêm Font Awesome và Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
    .btn-success {
        background-color: #28a745;
        border: none;
    }
    .w-30 {
        width: 30%;
    }
    .bg-dark {
        background: linear-gradient(90deg, #1a3c34, #2a5c52);
    }
    a {
        text-decoration: none;
    }
</style>

<?php include 'includes/footer.php'; ?>