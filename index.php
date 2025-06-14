<?php
session_start();
?>

<?php include 'includes/header.php'; ?>

<div class="hello-page text-center">
    
</div>

<div class="container-fluid vh-100 p-0">
    <div class="row vh-100">
        <div class="h-100 d-flex flex-column justify-content-center align-items-center text-white p-5 text-center" style="background: linear-gradient(0deg,rgb(46, 108, 93),rgb(37, 61, 78));">
            <h1>Chào mừng bạn đến với TalentHub!</h1>
            <p class="lead">Kết nối nhân tài, kiến tạo tương lai</p>
            <div class="mt-4">
                <a href="login.php" class="btn btn-primary btn-lg mr-3">Đăng nhập</a>
                <a href="register.php" class="btn btn-outline-primary btn-lg">Đăng ký</a>
            </div>
        </div>
    </div>
</div>

<style>
    .btn-primary {
        background-color:rgb(49, 157, 130);
        border-color: rgb(49, 157, 130);
        border-radius: 5px;
    }
    .btn-outline-primary {
        border-color:white;
        color:white;
        border-radius: 5px;
    }
    .btn-primary:hover {
        border-color:rgb(36, 114, 95);
        background-color:rgb(36, 114, 95);
    }
    .btn-outline-primary:hover {
        border-color:rgb(36, 114, 95);
        background-color:rgb(36, 114, 95);
        color: white;
    }
    .vh-100 {
        min-height: 100vh;
    }
</style>

<?php include 'includes/footer.php'; ?>