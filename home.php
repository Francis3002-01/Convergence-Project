<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

$currentIssue = null;

try {
    $stmt = $pdo->prepare(
        'SELECT "year", "volume", "number"
         FROM "PublicationIssue"
         WHERE "is_current" = TRUE
           AND "is_draft" = FALSE
         LIMIT 1'
    );

    $stmt->execute();

    $currentIssue = $stmt->fetch(PDO::FETCH_ASSOC);
} 

catch (PDOException $e) {
    error_log($e->getMessage());
}
?>

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
    <link rel="icon" type="image/jpeg" href="Images/Convergence Logo.png">
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
                    <div class="latest-issue-title">
                        <?php if ($currentIssue): ?>
                            <?= htmlspecialchars($currentIssue['year']) ?>:
                            Volume <?= htmlspecialchars($currentIssue['volume']) ?>,
                            Number <?= htmlspecialchars($currentIssue['number']) ?>
                        <?php else: ?>
                            No current issue available
                        <?php endif; ?>
                    </div>
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
                    <h3>Current</h3>
                    <p>View the current issue and explore individual journal articles</p>
                    <a href="journal.php" class="explore-link">Browse Current Issue →</a>
                </article>

                <!-- Archive -->
                <article class="explore-card">
                    <span class="explore-number">02</span>
                    <h3>Archive</h3>
                    <p>Explore previous publications and access earlier journal issues</p>
                    <a href="archive.php" class="explore-link">Explore Archive Issues →</a>
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