<?php

require_once '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require_once '../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

$message = '';
$messageType = '';

/*
|--------------------------------------------------------------------------
| SUPABASE SETTINGS
|--------------------------------------------------------------------------
*/

$supabaseUrl = $_ENV['SUPABASE_URL'];
$supabaseKey = $_ENV['SUPABASE_SECRET_KEY'];
$bucketName = $_ENV['SUPABASE_BUCKET'];


/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
|--------------------------------------------------------------------------
|
| Upload a PDF to Supabase Storage.
|
*/

function uploadPdfToSupabase(
    string $supabaseUrl,
    string $supabaseKey,
    string $bucketName,
    string $storagePath,
    string $fileContents
): void {

    $uploadUrl =
        rtrim($supabaseUrl, '/') .
        '/storage/v1/object/' .
        rawurlencode($bucketName) .
        '/' .
        str_replace(
            '%2F',
            '/',
            rawurlencode($storagePath)
        );

    $ch = curl_init($uploadUrl);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileContents,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $supabaseKey,
            'apikey: ' . $supabaseKey,
            'Content-Type: application/pdf',
            'x-upsert: true'
        ]
    ]);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        throw new Exception(
            'Supabase upload failed: ' . $curlError
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception(
            'Supabase upload failed. HTTP ' .
                $httpCode .
                ': ' .
                $response
        );
    }
}


/*
|--------------------------------------------------------------------------
| CREATE STORAGE FILENAME
|--------------------------------------------------------------------------
|
| Uses the same filename convention as manage_journal_api.php.
|
*/

function createStorageFilename(
    string $originalName,
    string $prefix
): string {

    $originalName = basename($originalName);

    $safeFileName = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        $originalName
    );

    return
        $prefix .
        '_' .
        uniqid('', true) .
        '_' .
        $safeFileName;
}


/*
|--------------------------------------------------------------------------
| VALIDATE PDF FILE
|--------------------------------------------------------------------------
*/

function validatePdfUpload(array $file): string
{
    if (
        !isset($file['error']) ||
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        throw new Exception(
            'Please select a PDF file.'
        );
    }

    $extension = strtolower(
        pathinfo(
            $file['name'],
            PATHINFO_EXTENSION
        )
    );

    if ($extension !== 'pdf') {
        throw new Exception(
            'Only PDF files are allowed.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file(
        $file['tmp_name']
    );

    if ($mimeType !== 'application/pdf') {
        throw new Exception(
            'The uploaded file is not a valid PDF.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 20 MB MAXIMUM
    |--------------------------------------------------------------------------
    */

    $maxFileSize = 20 * 1024 * 1024;

    if ($file['size'] > $maxFileSize) {
        throw new Exception(
            'PDF files must not exceed 20 MB.'
        );
    }

    $contents = file_get_contents(
        $file['tmp_name']
    );

    if ($contents === false) {
        throw new Exception(
            'Could not read the uploaded PDF.'
        );
    }

    return $contents;
}


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $uploadType = $_POST['uploadType'] ?? '';

    try {

        /*
        |--------------------------------------------------------------------------
        | EDITORIAL NOTE PDF
        |--------------------------------------------------------------------------
        */

        if ($uploadType === 'editorial') {

            $publicationID = isset(
                $_POST['publicationID']
            )
                ? (int) $_POST['publicationID']
                : 0;

            if ($publicationID <= 0) {
                throw new Exception(
                    'Invalid publication issue selected.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET EXISTING ARCHIVE ISSUE
            |--------------------------------------------------------------------------
            */

            $issueSQL = '
                SELECT
                    "publicationID",
                    "year",
                    "volume",
                    "number",
                    "publicationPDF"
                FROM "PublicationIssue"
                WHERE
                    "publicationID" = :publicationID
                    AND "is_current" = false
                    AND "is_draft" = false
                LIMIT 1
            ';

            $issueStmt = $pdo->prepare(
                $issueSQL
            );

            $issueStmt->execute([
                ':publicationID' => $publicationID
            ]);

            $issue = $issueStmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$issue) {
                throw new Exception(
                    'The selected publication issue does not exist or is not an archive issue.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE FILE
            |--------------------------------------------------------------------------
            */

            $fileContents = validatePdfUpload(
                $_FILES['editorialPDF']
            );


            /*
            |--------------------------------------------------------------------------
            | CREATE STORAGE FILENAME
            |--------------------------------------------------------------------------
            |
            | Same structure as manage_journal_api.php:
            |
            | {year}/publication_{publicationID}/filename.pdf
            |
            */

            $filename = createStorageFilename(
                $_FILES['editorialPDF']['name'],
                'publication_issue'
            );

            $storagePath =
                $issue['year'] .
                '/publication_' .
                $issue['publicationID'] .
                '/' .
                $filename;


            /*
            |--------------------------------------------------------------------------
            | UPLOAD TO SUPABASE
            |--------------------------------------------------------------------------
            */

            uploadPdfToSupabase(
                $supabaseUrl,
                $supabaseKey,
                $bucketName,
                $storagePath,
                $fileContents
            );


            /*
            |--------------------------------------------------------------------------
            | UPDATE PUBLICATION ISSUE
            |--------------------------------------------------------------------------
            */

            $updateSQL = '
                UPDATE "PublicationIssue"
                SET
                    "publicationPDF" = :publicationPDF
                WHERE
                    "publicationID" = :publicationID
            ';

            $updateStmt = $pdo->prepare(
                $updateSQL
            );

            $updateStmt->execute([
                ':publicationPDF' => $storagePath,
                ':publicationID' => $publicationID
            ]);


            $message =
                'Editorial note PDF uploaded successfully for ' .
                $issue['year'] .
                ' | Volume ' .
                $issue['volume'] .
                ' | Number ' .
                $issue['number'] .
                '.';

            $messageType = 'success';
        }


        /*
        |--------------------------------------------------------------------------
        | ARTICLE PDF
        |--------------------------------------------------------------------------
        */ elseif ($uploadType === 'article') {

            $journalID = isset(
                $_POST['journalID']
            )
                ? (int) $_POST['journalID']
                : 0;

            if ($journalID <= 0) {
                throw new Exception(
                    'Invalid article selected.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | GET EXISTING ARCHIVE ARTICLE
            |--------------------------------------------------------------------------
            */

            $articleSQL = '
                SELECT
                    ja."journalID",
                    ja."title",
                    ja."journalPDF",
                    ja."publicationID",
                    pi."year",
                    pi."volume",
                    pi."number"
                FROM "JournalArticle" ja
                INNER JOIN "PublicationIssue" pi
                    ON ja."publicationID"
                    = pi."publicationID"
                WHERE
                    ja."journalID" = :journalID
                    AND pi."is_current" = false
                    AND pi."is_draft" = false
                LIMIT 1
            ';

            $articleStmt = $pdo->prepare(
                $articleSQL
            );

            $articleStmt->execute([
                ':journalID' => $journalID
            ]);

            $article = $articleStmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$article) {
                throw new Exception(
                    'The selected article does not exist or is not an archive article.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | VALIDATE FILE
            |--------------------------------------------------------------------------
            */

            $fileContents = validatePdfUpload(
                $_FILES['articlePDF']
            );


            /*
            |--------------------------------------------------------------------------
            | CREATE STORAGE FILENAME
            |--------------------------------------------------------------------------
            |
            | Same filename convention as manage_journal_api.php:
            |
            | article_{uniqueID}_{originalName}.pdf
            |
            */

            $filename = createStorageFilename(
                $_FILES['articlePDF']['name'],
                'article'
            );

            $storagePath =
                $article['year'] .
                '/publication_' .
                $article['publicationID'] .
                '/' .
                $filename;


            /*
            |--------------------------------------------------------------------------
            | UPLOAD TO SUPABASE
            |--------------------------------------------------------------------------
            */

            uploadPdfToSupabase(
                $supabaseUrl,
                $supabaseKey,
                $bucketName,
                $storagePath,
                $fileContents
            );


            /*
            |--------------------------------------------------------------------------
            | UPDATE JOURNAL ARTICLE
            |--------------------------------------------------------------------------
            */

            $updateSQL = '
                UPDATE "JournalArticle"
                SET
                    "journalPDF" = :journalPDF
                WHERE
                    "journalID" = :journalID
            ';

            $updateStmt = $pdo->prepare(
                $updateSQL
            );

            $updateStmt->execute([
                ':journalPDF' => $storagePath,
                ':journalID' => $journalID
            ]);


            $message =
                'Article PDF uploaded successfully for "' .
                $article['title'] .
                '".';

            $messageType = 'success';
        } else {

            throw new Exception(
                'Invalid upload type.'
            );
        }
    } catch (Throwable $e) {

        $message =
            'Upload failed: ' .
            $e->getMessage();

        $messageType = 'error';
    }
}


/*
|--------------------------------------------------------------------------
| LOAD ARCHIVE ISSUES AND ARTICLES
|--------------------------------------------------------------------------
*/

$issues = [];

try {

    $issueSQL = '
        SELECT
            "publicationID",
            "year",
            "volume",
            "number",
            "publicationPDF"
        FROM "PublicationIssue"
        WHERE
            "is_current" = false
            AND "is_draft" = false
        ORDER BY
            "year" DESC,
            "volume" DESC,
            "number" DESC
    ';

    $issueStmt = $pdo->query(
        $issueSQL
    );

    $issues = $issueStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    /*
    |--------------------------------------------------------------------------
    | LOAD ARTICLES + AUTHORS
    |--------------------------------------------------------------------------
    */

    foreach ($issues as &$issue) {

        $articleSQL = '
            SELECT
                ja."journalID",
                ja."title",
                ja."journalPDF",
                ja."publicationID"
            FROM "JournalArticle" ja
            WHERE
                ja."publicationID" = :publicationID
            ORDER BY
                ja."journalID" ASC
        ';

        $articleStmt = $pdo->prepare(
            $articleSQL
        );

        $articleStmt->execute([
            ':publicationID' =>
            $issue['publicationID']
        ]);

        $issue['articles'] =
            $articleStmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | LOAD AUTHORS FOR EACH ARTICLE
        |--------------------------------------------------------------------------
        */

        foreach (
            $issue['articles']
            as &$article
        ) {

            $authorSQL = '
                SELECT
                    a."firstName",
                    a."lastName"
                FROM "Author" a
                INNER JOIN "ArticleAuthor" aa
                    ON a."authorID" = aa."authorID"
                WHERE
                    aa."journalID" = :journalID
                ORDER BY
                    a."lastName" ASC,
                    a."firstName" ASC
            ';

            $authorStmt = $pdo->prepare(
                $authorSQL
            );

            $authorStmt->execute([
                ':journalID' =>
                $article['journalID']
            ]);

            $article['authors'] =
                $authorStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

        unset($article);
    }

    unset($issue);
} catch (Throwable $e) {

    $message =
        'Could not load archive data: ' .
        $e->getMessage();

    $messageType = 'error';
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Temporary Archive PDF Uploader
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 40px 20px;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f5f5f5;
            color: #222;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        h1 {
            margin: 0 0 8px;
        }

        .description {
            margin: 0 0 30px;
            color: #666;
        }

        .message {
            padding: 12px 15px;
            margin-bottom: 25px;
            border-radius: 5px;
        }

        .success {
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #256029;
        }

        .error {
            background: #ffebee;
            border: 1px solid #ef9a9a;
            color: #b71c1c;
        }

        .issue {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            border-radius: 7px;
            overflow: hidden;
        }

        .issue-header {
            padding: 18px 20px;
            background: #800000;
            color: #fff;
        }

        .issue-header h2 {
            margin: 0 0 5px;
            font-size: 20px;
        }

        .issue-header p {
            margin: 0;
            font-size: 14px;
            opacity: .9;
        }

        .issue-body {
            padding: 20px;
        }

        .editorial-section {
            padding: 18px;
            margin-bottom: 20px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .editorial-section h3 {
            margin: 0 0 12px;
            font-size: 17px;
        }

        .article {
            padding: 18px;
            margin-bottom: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .article:last-child {
            margin-bottom: 0;
        }

        .article h3 {
            margin: 0 0 8px;
            font-size: 17px;
        }

        .authors {
            margin-bottom: 10px;
            color: #555;
            font-size: 14px;
        }

        .ids {
            margin-bottom: 12px;
            color: #888;
            font-size: 12px;
        }

        .status {
            margin-bottom: 12px;
            padding: 8px 10px;
            border-radius: 4px;
            background: #eee;
            color: #555;
            font-size: 13px;
        }

        .status.uploaded {
            background: #e8f5e9;
            color: #256029;
        }

        .upload-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .upload-form input[type="file"] {
            flex: 1;
            padding: 9px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background: #fff;
        }

        button {
            flex-shrink: 0;
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            background: #800000;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            background: #660000;
        }

        .note {
            margin-top: 25px;
            padding: 12px 15px;
            background: #f8f8f8;
            border-left: 4px solid #800000;
            color: #555;
            font-size: 14px;
        }

        @media (max-width: 700px) {

            .container {
                padding: 20px;
            }

            .upload-form {
                flex-direction: column;
                align-items: stretch;
            }

            button {
                width: 100%;
            }
        }
    </style>

</head>


<body>

    <div class="container">

        <h1>
            Temporary Archive PDF Uploader
        </h1>

        <p class="description">
            Upload editorial notes and article PDFs
            for existing archive issues.
            No issues, articles, or authors will be created.
        </p>


        <?php if ($message !== ''): ?>

            <div
                class="message
            <?= htmlspecialchars($messageType) ?>">
                <?= htmlspecialchars($message) ?>
            </div>

        <?php endif; ?>


        <?php if (empty($issues)): ?>

            <div class="note">
                No archive issues were found.
            </div>

        <?php else: ?>


            <?php foreach ($issues as $issue): ?>

                <div class="issue">


                    <!-- ==================================================
                     ISSUE HEADER
                =================================================== -->

                    <div class="issue-header">

                        <h2>

                            <?= htmlspecialchars(
                                $issue['year']
                            ) ?>

                            |

                            Volume

                            <?= htmlspecialchars(
                                $issue['volume']
                            ) ?>

                            |

                            Number

                            <?= htmlspecialchars(
                                $issue['number']
                            ) ?>

                        </h2>

                        <p>

                            Publication ID:

                            <?= htmlspecialchars(
                                $issue['publicationID']
                            ) ?>

                        </p>

                    </div>


                    <div class="issue-body">


                        <!-- ==================================================
                         EDITORIAL NOTE
                    =================================================== -->

                        <div class="editorial-section">

                            <h3>
                                Editorial Note PDF
                            </h3>


                            <?php if (
                                !empty($issue['publicationPDF'])
                            ): ?>

                                <div class="status uploaded">

                                    PDF already uploaded:

                                    <?= htmlspecialchars(
                                        $issue['publicationPDF']
                                    ) ?>

                                </div>

                            <?php else: ?>

                                <div class="status">

                                    No editorial note PDF uploaded yet.

                                </div>

                            <?php endif; ?>


                            <form
                                method="POST"
                                enctype="multipart/form-data"
                                class="upload-form">

                                <input
                                    type="hidden"
                                    name="uploadType"
                                    value="editorial">

                                <input
                                    type="hidden"
                                    name="publicationID"
                                    value="<?= htmlspecialchars(
                                                $issue['publicationID']
                                            ) ?>">

                                <input
                                    type="file"
                                    name="editorialPDF"
                                    accept="application/pdf,.pdf"
                                    required>

                                <button type="submit">

                                    <?= !empty($issue['publicationPDF'])
                                        ? 'Replace Editorial PDF'
                                        : 'Upload Editorial PDF'
                                    ?>

                                </button>

                            </form>

                        </div>


                        <!-- ==================================================
                         ARTICLES
                    =================================================== -->

                        <h3>
                            Articles
                        </h3>


                        <?php if (
                            empty($issue['articles'])
                        ): ?>

                            <div class="note">
                                No articles found for this issue.
                            </div>

                        <?php else: ?>


                            <?php foreach (
                                $issue['articles']
                                as $article
                            ): ?>

                                <div class="article">


                                    <h3>

                                        <?= htmlspecialchars(
                                            $article['title']
                                        ) ?>

                                    </h3>


                                    <!-- AUTHORS -->

                                    <div class="authors">

                                        <strong>
                                            Authors:
                                        </strong>

                                        <?php

                                        $authorNames = [];

                                        foreach (
                                            $article['authors']
                                            as $author
                                        ) {

                                            $authorNames[] =
                                                $author['firstName'] .
                                                ' ' .
                                                $author['lastName'];
                                        }

                                        echo htmlspecialchars(
                                            implode(
                                                ', ',
                                                $authorNames
                                            )
                                        );

                                        ?>

                                    </div>


                                    <!-- IDs -->

                                    <div class="ids">

                                        Journal ID:

                                        <?= htmlspecialchars(
                                            $article['journalID']
                                        ) ?>

                                        |

                                        Publication ID:

                                        <?= htmlspecialchars(
                                            $article['publicationID']
                                                ?? $issue['publicationID']
                                        ) ?>

                                    </div>


                                    <!-- PDF STATUS -->

                                    <?php if (
                                        !empty($article['journalPDF'])
                                    ): ?>

                                        <div class="status uploaded">

                                            PDF already uploaded:

                                            <?= htmlspecialchars(
                                                $article['journalPDF']
                                            ) ?>

                                        </div>

                                    <?php else: ?>

                                        <div class="status">

                                            No article PDF uploaded yet.

                                        </div>

                                    <?php endif; ?>


                                    <!-- ARTICLE PDF FORM -->

                                    <form
                                        method="POST"
                                        enctype="multipart/form-data"
                                        class="upload-form">

                                        <input
                                            type="hidden"
                                            name="uploadType"
                                            value="article">

                                        <input
                                            type="hidden"
                                            name="journalID"
                                            value="<?= htmlspecialchars(
                                                        $article['journalID']
                                                    ) ?>">

                                        <input
                                            type="file"
                                            name="articlePDF"
                                            accept="application/pdf,.pdf"
                                            required>

                                        <button type="submit">

                                            <?= !empty($article['journalPDF'])
                                                ? 'Replace Article PDF'
                                                : 'Upload Article PDF'
                                            ?>

                                        </button>

                                    </form>


                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>


                    </div>

                </div>

            <?php endforeach; ?>


        <?php endif; ?>


        <div class="note">

            <strong>Important:</strong>

            This temporary uploader only updates existing
            PDF fields.

            <br><br>

            Editorial Note:

            <strong>
                PublicationIssue.publicationPDF
            </strong>

            <br>

            Article PDF:

            <strong>
                JournalArticle.journalPDF
            </strong>

            <br><br>

            Existing issue, article, author, Journal ID,
            and Publication ID records are not recreated.

        </div>


    </div>

</body>

</html>