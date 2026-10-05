<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

// Get submitted passwords.
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Get the reset request from Device A's session.
$tokenID = $_SESSION['password_reset_request'] ?? null;
$handoffToken = $_SESSION['password_reset_handoff'] ?? null;

// Make sure Device A has an active password reset request.
if (!$tokenID || !$handoffToken) {
    header('Location: forgot_password.php?error=invalid_token');
    exit;
}

// Check empty passwords.
if ($newPassword === '' ||$confirmPassword === '') {
    header('Location: reset_password.php?device=original&error=empty');
    exit;
}

// Check password confirmation.
if ($newPassword !== $confirmPassword) {
    header('Location: reset_password.php?device=original&error=mismatch');
    exit;
}

// Check minimum password length.
if (strlen($newPassword) < 8) {
    header('Location: reset_password.php?device=original&error=length');
    exit;
}

try {

    // Connect to database.
    $database = new Database();
    $pdo = $database->getConnection();

    // Hash Device A's handoff token.
    $handoffHash = hash('sha256', $handoffToken);

    // Find the reset request belonging to this Device A session.
    $sql = '
        SELECT
            "tokenID",
            "adminID",
            "clickedAt",
            "expiresAt",
            "usedAt"
        FROM "PasswordResetToken"
        WHERE "tokenID" = :tokenID
        AND "handoffHash" = :handoffHash
        LIMIT 1
    ';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':tokenID' => $tokenID,
        ':handoffHash' => $handoffHash
    ]);

    $resetToken = $stmt->fetch();

    // Reset request does not exist.
    if (!$resetToken) {
        header('Location: forgot_password.php?error=invalid_token');
        exit;
    }

    // Reset has already been completed.
    if ($resetToken['usedAt'] !== null) {
        header('Location: forgot_password.php?error=invalid_token');
        exit;
    }

    // Reset request has expired.
    if (strtotime($resetToken['expiresAt']) <= time()) {
        header('Location: forgot_password.php?error=invalid_token');
        exit;
    }

    // Email has not been verified yet.
    if ($resetToken['clickedAt'] === null) {
        header('Location: reset_password.php?device=original');
        exit;
    }

    // Start transaction.
    $pdo->beginTransaction();

    // Hash the new password securely.
    $passwordHash = password_hash($newPassword,PASSWORD_DEFAULT);

    // Update administrator password.
    $updateSql = '
        UPDATE "Admin"
        SET
            "password" = :password,
            "mustChangePassword" = FALSE
        WHERE "adminID" = :adminID
    ';

    $updateStmt = $pdo->prepare($updateSql);

    $updateStmt->execute([
        ':password' => $passwordHash,
        ':adminID' => $resetToken['adminID']
    ]);

    // Mark this reset token as used.
    $tokenSql = '
        UPDATE "PasswordResetToken"
        SET
            "usedAt" = NOW()
        WHERE "tokenID" = :tokenID
        AND "usedAt" IS NULL
    ';

    $tokenStmt = $pdo->prepare($tokenSql);

    $tokenStmt->execute([
        ':tokenID' => $resetToken['tokenID']
    ]);

    // Invalidate any other unused reset tokens
    // belonging to this administrator.
    $invalidateSql = '
        UPDATE "PasswordResetToken"
        SET
            "usedAt" = NOW()
        WHERE "adminID" = :adminID
        AND "tokenID" <> :tokenID
        AND "usedAt" IS NULL
    ';

    $invalidateStmt = $pdo->prepare($invalidateSql);

    $invalidateStmt->execute([
        ':adminID' => $resetToken['adminID'],
        ':tokenID' => $resetToken['tokenID']
    ]);

    // Everything succeeded.
    $pdo->commit();

    // Remove the password reset information
    // from Device A's session.
    unset($_SESSION['password_reset_request']);
    unset($_SESSION['password_reset_handoff']);

    // Return to login page.
    header('Location: admin_login.php?reset=success');
    exit;

} catch (PDOException $e) {

    // Undo database changes if something failed.
    if (isset($pdo) &&$pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Log the actual error for development/server logs,
    // but do not expose it to the administrator.
    error_log('Password reset failed: ' .$e->getMessage());
    header('Location: forgot_password.php?error=general');
    exit;
}