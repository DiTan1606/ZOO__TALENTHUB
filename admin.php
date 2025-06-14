<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';

try {
    // Handle search
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search_type = isset($_GET['search_type']) ? $_GET['search_type'] : 'users';

    // Handle CRUD operations
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_user'])) {
            $name = $_POST['name'];
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'];
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role]);
            $message = "Thêm người dùng thành công!";
        } elseif (isset($_POST['update_user'])) {
            $user_id = $_POST['user_id'];
            $name = $_POST['name'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE user_id = ?");
            $stmt->execute([$name, $email, $role, $user_id]);
            $message = "Cập nhật người dùng thành công!";
        } elseif (isset($_POST['delete_user'])) {
            $user_id = $_POST['user_id'];
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM applications WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $pdo->commit();
            $message = "Xóa người dùng thành công!";
        }

        // Handle company CRUD
        if (isset($_POST['update_company'])) {
            $company_id = $_POST['company_id'];
            $company_name = $_POST['company_name'];
            $company_size = $_POST['company_size'];
            $industry = $_POST['industry'];
            $description = $_POST['description'];
            $house_number = $_POST['house_number'];
            $street = $_POST['street'];
            $ward = $_POST['ward'];
            $district = $_POST['district'];
            $province = $_POST['province'];
            $village = $_POST['village'];
            $logo = $_POST['current_logo'];
            if (!empty($_FILES['logo']['name'])) {
                $upload_dir = 'uploads/logos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_tmp = $_FILES['logo']['tmp_name'];
                $file_name = basename($_FILES['logo']['name']);
                $logo = $upload_dir . $file_name;
                move_uploaded_file($file_tmp, $logo);
            }
            $stmt = $pdo->prepare("UPDATE company SET company_name = ?, company_size = ?, industry = ?, description = ?, logo = ?, house_number = ?, street = ?, ward = ?, district = ?, province = ?, village = ? WHERE company_id = ?");
            $stmt->execute([$company_name, $company_size, $industry, $description, $logo, $house_number, $street, $ward, $district, $province, $village, $company_id]);
            $message = "Cập nhật công ty thành công!";
        } elseif (isset($_POST['delete_company'])) {
            $company_id = $_POST['company_id'];
            $stmt = $pdo->prepare("DELETE FROM company WHERE company_id = ?");
            $stmt->execute([$company_id]);
            $message = "Xóa công ty thành công!";
        }

        // Handle jobs CRUD
        if (isset($_POST['add_job'])) {
            $company_id = $_POST['company_id'];
            $job_title = $_POST['job_title'];
            $experience_required = $_POST['experience_required'];
            $work_type = $_POST['work_type'];
            $deadline = $_POST['deadline'];
            $description = $_POST['description'];
            $candidate_requirements = $_POST['candidate_requirements'];
            $benefits = $_POST['benefits'];
            $working_hours = $_POST['working_hours'];
            $work_location = $_POST['work_location'];
            $salary = $_POST['salary'];
            $status = $_POST['status'];
            $stmt = $pdo->prepare("INSERT INTO jobs (company_id, job_title, experience_required, work_type, deadline, description, candidate_requirements, benefits, working_hours, work_location, salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$company_id, $job_title, $experience_required, $work_type, $deadline, $description, $candidate_requirements, $benefits, $working_hours, $work_location, $salary, $status]);
            $message = "Thêm công việc thành công!";
        } elseif (isset($_POST['update_job'])) {
            $job_id = $_POST['job_id'];
            $company_id = $_POST['company_id'];
            $job_title = $_POST['job_title'];
            $experience_required = $_POST['experience_required'];
            $work_type = $_POST['work_type'];
            $deadline = $_POST['deadline'];
            $description = $_POST['description'];
            $candidate_requirements = $_POST['candidate_requirements'];
            $benefits = $_POST['benefits'];
            $working_hours = $_POST['working_hours'];
            $work_location = $_POST['work_location'];
            $salary = $_POST['salary'];
            $status = $_POST['status'];
            $stmt = $pdo->prepare("UPDATE jobs SET company_id = ?, job_title = ?, experience_required = ?, work_type = ?, deadline = ?, description = ?, candidate_requirements = ?, benefits = ?, working_hours = ?, work_location = ?, salary = ?, status = ? WHERE job_id = ?");
            $stmt->execute([$company_id, $job_title, $experience_required, $work_type, $deadline, $description, $candidate_requirements, $benefits, $working_hours, $work_location, $salary, $status, $job_id]);
            $message = "Cập nhật công việc thành công!";
        } elseif (isset($_POST['delete_job'])) {
            $job_id = $_POST['job_id'];
            $stmt = $pdo->prepare("DELETE FROM jobs WHERE job_id = ?");
            $stmt->execute([$job_id]);
            $message = "Xóa công việc thành công!";
        }

        // Handle applications CRUD
        if (isset($_POST['update_application'])) {
            $application_id = $_POST['application_id'];
            $full_name = $_POST['full_name'];
            $SDT = $_POST['SDT'];
            $email = $_POST['email'];
            $status = $_POST['status'];
            $rejected_reason = (in_array($status, ['không xác nhận', 'không được duyệt', 'không trúng tuyển'])) ? ($_POST['rejected_reason'] ?: NULL) : NULL;
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
            $stmt = $pdo->prepare("UPDATE applications SET full_name = ?, SDT = ?, email = ?, cv_path = ?, status = ?, rejected_reason = ? WHERE application_id = ?");
            $stmt->execute([$full_name, $SDT, $email, $cv_path, $status, $rejected_reason, $application_id]);
            $message = "Cập nhật hồ sơ ứng viên thành công!";
        } elseif (isset($_POST['delete_application'])) {
            $application_id = $_POST['application_id'];
            $stmt = $pdo->prepare("DELETE FROM interviews WHERE application_id = ?");
            $stmt->execute([$application_id]);
            $stmt = $pdo->prepare("DELETE FROM tests WHERE application_id = ?");
            $stmt->execute([$application_id]);
            $stmt = $pdo->prepare("DELETE FROM applications WHERE application_id = ?");
            $stmt->execute([$application_id]);
            $message = "Xóa hồ sơ ứng viên thành công!";
        }
    }

    // Fetch data based on search type
    $data = [];
    switch ($search_type) {
        case 'users':
            $data = [
                'admin' => [],
                'staff_candidate' => [],
                'staff_recruitment' => [],
                'recruiter' => [],
                'candidate' => []
            ];
            foreach (array_keys($data) as $role) {
                $sql = "SELECT user_id, name, email, role, created_at FROM users WHERE role = ? AND (name LIKE ? OR email LIKE ?)";
                $params = [$role, "%$search%", "%$search%"];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $data[$role] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
        case 'company':
            $sql = "SELECT * FROM company WHERE company_name LIKE ?";
            $params = ["%$search%"];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'jobs':
            $sql = "SELECT j.*, c.company_name FROM jobs j LEFT JOIN company c ON j.company_id = c.company_id WHERE j.job_title LIKE ?";
            $params = ["%$search%"];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($data as &$job) {
                $stmt = $pdo->prepare("SELECT a.application_id, a.job_id, a.full_name, a.SDT, a.email, a.cv_path, a.status, a.applied_at, a.rejected_reason FROM applications a WHERE a.job_id = ?");
                $stmt->execute([$job['job_id']]);
                $job['applications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($job['applications'] as &$application) {
                    $stmt = $pdo->prepare("SELECT interview_id, interview_date, location, meeting_link FROM interviews WHERE application_id = ?");
                    $stmt->execute([$application['application_id']]);
                    $application['interviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $stmt = $pdo->prepare("SELECT test_id, test_content, deadline, test_answer FROM tests WHERE application_id = ?");
                    $stmt->execute([$application['application_id']]);
                    $application['tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            break;
        case 'applications':
            $sql = "SELECT a.*, j.job_title, c.company_name FROM applications a LEFT JOIN jobs j ON a.job_id = j.job_id LEFT JOIN company c ON j.company_id = c.company_id WHERE a.full_name LIKE ? OR j.job_title LIKE ?";
            $params = ["%$search%", "%$search%"];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($data as &$application) {
                $stmt = $pdo->prepare("SELECT interview_id, interview_date, location, meeting_link FROM interviews WHERE application_id = ?");
                $stmt->execute([$application['application_id']]);
                $application['interviews'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("SELECT test_id, test_content, deadline, test_answer FROM tests WHERE application_id = ?");
                $stmt->execute([$application['application_id']]);
                $application['tests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>
<?php include 'includes/admin_navBar.php'; ?>

<section class="container my-5">
    <h2 class="mb-4">Quản lý hệ thống</h2>

    <?php if ($message): ?>
        <div class="alert alert-success mb-4"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <!-- Search and Filter -->
    <div class="mb-4">
        <form method="GET" class="d-flex">
            <select name="search_type" class="form-control me-2" style="height: fit-content" onchange="this.form.submit()">
                <option value="users" <?php echo $search_type === 'users' ? 'selected' : ''; ?>>Quản lý thông tin người dùng</option>
                <option value="company" <?php echo $search_type === 'company' ? 'selected' : ''; ?>>Quản lý thông tin công ty</option>
                <option value="jobs" <?php echo $search_type === 'jobs' ? 'selected' : ''; ?>>Quản lý hồ sơ tuyển dụng</option>
                <option value="applications" <?php echo $search_type === 'applications' ? 'selected' : ''; ?>>Quản lý hồ sơ ứng tuyển</option>
            </select>
            <input type="text" name="search" class="form-control me-2" placeholder="Tìm kiếm..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Tìm</button>
        </form>
    </div>

    <!-- Management Table -->
    <?php if (!empty($data)): ?>
        <?php if ($search_type === 'users'): ?>
            <?php $roles = [
                'admin' => 'Quản trị viên',
                'staff_candidate' => 'Nhân viên phụ trách hồ sơ ứng viên',
                'staff_recruitment' => 'Nhân viên phụ trách hồ sơ tuyển dụng',
                'recruiter' => 'Nhà tuyển dụng',
                'candidate' => 'Ứng viên'
            ]; ?>
            <div class="mb-4">
                <?php if ($search_type === 'users'): ?>
                    <button class="btn btn-success" onclick="openAddUserModal()">Thêm người dùng</button>
                <?php endif; ?>
            </div>
            <?php foreach ($roles as $role_key => $role_name): ?>
                <h3 class="mb-3"><?php echo $role_name; ?></h3>
                <!-- Buttons to open modals -->
                <?php if (!empty($data[$role_key])): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Tên</th>
                                    <th>Email</th>
                                    <th>Vai trò</th>
                                    <th>Ngày tạo</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data[$role_key] as $index => $item): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['email']); ?></td>
                                        <td><?php echo htmlspecialchars($item['role']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="openEditUserModal(<?php echo $item['user_id']; ?>, '<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>')">Sửa</button>
                                            <button class="btn btn-sm btn-danger" onclick="openDeleteUserModal(<?php echo $item['user_id']; ?>)">Xóa</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>Không có dữ liệu để hiển thị.</p>
                <?php endif; ?>
                <hr class="my-4">
            <?php endforeach; ?>
        <?php elseif ($search_type === 'company'): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên công ty</th>
                            <th>Ngành</th>
                            <th>Logo</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="#" class="company-title-link" onclick="openCompanyDetailsModal(<?php echo $item['company_id']; ?>, '<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>')">
                                        <?php echo htmlspecialchars($item['company_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($item['industry']); ?></td>
                                <td>
                                    <?php if (!empty($item['logo'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['logo']); ?>" alt="Logo" style="width: 50px; height: 50px; object-fit: contain;">
                                    <?php else: ?>
                                        Không có logo
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="openEditCompanyModal(<?php echo $item['company_id']; ?>, '<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>')">Sửa</button>
                                    <button class="btn btn-sm btn-danger" onclick="openDeleteCompanyModal(<?php echo $item['company_id']; ?>)">Xóa</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($search_type === 'jobs'): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tiêu đề</th>
                            <th>Công ty</th>
                            <th>Hạn nộp</th>
                            <th>Trạng thái</th>
                            <th>Ghi chú</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <a href="#" class="job-title-link" onclick="openJobDetailsModal(<?php echo $item['job_id']; ?>, '<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>')">
                                        <?php echo htmlspecialchars($item['job_title']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($item['company_name'] ?? ''); ?></td>
                                <td><?php echo $item['deadline'] ? date('d/m/Y', strtotime($item['deadline'])) : ''; ?></td>
                                <td><?php echo htmlspecialchars($item['status'] ?? 'Chưa xác định'); ?></td>
                                <td><?php echo htmlspecialchars($item['rejected_reason'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="openEditJobModal(<?php echo $item['job_id']; ?>, '<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>')">Sửa</button>
                                    <button class="btn btn-sm btn-danger" onclick="openDeleteJobModal(<?php echo $item['job_id']; ?>)">Xóa</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($search_type === 'applications'): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Họ tên</th>
                            <th>Tiêu đề công việc</th>
                            <th>Công ty</th>
                            <th>Ngày nộp</th>
                            <th>Trạng thái</th>
                            <th>Ghi chú</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $index => $item): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($item['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['job_title'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($item['company_name'] ?? ''); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($item['applied_at'])); ?></td>
                                <td><?php echo htmlspecialchars($item['status']); ?></td>
                                <td><?php echo htmlspecialchars($item['rejected_reason'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="openEditApplicationModal(<?php echo $item['application_id']; ?>, '<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>')">Sửa</button>
                                    <button class="btn btn-sm btn-danger" onclick="openDeleteJobModal(<?php echo $item['application_id']; ?>)">Xóa</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p>Không có dữ liệu để hiển thị.</p>
    <?php endif; ?>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddUserModal()">×</span>
            <h4 class="mb-3">Thêm người dùng</h4>
            <form method="POST">
                <div class="form-group mb-3">
                    <label>Tên:</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Email:</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Mật khẩu:</label>
                    <input type="text" name="password" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Vai trò:</label>
                    <select name="role" class="form-control" required>
                        <option value="admin">Quản trị viên</option>
                        <option value="staff_candidate">Nhân viên phụ trách hồ sơ ứng viên</option>
                        <option value="staff_recruitment">Nhân viên phụ trách hồ sơ tuyển dụng</option>
                        <option value="recruiter">Nhà tuyển dụng</option>
                        <option value="candidate">Ứng viên</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">Thêm</button>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditUserModal()">×</span>
            <h4 class="mb-3">Sửa người dùng</h4>
            <form method="POST">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="form-group mb-3">
                    <label>Tên:</label>
                    <input type="text" name="name" id="editUserName" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Email:</label>
                    <input type="email" name="email" id="editUserEmail" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Vai trò:</label>
                    <select name="role" id="editUserRole" class="form-control" required>
                        <option value="admin">Quản trị viên</option>
                        <option value="staff_candidate">Nhân viên phụ trách hồ sơ ứng viên</option>
                        <option value="staff_recruitment">Nhân viên phụ trách hồ sơ tuyển dụng</option>
                        <option value="recruiter">Nhà tuyển dụng</option>
                        <option value="candidate">Ứng viên</option>
                    </select>
                </div>
                <button type="submit" name="update_user" class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteUserModal()">×</span>
            <h4 class="mb-3">Xóa người dùng</h4>
            <p>Bạn có chắc chắn muốn xóa người dùng này không?</p>
            <form method="POST">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="user_id" id="deleteUserId">
                <button type="submit" class="btn btn-danger">Xóa</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteUserModal()">Hủy</button>
            </form>
        </div>
    </div>

    <!-- Edit Company Modal -->
    <div id="editCompanyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditCompanyModal()">×</span>
            <h4 class="mb-3">Sửa công ty</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="company_id" id="editCompanyId">
                <input type="hidden" name="current_logo" id="editCompanyCurrentLogo">
                <div class="form-group mb-3">
                    <label>Tên công ty:</label>
                    <input type="text" name="company_name" id="editCompanyName" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Kích thước:</label>
                    <select name="company_size" id="editCompanySize" class="form-control" required>
                        <option value="1-10">1-10</option>
                        <option value="11-50">11-50</option>
                        <option value="51-200">51-200</option>
                        <option value="201-500">201-500</option>
                        <option value="501+">501+</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label>Ngành:</label>
                    <input type="text" name="industry" id="editCompanyIndustry" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Mô tả:</label>
                    <textarea name="description" id="editCompanyDescription" class="form-control"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Logo hiện tại:</label>
                    <div id="editCompanyLogoPreview"></div>
                    <label>Logo mới (nếu muốn thay đổi):</label>
                    <input type="file" name="logo" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Số nhà:</label>
                    <input type="text" name="house_number" id="editCompanyHouseNumber" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Đường:</label>
                    <input type="text" name="street" id="editCompanyStreet" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Phường/Xã:</label>
                    <input type="text" name="ward" id="editCompanyWard" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Quận/Huyện:</label>
                    <input type="text" name="district" id="editCompanyDistrict" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Tỉnh/Thành:</label>
                    <input type="text" name="province" id="editCompanyProvince" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Thôn/Bản:</label>
                    <input type="text" name="village" id="editCompanyVillage" class="form-control">
                </div>
                <button type="submit" name="update_company" class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>

    <!-- Delete Company Modal -->
    <div id="deleteCompanyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteCompanyModal()">×</span>
            <h4 class="mb-3">Xóa công ty</h4>
            <p>Bạn có chắc chắn muốn xóa công ty này không?</p>
            <form method="POST">
                <input type="hidden" name="delete_company" value="1">
                <input type="hidden" name="company_id" id="deleteCompanyId">
                <button type="submit" class="btn btn-danger">Xóa</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteCompanyModal()">Hủy</button>
            </form>
        </div>
    </div>

    <!-- Company Details Modal -->
    <div id="companyDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCompanyDetailsModal()">×</span>
            <h4 class="mb-3">Chi tiết công ty</h4>
            <div id="companyDetailsContent">
                <p><strong>Tên công ty:</strong> <span id="companyDetailName"></span></p>
                <p><strong>Kích thước:</strong> <span id="companyDetailSize"></span></p>
                <p><strong>Ngành:</strong> <span id="companyDetailIndustry"></span></p>
                <p><strong>Mô tả:</strong> <span id="companyDetailDescription"></span></p>
                <p><strong>Logo:</strong> <span id="companyDetailLogo"></span></p>
                <p><strong>Số nhà:</strong> <span id="companyDetailHouseNumber"></span></p>
                <p><strong>Đường:</strong> <span id="companyDetailStreet"></span></p>
                <p><strong>Phường/Xã:</strong> <span id="companyDetailWard"></span></p>
                <p><strong>Quận/Huyện:</strong> <span id="companyDetailDistrict"></span></p>
                <p><strong>Tỉnh/Thành:</strong> <span id="companyDetailProvince"></span></p>
                <p><strong>Thôn/Bản:</strong> <span id="companyDetailVillage"></span></p>
            </div>
        </div>
    </div>

    <!-- Edit Job Modal -->
    <div id="editJobModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditJobModal()">×</span>
            <h4 class="mb-3">Sửa công việc</h4>
            <form method="POST">
                <input type="hidden" name="job_id" id="editJobId">
                <div class="form-group mb-3">
                    <label>ID Công ty:</label>
                    <input type="number" name="company_id" id="editJobCompanyId" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Tiêu đề:</label>
                    <input type="text" name="job_title" id="editJobTitle" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Kinh nghiệm yêu cầu:</label>
                    <input type="text" name="experience_required" id="editJobExperience" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Loại công việc:</label>
                    <select name="work_type" id="editJobWorkType" class="form-control">
                        <option value="full-time">Full-time</option>
                        <option value="part-time">Part-time</option>
                        <option value="freelance">Freelance</option>
                        <option value="contract">Contract</option>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label>Hạn nộp:</label>
                    <input type="date" name="deadline" id="editJobDeadline" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Mô tả:</label>
                    <textarea name="description" id="editJobDescription" class="form-control"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Yêu cầu ứng viên:</label>
                    <textarea name="candidate_requirements" id="editJobRequirements" class="form-control"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Quyền lợi:</label>
                    <textarea name="benefits" id="editJobBenefits" class="form-control"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Thời gian làm việc:</label>
                    <textarea name="working_hours" id="editJobWorkingHours" class="form-control"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Địa điểm làm việc:</label>
                    <textarea name="work_location" id="editJobWorkLocation" class="form-control"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label>Lương:</label>
                    <input type="text" name="salary" id="editJobSalary" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Trạng thái:</label>
                    <select name="status" id="editJobStatus" class="form-control">
                        <option value="chờ duyệt">Chờ duyệt</option>
                        <option value="đã duyệt">Đã duyệt</option>
                        <option value="không duyệt">Không duyệt</option>
                    </select>
                </div>
                <button type="submit" name="update_job" class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>

    <!-- Delete Job Modal -->
    <div id="deleteJobModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteJobModal()">×</span>
            <h4 class="mb-3">Xóa công việc</h4>
            <p>Bạn có chắc chắn muốn xóa công việc này không?</p>
            <form method="POST">
                <input type="hidden" name="delete_job" value="1">
                <input type="hidden" name="job_id" id="deleteJobId">
                <button type="submit" class="btn btn-danger">Xóa</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteJobModal()">Hủy</button>
            </form>
        </div>
    </div>

    <!-- Edit Application Modal -->
    <div id="editApplicationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditApplicationModal()">×</span>
            <h4 class="mb-3">Sửa hồ sơ ứng viên</h4>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="application_id" id="editApplicationId">
                <input type="hidden" name="current_cv_path" id="editApplicationCvPath">
                <div class="form-group mb-3">
                    <label>Họ tên:</label>
                    <input type="text" name="full_name" id="editApplicationFullName" class="form-control" required>
                </div>
                <div class="form-group mb-3">
                    <label>Số điện thoại:</label>
                    <input type="text" name="SDT" id="editApplicationSDT" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Email:</label>
                    <input type="email" name="email" id="editApplicationEmail" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>CV hiện tại:</label>
                    <div id="editApplicationCvPreview"></div>
                    <label>CV mới (nếu muốn thay đổi):</label>
                    <input type="file" name="cv" class="form-control">
                </div>
                <div class="form-group mb-3">
                    <label>Trạng thái:</label>
                    <select name="status" id="editApplicationStatus" class="form-control" required>
                        <option value="chờ xác nhận">Chờ xác nhận</option>
                        <option value="không xác nhận">Không xác nhận</option>
                        <option value="đã xác nhận">Đã xác nhận</option>
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
                    <textarea name="rejected_reason" id="editApplicationRejectedReason" class="form-control" placeholder="Nhập lý do từ chối (bắt buộc nếu không xác nhận, không duyệt, hoặc không trúng tuyển)"></textarea>
                </div>
                <button type="submit" name="update_application" class="btn btn-primary">Lưu</button>
            </form>
        </div>
    </div>

    <!-- Delete Application Modal -->
    <div id="deleteApplicationModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" onclick="closeDeleteApplicationModal()">×</span>
            <h4 class="mb-3">Xóa hồ sơ ứng viên</h4>
            <p>Bạn có chắc chắn muốn xóa hồ sơ ứng viên này không?</p>
            <form method="POST">
                <input type="hidden" name="delete_application" value="1">
                <input type="hidden" name="application_id" id="deleteApplicationId">
                <button type="submit" class="btn btn-danger">Xóa</button>
                <button type="button" class="btn btn-secondary" onclick="closeDeleteApplicationModal()">Hủy</button>
            </form>
        </div>
    </div>

    <!-- Job Details Modal -->
    <div id="jobDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeJobDetailsModal()">×</span>
            <h4 class="mb-3">Chi tiết công việc</h4>
            <div id="jobDetailsContent"></div>
        </div>
    </div>


</section>

<?php include 'includes/footer.php'; ?>

<script>
// User Modals
function openAddUserModal() {
    document.getElementById('addUserModal').style.display = 'flex';
}

function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
}

function openEditUserModal(user_id, user_data) {
    const user = JSON.parse(user_data);
    document.getElementById('editUserId').value = user_id;
    document.getElementById('editUserName').value = user.name;
    document.getElementById('editUserEmail').value = user.email;
    document.getElementById('editUserRole').value = user.role;
    document.getElementById('editUserModal').style.display = 'flex';
}

function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function openDeleteUserModal(user_id) {
    document.getElementById('deleteUserId').value = user_id;
    document.getElementById('deleteUserModal').style.display = 'flex';
}

function closeDeleteUserModal() {
    document.getElementById('deleteUserModal').style.display = 'none';
}

// Company Modals
function openEditCompanyModal(company_id, company_data) {
    const company = JSON.parse(company_data);
    document.getElementById('editCompanyId').value = company_id;
    document.getElementById('editCompanyCurrentLogo').value = company.logo || '';
    document.getElementById('editCompanyName').value = company.company_name;
    document.getElementById('editCompanySize').value = company.company_size;
    document.getElementById('editCompanyIndustry').value = company.industry;
    document.getElementById('editCompanyDescription').value = company.description || '';
    const logoPreview = document.getElementById('editCompanyLogoPreview');
    if (company.logo) {
        logoPreview.innerHTML = `<img src="${company.logo}" alt="Logo" style="width: 50px; height: 50px; object-fit: contain;">`;
    } else {
        logoPreview.innerHTML = 'Không có logo';
    }
    document.getElementById('editCompanyHouseNumber').value = company.house_number || '';
    document.getElementById('editCompanyStreet').value = company.street || '';
    document.getElementById('editCompanyWard').value = company.ward || '';
    document.getElementById('editCompanyDistrict').value = company.district || '';
    document.getElementById('editCompanyProvince').value = company.province || '';
    document.getElementById('editCompanyVillage').value = company.village || '';
    document.getElementById('editCompanyModal').style.display = 'flex';
}

function closeEditCompanyModal() {
    document.getElementById('editCompanyModal').style.display = 'none';
}

function openDeleteCompanyModal(company_id) {
    document.getElementById('deleteCompanyId').value = company_id;
    document.getElementById('deleteCompanyModal').style.display = 'flex';
}

function closeDeleteCompanyModal() {
    document.getElementById('deleteCompanyModal').style.display = 'none';
}

function openCompanyDetailsModal(company_id, company_data) {
    const company = JSON.parse(company_data);
    document.getElementById('companyDetailName').textContent = company.company_name || '';
    document.getElementById('companyDetailSize').textContent = company.company_size || '';
    document.getElementById('companyDetailIndustry').textContent = company.industry || '';
    document.getElementById('companyDetailDescription').textContent = company.description || '';
    const logoElement = document.getElementById('companyDetailLogo');
    if (company.logo) {
        logoElement.innerHTML = `<img src="${company.logo}" alt="Logo" style="width: 50px; height: 50px; object-fit: contain;">`;
    } else {
        logoElement.textContent = 'Không có logo';
    }
    document.getElementById('companyDetailHouseNumber').textContent = company.house_number || '';
    document.getElementById('companyDetailStreet').textContent = company.street || '';
    document.getElementById('companyDetailWard').textContent = company.ward || '';
    document.getElementById('companyDetailDistrict').textContent = company.district || '';
    document.getElementById('companyDetailProvince').textContent = company.province || '';
    document.getElementById('companyDetailVillage').textContent = company.village || '';
    document.getElementById('companyDetailsModal').style.display = 'flex';
}

function closeCompanyDetailsModal() {
    document.getElementById('companyDetailsModal').style.display = 'none';
}

// Job Modals
function openEditJobModal(job_id, job_data) {
    const job = JSON.parse(job_data);
    document.getElementById('editJobId').value = job_id;
    document.getElementById('editJobCompanyId').value = job.company_id;
    document.getElementById('editJobTitle').value = job.job_title;
    document.getElementById('editJobExperience').value = job.experience_required || '';
    document.getElementById('editJobWorkType').value = job.work_type || 'full-time';
    document.getElementById('editJobDeadline').value = job.deadline ? job.deadline.split(' ')[0] : '';
    document.getElementById('editJobDescription').value = job.description || '';
    document.getElementById('editJobRequirements').value = job.candidate_requirements || '';
    document.getElementById('editJobBenefits').value = job.benefits || '';
    document.getElementById('editJobWorkingHours').value = job.working_hours || '';
    document.getElementById('editJobWorkLocation').value = job.work_location || '';
    document.getElementById('editJobSalary').value = job.salary || '';
    document.getElementById('editJobStatus').value = job.status || 'chờ duyệt';
    document.getElementById('editJobModal').style.display = 'flex';
}

function closeEditJobModal() {
    document.getElementById('editJobModal').style.display = 'none';
}

function openDeleteJobModal(job_id) {
    document.getElementById('deleteJobId').value = job_id;
    document.getElementById('deleteJobModal').style.display = 'flex';
}

function closeDeleteJobModal() {
    document.getElementById('deleteJobModal').style.display = 'none';
}

// Job Details Modal
let currentJobData = null;
function openJobDetailsModal(job_id, job_data) {
    currentJobData = JSON.parse(job_data);
    const job = currentJobData;
    const content = document.getElementById('jobDetailsContent');
    content.innerHTML = `
        <h5>Thông tin công việc</h5>
        <p><strong>Tiêu đề:</strong> ${job.job_title || ''}</p>
        <p><strong>Công ty:</strong> ${job.company_name || ''}</p>
        <p><strong>Kinh nghiệm yêu cầu:</strong> ${job.experience_required || ''}</p>
        <p><strong>Loại công việc:</strong> ${job.work_type || ''}</p>
        <p><strong>Hạn nộp:</strong> ${job.deadline ? new Date(job.deadline).toLocaleDateString('vi-VN') : ''}</p>
        <p><strong>Mô tả:</strong> ${job.description || ''}</p>
        <p><strong>Yêu cầu ứng viên:</strong> ${job.candidate_requirements || ''}</p>
        <p><strong>Quyền lợi:</strong> ${job.benefits || ''}</p>
        <p><strong>Thời gian làm việc:</strong> ${job.working_hours || ''}</p>
        <p><strong>Địa điểm làm việc:</strong> ${job.work_location || ''}</p>
        <p><strong>Lương:</strong> ${job.salary || ''}</p>
        <p><strong>Trạng thái:</strong> ${job.status || 'Chưa xác định'}</p>

        <h5 class="mt-4">Danh sách ứng viên</h5>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Tên</th>
                        <th>Email</th>
                        <th>SĐT</th>
                        <th>CV</th>
                        <th>Trạng thái</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    ${job.applications && job.applications.length > 0 ? job.applications.map((app, idx) => `
                        <tr>
                            <td>${idx + 1}</td>
                            <td>${app.full_name || ''}</td>
                            <td>${app.email || ''}</td>
                            <td>${app.SDT || ''}</td>
                            <td>${app.cv_path ? `<a href="${app.cv_path}" target="_blank">Xem CV</a>` : 'Không có CV'}</td>
                            <td>${app.status || ''}</td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick="openEditApplicationModal(${app.application_id}, '${JSON.stringify(app)}')">Sửa</button>
                                <button class="btn btn-sm btn-danger" onclick="confirmDeleteApplication(${app.application_id})">Xóa</button>
                            </td>
                        </tr>
                    `).join('') : '<tr><td colspan="7">Không có ứng viên nào.</td></tr>'}
                </tbody>
            </table>
        </div>

        <h5 class="mt-4">Danh sách các buổi phỏng vấn</h5>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Ứng viên</th>
                        <th>Ngày giờ</th>
                        <th>Địa điểm</th>
                        <th>Link họp</th>
                    </tr>
                </thead>
                <tbody>
                    ${job.applications && job.applications.length > 0 ? job.applications.map((app, idx) => 
                        app.interviews.map((interview, i) => `
                            <tr>
                                <td>${idx + 1}.${i + 1}</td>
                                <td>${app.full_name || ''}</td>
                                <td>${interview.interview_date ? new Date(interview.interview_date).toLocaleString('vi-VN') : ''}</td>
                                <td>${interview.location || ''}</td>
                                <td>${interview.meeting_link ? `<a href="${interview.meeting_link}" target="_blank">Link</a>` : ''}</td>
                            </tr>
                        `).join('')
                    ).join('') : '<tr><td colspan="5">Không có buổi phỏng vấn nào.</td></tr>'}
                </tbody>
            </table>
        </div>

        <h5 class="mt-4">Danh sách các bài test</h5>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>STT</th>
                        <th>Ứng viên</th>
                        <th>Nội dung</th>
                        <th>Hạn nộp</th>
                        <th>Đáp án</th>
                    </tr>
                </thead>
                <tbody>
                    ${job.applications && job.applications.length > 0 ? job.applications.map((app, idx) => 
                        app.tests.map((test, i) => `
                            <tr>
                                <td>${idx + 1}.${i + 1}</td>
                                <td>${app.full_name || ''}</td>
                                <td>${test.test_content || '' ? `<a href="${test.test_content}" target="_blank">Link</a>` : ''}</td>
                                <td>${test.deadline ? new Date(test.deadline).toLocaleString('vi-VN') : ''}</td>
                                <td>${test.test_answer || 'Chưa nộp' ? `<a href="${test.test_answer}" target="_blank">Link</a>` : ''}</td>
                            </tr>
                        `).join('')
                    ).join('') : '<tr><td colspan="5">Không có bài test nào.</td></tr>'}
                </tbody>
            </table>
        </div>
    `;
    document.getElementById('jobDetailsModal').style.display = 'flex';
}

function closeJobDetailsModal() {
    document.getElementById('jobDetailsModal').style.display = 'none';
    currentJobData = null;
}

// Application Modals
function openEditApplicationModal(application_id, application_data) {
    const application = JSON.parse(application_data);
    document.getElementById('editApplicationId').value = application_id;
    document.getElementById('editApplicationFullName').value = application.full_name;
    document.getElementById('editApplicationSDT').value = application.SDT || '';
    document.getElementById('editApplicationEmail').value = application.email || '';
    document.getElementById('editApplicationCvPath').value = application.cv_path || '';
    const cvPreview = document.getElementById('editApplicationCvPreview');
    if (application.cv_path) {
        cvPreview.innerHTML = `<a href="${application.cv_path}" target="_blank">Xem CV hiện tại</a>`;
    } else {
        cvPreview.innerHTML = 'Không có CV';
    }
    document.getElementById('editApplicationStatus').value = application.status;
    document.getElementById('editApplicationRejectedReason').value = application.rejected_reason || '';
    const rejectedReasonField = document.getElementById('rejectedReasonField');
    if (['không xác nhận', 'không được duyệt', 'không trúng tuyển'].includes(application.status)) {
        rejectedReasonField.style.display = 'block';
    } else {
        rejectedReasonField.style.display = 'none';
    }
    document.getElementById('editApplicationModal').style.display = 'flex';

    document.getElementById('editApplicationStatus').addEventListener('change', function() {
        if (['không xác nhận', 'không được duyệt', 'không trúng tuyển'].includes(this.value)) {
            rejectedReasonField.style.display = 'block';
        } else {
            rejectedReasonField.style.display = 'none';
        }
    });
}

function closeEditApplicationModal() {
    document.getElementById('editApplicationModal').style.display = 'none';
}

function confirmDeleteApplication(application_id) {
    if (confirm('Bạn có chắc chắn muốn xóa hồ sơ ứng viên này không?')) {
        document.getElementById('deleteApplicationId').value = application_id;
        document.querySelector('#deleteApplicationModal form').submit();
    }
}

function openDeleteApplicationModal(application_id) {
    document.getElementById('deleteApplicationId').value = application_id;
    document.getElementById('deleteApplicationModal').style.display = 'flex';
}

function closeDeleteApplicationModal() {
    document.getElementById('deleteApplicationModal').style.display = 'none';
}

// Close modals on outside click
window.onclick = function(event) {
    const modals = ['addUserModal', 'editUserModal', 'deleteUserModal', 'editCompanyModal', 'deleteCompanyModal', 'companyDetailsModal', 'editJobModal', 'deleteJobModal', 'editApplicationModal', 'deleteApplicationModal', 'jobDetailsModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
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
        max-width: 1200px;
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
        margin-right: 5px;
    }

    .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        margin-right: 5px;
    }

    .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
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

    hr.my-4 {
        border: 0;
        border-top: 1px solid #e0e0e0;
        margin: 2rem 0;
    }

    #jobDetailsContent p, #jobDetailsContent h5 {
        margin-bottom: 10px;
    }

    #jobDetailsContent p strong {
        display: inline-block;
        width: 150px;
    }

    .job-title-link, .candidate-name-link {
        text-decoration: none;
        color: black;
    }

    .job-title-link:hover, .candidate-name-link:hover {
        color: #00b14f;
    }

    .company-title-link {
        text-decoration: none;
        color: black;
    }

    .company-title-link:hover {
        color: #00b14f;
    }
</style>