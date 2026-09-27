<?php

// Edit Profile Handler
// Processes username, email, and profile picture updates.

require_once '../config/session.php';
require_once '../config/database.php';


// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit_profile.php');
    exit;
}


// Make sure an admin is logged in
if (!isset($_SESSION['adminID'])) {
    header('Location: admin_login.php');
    exit;
}


// Get the logged-in admin ID
$adminID = $_SESSION['adminID'];


// Get submitted information
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');


try {

    // Connect to database
    $database = new Database();
    $pdo = $database->getConnection();


    /*
     * Get the current admin information.
     *
     * This allows empty fields to keep their
     * existing values.
     */
    $currentQuery = '
        SELECT
            "username",
            "email",
            "profilePic"
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
     * If the username field is empty,
     * keep the existing username.
     */
    if ($username === '') {
        $username = $currentAdmin['username'];
    }


    /*
     * If the email field is empty,
     * keep the existing email.
     */
    if ($email === '') {
        $email = $currentAdmin['email'];
    }


    /*
     * Validate the email only if a new email
     * was actually entered.
     *
     * Since an empty email keeps the old value,
     * there is no need to validate an empty field.
     */
    if (
        trim($_POST['email'] ?? '') !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        header('Location: edit_profile.php?error=email');
        exit;
    }


    /*
     * Start with no new profile picture.
     */
    $profilePicPath = null;


    /*
     * Handle profile picture upload if a file was selected.
     */
    if (
        isset($_FILES['profile_picture']) &&
        $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        // Check for upload errors
        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            header('Location: edit_profile.php?error=upload');
            exit;
        }


        // Maximum file size: 2 MB
        $maxFileSize = 2 * 1024 * 1024;

        if ($_FILES['profile_picture']['size'] > $maxFileSize) {
            header('Location: edit_profile.php?error=size');
            exit;
        }


        // Verify that the uploaded file is actually an image
        $imageInfo = getimagesize(
            $_FILES['profile_picture']['tmp_name']
        );

        if ($imageInfo === false) {
            header('Location: edit_profile.php?error=image');
            exit;
        }


        // Allowed image types
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
         * Create the upload directory if it doesn't exist.
         *
         * This creates:
         * Admin Panel/uploads/admin_profiles/
         */
        $uploadDirectory = __DIR__ . '/uploads/admin_profiles/';

        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0755, true);
        }


        /*
         * Generate a unique filename.
         *
         * Example:
         * admin_1_68d2f8a91c2e4.jpg
         */
        $extension = $allowedTypes[$imageType];

        $fileName =
            'admin_' .
            $adminID .
            '_' .
            uniqid() .
            '.' .
            $extension;

        $destination = $uploadDirectory . $fileName;


        /*
         * Move the uploaded file into the upload directory.
         */
        if (!move_uploaded_file(
            $_FILES['profile_picture']['tmp_name'],
            $destination
        )) {
            header('Location: edit_profile.php?error=upload');
            exit;
        }


        /*
         * Path stored in the database.
         */
        $profilePicPath =
            'uploads/admin_profiles/' . $fileName;
    }


    /*
     * Update username and email.
     *
     * These values are either:
     * - the newly entered values, or
     * - the existing values if the fields were empty.
     */
    if ($profilePicPath !== null) {

        /*
         * A new profile picture was uploaded.
         */
        $updateQuery = '
            UPDATE "Admin"
            SET
                "username" = :username,
                "email" = :email,
                "profilePic" = :profilePic
            WHERE "adminID" = :adminID
        ';

        $updateStmt = $pdo->prepare($updateQuery);

        $updateStmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':profilePic' => $profilePicPath,
            ':adminID' => $adminID
        ]);

    } else {

        /*
         * No new profile picture was uploaded.
         * Keep the existing profile picture.
         */
        $updateQuery = '
            UPDATE "Admin"
            SET
                "username" = :username,
                "email" = :email
            WHERE "adminID" = :adminID
        ';

        $updateStmt = $pdo->prepare($updateQuery);

        $updateStmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':adminID' => $adminID
        ]);
    }


    /*
     * Update the session so the changes are
     * immediately reflected throughout the admin panel.
     */
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;


    if ($profilePicPath !== null) {
        $_SESSION['profilePic'] = $profilePicPath;
    }


    /*
     * Redirect back to the edit profile page
     * with a success message.
     */
    header('Location: edit_profile.php?success=updated');
    exit;


} catch (PDOException $e) {

    /*
     * Log the actual database error rather than
     * displaying it to the user.
     */
    error_log('Edit Profile Error: ' . $e->getMessage());

    header('Location: edit_profile.php?error=database');
    exit;
}