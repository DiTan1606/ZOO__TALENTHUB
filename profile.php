<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT name, avatar, profile_id FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        die("Error: User not found.");
    }
    $name = $user['name'];
    $avatar = $user['avatar'] ?? '/recruitment_website/uploads/avatars/default.jpg';
    $profile_id = $user['profile_id'];

    // Fetch profile data
    $stmt_profile = $pdo->prepare("SELECT * FROM profile WHERE profile_id = ?");
    $stmt_profile->execute([$profile_id]);
    $profile = $stmt_profile->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        // Insert default profile if not exists
        $stmt_insert = $pdo->prepare("INSERT INTO profile (profile_id) VALUES (?)");
        $stmt_insert->execute([$profile_id]);
        $profile = ['profile_id' => $profile_id, 'SDT' => null, 'mail' => null, 'link' => null, 'birthday' => null, 'address' => null, 'introduce' => null, 'careers' => null, 'experience' => null, 'study' => null, 'skills' => null, 'certificates' => null, 'favorite' => null, 'Achievements_and_Awards' => null];
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

// Handle form submission
$edit_mode = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit'])) {
        $edit_mode = true;
    } elseif (isset($_POST['save'])) {
        $SDT = $_POST['SDT'] ?? $profile['SDT'];
        $mail = $_POST['mail'] ?? $profile['mail'];
        $link = $_POST['link'] ?? $profile['link'];
        $birthday = $_POST['birthday'] ?? $profile['birthday'];
        $address = $_POST['address'] ?? $profile['address'];
        $introduce = $_POST['introduce'] ?? $profile['introduce'];
        $careers = $_POST['careers'] ?? $profile['careers'];
        $experience = $_POST['experience'] ?? $profile['experience'];
        $study = $_POST['study'] ?? $profile['study'];
        $skills = $_POST['skills'] ?? $profile['skills'];
        $certificates = $_POST['certificates'] ?? $profile['certificates'];
        $favorite = $_POST['favorite'] ?? $profile['favorite'];
        $Achievements_and_Awards = $_POST['Achievements_and_Awards'] ?? $profile['Achievements_and_Awards'];

        try {
            // Handle avatar upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
                $max_size = 2 * 1024 * 1024; // 2MB
                $file_type = $_FILES['avatar']['type'];
                $file_size = $_FILES['avatar']['size'];

                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("Chỉ cho phép tải lên file JPG, JPEG hoặc PNG.");
                }
                if ($file_size > $max_size) {
                    throw new Exception("Kích thước file không được vượt quá 2MB.");
                }

                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/recruitment_website/uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $new_filename = "avatar_{$user_id}_" . time() . "." . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                $db_path = "/recruitment_website/uploads/avatars/{$new_filename}";

                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    // Update avatar in users table
                    $stmt_avatar = $pdo->prepare("UPDATE users SET avatar = ? WHERE user_id = ?");
                    $stmt_avatar->execute([$db_path, $user_id]);
                    $avatar = $db_path; // Update avatar variable for display
                } else {
                    throw new Exception("Lỗi khi tải lên file ảnh.");
                }
            }

            // Update profile data
            $stmt_update = $pdo->prepare("UPDATE profile SET SDT = ?, mail = ?, link = ?, birthday = ?, address = ?, introduce = ?, careers = ?, experience = ?, study = ?, skills = ?, certificates = ?, favorite = ?, Achievements_and_Awards = ?, updated_at = CURRENT_TIMESTAMP WHERE profile_id = ?");
            $stmt_update->execute([$SDT, $mail, $link, $birthday, $address, $introduce, $careers, $experience, $study, $skills, $certificates, $favorite, $Achievements_and_Awards, $profile_id]);
            $message = "Cập nhật hồ sơ thành công!";
            $profile = array_merge($profile, ['SDT' => $SDT, 'mail' => $mail, 'link' => $link, 'birthday' => $birthday, 'address' => $address, 'introduce' => $introduce, 'careers' => $careers, 'experience' => $experience, 'study' => $study, 'skills' => $skills, 'certificates' => $certificates, 'favorite' => $favorite, 'Achievements_and_Awards' => $Achievements_and_Awards]);
        } catch (Exception $e) {
            $message = "Lỗi khi lưu hồ sơ: " . $e->getMessage();
        }
        $edit_mode = false;
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/can_navBar.php'; ?>

<!-- Profile Section -->
<section class="container my-5">
    <div class="profile-card shadow">
        <?php if ($message): ?>
            <div class="alert alert-success mb-4"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
    
        <form method="POST" id="profileForm" enctype="multipart/form-data">
            <div class="d-flex align-items-start justify-content-between mb-4">
                <div class="introduce">
                    <p class="profile-name"><?php echo htmlspecialchars($name); ?></p>
                    <div class="">
                        <textarea name="introduce" class="form-control" rows="4" cols="10" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['introduce'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="avatar-section text-center">
                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="Avatar" class="profile-avatar mb-2">
                    <?php if ($edit_mode): ?>
                        <div class="form-group">
                            <label for="avatar" class="form-label">Cập nhật ảnh đại diện:</label>
                            <input type="file" name="avatar" id="avatar" class="form-control" accept="image/jpeg,image/jpg,image/png">
                            <small class="text-muted">Chỉ chấp nhận JPG, JPEG, PNG. Tối đa 2MB.</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Personal Info -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">THÔNG TIN CÁ NHÂN</h5>
                <div class="form-group mb-3">
                    <label>Số điện thoại (SDT):</label>
                    <input type="text" name="SDT" class="form-control" value="<?php echo htmlspecialchars($profile['SDT'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                </div>
                <div class="form-group mb-3">
                    <label>Email:</label>
                    <input type="email" name="mail" class="form-control" value="<?php echo htmlspecialchars($profile['mail'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                </div>
                <div class="form-group mb-3">
                    <label>Link trang cá nhân:</label>
                    <input type="url" name="link" class="form-control" value="<?php echo htmlspecialchars($profile['link'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                </div>
                <div class="form-group mb-3">
                    <label>Ngày sinh:</label>
                    <input type="date" name="birthday" class="form-control" value="<?php echo htmlspecialchars($profile['birthday'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                </div>
                <div class="form-group mb-3">
                    <label>Địa chỉ:</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>" <?php echo $edit_mode ? '' : 'readonly'; ?>>
                </div>
            </div>

            <!-- Careers -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">CÁC NGHỀ NGHIỆP</h5>
                <textarea name="careers" class="form-control" rows="4" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['careers'] ?? ''); ?></textarea>
            </div>

            <!-- Experience -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">KINH NGHIỆM LÀM VIỆC</h5>
                <textarea name="experience" class="form-control" rows="4" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['experience'] ?? ''); ?></textarea>
            </div>

            <!-- Study -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">HỌC VẤN</h5>
                <textarea name="study" class="form-control" rows="4" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['study'] ?? ''); ?></textarea>
            </div>

            <!-- Skills -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">KỸ NĂNG</h5>
                <textarea name="skills" class="form-control" rows="4" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['skills'] ?? ''); ?></textarea>
            </div>

            <!-- Certificates -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">CHỨNG CHỈ</h5>
                <textarea name="certificates" class="form-control" rows="4" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['certificates'] ?? ''); ?></textarea>
            </div>

            <!-- Favorite -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">SỞ THÍCH</h5>
                <textarea name="favorite" class="form-control" rows="4" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['favorite'] ?? ''); ?></textarea>
            </div>

            <!-- Achievements and Awards -->
            <div class="profile-section bg-light p-3 mb-4">
                <h5 class="mb-3">THÀNH TÍCH VÀ GIẢI THƯỞNG</h5>
                <textarea name="Achievements_and_Awards" class="form-control" rows="4" <?php echo $edit_mode ? '' : 'readonly'; ?>><?php echo htmlspecialchars($profile['Achievements_and_Awards'] ?? ''); ?></textarea>
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
    </div>
</section>

<style>
    .profile-card {
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

    .profile-avatar {
        width: 200px;
        height: 200px;
        border-radius: 10px;
        object-fit: cover;
        border: 2px solid #e0e0e0;
    }

    .avatar-section {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .avatar-section .form-group {
        margin-top: 10px;
    }

    .profile-section {
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