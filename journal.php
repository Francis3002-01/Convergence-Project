<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/PublicationIssue.php';

use Dotenv\Dotenv;

function normalizeIssuePdfPath(string $storagePath): string
{
    $storagePath = trim($storagePath);

    if ($storagePath === '') {
        return '';
    }

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {

        $parsedPath = parse_url($storagePath, PHP_URL_PATH);

        if (!is_string($parsedPath)) {
            return $storagePath;
        }

        $storagePath = $parsedPath;

        $marker = '/storage/v1/object/';
        $position = strpos($storagePath, $marker);

        if ($position !== false) {

            $storagePath = substr(
                $storagePath,
                $position + strlen($marker)
            );

            $storagePath =
                preg_replace(
                    '#^(public|sign)/#',
                    '',
                    $storagePath
                ) ?? $storagePath;
        }
    }

    $storagePath = ltrim($storagePath, '/');

    $bucketName = trim(
        (string) ($_ENV['SUPABASE_BUCKET'] ?? ''),
        '/'
    );

    if ($bucketName !== '') {
        $bucketPrefix = $bucketName . '/';
        if (str_starts_with($storagePath, $bucketPrefix)) {
            $storagePath = substr($storagePath,strlen($bucketPrefix));
        }
    }

    $storagePath =
        preg_replace(
            '#/+#',
            '/',
            $storagePath
        ) ?? $storagePath;

    $storagePath = rtrim($storagePath, '/');

    return $storagePath;
}

function createPublicIssuePdfUrl(string $storagePath): string
{
    $supabaseUrl = trim((string) ($_ENV['SUPABASE_URL'] ?? ''));

    $bucketName = trim((string) ($_ENV['SUPABASE_BUCKET'] ?? ''));

    if ($storagePath === '') {
        return '';
    }

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        return $storagePath;
    }

    if ($supabaseUrl === '' || $bucketName === '') {
        return $storagePath;
    }

    $normalizedPath =normalizeIssuePdfPath($storagePath);
    $encodedPath = implode(
        '/',
        array_map('rawurlencode',explode('/', $normalizedPath))
    );

    return rtrim($supabaseUrl, '/')
        . '/storage/v1/object/public/'
        . rawurlencode(trim($bucketName, '/'))
        . '/'
        . $encodedPath;
}

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

$publicationID = filter_input(INPUT_GET, 'publicationID', FILTER_VALIDATE_INT);

// Handle editorial note download
if (isset($_GET['download']) &&$_GET['download'] === 'editorial' &&isset($_GET['publicationID']) &&ctype_digit($_GET['publicationID'])) {
    $publicationIssue = new PublicationIssue();
    $publicationIssue->downloadEditorNote(
        $pdo,
        (int) $_GET['publicationID']
    );
}

/*
|--------------------------------------------------------------------------
| If no publicationID was provided,
| retrieve the current published issue
|--------------------------------------------------------------------------
*/
if (!$publicationID || $publicationID <= 0) {

    $issueStmt = $pdo->query(
        'SELECT *
         FROM "PublicationIssue"
         WHERE "is_current" = TRUE
           AND "is_draft" = FALSE
         ORDER BY "year" DESC,
                  "volume" DESC,
                  "number" DESC
         LIMIT 1'
    );

    $issue = $issueStmt->fetch();

    $publicationID = $issue
        ? (int) $issue['publicationID']
        : 0;
}

$issue = null;
$articles = [];

if ($publicationID && $publicationID > 0) {

    /*Get Publication Issue*/
    $issueStmt = $pdo->prepare(
        'SELECT *
         FROM "PublicationIssue"
         WHERE "publicationID" = :publicationID
         LIMIT 1'
    );

    $issueStmt->execute([
        ':publicationID' => $publicationID
    ]);

    $issue = $issueStmt->fetch();


    /*Create Editorial Note URL*/
    if ($issue && !empty($issue['publicationPDF'])) {

        $issue['publicationPDF'] =
            createPublicIssuePdfUrl(
                (string) $issue['publicationPDF']
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieve Articles and Authors
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Group Authors by Article
    |--------------------------------------------------------------------------
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


        $firstName = trim((string) ($row['firstName'] ?? ''));
        $lastName = trim((string) ($row['lastName'] ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            $authorName = trim($firstName . ' ' . $lastName);
            $articles[$journalID]['authors'][] =
                $authorName;
        }
    }


    $articles = array_values($articles);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Journal | Convergence</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Header -->
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="css/transition.css">
    <link rel="stylesheet" href="includes_css/footer.css">
    <link rel="stylesheet" href="css/journal_page.css">
    <link rel="icon" type="image/jpeg" href="Images/Convergence Logo.png">
</head>

<body class="journal-page">

    <?php include 'includes/header.php'; ?>

    <section class="about-intro">
        <div class="about-intro-content">
            <h1>Journal</h1>
            <p>Explore the latest articles and scholarly contributions from Convergence</p>
        </div>
    </section>


    <!-- =========================================================
     PAGE CONTENT
========================================================= -->

    <main class="journal-content">


        <?php if ($issue): ?>


            <!-- =====================================================
             CURRENT ISSUE
        ====================================================== -->

            <section
                class="current-issue"
                aria-labelledby="current-issue-heading">

                <div class="issue-label">
                    CONVERGENCE JOURNAL
                </div>


                <h1 id="current-issue-heading">Current Issue</h1>


                <!-- Year | Volume | Number -->
                <div class="issue-information">
                    <span>
                        <?= htmlspecialchars(
                            (string) ($issue['year'] ?? '')
                        ) ?>
                    </span>

                    <span class="issue-divider">
                        |
                    </span>

                    <span>Volume<?= htmlspecialchars((string) ($issue['volume'] ?? '')) ?></span>

                    <span class="issue-divider">|</span>

                    <span>
                        Number
                        <?= htmlspecialchars(
                            (string) ($issue['number'] ?? '')
                        ) ?>
                    </span>

                </div>


                <!-- =================================================
                 EDITORIAL NOTE BUTTONS
            ================================================== -->

                <?php if (!empty($issue['publicationPDF'])): ?>

                    <div class="editorial-actions">


                        <!-- Read -->

                        <a href="<?= htmlspecialchars(
                                        (string) $issue['publicationPDF']
                                    ) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="editorial-button read-button">
                            <i class="fa-solid fa-book-open"></i>

                            <span>
                                Read Editorial Note
                            </span>
                        </a>


                        <!-- Download -->
                        <a href="journal.php?publicationID=<?= (int) $issue['publicationID'] ?>&download=editorial"
                            class="editorial-button download-button">
                            <i class="fa-solid fa-download"></i>
                            <span>Download Editorial Note</span>
                        </a>

                    </div>

                <?php endif; ?>
            </section>

            <!-- =====================================================
             LIST OF ARTICLES
        ====================================================== -->
            <section class="articles-section" aria-labelledby="articles-heading">
                <div class="articles-heading">
                    <h2 id="articles-heading">List of Articles</h2>
                    <p>Click the article title to view details</p>
                </div>

                <?php if (!empty($articles)): ?>


                    <div class="article-list">


                        <?php foreach ($articles as $index => $article): ?>


                            <article class="article-item">


                                <!-- Article Number -->

                                <div class="article-number">

                                    Article
                                    <?= sprintf(
                                        '%02d',
                                        $index + 1
                                    ) ?>

                                </div>


                                <!-- =================================================
                                 ARTICLE TITLE
                                 ONLY THIS IS CLICKABLE
                            ================================================== -->

                                <h3 class="article-title">

                                    <a
                                        href="article_details.php?journalID=<?= (int) $article['journalID'] ?>">

                                        <?= htmlspecialchars(
                                            (string) (
                                                $article['title']
                                                ?? 'Untitled Article'
                                            )
                                        ) ?>

                                    </a>

                                </h3>


                                <!-- =================================================
                                 AUTHORS
                            ================================================== -->

                                <?php if (!empty($article['authors'])): ?>

                                    <div class="article-authors">

                                        <?php foreach (
                                            $article['authors']
                                            as $authorIndex => $author
                                        ): ?>

                                            <span class="author">

                                                <?= htmlspecialchars(
                                                    $author
                                                ) ?>

                                            </span>

                                        <?php endforeach; ?>

                                    </div>

                                <?php else: ?>

                                    <div class="article-authors">

                                        <span class="author">
                                            Unspecified
                                        </span>

                                    </div>

                                <?php endif; ?>


                            </article>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <!-- No Articles -->

                    <div class="empty-state">

                        <h3>
                            No Articles Available
                        </h3>

                        <p>
                            No articles were found for this issue.
                        </p>

                    </div>


                <?php endif; ?>


            </section>


        <?php else: ?>


            <!-- =====================================================
             NO CURRENT ISSUE
        ====================================================== -->

            <section class="empty-issue">

                <div class="empty-issue-icon">
                    <i class="fa-regular fa-file-lines"></i>
                </div>

                <h2>
                    No Current Issue Found
                </h2>

                <p>
                    The database does not currently have
                    an active publication issue.
                </p>

            </section>


        <?php endif; ?>


    </main>


    <?php include 'includes/footer.php'; ?>


</body>

</html>