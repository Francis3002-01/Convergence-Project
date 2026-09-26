<aside class="sidebar">

    <div class="sidebar-logo">
        <h2>CONVERGENCE</h2>
        <p>A Multidisciplinary Journal</p>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php"
            class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="manage_journal.php"
            class="<?= basename($_SERVER['PHP_SELF']) === 'manage_journal.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-book"></i>
            <span>Manage Journals</span>
        </a>

        <a href="archive_admin.php"
            class="<?= basename($_SERVER['PHP_SELF']) === 'archive_admin.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-book-open"></i>
            <span>Archive</span>
        </a>

    </nav>

    <div class="sidebar-logout">
        <a href="logout_handler.php">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>

</aside>