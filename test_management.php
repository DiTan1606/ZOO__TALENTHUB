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

    // Fetch tests for this company
    $stmt_tests = $pdo->prepare("
        SELECT t.test_id, t.application_id, t.test_content, t.deadline, t.created_at, t.test_answer, 
               a.full_name, a.email, j.job_title
        FROM tests t
        JOIN applications a ON t.application_id = a.application_id
        JOIN jobs j ON a.job_id = j.job_id
        WHERE j.company_id = ?
        ORDER BY t.deadline DESC
    ");
    $stmt_tests->execute([$company_id]);
    $tests = $stmt_tests->fetchAll(PDO::FETCH_ASSOC);

    // Handle update for test
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_test'])) {
        $test_id = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
        $deadline = $_POST['deadline'];

        if ($test_id > 0 && !empty($deadline)) {
            // Fetch current test to get old file path
            $stmt_current = $pdo->prepare("SELECT test_content FROM tests WHERE test_id = ?");
            $stmt_current->execute([$test_id]);
            $current_test = $stmt_current->fetch(PDO::FETCH_ASSOC);
            $old_file_path = $current_test['test_content'];

            $update_data = ['deadline' => $deadline];
            $params = [$deadline];

            // Handle file upload if a new file is provided
            if (!empty($_FILES['test_content']['name'])) {
                $upload_dir = 'uploads/tests/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_tmp = $_FILES['test_content']['tmp_name'];
                $file_name = basename($_FILES['test_content']['name']);
                $file_path = $upload_dir . time() . '_' . $file_name; // Add timestamp to avoid overwriting

                if (move_uploaded_file($file_tmp, $file_path)) {
                    // Delete old file if exists
                    if ($old_file_path && file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                    $update_data['test_content'] = $file_path;
                    $params[] = $file_path;
                } else {
                    $message = "Lỗi khi upload file đề bài!";
                    header("Location: test_management.php");
                    exit();
                }
            }

            // Update the test record
            $set_clause = implode(', ', array_map(fn($key) => "$key = ?", array_keys($update_data)));
            $params[] = $test_id;
            $stmt_update = $pdo->prepare("UPDATE tests SET $set_clause WHERE test_id = ?");
            $stmt_update->execute($params);

            $message = "Cập nhật bài kiểm tra thành công!";
            header("Location: test_management.php");
            exit();
        } else {
            $message = "Cập nhật thất bại. Vui lòng thử lại!";
        }
    }

    // Handle delete for test
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_test'])) {
        $test_id = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
        if ($test_id > 0) {
            // Fetch test to delete associated file
            $stmt_current = $pdo->prepare("SELECT test_content, test_answer FROM tests WHERE test_id = ?");
            $stmt_current->execute([$test_id]);
            $test = $stmt_current->fetch(PDO::FETCH_ASSOC);

            // Delete associated files
            if ($test['test_content'] && file_exists($test['test_content'])) {
                unlink($test['test_content']);
            }
            if ($test['test_answer'] && file_exists($test['test_answer'])) {
                unlink($test['test_answer']);
            }

            $stmt_delete = $pdo->prepare("DELETE FROM tests WHERE test_id = ?");
            $stmt_delete->execute([$test_id]);
            $message = "Xóa bài kiểm tra thành công!";
            header("Location: test_management.php");
            exit();
        } else {
            $message = "Xóa thất bại. Vui lòng thử lại!";
        }
    }
} catch (PDOException $e) {
    die("Error fetching tests: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/rec_navBar.php'; ?>

<!-- Tests Management Section -->
<section class="container my-5">
    <h2 class="mb-4">Quản lý bài kiểm tra</h2>
    <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'thành công') !== false ? 'success' : 'danger'; ?> mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if (empty($tests)): ?>
        <p>Chưa có bài kiểm tra nào được giao.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Ứng viên</th>
                    <th>Email</th>
                    <th>Công việc</th>
                    <th>Nội dung kiểm tra</th>
                    <th>Hạn nộp</th>
                    <th>Ngày tạo</th>
                    <th>Bài làm</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $index => $test): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($test['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($test['email'] ?? 'Chưa cung cấp'); ?></td>
                        <td><?php echo htmlspecialchars($test['job_title']); ?></td>
                        <td>
                            <?php if ($test['test_content'] && file_exists($test['test_content'])): ?>
                                <a href="<?php echo htmlspecialchars($test['test_content']); ?>" target="_blank" download>Đề bài</a>
                            <?php else: ?>
                                Chưa upload
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($test['deadline'])); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($test['created_at'])); ?></td>
                        <td>
                            <?php if ($test['test_answer'] && file_exists($test['test_answer'])): ?>
                                <a href="<?php echo htmlspecialchars($test['test_answer']); ?>" target="_blank" download>Bài làm</a>
                            <?php else: ?>
                                Chưa nộp
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="openEditModal(<?php echo $test['test_id']; ?>, '<?php echo htmlspecialchars(addslashes(json_encode($test))); ?>')">Sửa</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?');">
                                <input type="hidden" name="test_id" value="<?php echo $test['test_id']; ?>">
                                <button type="submit" name="delete_test" class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- Edit Test Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">×</span>
        <h4 class="mb-3">Sửa bài kiểm tra</h4>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="test_id" id="editTestId">
            <div class="form-group mb-3">
                <label>Đề bài (upload lại nếu muốn thay đổi):</label>
                <input type="file" name="test_content" class="form-control" style="height: fit-content">
            </div>
            <div class="form-group mb-3">
                <label>Hạn nộp:</label>
                <input type="datetime-local" name="deadline" id="editDeadline" class="form-control" style="height: fit-content" required>
            </div>
            <button type="submit" name="update_test" class="btn btn-primary">Lưu</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function openEditModal(test_id, test_data) {
        const data = JSON.parse(test_data);
        document.getElementById('editTestId').value = test_id;
        document.getElementById('editDeadline').value = data.deadline.replace(' ', 'T');
        document.getElementById('editModal').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const editModal = document.getElementById('editModal');
        if (event.target === editModal) {
            editModal.style.display = 'none';
        }
    }
</script>

<style>
    .table {
        font-size: 14px;
        width: 100%;
        border-collapse: collapse;
    }

    .table th, .table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
    }

    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: #f9f9f9;
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
        font-size: 10px;
        background-color: #00b14f;
        border-color: #00b14f;
    }

    .btn-danger {
        font-size: 10px;
        background-color: #dc3545;
        border-color: #dc3545;
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