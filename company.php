<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT name, avatar, company_id FROM users WHERE user_id = ? AND role = 'recruiter'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header("Location: login.php");
        exit();
    }

    // Fetch company data if exists
    $company = null;
    $company_id = $user['company_id'] ?? null; // Sử dụng null nếu không tồn tại company_id
    if ($company_id) {
        $stmt_company = $pdo->prepare("SELECT * FROM company WHERE company_id = ?");
        $stmt_company->execute([$company_id]);
        $company = $stmt_company->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Handle form submission
$edit_mode = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_company']) && !$company_id) { // Sử dụng $company_id thay vì $user['company_id']
        // Create new company
        try {
            $stmt_insert = $pdo->prepare("INSERT INTO company (company_name) VALUES ('Công ty của bạn')");
            $stmt_insert->execute();
            $new_company_id = $pdo->lastInsertId();

            // Update user's company_id
            $stmt_update_user = $pdo->prepare("UPDATE users SET company_id = ? WHERE user_id = ?");
            $stmt_update_user->execute([$new_company_id, $user_id]);

            $stmt_company = $pdo->prepare("SELECT * FROM company WHERE company_id = ?");
            $stmt_company->execute([$new_company_id]);
            $company = $stmt_company->fetch(PDO::FETCH_ASSOC);
            $company_id = $new_company_id; // Cập nhật lại company_id
            $message = "Tạo công ty thành công! Vui lòng cập nhật thông tin công ty.";
        } catch (PDOException $e) {
            $message = "Lỗi khi tạo công ty: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit']) && $company_id) { // Sử dụng $company_id
        $edit_mode = true;
    } elseif (isset($_POST['save']) && $company_id) { // Sử dụng $company_id
        // Update company information
        $company_name = $_POST['company_name'] ?? $company['company_name'];
        $company_size = $_POST['company_size'] ?? $company['company_size'];
        $industry = $_POST['industry'] ?? $company['industry'];
        $description = $_POST['description'] ?? $company['description'];
        $logo = $company['logo'] ?? null;
        $house_number = $_POST['house_number'] ?? $company['house_number'];
        $street = $_POST['street'] ?? $company['street'];
        $ward = $_POST['ward'] ?? $company['ward'];
        $district = $_POST['district'] ?? $company['district'];
        $province = $_POST['province'] ?? $company['province'];
        $village = $_POST['village'] ?? $company['village'];

        // Handle logo upload and update avatar
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/company_logos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $logo_path = $upload_dir . uniqid() . '_' . basename($_FILES['logo']['name']);
            move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
            $logo = $logo_path;

            // Update user's avatar
            $stmt_update_avatar = $pdo->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
            $stmt_update_avatar->execute([$logo_path, $user_id]);
        }

        try {
            $stmt_update = $pdo->prepare("UPDATE company SET company_name = ?, company_size = ?, industry = ?, description = ?, logo = ?, house_number = ?, street = ?, ward = ?, district = ?, province = ?, village = ? WHERE company_id = ?");
            $stmt_update->execute([$company_name, $company_size, $industry, $description, $logo, $house_number, $street, $ward, $district, $province, $village, $company_id]);
            $message = "Cập nhật thông tin công ty thành công!";
            $company = array_merge($company ?? [], [
                'company_name' => $company_name,
                'company_size' => $company_size,
                'industry' => $industry,
                'description' => $description,
                'logo' => $logo,
                'house_number' => $house_number,
                'street' => $street,
                'ward' => $ward,
                'district' => $district,
                'province' => $province,
                'village' => $village
            ]);
        } catch (PDOException $e) {
            $message = "Lỗi khi lưu thông tin công ty: " . $e->getMessage();
        }
        $edit_mode = false;
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/rec_navBar.php'; ?>

<!-- Company Section -->
<section class="container my-5">
    <div class="company-card shadow">
        <?php if ($message): ?>
            <div class="alert alert-success mb-4"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!$company_id): ?>
            <!-- Prompt to create company -->
            <h2 class="mb-4">Tạo công ty của bạn</h2>
            <form method="POST">
                <p>Nhấn nút bên dưới để tạo công ty của bạn và bắt đầu quản lý tuyển dụng.</p>
                <button type="submit" name="create_company" class="btn btn-primary">Tạo công ty</button>
            </form>
        <?php else: ?>
            <!-- Company Information Form -->
            <form method="POST" enctype="multipart/form-data" id="companyForm">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <div class="introduce">
                        <p class="profile-name"><?php echo htmlspecialchars($company['company_name']); ?></p>
                        <div class="">
                            <textarea name="description" class="form-control" rows="4" cols="10" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <img src="<?php echo htmlspecialchars($company['logo'] ?? 'https://via.placeholder.com/200'); ?>" alt="Company Logo" class="company-logo">
                </div>

                <!-- Company Info -->
                <div class="company-section bg-light p-3 mb-4">
                    <h5 class="mb-3">THÔNG TIN CÔNG TY</h5>
                    <div class="form-group mb-3">
                        <label>Tên công ty:</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?> required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Quy mô công ty:</label>
                        <select name="company_size" class="form-control" <?php echo $edit_mode ? '' : 'disabled'; ?>>
                            <option value="">Chọn quy mô</option>
                            <option value="1-10" <?php echo ($company['company_size'] === '1-10') ? 'selected' : ''; ?>>1-10 nhân viên</option>
                            <option value="11-50" <?php echo ($company['company_size'] === '11-50') ? 'selected' : ''; ?>>11-50 nhân viên</option>
                            <option value="51-200" <?php echo ($company['company_size'] === '51-200') ? 'selected' : ''; ?>>51-200 nhân viên</option>
                            <option value="201-500" <?php echo ($company['company_size'] === '201-500') ? 'selected' : ''; ?>>201-500 nhân viên</option>
                            <option value="501+" <?php echo ($company['company_size'] === '501+') ? 'selected' : ''; ?>>501+ nhân viên</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label>Lĩnh vực:</label>
                        <input type="text" name="industry" class="form-control" value="<?php echo htmlspecialchars($company['industry'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                    </div>
                    <?php if ($edit_mode): ?>
                        <div class="form-group mb-3">
                            <label>Logo công ty:</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Address Info -->
                <div class="company-section bg-light p-3 mb-4">
                    <h5 class="mb-3">ĐỊA CHỈ</h5>
                    <div class="form-group mb-3">
                        <label>Số nhà:</label>
                        <input type="text" name="house_number" class="form-control" value="<?php echo htmlspecialchars($company['house_number'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                    </div>
                    <div class="form-group mb-3">
                        <label>Đường:</label>
                        <input type="text" name="street" class="form-control" value="<?php echo htmlspecialchars($company['street'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                    </div>
                    <div class="form-group mb-3">
                        <label>Phường/Xã:</label>
                        <input type="text" name="ward" class="form-control" value="<?php echo htmlspecialchars($company['ward'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                    </div>
                    <div class="form-group mb-3">
                        <label>Quận/Huyện:</label>
                        <input type="text" name="district" class="form-control" value="<?php echo htmlspecialchars($company['district'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                    </div>
                    <div class="form-group mb-3">
                        <label>Tỉnh/Thành phố:</label>
                        <input type="text" name="province" class="form-control" value="<?php echo htmlspecialchars($company['province'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                    </div>
                    <div class="form-group mb-3">
                        <label>Thôn/Làng:</label>
                        <input type="text" name="village" class="form-control" value="<?php echo htmlspecialchars($company['village'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="text-end">
                    <?php if (!$edit_mode): ?>
                        <button type="submit" name="edit" class="btn btn-primary">Chỉnh sửa</button>
                    <?php else: ?>
                        <button type="submit" name="save" class="btn btn-success">Lưu</button>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<style>
    .company-card {
        background-color: #fff;
        border-radius: 10px;
        padding: 30px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .profile-name {
        font-size: 40px;
        font-weight: 800;
    }

    .introduce {
        width: 80%;
    }

    .company-logo {
        width: 200px;
        height: 200px;
        border-radius: 10px;
        object-fit: cover;
        border: 2px solid #e0e0e0;
    }

    .company-section {
        background-color: #fff5f5;
        border-radius: 5px;
    }

    .form-group label {
        font-weight: 600;
        margin-bottom: 5px;
    }

    .form-control[readonly] {
        background-color: #fff;
        opacity: 1;
    }

    .btn-primary {
        background-color: #00b14f;
        border-color: #00b14f;
    }

    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
        padding: 10px;
        border-radius: 5px;
    }
</style>

<?php include 'includes/footer.php'; ?>