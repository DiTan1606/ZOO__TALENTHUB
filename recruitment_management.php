<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff_recruitment') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
try {
    // Auto-delete jobs with status 'không duyệt' older than 24 hours
    $delete_threshold = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $stmt_delete = $pdo->prepare("
        DELETE FROM jobs 
        WHERE status = 'không duyệt' 
        AND created_at < ?
    ");
    $stmt_delete->execute([$delete_threshold]);

    // Handle search by job title
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build the query for jobs waiting for approval
    $sql_waiting = "
        SELECT j.job_id, j.company_id, j.job_title, j.experience_required, j.work_type, j.deadline, j.description, j.candidate_requirements, j.benefits, j.working_hours, j.work_location, j.salary, j.status, j.rejection_reason, j.created_at, c.company_name
        FROM jobs j
        JOIN company c ON j.company_id = c.company_id
        WHERE j.status = 'chờ duyệt'
    ";
    $params_waiting = [];

    if (!empty($search)) {
        $sql_waiting .= " AND j.job_title LIKE ?";
        $params_waiting[] = "%$search%";
    }

    $sql_waiting .= " ORDER BY j.created_at DESC";

    $stmt_waiting = $pdo->prepare($sql_waiting);
    $stmt_waiting->execute($params_waiting);
    $waiting_jobs = $stmt_waiting->fetchAll(PDO::FETCH_ASSOC);

    // Build the query for approved jobs
    $sql_approved = "
        SELECT j.job_id, j.company_id, j.job_title, j.experience_required, j.work_type, j.deadline, j.description, j.candidate_requirements, j.benefits, j.working_hours, j.work_location, j.salary, j.status, j.rejection_reason, j.created_at, c.company_name
        FROM jobs j
        JOIN company c ON j.company_id = c.company_id
        WHERE j.status = 'đã duyệt'
    ";
    $params_approved = [];

    if (!empty($search)) {
        $sql_approved .= " AND j.job_title LIKE ?";
        $params_approved[] = "%$search%";
    }

    $sql_approved .= " ORDER BY j.created_at DESC";

    $stmt_approved = $pdo->prepare($sql_approved);
    $stmt_approved->execute($params_approved);
    $approved_jobs = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);

    // Build the query for rejected jobs
    $sql_rejected = "
        SELECT j.job_id, j.company_id, j.job_title, j.experience_required, j.work_type, j.deadline, j.description, j.candidate_requirements, j.benefits, j.working_hours, j.work_location, j.salary, j.status, j.rejection_reason, j.created_at, c.company_name
        FROM jobs j
        JOIN company c ON j.company_id = c.company_id
        WHERE j.status = 'không duyệt'
    ";
    $params_rejected = [];

    if (!empty($search)) {
        $sql_rejected .= " AND j.job_title LIKE ?";
        $params_rejected[] = "%$search%";
    }

    $sql_rejected .= " ORDER BY j.created_at DESC";

    $stmt_rejected = $pdo->prepare($sql_rejected);
    $stmt_rejected->execute($params_rejected);
    $rejected_jobs = $stmt_rejected->fetchAll(PDO::FETCH_ASSOC);

    // Handle update for job status
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $rejection_reason = isset($_POST['rejection_reason']) ? trim($_POST['rejection_reason']) : NULL;

        if ($job_id > 0 && in_array($status, ['đã duyệt', 'không duyệt'])) {
            if ($status === 'đã duyệt') {
                $stmt_update = $pdo->prepare("UPDATE jobs SET status = ?, rejection_reason = NULL WHERE job_id = ?");
                $stmt_update->execute([$status, $job_id]);
                $message = "Duyệt bài đăng thành công!";
            } elseif ($status === 'không duyệt') {
                if (!$rejection_reason) {
                    $message = "Vui lòng nhập lý do từ chối!";
                } else {
                    $stmt_update = $pdo->prepare("UPDATE jobs SET status = ?, rejection_reason = ?, created_at = NOW() WHERE job_id = ?");
                    $stmt_update->execute([$status, $rejection_reason, $job_id]);
                    $message = "Từ chối bài đăng thành công!";
                }
            }
        } else {
            $message = "Cập nhật trạng thái thất bại. Vui lòng thử lại!";
        }

        // Refresh page to reflect updated status
        header("Location: recruitment_management.php?search=" . urlencode($search));
        exit();
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/sr_navBar.php'; ?>

<!-- Jobs Management Section -->
<section class="container my-5">
    <h2 class="mb-4">Quản lý bài đăng tuyển dụng</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'thành công') !== false ? 'success' : 'danger'; ?> mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="mb-4">
        <form method="GET" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm kiếm theo tiêu đề..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </form>
    </div>

    <!-- Section: Waiting for Approval -->
    <h3 class="mb-3">Bài đăng chờ duyệt</h3>
    <?php if (!empty($waiting_jobs)): ?>
        <div class="row">
            <?php foreach ($waiting_jobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="application-card shadow p-3" data-job='<?php echo htmlspecialchars(json_encode($job, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>
                        <a href="javascript:void(0)" class="application-title">
                            <?php echo htmlspecialchars($job['job_title']); ?>
                        </a>
                        <p class="application-info"><strong>Công ty:</strong> <?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p class="application-info"><strong>Hạn nộp:</strong> <?php echo date('d/m/Y', strtotime($job['deadline'])); ?></p>
                        <p class="application-info"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($job['status'] ?? 'Chờ duyệt'); ?></p>
                        <p class="application-info"><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($job['created_at'])); ?></p>
                        <div class="application-actions mt-2">
                            <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $job['job_id']; ?>)">Cập nhật trạng thái</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có bài đăng nào đang chờ duyệt.</p>
    <?php endif; ?>

    <hr class="my-4">

    <!-- Section: Approved Jobs -->
    <h3 class="mb-3">Bài đăng đã duyệt</h3>
    <?php if (!empty($approved_jobs)): ?>
        <div class="row">
            <?php foreach ($approved_jobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="application-card shadow p-3" data-job='<?php echo htmlspecialchars(json_encode($job, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>
                        <a href="javascript:void(0)" class="application-title">
                            <?php echo htmlspecialchars($job['job_title']); ?>
                        </a>
                        <p class="application-info"><strong>Công ty:</strong> <?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p class="application-info"><strong>Hạn nộp:</strong> <?php echo date('d/m/Y', strtotime($job['deadline'])); ?></p>
                        <p class="application-info"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($job['status'] ?? 'Đã duyệt'); ?></p>
                        <p class="application-info"><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($job['created_at'])); ?></p>
                        <div class="application-actions mt-2">
                            <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $job['job_id']; ?>)">Cập nhật trạng thái</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có bài đăng nào đã duyệt.</p>
    <?php endif; ?>

    <hr class="my-4">

    <!-- Section: Rejected Jobs -->
    <h3 class="mb-3">Bài đăng không duyệt</h3>
    <?php if (!empty($rejected_jobs)): ?>
        <div class="row">
            <?php foreach ($rejected_jobs as $job): ?>
                <div class="col-md-4 mb-3">
                    <div class="application-card shadow p-3" data-job='<?php echo htmlspecialchars(json_encode($job, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>
                        <a href="javascript:void(0)" class="application-title">
                            <?php echo htmlspecialchars($job['job_title']); ?>
                        </a>
                        <p class="application-info"><strong>Công ty:</strong> <?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p class="application-info"><strong>Hạn nộp:</strong> <?php echo date('d/m/Y', strtotime($job['deadline'])); ?></p>
                        <p class="application-info"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($job['status'] ?? 'Không duyệt'); ?></p>
                        <p class="application-info"><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($job['created_at'])); ?></p>
                        <p class="application-info"><strong>Lý do từ chối:</strong> <?php echo htmlspecialchars($job['rejection_reason'] ?? 'Không có'); ?></p>
                        <div class="application-actions mt-2">
                            <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $job['job_id']; ?>)">Cập nhật trạng thái</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có bài đăng nào không được duyệt.</p>
    <?php endif; ?>
</section>

<!-- Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeStatusModal()">×</span>
        <h4 class="mb-3">Cập nhật trạng thái bài đăng</h4>
        <form method="POST">
            <input type="hidden" name="job_id" id="statusJobId">
            <div class="form-group mb-3">
                <label>Trạng thái:</label>
                <select name="status" class="form-control" style="height: fit-content" required>
                    <option value="đã duyệt">Đã duyệt</option>
                    <option value="không duyệt">Không duyệt</option>
                </select>
            </div>
            <div class="form-group mb-3" id="rejectedReasonField" style="display: none;">
                <label>Lý do từ chối:</label>
                <textarea name="rejection_reason" class="form-control" style="height: fit-content" placeholder="Nhập lý do từ chối (bắt buộc nếu không duyệt)"></textarea>
            </div>
            <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
        </form>
    </div>
</div>

<!-- Job Detail Modal -->
<div id="jobDetailModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeJobDetailModal()">×</span>
        <h4 class="mb-3">Chi tiết bài đăng tuyển dụng</h4>
        <div id="jobDetailContent">
            <div class="mb-3">
                <p class="mb-1"><strong>Tiêu đề công việc:</strong></p>
                <div id="jobDetailTitle"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Kinh nghiệm yêu cầu:</strong></p>
                <div id="jobDetailExperience"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Loại công việc:</strong></p>
                <div id="jobDetailWorkType"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Hạn nộp hồ sơ:</strong></p>
                <div id="jobDetailDeadline"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Mô tả công việc:</strong></p>
                <div id="jobDetailDescription"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Yêu cầu ứng viên:</strong></p>
                <div id="jobDetailRequirements"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Quyền lợi:</strong></p>
                <div id="jobDetailBenefits"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Thời gian làm việc:</strong></p>
                <div id="jobDetailWorkingHours"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Địa điểm làm việc:</strong></p>
                <div id="jobDetailWorkLocation"></div>
            </div>
            <div class="mb-3">
                <p class="mb-1"><strong>Lương:</strong></p>
                <div id="jobDetailSalary"></div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    // Add event listeners to all application cards
    document.addEventListener('DOMContentLoaded', function() {
        const applicationCards = document.querySelectorAll('.application-card');
        applicationCards.forEach(card => {
            card.addEventListener('click', function(event) {
                // Prevent click on button from triggering the modal
                if (event.target.tagName !== 'BUTTON') {
                    const jobData = JSON.parse(this.getAttribute('data-job'));
                    openJobDetailModal(jobData);
                }
            });
        });
    });

    function openStatusModal(job_id) {
        document.getElementById('statusJobId').value = job_id;
        document.getElementById('statusModal').style.display = 'flex';

        const statusSelect = document.getElementById('statusModal').querySelector('select[name="status"]');
        const rejectedReasonField = document.getElementById('rejectedReasonField');
        statusSelect.addEventListener('change', function() {
            if (this.value === 'không duyệt') {
                rejectedReasonField.style.display = 'block';
            } else {
                rejectedReasonField.style.display = 'none';
            }
        });
        // Reset display based on initial value
        if (statusSelect.value === 'không duyệt') {
            rejectedReasonField.style.display = 'block';
        }
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    function openJobDetailModal(job) {
        console.log('Opening modal with job:', job); // Debug log
        document.getElementById('jobDetailTitle').textContent = job.job_title || 'Không có';
        document.getElementById('jobDetailExperience').textContent = job.experience_required || 'Không có';
        document.getElementById('jobDetailWorkType').textContent = job.work_type || 'Không có';
        document.getElementById('jobDetailDeadline').textContent = job.deadline ? new Date(job.deadline).toLocaleDateString('vi-VN') : 'Không có';
        document.getElementById('jobDetailDescription').textContent = job.description || 'Không có';
        document.getElementById('jobDetailRequirements').textContent = job.candidate_requirements || 'Không có';
        document.getElementById('jobDetailBenefits').textContent = job.benefits || 'Không có';
        document.getElementById('jobDetailWorkingHours').textContent = job.working_hours || 'Không có';
        document.getElementById('jobDetailWorkLocation').textContent = job.work_location || 'Không có';
        document.getElementById('jobDetailSalary').textContent = job.salary || 'Không có';

        document.getElementById('jobDetailModal').style.display = 'flex';
    }

    function closeJobDetailModal() {
        document.getElementById('jobDetailModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const statusModal = document.getElementById('statusModal');
        const jobDetailModal = document.getElementById('jobDetailModal');
        if (event.target === statusModal) {
            statusModal.style.display = 'none';
        }
        if (event.target === jobDetailModal) {
            jobDetailModal.style.display = 'none';
        }
    }
</script>

<style>
    .application-card {
        background-color: white;
        border-radius: 5px;
        padding: 15px;
        border: 1px solid #e0e0e0;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
        cursor: pointer; /* Add cursor to indicate clickable area */
    }

    .application-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .application-card:hover .application-title {
        color: #00b14f;
    }

    .application-title {
        font-size: 16px;
        font-weight: 500;
        color: #333;
        margin-bottom: 10px;
        text-decoration: none;
        display: block;
    }

    .application-info {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
    }

    .application-info strong {
        color: #333;
    }

    .application-actions .btn {
        margin-right: 5px;
        font-size: 12px;
        padding: 4px 8px;
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

    .btn-primary {
        background-color: #00b14f;
        border-color: #00b14f;
    }

    .btn-success {
        background-color: #28a745;
        border-color: #28a745;
    }

    .form-control {
        border-radius: 3px;
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

    hr.my-4 {
        border: 0;
        border-top: 1px solid #e0e0e0;
        margin: 2rem 0;
    }

    #jobDetailContent p {
        margin-bottom: 10px;
    }

    #jobDetailContent strong {
        color: #333;
    }
</style>