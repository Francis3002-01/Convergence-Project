<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

/*use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;*/


use Dotenv\Dotenv;

$envFile = dirname(__DIR__) . '/.env';

if (file_exists($envFile)) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}


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

    //$requestID = bin2hex(random_bytes(32));


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
    RETURNING "tokenID"
';

        $insertStmt = $pdo->prepare($insertSql);

        $insertStmt->execute([
            ':adminID' => $admin['adminID'],
            ':tokenHash' => $tokenHash,
            ':expiresAt' => $expiresAt
        ]);

        $tokenID = $insertStmt->fetchColumn();


        $pdo->commit();
        $_SESSION['password_reset_request'] = $tokenID;
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

    // Build password reset URL dynamically from the current request.

    $scheme = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    )
        ? 'https'
        : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? '';

    $scriptPath = str_replace(
        '\\',
        '/',
        dirname($_SERVER['SCRIPT_NAME'] ?? '')
    );

    // Remove the "Admin Panel" directory from the current path.
    $basePath = preg_replace(
        '#/Admin%20Panel$#i',
        '',
        $scriptPath
    );

    $basePath = preg_replace(
        '#/Admin Panel$#i',
        '',
        $basePath
    );

    $basePath = rtrim($basePath, '/');

    $resetUrl =
        $scheme .
        '://' .
        $host .
        $basePath .
        '/Admin%20Panel/reset_password.php?token=' .
        urlencode($rawToken);

    // TEMPORARY connectivity probe - remove after use.
    /*foreach (
        [
            ['smtp.gmail.com', 465],
            ['smtp.gmail.com', 587],
            ['google.com', 443],      // control: general outbound HTTPS
            ['api.brevo.com', 443],   // control: HTTPS email API
        ] as [$h, $p]
    ) {
        $t  = microtime(true);
        $fp = @stream_socket_client("tcp://$h:$p", $errno, $errstr, 8);
        $ms = round((microtime(true) - $t) * 1000);
        error_log(sprintf(
            'PROBE %s:%d %s in %dms errno=%s %s',
            $h,
            $p,
            $fp ? 'OK' : 'FAIL',
            $ms,
            $errno,
            $errstr
        ));
        if ($fp) fclose($fp);
    }
    error_log('PROBE DNS smtp.gmail.com A=' . json_encode(array_column(@dns_get_record('smtp.gmail.com', DNS_A) ?: [], 'ip')));*/

    // Get Mailjet credentials from environment variables.
    $apiKey = $_ENV['MAILJET_API_KEY'] ?? getenv('MAILJET_API_KEY');
    $secretKey = $_ENV['MAILJET_SECRET_KEY'] ?? getenv('MAILJET_SECRET_KEY');
    $fromAddress = $_ENV['MAIL_FROM_ADDRESS'] ?? getenv('MAIL_FROM_ADDRESS');
    $fromName = $_ENV['MAIL_FROM_NAME'] ?? getenv('MAIL_FROM_NAME');

    // Email HTML content.
    $htmlBody = '
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

    // Plain-text version of the email.
    $textBody =
        "A password reset was requested for your " .
        "Convergence Journal administrator account.\n\n" .
        "Reset your password using this link:\n" .
        $resetUrl . "\n\n" .
        "This link expires in 30 minutes.";

    // Prepare Mailjet API request.
    $payload = [
        'Messages' => [
            [
                'From' => [
                    'Email' => $fromAddress,
                    'Name' => $fromName
                ],
                'To' => [
                    [
                        'Email' => $admin['email']
                    ]
                ],
                'Subject' => 'Convergence Journal - Password Reset',
                'HTMLPart' => $htmlBody,
                'TextPart' => $textBody
            ]
        ]
    ];

    // Send through Mailjet HTTPS API.
    $ch = curl_init('https://api.mailjet.com/v3.1/send');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $apiKey . ':' . $secretKey,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // Check whether Mailjet accepted the email.
    if ($response === false ||$httpCode < 200 ||$httpCode >= 300) {
        error_log('Mailjet password reset failed. HTTP ' .$httpCode .'. cURL error: ' .$curlError);
        header('Location: forgot_password.php?error=general');
        exit;
    }

    /*
     * Always show the generic success message.
     */
    header('Location: forgot_password.php?message=' . urlencode($successMessage));

    exit;

} catch (PDOException $e) {
    error_log(
        'Password reset database error: ' .
            $e->getMessage()
    );

    header(
        'Location: forgot_password.php?error=general'
    );
    exit;
} catch (Exception $e) {
    error_log(
        'Password reset email failed: ' .
            $e->getMessage()
    );

    header(
        'Location: forgot_password.php?error=general'
    );
    exit;
}
