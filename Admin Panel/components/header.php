<?php

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Admin.php';

/*Database and Admin*/
$database = new Database();
$pdo = $database->getConnection();
$admin = new Admin($pdo);

/*Current Admin Information*/
$username = $_SESSION['username'] ?? 'Admin';
$profilePicture = $admin->getProfilePictureUrl();

?>

<header class="admin-header">
    <!-- Admin Information -->
    <div class="admin-profile">
        <div class="profile-picture">
            <?php if (!empty($profilePicture)): ?>
                <img src="<?= htmlspecialchars(
                                $profilePicture,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                    alt="Admin Profile Picture">
            <?php else: ?>

                <span>
                    <?= htmlspecialchars(
                        strtoupper(substr($username, 0, 1)),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </span>

            <?php endif; ?>

        </div>

        <span class="profile-name">
            <?= htmlspecialchars(
                $username,
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </span>

    </div>

    <!-- Ellipsis Button -->
    <button type="button" class="profile-menu" id="profileMenuButton" aria-label="Admin menu" aria-expanded="false">
        <i class="fa-solid fa-ellipsis-vertical"></i>
    </button>
</header>


<!-- Admin Profile Modal -->
<div class="profile-modal" id="profileModal" aria-hidden="true">
    <div class="profile-modal-content">

        <!-- Edit Profile -->
        <a href="edit_profile.php" class="profile-option">
            <i class="fa-solid fa-user-pen"></i>
            <span>Edit Profile</span>
        </a>

        <!-- Change Password -->
        <a href="change_password.php" class="profile-option">
            <i class="fa-solid fa-lock"></i>
            <span>Change Password</span>
        </a>
    </div>
</div>


<script>
    const profileMenuButton = document.getElementById('profileMenuButton');
    const profileModal = document.getElementById('profileModal');

    /*Open / Close Profile Menu*/
    profileMenuButton.addEventListener('click', function(event) {
        event.stopPropagation();
        const isOpen = profileModal.classList.toggle('active');
        profileMenuButton.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        profileModal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    });

    /*Close Menu When Clicking Outside*/
    document.addEventListener('click', function(event) {
        const modalContent =
            profileModal.querySelector(
                '.profile-modal-content'
            );

        if (profileModal.classList.contains('active') && !modalContent.contains(event.target) && !profileMenuButton.contains(event.target)) {
            profileModal.classList.remove('active');
            profileMenuButton.setAttribute('aria-expanded', 'false');
            profileModal.setAttribute('aria-hidden', 'true');
        }
    });
</script>