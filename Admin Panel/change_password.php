<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../classes/Admin.php';

// Make sure the admin is logged in.
if (!Admin::isLoggedIn()) {
    header('Location: admin_login.php');
    exit;
}

// If password change is no longer required, go to dashboard.
if ($_SESSION['mustChangePassword'] !== true) {
    header('Location: dashboard.php');
    exit;
}

// Generate CSRF token if one does not already exist.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Change Password | Convergence Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@700&display=swap" rel="stylesheet">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <link rel="stylesheet" href="../css/change_password.css">

</head>

<body>

  <header class="site-header">

    <div class="brand">

      <img
        src="../Images/Convergence Logo.png"
        alt="Convergence logo"
        class="brand-logo"
      >

      <div class="brand-text">

        <h1>CONVERGENCE</h1>

        <span>A MULTIDISCIPLINARY JOURNAL</span>

      </div>

    </div>

  </header>


  <section class="page-banner">

    <h2>Change Password</h2>

  </section>


  <main class="content-area">

    <div class="cp-card">

      <?php if (isset($_GET['error'])): ?>

        <?php if ($_GET['error'] === 'current'): ?>

          <p class="error-message">
            Current password is incorrect.
          </p>

        <?php elseif ($_GET['error'] === 'mismatch'): ?>

          <p class="error-message">
            New passwords do not match.
          </p>

        <?php elseif ($_GET['error'] === 'length'): ?>

          <p class="error-message">
            New password must be at least 8 characters long.
          </p>

        <?php elseif ($_GET['error'] === 'same'): ?>

          <p class="error-message">
            New password must be different from your current password.
          </p>

        <?php elseif ($_GET['error'] === 'empty'): ?>

          <p class="error-message">
            Please fill in all password fields.
          </p>

        <?php elseif ($_GET['error'] === 'csrf'): ?>

          <p class="error-message">
            Invalid security token. Please try again.
          </p>

        <?php elseif ($_GET['error'] === 'database'): ?>

          <p class="error-message">
            Unable to change your password. Please try again.
          </p>

        <?php endif; ?>

      <?php endif; ?>


      <form
        action="change_password_handler.php"
        method="POST"
        class="cp-form"
      >

        <!-- CSRF token -->
        <input
          type="hidden"
          name="csrf_token"
          value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
        >


        <!-- Current Password -->
        <div class="cp-field">

          <label for="current_password">
            Current Password
          </label>

          <div class="pw">

            <input
              type="password"
              id="current_password"
              name="current_password"
              autocomplete="current-password"
              required
            >

            <button
              type="button"
              class="toggle-password"
              data-target="current_password"
              aria-label="Show password"
              aria-pressed="false"
            >
              <i class="fa-solid fa-eye" aria-hidden="true"></i>
            </button>

          </div>

        </div>


        <!-- New Password -->
        <div class="cp-field">

          <label for="new_password">
            New Password
          </label>

          <div class="pw">

            <input
              type="password"
              id="new_password"
              name="new_password"
              autocomplete="new-password"
              required
            >

            <button
              type="button"
              class="toggle-password"
              data-target="new_password"
              aria-label="Show password"
              aria-pressed="false"
            >
              <i class="fa-solid fa-eye" aria-hidden="true"></i>
            </button>

          </div>

        </div>


        <!-- Confirm Password -->
        <div class="cp-field">

          <label for="confirm_password">
            Confirm New Password
          </label>

          <div class="pw">

            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              autocomplete="new-password"
              required
            >

            <button
              type="button"
              class="toggle-password"
              data-target="confirm_password"
              aria-label="Show password"
              aria-pressed="false"
            >
              <i class="fa-solid fa-eye" aria-hidden="true"></i>
            </button>

          </div>

        </div>


        <button type="submit" class="btn-primary">
          Change Password
        </button>

      </form>

    </div>

  </main>


  <footer class="site-footer">

    <p>
      &copy; <?php echo date("Y"); ?> Convergence Journal.
      All rights reserved.
    </p>

  </footer>


  <script>

    document.querySelectorAll('.toggle-password').forEach(function(button) {

      button.addEventListener('click', function() {

        var targetId = this.getAttribute('data-target');
        var passwordInput = document.getElementById(targetId);

        var show = passwordInput.type === 'password';

        passwordInput.type = show ? 'text' : 'password';

        this.setAttribute(
          'aria-pressed',
          show ? 'true' : 'false'
        );

        this.setAttribute(
          'aria-label',
          show ? 'Hide password' : 'Show password'
        );

        var icon = this.querySelector('i');

        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);

      });

    });

  </script>

</body>

</html>