<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Admin.php';

if (!class_exists('Admin')) {
    die('Admin.php was loaded, but the Admin class was not found.');
}

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit;
}

// Get submitted credentials.
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate email.
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: admin_login.php?error=invalid');
    exit;
}

// Validate password.
if ($password === '') {
    header('Location: admin_login.php?error=invalid');
    exit;
}

// Create database connection using the existing Database class.
$database = new Database();
$pdo = $database->getConnection();

// Create Admin object.
$admin = new Admin($pdo);

// Attempt authentication.
if (!$admin->login($email, $password)) {
    header('Location: admin_login.php?error=invalid');
    exit;
}

// Successful login.
// Check whether the administrator must change their password.
if ($_SESSION['mustChangePassword'] === true) {
    header('Location: change_password.php');
    exit;
}

// Normal login.
header('Location: dashboard.php');
exit;