<?php

$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password | Convergence Admin</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@700&display=swap"
        rel="stylesheet"
    >

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- Forgot Password CSS -->
    <link rel="stylesheet" href="../css/forgot_password.css">

</head>

<body>

    <!-- Header -->
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


    <!-- Page Banner -->
    <section class="page-banner">

        <h2>Forgot Password</h2>

    </section>


    <!-- Main Content -->
    <main class="content-area">

        <div class="fp-card">

            <?php if ($message !== ''): ?>

                <p class="success-message">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </p>

            <?php endif; ?>


            <?php if ($error === 'general'): ?>

                <p class="error-message">
                    Unable to process your password reset request.
                    Please try again later.
                </p>

            <?php endif; ?>


            <div class="fp-intro">

                <div class="fp-icon">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <h3>Reset Your Password</h3>

                <p>
                    Enter the email address associated with your administrator
                    account and we'll help you reset your password.
                </p>

            </div>


            <!-- Forgot Password Form -->
            <form
                action="forgot_password_handler.php"
                method="POST"
                class="fp-form"
            >

                <div class="fp-field">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@gmail.com"
                        autocomplete="email"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="btn-primary"
                >
                    <i class="fa-solid fa-paper-plane"></i>
                    Reset Password
                </button>


                <a
                    href="admin_login.php"
                    class="back-login"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to Log In
                </a>

            </form>

        </div>

    </main>


    <!-- Footer -->
    <footer class="site-footer">

        <p>
            &copy; <?php echo date("Y"); ?> Convergence Journal.
            All rights reserved.
        </p>

    </footer>

      <script>
        <?php if ($message !== ''): ?>
            setInterval(async () => {
                try {
                    const response = await fetch('check_reset_status.php', {
                        cache: 'no-store'
                    });

                    const data = await response.json();

                    if (data.status === 'clicked') {
                        window.location.href = 'reset_password.php?device=original';
                    }
                } catch (error) {
                    console.error('Reset status check failed:', error);
                }
            }, 2000);
        <?php endif; ?>
    </script>

</body>

</html>