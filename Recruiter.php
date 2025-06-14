<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    header("Location: login.php");
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

    // Handle job actions
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['create_job'])) {
            $job_title = $_POST['job_title'] ?? 'Công việc mới';
            $experience_required = $_POST['experience_required'] ?? null;
            $work_type = $_POST['work_type'] ?? null;
            $deadline = $_POST['deadline'] ?? null;
            $description = $_POST['description'] ?? null;
            $candidate_requirements = $_POST['candidate_requirements'] ?? null;
            $benefits = $_POST['benefits'] ?? null;
            $working_hours = $_POST['working_hours'] ?? null;
            $work_location = $_POST['work_location'] ?? null;
            $salary = $_POST['salary'] ?? null;

            $stmt_insert = $pdo->prepare("INSERT INTO jobs (company_id, job_title, experience_required, work_type, deadline, description, candidate_requirements, benefits, working_hours, work_location, salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'chờ duyệt')");
            $stmt_insert->execute([$company_id, $job_title, $experience_required, $work_type, $deadline, $description, $candidate_requirements, $benefits, $working_hours, $work_location, $salary]);
            $message = "Tạo công việc mới thành công! Đang chờ duyệt.";
        } elseif (isset($_POST['delete_job']) && isset($_POST['job_id'])) {
            $job_id = (int)$_POST['job_id'];
            $stmt_check = $pdo->prepare("SELECT status FROM jobs WHERE job_id = ? AND company_id = ?");
            $stmt_check->execute([$job_id, $company_id]);
            $job = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($job && $job['status'] === 'đã duyệt') {
                $stmt_delete = $pdo->prepare("DELETE FROM jobs WHERE job_id = ? AND company_id = ?");
                $stmt_delete->execute([$job_id, $company_id]);
                $message = "Xóa công việc thành công!";
            } else {
                $message = "Chỉ có thể xóa công việc đã được duyệt!";
            }
        }
    }

    // Handle search and pagination parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $jobs_per_page = 6; // Số job trên mỗi trang
    $offset = ($page - 1) * $jobs_per_page;

    // Build the job query dynamically
    $query = "
        SELECT j.job_id, j.job_title, j.work_type, j.salary, j.deadline, j.status, j.rejection_reason, c.company_name, c.logo, c.province
        FROM jobs j
        JOIN company c ON j.company_id = c.company_id
        WHERE j.company_id = :company_id
    ";
    $params = [':company_id' => $company_id];

    if ($search) {
        $query .= " AND j.job_title LIKE :search";
        $params[':search'] = "%$search%";
    }

    $query .= " ORDER BY j.deadline DESC LIMIT :limit OFFSET :offset";

    // Fetch jobs with pagination
    $stmt_jobs = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt_jobs->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt_jobs->bindValue(':limit', (int)$jobs_per_page, PDO::PARAM_INT);
    $stmt_jobs->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt_jobs->execute();
    $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    // Count total jobs for pagination
    $count_query = "SELECT COUNT(*) FROM jobs j WHERE j.company_id = :company_id";
    $count_params = [':company_id' => $company_id];
    if ($search) {
        $count_query .= " AND j.job_title LIKE :search";
        $count_params[':search'] = "%$search%";
    }
    $stmt_count = $pdo->prepare($count_query);
    foreach ($count_params as $key => $value) {
        $stmt_count->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt_count->execute();
    $total_jobs = $stmt_count->fetchColumn();
    $total_pages = max(1, ceil($total_jobs / $jobs_per_page));

    // Adjust page if it exceeds total pages
    if ($page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $jobs_per_page;
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/rec_navBar.php'; ?>

<!-- Job Management Section -->
<section class="container my-5">
    <h2 class="mb-4">Quản lý tuyển dụng</h2>

    <!-- Search and Add Job Row -->
    <div class="row mb-4 align-items-center">
        <div class="col">
            <form method="GET" class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Tìm theo tiêu đề..." value="<?php echo htmlspecialchars($search ?? ''); ?>">
                <button type="submit" class="btn btn-primary">Tìm kiếm</button>
            </form>
        </div>
        <div class="col-auto">
            <button type="button" class="btn btn-primary" onclick="showJobForm()">Thêm bài tuyển dụng mới</button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success mb-4"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (count($jobs) > 0): ?>
        <div class="row">
            <?php foreach ($jobs as $job): ?>
                <div class="col-md-4 mb-4">
                    <a href="recruit_detail.php?id=<?php echo $job['job_id']; ?>" style="color: inherit; text-decoration: none;" class="job-card shadow position-relative <?php echo $job['status'] === 'chờ duyệt' ? 'opacity-50' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($job['logo'] ?? 'https://via.placeholder.com/70'); ?>" alt="Company Logo" class="job-logo">
                        <div class="job-details flex-grow-1">
                            <h5 class="job-title"><?php echo htmlspecialchars($job['job_title']); ?></h5>
                            <p class="job-info"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            <div class="job-subinfo-box">
                                <span class="job-subinfo"><?php echo htmlspecialchars($job['province'] ?? 'Không xác định'); ?></span>
                                <span class="job-subinfo"><?php echo htmlspecialchars($job['work_type']); ?></span>
                                <span class="job-subinfo"><?php echo htmlspecialchars($job['salary'] ?? 'Thỏa thuận'); ?></span>
                            </div>
                            <p class="job-status"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($job['status']); ?></p>
                            <?php if ($job['status'] === 'không duyệt'): ?>
                                <p class="text-danger mt-2">Lý do: <?php echo htmlspecialchars($job['rejection_reason'] ?? 'Không có'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($job['status'] === 'đã duyệt'): ?>
                            <form method="POST" style="position: absolute; top: 5px; right: 5px;" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                                <button type="submit" name="delete_job" class="close-btn">×</button>
                            </form>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Trước</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Sau</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php else: ?>
        <p class="text-center">Chưa có bài đăng tuyển dụng nào.</p>
    <?php endif; ?>
</section>

<!-- Job Form Modal -->
<div id="jobModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeJobForm()">×</span>
        <h4 class="mb-3">Thông tin công việc</h4>
        <form method="POST" id="jobForm">
            <input type="hidden" name="job_id" id="jobId">
            <div class="job-section bg-light p-3 mb-4">
                <div class="form-group mb-3">
                    <label>Tiêu đề công việc:</label>
                    <input type="text" name="job_title" class="form-control" id="jobTitle" required>
                </div>
                <div class="form-group mb-3">
                    <label>Kinh nghiệm yêu cầu:</label>
                    <input type="text" name="experience_required" class="form-control" id="experienceRequired" placeholder="Ví dụ: 1-2 năm">
                </div>
                <div class="form-group mb-3">
                    <label>Loại công việc:</label>
                    <select name="work_type" class="form-control" id="workType">
                        <option value="">Chọn loại công việc</option>
                        <option value="full-time">Toàn thời gian</option>
                        <option value="part-time">Bán thời gian</option>
                        <option value="freelance">Freelance</option>
                        <option value="contract">Hợp đồng ngắn hạn</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label>Hạn nộp hồ sơ:</label>
                    <input type="date" name="deadline" class="form-control" id="deadline">
                </div>
                <div class="form-group mb-3">
                    <label>Mô tả công việc:</label>
                    <textarea name="description" class="form-control" rows="4" id="description"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Yêu cầu ứng viên:</label>
                    <textarea name="candidate_requirements" class="form-control" rows="4" id="candidateRequirements"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Quyền lợi:</label>
                    <textarea name="benefits" class="form-control" rows="4" id="benefits"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Thời gian làm việc:</label>
                    <textarea name="working_hours" class="form-control" rows="4" id="workingHours" placeholder="Ví dụ: 8h-17h, Thứ 2 - Thứ 6"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Địa điểm làm việc:</label>
                    <input type="text" name="work_location" class="form-control" id="workLocation">
                </div>
                <div class="form-group mb-3">
                    <label>Lương:</label>
                    <input type="text" name="salary" class="form-control" id="salary" placeholder="Ví dụ: 10-15 triệu">
                </div>
            </div>
            <div class="text-end">
                <button type="submit" name="create_job" class="btn btn-success">Lưu</button>
            </div>
        </form>
    </div>
</div>

<style>
    .job-card {
        background-color: white;
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #e0e0e0;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        position: relative;
    }

    .job-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .job-card:hover .job-title {
        color: #00b14f;
    }
    .job-logo {
        width: 70px;
        height: 70px;
        object-fit: contain;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
    }

    .job-details {
        flex-grow: 1;
    }

    .job-title {
        font-size: 16px;
        font-weight: 500;
        color: #333;
        margin-bottom: 5px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .job-title a:hover {
        color: #00b14f;
    }

    .job-info {
        font-size: 14px;
        color: #666;
        margin-bottom: 5px;
    }
    .job-subinfo-box {
        display: flex;
        flex-wrap: wrap;
    }
    .job-subinfo {
        display: inline;
        font-size: 9px;
        font-weight: 400;
        color: #000;
        padding: 5px 10px;
        margin-right: 5px;
        background: #ddd;
        border-radius: 30px;
    }

    .job-status {
        font-size: 12px;
        color: #666;
        margin: 5px 0;
    }

    .close-btn {
        position: absolute;
        top: 5px;
        right: 5px;
        background: none;
        border: none;
        font-size: 20px;
        color: #dc3545;
        padding: 0;
        line-height: 1;
        cursor: pointer;
    }

    .close-btn:hover {
        color: #a71d2a;
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
    }

    .close {
        position: absolute;
        top: 10px;
        right: 15px;
        font-size: 24px;
        cursor: pointer;
    }

    .job-section {
        background-color: #fff5f5;
        border-radius: 5px;
    }

    .form-group label {
        font-weight: 600;
        margin-bottom: 5px;
    }

    .form-control {
        border-radius: 5px;
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

    .input-group .btn {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .pagination .page-link {
        color: #00b14f;
    }

    .pagination .page-item.active .page-link {
        background-color: #00b14f;
        border-color: #00b14f;
        color: white;
    }

    .opacity-50 {
        opacity: 0.5;
        pointer-events: none;
    }
</style>

<script>
    let edit_mode = false;
    let current_job_id = null;

    function showJobForm(job_id = null) {
        const modal = document.getElementById('jobModal');
        const form = document.getElementById('jobForm');
        edit_mode = !!job_id;
        current_job_id = job_id;

        if (edit_mode) {
            // Chuyển hướng đến trang recruit_detail.php để chỉnh sửa chi tiết
            window.location.href = 'recruit_detail.php?id=' + job_id;
            return;
        } else {
            form.reset();
            document.getElementById('jobId').value = '';
            form.querySelector('button[name="create_job"]').textContent = 'Lưu';
        }

        modal.style.display = 'flex';
    }

    function closeJobForm() {
        document.getElementById('jobModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('jobModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>

<?php include 'includes/footer.php'; ?>