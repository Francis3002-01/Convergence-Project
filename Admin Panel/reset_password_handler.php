<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';


// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: forgot_password.php');

    exit;
}


// Get submitted values.
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


// Check empty passwords.
if (
    $newPassword === '' ||
    $confirmPassword === ''
) {

    header(
        'Location: reset_password.php?token=' .
        urlencode($token) .
        '&error=empty'
    );

    exit;
}


// Check password confirmation.
if ($newPassword !== $confirmPassword) {

    header(
        'Location: reset_password.php?token=' .
        urlencode($token) .
        '&error=mismatch'
    );

    exit;
}


// Check minimum password length.
if (strlen($newPassword) < 8) {

    header(
        'Location: reset_password.php?token=' .
        urlencode($token) .
        '&error=length'
    );

    exit;
}


try {

    // Connect to database.
    $database = new Database();
    $pdo = $database->getConnection();


    /*
     * Hash the token to compare it with
     * the database value.
     */
    $tokenHash = hash('sha256', $token);


    /*
     * Find the valid reset token.
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


    /*
     * Token does not exist, was already used,
     * or has expired.
     */
    if (!$resetToken) {

        header(
            'Location: forgot_password.php?error=invalid_token'
        );

        exit;
    }


    /*
     * Start transaction.
     *
     * Password update and token invalidation
     * should happen together.
     */
    $pdo->beginTransaction();


    /*
     * Hash the new password securely.
     */
    $passwordHash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );


    /*
     * Update administrator password.
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
     * Mark the token used.
     *
     * The token cannot be used again.
     */
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


    /*
     * Invalidate any other unused reset tokens
     * belonging to this administrator.
     */
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


    /*
     * Everything succeeded.
     */
    $pdo->commit();


    /*
     * Return to login page.
     */
    header(
        'Location: admin_login.php?reset=success'
    );

    exit;


} catch (PDOException $e) {


    /*
     * Undo database changes if something failed.
     */
    if (
        isset($pdo) &&
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    /*
     * Log the actual error for development/server logs,
     * but do not expose it to the administrator.
     */
    error_log(
        'Password reset failed: ' .
        $e->getMessage()
    );


    header(
        'Location: forgot_password.php?error=general'
    );

    exit;
}