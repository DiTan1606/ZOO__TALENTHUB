<?php
session_start();
require 'includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'recruiter') {
    die(json_encode(['error' => 'Access denied']));
}

$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($job_id <= 0) {
    die(json_encode(['error' => 'Invalid job ID']));
}

$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT company_id FROM users WHERE user_id = ? AND role = 'recruiter'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['company_id']) {
        die(json_encode(['error' => 'Company not found']));
    }

    $stmt_job = $pdo->prepare("SELECT * FROM jobs WHERE job_id = ? AND company_id = ?");
    $stmt_job->execute([$job_id, $user['company_id']]);
    $job = $stmt_job->fetch(PDO::FETCH_ASSOC);

    if (!$job) {
        die(json_encode(['error' => 'Job not found']));
    }

    echo json_encode($job);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Error fetching job: ' . $e->getMessage()]));
}