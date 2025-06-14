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

    // Fetch interviews for this company
    $stmt_interviews = $pdo->prepare("
        SELECT i.interview_id, i.application_id, i.interview_date, i.location, i.meeting_link, i.created_at, 
               a.full_name, a.email, j.job_title
        FROM interviews i
        JOIN applications a ON i.application_id = a.application_id
        JOIN jobs j ON a.job_id = j.job_id
        WHERE j.company_id = ?
        ORDER BY i.interview_date DESC
    ");
    $stmt_interviews->execute([$company_id]);
    $interviews = $stmt_interviews->fetchAll(PDO::FETCH_ASSOC);

    // Handle update for interview
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_interview'])) {
        $interview_id = isset($_POST['interview_id']) ? (int)$_POST['interview_id'] : 0;
        $interview_date = $_POST['interview_date'];
        $location = $_POST['location'] ?: NULL;
        $meeting_link = $_POST['meeting_link'] ?: NULL;

        if ($interview_id > 0 && !empty($interview_date)) {
            $stmt_update = $pdo->prepare("
                UPDATE interviews 
                SET interview_date = ?, location = ?, meeting_link = ? 
                WHERE interview_id = ?
            ");
            $stmt_update->execute([$interview_date, $location, $meeting_link, $interview_id]);
            $message = "Cập nhật lịch phỏng vấn thành công!";
            header("Location: interview_management.php");
            exit();
        } else {
            $message = "Cập nhật thất bại. Vui lòng thử lại!";
        }
    }

    // Handle delete for interview
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_interview'])) {
        $interview_id = isset($_POST['interview_id']) ? (int)$_POST['interview_id'] : 0;
        if ($interview_id > 0) {
            $stmt_delete = $pdo->prepare("DELETE FROM interviews WHERE interview_id = ?");
            $stmt_delete->execute([$interview_id]);
            $message = "Xóa lịch phỏng vấn thành công!";
            header("Location: interview_management.php");
            exit();
        } else {
            $message = "Xóa thất bại. Vui lòng thử lại!";
        }
    }
} catch (PDOException $e) {
    die("Error fetching interviews: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/rec_navBar.php'; ?>

<!-- Interviews Management Section -->
<section class="container my-5">
    <h2 class="mb-4">Quản lý lịch phỏng vấn</h2>
    <?php if ($message): ?>
        <div class="alert alert-<?php echo strpos($message, 'thành công') !== false ? 'success' : 'danger'; ?> mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    <?php if (empty($interviews)): ?>
        <p>Chưa có lịch phỏng vấn nào được lên lịch.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Ứng viên</th>
                    <th>Email</th>
                    <th>Công việc</th>
                    <th>Ngày phỏng vấn</th>
                    <th>Địa điểm</th>
                    <th>Link họp</th>
                    <th>Ngày tạo</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interviews as $index => $interview): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($interview['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($interview['email'] ?? 'Chưa cung cấp'); ?></td>
                        <td><?php echo htmlspecialchars($interview['job_title']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($interview['interview_date'])); ?></td>
                        <td><?php echo htmlspecialchars($interview['location'] ?? 'Không có'); ?></td>
                        <td>
                            <?php if ($interview['meeting_link']): ?>
                                <a href="<?php echo htmlspecialchars($interview['meeting_link']); ?>" target="_blank">Tham gia</a>
                            <?php else: ?>
                                Không có
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y H:i', strtotime($interview['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="openEditModal(<?php echo $interview['interview_id']; ?>, '<?php echo htmlspecialchars(addslashes(json_encode($interview))); ?>')">Sửa</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa?');">
                                <input type="hidden" name="interview_id" value="<?php echo $interview['interview_id']; ?>">
                                <button type="submit" name="delete_interview" class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- Edit Interview Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">×</span>
        <h4 class="mb-3">Sửa lịch phỏng vấn</h4>
        <form method="POST">
            <input type="hidden" name="interview_id" id="editInterviewId">
            <div class="form-group mb-3">
                <label>Ngày phỏng vấn:</label>
                <input type="datetime-local" name="interview_date" id="editInterviewDate" class="form-control" style="height: fit-content" required>
            </div>
            <div class="form-group mb-3">
                <label>Địa điểm:</label>
                <input type="text" name="location" id="editLocation" class="form-control" style="height: fit-content" placeholder="Nhập địa điểm (nếu có)">
            </div>
            <div class="form-group mb-3">
                <label>Link họp (nếu phỏng vấn online):</label>
                <input type="text" name="meeting_link" id="editMeetingLink" class="form-control" style="height: fit-content" placeholder="Nhập link họp (nếu có)">
            </div>
            <button type="submit" name="update_interview" class="btn btn-primary">Lưu</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function openEditModal(interview_id, interview_data) {
        const data = JSON.parse(interview_data);
        document.getElementById('editInterviewId').value = interview_id;
        document.getElementById('editInterviewDate').value = data.interview_date.replace(' ', 'T');
        document.getElementById('editLocation').value = data.location || '';
        document.getElementById('editMeetingLink').value = data.meeting_link || '';
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
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
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
        color:rgb(32, 35, 33);
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