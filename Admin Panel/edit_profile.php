<?php
// Edit Profile page - for a logged-in admin
// Frontend only. Wire up the form action to your profile handler later.
?>

<!DOCTYPE html>

<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Edit Profile | Convergence Admin</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:wght@700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="../css/edit_profile.css">
</head>

<body>

  <!-- Site Header -->

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
    <h2>Edit Profile</h2>
    <p>Update your administrator account information.</p>
  </section>

  <!-- Main Content -->

  <main class="content-area">


<div class="profile-card">

  <form 
    action="edit_profile_handler.php" 
    method="POST" 
    enctype="multipart/form-data"
    class="profile-form"
  >

    <!-- Profile Picture -->
    <div class="profile-picture-section">

      <div class="profile-picture-preview">
        <span>A</span>
      </div>

      <div class="profile-picture-content">
        <label for="profile_picture" class="profile-picture-label">
          Profile Picture
        </label>

        <p class="profile-picture-help">
          Upload a new profile picture.
        </p>

        <input
          type="file"
          id="profile_picture"
          name="profile_picture"
          accept="image/*"
        >
      </div>

    </div>


    <!-- Username -->
    <div class="profile-field">
      <label for="username">Username</label>

      <input
        type="text"
        id="username"
        name="username"
        value="Admin"
        required
      >
    </div>


    <!-- Email -->
    <div class="profile-field">
      <label for="email">Email</label>

      <input
        type="email"
        id="email"
        name="email"
        placeholder="admin@example.com"
        required
      >
    </div>


    <!-- Buttons -->
    <div class="profile-actions">

      <a href="dashboard.php" class="btn-secondary">
        Cancel
      </a>

      <button type="submit" class="btn-primary">
        Save Changes
      </button>

    </div>

  </form>

</div>


  </main>

  <!-- Footer -->

  <footer class="site-footer">
    <p>
      &copy; <?php echo date("Y"); ?> Convergence Journal. All rights reserved.
    </p>
  </footer>

</body>
</html>
