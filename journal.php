<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use Dotenv\Dotenv;

function normalizeIssuePdfPath(string $storagePath): string{
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
            $storagePath = substr($storagePath, $position + strlen($marker));
            $storagePath = preg_replace('#^(public|sign)/#', '', $storagePath) ?? $storagePath;
        }
    }

    $storagePath = ltrim($storagePath, '/');

    $bucketName = trim((string) ($_ENV['SUPABASE_BUCKET'] ?? ''), '/');
    if ($bucketName !== '') {
        $bucketPrefix = $bucketName . '/';

        if (str_starts_with($storagePath, $bucketPrefix)) {
            $storagePath = substr($storagePath, strlen($bucketPrefix));
        }
    }

    $storagePath = preg_replace('#/+#', '/', $storagePath) ?? $storagePath;
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

    $normalizedPath = normalizeIssuePdfPath($storagePath);
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $normalizedPath)));

    return rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . rawurlencode(trim($bucketName, '/')) . '/' . $encodedPath;
}

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

$publicationID = filter_input(INPUT_GET, 'publicationID', FILTER_VALIDATE_INT);

if (!$publicationID || $publicationID <= 0) {
    $issueStmt = $pdo->query(
        'SELECT *
         FROM "PublicationIssue"
         WHERE "is_current" = TRUE
           AND "is_draft" = FALSE
         ORDER BY "year" DESC, "volume" DESC, "number" DESC
         LIMIT 1'
    );
    $issue = $issueStmt->fetch();
    $publicationID = $issue ? (int) $issue['publicationID'] : 0;
}

$issue = null;
$articles = [];

if ($publicationID && $publicationID > 0) {
    $issueStmt = $pdo->prepare(
        'SELECT *
         FROM "PublicationIssue"
         WHERE "publicationID" = :publicationID
         LIMIT 1'
    );
    $issueStmt->execute([':publicationID' => $publicationID]);
    $issue = $issueStmt->fetch();

    if ($issue && !empty($issue['publicationPDF'])) {
        $issue['publicationPDF'] = createPublicIssuePdfUrl((string) $issue['publicationPDF']);
    }

    $articleStmt = $pdo->prepare(
        'SELECT
            ja."journalID",
            ja."title",
            ja."journalPDF",
            a."firstName",
            a."lastName"
         FROM "JournalArticle" ja
         LEFT JOIN "ArticleAuthor" aa
            ON aa."journalID" = ja."journalID"
         LEFT JOIN "Author" a
            ON a."authorID" = aa."authorID"
         WHERE ja."publicationID" = :publicationID
         ORDER BY ja."journalID", a."authorID"'
    );
    $articleStmt->execute([':publicationID' => $publicationID]);
    $rows = $articleStmt->fetchAll();

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
            $articles[$journalID]['authors'][] = trim($firstName . ' ' . $lastName);
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

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="css/transition.css">
    <link rel="stylesheet" href="includes_css/footer.css">
    <link rel="stylesheet" href="css/journal_page.css">
</head>

<body class="journal-page">
    <?php include 'includes/header.php'; ?>

    <section class="about-intro">
        <div class="about-intro-content">
            <h1>Journal</h1>
            <p>Current issue of Convergence: interdisciplinary scholarship and contemporary research in conversation</p>
        </div>
    </section>

    <main class="journal-shell">
        <?php if ($issue): ?>
            <section class="issue-overview" aria-labelledby="current-issue-heading">
                <div class="issue-header">
                    <span class="issue-badge">Current issue</span>
                    <h2 id="current-issue-heading">Volume <?= htmlspecialchars((string) ($issue['volume'] ?? '')) ?>, No. <?= htmlspecialchars((string) ($issue['number'] ?? '')) ?></h2>
                </div>

                <div class="issue-meta">
                    <div>
                        <span class="meta-label">Year</span>
                        <strong><?= htmlspecialchars((string) ($issue['year'] ?? '')) ?></strong>
                    </div>
                    <div>
                        <span class="meta-label">Volume</span>
                        <strong><?= htmlspecialchars((string) ($issue['volume'] ?? '')) ?></strong>
                    </div>
                    <div>
                        <span class="meta-label">Number</span>
                        <strong><?= htmlspecialchars((string) ($issue['number'] ?? '')) ?></strong>
                    </div>
                </div>

                <?php if (!empty($issue['publicationPDF'])): ?>
                    <div class="publication-pdf-box">
                        <span class="meta-label">Issue PDF</span>
                        <a class="pdf-link" href="<?= htmlspecialchars((string) $issue['publicationPDF']) ?>?download" target="_blank" rel="noopener noreferrer" download>
                            <i class="fa-solid fa-file-pdf"></i> Download issue PDF
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <section class="articles-panel" aria-labelledby="articles-heading">
                <div class="section-heading">
                    <h3 id="articles-heading">Articles</h3>
                    <div class="articles-instruction" aria-label="Article details instructions">
                        <span class="instruction-line" aria-hidden="true"></span>
                        <p>Click article title to view details</p>
                    </div>
                </div>

                <?php if (!empty($articles)): ?>
                    <div class="articles-list">
                        <?php foreach ($articles as $article): ?>
                            <article class="article-item">
                                <div class="article-body">
                                    <h4>
                                        <a href="article_details.php?journalID=<?= (int) ($article['journalID'] ?? 0) ?>">
                                            <?= htmlspecialchars((string) ($article['title'] ?? 'Untitled Article')) ?>
                                        </a>
                                    </h4>
                                    <p class="authors">
                                        <?php if (!empty($article['authors'])): ?>
                                            <?= htmlspecialchars(implode(', ', $article['authors'])) ?>
                                        <?php else: ?>
                                            Unspecified
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty-state">No articles found for this issue.</p>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <section class="empty-issue">
                <h2>No current issue found.</h2>
                <p>The database does not currently have an active publication issue.</p>
            </section>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>

</html>
