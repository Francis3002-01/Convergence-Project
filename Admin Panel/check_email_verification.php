<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Classes/Admin.php';

header('Content-Type: application/json');

if (!Admin::isLoggedIn()) {
    echo json_encode([
        'status' => 'unauthorized'
    ]);
    exit;
}

try {
    $database = new Database();
    $pdo = $database->getConnection();

    $adminID = $_SESSION['adminID'];

    $query = '
        SELECT
            "email",
            "pendingEmail",
            "emailVerificationExpires"
        FROM "Admin"
        WHERE "adminID" = :adminID
        LIMIT 1
    ';

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':adminID' => $adminID
    ]);

    $admin = $stmt->fetch();

    if (!$admin) {
        echo json_encode([
            'status' => 'error'
        ]);
        exit;
    }

    /*
     * No pending email means the verification
     * has either been completed or there is
     * currently no email change request.
     */
    if (empty($admin['pendingEmail'])) {
        echo json_encode([
            'status' => 'verified',
            'email' => $admin['email']
        ]);
        exit;
    }

    /*
     * Check whether the verification request expired.
     */
    if (
        empty($admin['emailVerificationExpires']) ||
        strtotime($admin['emailVerificationExpires']) <= time()
    ) {
        echo json_encode([
            'status' => 'expired'
        ]);
        exit;
    }

    /*
     * Verification is still waiting.
     */
    echo json_encode([
        'status' => 'waiting',
        'pendingEmail' => $admin['pendingEmail']
    ]);

} catch (PDOException $e) {

    error_log(
        'Email Verification Status Error: ' .
        $e->getMessage()
    );

    echo json_encode([
        'status' => 'error'
    ]);
}