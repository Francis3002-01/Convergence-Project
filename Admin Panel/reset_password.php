<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';


// Get token and device mode from URL.
$token = $_GET['token'] ?? '';
$device = $_GET['device'] ?? '';


// Get validation error from handler.
$urlError = $_GET['error'] ?? '';


$error = '';
$validToken = false;
$emailVerified = false;


// Validate token format first.
// Validate the reset request.

if ($device === 'original') {

    // Original device mode.
    // Use the tokenID stored in this device's session.

    $tokenID = $_SESSION['password_reset_request'] ?? null;
    $handoffToken = $_SESSION['password_reset_handoff'] ?? null;

    if (!$tokenID || !$handoffToken) {

        $error = 'No active password reset request was found.';
    } else {

        try {

            // Connect to database.
            $database = new Database();
            $pdo = $database->getConnection();

            /*$sql = '
                SELECT
                    "tokenID",
                    "clickedAt",
                    "expiresAt",
                    "usedAt"
                FROM "PasswordResetToken"
                WHERE "tokenID" = :tokenID
                LIMIT 1
            ';*/

            $handoffHash = hash('sha256', $handoffToken);

            $sql = '
                SELECT
                    "tokenID",
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

            if (!$resetToken) {

                $error = 'Invalid or expired password reset request.';
            } elseif ($resetToken['usedAt'] !== null) {

                $error = 'This password reset has already been completed.';
            } elseif (strtotime($resetToken['expiresAt']) < time()) {

                $error = 'This password reset request has expired.';
            } elseif ($resetToken['clickedAt'] === null) {

                $error = 'Please click the password reset link sent to your email first.';
            } else {

                // Email link was clicked.
                $validToken = true;
            }
        } catch (PDOException $e) {

            error_log(
                'Original device reset validation failed: ' .
                    $e->getMessage()
            );

            $error = 'Unable to process the password reset.';
        }
    }
} elseif (
    $token === '' ||
    !preg_match('/^[a-f0-9]{64}$/', $token)
) {

    // Email link mode.
    $error = 'Invalid or expired password reset link.';
} else {

    try {

        // Connect to database.
        $database = new Database();
        $pdo = $database->getConnection();


        /*
         * Hash the raw token so that it can be
         * compared with the hash stored in the database.
         */
        $tokenHash = hash('sha256', $token);


        /*
         * Find a valid unused and unexpired token.
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


        if ($resetToken) {

            // Mark the reset link as clicked.
            // This tells the original device that
            // the email has been successfully verified.
            $updateSql = '
                UPDATE "PasswordResetToken"
                SET "clickedAt" = COALESCE("clickedAt", NOW())
                WHERE "tokenID" = :tokenID
            ';

            $updateStmt = $pdo->prepare($updateSql);

            $updateStmt->execute([
                ':tokenID' => $resetToken['tokenID']
            ]);

            // IMPORTANT:
            // Device B only verifies the email.
            // It must NOT show the password form.
            $emailVerified = true;
        } else {

            $error = 'Invalid or expired password reset link.';
        }
    } catch (PDOException $e) {

        error_log(
            'Reset token validation failed: ' .
                $e->getMessage()
        );

        $error = 'Unable to process the reset link.';
    }
}


/*
 * Display errors returned from the reset handler.
 */
if ($urlError === 'empty') {

    $error = 'Please enter and confirm your new password.';
} elseif ($urlError === 'mismatch') {

    $error = 'The passwords do not match.';
} elseif ($urlError === 'length') {

    $error = 'Password must be at least 8 characters long.';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Reset Password | Convergence Admin</title>


    <!-- Google Fonts -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@700&display=swap"
        rel="stylesheet">


    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- Reset Password CSS -->
    <link
        rel="stylesheet"
        href="../css/reset_password.css">

</head>


<body>


    <!-- Header -->
    <header class="site-header">

        <div class="brand">

            <img
                src="../Images/Convergence Logo.png"
                alt="Convergence logo"
                class="brand-logo">

            <div class="brand-text">

                <h1>CONVERGENCE</h1>

                <span>A MULTIDISCIPLINARY JOURNAL</span>

            </div>

        </div>

    </header>


    <!-- Page Banner -->
    <section class="page-banner">

        <h2>Reset Password</h2>

        <p>Create a new password for your administrator account.</p>

    </section>


    <!-- Main Content -->
    <main class="content-area">

        <div class="rp-card">


            <?php if ($emailVerified): ?>

                <p class="rp-intro">
                    Your email has been verified successfully.
                    Please return to the device where you requested
                    the password reset to continue.
                </p>

            <?php elseif (!$validToken): ?>


                <!-- Invalid Token -->

                <p class="error-message">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>


                <a
                    href="forgot_password.php"
                    class="btn-primary">
                    Request a New Reset Link
                </a>


            <?php else: ?>


                <!-- Valid Token -->

                <p class="rp-intro">

                    Enter and confirm your new password below.

                    Your reset link will only work once and will
                    expire after 30 minutes.

                </p>


                <form
                    action="reset_password_handler.php"
                    method="POST"
                    class="rp-form">


                    <!-- Reset Token -->
                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars(
                                    $token,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">


                    <!-- New Password -->
                    <div class="rp-field">

                        <label for="new_password">
                            New Password
                        </label>


                        <div class="pw">

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                autocomplete="new-password"
                                required>


                            <button
                                type="button"
                                class="toggle-password"
                                data-target="new_password"
                                aria-label="Show password"
                                aria-pressed="false">

                                <i
                                    class="fa-solid fa-eye-slash"
                                    aria-hidden="true"></i>

                            </button>

                        </div>


                        <p class="password-hint">
                            Password must be at least 8 characters long.
                        </p>

                    </div>


                    <!-- Confirm Password -->
                    <div class="rp-field">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>


                        <div class="pw">

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                required>


                            <button
                                type="button"
                                class="toggle-password"
                                data-target="confirm_password"
                                aria-label="Show password"
                                aria-pressed="false">

                                <i
                                    class="fa-solid fa-eye-slash"
                                    aria-hidden="true"></i>

                            </button>

                        </div>

                    </div>


                    <!-- Submit -->
                    <button
                        type="submit"
                        class="btn-primary">
                        Reset Password
                    </button>


                </form>


                <a
                    href="admin_login.php"
                    class="back-login">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Log In
                </a>


            <?php endif; ?>


        </div>

    </main>


    <!-- Footer -->
    <footer class="site-footer">

        <p>
            &copy; <?php echo date("Y"); ?> Convergence Journal.
            All rights reserved.
        </p>

    </footer>


    <!-- Password Visibility -->
    <script>
        document
            .querySelectorAll('.toggle-password')
            .forEach(function(button) {

                button.addEventListener(
                    'click',
                    function() {

                        var targetId =
                            this.getAttribute('data-target');

                        var passwordInput =
                            document.getElementById(targetId);

                        var show =
                            passwordInput.type === 'password';


                        passwordInput.type =
                            show ? 'text' : 'password';


                        this.setAttribute(
                            'aria-pressed',
                            show ? 'true' : 'false'
                        );


                        this.setAttribute(
                            'aria-label',
                            show ?
                            'Hide password' :
                            'Show password'
                        );


                        var icon =
                            this.querySelector('i');


                        icon.classList.toggle(
                            'fa-eye',
                            show
                        );


                        icon.classList.toggle(
                            'fa-eye-slash',
                            !show
                        );

                    }
                );

            });
    </script>


</body>

</html>