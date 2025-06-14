<?php
// Get user information
$user_id = $_SESSION['user_id'];
if (!$user_id) {
    die("Error: user_id not set in session.");
}

try {
    $stmt = $pdo->prepare("SELECT name, avatar FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("Error: User not found.");
    }
    $name = $user['name'];
    $avatar = $user['avatar'] ?? '/recruitment_website/uploads/avatars/default.jpg';
} catch (PDOException $e) {
    die("Error fetching user: " . $e->getMessage());
}

// Get current page file name
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Navbar -->
<div class="navbar-container">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="navbar-info d-flex align-items-center">
            <span class="navbar-logo" id="logoTrigger">TalentHub</span>
            <nav class="navbar navbar-expand">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">Hồ sơ</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'candidate.php' ? 'active' : ''; ?>" href="candidate.php">Tìm việc</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'applications.php' ? 'active' : ''; ?>" href="applications.php">Đơn ứng tuyển</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'interviews.php' ? 'active' : ''; ?>" href="interviews.php">Lịch phỏng vấn</a></li>
                    <li class="nav-item"><a class="nav-link <?php echo $current_page === 'tests.php' ? 'active' : ''; ?>" href="tests.php">Bài kiểm tra</a></li>
                </ul>
            </nav>
            <div class="dropdown-menu logo-dropdown" id="logoDropdown">
                <a class="nav-link <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>" href="profile.php">Hồ sơ</a>
                <a class="nav-link <?php echo $current_page === 'candidate.php' ? 'active' : ''; ?>" href="candidate.php">Tìm việc</a>
                <a class="nav-link <?php echo $current_page === 'applications.php' ? 'active' : ''; ?>" href="applications.php">Đơn ứng tuyển</a>
                <a class="nav-link <?php echo $current_page === 'interviews.php' ? 'active' : ''; ?>" href="interviews.php">Lịch phỏng vấn</a>
                <a class="nav-link <?php echo $current_page === 'tests.php' ? 'active' : ''; ?>" href="tests.php">Bài kiểm tra</a>
                <a class="nav-link <?php echo $current_page === 'logout.php' ? 'active' : ''; ?>" href="logout.php">Đăng xuất</a>
            </div>
        </div>
        <div class="user-info d-flex align-items-center">
            <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="avatar">
            <div class="user-info-content">
                <span class="user-name me-2"><?php echo ("Ứng viên "). htmlspecialchars($name); ?></span>
                <a class="logout-link" href="logout.php">Đăng xuất</a>
            </div>
        </div>
    </div>
</div>

<style>
    /* Navbar styles */
    .navbar-container {
        background-color: #fff; 
        padding: 15px 0; 
        border-bottom: 1px solid #e0e0e0;
        position: fixed;
        top: 0;
        width: 100%;
        z-index: 1000;
    }

    body {
        padding-top: 80px;
    }

    .navbar-logo {
        font-size: 2rem;
        font-weight: 800;
        background: linear-gradient(0deg, rgb(46, 108, 93), rgb(37, 61, 78));
        -webkit-background-clip: text; 
        background-clip: text;
        color: transparent;
        margin-right: 40px;
        cursor: pointer; 
    }

    .navbar-nav .nav-link {
        color: #000000; 
        font-size: 1rem;
        font-weight: 600; 
        margin: 0 10px;
    }

    .navbar-nav .nav-link:hover,
    .navbar-nav .nav-link.active {
        color: #00b14f;
    }

    .user-info .avatar {
        width: 45px; 
        height: 45px;
        border-radius: 50%;
        cursor: pointer;
        margin-right: 10px;
    }

    .user-info .user-name {
        color: #000000; 
        font-size: 1rem;
        font-weight: 600; 
        display: block;
    }

    .user-info .logout-link {
        color: rgb(208, 0, 0);
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        display: block;
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        background-color: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 10px 0;
        z-index: 1001;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .logo-dropdown {
        top: 80px; 
        left: 0;
        right: 0;
        width: 100%;
        border-radius: 0;
    }

    .logo-dropdown .nav-link {
        display: block;
        padding: 10px 20px;
        color: #000000;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
    }

    .logo-dropdown .nav-link:hover,
    .logo-dropdown .nav-link.active {
        color: #00b14f;
    }

    .logo-dropdown .nav-link[href="logout.php"]:hover,
    .logo-dropdown .nav-link[href="logout.php"].active {
        color: rgb(193, 0, 0);
    }

    .logo-dropdown.show {
        display: block;
    }

    @media (max-width: 768px) {
        .navbar-nav {
            display: none;
        }
        .user-info .user-info-content {
            display: none; 
        }
        .navbar-logo {
            margin-right: 0; 
        }
    }

    @media (min-width: 769px) {
        .logo-dropdown {
            display: none !important;
        }
    }
</style>

<script>
    function toggleLogoDropdown() {
        if (window.innerWidth <= 768) {
            const dropdown = document.getElementById('logoDropdown');
            dropdown.classList.toggle('show');
        }
    }

    document.getElementById('logoTrigger').addEventListener('click', function(event) {
        event.preventDefault();
        toggleLogoDropdown();
    });

    document.addEventListener('click', function(event) {
        const logoDropdown = document.getElementById('logoDropdown');
        const logo = document.getElementById('logoTrigger');

        if (window.innerWidth <= 768 && !logo.contains(event.target) && !logoDropdown.contains(event.target)) {
            logoDropdown.classList.remove('show');
        }
    });
</script>