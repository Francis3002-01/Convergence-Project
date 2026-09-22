<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/PublicationIssue.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

function normalizeIssuePdfPath(string $storagePath): string
{
    $storagePath = trim($storagePath);

    if ($storagePath === '') {
        return '';
    }

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {

        $parsedPath = parse_url(
            $storagePath,
            PHP_URL_PATH
        );

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

    $storagePath = ltrim(
        $storagePath,
        '/'
    );

    $bucketName = trim(
        (string) ($_ENV['SUPABASE_BUCKET'] ?? ''),
        '/'
    );

    if ($bucketName !== '') {

        $bucketPrefix = $bucketName . '/';

        if (
            str_starts_with(
                $storagePath,
                $bucketPrefix
            )
        ) {

            $storagePath = substr(
                $storagePath,
                strlen($bucketPrefix)
            );
        }
    }

    $storagePath =
        preg_replace(
            '#/+#',
            '/',
            $storagePath
        ) ?? $storagePath;

    return rtrim(
        $storagePath,
        '/'
    );
}


function createPublicIssuePdfUrl(
    string $storagePath
): string {

    $supabaseUrl = trim(
        (string) ($_ENV['SUPABASE_URL'] ?? '')
    );

    $bucketName = trim(
        (string) ($_ENV['SUPABASE_BUCKET'] ?? '')
    );

    if ($storagePath === '') {
        return '';
    }

    if (
        filter_var(
            $storagePath,
            FILTER_VALIDATE_URL
        )
    ) {
        return $storagePath;
    }

    if (
        $supabaseUrl === ''
        || $bucketName === ''
    ) {
        return $storagePath;
    }

    $normalizedPath =
        normalizeIssuePdfPath(
            $storagePath
        );

    $encodedPath =
        implode(
            '/',
            array_map(
                'rawurlencode',
                explode(
                    '/',
                    $normalizedPath
                )
            )
        );

    return rtrim(
        $supabaseUrl,
        '/'
    )
        . '/storage/v1/object/public/'
        . rawurlencode(
            trim(
                $bucketName,
                '/'
            )
        )
        . '/'
        . $encodedPath;
}

$publicationID = filter_input(
    INPUT_GET,
    'issue_id',
    FILTER_VALIDATE_INT
);

if (isset($_GET['download']) && $_GET['download'] === 'editorial' && isset($_GET['issue_id']) && ctype_digit($_GET['issue_id'])) {
    $downloadPublicationID = (int) $_GET['issue_id'];
    $publicationIssue = new PublicationIssue();
    $publicationIssue->downloadEditorNote($pdo, $downloadPublicationID);
}

$issue = null;
$articles = [];

if ($publicationID && $publicationID > 0) {

    $issueStmt = $pdo->prepare(
        'SELECT
            "publicationID",
            "year",
            "volume",
            "number",
            "publicationPDF",
            "is_current",
            "is_draft"

         FROM "PublicationIssue"

         WHERE "publicationID" = :publicationID
           AND "is_current" = FALSE
           AND "is_draft" = FALSE

         LIMIT 1'
    );

    $issueStmt->execute([
        ':publicationID' => $publicationID
    ]);

    $issue = $issueStmt->fetch();

    if ($issue && !empty($issue['publicationPDF'])) {
        $issue['publicationPDF'] = createPublicIssuePdfUrl((string)$issue['publicationPDF']);
    }

    if ($issue) {

        $articleStmt = $pdo->prepare(
            'SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",

                a."authorID",
                a."firstName",
                a."lastName"

             FROM "JournalArticle" ja

             LEFT JOIN "ArticleAuthor" aa
                ON aa."journalID" =
                   ja."journalID"

             LEFT JOIN "Author" a
                ON a."authorID" =
                   aa."authorID"

             WHERE ja."publicationID" =
                   :publicationID

             ORDER BY
                ja."journalID",
                a."authorID"'
        );

        $articleStmt->execute([
            ':publicationID' => $publicationID
        ]);

        $rows = $articleStmt->fetchAll();

        foreach ($rows as $row) {
            $journalID = (int) $row['journalID'];

            if (!isset($articles[$journalID])) {

                $articles[$journalID] = [

                    'journalID' =>
                    $journalID,

                    'title' =>
                    $row['title']
                        ?? '',

                    'journalPDF' =>
                    $row['journalPDF']
                        ?? '',

                    'authors' =>
                    []

                ];
            }

            $firstName = trim((string)($row['firstName'] ?? ''));
            $lastName = trim((string)($row['lastName'] ?? ''));

            if ($firstName !== '' || $lastName !== '') {
                $articles[$journalID]['authors'][] =
                    trim(
                        $firstName
                            . ' '
                            . $lastName
                    );
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
    <title>Archive | Convergence</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="css/transition.css">
    <link rel="stylesheet" href="includes_css/footer.css">
    <link rel="stylesheet" href="css/archive_detailspage.css">
    <link rel="icon" type="image/jpeg" href="Images/Convergence Logo.png">
</head>

<body class="archive-details-page">

    <?php include 'includes/header.php'; ?>

    <section class="about-intro">
        <div class="about-intro-content">
            <h1>Archive</h1>
            <p>Explore past issues and scholarly contributions published by Convergence</p>
        </div>
    </section>

    <main class="archive-details-content">

        <?php if ($issue): ?>

            <a href="archive.php" class="back-to-archive">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Archive
            </a>

            <section class="archive-issue" aria-labelledby="archive-issue-heading">

                <div class="issue-label">
                    CONVERGENCE JOURNAL ARCHIVE
                </div>

                <h1 id="archive-issue-heading">Archive Issue</h1>

                <!-- Year | Volume | Number -->
                <div class="issue-information">
                    <span><?= htmlspecialchars((string)($issue['year'] ?? '')) ?></span>
                    <span class="issue-divider">|</span>
                    <span>Volume <?= htmlspecialchars((string)($issue['volume'] ?? '')) ?></span>
                    <span class="issue-divider">|</span>
                    <span>Number <?= htmlspecialchars((string) ($issue['number'] ?? '')) ?></span>
                </div>

                <?php if (!empty($issue['publicationPDF'])): ?>
                    <div class="editorial-actions">

                        <!-- Read Editorial Note -->
                        <a href="<?= htmlspecialchars(
                                        (string)
                                        $issue['publicationPDF']
                                    ) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="editorial-button read-button">

                            <i class="fa-solid fa-book-open"></i>

                            <span>Read Editorial Note</span>
                        </a>

                        <!-- Download Editorial Note -->
                        <a href="archive-detailspage.php?issue_id=<?= (int) $issue['publicationID'] ?>&download=editorial" class="editorial-button download-button">
                            <i class="fa-solid fa-download"></i>
                            <span>Download Editorial Note</span>
                        </a>

                    </div>
                <?php endif; ?>
            </section>

            <section class="articles-section" aria-labelledby="articles-heading">
                <div class="articles-heading">
                    <h2 id="articles-heading">List of Archive Articles</h2>
                    <p>Click the article title to view details</p>
                </div>

                <?php if (!empty($articles)): ?>

                    <div class="article-list">


                        <?php foreach (
                            $articles
                            as $index => $article
                        ): ?>


                            <article class="article-item">

                                <!-- Article Number -->
                                <div class="article-number">
                                    Article
                                    <?= sprintf(
                                        '%02d',
                                        $index + 1
                                    ) ?>
                                </div>


                                <!-- Article Title -->
                                <h3 class="article-title">
                                    <a href="article_details.php?journalID=<?= (int) $article['journalID'] ?>">
                                        <?= htmlspecialchars(
                                            (string)
                                            (
                                                $article['title']
                                                ?? 'Untitled Article'
                                            )
                                        ) ?>
                                    </a>
                                </h3>

                                <!-- Authors -->
                                <?php if (
                                    !empty($article['authors'])
                                ): ?>

                                    <div class="article-authors">

                                        <?php foreach (
                                            $article['authors']
                                            as $author
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
                                        <span class="author">Unspecified</span>
                                    </div>
                                <?php endif; ?>
                            </article>


                        <?php endforeach; ?>


                    </div>

                <?php else: ?>

                    <!-- No Articles -->
                    <div class="empty-state">
                        <h3>No Articles Available</h3>
                        <p>No articles were found for this archive issue</p>
                    </div>

                <?php endif; ?>
            </section>

        <?php else: ?>
            <section class="empty-issue">
                <div class="empty-issue-icon">
                    <i class="fa-regular fa-file-lines"></i>
                </div>
                <h2>Archive Issue Not Found</h2>
                <p>The requested archive issue does not exist or is not available in the archive</p>
            </section>

        <?php endif; ?>

    </main>
    <?php include 'includes/footer.php'; ?>
</body>

</html>