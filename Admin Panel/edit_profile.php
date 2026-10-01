<?php

// Edit Profile page - for a logged-in admin

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Classes/Admin.php';


// Make sure the admin is logged in
if (!Admin::isLoggedIn()) {
    header('Location: admin_login.php');
    exit;
}


// Connect to database
$database = new Database();
$pdo = $database->getConnection();


// Create Admin object
$admin = new Admin($pdo);


// Get current admin profile
$profile = $admin->getProfile();


// If the admin profile could not be found
if (!$profile) {
    header('Location: dashboard.php');
    exit;
}


// Current profile information
$currentUsername = $profile['username'] ?? '';
$currentEmail = $profile['email'] ?? '';
$currentProfilePic = $profile['profilePic'] ?? '';
$pendingEmail = $profile['pendingEmail'] ?? '';

// First letter for the default profile picture
$initial = strtoupper(substr($currentUsername, 0, 1));

?>

<!DOCTYPE html>

<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Edit Profile | Convergence Admin</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@700&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="../css/edit_profile.css">

  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

  <!-- Site Header -->

  <header class="site-header">

    <div class="brand">

      <img
        src="../Images/Convergence Logo.png"
        alt="Convergence logo"
        class="brand-logo">

      <div class="brand-text">
        <h1>CONVERGENCE</h1>
        <span>A MULTIDISCIPLINARY JOURNAL</span>
      </div>

    </div>

  </header>


  <!-- Page Banner -->

  <section class="page-banner">

    <h2>Edit Profile</h2>

    <p>
      Update your administrator account information.
    </p>

  </section>


  <!-- Main Content -->

  <main class="content-area">

    <div class="profile-card">

      <?php if (isset($_GET['success'])): ?>

    <div class="success-message">

        <?php
        switch ($_GET['success']) {

            case 'updated':
                echo 'Profile updated successfully.';
                break;

            case 'verification_sent':
                echo 'Your profile was updated. A verification link has been sent to your new email address.';
                break;

            default:
                echo 'Changes saved successfully.';
                break;
        }
        ?>

    </div>  

<?php endif; ?>


      <?php if (isset($_GET['error'])): ?>

        <div class="error-message">

          <?php

          switch ($_GET['error']) {

              case 'email':
                  echo 'Please enter a valid email address.';
                  break;

              case 'email_send':
                  echo 'Your changes could not be completed because the verification email could not be sent. Please try again.';
                  break;

              case 'upload':
                  echo 'There was a problem uploading the profile picture.';
                  break;

              case 'size':
                  echo 'The profile picture must be 2 MB or smaller.';
                  break;

              case 'image':
                  echo 'The uploaded file is not a valid image.';
                  break;

              case 'type':
                  echo 'Only JPG, PNG, and WebP images are allowed.';
                  break;

              case 'database':
                  echo 'Unable to update your profile. Please try again.';
                  break;

              default:
                  echo 'Something went wrong. Please try again.';
                  break;
          }

          ?>

        </div>

      <?php endif; ?>


      <form
        action="edit_profile_handler.php"
        method="POST"
        enctype="multipart/form-data"
        class="profile-form">


        <!-- Profile Picture -->

        <div class="profile-picture-section">

          <div class="profile-picture-preview">

            <?php if (!empty($currentProfilePic)): ?>

              <img
                src="<?php echo htmlspecialchars($currentProfilePic, ENT_QUOTES, 'UTF-8'); ?>"
                alt="Admin Profile Picture">

            <?php else: ?>

              <span>
                <?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?>
              </span>

            <?php endif; ?>

          </div>


          <div class="profile-picture-content">

            <span class="profile-picture-label">
              Profile Picture
            </span>

            <p class="profile-picture-help">
              Upload a new profile picture.
            </p>

            <label
              for="profile_picture"
              class="upload-button">

              <i class="fa-solid fa-upload"></i>
              Choose Picture

            </label>

            <input
              type="file"
              id="profile_picture"
              name="profile_picture"
              accept="image/jpeg,image/png,image/webp">

          </div>

        </div>


        <!-- Username -->

        <div class="profile-field">

          <label for="username">
            Username
          </label>

          <input
            type="text"
            id="username"
            name="username"
            value="<?php echo htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8'); ?>"
            placeholder="Enter username">

        </div>


        <!-- Email -->

        <div class="profile-field">

    <label for="email">
        Email
    </label>

    <input
        type="email"
        id="email"
        name="email"
        value="<?php echo htmlspecialchars(
            $currentEmail,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
        placeholder="Enter email"
    >

    <?php if (!empty($pendingEmail)): ?>

        <p style="
            margin-top: 8px;
            color: #a5241e;
            font-size: 13px;
        ">

            Verification pending for:

            <strong>
                <?php
                echo htmlspecialchars(
                    $pendingEmail,
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </strong>

        </p>

    <?php endif; ?>

</div>


        <!-- Buttons -->

        <div class="profile-actions">

          <a
            href="dashboard.php"
            class="btn-secondary">
            Cancel
          </a>

          <button
            type="submit"
            class="btn-primary">
            Save Changes
          </button>

        </div>

      </form>

    </div>

  </main>


  <!-- Footer -->

  <footer class="site-footer">

    <p>
      &copy; <?php echo date("Y"); ?>
      Convergence Journal. All rights reserved.
    </p>

  </footer>

</body>

</html>