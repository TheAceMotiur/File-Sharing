<?php
session_start();

$host = '184.164.80.178';
$db   = 'onenetly_home';
$user = 'onenetly_home';
$pass = 'AmiMotiur27@';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $_SESSION['success'] = "Database connection successful!";
} catch (\PDOException $e) {
    $_SESSION['error'] = "Connection failed: " . $e->getMessage();
    error_log("Database Error: " . $e->getMessage());
}

// Function to display messages
function displayMessage() {
    if(isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
        unset($_SESSION['error']);
    }
    if(isset($_SESSION['success'])) {
        echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
        unset($_SESSION['success']);
    }
}
?>
