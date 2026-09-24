<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Admin.php';

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: change_password.php');
    exit;
}

// Make sure the admin is logged in.
if (!Admin::isLoggedIn()) {
    header('Location: admin_login.php');
    exit;
}

// Get the logged-in admin ID.
$adminID = $_SESSION['adminID'] ?? null;

if (!$adminID) {
    header('Location: admin_login.php');
    exit;
}

// Get submitted values.
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? '';

// Verify CSRF token.
if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    header('Location: change_password.php?error=csrf');
    exit;
}

// Make sure all password fields are filled.
if (
    $currentPassword === '' ||
    $newPassword === '' ||
    $confirmPassword === ''
) {
    header('Location: change_password.php?error=empty');
    exit;
}

// Check that the new passwords match.
if ($newPassword !== $confirmPassword) {
    header('Location: change_password.php?error=mismatch');
    exit;
}

// Require a minimum password length.
if (strlen($newPassword) < 8) {
    header('Location: change_password.php?error=length');
    exit;
}

// Prevent using the current password again.
if ($currentPassword === $newPassword) {
    header('Location: change_password.php?error=same');
    exit;
}

try {
    // Connect using the existing Database class.
    $database = new Database();
    $pdo = $database->getConnection();

    // Retrieve the current password hash.
    $sql = '
        SELECT "password"
        FROM "Admin"
        WHERE "adminID" = :adminID
        LIMIT 1
    ';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':adminID' => $adminID
    ]);

    $admin = $stmt->fetch();

    // Admin account no longer exists.
    if (!$admin) {
        header('Location: admin_login.php');
        exit;
    }

    // Verify the current password.
    if (!password_verify($currentPassword, $admin['password'])) {
        header('Location: change_password.php?error=current');
        exit;
    }

    // Hash the new password.
    $newPasswordHash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

    // Update password and remove the forced-password-change requirement.
    $sql = '
        UPDATE "Admin"
        SET
            "password" = :password,
            "mustChangePassword" = FALSE
        WHERE "adminID" = :adminID
    ';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':password' => $newPasswordHash,
        ':adminID' => $adminID
    ]);

    // Update the session.
    $_SESSION['mustChangePassword'] = false;

    // Regenerate the session ID after changing the password.
    session_regenerate_id(true);

    // Remove the used CSRF token.
    unset($_SESSION['csrf_token']);

    // Send the admin to the dashboard.
    header('Location: dashboard.php');
    exit;

} catch (PDOException $e) {

    // Log the technical error rather than displaying database details.
    error_log('Password change failed: ' . $e->getMessage());

    header('Location: change_password.php?error=database');
    exit;
}