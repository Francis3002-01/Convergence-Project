<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;


/*
|--------------------------------------------------------------------------
| Helper: Upload profile picture to Supabase Storage
|--------------------------------------------------------------------------
*/

function uploadProfilePictureToSupabase(
    string $temporaryFile,
    string $fileName,
    string $mimeType
): string {

    $supabaseUrl = rtrim($_ENV['SUPABASE_URL'] ?? '', '/');
    $bucket = $_ENV['SUPABASE_PICTURE_BUCKET'] ?? '';
    $secretKey = $_ENV['SUPABASE_SECRET_KEY'] ?? '';

    if (
        empty($supabaseUrl) ||
        empty($bucket) ||
        empty($secretKey)
    ) {
        throw new Exception(
            'Supabase Storage configuration is missing.'
        );
    }

    /*
     * Store images inside:
     *
     * profilePic/
     *     admins/
     *         admin_1_xxxxx.jpg
     */
    $storagePath = 'admins/' . $fileName;

    $uploadUrl =
        $supabaseUrl .
        '/storage/v1/object/' .
        rawurlencode($bucket) .
        '/' .
        $storagePath;

    $fileContents = file_get_contents($temporaryFile);

    if ($fileContents === false) {
        throw new Exception(
            'Unable to read the uploaded profile picture.'
        );
    }

    $curl = curl_init($uploadUrl);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,

        /*
         * Supabase Storage accepts POST for standard uploads.
         */
        CURLOPT_CUSTOMREQUEST => 'POST',

        CURLOPT_POSTFIELDS => $fileContents,

        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'apikey: ' . $secretKey,
            'Content-Type: ' . $mimeType,
            'x-upsert: false'
        ]
    ]);

    $response = curl_exec($curl);

    if ($response === false) {

        $curlError = curl_error($curl);

        curl_close($curl);

        throw new Exception(
            'Supabase upload failed: ' . $curlError
        );
    }

    $httpStatus = curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    if ($httpStatus < 200 || $httpStatus >= 300) {

        throw new Exception(
            'Supabase upload failed. HTTP status: ' .
            $httpStatus .
            '. Response: ' .
            $response
        );
    }

    /*
     * Public URL of the uploaded image.
     */
    return
        $supabaseUrl .
        '/storage/v1/object/public/' .
        rawurlencode($bucket) .
        '/' .
        $storagePath;
}


/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit_profile.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication check
|--------------------------------------------------------------------------
*/

if (!Admin::isLoggedIn()) {
    header('Location: admin_login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$database = new Database();
$pdo = $database->getConnection();

$adminID = $_SESSION['adminID'];


/*
|--------------------------------------------------------------------------
| Form values
|--------------------------------------------------------------------------
*/

$username = trim($_POST['username'] ?? '');
$newEmail = trim($_POST['email'] ?? '');


/*
|--------------------------------------------------------------------------
| Validate username
|--------------------------------------------------------------------------
*/

if ($username === '') {
    header('Location: edit_profile.php?error=required');
    exit;
}


/*
|--------------------------------------------------------------------------
| Get current admin information
|--------------------------------------------------------------------------
*/

try {

    $currentQuery = '
        SELECT
            "username",
            "email",
            "profilePic",
            "pendingEmail",
            "emailVerificationToken",
            "emailVerificationExpires"
        FROM "Admin"
        WHERE "adminID" = :adminID
        LIMIT 1
    ';

    $currentStmt = $pdo->prepare($currentQuery);

    $currentStmt->execute([
        ':adminID' => $adminID
    ]);

    $currentAdmin = $currentStmt->fetch();

    if (!$currentAdmin) {
        header('Location: edit_profile.php?error=database');
        exit;
    }

} catch (PDOException $e) {

    error_log(
        'Edit Profile Fetch Error: ' .
        $e->getMessage()
    );

    header('Location: edit_profile.php?error=database');
    exit;
}


$currentUsername = $currentAdmin['username'];
$currentEmail = $currentAdmin['email'];
$currentProfilePic = $currentAdmin['profilePic'] ?? null;


/*
|--------------------------------------------------------------------------
| Email validation
|--------------------------------------------------------------------------
*/

if ($newEmail === '') {
    $newEmail = $currentEmail;
}

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: edit_profile.php?error=email');
    exit;
}


/*
|--------------------------------------------------------------------------
| Determine whether email is changing
|--------------------------------------------------------------------------
*/

$emailChanged = (
    strcasecmp($newEmail, $currentEmail) !== 0
);


/*
|--------------------------------------------------------------------------
| Profile picture
|--------------------------------------------------------------------------
*/

$profilePicPath = $currentProfilePic;


/*
|--------------------------------------------------------------------------
| Handle profile picture upload
|--------------------------------------------------------------------------
*/

if (
    isset($_FILES['profilePic']) &&
    $_FILES['profilePic']['error'] !== UPLOAD_ERR_NO_FILE
) {

    /*
     * Upload error
     */
    if ($_FILES['profilePic']['error'] !== UPLOAD_ERR_OK) {
        header('Location: edit_profile.php?error=upload');
        exit;
    }


    /*
     * Maximum size: 2 MB
     */
    if ($_FILES['profilePic']['size'] > 2 * 1024 * 1024) {
        header('Location: edit_profile.php?error=size');
        exit;
    }


    /*
     * Verify that the file is actually an image.
     */
    $imageInfo = getimagesize(
        $_FILES['profilePic']['tmp_name']
    );

    if ($imageInfo === false) {
        header('Location: edit_profile.php?error=image');
        exit;
    }


    /*
     * Allowed image types.
     */
    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp'
    ];

    $imageType = $imageInfo[2];

    if (!isset($allowedTypes[$imageType])) {
        header('Location: edit_profile.php?error=type');
        exit;
    }


    /*
     * Get extension and MIME type.
     */
    $extension = $allowedTypes[$imageType];
    $mimeType = $imageInfo['mime'];


    /*
     * Generate a unique filename.
     *
     * Example:
     * admin_1_a83f92b7c123.jpg
     */
    $fileName =
        'admin_' .
        $adminID .
        '_' .
        bin2hex(random_bytes(8)) .
        '.' .
        $extension;


    /*
     * Upload directly to Supabase Storage.
     */
    try {

        $profilePicPath = uploadProfilePictureToSupabase(
            $_FILES['profilePic']['tmp_name'],
            $fileName,
            $mimeType
        );

    } catch (Exception $e) {

        error_log(
            'Profile Picture Upload Error: ' .
            $e->getMessage()
        );

        header('Location: edit_profile.php?error=upload');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Begin database transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Handle email change
    |--------------------------------------------------------------------------
    */

    if ($emailChanged) {

        /*
         * Generate raw token.
         *
         * This is sent through the email.
         */
        $verificationToken = bin2hex(
            random_bytes(32)
        );


        /*
         * Store only the SHA-256 hash in the database.
         */
        $verificationTokenHash = hash(
            'sha256',
            $verificationToken
        );


        /*
         * Verification expires after 30 minutes.
         */
        $verificationExpires = date(
            'Y-m-d H:i:s',
            time() + (30 * 60)
        );


        /*
         * Save pending email and verification data.
         *
         * The existing email remains the active email
         * until verification is completed.
         */
        $emailQuery = '
            UPDATE "Admin"
            SET
                "pendingEmail" = :pendingEmail,
                "emailVerificationToken" = :token,
                "emailVerificationExpires" = :expires
            WHERE "adminID" = :adminID
        ';

        $emailStmt = $pdo->prepare($emailQuery);

        $emailStmt->execute([
            ':pendingEmail' => $newEmail,
            ':token' => $verificationTokenHash,
            ':expires' => $verificationExpires,
            ':adminID' => $adminID
        ]);


        /*
         * Build verification URL.
         *
         * Local development URL.
         */
        $verificationUrl =
            'http://localhost/convergence/' .
            'Admin%20Panel/verify_email.php?token=' .
            urlencode($verificationToken);


        /*
         * Send verification email.
         */
        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->Host =
            $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username =
            $_ENV['MAIL_USERNAME'] ?? '';

        $mail->Password =
            $_ENV['MAIL_PASSWORD'] ?? '';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port =
            (int) ($_ENV['MAIL_PORT'] ?? 587);


        /*
         * Sender
         */
        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'],
            $_ENV['MAIL_FROM_NAME'] ?? 'Convergence Journal'
        );


        /*
         * Recipient
         */
        $mail->addAddress($newEmail);


        /*
         * Email format
         */
        $mail->isHTML(true);

        $mail->Subject =
            'Verify Your New Email Address - Convergence Journal';


        /*
         * Email body
         */
        $mail->Body = '
            <h2>Verify Your Email Address</h2>

            <p>
                You recently requested to change the email
                address associated with your Convergence Journal
                administrator account.
            </p>

            <p>
                Please click the button below to verify your
                new email address.
            </p>

            <p>
                <a
                    href="' . htmlspecialchars(
                        $verificationUrl,
                        ENT_QUOTES,
                        'UTF-8'
                    ) . '"
                    style="
                        display:inline-block;
                        padding:12px 20px;
                        background:#a5241e;
                        color:#ffffff;
                        text-decoration:none;
                        border-radius:5px;
                    "
                >
                    Verify Email Address
                </a>
            </p>

            <p>
                This verification link will expire in
                <strong>30 minutes</strong>.
            </p>

            <p>
                If you did not request this change, you can
                safely ignore this email.
            </p>
        ';


        /*
         * Plain-text fallback
         */
        $mail->AltBody =
            'Verify your new Convergence Journal email address ' .
            'using this link: ' .
            $verificationUrl;


        /*
         * Send
         */
        $mail->send();
    }


    /*
    |--------------------------------------------------------------------------
    | Update admin record
    |--------------------------------------------------------------------------
    */

    if ($emailChanged) {

        /*
         * Keep the CURRENT email.
         *
         * The new email is stored in pendingEmail until
         * verification is completed.
         */
        $updateQuery = '
            UPDATE "Admin"
            SET
                "username" = :username,
                "profilePic" = :profilePic
            WHERE "adminID" = :adminID
        ';

    } else {

        /*
         * No email change.
         */
        $updateQuery = '
            UPDATE "Admin"
            SET
                "username" = :username,
                "profilePic" = :profilePic
            WHERE "adminID" = :adminID
        ';
    }


    $updateStmt = $pdo->prepare($updateQuery);

    $updateStmt->execute([
        ':username' => $username,
        ':profilePic' => $profilePicPath,
        ':adminID' => $adminID
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Update current session
    |--------------------------------------------------------------------------
    */

    $_SESSION['username'] = $username;

    $_SESSION['profilePic'] = $profilePicPath;


    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    if ($emailChanged) {

        header(
            'Location: edit_profile.php?success=verification_sent'
        );

    } else {

        header(
            'Location: edit_profile.php?success=updated'
        );
    }

    exit;


} catch (Exception $e) {

    /*
     * PHPMailer errors
     */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Edit Profile Email Error: ' .
        $e->getMessage()
    );

    header(
        'Location: edit_profile.php?error=email_send'
    );

    exit;


} catch (PDOException $e) {

    /*
     * Database errors
     */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Edit Profile Database Error: ' .
        $e->getMessage()
    );

    header(
        'Location: edit_profile.php?error=database'
    );

    exit;
}