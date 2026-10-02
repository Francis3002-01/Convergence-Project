<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit_profile.php');
    exit;
}

if (!Admin::isLoggedIn()) {
    header('Location: admin_login.php');
    exit;
}

$adminID = $_SESSION['adminID'];

$usernameInput = trim($_POST['username'] ?? '');
$emailInput = trim($_POST['email'] ?? '');

try {

    $database = new Database();
    $pdo = $database->getConnection();

    /*
    |--------------------------------------------------------------------------
    | Get Current Admin Information
    |--------------------------------------------------------------------------
    */

    $currentQuery = '
        SELECT
            "username",
            "email",
            "profilePic",
            "pendingEmail",
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

    /*
    |--------------------------------------------------------------------------
    | Clear Expired Email Verification Data
    |--------------------------------------------------------------------------
    */

    if (
        !empty($currentAdmin['pendingEmail']) &&
        !empty($currentAdmin['emailVerificationExpires']) &&
        strtotime($currentAdmin['emailVerificationExpires']) <= time()
    ) {

        $clearExpiredQuery = '
            UPDATE "Admin"
            SET
                "pendingEmail" = NULL,
                "emailVerificationToken" = NULL,
                "emailVerificationExpires" = NULL
            WHERE "adminID" = :adminID
        ';

        $clearExpiredStmt = $pdo->prepare($clearExpiredQuery);

        $clearExpiredStmt->execute([
            ':adminID' => $adminID
        ]);

        // Update the local copy as well.
        $currentAdmin['pendingEmail'] = null;
        $currentAdmin['emailVerificationExpires'] = null;
    }

    /*
    |--------------------------------------------------------------------------
    | Username
    |--------------------------------------------------------------------------
    */

    $username = $usernameInput !== ''
        ? $usernameInput
        : $currentAdmin['username'];

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    */

    $emailChanged = false;

    if ($emailInput === '') {

        // Keep the current verified email.
        $emailInput = $currentAdmin['email'];

    } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {

        header('Location: edit_profile.php?error=email');
        exit;

    } elseif (
        strcasecmp(
            $emailInput,
            $currentAdmin['email']
        ) !== 0
    ) {

        $emailChanged = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Picture Upload
    |--------------------------------------------------------------------------
    */

    $profilePicPath = null;

    if (
        isset($_FILES['profile_picture']) &&
        $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            header('Location: edit_profile.php?error=upload');
            exit;
        }

        $maxFileSize = 2 * 1024 * 1024; // 2 MB

        if ($_FILES['profile_picture']['size'] > $maxFileSize) {
            header('Location: edit_profile.php?error=size');
            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Image
        |--------------------------------------------------------------------------
        */

        $imageInfo = getimagesize(
            $_FILES['profile_picture']['tmp_name']
        );

        if ($imageInfo === false) {
            header('Location: edit_profile.php?error=image');
            exit;
        }

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
        |--------------------------------------------------------------------------
        | Create Upload Directory
        |--------------------------------------------------------------------------
        */

        $uploadDirectory = __DIR__ . '/uploads/admin_profiles/';

        if (!is_dir($uploadDirectory)) {

            if (!mkdir($uploadDirectory, 0755, true)) {
                header('Location: edit_profile.php?error=upload');
                exit;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Generate Unique File Name
        |--------------------------------------------------------------------------
        */

        $extension = $allowedTypes[$imageType];

        $fileName =
            'admin_' .
            $adminID .
            '_' .
            bin2hex(random_bytes(8)) .
            '.' .
            $extension;

        $destination = $uploadDirectory . $fileName;

        /*
        |--------------------------------------------------------------------------
        | Move Uploaded File
        |--------------------------------------------------------------------------
        */

        if (!move_uploaded_file(
            $_FILES['profile_picture']['tmp_name'],
            $destination
        )) {

            header('Location: edit_profile.php?error=upload');
            exit;
        }

        $profilePicPath =
            'uploads/admin_profiles/' . $fileName;
    }

    /*
    |--------------------------------------------------------------------------
    | Begin Database Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Update Username / Profile Picture
    |--------------------------------------------------------------------------
    */

    if ($profilePicPath !== null) {

        $updateQuery = '
            UPDATE "Admin"
            SET
                "username" = :username,
                "profilePic" = :profilePic
            WHERE "adminID" = :adminID
        ';

        $updateStmt = $pdo->prepare($updateQuery);

        $updateStmt->execute([
            ':username' => $username,
            ':profilePic' => $profilePicPath,
            ':adminID' => $adminID
        ]);

    } else {

        $updateQuery = '
            UPDATE "Admin"
            SET
                "username" = :username
            WHERE "adminID" = :adminID
        ';

        $updateStmt = $pdo->prepare($updateQuery);

        $updateStmt->execute([
            ':username' => $username,
            ':adminID' => $adminID
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Email Change
    |--------------------------------------------------------------------------
    */

    if ($emailChanged) {

        /*
        |--------------------------------------------------------------------------
        | Generate Verification Token
        |--------------------------------------------------------------------------
        */

        $verificationToken = bin2hex(
            random_bytes(32)
        );

        /*
        |--------------------------------------------------------------------------
        | Store Only Token Hash in Database
        |--------------------------------------------------------------------------
        */

        $tokenHash = hash(
            'sha256',
            $verificationToken
        );

        /*
        |--------------------------------------------------------------------------
        | Token Expires in 30 Minutes
        |--------------------------------------------------------------------------
        */

        $expiration = date(
            'Y-m-d H:i:s',
            time() + (30 * 60)
        );

        /*
        |--------------------------------------------------------------------------
        | Store Pending Email
        |--------------------------------------------------------------------------
        */

        $pendingEmailQuery = '
            UPDATE "Admin"
            SET
                "pendingEmail" = :pendingEmail,
                "emailVerificationToken" = :token,
                "emailVerificationExpires" = :expires
            WHERE "adminID" = :adminID
        ';

        $pendingEmailStmt = $pdo->prepare(
            $pendingEmailQuery
        );

        $pendingEmailStmt->execute([
            ':pendingEmail' => $emailInput,
            ':token' => $tokenHash,
            ':expires' => $expiration,
            ':adminID' => $adminID
        ]);

        /*
        |--------------------------------------------------------------------------
        | Verification URL
        |--------------------------------------------------------------------------
        */

        $verificationUrl =
            'http://localhost/convergence/Admin%20Panel/verify_email.php?token=' .
            urlencode($verificationToken);

        /*
        |--------------------------------------------------------------------------
        | Configure PHPMailer
        |--------------------------------------------------------------------------
        */

        $mail = new PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = $_ENV['MAIL_HOST'];

        $mail->SMTPAuth = true;

        $mail->Username = $_ENV['MAIL_USERNAME'];

        $mail->Password = $_ENV['MAIL_PASSWORD'];

        $mail->SMTPSecure =
            PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port =
            (int) $_ENV['MAIL_PORT'];

        /*
        |--------------------------------------------------------------------------
        | Sender
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $_ENV['MAIL_FROM_ADDRESS'],
            $_ENV['MAIL_FROM_NAME']
        );

        /*
        |--------------------------------------------------------------------------
        | Recipient
        |--------------------------------------------------------------------------
        */

        $mail->addAddress($emailInput);

        /*
        |--------------------------------------------------------------------------
        | Email Content
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        $mail->Subject =
            'Verify Your New Email Address - Convergence Journal';

        $mail->Body = '
            <div style="
                font-family: Arial, sans-serif;
                line-height: 1.6;
                max-width: 600px;
                margin: 0 auto;
            ">

                <h2>Convergence Journal</h2>

                <p>
                    You requested to change the email address
                    associated with your administrator account.
                </p>

                <p>
                    Please click the button below to verify
                    your new email address.
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
                            background:#A5241E;
                            color:#ffffff;
                            text-decoration:none;
                            border-radius:5px;
                        "
                    >
                        Verify Email Address
                    </a>
                </p>

                <p>
                    This verification link will expire
                    in 30 minutes.
                </p>

                <p>
                    If you did not request this change,
                    you can safely ignore this email.
                </p>

            </div>
        ';

        $mail->AltBody =
            "You requested to change your Convergence Journal " .
            "administrator email address.\n\n" .

            "Verify your new email address using this link:\n\n" .

            $verificationUrl .

            "\n\nThis link will expire in 30 minutes.";

        /*
        |--------------------------------------------------------------------------
        | Send Email
        |--------------------------------------------------------------------------
        */

        $mail->send();

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

        if ($profilePicPath !== null) {
            $_SESSION['profilePic'] = $profilePicPath;
        }

        /*
        |--------------------------------------------------------------------------
        | Redirect
        |--------------------------------------------------------------------------
        */

        header(
            'Location: edit_profile.php?success=verification_sent'
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | No Email Change
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Update Session
    |--------------------------------------------------------------------------
    */

    $_SESSION['username'] = $username;

    if ($profilePicPath !== null) {
        $_SESSION['profilePic'] = $profilePicPath;
    }

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    */

    header(
        'Location: edit_profile.php?success=updated'
    );

    exit;

} catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | PHPMailer Error
    |--------------------------------------------------------------------------
    */

    if (isset($pdo) && $pdo->inTransaction()) {
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
    |--------------------------------------------------------------------------
    | Database Error
    |--------------------------------------------------------------------------
    */

    if (isset($pdo) && $pdo->inTransaction()) {
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