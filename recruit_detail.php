<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: login.php");
    exit();
}

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($job_id <= 0) {
    header("Location: recruiter.php");
    exit();
}

// Get user and company information
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT company_id FROM users WHERE user_id = ? AND role = 'recruiter'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['company_id']) {
        header("Location: company.php");
        exit();
    }

    $company_id = $user['company_id'];

    // Fetch job details
    $stmt_job = $pdo->prepare("
        SELECT j.*, c.company_name, c.logo
        FROM jobs j
        JOIN company c ON j.company_id = c.company_id
        WHERE j.job_id = ? AND j.company_id = ?
    ");
    $stmt_job->execute([$job_id, $company_id]);
    $job = $stmt_job->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        header("Location: recruiter.php");
        exit();
    }

    // Fetch applications for this job (excluding 'chờ xác nhận' and 'không xác nhận')
    $stmt_applications = $pdo->prepare("
        SELECT application_id, job_id, user_id, full_name, SDT, email, cv_path, status, applied_at, rejected_reason
        FROM applications
        WHERE job_id = ? AND status NOT IN ('chờ xác nhận', 'không xác nhận')
    ");
    $stmt_applications->execute([$job_id]);
    $applications = $stmt_applications->fetchAll(PDO::FETCH_ASSOC);

    // Handle update for job details
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_field'])) {
        $field = $_POST['field'];
        $value = $_POST['value'];
        $stmt_update = $pdo->prepare("UPDATE jobs SET $field = ? WHERE job_id = ? AND company_id = ?");
        $stmt_update->execute([$value, $job_id, $company_id]);
        $job[$field] = $value;
        $message = "Cập nhật thành công!";
    }

    // Handle update for application status
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $rejected_reason = isset($_POST['rejected_reason']) ? trim($_POST['rejected_reason']) : NULL;

        if ($application_id > 0 && !empty($status)) {
            // Handle statuses requiring rejected_reason
            if (in_array($status, ['không được duyệt', 'không trúng tuyển']) && empty($rejected_reason)) {
                $message = "Vui lòng nhập lý do khi chọn trạng thái '$status'!";
            } else {
                // Update status and rejected_reason
                $stmt_update_status = $pdo->prepare("
                    UPDATE applications 
                    SET status = ?, rejected_reason = ? 
                    WHERE application_id = ? AND job_id = ?
                ");
                $stmt_update_status->execute([$status, $rejected_reason, $application_id, $job_id]);

                // Handle 'chờ phỏng vấn'
                if ($status === 'chờ phỏng vấn') {
                    $interview_date = isset($_POST['interview_date']) ? $_POST['interview_date'] : '';
                    $location = isset($_POST['location']) ? trim($_POST['location']) : NULL;
                    $meeting_link = isset($_POST['meeting_link']) ? trim($_POST['meeting_link']) : NULL;

                    if (empty($interview_date) || (empty($location) && empty($meeting_link))) {
                        $message = "Vui lòng nhập đầy đủ thông tin phỏng vấn!";
                    } else {
                        $stmt_interview = $pdo->prepare("
                            INSERT INTO interviews (application_id, interview_date, location, meeting_link, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt_interview->execute([$application_id, $interview_date, $location, $meeting_link]);
                        header("Location: interview_management.php");
                        exit();
                    }
                }
                // Handle 'chờ kiểm tra'
                elseif ($status === 'chờ kiểm tra') {
                    $test_content = isset($_FILES['test_content']) ? $_FILES['test_content']['name'] : '';
                    $deadline = isset($_POST['deadline']) ? $_POST['deadline'] : '';

                    if (empty($test_content) || empty($deadline)) {
                        $message = "Vui lòng nhập đầy đủ thông tin kiểm tra!";
                    } else {
                        // Handle file upload
                        $upload_dir = 'uploads/tests/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        $file_tmp = $_FILES['test_content']['tmp_name'];
                        $file_name = basename($_FILES['test_content']['name']);
                        $file_path = $upload_dir . $file_name;

                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $stmt_test = $pdo->prepare("
                                INSERT INTO tests (application_id, test_content, deadline, created_at) 
                                VALUES (?, ?, ?, NOW())
                            ");
                            $stmt_test->execute([$application_id, $file_path, $deadline]);
                            header("Location: test_management.php");
                            exit();
                        } else {
                            $message = "Lỗi khi upload file kiểm tra!";
                        }
                    }
                } else {
                    $message = "Cập nhật trạng thái thành công!";
                }
            }
        } else {
            $message = "Cập nhật trạng thái thất bại. Vui lòng thử lại!";
        }

        // Refresh page to reflect updated status
        header("Location: recruit_detail.php?id=" . $job_id);
        exit();
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/rec_navBar.php'; ?>

<!-- Job Detail Section -->
<section class="container my-5">
    <h2 class="mb-4"><?php echo htmlspecialchars($job['job_title']); ?></h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'thành công') !== false ? 'success' : 'danger'; ?> mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="job-detail-card shadow mb-4 p-3">
        <div class="form-group mb-3">
            <label class="mb-1">Tiêu đề công việc:</label>
            <form method="POST" class="d-flex">
                <input type="text" name="value" class="form-control me-2" style="height: fit-content" value="<?php echo htmlspecialchars($job['job_title']); ?>">
                <input type="hidden" name="field" value="job_title">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Kinh nghiệm yêu cầu:</label>
            <form method="POST" class="d-flex">
                <input type="text" name="value" class="form-control me-2" style="height: fit-content" value="<?php echo htmlspecialchars($job['experience_required'] ?? ''); ?>">
                <input type="hidden" name="field" value="experience_required">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Loại công việc:</label>
            <form method="POST" class="d-flex">
                <select name="value" class="form-control me-2" style="height: fit-content">
                    <option value="full-time" <?php echo ($job['work_type'] === 'full-time') ? 'selected' : ''; ?>>Toàn thời gian</option>
                    <option value="part-time" <?php echo ($job['work_type'] === 'part-time') ? 'selected' : ''; ?>>Bán thời gian</option>
                    <option value="freelance" <?php echo ($job['work_type'] === 'freelance') ? 'selected' : ''; ?>>Freelance</option>
                    <option value="contract" <?php echo ($job['work_type'] === 'contract') ? 'selected' : ''; ?>>Hợp đồng ngắn hạn</option>
                </select>
                <input type="hidden" name="field" value="work_type">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Hạn nộp hồ sơ:</label>
            <form method="POST" class="d-flex">
                <input type="date" name="value" class="form-control me-2" style="height: fit-content" value="<?php echo htmlspecialchars($job['deadline'] ?? ''); ?>">
                <input type="hidden" name="field" value="deadline">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Mô tả công việc:</label>
            <form method="POST" class="d-flex">
                <textarea name="value" class="form-control me-2" style="height: fit-content"><?php echo htmlspecialchars($job['description'] ?? ''); ?></textarea>
                <input type="hidden" name="field" value="description">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Yêu cầu ứng viên:</label>
            <form method="POST" class="d-flex">
                <textarea name="value" class="form-control me-2" style="height: fit-content"><?php echo htmlspecialchars($job['candidate_requirements'] ?? ''); ?></textarea>
                <input type="hidden" name="field" value="candidate_requirements">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Quyền lợi:</label>
            <form method="POST" class="d-flex">
                <textarea name="value" class="form-control me-2" style="height: fit-content"><?php echo htmlspecialchars($job['benefits'] ?? ''); ?></textarea>
                <input type="hidden" name="field" value="benefits">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Thời gian làm việc:</label>
            <form method="POST" class="d-flex">
                <textarea name="value" class="form-control me-2" style="height: fit-content"><?php echo htmlspecialchars($job['working_hours'] ?? ''); ?></textarea>
                <input type="hidden" name="field" value="working_hours">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Địa điểm làm việc:</label>
            <form method="POST" class="d-flex">
                <input type="text" name="value" class="form-control me-2" style="height: fit-content" value="<?php echo htmlspecialchars($job['work_location'] ?? ''); ?>">
                <input type="hidden" name="field" value="work_location">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
        <div class="form-group mb-3">
            <label class="mb-1">Lương:</label>
            <form method="POST" class="d-flex">
                <input type="text" name="value" class="form-control me-2" style="height: fit-content" value="<?php echo htmlspecialchars($job['salary'] ?? ''); ?>">
                <input type="hidden" name="field" value="salary">
                <button type="submit" name="update_field" class="btn btn-sm btn-primary">Lưu</button>
            </form>
        </div>
    </div>

    <!-- Applications Section -->
    <h3 class="mb-4">Danh sách ứng viên</h3>
    <?php if (empty($applications)): ?>
        <p>Chưa có ứng viên nào ứng tuyển công việc này.</p>
    <?php else: ?>
        <div class="row">
            <?php foreach ($applications as $app): ?>
                <div class="col-md-3 mb-3">
                    <div class="job-card shadow p-2">
                        <div class="job-details">
                            <h6 class="job-title"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                            <p class="job-info">SĐT: <?php echo htmlspecialchars($app['SDT'] ?? 'Chưa cung cấp'); ?></p>
                            <p class="job-info">Email: <?php echo htmlspecialchars($app['email'] ?? 'Chưa cung cấp'); ?></p>
                            <p class="job-info">Trạng thái: <?php echo htmlspecialchars($app['status'] ?? 'Chưa xác định'); ?></p>
                            <p class="job-info">Ngày nộp: <?php echo date('d/m/Y H:i', strtotime($app['applied_at'])); ?></p>
                            <p class="job-info">Lý do từ chối: <?php echo htmlspecialchars($app['rejected_reason'] ?? 'Không có'); ?></p>
                            <div class="job-actions mt-1">
                                <a href="<?php echo htmlspecialchars($app['cv_path'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-info" <?php echo empty($app['cv_path']) ? 'disabled' : ''; ?>>Xem CV</a>
                                <button class="btn btn-sm btn-primary" onclick="openProfileModal(<?php echo $app['user_id']; ?>)">Xem hồ sơ</button>
                                <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $app['application_id']; ?>)">Cập nhật trạng thái</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Profile Modal -->
<div id="profileModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeProfileModal()">×</span>
        <h4 class="mb-4">Hồ sơ ứng viên</h4>
        <div id="profileContent" class="profile-content"></div>
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeStatusModal()">×</span>
        <h4 class="mb-3">Cập nhật trạng thái ứng viên</h4>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="application_id" id="statusApplicationId">
            <div class="form-group mb-3">
                <label>Trạng thái:</label>
                <select name="status" class="form-control" style="height: fit-content" required>
                    <option value="đang duyệt">Đang duyệt</option>
                    <option value="không được duyệt">Không được duyệt</option>
                    <option value="đã duyệt">Đã duyệt</option>
                    <option value="chờ phỏng vấn">Chờ phỏng vấn</option>
                    <option value="chờ kiểm tra">Chờ kiểm tra</option>
                    <option value="không trúng tuyển">Không trúng tuyển</option>
                    <option value="trúng tuyển">Trúng tuyển</option>
                </select>
            </div>
            <div class="form-group mb-3" id="rejectedReasonField" style="display: none;">
                <label>Lý do từ chối:</label>
                <textarea name="rejected_reason" class="form-control" style="height: fit-content" placeholder="Nhập lý do từ chối (bắt buộc nếu không duyệt hoặc không trúng tuyển)"></textarea>
            </div>
            <div class="form-group mb-3" id="interviewField" style="display: none;">
                <label>Ngày phỏng vấn:</label>
                <input type="datetime-local" name="interview_date" class="form-control" style="height: fit-content">
                <label class="mt-2">Địa điểm:</label>
                <input type="text" name="location" class="form-control" style="height: fit-content" placeholder="Nhập địa điểm (nếu có)">
                <label class="mt-2">Link họp (nếu phỏng vấn online):</label>
                <input type="text" name="meeting_link" class="form-control" style="height: fit-content" placeholder="Nhập link họp (nếu có)">
            </div>
            <div class="form-group mb-3" id="testField" style="display: none;">
                <label>Nội dung kiểm tra (tệp):</label>
                <input type="file" name="test_content" class="form-control" style="height: fit-content">
                <label class="mt-2">Hạn nộp:</label>
                <input type="datetime-local" name="deadline" class="form-control" style="height: fit-content">
            </div>
            <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function openProfileModal(user_id) {
        fetch('get_profile.php?user_id=' + user_id)
            .then(response => response.text())
            .then(data => {
                document.getElementById('profileContent').innerHTML = data;
                document.getElementById('profileModal').style.display = 'flex';
            })
            .catch(error => console.error('Error:', error));
    }

    function closeProfileModal() {
        document.getElementById('profileModal').style.display = 'none';
    }

    function openStatusModal(application_id) {
        document.getElementById('statusApplicationId').value = application_id;
        document.getElementById('statusModal').style.display = 'flex';

        const statusSelect = document.getElementById('statusModal').querySelector('select[name="status"]');
        const rejectedReasonField = document.getElementById('rejectedReasonField');
        const interviewField = document.getElementById('interviewField');
        const testField = document.getElementById('testField');

        statusSelect.addEventListener('change', function() {
            // Reset all fields
            rejectedReasonField.style.display = 'none';
            interviewField.style.display = 'none';
            testField.style.display = 'none';

            // Show fields based on status
            if (this.value === 'không được duyệt' || this.value === 'không trúng tuyển') {
                rejectedReasonField.style.display = 'block';
            } else if (this.value === 'chờ phỏng vấn') {
                interviewField.style.display = 'block';
            } else if (this.value === 'chờ kiểm tra') {
                testField.style.display = 'block';
            }
        });

        // Trigger change event on modal open to set initial state
        statusSelect.dispatchEvent(new Event('change'));
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const profileModal = document.getElementById('profileModal');
        const statusModal = document.getElementById('statusModal');
        if (event.target === profileModal) {
            profileModal.style.display = 'none';
        }
        if (event.target === statusModal) {
            statusModal.style.display = 'none';
        }
    }
</script>

<style>
    .job-detail-card {
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .job-card {
        background-color: white;
        border-radius: 5px;
        padding: 5px 10px;
        border: 1px solid #e0e0e0;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .job-details {
        flex-grow: 1;
    }

    .job-title {
        font-size: 14px;
        font-weight: 500;
        color: #333;
        margin-bottom: 2px;
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .job-info {
        font-size: 12px;
        color: #666;
        margin-bottom: 2px;
    }

    .job-actions .btn {
        margin-right: 2px;
        font-size: 10px;
        padding: 2px 5px;
    }

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
        max-width: 600px;
        position: relative;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .close {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        cursor: pointer;
        color: #666;
    }

    .close:hover {
        color: #333;
    }

    .profile-content {
        display: flex;
        gap: 20px;
        padding: 10px;
    }

    .profile-content .avatar-section {
        flex: 0 0 auto;
    }

    .profile-content .avatar-section img {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e0e0e0;
    }

    .profile-content .info-section {
        flex: 1;
    }

    .profile-content .info-section h5 {
        margin-bottom: 15px;
        color: #333;
        font-size: 20px;
    }

    .profile-content .info-section p {
        margin-bottom: 10px;
        font-size: 14px;
        color: #555;
    }

    .profile-content .info-section p strong {
        color: #333;
        display: inline-block;
        width: 120px;
    }

    .profile-content .info-section hr {
        border: 0;
        border-top: 1px solid #e0e0e0;
        margin: 15px 0;
    }

    .btn-primary {
        background-color: #00b14f;
        border-color: #00b14f;
    }

    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }

    .btn-info {
        background-color: #17a2b8;
        border-color: #17a2b8;
    }

    .form-control {
        border-radius: 3px;
        height: 30px;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        font-weight: 600;
    }

    .d-flex {
        display: flex;
    }

    .me-2 {
        margin-right: 0.5rem;
    }

    .btn:disabled {
        background-color: #ccc;
        border-color: #ccc;
        cursor: not-allowed;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        border-radius: 4px;
        padding: 10px;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        border-radius: 4px;
        padding: 10px;
    }
</style>