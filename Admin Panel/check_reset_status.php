<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

//$tokenID = $_SESSION['password_reset_token_id'] ?? null;
$tokenID = $_SESSION['password_reset_request'] ?? null;

if (!$tokenID) {
    echo json_encode([
        'status' => 'none'
    ]);
    exit;
}

try {

    $database = new Database();
    $pdo = $database->getConnection();

    $sql = '
        SELECT
            "tokenID",
            "clickedAt",
            "expiresAt",
            "usedAt"
        FROM "PasswordResetToken"
        WHERE "tokenID" = :tokenID
        LIMIT 1
    ';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':tokenID' => $tokenID
    ]);

    $reset = $stmt->fetch();

    if (!$reset) {
        echo json_encode([
            'status' => 'none'
        ]);
        exit;
    }

    // Reset already completed.
    if ($reset['usedAt'] !== null) {
        echo json_encode([
            'status' => 'used'
        ]);
        exit;
    }

    // Reset link has expired.
    if (strtotime($reset['expiresAt']) < time()) {
        echo json_encode([
            'status' => 'expired'
        ]);
        exit;
    }

    // Email link has not been clicked yet.
    if ($reset['clickedAt'] === null) {
        echo json_encode([
            'status' => 'waiting'
        ]);
        exit;
    }

    // Email link was clicked.
    echo json_encode([
        'status' => 'clicked'
    ]);

} catch (PDOException $e) {

    error_log(
        'Password reset status error: ' .
        $e->getMessage()
    );

    echo json_encode([
        'status' => 'error'
    ]);
}