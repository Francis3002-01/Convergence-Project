<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Prevent unauthorized access
if (!isset($_SESSION['adminID'])) {
    header('Location: admin_login.php');
    exit;
}
