<?php
$host = 'localhost';
$dbname = 'recruitment_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(dsn: "mysql:host=$host;dbname=$dbname", username: $username, password: $password);
    $pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>

