<?php

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Classes/JournalArticle.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();


/**
 * --------------------------------------------------------------------------
 * Create Supabase public PDF URL
 * --------------------------------------------------------------------------
 */
function createPublicPdfUrl(?string $storagePath): string
{
    if (empty($storagePath)) {
        return '';
    }

    // If the database already contains a complete URL,
    // use it directly.
    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        return $storagePath;
    }

    $supabaseUrl = rtrim(
        $_ENV['SUPABASE_URL'] ?? '',
        '/'
    );

    $bucketName = $_ENV['SUPABASE_BUCKET'] ?? 'journalpdf';

    if ($supabaseUrl === '') {
        return '';
    }

    // Encode each path segment separately so slashes remain
    // directory separators.
    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode('/', ltrim($storagePath, '/'))
        )
    );

    return $supabaseUrl .
        '/storage/v1/object/public/' .
        rawurlencode($bucketName) .
        '/' .
        $encodedPath;
}


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


/**
 * --------------------------------------------------------------------------
 * Get search keyword
 * --------------------------------------------------------------------------
 */
$searchKeyword = trim($_GET['search'] ?? '');

/**
 * If the search parameter exists but is empty,
 * return to the normal Archive page.
 *
 * Example:
 * archive_admin.php?search=
 *
 * becomes:
 * archive_admin.php
 */
if (array_key_exists('search', $_GET) && $searchKeyword === '') {
    header('Location: archive_admin.php');
    exit;
}


$selectedIssue = null;
$articles = [];
$searchResults = [];


/**
 * --------------------------------------------------------------------------
 * Search archived articles
 * --------------------------------------------------------------------------
 *
 * Search only when a keyword is entered.
 * publicationID is ignored while searching.
 */
if ($searchKeyword !== '') {

    $publicationID = null;

    $journalArticle = new JournalArticle();

    $searchResults = $journalArticle->searchArchivedJournal(
        $pdo,
        $searchKeyword
    );
}


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

$archiveIssues = $archiveStmt->fetchAll(PDO::FETCH_ASSOC);


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

    $selectedIssue = $issueStmt->fetch(PDO::FETCH_ASSOC);


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

        $rows = $articleStmt->fetchAll(PDO::FETCH_ASSOC);


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

                if (
                    !in_array(
                        $authorName,
                        $articles[$journalID]['authors'],
                        true
                    )
                ) {
                    $articles[$journalID]['authors'][] = $authorName;
                }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archive - Convergence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/archive_admin.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="icon" type="image/png" href="../Images/Convergence Logo.png">
</head>


<body>


    <?php include 'components/left_sidebar.php'; ?>


    <div class="main-area">


        <?php include 'components/header.php'; ?>


        <main class="content">


            <?php if (!$selectedIssue): ?>


                <!-- =====================================================
                     ARCHIVE LIST / SEARCH PAGE
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

                            <form method="GET" action="archive_admin.php"
                                class="search-box"
                                id="archiveSearchForm">

                                <input
                                    type="text"
                                    name="search"
                                    id="searchInput"
                                    value="<?= htmlspecialchars(
                                                $searchKeyword,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                    placeholder="Search articles..."
                                    autocomplete="off"
                                    aria-label="Search articles">

                                <button
                                    type="submit"
                                    id="searchButton"
                                    aria-label="Search">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>

                            </form>

                        </div>

                    </div>


                    <?php if ($searchKeyword !== ''): ?>


                        <!-- =================================================
                             ARTICLE SEARCH RESULTS
                             ================================================= -->


                        <div class="archive-list-container archive-search-container">


                            <!-- SEARCH RESULT HEADER -->

                            <div class="archive-list-header">

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


                            <!-- SEARCH RESULTS -->

                            <div
                                id="archiveSearchResults"
                                class="archive-list">


                                <?php if (!empty($searchResults)): ?>


                                    <?php foreach (
                                        $searchResults as $article
                                    ): ?>


                                        <div class="archive-list-row">


                                            <!-- TITLE -->

                                            <div>

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $article['title']
                                                        ?? 'Untitled Article'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </div>


                                            <!-- AUTHORS -->

                                            <div>

                                                <?php if (
                                                    !empty($article['authors'])
                                                ): ?>

                                                    <?= htmlspecialchars(
                                                        implode(
                                                            ', ',
                                                            $article['authors']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                <?php else: ?>

                                                    Unspecified

                                                <?php endif; ?>

                                            </div>


                                            <!-- ACTIONS -->

                                            <div>

                                                <?php

                                                $articlePdfUrl =
                                                    createPublicPdfUrl(
                                                        $article['journalPDF']
                                                            ?? ''
                                                    );

                                                ?>

                                                <a
                                                    href="<?= htmlspecialchars(
                                                                $articlePdfUrl,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                    class="read-button"
                                                    title="Read Article"
                                                    <?= $articlePdfUrl !== ''
                                                        ? 'target="_blank" rel="noopener noreferrer"'
                                                        : '' ?>>

                                                    Read

                                                </a>

                                            </div>


                                        </div>


                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <!-- NO SEARCH RESULTS -->

                                    <div class="empty-state">

                                        <h3>
                                            No Articles Found
                                        </h3>

                                        <p>

                                            No archived articles matched
                                            "<strong><?= htmlspecialchars(
                                                            $searchKeyword,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?></strong>".

                                        </p>

                                    </div>


                                <?php endif; ?>


                            </div>


                        </div>


                    <?php else: ?>


                        <!-- =================================================
                             NORMAL ARCHIVE ISSUE LIST
                             ================================================= -->


                        <div class="archive-list-container">


                            <!-- ARCHIVE HEADER -->

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


                            <!-- ARCHIVE ISSUES -->

                            <div
                                id="archiveList"
                                class="archive-list">


                                <?php if (!empty($archiveIssues)): ?>


                                    <?php foreach (
                                        $archiveIssues as $archive
                                    ): ?>


                                        <div class="archive-list-row">


                                            <!-- YEAR -->

                                            <div>

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $archive['year']
                                                        ?? ''
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </div>


                                            <!-- VOLUME -->

                                            <div>

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $archive['volume']
                                                        ?? ''
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </div>


                                            <!-- NUMBER -->

                                            <div>

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $archive['number']
                                                        ?? ''
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </div>


                                            <!-- ACTIONS -->

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


                                    <!-- NO ARCHIVE ISSUES -->

                                    <div class="empty-state">

                                        <h3>
                                            No Archive Issues
                                        </h3>

                                        <p>
                                            No previously published
                                            journal issues were found.
                                        </p>

                                    </div>


                                <?php endif; ?>


                            </div>


                        </div>


                    <?php endif; ?>


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
                                    (string) $selectedIssue['volume'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                Number
                                <?= htmlspecialchars(
                                    (string) $selectedIssue['number'],
                                    ENT_QUOTES,
                                    'UTF-8'
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
                                        (string) $selectedIssue['year'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </div>


                            <div>

                                <span>
                                    Volume
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        (string) $selectedIssue['volume'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </strong>

                            </div>


                            <div>

                                <span>
                                    Number
                                </span>

                                <strong>

                                    <?= htmlspecialchars(
                                        (string) $selectedIssue['number'],
                                        ENT_QUOTES,
                                        'UTF-8'
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


                                <?php

                                $editorialPdfUrl =
                                    createPublicPdfUrl(
                                        $selectedIssue['publicationPDF']
                                            ?? ''
                                    );

                                ?>


                                <!-- READ EDITORIAL NOTE -->

                                <a
                                    href="<?= htmlspecialchars(
                                                $editorialPdfUrl,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                    class="read-button"
                                    title="Read Editorial Note"
                                    <?= $editorialPdfUrl !== ''
                                        ? 'target="_blank" rel="noopener noreferrer"'
                                        : '' ?>>

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
                                    Archived Articles
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


                            <!-- ARTICLES -->

                            <div
                                id="archiveArticlesList"
                                class="archive-articles-list">


                                <?php if (!empty($articles)): ?>


                                    <?php foreach (
                                        $articles as $article
                                    ): ?>


                                        <div class="archive-article-row">


                                            <!-- ARTICLE TITLE -->

                                            <div class="article-title">

                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $article['title']
                                                        ?? 'Untitled Article'
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </div>


                                            <!-- AUTHORS -->

                                            <div class="article-authors">

                                                <?php if (
                                                    !empty($article['authors'])
                                                ): ?>

                                                    <?= htmlspecialchars(
                                                        implode(
                                                            ', ',
                                                            $article['authors']
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                <?php else: ?>

                                                    Unspecified

                                                <?php endif; ?>

                                            </div>


                                            <!-- ARTICLE ACTIONS -->

                                            <div class="article-actions">


                                                <?php

                                                $articlePdfUrl =
                                                    createPublicPdfUrl(
                                                        $article['journalPDF']
                                                            ?? ''
                                                    );

                                                ?>


                                                <!-- READ ARTICLE -->

                                                <a
                                                    href="<?= htmlspecialchars(
                                                                $articlePdfUrl,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                    class="read-button"
                                                    title="Read Article"
                                                    <?= $articlePdfUrl !== ''
                                                        ? 'target="_blank" rel="noopener noreferrer"'
                                                        : '' ?>>

                                                    Read

                                                </a>


                                            </div>


                                        </div>


                                    <?php endforeach; ?>


                                <?php else: ?>


                                    <!-- NO ARTICLES -->

                                    <div class="empty-state">

                                        <h3>
                                            No Articles Available
                                        </h3>

                                        <p>
                                            No articles were found for
                                            this issue.
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const searchInput = document.getElementById('searchInput');

            if (!searchInput) {
                return;
            }

            searchInput.addEventListener('input', function() {

                if (this.value.trim() === '') {
                    window.location.href = 'archive_admin.php';
                }

            });

        });
    </script>


</body>

</html>