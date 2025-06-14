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
    // Fetch tests for this candidate
    $stmt_tests = $pdo->prepare("
        SELECT t.test_id, t.application_id, t.test_content, t.deadline, t.created_at, t.test_answer, 
               j.job_title, c.company_name
        FROM tests t
        JOIN applications a ON t.application_id = a.application_id
        JOIN jobs j ON a.job_id = j.job_id
        JOIN company c ON j.company_id = c.company_id
        WHERE a.user_id = ?
        ORDER BY t.deadline DESC
    ");
    $stmt_tests->execute([$user_id]);
    $tests = $stmt_tests->fetchAll(PDO::FETCH_ASSOC);

    // Handle submission or update of test answer
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
        $test_id = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
        $current_time = date('Y-m-d H:i:s');
        
        if ($test_id > 0) {
            $stmt_current = $pdo->prepare("SELECT deadline, test_answer FROM tests WHERE test_id = ?");
            $stmt_current->execute([$test_id]);
            $test = $stmt_current->fetch(PDO::FETCH_ASSOC);

            if (strtotime($current_time) > strtotime($test['deadline'])) {
                $message = "Hạn nộp đã hết. Không thể nộp hoặc chỉnh sửa bài làm!";
            } else {
                if (!empty($_FILES['test_answer']['name'])) {
                    $upload_dir = 'uploads/tests/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $file_tmp = $_FILES['test_answer']['tmp_name'];
                    $file_name = basename($_FILES['test_answer']['name']);
                    $file_path = $upload_dir . time() . '_' . $file_name;

                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Delete old file if exists
                        if ($test['test_answer'] && file_exists($test['test_answer'])) {
                            unlink($test['test_answer']);
                        }
                        $stmt_update = $pdo->prepare("UPDATE tests SET test_answer = ? WHERE test_id = ?");
                        $stmt_update->execute([$file_path, $test_id]);
                        $message = "Nộp bài làm thành công!";
                    } else {
                        $message = "Lỗi khi upload bài làm!";
                    }
                } else {
                    $message = "Vui lòng chọn file bài làm để nộp!";
                }
            }
            header("Location: tests.php");
            exit();
        }
    }
} catch (PDOException $e) {
    die("Error fetching tests: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/can_navBar.php'; ?>

<!-- Tests Section -->
<section class="container my-5">
    <h2 class="mb-4">Bài kiểm tra của bạn</h2>
    <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'thành công') !== false ? 'success' : 'danger'; ?> mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if (empty($tests)): ?>
        <p>Bạn chưa được giao bài kiểm tra nào.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Công việc</th>
                    <th>Công ty</th>
                    <th>Đề bài</th>
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
                        <td><?php echo htmlspecialchars($test['job_title']); ?></td>
                        <td><?php echo htmlspecialchars($test['company_name']); ?></td>
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
                            <?php
                            $current_time = date('Y-m-d H:i:s');
                            $is_editable = (strtotime($current_time) <= strtotime($test['deadline']));
                            ?>
                            <form method="POST" enctype="multipart/form-data" style="display:inline;">
                                <input type="hidden" name="test_id" value="<?php echo $test['test_id']; ?>">
                                <input type="file" name="test_answer" class="form-control-file mb-2" style="height: fit-content;" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                <button type="submit" name="submit_test" class="btn btn-sm btn-primary" <?php echo !$is_editable ? 'disabled' : ''; ?>>Lưu</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php include 'includes/footer.php'; ?>

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

    .btn-primary {
        font-size: 10px;
        background-color: #00b14f;
        border-color: #00b14f;
    }

    .form-control-file {
        border-radius: 3px;
    }

    .form-group {
        margin-bottom: 1rem;
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

    button:disabled {
        background-color: #ccc;
        border-color: #ccc;
        cursor: not-allowed;
    }
</style>