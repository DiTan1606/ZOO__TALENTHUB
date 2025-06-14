<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    // 1. Thống kê người dùng
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_users = array_sum(array_column($user_stats, 'count'));

    // 2. Thống kê công ty theo lĩnh vực
    $stmt = $pdo->query("SELECT industry, COUNT(*) as count FROM company GROUP BY industry");
    $company_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_companies = array_sum(array_column($company_stats, 'count'));

    // 3. Thống kê hồ sơ tuyển dụng (jobs) theo công ty
    $stmt = $pdo->query("SELECT c.company_name, COUNT(j.job_id) as job_count 
                         FROM jobs j 
                         LEFT JOIN company c ON j.company_id = c.company_id 
                         GROUP BY c.company_id, c.company_name");
    $job_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_jobs = array_sum(array_column($job_stats, 'job_count'));

    // 4. Thống kê hồ sơ ứng tuyển (applications) theo ứng viên
    $stmt = $pdo->query("SELECT full_name, COUNT(*) as app_count 
                         FROM applications 
                         GROUP BY full_name");
    $application_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_applications = array_sum(array_column($application_stats, 'app_count'));

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/admin_navBar.php'; ?>

<section class="container my-5">
    <h2 class="mb-4 text-center">Thống kê hệ thống</h2>

    <!-- Thống kê người dùng -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Thống kê người dùng</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">Tổng số người dùng: <strong><?php echo $total_users; ?></strong></p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Vai trò</th>
                            <th>Số lượng</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $roles = [
                            'admin' => 'Quản trị viên',
                            'staff_candidate' => 'Nhân viên phụ trách hồ sơ ứng viên',
                            'staff_recruitment' => 'Nhân viên phụ trách hồ sơ tuyển dụng',
                            'recruiter' => 'Nhà tuyển dụng',
                            'candidate' => 'Ứng viên'
                        ];
                        foreach ($roles as $role_key => $role_name): 
                            $count = 0;
                            foreach ($user_stats as $stat) {
                                if ($stat['role'] === $role_key) {
                                    $count = $stat['count'];
                                    break;
                                }
                            }
                            $percentage = $total_users > 0 ? round(($count / $total_users) * 100, 2) : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($role_name); ?></td>
                                <td><?php echo $count; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-primary" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Thống kê công ty -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0">Thống kê công ty</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">Tổng số công ty: <strong><?php echo $total_companies; ?></strong></p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Lĩnh vực</th>
                            <th>Số lượng</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($company_stats as $stat): 
                            $percentage = $total_companies > 0 ? round(($stat['count'] / $total_companies) * 100, 2) : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['industry']); ?></td>
                                <td><?php echo $stat['count']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Thống kê hồ sơ tuyển dụng -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-info text-white">
            <h4 class="mb-0">Thống kê hồ sơ tuyển dụng</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">Tổng số công việc: <strong><?php echo $total_jobs; ?></strong></p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Công ty</th>
                            <th>Số lượng công việc</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($job_stats as $stat): 
                            $percentage = $total_jobs > 0 ? round(($stat['job_count'] / $total_jobs) * 100, 2) : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['company_name'] ?? 'Không xác định'); ?></td>
                                <td><?php echo $stat['job_count']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-info" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Thống kê hồ sơ ứng tuyển -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-warning text-white">
            <h4 class="mb-0">Thống kê hồ sơ ứng tuyển</h4>
        </div>
        <div class="card-body">
            <p class="text-muted">Tổng số hồ sơ ứng tuyển: <strong><?php echo $total_applications; ?></strong></p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Ứng viên</th>
                            <th>Số lượng hồ sơ</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($application_stats as $stat): 
                            $percentage = $total_applications > 0 ? round(($stat['app_count'] / $total_applications) * 100, 2) : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['full_name']); ?></td>
                                <td><?php echo $stat['app_count']; ?></td>
                                <td>
                                    <div class="progress">
                                        <div class="progress-bar bg-warning" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%;" 
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                            <?php echo $percentage; ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<style>
    .card {
        border: none;
        border-radius: 10px;
        transition: transform 0.2s;
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .card-header {
        border-bottom: none;
        border-radius: 10px 10px 0 0;
        padding: 15px;
    }

    .card-body {
        padding: 20px;
    }

    .table {
        margin-bottom: 0;
    }

    .table th, .table td {
        vertical-align: middle;
    }

    .progress {
        height: 20px;
        border-radius: 5px;
        background-color: #f1f1f1;
    }

    .progress-bar {
        transition: width 0.3s ease-in-out;
    }

    .text-center {
        text-align: center;
    }

    .text-muted {
        color: #6c757d;
    }
</style>