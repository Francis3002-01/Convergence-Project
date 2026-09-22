<?php
$email = htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$error = ''; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sign In | Convergence Journal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../css/admin_login.css">
</head>
<body>

<header>
  <div class="header-container">
    <a href="../home.php" class="brand">

      <div class="logo">
        <img src="../Images/Convergence Logo.jpg" alt="Convergence Logo">
      </div>

      <div class="brand-text">
        <h1>CONVERGENCE</h1>

        <p>A Multidisciplinary Journal</p>
      </div>
    </a>
  </div>
</header>

<main>
  <section class="card" aria-labelledby="t">
    <p class="eyebrow">Convergence Journal</p>
    <h2 id="t">Log In</h2>
    <p class="lead">Access the Admin Dashboard </p>

    <form id="f" method="post" action="admin_login_process.php" novalidate>

          <?php if (isset($_GET['error']) && $_GET['error'] === 'invalid'): ?>
          <p class="error-message">Invalid email or password.</p>
          <?php endif; ?>

      <div class="field" id="fe">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="email" placeholder="you@gmail.com" value="<?= $email ?>" required>
        <div class="err" id="ee">Enter a valid email address.</div>
      </div>

      <div class="field" id="fp">
        <label for="pw">Password</label>
        <div class="pw">
          <input id="pw" name="password" type="password" autocomplete="current-password" required>
          <button type="button" id="tg" aria-label="Show password" aria-pressed="false">
            <i class="fa-solid fa-eye" aria-hidden="true"></i>
          </button>
        </div>
        <div class="err" id="ep">Enter your password.</div>
      </div>

      <div class="row">
        <a href="#">Forgot password?</a>
      </div>

      <button class="btn" type="submit">
        <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
        Log In
      </button>

      <?php if ($error !== ''): ?>
        <div class="msg show no" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
    </form>
  </section>
</main>

<script>
(function () {
  var f = document.getElementById('f'), em = document.getElementById('email'),
      pw = document.getElementById('pw'), tg = document.getElementById('tg');

  tg.addEventListener('click', function () {
    var show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    tg.setAttribute('aria-pressed', show);
    tg.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    var icon = tg.querySelector('i');
    icon.classList.toggle('fa-eye', !show);
    icon.classList.toggle('fa-eye-slash', show);
  });

  function flag(id, input, bad) {
    document.getElementById(id).classList.toggle('bad', bad);
    input.setAttribute('aria-invalid', bad);
  }

  f.addEventListener('submit', function (e) {
    var badE = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value.trim()), 
    badP = !pw.value;
    flag('fe', em, badE); flag('fp', pw, badP);
    if (badE || badP) { e.preventDefault(); (badE ? em : pw).focus(); }
  });
})();
</script>
</body>
</html>