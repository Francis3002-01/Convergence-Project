<?php

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once 'config/database.php';

$database = new Database();
$pdo = $database->getConnection();

/*Get Archive Issues*/

$sql = '
    SELECT
        "publicationID",
        "year",
        "volume",
        "number"
    FROM "PublicationIssue"
    WHERE "is_current" = FALSE
      AND "is_draft" = FALSE
    ORDER BY "year" DESC, "volume" DESC, "number" DESC
';

$stmt = $pdo->prepare($sql);
$stmt->execute();

$archiveIssues = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive | Convergence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="includes_css/footer.css">
    <link rel="stylesheet" href="css/transition.css">
    <link rel="stylesheet" href="css/archive_page.css">
    <link rel="icon" type="image/jpeg" href="Images/Convergence Logo.png">
</head>

<body>

    <?php include 'includes/header.php'; ?>
    <!-- Archive Introduction -->
    <section class="about-intro">
        <div class="about-intro-content">
            <h1>Archive</h1>
            <p>A record of past issues, academic conversations,and enduring contributions to knowledge</p>
        </div>
    </section>

    <!-- Main Content Container -->
    <main class="archive-container">
        <h2 class="section-title">List of Archive Issues</h2>

        <div class="journal-list">
            <?php if (empty($archiveIssues)): ?>
                <div class="no-issues">
                    <p>No archived issues are currently available.</p>
                </div>
            <?php else: ?>

                <?php foreach ($archiveIssues as $issue): ?>

                    <div class="journal-item">
                        <div class="journal-info">
                            <a href="archive-detailspage.php?issue_id=<?= htmlspecialchars($issue['publicationID']) ?>" class="issue-label">
                                <?= htmlspecialchars($issue['year']) ?>:
                                Volume <?= htmlspecialchars($issue['volume']) ?>,
                                Number <?= htmlspecialchars($issue['number']) ?>
                            </a>
                        </div>

                        <a href="archive-detailspage.php?issue_id=<?= htmlspecialchars($issue['publicationID']) ?>" class="view-btn" title="View Issue">
                            <i class="fa-solid fa-eye"></i>
                        </a>

                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
</body>
</html>