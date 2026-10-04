<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Classes/Admin.php';
require_once __DIR__ . '/../vendor/autoload.php';

error_log('EDIT PROFILE HANDLER REACHED');

error_log(
    'FILES: ' . print_r($_FILES, true)
);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;


/*
|--------------------------------------------------------------------------
| Helper: Upload Profile Picture to Supabase Storage
|--------------------------------------------------------------------------
*/

function uploadProfilePictureToSupabase(
    string $temporaryFile,
    string $fileName,
    string $mimeType
): string {

    $supabaseUrl = rtrim(
        $_ENV['SUPABASE_URL'] ?? '',
        '/'
    );

    $bucket = $_ENV['SUPABASE_PICTURE_BUCKET'] ?? '';

    $secretKey = $_ENV['SUPABASE_SECRET_KEY'] ?? '';

    if (
        $supabaseUrl === '' ||
        $bucket === '' ||
        $secretKey === ''
    ) {
        throw new \Exception(
            'Supabase Storage configuration is missing.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Fixed Storage Path
    |--------------------------------------------------------------------------
    |
    | Every new profile picture replaces this file.
    |
    */

    $storagePath = 'admin/profile.jpg';

    $uploadUrl =
        $supabaseUrl .
        '/storage/v1/object/' .
        rawurlencode($bucket) .
        '/' .
        $storagePath;

    if (!is_file($temporaryFile)) {
        throw new \Exception(
            'Temporary uploaded image file does not exist.'
        );
    }

    $fileContents = file_get_contents(
        $temporaryFile
    );

    if ($fileContents === false) {
        throw new \Exception(
            'Unable to read the uploaded image.'
        );
    }

    $curl = curl_init($uploadUrl);

    if ($curl === false) {
        throw new \Exception(
            'Unable to initialize cURL.'
        );
    }

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_CUSTOMREQUEST => 'POST',

        CURLOPT_POSTFIELDS => $fileContents,

        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'apikey: ' . $secretKey,
            'Content-Type: ' . $mimeType,
            'x-upsert: true'
        ]
    ]);

    $response = curl_exec($curl);

    if ($response === false) {

        $curlError = curl_error($curl);

        curl_close($curl);

        throw new \Exception(
            'cURL error: ' . $curlError
        );
    }

    $httpStatus = curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);


    /*
    |--------------------------------------------------------------------------
    | Temporary Debug Logging
    |--------------------------------------------------------------------------
    */

    error_log(
        'Supabase Storage HTTP Status: ' .
            $httpStatus
    );

    error_log(
        'Supabase Storage Response: ' .
            $response
    );


    /*
    |--------------------------------------------------------------------------
    | Check Supabase Response
    |--------------------------------------------------------------------------
    */

    if (
        $httpStatus < 200 ||
        $httpStatus >= 300
    ) {
        throw new \Exception(
            'Supabase Storage upload failed. HTTP ' .
                $httpStatus .
                ': ' .
                $response
        );
    }

    return $storagePath;
}


/*
|--------------------------------------------------------------------------
| Only Allow POST Requests
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    header(
        'Location: edit_profile.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!Admin::isLoggedIn()) {

    header(
        'Location: admin_login.php'
    );

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
| Form Values
|--------------------------------------------------------------------------
*/

$username = trim(
    $_POST['username'] ?? ''
);

$newEmail = trim(
    $_POST['email'] ?? ''
);


/*
|--------------------------------------------------------------------------
| Validate Username
|--------------------------------------------------------------------------
*/

if ($username === '') {

    header(
        'Location: edit_profile.php?error=required'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Current Admin Information
|--------------------------------------------------------------------------
*/

try {

    $currentQuery = '
        SELECT
            "username",
            "email",
            "pendingEmail",
            "emailVerificationToken",
            "emailVerificationExpires"
        FROM "Admin"
        WHERE "adminID" = :adminID
        LIMIT 1
    ';

    $currentStmt = $pdo->prepare(
        $currentQuery
    );

    $currentStmt->execute([
        ':adminID' => $adminID
    ]);

    $currentAdmin = $currentStmt->fetch();

    if (!$currentAdmin) {

        header(
            'Location: edit_profile.php?error=database'
        );

        exit;
    }
} catch (PDOException $e) {

    error_log(
        'Edit Profile Fetch Error: ' .
            $e->getMessage()
    );

    header(
        'Location: edit_profile.php?error=database'
    );

    exit;
}


$currentEmail = $currentAdmin['email'];


/*
|--------------------------------------------------------------------------
| Email Validation
|--------------------------------------------------------------------------
*/

if ($newEmail === '') {

    $newEmail = $currentEmail;
}

if (
    !filter_var(
        $newEmail,
        FILTER_VALIDATE_EMAIL
    )
) {

    header(
        'Location: edit_profile.php?error=email'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Determine Whether Email Changed
|--------------------------------------------------------------------------
*/

$emailChanged = (
    strcasecmp(
        $newEmail,
        $currentEmail
    ) !== 0
);


/*
|--------------------------------------------------------------------------
| Handle Profile Picture
|--------------------------------------------------------------------------
*/

if (
    isset($_FILES['profile_picture']) &&
    $_FILES['profile_picture']['error'] !==
    UPLOAD_ERR_NO_FILE
) {

    /*
    |--------------------------------------------------------------------------
    | Debug Log
    |--------------------------------------------------------------------------
    */

    error_log(
        'PROFILE PICTURE BLOCK REACHED'
    );


    /*
    |--------------------------------------------------------------------------
    | Check Upload Error
    |--------------------------------------------------------------------------
    */

    if (
        $_FILES['profile_picture']['error'] !==
        UPLOAD_ERR_OK
    ) {

        error_log(
            'Profile Picture Upload Error Code: ' .
                $_FILES['profile_picture']['error']
        );

        header(
            'Location: edit_profile.php?error=upload'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Check File Size
    |--------------------------------------------------------------------------
    */

    if (
        $_FILES['profile_picture']['size'] >
        2 * 1024 * 1024
    ) {

        header(
            'Location: edit_profile.php?error=size'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Verify Image
    |--------------------------------------------------------------------------
    */

    $imageInfo = getimagesize(
        $_FILES['profile_picture']['tmp_name']
    );

    if ($imageInfo === false) {

        header(
            'Location: edit_profile.php?error=image'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Only Allow JPEG
    |--------------------------------------------------------------------------
    |
    | The Supabase object uses the fixed filename:
    |
    | admin/profile.jpg
    |
    | Therefore the uploaded file must actually be JPEG.
    |
    */

    $imageType = $imageInfo[2];

    if (
        $imageType !== IMAGETYPE_JPEG
    ) {

        header(
            'Location: edit_profile.php?error=type'
        );

        exit;
    }


    $mimeType = 'image/jpeg';

    $fileName = 'admin/profile.jpg';


    /*
    |--------------------------------------------------------------------------
    | Upload to Supabase
    |--------------------------------------------------------------------------
    */

    try {

        error_log(
            'ABOUT TO UPLOAD PROFILE PICTURE TO SUPABASE'
        );

        uploadProfilePictureToSupabase(
            $_FILES['profile_picture']['tmp_name'],
            $fileName,
            $mimeType
        );

        error_log(
            'PROFILE PICTURE UPLOAD COMPLETED'
        );
    } catch (\Exception $e) {

        error_log(
            'Profile Picture Upload Error: ' .
                $e->getMessage()
        );

        header(
            'Location: edit_profile.php?error=upload'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Begin Database Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Email Change
    |--------------------------------------------------------------------------
    */

    if ($emailChanged) {

        /*
        | Generate verification token
        */

        $verificationToken = bin2hex(
            random_bytes(32)
        );


        /*
        | Store only the hashed token
        */

        $verificationTokenHash = hash(
            'sha256',
            $verificationToken
        );


        /*
        | Token expires after 30 minutes
        */

        $verificationExpires = date(
            'Y-m-d H:i:s',
            time() + (30 * 60)
        );


        /*
        | Save pending email
        */

        $emailQuery = '
            UPDATE "Admin"
            SET
                "pendingEmail" = :pendingEmail,
                "emailVerificationToken" = :token,
                "emailVerificationExpires" = :expires
            WHERE "adminID" = :adminID
        ';

        $emailStmt = $pdo->prepare(
            $emailQuery
        );

        $emailStmt->execute([
            ':pendingEmail' => $newEmail,
            ':token' => $verificationTokenHash,
            ':expires' => $verificationExpires,
            ':adminID' => $adminID
        ]);


        /*
        |--------------------------------------------------------------------------
        | Verification URL
        |--------------------------------------------------------------------------
        */

        $scheme = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        )
            ? 'https'
            : 'http';

        $host = $_SERVER['HTTP_HOST'];

        $projectPath = '/Convergence%20Project';

        $verificationUrl =
            $scheme .
            '://' .
            $host .
            $projectPath .
            '/Admin%20Panel/verify_email.php?token=' .
            urlencode($verificationToken);

        /*
        |--------------------------------------------------------------------------
        | Configure PHPMailer
        |--------------------------------------------------------------------------
        */

        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->Host =
            $_ENV['MAIL_HOST'] ??
            'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username =
            $_ENV['MAIL_USERNAME'] ??
            '';

        $mail->Password =
            $_ENV['MAIL_PASSWORD'] ??
            '';

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port =
            (int) (
                $_ENV['MAIL_PORT'] ??
                587
            );


        /*
        |--------------------------------------------------------------------------
        | Sender
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'] ??
                '',
            $_ENV['MAIL_FROM_NAME'] ??
                'Convergence Journal'
        );


        /*
        |--------------------------------------------------------------------------
        | Recipient
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $newEmail
        );


        /*
        |--------------------------------------------------------------------------
        | Email Content
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        $mail->Subject =
            'Verify Your New Email Address - Convergence Journal';

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
                    href="' .
            htmlspecialchars(
                $verificationUrl,
                ENT_QUOTES,
                'UTF-8'
            ) .
            '"
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
                If you did not request this change,
                you can safely ignore this email.
            </p>
        ';


        /*
        |--------------------------------------------------------------------------
        | Plain Text Email
        |--------------------------------------------------------------------------
        */

        $mail->AltBody =
            'Verify your new Convergence Journal email address ' .
            'using this link: ' .
            $verificationUrl;


        /*
        |--------------------------------------------------------------------------
        | Send Email
        |--------------------------------------------------------------------------
        */

        $mail->send();
    }


    /*
    |--------------------------------------------------------------------------
    | Update Username
    |--------------------------------------------------------------------------
    |
    | Profile picture is stored in Supabase Storage,
    | so there is no profilePic database column update here.
    |
    */

    $updateQuery = '
        UPDATE "Admin"
        SET
            "username" = :username
        WHERE "adminID" = :adminID
    ';

    $updateStmt = $pdo->prepare(
        $updateQuery
    );

    $updateStmt->execute([
        ':username' => $username,
        ':adminID' => $adminID
    ]);


    /*
    |--------------------------------------------------------------------------
    | Commit Database Changes
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Update Session
    |--------------------------------------------------------------------------
    */

    $_SESSION['username'] = $username;


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


    /*
|--------------------------------------------------------------------------
| PHPMailer Exception
|--------------------------------------------------------------------------
*/
} catch (PHPMailerException $e) {

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


    /*
|--------------------------------------------------------------------------
| Database Exception
|--------------------------------------------------------------------------
*/
} catch (PDOException $e) {

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
