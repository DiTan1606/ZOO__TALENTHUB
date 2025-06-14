<?php
session_start();
require 'includes/db_connect.php';

// Xử lý form khi người dùng submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $default_avatar = '/recruitment_website/uploads/avatars/default.jpg';

    // Kiểm tra mật khẩu có khớp không
    if ($password !== $confirm_password) {
        $error = "Mật khẩu và xác nhận mật khẩu không khớp.";
    } else {
        // Kiểm tra email đã tồn tại
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $email_count = $stmt->fetchColumn();

        if ($email_count > 0) {
            $error = "Email này đã tồn tại. Vui lòng sử dụng email khác.";
        } else {
            // Mã hóa mật khẩu (sử dụng password_hash để bảo mật)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Thêm người dùng mới vào cơ sở dữ liệu
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, avatar) VALUES (:name, :email, :password, :role, :avatar)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':role', $role);
            $stmt->bindParam(':avatar', var: $default_avatar);

            if ($stmt->execute()) {
                // Chuyển hướng đến trang đăng nhập sau khi đăng ký thành công
                header("Location: login.php?success=1");
                exit();
            } else {
                $error = "Đăng ký thất bại. Vui lòng thử lại.";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container-fluid vh-100 p-0">
    <div class="row vh-100">
        <!-- Cột bên trái: Form đăng ký -->
        <div class="col-md-6 d-flex justify-content-center align-items-center">
            <div class="w-75">
                <h3 class="text-success">ĐĂNG KÝ</h3>

                <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>

                <form method="POST">
                    <div class="form-group mb-3">
                        <label class="form-label">Họ và tên</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="Nhập họ tên" required>
                        </div>
                    </div>

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
                            <input type="password" name="password" id="password" class="form-control" placeholder="Nhập mật khẩu" required>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Xác nhận mật khẩu</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="Nhập lại mật khẩu" required>
                        </div>
                        <div id="passwordMismatch" class="text-danger mt-2" style="display: none;">Mật khẩu không khớp. Vui lòng kiểm tra lại.</div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Bạn là:</label>
                        <div class="input-group">
                            <select name="role" class="form-control" required>
                                <option value="candidate">
                                    Ứng viên
                                </option>
                                <option value="recruiter">
                                    Nhà tuyển dụng
                                </option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 mb-3">Đăng ký</button>

                    <p class="text-center mt-3">Bạn đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
                </form>     
            </div>
        </div>

        <!-- Cột bên phải: Hình ảnh -->
        <div class="col-md-6 d-none d-md-block">
            <div class="text-white p-5 h-100 d-flex flex-column justify-content-center" style="background: linear-gradient(0deg,rgb(46, 108, 93),rgb(37, 61, 78));">
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

<script>
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordMismatch = document.getElementById('passwordMismatch');
    const submitBtn = document.getElementById('submitBtn');

    function validatePasswords() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (password && confirmPassword) {
            if (password !== confirmPassword) {
                passwordMismatch.style.display = 'block';
                submitBtn.disabled = true;
            } else {
                passwordMismatch.style.display = 'none';
                submitBtn.disabled = false;
            }
        } else {
            passwordMismatch.style.display = 'none';
            submitBtn.disabled = true;
        }
    }

    passwordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);
</script>

<?php include 'includes/footer.php'; ?>