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
    // Fetch interviews for this candidate
    $stmt_interviews = $pdo->prepare("
        SELECT i.interview_id, i.interview_date, i.location, i.meeting_link, i.created_at, 
               j.job_title, c.company_name
        FROM interviews i
        JOIN applications a ON i.application_id = a.application_id
        JOIN jobs j ON a.job_id = j.job_id
        JOIN company c ON j.company_id = c.company_id
        WHERE a.user_id = ?
        ORDER BY i.interview_date DESC
    ");
    $stmt_interviews->execute([$user_id]);
    $interviews = $stmt_interviews->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching interviews: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/can_navBar.php'; ?>

<!-- Interviews Section -->
<section class="container my-5">
    <h2 class="mb-4">Lịch phỏng vấn của bạn</h2>
    <?php if (empty($interviews)): ?>
        <p>Bạn chưa có lịch phỏng vấn nào.</p>
    <?php else: ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Công việc</th>
                    <th>Công ty</th>
                    <th>Ngày phỏng vấn</th>
                    <th>Địa điểm</th>
                    <th>Link họp</th>
                    <th>Ngày tạo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interviews as $index => $interview): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($interview['job_title']); ?></td>
                        <td><?php echo htmlspecialchars($interview['company_name']); ?></td>
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
</style>