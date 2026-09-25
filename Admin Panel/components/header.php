<?php
// Make sure the session is available
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = $_SESSION['username'] ?? 'Admin';
$profilePicture = $_SESSION['profilePicture'] ?? '';
?>

<header class="admin-header">

    <!-- Admin Information -->
    <div class="admin-profile">

        <div class="profile-picture">

            <?php if (!empty($profilePicture)): ?>

                <img
                    src="<?php echo htmlspecialchars($profilePicture, ENT_QUOTES, 'UTF-8'); ?>"
                    alt="Admin Profile Picture"
                >

            <?php else: ?>

                <span>
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </span>

            <?php endif; ?>

        </div>

        <span class="profile-name">
            <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>
        </span>

    </div>


    <!-- Ellipsis Button -->
    <button
        type="button"
        class="profile-menu"
        id="profileMenuButton"
        aria-label="Admin menu"
        aria-expanded="false"
    >
        <i class="fa-solid fa-ellipsis-vertical"></i>
    </button>

</header>


<!-- Admin Profile Modal -->
<div
    class="profile-modal"
    id="profileModal"
    aria-hidden="true"
>

    <div class="profile-modal-content">

        <a
            href="edit_profile.php"
            class="profile-option"
        >
            <i class="fa-solid fa-user-pen"></i>

            <span>
                Edit Profile
            </span>
        </a>


        <a
            href="change_password.php"
            class="profile-option"
        >
            <i class="fa-solid fa-lock"></i>

            <span>
                Change Password
            </span>
        </a>

    </div>

</div>


<script>
    const profileMenuButton =
        document.getElementById('profileMenuButton');

    const profileModal =
        document.getElementById('profileModal');


    // Open / close the profile menu
    profileMenuButton.addEventListener('click', function (event) {

        event.stopPropagation();

        const isOpen =
            profileModal.classList.toggle('active');

        profileMenuButton.setAttribute(
            'aria-expanded',
            isOpen
        );

        profileModal.setAttribute(
            'aria-hidden',
            !isOpen
        );

    });


    // Close when clicking outside the menu
    document.addEventListener('click', function (event) {

        if (
            profileModal.classList.contains('active') &&
            !profileModal.querySelector('.profile-modal-content').contains(event.target) &&
            !profileMenuButton.contains(event.target)
        ) {

            profileModal.classList.remove('active');

            profileMenuButton.setAttribute(
                'aria-expanded',
                'false'
            );

            profileModal.setAttribute(
                'aria-hidden',
                'true'
            );

        }

    });
</script>