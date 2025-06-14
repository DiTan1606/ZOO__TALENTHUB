<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

try {
    $stmt = $pdo->prepare("
        SELECT a.application_id, a.status, a.applied_at, a.rejected_reason, a.full_name, a.SDT, a.email, a.cv_path, j.job_id, j.job_title, c.company_name
        FROM applications a
        JOIN jobs j ON a.job_id = j.job_id
        JOIN company c ON j.company_id = c.company_id
        WHERE a.user_id = ?
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([$user_id]);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle update application
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application'])) {
        $application_id = $_POST['application_id'];
        $full_name = $_POST['full_name'];
        $SDT = $_POST['SDT'];
        $email = $_POST['email'];
        $cv_path = $_POST['current_cv_path'];
        if (!empty($_FILES['cv']['name'])) {
            $upload_dir = 'uploads/cvs/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_tmp = $_FILES['cv']['tmp_name'];
            $file_name = basename($_FILES['cv']['name']);
            $cv_path = $upload_dir . $file_name;
            move_uploaded_file($file_tmp, $cv_path);
        }
        $stmt = $pdo->prepare("UPDATE applications SET full_name = ?, SDT = ?, email = ?, cv_path = ?, status = 'chờ xác nhận' WHERE application_id = ?");
        $stmt->execute([$full_name, $SDT, $email, $cv_path, $application_id]);
        $message = "Cập nhật thông tin ứng tuyển thành công!";
        // Refresh data after update
        $stmt = $pdo->prepare("
            SELECT a.application_id, a.status, a.applied_at, a.rejected_reason, a.full_name, a.SDT, a.email, a.cv_path, j.job_title, c.company_name
            FROM applications a
            JOIN jobs j ON a.job_id = j.job_id
            JOIN company c ON j.company_id = c.company_id
            WHERE a.user_id = ?
            ORDER BY a.applied_at DESC
        ");
        $stmt->execute([$user_id]);
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Handle delete application
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'])) {
        $application_id = $_POST['application_id'];
        $stmt = $pdo->prepare("SELECT status FROM applications WHERE application_id = ?");
        $stmt->execute([$application_id]);
        $status = $stmt->fetchColumn();
        if (in_array($status, ['chờ xác nhận', 'không xác nhận'])) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM applications WHERE application_id = ? AND user_id = ?");
            $stmt->execute([$application_id, $user_id]);
            $pdo->commit();
            $message = "Xóa đơn ứng tuyển thành công!";
            // Refresh data after deletion
            $stmt = $pdo->prepare("
                SELECT a.application_id, a.status, a.applied_at, a.rejected_reason, a.full_name, a.SDT, a.email, a.cv_path, j.job_title, c.company_name
                FROM applications a
                JOIN jobs j ON a.job_id = j.job_id
                JOIN company c ON j.company_id = c.company_id
                WHERE a.user_id = ?
                ORDER BY a.applied_at DESC
            ");
            $stmt->execute([$user_id]);
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $message = "Chỉ có thể xóa khi trạng thái là 'chờ xác nhận' hoặc 'không xác nhận'!";
        }
    }
} catch (PDOException $e) {
    die("Error fetching applications: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/can_navBar.php'; ?>

<section class="container my-5">
    <h4 class="mb-4">Quản lý đơn ứng tuyển</h4>
    <?php if ($message): ?>
        <div class="alert alert-success mb-4"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if (empty($applications)): ?>
        <p>Bạn chưa nộp đơn ứng tuyển nào.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tiêu đề công việc</th>
                    <th>Công ty</th>
                    <th>Ngày nộp</th>
                    <th>Trạng thái</th>
                    <th>Ghi chú</th>
                    <th>CV</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($applications as $index => $app): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($app['job_title']); ?></td>
                        <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($app['applied_at'])); ?></td>
                        <td><?php echo htmlspecialchars($app['status']); ?></td>
                        <td><?php echo htmlspecialchars($app['rejected_reason'] ?? ''); ?></td>
                        <td>
                            <?php if (!empty($app['cv_path'])): ?>
                                <a href="<?php echo htmlspecialchars($app['cv_path']); ?>" target="_blank">Xem CV</a>
                            <?php else: ?>
                                Không có CV
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (in_array($app['status'], ['chờ xác nhận', 'không xác nhận'])): ?>
                                <button class="btn btn-sm btn-warning" onclick="openEditApplicationModal(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars(json_encode($app, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>')">Sửa</button>
                                <button class="btn btn-sm btn-danger" onclick="openDeleteApplicationModal(<?php echo $app['application_id']; ?>)">Xóa</button>
                            <?php else: ?>
                                <span class="text-muted">Không thể sửa/xóa</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Edit Application Modal -->
    <div id="editApplicationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditApplicationModal()">×</span>
            <h4 class="mb-3">Sửa thông tin ứng tuyển</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="application_id" id="editApplicationId">
                <input type="hidden" name="current_cv_path" id="editCurrentCvPath">
                <div class="form-group mb-3">
                    <label>Họ tên:</label>
                    <input type="text" name="full_name" id="editFullName" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Số điện thoại:</label>
                    <input type="text" name="SDT" id="editSDT" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Email:</label>
                    <input type="email" name="email" id="editEmail" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>CV hiện tại:</label>
                    <div id="editCvPathPreview"></div>
                    <label>CV mới (nếu muốn thay đổi):</label>
                    <input type="file" name="cv" class="form-control">
                </div>
                <button type="submit" name="update_application" class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>

    <!-- Delete Application Modal -->
    <div id="deleteApplicationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteApplicationModal()">×</span>
            <h4 class="mb-3">Xóa đơn ứng tuyển</h4>
            <p>Bạn có chắc chắn muốn xóa đơn ứng tuyển này không?</p>
            <form method="POST">
                <input type="hidden" name="delete_application" value="1">
                <input type="hidden" name="application_id" id="deleteApplicationId">
                <button type="submit" class="btn btn-danger">Xóa</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteApplicationModal()">Hủy</button>
            </form>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
    // Edit Application Modal
    function openEditApplicationModal(application_id, application_data) {
        const app = JSON.parse(application_data);
        document.getElementById('editApplicationId').value = application_id;
        document.getElementById('editCurrentCvPath').value = app.cv_path || '';
        document.getElementById('editFullName').value = app.full_name;
        document.getElementById('editSDT').value = app.SDT || '';
        document.getElementById('editEmail').value = app.email || '';
        const cvPreview = document.getElementById('editCvPathPreview');
        if (app.cv_path) {
            cvPreview.innerHTML = `<a href="${app.cv_path}" target="_blank">Xem CV hiện tại</a>`;
        } else {
            cvPreview.innerHTML = 'Không có CV';
        }
        document.getElementById('editApplicationModal').style.display = 'flex';
    }

    function closeEditApplicationModal() {
        document.getElementById('editApplicationModal').style.display = 'none';
    }

    // Delete Application Modal
    function openDeleteApplicationModal(application_id) {
        document.getElementById('deleteApplicationId').value = application_id;
        document.getElementById('deleteApplicationModal').style.display = 'flex';
    }

    function closeDeleteApplicationModal() {
        document.getElementById('deleteApplicationModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const editModal = document.getElementById('editApplicationModal');
        const deleteModal = document.getElementById('deleteApplicationModal');
        if (event.target === editModal) {
            editModal.style.display = 'none';
        }
        if (event.target === deleteModal) {
            deleteModal.style.display = 'none';
        }
    }
</script>

<style>
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

    .btn-warning {
        background-color: #ffc107;
        border-color: #ffc107;
    }

    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
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

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        border-radius: 4px;
        padding: 10px;
    }

    .text-muted {
        color: #6c757d;
    }
</style>