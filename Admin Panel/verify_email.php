<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

$token = trim($_GET['token'] ?? '');

$success = false;
$message = '';

if ($token === '') {

    $message = 'Invalid verification link.';

} else {

    try {

        $database = new Database();
        $pdo = $database->getConnection();

        /*
         * Hash the token supplied in the URL.
         */
        $tokenHash = hash(
            'sha256',
            $token
        );

        /*
         * Find the admin account associated
         * with this verification token.
         */
        $query = '
            SELECT
                "adminID",
                "pendingEmail",
                "emailVerificationExpires"
            FROM "Admin"
            WHERE "emailVerificationToken" = :token
            LIMIT 1
        ';

        $stmt = $pdo->prepare($query);

        $stmt->execute([
            ':token' => $tokenHash
        ]);

        $admin = $stmt->fetch();

        /*
         * Token does not exist.
         */
        if (!$admin) {

            $message =
                'This verification link is invalid or has already been used.';

        /*
         * Token exists but there is no pending email.
         */
        } elseif (
            empty($admin['pendingEmail']) ||
            empty($admin['emailVerificationExpires'])
        ) {

            $message =
                'There is no pending email change to verify.';

        /*
         * Token has expired.
         */
        } elseif (
            strtotime($admin['emailVerificationExpires']) < time()
        ) {

            $message =
                'This verification link has expired. Please request a new email change.';

        } else {

            /*
             * Save the pending email before
             * clearing it from the database.
             */
            $verifiedEmail = $admin['pendingEmail'];

            /*
             * Move pendingEmail into the actual
             * verified email field.
             */
            $updateQuery = '
                UPDATE "Admin"
                SET
                    "email" = "pendingEmail",
                    "pendingEmail" = NULL,
                    "emailVerificationToken" = NULL,
                    "emailVerificationExpires" = NULL
                WHERE "adminID" = :adminID
            ';

            $updateStmt = $pdo->prepare($updateQuery);

            $updateStmt->execute([
                ':adminID' => $admin['adminID']
            ]);

            /*
             * Update the current session if the
             * admin is logged in.
             */
            if (
                isset($_SESSION['adminID']) &&
                (int) $_SESSION['adminID'] ===
                (int) $admin['adminID']
            ) {

                $_SESSION['email'] = $verifiedEmail;
            }

            $success = true;

            $message =
                'Your email address has been successfully verified and updated.';
        }

    } catch (PDOException $e) {

        error_log(
            'Email Verification Error: ' . $e->getMessage()
        );

        $message =
            'Unable to verify the email address. Please try again later.';
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Email Verification | Convergence Journal
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            font-family:
                "Inter",
                "Segoe UI",
                Arial,
                sans-serif;

            background: #f5f2ed;
            color: #1f2a44;
        }

        .verification-card {
            width: 100%;
            max-width: 500px;

            padding: 40px;

            text-align: center;

            background: #ffffff;

            border: 1px solid #e5e1dc;
            border-radius: 12px;

            box-shadow:
                0 8px 25px
                rgba(0, 0, 0, 0.08);
        }

        .verification-icon {
            width: 64px;
            height: 64px;

            display: flex;
            align-items: center;
            justify-content: center;

            margin: 0 auto 20px;

            border-radius: 50%;

            background: #f8efee;

            color: #a5241e;

            font-size: 28px;
        }

        h1 {
            margin: 0 0 15px;

            font-size: 24px;
        }

        p {
            margin: 0 0 25px;

            color: #6b7280;
            line-height: 1.6;
        }

        .button {
            display: inline-block;

            padding: 12px 22px;

            background: #a5241e;
            color: #ffffff;

            text-decoration: none;

            border-radius: 6px;

            font-weight: 600;
        }

        .button:hover {
            background: #7c1b16;
        }

    </style>

</head>

<body>

    <div class="verification-card">

        <div class="verification-icon">
            <?php echo $success ? '✓' : '!'; ?>
        </div>

        <h1>
            <?php
            echo $success
                ? 'Email Verified'
                : 'Verification Failed';
            ?>
        </h1>

        <p>
            <?php
            echo htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
        </p>

        <a
            href="edit_profile.php"
            class="button"
        >
            Return to Edit Profile
        </a>

    </div>

</body>

</html>