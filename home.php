<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage | Convergence</title>
    
    <!--External CSS-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="css/transition.css">
    <link rel="stylesheet" href="css/home.css">
    <link rel="stylesheet" href="includes_css/footer.css">
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <main>
        <!--HERO-->

        <section class="hero">
            <div class="hero-container">
                <h2>Convergence</h2>
                <p class="hero-description">
                    Exploring ideas and research across the humanities, social sciences, and natural sciences
                </p>

                <!-- Latest Issue -->
                <div class="latest-issue">
                    <span class="latest-issue-label">LATEST ISSUE</span>
                    <div class="latest-issue-title">2026: Volume 11, Number 1</div>
                </div>
                <a href="journal.php" class="hero-button">VIEW LATEST ARTICLES</a>
            </div>
        </section>

        <!--EXPLORE CONVERGENCE-->
        <section class="explore-section">
            <h2 class="section-title">Explore Convergence</h2>
            <div class="explore-grid">

                <!-- Journals -->
                <article class="explore-card">
                    <span class="explore-number">01</span>
                    <h3>Journals</h3>
                    <p>View the current issue and explore individual journal articles</p>
                    <a href="journal.php" class="explore-link">Browse Journals →</a>
                </article>

                <!-- Archive -->
                <article class="explore-card">
                    <span class="explore-number">02</span>
                    <h3>Archive</h3>
                    <p>Explore previous publications and access earlier journal issues</p>
                    <a href="archive.php" class="explore-link">Explore Archive →</a>
                </article>

                <!-- About -->
                <article class="explore-card">
                    <span class="explore-number">03</span>
                    <h3>About</h3>
                    <p>Learn about Convergence, its purpose, and its multidisciplinary scope</p>
                    <a href="about.php" class="explore-link">Learn More →</a>
                </article>
            </div>
        </section>

        <!--ABOUT INTRODUCTION-->
        <section class="about-intro">
            <div class="about-intro-container">
                <h2>Connecting perspectives across disciplines</h2>
                <p>
                    Convergence is devoted to the publication of
                    inter and multidisciplinary interests of scholars
                    in the humanities, social sciences, and natural
                    sciences
                </p>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>