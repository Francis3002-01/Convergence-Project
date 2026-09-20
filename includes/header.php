    <header>

        <div class="header-container">
            <!-- Clickable Logo and Brand -->
            <a href="home.php" class="brand">

                <div class="logo">
                    <img src="Images/Convergence Logo.jpg" alt="Convergence Logo">
                </div>

                <div class="brand-text">
                    <h1>CONVERGENCE</h1>

                    <p>A Multidisciplinary Journal</p>
                </div>
            </a>

            <!-- Search -->
            <form class="search-box">
                <input type="text" placeholder="Search for articles..." aria-label="Search journals">
                <button type="submit" aria-label="Search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>
        </div>

        <!-- Navigation -->
        <nav>
            <div class="nav-container">
                <!-- Mobile Menu Button -->
                <button class="menu-toggle" type="button" aria-label="Open navigation menu" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <!-- Navigation Links -->
                <div class="nav-links">
                    <a href="home.php">Home</a>
                    <a href="about.php">About</a>
                    <a href="journal.php">Journal</a>
                    <a href="archive.php">Archive</a>
                    <a href="contact.php">Contact</a>
                </div>
            </div>
        </nav>
    </header>

    <!-- MOBILE MENU JAVASCRIPT -->
    <script>
        const menuToggle = document.querySelector(".menu-toggle");
        const navLinks = document.querySelector(".nav-links");

        menuToggle.addEventListener("click", function() {
            navLinks.classList.toggle("open");
            const isOpen = navLinks.classList.contains("open");
            menuToggle.setAttribute("aria-expanded", isOpen);

        });
    </script>