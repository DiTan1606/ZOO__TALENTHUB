<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
?>

<?php include 'includes/header.php'; ?>
<h2>Welcome to Dashboard</h2>
<?php
switch ($role) {
    case 'candidate':
        echo "<p>Welcome, Candidate! You can search for jobs or manage your profile.</p>";
        break;
    case 'recruiter':
        echo "<p>Welcome, Recruiter! You can post jobs or manage applications.</p>";
        break;
    case 'staff_recruitment':
        echo "<p>Welcome, Staff! You can approve job postings.</p>";
        break;
    case 'staff_candidate':
        echo "<p>Welcome, Staff! You can manage candidate profiles.</p>";
        break;
}
?>
<a href="logout.php" class="btn btn-danger">Logout</a>
<?php include 'includes/footer.php'; ?>