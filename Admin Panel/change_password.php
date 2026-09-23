<?php
// Change Password page - for a logged-in admin
// Frontend only. Wire up the form action to your change-password handler.
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
<link rel="stylesheet" href="../css/change_password.css">
</head>
<body>

  <header class="site-header">
    <div class="brand">
      <img src="../Images/Convergence Logo.png" alt="Convergence logo" class="brand-logo">
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
      <form action="change_password_handler.php" method="POST" class="cp-form">
        <div class="cp-field">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" required>
        </div>

        <div class="cp-field">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" required>
        </div>

        <div class="cp-field">
          <label for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <button type="submit" class="btn-primary">Change Password</button>
      </form>
    </div>
  </main>

  <footer class="site-footer">
    <p>&copy; <?php echo date("Y"); ?> Convergence Journal. All rights reserved.</p>
  </footer>

</body>
</html>