<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

/**
 * --------------------------------------------------------------------------
 * Get selected publication ID
 * --------------------------------------------------------------------------
 */

$publicationID = filter_input(
    INPUT_GET,
    'publicationID',
    FILTER_VALIDATE_INT
);

$selectedIssue = null;
$articles = [];

/**
 * --------------------------------------------------------------------------
 * Get Archive Issues
 * --------------------------------------------------------------------------
 */

$archiveStmt = $pdo->query(
    'SELECT
        "publicationID",
        "year",
        "volume",
        "number",
        "publicationPDF"
     FROM "PublicationIssue"
     WHERE "is_current" = FALSE
       AND "is_draft" = FALSE
     ORDER BY
        "year" DESC,
        "volume" DESC,
        "number" DESC'
);

$archiveIssues = $archiveStmt->fetchAll();

/**
 * --------------------------------------------------------------------------
 * If an archive issue was selected, retrieve its details
 * --------------------------------------------------------------------------
 */

if ($publicationID && $publicationID > 0) {

    /**
     * ----------------------------------------------------------------------
     * Get selected archive issue
     * ----------------------------------------------------------------------
     */

    $issueStmt = $pdo->prepare(
        'SELECT
            "publicationID",
            "year",
            "volume",
            "number",
            "publicationPDF"
         FROM "PublicationIssue"
         WHERE "publicationID" = :publicationID
           AND "is_current" = FALSE
           AND "is_draft" = FALSE
         LIMIT 1'
    );

    $issueStmt->execute([
        ':publicationID' => $publicationID
    ]);

    $selectedIssue = $issueStmt->fetch();

    /**
     * ----------------------------------------------------------------------
     * Get Articles and Authors
     * ----------------------------------------------------------------------
     */

    if ($selectedIssue) {

        $articleStmt = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                a."firstName",
                a."lastName",
                a."authorID"
             FROM "JournalArticle" ja
             LEFT JOIN "ArticleAuthor" aa
                ON aa."journalID" = ja."journalID"
             LEFT JOIN "Author" a
                ON a."authorID" = aa."authorID"
             WHERE ja."publicationID" = :publicationID
             ORDER BY
                ja."journalID",
                a."authorID"'
        );

        $articleStmt->execute([
            ':publicationID' => $publicationID
        ]);

        $rows = $articleStmt->fetchAll();

        /**
         * ------------------------------------------------------------------
         * Group Authors by Article
         * ------------------------------------------------------------------
         */

        foreach ($rows as $row) {

            $journalID = (int) $row['journalID'];

            if (!isset($articles[$journalID])) {
                $articles[$journalID] = [
                    'journalID' => $journalID,
                    'title' => $row['title'] ?? '',
                    'journalPDF' => $row['journalPDF'] ?? '',
                    'authors' => []
                ];
            }

            $firstName = trim(
                (string) ($row['firstName'] ?? '')
            );

            $lastName = trim(
                (string) ($row['lastName'] ?? '')
            );

            if ($firstName !== '' || $lastName !== '') {

                $authorName = trim(
                    $firstName . ' ' . $lastName
                );

                $articles[$journalID]['authors'][] =
                    $authorName;
            }
        }

        $articles = array_values($articles);
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Archive - Convergence</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link
        rel="stylesheet"
        href="../css/admin.css">

    <link
        rel="stylesheet"
        href="../css/archive_admin.css">

    <link
        rel="icon"
        type="image/png"
        href="../Images/Convergence Logo.png">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

</head>

<body>

    <?php include 'components/left_sidebar.php'; ?>

    <div class="main-area">

        <?php include 'components/header.php'; ?>

        <main class="content">

            <?php if (!$selectedIssue): ?>

                <!-- =====================================================
                     ARCHIVE LIST PAGE
                     ===================================================== -->

                <section id="archiveListPage">

                    <!-- PAGE HEADER -->

                    <div class="page-header">

                        <div class="page-heading">

                            <h1>Archive</h1>

                            <p>
                                View previously published journal issues
                            </p>

                        </div>

                        <!-- SEARCH -->

                        <div class="page-actions">

                            <div class="search-box">

                                <input
                                    type="text"
                                    id="searchInput"
                                    placeholder="Search archive issues..."
                                    autocomplete="off"
                                    aria-label="Search archive issues">

                                <button
                                    type="button"
                                    id="searchButton"
                                    aria-label="Search">

                                    <i class="fa-solid fa-magnifying-glass"></i>

                                </button>

                            </div>

                        </div>

                    </div>

                    <div class="archive-list-container">

                        <div class="archive-list-header">

                            <div>
                                Year
                            </div>

                            <div>
                                Volume
                            </div>

                            <div>
                                Number
                            </div>

                            <div>
                                Actions
                            </div>

                        </div>

                        <!-- DATABASE ARCHIVE ISSUES -->

                        <div
                            id="archiveList"
                            class="archive-list">

                            <?php if (!empty($archiveIssues)): ?>

                                <?php foreach ($archiveIssues as $archive): ?>

                                    <div
                                        class="archive-list-row"
                                        data-search="<?= htmlspecialchars(
                                                            strtolower(
                                                                (string) $archive['year']
                                                                    . ' '
                                                                    . (string) $archive['volume']
                                                                    . ' '
                                                                    . (string) $archive['number']
                                                            ),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>">

                                        <div>
                                            <?= htmlspecialchars(
                                                (string) $archive['year']
                                            ) ?>
                                        </div>

                                        <div>
                                            <?= htmlspecialchars(
                                                (string) $archive['volume']
                                            ) ?>
                                        </div>

                                        <div>
                                            <?= htmlspecialchars(
                                                (string) $archive['number']
                                            ) ?>
                                        </div>

                                        <div>

                                            <a
                                                href="archive_admin.php?publicationID=<?= (int) $archive['publicationID'] ?>"
                                                class="view-archive-button"
                                                title="View Archive Issue"
                                                aria-label="View Archive Issue">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <div class="empty-state">

                                    <h3>
                                        No Archive Issues
                                    </h3>

                                    <p>
                                        No previously published journal issues were found.
                                    </p>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </section>


            <?php else: ?>

                <!-- =====================================================
                     ARCHIVE DETAILS PAGE
                     ===================================================== -->

                <section
                    id="archiveDetailsPage"
                    class="archive-details-page">

                    <!-- BACK BUTTON -->

                    <div class="archive-details-top">

                        <a
                            href="archive_admin.php"
                            class="back-to-archives">

                            <i class="fa-solid fa-arrow-left"></i>

                            Back to Archives

                        </a>

                    </div>


                    <!-- =================================================
                         ISSUE INFORMATION
                         ================================================= -->

                    <div class="archive-issue-card">

                        <div class="archive-issue-heading">

                            <span class="archive-label">
                                Archive Issue
                            </span>

                            <h2>

                                Volume
                                <?= htmlspecialchars(
                                    (string) $selectedIssue['volume']
                                ) ?>

                                Number
                                <?= htmlspecialchars(
                                    (string) $selectedIssue['number']
                                ) ?>

                            </h2>

                        </div>


                        <!-- ISSUE DETAILS -->

                        <div class="archive-publication-summary">

                            <div>

                                <span>
                                    Year
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        (string) $selectedIssue['year']
                                    ) ?>

                                </strong>

                            </div>

                            <div>

                                <span>
                                    Volume
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        (string) $selectedIssue['volume']
                                    ) ?>

                                </strong>

                            </div>

                            <div>

                                <span>
                                    Number
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        (string) $selectedIssue['number']
                                    ) ?>

                                </strong>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         EDITORIAL NOTE
                         ================================================= -->

                    <div class="archive-section">

                        <div class="archive-section-header">

                            <div>

                                <span class="section-label">
                                    Publication
                                </span>

                                <h3>
                                    Editorial Note
                                </h3>

                            </div>

                        </div>


                        <div class="editorial-note-card">

                            <div class="editorial-note-icon">

                                <i class="fa-solid fa-file-pdf"></i>

                            </div>


                            <div class="editorial-note-info">

                                <strong>
                                    Editorial Note PDF
                                </strong>

                                <span>
                                    Read the editorial note for this issue
                                </span>

                            </div>


                            <div class="editorial-note-actions">

                                <!-- READ EDITORIAL NOTE -->

                                <a
                                    href="#"
                                    class="read-button"
                                    title="Read Editorial Note">
                                    Read
                                </a>

                            </div>

                        </div>

                    </div>


                    <!-- =================================================
                         ARTICLES
                         ================================================= -->

                    <div class="archive-section">

                        <div class="archive-section-header">

                            <div>

                                <span class="section-label">
                                    Published Articles
                                </span>

                                <h3>
                                    Articles
                                </h3>

                            </div>

                        </div>


                        <div class="archive-articles-container">

                            <!-- ARTICLE LIST HEADER -->

                            <div class="archive-articles-header">

                                <div>
                                    Title
                                </div>

                                <div>
                                    Authors
                                </div>

                                <div>
                                    Actions
                                </div>

                            </div>


                            <!-- DATABASE ARTICLES -->

                            <div
                                id="archiveArticlesList"
                                class="archive-articles-list">

                                <?php if (!empty($articles)): ?>

                                    <?php foreach ($articles as $article): ?>

                                        <div class="archive-article-row">

                                            <!-- ARTICLE TITLE -->

                                            <div class="article-title">

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $article['title']
                                                        ?? 'Untitled Article'
                                                    )
                                                ) ?>

                                            </div>


                                            <!-- AUTHORS -->

                                            <div class="article-authors">

                                                <?php if (!empty($article['authors'])): ?>

                                                    <?= htmlspecialchars(
                                                        implode(
                                                            ', ',
                                                            $article['authors']
                                                        )
                                                    ) ?>

                                                <?php else: ?>

                                                    Unspecified

                                                <?php endif; ?>

                                            </div>


                                            <!-- ARTICLE ACTIONS -->

                                            <div class="article-actions">

                                                <a
                                                    href="<?= !empty($article['journalPDF'])
                                                                ? htmlspecialchars(
                                                                    (string) $article['journalPDF']
                                                                )
                                                                : '#'
                                                            ?>"
                                                    class="read-button"
                                                    title="Read Article"
                                                    <?= !empty($article['journalPDF'])
                                                        ? 'target="_blank" rel="noopener noreferrer"'
                                                        : '' ?>>
                                                    Read
                                                </a>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <div class="empty-state">

                                        <h3>
                                            No Articles Available
                                        </h3>

                                        <p>
                                            No articles were found for this issue.
                                        </p>

                                    </div>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </section>

            <?php endif; ?>

        </main>

    </div>


    <!-- =============================================================
         SEARCH
         ============================================================= -->

    <?php if (!$selectedIssue): ?>

        <script>
            const searchInput =
                document.getElementById('searchInput');

            const archiveList =
                document.getElementById('archiveList');


            function searchArchives() {

                const searchTerm =
                    searchInput.value
                    .trim()
                    .toLowerCase();


                const rows =
                    archiveList.querySelectorAll(
                        '.archive-list-row'
                    );


                rows.forEach(function(row) {

                    const searchableText =
                        row.dataset.search || '';


                    if (
                        searchTerm === '' ||
                        searchableText.includes(searchTerm)
                    ) {

                        row.style.display = '';

                    } else {

                        row.style.display = 'none';

                    }

                });

            }


            searchInput.addEventListener(
                'input',
                searchArchives
            );


            document
                .getElementById('searchButton')
                .addEventListener(
                    'click',
                    searchArchives
                );
        </script>

    <?php endif; ?>

</body>

</html>