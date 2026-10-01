<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../Classes/Admin.php';

// Log the admin out.
Admin::logout();

// Redirect to the login page.
header('Location: admin_login.php');
exit;