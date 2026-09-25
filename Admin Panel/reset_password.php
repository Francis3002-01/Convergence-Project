<?php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

$token = $_GET['token'] ?? '';

$error = '';
$validToken = false;

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $error = 'Invalid or expired password reset link.';
} else {

    try {

        $database = new Database();
        $pdo = $database->getConnection();

        $tokenHash = hash('sha256', $token);

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
            $validToken = true;
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

?>

<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Reset Password | Convergence Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@700&display=swap"
    rel="stylesheet"
  >

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
  >

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

    <h2>Reset Password</h2>

  </section>


  <main class="content-area">

    <div class="cp-card">

      <?php if (!$validToken): ?>

        <p class="error-message">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <a href="forgot_password.php" class="btn-primary">
          Request a New Reset Link
        </a>

      <?php else: ?>

        <form
          action="reset_password_handler.php"
          method="POST"
          class="cp-form"
        >

          <input
            type="hidden"
            name="token"
            value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
          >

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


          <button
            type="submit"
            class="btn-primary"
          >
            Reset Password
          </button>

        </form>

      <?php endif; ?>

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