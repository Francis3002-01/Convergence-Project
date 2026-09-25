<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}

$token = $_POST['token'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validate token format.
if (
    $token === '' ||
    !preg_match('/^[a-f0-9]{64}$/', $token)
) {
    header(
        'Location: forgot_password.php?error=invalid_token'
    );
    exit;
}

// Validate password fields.
if ($newPassword === '' || $confirmPassword === '') {
    header(
        'Location: reset_password.php?token=' .
        urlencode($token) .
        '&error=empty'
    );
    exit;
}

// Check that passwords match.
if ($newPassword !== $confirmPassword) {
    header(
        'Location: reset_password.php?token=' .
        urlencode($token) .
        '&error=mismatch'
    );
    exit;
}

// Minimum password length.
if (strlen($newPassword) < 8) {
    header(
        'Location: reset_password.php?token=' .
        urlencode($token) .
        '&error=length'
    );
    exit;
}

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $tokenHash = hash('sha256', $token);

    /*
     * Find a valid, unused, non-expired token.
     */
    $sql = '
        SELECT
            "tokenID",
            "adminID"
        FROM "PasswordResetToken"
        WHERE "tokenHash" = :tokenHash
        AND "usedAt" IS NULL
        AND "expiresAt" > NOW()
        LIMIT 1
    ';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':tokenHash' => $tokenHash
    ]);

    $resetToken = $stmt->fetch();

    if (!$resetToken) {
        header(
            'Location: forgot_password.php?error=invalid_token'
        );
        exit;
    }

    /*
     * Hash the new password.
     */
    $passwordHash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

    /*
     * Update the administrator password.
     */
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

    /*
     * Mark the reset token as used.
     */
    $tokenSql = '
        UPDATE "PasswordResetToken"
        SET "usedAt" = NOW()
        WHERE "tokenID" = :tokenID
    ';

    $tokenStmt = $pdo->prepare($tokenSql);

    $tokenStmt->execute([
        ':tokenID' => $resetToken['tokenID']
    ]);

    /*
     * Invalidate any other outstanding reset tokens
     * belonging to this administrator.
     */
    $invalidateSql = '
        UPDATE "PasswordResetToken"
        SET "usedAt" = NOW()
        WHERE "adminID" = :adminID
        AND "usedAt" IS NULL
    ';

    $invalidateStmt = $pdo->prepare($invalidateSql);

    $invalidateStmt->execute([
        ':adminID' => $resetToken['adminID']
    ]);

    /*
     * Send the administrator back to the login page.
     */
    header(
        'Location: admin_login.php?reset=success'
    );
    exit;

} catch (PDOException $e) {

    error_log(
        'Password reset failed: ' .
        $e->getMessage()
    );

    header(
        'Location: forgot_password.php?error=general'
    );
    exit;
}