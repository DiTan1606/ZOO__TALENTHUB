<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    die("Access denied");
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    die("Invalid user ID");
}

try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.avatar, p.*
        FROM users u
        LEFT JOIN profile p ON u.profile_id = p.profile_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found");
    }

    // Prepare data with fallback values
    $avatar = htmlspecialchars($user['avatar'] ?? '/recruitment_website/uploads/avatars/default.jpg');
    $name = htmlspecialchars($user['name']);
    $phone = htmlspecialchars($user['SDT'] ?? 'Chưa cập nhật');
    $email = htmlspecialchars($user['mail'] ?? 'Chưa cập nhật');
    $link = htmlspecialchars($user['link'] ?? 'Chưa cập nhật');
    $birthday = $user['birthday'] ? date('d/m/Y', strtotime($user['birthday'])) : 'Chưa cập nhật';
    $address = htmlspecialchars($user['address'] ?? 'Chưa cập nhật');
    $introduce = nl2br(htmlspecialchars($user['introduce'] ?? 'Chưa cập nhật'));
    $careers = nl2br(htmlspecialchars($user['careers'] ?? 'Chưa cập nhật'));
    $experience = nl2br(htmlspecialchars($user['experience'] ?? 'Chưa cập nhật'));
    $study = nl2br(htmlspecialchars($user['study'] ?? 'Chưa cập nhật'));
    $skills = htmlspecialchars($user['skills'] ?? 'Chưa cập nhật');
    $certificates = nl2br(htmlspecialchars($user['certificates'] ?? 'Chưa cập nhật'));
    $favorite = nl2br(htmlspecialchars($user['favorite'] ?? 'Chưa cập nhật'));
    $achievements = nl2br(htmlspecialchars($user['Achievements_and_Awards'] ?? 'Chưa cập nhật'));

    // Generate HTML
    echo '<div class="profile-preview">';

    echo '<div class="avatar-section mb-3">';
    echo '<img src="' . $avatar . '" alt="Avatar" class="profile-avatar">';
    echo '</div>';

    echo '<div class="info-section">';
    echo '<h5>' . $name . '</h5>';

    echo '<div>';
    echo '<div><strong>Giới thiệu:</strong></div>';
    echo '<p>' . $introduce . '</p>';
    echo '</div>';


    echo '<div>';
    echo '<div><strong>SĐT:</strong></div>';
    echo '<p>' . $phone . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Email:</strong></div>';
    echo '<p>' . $email . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Liên kết:</strong></div>';
    echo '<p>' . ($link !== 'Chưa cập nhật' ? '<a href="' . $link . '" target="_blank">' . $link . '</a>' : $link) . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Ngày sinh:</strong></div>';
    echo '<p>' . $birthday . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Địa chỉ:</strong></div>';
    echo '<p>' . $address . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Mục tiêu nghề nghiệp:</strong></div>';
    echo '<p>' . $careers . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Kinh nghiệm:</strong></div>';
    echo '<p>' . $experience . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Học vấn:</strong></div>';
    echo '<p>' . $study . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Kỹ năng:</strong></div>';
    echo '<p>' . $skills . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Chứng chỉ:</strong></div>';
    echo '<p>' . $certificates . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Sở thích:</strong></div>';
    echo '<p>' . $favorite . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<div><strong>Thành tựu & Giải thưởng:</strong></div>';
    echo '<p>' . $achievements . '</p>';
    echo '</div>';

    echo '</div>';
} catch (PDOException $e) {
    die("Error fetching profile: " . $e->getMessage());
}
?>