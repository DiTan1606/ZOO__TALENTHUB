<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: login.php");
    exit();
}

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($job_id <= 0) {
    header("Location: candidate.php");
    exit();
}

try {
    // Fetch job details
    $stmt = $pdo->prepare("
        SELECT 
            j.job_id, j.job_title, j.salary, j.work_location, j.experience_required, 
            j.work_type, j.deadline, j.description, j.candidate_requirements, j.benefits, 
            j.working_hours, c.company_name, c.company_size, c.industry, c.logo, c.province,
            CONCAT(
                COALESCE(c.house_number, ''), ' ',
                COALESCE(c.street, ''), ', ',
                COALESCE(c.ward, ''), ', ',
                COALESCE(c.district, ''), ', ',
                COALESCE(c.province, ''), ' ',
                COALESCE(c.village, '')
            ) AS full_address
        FROM jobs j
        JOIN company c ON j.company_id = c.company_id
        WHERE j.job_id = ?
    ");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        header("Location: candidate.php");
        exit();
    }

    // Check if user has already applied
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE user_id = ? AND job_id = ?");
    $stmt_check->execute([$_SESSION['user_id'], $job_id]);
    $has_applied = $stmt_check->fetchColumn() > 0;
} catch (PDOException $e) {
    die("Error fetching job details: " . $e->getMessage());
}

// Handle application submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_submit']) && !$has_applied) {
    $full_name = $_POST['full_name'] ?? '';
    $SDT = $_POST['SDT'] ?? '';
    $email = $_POST['email'] ?? '';
    $cv_file = $_FILES['cv_file'] ?? null;

    $cv_path = null;
    if ($cv_file && $cv_file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/cvs/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $cv_path = $upload_dir . uniqid() . '_' . basename($cv_file['name']);
        move_uploaded_file($cv_file['tmp_name'], $cv_path);
    }

    try {
        $stmt_apply = $pdo->prepare("INSERT INTO applications (user_id, job_id, full_name, SDT, email, cv_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_apply->execute([$_SESSION['user_id'], $job_id, $full_name, $SDT, $email, $cv_path]);
        $message = "Nộp đơn ứng tuyển thành công!";
        $has_applied = true; // Update status after successful application
    } catch (PDOException $e) {
        $message = "Lỗi khi ứng tuyển: " . $e->getMessage();
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/can_navBar.php'; ?>

<style>
    /* Job Detail Card styles */
    .job-detail-card {
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .company-logo-container {
        width: 120px;
        height: 120px;
        margin-right: 20px;
        border: #ddd solid 1px;
        border-radius: 10px;
    }

    .company-logo {
        width: 100%;
        height: 100%;
        border-radius: 10px;
        object-fit: contain;
    }

    .job-title {
        font-size: 20px;
        font-weight: 600;
    }

    .job-icon {
        background: linear-gradient(0deg,rgb(20, 146, 125),rgb(7, 198, 93));
        width: 50px;
        height: 50px;
        border-radius: 50%;
        margin-right: 10px;
    }

    .job-icon i {
        color: #fff;
    }

    .job-dl-btn {
        background-color: #eee;
        padding: 10px 20px;
        border-radius: 10px;
    }

    .btn-primary {
        background-color: #00b14f;
        border-color: #00b14f;
        width: 100%;
    }

    .btn-primary:hover {
        border-color: rgb(36, 114, 95);
        background-color: rgb(36, 114, 95);
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(0,0,0,0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background-color: #fff;
        padding: 20px;
        border-radius: 10px;
        width: 90%;
        max-width: 500px;
        position: relative;
    }

    .close {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        cursor: pointer;
    }

    @media (max-width: 576px) {
        .info-item {
            flex: 0 0 100%;
            max-width: 100%;
        }
        .job-info-list {
            justify-content: start;
        }
        .company-info-card {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .company-logo-container {
            margin-bottom: 15px;
            margin-right: 10px;
        }
        .company-logo-container {
            width: 120px;
            height: 120px;
            margin-right: 10px;
        }
    }
</style>

<!-- Job Detail Section -->
<section class="container my-5">
    <div class="">
        <?php if (isset($message) && !empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'thành công') !== false ? 'alert-success' : 'alert-danger'; ?> mb-3">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Company Info Card -->
    <div class="job-detail-card shadow mb-4 d-flex align-items-center company-info-card">
        <!-- Logo -->
        <div class="company-logo-container">
            <img class="company-logo" src="<?php echo htmlspecialchars($job['logo'] ?? 'https://via.placeholder.com/200'); ?>" alt="Company Logo">
        </div>
        <!-- Company Details -->
        <div class="company-details">
            <p class="job-title mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-users me-2" style="color: #00b14f;"></i>
                <span><strong>Quy mô:</strong> <?php echo htmlspecialchars($job['company_size']); ?> nhân viên</span>
            </div>
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-building me-2" style="color: #00b14f;"></i>
                <span><strong>Lĩnh vực:</strong> <?php echo htmlspecialchars($job['industry']); ?></span>
            </div>
            <div class="d-flex align-items-center mb-2">
                <i class="fas fa-map-marker-alt me-2" style="color: #00b14f;"></i>
                <span><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($job['full_address'] ?? 'Không có thông tin'); ?></span>
            </div>
        </div>       
    </div>

    <!-- Job Info Card -->
    <div class="job-detail-card shadow mb-4">
        <p class="job-title mb-4"><?php echo htmlspecialchars($job['job_title']); ?></p>

        <div class="job-info-list d-flex justify-content-between flex-wrap">
            <div class="job-info-item d-flex align-items-center mb-4">
                <div class="job-icon d-flex align-items-center justify-content-center">
                    <i class="fas fa-money-bill-wave" style="color: #fff;"></i>
                </div>
                <span><strong>Thu nhập: </strong> <?php echo htmlspecialchars($job['salary'] ?? 'Thỏa thuận'); ?></span>
            </div>
            <div class="job-info-item d-flex align-items-center mb-4">
                <div class="job-icon d-flex align-items-center justify-content-center">
                    <i class="fas fa-map-marker-alt" style="color: #fff;"></i>
                </div>
                <span><strong>Địa điểm:</strong> <?php echo htmlspecialchars($job['province'] ?? 'Không xác định'); ?></span>
            </div>
            <div class="job-info-item d-flex align-items-center mb-4">
                <div class="job-icon d-flex align-items-center justify-content-center">
                    <i class="fas fa-briefcase" style="color: #fff;"></i>
                </div>
                <span><strong>Loại công việc:</strong> <?php echo htmlspecialchars($job['work_type']); ?></span>
            </div>
            <div class="job-info-item d-flex align-items-center mb-4">
                <div class="job-icon d-flex align-items-center justify-content-center">
                    <i class="fas fa-clock" style="color: #fff;"></i>
                </div>
                <span><strong>Kinh nghiệm:</strong> <?php echo htmlspecialchars($job['experience_required'] ?? 'Không yêu cầu'); ?></span>
            </div>
        </div>

        <div class="job-dl-btn d-flex align-items-center mb-3">
            <i class="fas fa-calendar-alt me-2" style="color: #00b14f;"></i>
            <span><strong>Hạn nộp:</strong> <?php echo $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : 'Không xác định'; ?></span>
        </div>

        <div class="job-actions d-flex align-items-center justify-content-between">
            <?php if ($has_applied): ?>
                <span class="text-warning mb-3">Bạn đã nộp đơn cho công việc này.</span>
            <?php else: ?>
                <button type="button" class="btn btn-primary me-2" id="applyButton">Ứng tuyển ngay</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Job Detail Card -->
    <div class="job-detail-card shadow mb-4">
        <p class="job-title mb-3">Chi tiết tin tuyển dụng</p>

        <p class="mb-2"><strong>Mô tả công việc</strong></p>
        <p class="mb-4"><?php echo nl2br(htmlspecialchars($job['description'] ?? 'Không có mô tả.')); ?></p>

        <p class="mb-2"><strong>Yêu cầu ứng viên</strong></p>
        <p class="mb-4"><?php echo nl2br(htmlspecialchars($job['candidate_requirements'] ?? 'Không có yêu cầu.')); ?></p>

        <p class="mb-2"><strong>Quyền lợi</strong></p>
        <p class="mb-4"><?php echo nl2br(htmlspecialchars($job['benefits'] ?? 'Không có thông tin.')); ?></p>

        <p class="mb-2"><strong>Thời gian, địa điểm làm việc</strong></p>
        <p class="mb-4"><?php echo nl2br(htmlspecialchars($job['working_hours'] ?? 'Không có thông tin.')); ?> - <?php echo htmlspecialchars($job['work_location'] ?? 'Không xác định'); ?></p>

        <p class="mb-2"><strong>Cách thức ứng tuyển</strong></p>
        <p class="mb-4">Ứng viên nộp hồ sơ trực tuyến bằng cách bấm <strong>Ứng tuyển ngay</strong> tại đây.</p>

        <p class="mb-2"><strong>Hạn nộp hồ sơ:</strong></p>
        <p><?php echo $job['deadline'] ? date('d/m/Y', strtotime($job['deadline'])) : 'Không xác định'; ?></p>
    </div>

    <!-- Application Modal -->
    <div id="applicationModal" class="modal">
        <div class="modal-content">
            <span class="close">×</span>
            <h4 class="mb-3">Ứng tuyển công việc: <?php echo htmlspecialchars($job['job_title']); ?></h4>
            <?php if (isset($message) && !empty($message)): ?>
                <div class="alert <?php echo strpos($message, 'thành công') !== false ? 'alert-success' : 'alert-danger'; ?> mb-3">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group mb-3">
                    <label>Họ và tên:</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user']['name'] ?? ''); ?>" required>
                </div>
                <div class="form-group mb-3">
                    <label>Số điện thoại (SDT):</label>
                    <input type="text" name="SDT" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Email:</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>File CV (PDF, DOC, DOCX):</label>
                    <input type="file" name="cv_file" class="form-control" accept=".pdf,.doc,.docx" required>
                </div>
                <button type="submit" name="apply_submit" class="btn btn-primary">Gửi ứng tuyển</button>
            </form>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
    // Modal handling
    const modal = document.getElementById('applicationModal');
    const applyButton = document.getElementById('applyButton');
    const closeButton = document.getElementsByClassName('close')[0];

    if (applyButton) {
        applyButton.onclick = function() {
            modal.style.display = 'flex';
        }
    }

    if (closeButton) {
        closeButton.onclick = function() {
            modal.style.display = 'none';
        }
    }

    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>