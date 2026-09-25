<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/Classes/JournalArticle.php';

use Dotenv\Dotenv;

function normalizeReaderStoragePath(string $storagePath): string
{
    $storagePath = trim($storagePath);

    if ($storagePath === '') {
        throw new Exception('The storage path is empty.');
    }

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        $parsedPath = parse_url($storagePath, PHP_URL_PATH);

        if (!is_string($parsedPath)) {
            throw new Exception('Unable to extract the storage path from the PDF URL.');
        }

        $storagePath = $parsedPath;

        $marker = '/storage/v1/object/';
        $position = strpos($storagePath, $marker);

        if ($position !== false) {
            $storagePath = substr($storagePath,$position + strlen($marker));
            $storagePath = preg_replace('#^(public|sign)/#','',$storagePath) ?? $storagePath;
        }
    }

    $storagePath = ltrim($storagePath, '/');
    $bucketName = trim((string) ($_ENV['SUPABASE_BUCKET'] ?? ''),'/');

    if ($bucketName !== '') {
        $bucketPrefix = $bucketName . '/';
        if (str_starts_with($storagePath, $bucketPrefix)) {
            $storagePath = substr($storagePath,strlen($bucketPrefix));
        }
    }

    $storagePath = preg_replace('#/+#','/',$storagePath) ?? $storagePath;

    $storagePath = rtrim($storagePath, '/');

    if ($storagePath === '') {
        throw new Exception('The storage path is empty.');
    }

    return $storagePath;
}

function createReaderPublicPdfUrl(string $storagePath): string
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

    $normalizedPath = normalizeReaderStoragePath($storagePath);

    $encodedPath = implode('/',array_map('rawurlencode', explode('/', $normalizedPath)));

    return rtrim($supabaseUrl, '/')
        . '/storage/v1/object/public/'
        . rawurlencode(trim($bucketName, '/'))
        . '/'
        . $encodedPath;
}

/* Load environment and database */
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

/* Get Journal Article ID */

$journalID = filter_input(INPUT_GET,'journalID',FILTER_VALIDATE_INT);

if (!$journalID || $journalID <= 0) {
    header('Location: journal.php');
    exit;
}

/* Create JournalArticle object */
$journalArticle = new JournalArticle();

/*Get article details, publication information, and authors*/
$article = $journalArticle->viewJournal($pdo,$journalID);

if (!$article) {
    header('Location: journal.php');
    exit;
}

$authors = $article['authors'] ?? [];

/*Publication information*/
$publication = [
    'year' => $article['year'] ?? '',
    'volume' => $article['volume'] ?? '',
    'number' => $article['number'] ?? ''
];

/*Generate APA citation using JournalArticle*/
$citation = $journalArticle->generateCitation($article,$publication,$authors);

if (trim($citation) === '') {
    $citation = 'Citation unavailable';
}

/*Determine whether article belongs to Current Issue or Archive*/
$backUrl = 'journal.php';
$backText = 'Back to Current Issue';

if (!empty($article['is_current'])) {
    $backUrl = 'journal.php';
    $backText = 'Back to Current Issue';
} 

else {
    $backUrl = 'archive-detailspage.php?issue_id=' . urlencode((string) $article['publicationID']);
    $backText = 'Back to Archive Issue';
}

/*Get PDF for Read Online using JournalArticle*/
$readPdfPath = $journalArticle->readJournal($pdo, $journalID);

$readPdfUrl = '';

if ($readPdfPath !== null && $readPdfPath !== '') {
    $readPdfUrl = createReaderPublicPdfUrl($readPdfPath);
}

/*Get PDF for Download using JournalArticle*/
$downloadPdfPath = $journalArticle->downloadPDF($pdo, $journalID);

$downloadPdfUrl = '';

if ($downloadPdfPath !== null && $downloadPdfPath !== '') {
    $downloadPdfUrl = createReaderPublicPdfUrl($downloadPdfPath);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Article Details | Convergence</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="includes_css/header.css">
    <link rel="stylesheet" href="includes_css/footer.css">
    <link rel="stylesheet" href="css/admin_journal_details.css">
    <link rel="icon" type="image/jpeg" href="Images/Convergence Logo.png">
    <style>
        body {
            margin: 0;
            background: #f7f7f7;
            font-family: Arial, Helvetica, sans-serif;
        }

        .reader-detail-shell {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        @media (min-width: 1024px) {
            .reader-detail-shell {
                padding-top: 55px;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/header.php'; ?>

    <main class="reader-detail-shell">

        <a href="<?= htmlspecialchars($backUrl) ?>" class="back-button">
            <span class="back-arrow" aria-hidden="true">←</span>
            <?= htmlspecialchars($backText) ?>
        </a>

        <article class="details-container details-card">

            <!-- Article information -->

            <div class="details-header">
                <h1 id="articleTitle">
                    <?= htmlspecialchars((string) ($article['title']?? 'Untitled Article')) ?>
                </h1>

                <?php

                $publicationParts = [];

                if (!empty($publication['volume'])) {
                    $publicationParts[] =
                        'Volume ' .
                        (string) $publication['volume'];
                }

                if (!empty($publication['number'])) {
                    $publicationParts[] =
                        'Number ' .
                        (string) $publication['number'];
                }

                if (!empty($publication['year'])) {
                    $publicationParts[] =
                        (string) $publication['year'];
                }

                ?>

                <p class="publication-info">
                    <?= htmlspecialchars(implode(', ', $publicationParts)?: 'Publication information unavailable.') ?>
                </p>

            </div>

            <!-- Authors -->
            <section class="details-section" aria-labelledby="authors-heading">
                <h2 id="authors-heading">Authors</h2>
                <ul class="authors-list">
                    <?php if (!empty($authors)): ?>
                        <?php foreach ($authors as $author): ?>
                            <li class="author-item">
                                <?= htmlspecialchars(
                                    trim(
                                        (string) (
                                            ($author['firstName'] ?? '')
                                            . ' '
                                            . ($author['lastName'] ?? '')
                                        )
                                    )
                                        ?: 'Unknown Author'
                                ) ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>

                        <li class="author-item">
                            Unknown Author
                        </li>

                    <?php endif; ?>

                </ul>
            </section>

            <!-- APA Citation -->
            <section class="details-section"aria-labelledby="citation-heading">
                <h2 id="citation-heading">APA Citation</h2>
                <p id="citation" class="citation-box"><?= htmlspecialchars($citation) ?></p>
                <button type="button" id="copyCitation" class="copy-button">Copy Citation</button>
            </section>

            <!-- PDF -->
            <?php if ($readPdfUrl !== '' ||$downloadPdfUrl !== ''): ?>
                <section id="pdfSection" class="pdf-section" data-pdf-url="<?= htmlspecialchars($readPdfUrl) ?>" data-journal-id="<?= (int) $article['journalID'] ?>"aria-labelledby="pdf-heading">
                    <!--<h2 id="pdf-heading">Article PDF</h2>-->
                    <div class="pdf-actions" aria-label="PDF actions">
                        <?php if ($readPdfUrl !== ''): ?>
                            <button id="readOnline"type="button"class="action-button">Read Online</button>
                        <?php endif; ?>

                        <?php if ($downloadPdfUrl !== ''): ?>
                            <a id="downloadPdf" class="action-button secondary" href="<?= htmlspecialchars($downloadPdfUrl) ?>?download"download>
                                Download PDF
                            </a>
                        <?php endif; ?>

                    </div>

                    <?php if ($readPdfUrl !== ''): ?>
                        <figure id="pdfViewerContainer" class="pdf-viewer-container"style="display: none;">
                            <iframe id="pdfViewer" class="pdf-viewer"title="Journal article PDF"></iframe>
                        </figure>
                    <?php endif; ?>

                </section>
            <?php endif; ?>
        </article>

    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="javascript/article_details.js"></script>
</body>
</html>