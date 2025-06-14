<?php
session_start();
require 'includes/db_connect.php';

// Check login and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'candidate') {
    header("Location: login.php");
    exit();
}

// Handle search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$province = isset($_GET['province']) ? trim($_GET['province']) : '';
$industry = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$work_type = isset($_GET['work_type']) ? trim($_GET['work_type']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$jobs_per_page = 12;
$offset = ($page - 1) * $jobs_per_page;

// Danh sách 63 tỉnh thành Việt Nam, sắp xếp theo ABC
$provinces = [
    'An Giang', 'Bà Rịa - Vũng Tàu', 'Bắc Giang', 'Bắc Kạn', 'Bạc Liêu', 'Bắc Ninh', 
    'Bến Tre', 'Bình Định', 'Bình Dương', 'Bình Phước', 'Bình Thuận', 'Cà Mau', 
    'Cần Thơ', 'Cao Bằng', 'Đà Nẵng', 'Đắk Lắk', 'Đắk Nông', 'Điện Biên', 'Đồng Nai', 
    'Đồng Tháp', 'Gia Lai', 'Hà Giang', 'Hà Nam', 'Hà Nội', 'Hà Tĩnh', 'Hải Dương', 
    'Hải Phòng', 'Hậu Giang', 'Hòa Bình', 'Hưng Yên', 'Khánh Hòa', 'Kiên Giang', 
    'Kon Tum', 'Lai Châu', 'Lâm Đồng', 'Lạng Sơn', 'Lào Cai', 'Long An', 
    'Nam Định', 'Nghệ An', 'Ninh Bình', 'Ninh Thuận', 'Phú Thọ', 'Phú Yên', 
    'Quảng Bình', 'Quảng Nam', 'Quảng Ngãi', 'Quảng Ninh', 'Quảng Trị', 'Sóc Trăng', 
    'Sơn La', 'Tây Ninh', 'Thái Bình', 'Thái Nguyên', 'Thanh Hóa', 'Thừa Thiên Huế', 
    'Tiền Giang', 'TP. Hồ Chí Minh', 'Trà Vinh', 'Tuyên Quang', 'Vĩnh Long', 
    'Vĩnh Phúc', 'Yên Bái'
];

// Build the job query dynamically
$query = "
    SELECT 
        j.job_id, j.job_title, j.salary, j.work_location, j.experience_required, 
        j.work_type, j.deadline, j.description, c.company_name, c.company_size, 
        c.industry, c.province, c.logo
    FROM jobs j
    JOIN company c ON j.company_id = c.company_id
    WHERE j.status = 'đã duyệt'
";
$params = [];

if ($search) {
    $query .= " AND (j.job_title LIKE :search OR j.description LIKE :search)";
    $params[':search'] = "%" . $search . "%";
}
if ($province) {
    $query .= " AND c.province = :province";
    $params[':province'] = $province;
}
if ($industry) {
    $query .= " AND c.industry = :industry";
    $params[':industry'] = $industry;
}
if ($work_type) {
    $query .= " AND j.work_type = :work_type";
    $params[':work_type'] = $work_type;
}

$query .= " ORDER BY j.deadline DESC LIMIT :limit OFFSET :offset";

try {
    $stmt_jobs = $pdo->prepare($query);
    // Bind parameters
    if ($search) {
        $stmt_jobs->bindValue(':search', "%$search%", PDO::PARAM_STR);
    }
    if ($province) {
        $stmt_jobs->bindValue(':province', $province, PDO::PARAM_STR);
    }
    if ($industry) {
        $stmt_jobs->bindValue(':industry', $industry, PDO::PARAM_STR);
    }
    if ($work_type) {
        $stmt_jobs->bindValue(':work_type', $work_type, PDO::PARAM_STR);
    }
    $stmt_jobs->bindValue(':limit', (int)$jobs_per_page, PDO::PARAM_INT);
    $stmt_jobs->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt_jobs->execute();
    $jobs = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);

    // Count total jobs for pagination
    $count_query = "SELECT COUNT(*) FROM jobs j JOIN company c ON j.company_id = c.company_id WHERE j.status = 'đã duyệt'";
    $count_params = [];
    if ($search) {
        $count_query .= " AND (j.job_title LIKE :search OR j.description LIKE :search)";
        $count_params[':search'] = "%$search%";
    }
    if ($province) {
        $count_query .= " AND c.province = :province";
        $count_params[':province'] = $province;
    }
    if ($industry) {
        $count_query .= " AND c.industry = :industry";
        $count_params[':industry'] = $industry;
    }
    if ($work_type) {
        $count_query .= " AND j.work_type = :work_type";
        $count_params[':work_type'] = $work_type;
    }
    $stmt_count = $pdo->prepare($count_query);
    foreach ($count_params as $key => $value) {
        $stmt_count->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt_count->execute();
    $total_jobs = $stmt_count->fetchColumn();
    $total_pages = ceil($total_jobs / $jobs_per_page);
} catch (PDOException $e) {
    die("Error fetching jobs: " . $e->getMessage());
}
?>

<?php include 'includes/header.php'; ?>

<?php include 'includes/can_navBar.php'; ?>

<!-- Banner -->
<section class="banner">
    <div class="container">
        <h3>Kết nối nhân tài, kiến tạo tương lai</h3>
        <p>Hiện thực hóa ước mơ công việc tại Việt Nam</p>
    </div>
</section>

<style>
    .banner {
        background: linear-gradient(to right,rgb(6, 132, 63),rgb(9, 122, 174));
        color: white;
        padding: 80px 0;
        text-align: center;
        position: relative;
        margin-bottom: 20px;
    }
</style>

<!-- Search Bar -->
<section class="container">
    <div class="search-bar shadow">
        <form method="GET" action="candidate.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control search-input" 
                           placeholder="Tìm việc (VD: PHP Developer)" 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <div class="input-group-append">
                        <button type="submit" class="btn btn-primary">Tìm kiếm</button>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <select name="province" class="form-control">
                    <option value="">Tất cả tỉnh thành</option>
                    <?php foreach ($provinces as $prov): ?>
                        <option value="<?php echo htmlspecialchars($prov); ?>" <?php echo $province === $prov ? 'selected' : ''; ?>><?php echo htmlspecialchars($prov); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="industry" class="form-control">
                    <option value="">Tất cả lĩnh vực</option>
                    <option value="Công nghệ thông tin" <?php echo $industry === 'Công nghệ thông tin' ? 'selected' : ''; ?>>Công nghệ thông tin</option>
                    <option value="Giáo dục" <?php echo $industry === 'Giáo dục' ? 'selected' : ''; ?>>Giáo dục</option>
                    <option value="Y tế" <?php echo $industry === 'Y tế' ? 'selected' : ''; ?>>Y tế</option>
                    <option value="Xây dựng" <?php echo $industry === 'Xây dựng' ? 'selected' : ''; ?>>Xây dựng</option>
                    <option value="Nông nghiệp" <?php echo $industry === 'Nông nghiệp' ? 'selected' : ''; ?>>Nông nghiệp</option>
                    <option value="Du lịch" <?php echo $industry === 'Du lịch' ? 'selected' : ''; ?>>Du lịch</option>
                    <option value="Thực phẩm" <?php echo $industry === 'Thực phẩm' ? 'selected' : ''; ?>>Thực phẩm</option>
                    <option value="Thời trang" <?php echo $industry === 'Thời trang' ? 'selected' : ''; ?>>Thời trang</option>
                    <option value="Vận tải" <?php echo $industry === 'Vận tải' ? 'selected' : ''; ?>>Vận tải</option>
                    <option value="Tài chính" <?php echo $industry === 'Tài chính' ? 'selected' : ''; ?>>Tài chính</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="work_type" class="form-control">
                    <option value="">Loại công việc</option>
                    <option value="full-time" <?php echo $work_type === 'full-time' ? 'selected' : ''; ?>>Toàn thời gian</option>
                    <option value="part-time" <?php echo $work_type === 'part-time' ? 'selected' : ''; ?>>Bán thời gian</option>
                    <option value="freelance" <?php echo $work_type === 'freelance' ? 'selected' : ''; ?>>Freelance</option>
                    <option value="contract" <?php echo $work_type === 'contract' ? 'selected' : ''; ?>>Hợp đồng ngắn hạn</option>
                </select>
            </div>
            <div class="col-12 mt-2">
                <a href="candidate.php" class="btn btn-outline-secondary btn-sm">Xóa bộ lọc</a>
            </div>
        </form>
    </div>
</section>

<style>
    .search-bar {
        background-color: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        margin-top: -60px;
        position: relative;
        z-index: 1;
    }
    .search-input {
        border: 1px solid #ddd;
        border-radius: 5px 0 0 5px;
        padding: 10px;
        font-size: 16px;
        height: 45px;
    }
    .input-group-append .btn-primary {
        background-color: #00b14f;
        border: none;
        border-radius: 0 5px 5px 0;
        height: 45px;
        font-weight: 500;
    }
    .input-group-append .btn-primary:hover {
        background-color: #009b45;
    }
    .form-control {
        height: 45px;
        border-radius: 5px;
        font-size: 16px;
    }
    .filters .btn-outline-primary {
        border-color: #00b14f;
        color: #00b14f;
        font-size: 14px;
        padding: 5px 15px;
        margin-right: 10px;
        border-radius: 20px;
    }
    .filters .btn-outline-primary:hover {
        background-color: #00b14f;
        color: white;
    }
    .btn-outline-secondary {
        font-size: 14px;
        padding: 5px 15px;
        border-radius: 20px;
    }
</style>

<script>
    function setFilter(param, value) {
        const url = new URL(window.location);
        url.searchParams.set(param, value);
        url.searchParams.delete('page'); // Reset page to 1 when applying new filter
        window.location = url;
    }
</script>

<!-- Job Listings -->
<section class="container my-4">
    <h4 class="mb-4">Việc làm tốt nhất</h4>
    <?php if (count($jobs) > 0): ?>
        <div class="row">
            <?php foreach ($jobs as $job): ?>
                <div class="col-md-4 mb-4">
                    <a href="job_detail.php?id=<?php echo $job['job_id']; ?>" class="job-card shadow d-flex text-decoration-none">
                        <img src="<?php echo htmlspecialchars($job['logo'] ?? 'https://via.placeholder.com/70'); ?>" alt="Company Logo" class="job-logo">
                        <div class="job-details">
                            <h5 class="job-title"><?php echo htmlspecialchars($job['job_title']); ?></h5>
                            <p class="job-info"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            <div class="job-subinfo-box">
                                <span class="job-subinfo"><?php echo htmlspecialchars($job['province'] ?? 'Không xác định'); ?></span>
                                <span class="job-subinfo"><?php echo htmlspecialchars($job['industry']); ?></span>
                                <span class="job-subinfo"><?php echo htmlspecialchars($job['work_type']); ?></span>
                            </div>
                        </div>
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
        <p class="text-center">Không tìm thấy công việc nào phù hợp. Vui lòng thử các bộ lọc khác.</p>
    <?php endif; ?>
</section>

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
        text-decoration: none;
        color: inherit;
    }
    .job-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        font-size: 14px;
        font-weight: 500;
        color: #333;
        margin-bottom: 5px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .job-card:hover .job-title {
        color: #00b14f;
    }
    .job-subinfo-box {
        display: flex;
        flex-wrap: wrap;
    }
    .job-info {
        font-size: 12px;
        color: #666;
        margin-bottom: 5px;
    }
    .job-subinfo {
        display: inline;
        font-size: 9px;
        font-weight: 400;
        color: #000;
        padding: 5px 10px;
        margin: 0 5px 5px 0;
        background: #ddd;
        border-radius: 30px;
    }
    .pagination .page-link {
        color: #00b14f;
    }
    .pagination .page-item.active .page-link {
        background-color: #00b14f;
        border-color: #00b14f;
        color: white;
    }
</style>

<?php include 'includes/footer.php'; ?>