<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot_password.php');
    exit;
}


// Get submitted email.
$email = trim($_POST['email'] ?? '');


// Generic response.
// This prevents revealing whether an administrator account exists.
$successMessage =
    'If an administrator account exists for that email address, a password reset link has been sent.';


// Invalid email receives the same generic response.
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

    header(
        'Location: forgot_password.php?message=' .
        urlencode($successMessage)
    );

    exit;
}


try {

    // Connect using the existing Database class.
    $database = new Database();
    $pdo = $database->getConnection();


    // Find administrator account.
    $sql = '
        SELECT
            "adminID",
            "email"
        FROM "Admin"
        WHERE "email" = :email
        LIMIT 1
    ';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':email' => $email
    ]);

    $admin = $stmt->fetch();


    /*
     * If the email does not exist,
     * return the same response.
     */
    if (!$admin) {

        header(
            'Location: forgot_password.php?message=' .
            urlencode($successMessage)
        );

        exit;
    }


    /*
     * Generate a cryptographically secure
     * 32-byte random token.
     *
     * bin2hex() converts it into 64 hexadecimal
     * characters.
     */
    $rawToken = bin2hex(random_bytes(32));


    /*
     * Store only the SHA-256 hash of the token.
     *
     * The raw token is only sent through the
     * email link.
     */
    $tokenHash = hash('sha256', $rawToken);


    /*
     * Token expires after 30 minutes.
     */
    $expiresAt = date(
        'Y-m-d H:i:sP',
        time() + (30 * 60)
    );


    /*
     * Delete previous unused tokens and
     * create the new token as one transaction.
     */
    $pdo->beginTransaction();

    try {

        // Remove previous unused tokens.
        $deleteSql = '
            DELETE FROM "PasswordResetToken"
            WHERE "adminID" = :adminID
            AND "usedAt" IS NULL
        ';

        $deleteStmt = $pdo->prepare($deleteSql);

        $deleteStmt->execute([
            ':adminID' => $admin['adminID']
        ]);


        // Insert new reset token.
        $insertSql = '
            INSERT INTO "PasswordResetToken"
            (
                "adminID",
                "tokenHash",
                "expiresAt"
            )
            VALUES
            (
                :adminID,
                :tokenHash,
                :expiresAt
            )
        ';

        $insertStmt = $pdo->prepare($insertSql);

        $insertStmt->execute([
            ':adminID' => $admin['adminID'],
            ':tokenHash' => $tokenHash,
            ':expiresAt' => $expiresAt
        ]);


        $pdo->commit();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }


    /*
     * Build password reset URL.
     *
     * This is correct for the current local
     * XAMPP project location.
     *
     * Change this URL when the website is
     * deployed to the actual hosting domain.
     */
    $resetUrl =
        'http://localhost/convergence/Admin%20Panel/reset_password.php?token=' .
        urlencode($rawToken);


    /*
     * Create PHPMailer instance.
     */
    $mail = new PHPMailer(true);


    // SMTP configuration.
    $mail->isSMTP();

    $mail->Host = $_ENV['MAIL_HOST'];

    $mail->SMTPAuth = true;

    $mail->Username = $_ENV['MAIL_USERNAME'];

    $mail->Password = $_ENV['MAIL_PASSWORD'];

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = (int) $_ENV['MAIL_PORT'];


    // Sender.
    $mail->setFrom(
        $_ENV['MAIL_FROM_ADDRESS'],
        $_ENV['MAIL_FROM_NAME']
    );


    // Administrator's email.
    $mail->addAddress($admin['email']);


    // HTML email.
    $mail->isHTML(true);

    $mail->Subject = 'Convergence Journal - Password Reset';


    /*
     * Email body.
     */
    $mail->Body = '
        <div style="
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
        ">

            <h2 style="color: #A5241E;">
                Convergence Journal
            </h2>

            <p>
                A password reset request was made for your
                administrator account.
            </p>

            <p>
                Click the button below to create a new password.
            </p>

            <p>
                <a
                    href="' .
                    htmlspecialchars(
                        $resetUrl,
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    '"
                    style="
                        display: inline-block;
                        padding: 12px 20px;
                        background: #A5241E;
                        color: #ffffff;
                        text-decoration: none;
                        border-radius: 6px;
                    "
                >
                    Reset Password
                </a>
            </p>

            <p>
                This link will expire in 30 minutes.
            </p>

            <p>
                If you did not request a password reset,
                you can safely ignore this email.
            </p>

        </div>
    ';


    /*
     * Plain-text alternative.
     */
    $mail->AltBody =
        "A password reset was requested for your " .
        "Convergence Journal administrator account.\n\n" .

        "Reset your password using this link:\n" .
        $resetUrl . "\n\n" .

        "This link expires in 30 minutes.";


    // Send email.
    $mail->send();


    /*
     * Always show the generic success message.
     */
    header(
        'Location: forgot_password.php?message=' .
        urlencode($successMessage)
    );

    exit;


} catch (Exception $e) {

    /*
     * Do not expose PHPMailer errors to the user.
     */
    error_log(
        'Password reset email failed: ' .
        $e->getMessage()
    );

    header(
        'Location: forgot_password.php?error=general'
    );

    exit;


} catch (PDOException $e) {

    /*
     * Do not expose database errors to the user.
     */
    error_log(
        'Password reset database error: ' .
        $e->getMessage()
    );

    header(
        'Location: forgot_password.php?error=general'
    );

    exit;
}