<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Convergence</title>

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- Header CSS -->
    <link rel="stylesheet" href="css/header.css">

</head>

<body>

    <!-- HEADER -->
    <header>

        <div class="header-container">
            <!-- Clickable Logo and Brand -->
            <a href="home.php" class="brand">

                <div class="logo">
                    <img src="images/logo.png"alt="Convergence Logo">
                </div>

                <div class="brand-text">
                    <h1>CONVERGENCE</h1>

                    <p>A Multidisciplinary Journal</p>
                </div>
            </a>


            <!-- Search -->
            <form class="search-box">

                <input type="text" placeholder="Search journals..." aria-label="Search journals">

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
                    <a href="#">Journals</a>
                    <a href="#">About</a>
                    <a href="#">Contact</a>
                </div>
            </div>
        </nav>
    </header>


    <!-- MOBILE MENU JAVASCRIPT -->
    <script>
        const menuToggle = document.querySelector(".menu-toggle");
        const navLinks = document.querySelector(".nav-links");

        menuToggle.addEventListener("click", function () {
            navLinks.classList.toggle("open");
            const isOpen = navLinks.classList.contains("open");
            menuToggle.setAttribute("aria-expanded", isOpen);

        });

    </script>