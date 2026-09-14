<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use Dotenv\Dotenv;

function normalizeReaderStoragePath(string $storagePath): string{
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

    if ($storagePath === '') {
        throw new Exception('The storage path is empty.');
    }

    return $storagePath;
}

function createReaderPublicPdfUrl(string $storagePath): string{
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
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $normalizedPath)));

    return rtrim($supabaseUrl, '/') . '/storage/v1/object/public/' . rawurlencode(trim($bucketName, '/')) . '/' . $encodedPath;
}

function generateReaderApaCitation(array $authors, array $publication, string $title): string{
    $citationAuthors = [];

    foreach ($authors as $author) {
        $firstName = trim((string) ($author['firstName'] ?? ''));
        $lastName = trim((string) ($author['lastName'] ?? ''));

        if ($firstName === '' && $lastName === '') {
            continue;
        }

        if ($firstName === '') {
            $citationAuthors[] = $lastName;
            continue;
        }

        $parts = preg_split('/\s+/', $firstName);
        $initials = '';

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part !== '') {
                $initials .= strtoupper(mb_substr($part, 0, 1)) . '. ';
            }
        }

        $initials = trim($initials);

        if ($lastName !== '' && $initials !== '') {
            $citationAuthors[] = $lastName . ', ' . $initials;
        } elseif ($lastName !== '') {
            $citationAuthors[] = $lastName;
        } else {
            $citationAuthors[] = $initials;
        }
    }

    $authorText = '';
    $authorCount = count($citationAuthors);

    if ($authorCount === 1) {
        $authorText = $citationAuthors[0] . ' ';
    } 
    
    elseif ($authorCount === 2) {
        $authorText = $citationAuthors[0] . ', & ' . $citationAuthors[1] . ' ';
    } 
    
    elseif ($authorCount > 2) {
        $lastAuthor = array_pop($citationAuthors);
        $authorText = implode(', ', $citationAuthors) . ', & ' . $lastAuthor . ' ';
    }

    $year = !empty($publication['year']) ? (string) $publication['year'] : 'n.d.';
    $titleText = trim($title) !== '' ? $title : 'Untitled Article';
    $volume = !empty($publication['volume']) ? (string) $publication['volume'] : '';
    $number = !empty($publication['number']) ? (string) $publication['number'] : '';

    $journalInformation = 'Convergence';
    if ($volume !== '') {
        $journalInformation .= ', ' . $volume;

        if ($number !== '') {
            $journalInformation .= '(' . $number . ')';
        }
    }

    return $authorText . '(' . $year . '). ' . $titleText . '. ' . $journalInformation . '.';
}

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$database = new Database();
$pdo = $database->getConnection();

$journalID = filter_input(INPUT_GET, 'journalID', FILTER_VALIDATE_INT);

if (!$journalID || $journalID <= 0) {
    header('Location: journal.php');
    exit;
}

$article = null;
$authors = [];
$publication = [
    'year' => '',
    'volume' => '',
    'number' => ''
];
$pdfUrl = '';
$citation = 'Citation unavailable.';

$articleStmt = $pdo->prepare(
    'SELECT
        ja."journalID",
        ja."title",
        ja."journalPDF",
        ja."publicationID",
        pi."year",
        pi."volume",
        pi."number"
     FROM "JournalArticle" ja
     INNER JOIN "PublicationIssue" pi
        ON pi."publicationID" = ja."publicationID"
     WHERE ja."journalID" = :journalID
     LIMIT 1'
);
$articleStmt->execute([':journalID' => $journalID]);
$article = $articleStmt->fetch();

if ($article) {
    $authorStmt = $pdo->prepare(
        'SELECT a."firstName", a."lastName"
         FROM "ArticleAuthor" aa
         INNER JOIN "Author" a
            ON a."authorID" = aa."authorID"
         WHERE aa."journalID" = :journalID
         ORDER BY a."lastName", a."firstName"'
    );
    $authorStmt->execute([':journalID' => $journalID]);
    $authorRows = $authorStmt->fetchAll();

    foreach ($authorRows as $row) {
        $firstName = trim((string) ($row['firstName'] ?? ''));
        $lastName = trim((string) ($row['lastName'] ?? ''));
        $authors[] = [
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];
    }

    $publication = [
        'year' => $article['year'] ?? '',
        'volume' => $article['volume'] ?? '',
        'number' => $article['number'] ?? ''
    ];

    if (!empty($article['journalPDF'])) {
        $pdfUrl = createReaderPublicPdfUrl((string) $article['journalPDF']);
    }

    $citation = generateReaderApaCitation($authors, $publication, (string) ($article['title'] ?? 'Untitled Article'));
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

        .details-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }

        .details-card {
            margin-top: 20px;
        }

        .pdf-section {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #eeeeee;
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
        <div class="details-container">
            <a href="journal.php" class="back-button">
                <span class="back-arrow">←</span>Back to Journal
            </a>

            <?php if (!$article): ?>
                <div class="details-card">
                    <h2>Article not found.</h2>
                </div>
            <?php else: ?>
                <div class="details-card">
                    <div class="details-header">
                        <h1 id="articleTitle"><?= htmlspecialchars((string) ($article['title'] ?? 'Untitled Article')) ?></h1>
                    </div>

                    <div class="publication-info">
                        <?php
                        $publicationParts = [];

                        if (!empty($publication['volume'])) {
                            $publicationParts[] = 'Volume ' . htmlspecialchars((string) $publication['volume']);
                        }

                        if (!empty($publication['number'])) {
                            $publicationParts[] = 'Number ' . htmlspecialchars((string) $publication['number']);
                        }

                        if (!empty($publication['year'])) {
                            $publicationParts[] = htmlspecialchars((string) $publication['year']);
                        }
                        ?>
                        <?= htmlspecialchars(implode(', ', $publicationParts) ?: 'Publication information unavailable.') ?>
                    </div>

                    <div class="details-section">
                        <h2>Authors</h2>
                        <div class="authors-list">
                            <?php if (!empty($authors)): ?>
                                <?php foreach ($authors as $author): ?>
                                    <div class="author-item">
                                        <?= htmlspecialchars(trim((string) (($author['firstName'] ?? '') . ' ' . ($author['lastName'] ?? '')))) ?: 'Unknown Author' ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="author-item">Unknown Author</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="details-section">
                        <h2>APA Citation</h2>
                        <div class="citation-box">
                            <p id="citation"><?= htmlspecialchars($citation) ?></p>
                            <button type="button" id="copyCitation" class="copy-button">Copy Citation</button>
                        </div>
                    </div>

                    <?php if ($pdfUrl !== ''): ?>
                        <div id="pdfSection" class="pdf-section">
                            <div class="pdf-actions">
                                <button id="readOnline" type="button" class="action-button">Read Online</button>
                                <a id="downloadPdf" class="action-button secondary" href="<?= htmlspecialchars($pdfUrl) ?>?download" download>Download PDF</a>
                            </div>

                            <div id="pdfViewerContainer" class="pdf-viewer-container" style="display: none;">
                                <iframe id="pdfViewer" class="pdf-viewer" title="Journal PDF Viewer"></iframe>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        const pdfUrl = <?= json_encode($pdfUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const readOnline = document.getElementById('readOnline');
        const downloadPdf = document.getElementById('downloadPdf');
        const pdfViewer = document.getElementById('pdfViewer');
        const pdfViewerContainer = document.getElementById('pdfViewerContainer');
        const copyCitation = document.getElementById('copyCitation');
        const citation = document.getElementById('citation');

        if (pdfUrl) {
            if (downloadPdf) {
                downloadPdf.href = pdfUrl.includes('?') ? `${pdfUrl}&download` : `${pdfUrl}?download`;
                downloadPdf.target = '_blank';
                downloadPdf.rel = 'noopener';
            }

            if (readOnline && pdfViewer && pdfViewerContainer) {
                readOnline.addEventListener('click', function () {
                    pdfViewer.src = pdfUrl;
                    pdfViewerContainer.style.display = 'block';
                    pdfViewerContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        }

        if (copyCitation && citation) {
            copyCitation.addEventListener('click', async function () {
                const citationText = citation.textContent.trim();

                if (!citationText) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(citationText);
                    const originalText = copyCitation.textContent;
                    copyCitation.textContent = 'Copied!';
                    setTimeout(() => {
                        copyCitation.textContent = originalText;
                    }, 1500);
                } catch (err) {
                    console.error('Copy citation error:', err);

                    const textArea = document.createElement('textarea');
                    textArea.value = citationText;
                    textArea.style.position = 'fixed';
                    textArea.style.opacity = '0';
                    textArea.style.pointerEvents = 'none';
                    document.body.appendChild(textArea);
                    textArea.select();

                    try {
                        document.execCommand('copy');
                        const originalText = copyCitation.textContent;
                        copyCitation.textContent = 'Copied!';
                        setTimeout(() => {
                            copyCitation.textContent = originalText;
                        }, 1500);
                    } catch (fallbackError) {
                        console.error('Fallback copy failed:', fallbackError);
                        alert('Unable to copy the citation.');
                    } finally {
                        document.body.removeChild(textArea);
                    }
                }
            });
        }
    </script>
</body>
</html>