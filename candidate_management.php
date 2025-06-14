<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff_candidate') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
try {
    // Auto-delete applications with status 'không xác nhận' older than 24 hours
    $delete_threshold = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $stmt_delete = $pdo->prepare("
        DELETE FROM applications 
        WHERE status = 'không xác nhận' 
        AND applied_at < ?
    ");
    $stmt_delete->execute([$delete_threshold]);

    // Handle search by name or email
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Build the query based on search
    $sql = "
        SELECT a.application_id, a.job_id, a.user_id, a.full_name, a.SDT, a.email, a.cv_path, a.status, a.applied_at, a.rejected_reason, j.job_title
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " WHERE (a.full_name LIKE ? OR a.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY a.applied_at DESC";

    $stmt_applications = $pdo->prepare($sql);
    $stmt_applications->execute($params);
    $applications = $stmt_applications->fetchAll(PDO::FETCH_ASSOC);

    // Group applications by status for display
    $waiting = array_filter($applications, fn($app) => $app['status'] === 'chờ xác nhận');
    $rejected = array_filter($applications, fn($app) => $app['status'] === 'không xác nhận');
    $confirmed = array_filter($applications, fn($app) => $app['status'] === 'đã xác nhận');
    $others = array_filter($applications, fn($app) => !in_array($app['status'], ['chờ xác nhận', 'không xác nhận', 'đã xác nhận']));

    // Handle update for application status
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
        $rejected_reason = isset($_POST['rejected_reason']) ? trim($_POST['rejected_reason']) : NULL;

        if ($application_id > 0 && in_array($status, ['đã xác nhận', 'không xác nhận'])) {
            // Validate CV file (basic check: file exists and is a PDF or DOC/DOCX)
            $stmt_cv = $pdo->prepare("SELECT cv_path FROM applications WHERE application_id = ?");
            $stmt_cv->execute([$application_id]);
            $cv_path = $stmt_cv->fetchColumn();

            $is_valid_cv = false;
            if ($cv_path && file_exists($cv_path)) {
                $file_extension = strtolower(pathinfo($cv_path, PATHINFO_EXTENSION));
                $is_valid_cv = in_array($file_extension, ['pdf', 'doc', 'docx']);
            }

            if ($status === 'đã xác nhận' && $is_valid_cv) {
                $stmt_update = $pdo->prepare("UPDATE applications SET status = ?, rejected_reason = NULL WHERE application_id = ?");
                $stmt_update->execute([$status, $application_id]);
                $message = "Xác nhận hồ sơ thành công!";
            } elseif ($status === 'không xác nhận') {
                if (!$rejected_reason) {
                    $message = "Vui lòng nhập lý do từ chối!";
                } else {
                    $stmt_update = $pdo->prepare("UPDATE applications SET status = ?, rejected_reason = ? WHERE application_id = ?");
                    $stmt_update->execute([$status, $rejected_reason, $application_id]);
                    $message = "Từ chối hồ sơ thành công!";
                }
            } else {
                $message = "Hồ sơ không hợp lệ. Vui lòng kiểm tra lại CV!";
            }
        } else {
            $message = "Cập nhật trạng thái thất bại. Vui lòng thử lại!";
        }

        // Refresh page to reflect updated status
        header("Location: candidate_management.php?search=" . urlencode($search));
        exit();
    }
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/sc_navBar.php'; ?>

<!-- Applications Management Section -->
<section class="container my-5">
    <h2 class="mb-4">Quản lý đơn ứng tuyển</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'thành công') !== false ? 'success' : 'danger'; ?> mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="mb-4">
        <form method="GET" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm kiếm theo tên hoặc email..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Tìm kiếm</button>
        </form>
    </div>

    <!-- Section 1: Waiting for Confirmation -->
    <h3 class="mb-3">Đơn chờ xác nhận</h3>
    <?php if (!empty($waiting)): ?>
        <div class="row">
            <?php foreach ($waiting as $app): ?>
                <div class="col-md-4 mb-3">
                    <div class="application-card shadow p-3">
                        <h6 class="application-title"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                        <p class="application-info"><strong>Công việc:</strong> <?php echo htmlspecialchars($app['job_title']); ?></p>
                        <p class="application-info"><strong>Email:</strong> <?php echo htmlspecialchars($app['email'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>SĐT:</strong> <?php echo htmlspecialchars($app['SDT'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($app['status'] ?? 'Chờ xác nhận'); ?></p>
                        <p class="application-info"><strong>Ngày nộp:</strong> <?php echo date('d/m/Y H:i', strtotime($app['applied_at'])); ?></p>
                        <div class="application-actions mt-2">
                            <a href="<?php echo htmlspecialchars($app['cv_path'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-info" <?php echo empty($app['cv_path']) ? 'disabled' : ''; ?>>Xem CV</a>
                            <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $app['application_id']; ?>)">Cập nhật trạng thái</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có đơn nào đang chờ xác nhận.</p>
    <?php endif; ?>
    <hr class="my-4">

    <!-- Section 2: Rejected -->
    <h3 class="mb-3">Đơn không xác nhận</h3>
    <?php if (!empty($rejected)): ?>
        <div class="row">
            <?php foreach ($rejected as $app): ?>
                <div class="col-md-4 mb-3">
                    <div class="application-card shadow p-3">
                        <h6 class="application-title"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                        <p class="application-info"><strong>Công việc:</strong> <?php echo htmlspecialchars($app['job_title']); ?></p>
                        <p class="application-info"><strong>Email:</strong> <?php echo htmlspecialchars($app['email'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>SĐT:</strong> <?php echo htmlspecialchars($app['SDT'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($app['status'] ?? 'Không xác nhận'); ?></p>
                        <p class="application-info"><strong>Lý do từ chối:</strong> <?php echo htmlspecialchars($app['rejected_reason'] ?? 'Không có'); ?></p>
                        <p class="application-info"><strong>Ngày nộp:</strong> <?php echo date('d/m/Y H:i', strtotime($app['applied_at'])); ?></p>
                        <div class="application-actions mt-2">
                            <a href="<?php echo htmlspecialchars($app['cv_path'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-info" <?php echo empty($app['cv_path']) ? 'disabled' : ''; ?>>Xem CV</a>
                            <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $app['application_id']; ?>)">Cập nhật trạng thái</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có đơn nào bị từ chối.</p>
    <?php endif; ?>
    <hr class="my-4">

    <!-- Section 3: Confirmed -->
    <h3 class="mb-3">Đơn đã xác nhận</h3>
    <?php if (!empty($confirmed)): ?>
        <div class="row">
            <?php foreach ($confirmed as $app): ?>
                <div class="col-md-4 mb-3">
                    <div class="application-card shadow p-3">
                        <h6 class="application-title"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                        <p class="application-info"><strong>Công việc:</strong> <?php echo htmlspecialchars($app['job_title']); ?></p>
                        <p class="application-info"><strong>Email:</strong> <?php echo htmlspecialchars($app['email'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>SĐT:</strong> <?php echo htmlspecialchars($app['SDT'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($app['status'] ?? 'Đã xác nhận'); ?></p>
                        <p class="application-info"><strong>Ngày nộp:</strong> <?php echo date('d/m/Y H:i', strtotime($app['applied_at'])); ?></p>
                        <div class="application-actions mt-2">
                            <a href="<?php echo htmlspecialchars($app['cv_path'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-info" <?php echo empty($app['cv_path']) ? 'disabled' : ''; ?>>Xem CV</a>
                            <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $app['application_id']; ?>)">Cập nhật trạng thái</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có đơn nào đã xác nhận.</p>
    <?php endif; ?>
    <hr class="my-4">

    <!-- Section 4: Other Statuses -->
    <h3 class="mb-3">Đơn các trạng thái khác</h3>
    <?php if (!empty($others)): ?>
        <div class="row">
            <?php foreach ($others as $app): ?>
                <div class="col-md-4 mb-3">
                    <div class="application-card shadow p-3">
                        <h6 class="application-title"><?php echo htmlspecialchars($app['full_name']); ?></h6>
                        <p class="application-info"><strong>Công việc:</strong> <?php echo htmlspecialchars($app['job_title']); ?></p>
                        <p class="application-info"><strong>Email:</strong> <?php echo htmlspecialchars($app['email'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>SĐT:</strong> <?php echo htmlspecialchars($app['SDT'] ?? 'Chưa cung cấp'); ?></p>
                        <p class="application-info"><strong>Trạng thái:</strong> <?php echo htmlspecialchars($app['status'] ?? 'Khác'); ?></p>
                        <p class="application-info"><strong>Ngày nộp:</strong> <?php echo date('d/m/Y H:i', strtotime($app['applied_at'])); ?></p>
                        <div class="application-actions mt-2">
                            <a href="<?php echo htmlspecialchars($app['cv_path'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-info" <?php echo empty($app['cv_path']) ? 'disabled' : ''; ?>>Xem CV</a>
                            <button class="btn btn-sm btn-success" onclick="openStatusModal(<?php echo $app['application_id']; ?>)">Cập nhật trạng thái</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Không có đơn nào thuộc các trạng thái khác.</p>
    <?php endif; ?>
</section>

<!-- Status Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeStatusModal()">×</span>
        <h4 class="mb-3">Cập nhật trạng thái hồ sơ</h4>
        <form method="POST">
            <input type="hidden" name="application_id" id="statusApplicationId">
            <div class="form-group mb-3">
                <label>Trạng thái:</label>
                <select name="status" class="form-control" style="height: fit-content" required>
                    <option value="đã xác nhận">Đã xác nhận</option>
                    <option value="không xác nhận">Không xác nhận</option>
                </select>
            </div>
            <div class="form-group mb-3" id="rejectedReasonField" style="display: none;">
                <label>Lý do từ chối:</label>
                <textarea name="rejected_reason" class="form-control" style="height: fit-content" placeholder="Nhập lý do từ chối (bắt buộc nếu không xác nhận)"></textarea>
            </div>
            <button type="submit" name="update_status" class="btn btn-primary">Cập nhật</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function openStatusModal(application_id) {
        document.getElementById('statusApplicationId').value = application_id;
        document.getElementById('statusModal').style.display = 'flex';

        const statusSelect = document.getElementById('statusModal').querySelector('select[name="status"]');
        const rejectedReasonField = document.getElementById('rejectedReasonField');
        statusSelect.addEventListener('change', function() {
            if (this.value === 'không xác nhận') {
                rejectedReasonField.style.display = 'block';
            } else {
                rejectedReasonField.style.display = 'none';
            }
        });
        // Reset display based on initial value
        if (statusSelect.value === 'không xác nhận') {
            rejectedReasonField.style.display = 'block';
        }
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const statusModal = document.getElementById('statusModal');
        if (event.target === statusModal) {
            statusModal.style.display = 'none';
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
    }

    .application-title {
        font-size: 16px;
        font-weight: 500;
        color: #333;
        margin-bottom: 10px;
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
        max-width: 500px;
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

    .btn-info {
        background-color: #17a2b8;
        border-color: #17a2b8;
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
</style>