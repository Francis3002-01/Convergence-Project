<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../Classes/JournalArticle.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$supabaseUrl = trim($_ENV['SUPABASE_URL'] ?? '');
$supabaseKey = trim($_ENV['SUPABASE_SECRET_KEY'] ?? '');
$bucket = trim($_ENV['SUPABASE_BUCKET'] ?? '');

if ($supabaseUrl === '') {
    throw new Exception('SUPABASE_URL is not configured.');
}

if ($supabaseKey === '') {
    throw new Exception('SUPABASE_SECRET_KEY is not configured.');
}

if ($bucket === '') {
    throw new Exception('SUPABASE_BUCKET is not configured.');
}


function sendResponse(bool $success,string $message = '',array $data = [],int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

function normalizeStoragePath(string $storagePath): string
{
    global $bucket;

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
        $objectMarker = '/storage/v1/object/';
        $markerPosition = strpos($storagePath, $objectMarker);

        if ($markerPosition !== false) {
            $storagePath = substr(
                $storagePath,
                $markerPosition + strlen($objectMarker)
            );

            $storagePath = preg_replace(
                '#^(public|sign)/#',
                '',
                $storagePath
            );
        }
    }

    $storagePath = ltrim($storagePath, '/');

    $bucketName = trim($bucket, '/');
    $bucketPrefix = $bucketName . '/';

    if (str_starts_with($storagePath, $bucketPrefix)) {
        $storagePath = substr(
            $storagePath,
            strlen($bucketPrefix)
        );
    }

    $storagePath = preg_replace('#/+#', '/', $storagePath);
    $storagePath = rtrim($storagePath, '/');

    if ($storagePath === '') {
        throw new Exception('The storage path is empty.');
    }

    return $storagePath;
}


function createSignedPdfUrl(string $storagePath,int $expiresIn = 3600): string {
    global $supabaseUrl, $supabaseKey, $bucket;

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        return $storagePath;
    }

    $bucketName = trim($bucket, '/');

    if ($bucketName === '') {
        throw new Exception(
            'Supabase storage bucket is not configured.'
        );
    }

    $storagePath = normalizeStoragePath($storagePath);

    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode('/', $storagePath)
        )
    );

    $url =
        rtrim($supabaseUrl, '/') .
        '/storage/v1/object/sign/' .
        rawurlencode($bucketName) .
        '/' .
        $encodedPath;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'expiresIn' => $expiresIn
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $supabaseKey,
            'apikey: ' . $supabaseKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        throw new Exception(
            'Unable to create signed PDF URL: ' .
            ($curlError ?: 'Unknown cURL error.')
        );
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception(
            'Unable to create signed PDF URL. Supabase returned HTTP ' .
            $httpCode .
            ': ' .
            $response
        );
    }

    $result = json_decode($response, true);

    if (!is_array($result)) {
        throw new Exception('Supabase returned an invalid signed URL response.');
    }

    $signedUrl = trim((string) ($result['signedURL'] ?? ''));

    if ($signedUrl === '') {
        throw new Exception('Supabase did not return a signed PDF URL.');
    }

    if (str_starts_with($signedUrl, '/')) {
        $signedUrl =
            rtrim($supabaseUrl, '/') .
            $signedUrl;
    }

    return $signedUrl;
}


function createPublicPdfUrl(string $storagePath): string
{
    global $supabaseUrl, $bucket;

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        return $storagePath;
    }

    $bucketName = trim($bucket, '/');

    if ($bucketName === '') {
        throw new Exception(
            'Supabase storage bucket is not configured.'
        );
    }

    $storagePath = normalizeStoragePath($storagePath);

    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode('/', $storagePath)
        )
    );

    return
        rtrim($supabaseUrl, '/') .
        '/storage/v1/object/public/' .
        rawurlencode($bucketName) .
        '/' .
        $encodedPath;
}


function validatePdf(array $file,string $description): void {
    
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception($description . ' upload failed.');
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception( $description . ' is invalid.');
    }

    $mimeType = mime_content_type( $file['tmp_name']);

    if ($mimeType !== 'application/pdf') {
        throw new Exception($description . ' must be a PDF.');
    }

    $maxFileSize = 40 * 1024 * 1024;

    if ($file['size'] > $maxFileSize) {
        throw new Exception(
            $description .
            ' exceeds the 40 MB limit.'
        );
    }
}


function createStorageFilename(string $originalName,string $prefix): string 
{
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


function uploadPdfsConcurrently(array $uploads): void
{
    global $supabaseUrl, $supabaseKey, $bucket;

    if (empty($uploads)) {
        return;
    }

    $multiHandle = curl_multi_init();
    $handles = [];

    try {
        foreach ($uploads as $index => $upload) {
            $localFile = $upload['localFile'];

            $storagePath = normalizeStoragePath(
                $upload['storagePath']
            );

            $fileContents = file_get_contents(
                $localFile
            );

            if ($fileContents === false) {
                throw new Exception('Unable to read PDF file.');
            }

            $encodedPath = implode(
                '/',
                array_map(
                    'rawurlencode',
                    explode('/', $storagePath)
                )
            );

            $url =
                rtrim($supabaseUrl, '/') .
                '/storage/v1/object/' .
                rawurlencode($bucket) .
                '/' .
                $encodedPath;

            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fileContents,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' .
                        $supabaseKey,
                    'apikey: ' .
                        $supabaseKey,
                    'Content-Type: application/pdf',
                    'Content-Length: ' .
                        strlen($fileContents)
                ],
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_TCP_KEEPALIVE => 1
            ]);

            curl_multi_add_handle(
                $multiHandle,
                $ch
            );

            $handles[$index] = $ch;
        }

        $running = null;

        do {
            $status = curl_multi_exec($multiHandle,$running);

            if ($running) {
                curl_multi_select(
                    $multiHandle,
                    1.0
                );
            }

        }while ($running &&$status === CURLM_OK);

        foreach ($handles as $index => $ch) {
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );
            $curlError = curl_error($ch);

            if ($response === false ||$httpCode < 200 ||$httpCode >= 300) {
                throw new Exception('PDF upload failed for ' .$uploads[$index]['description'] .': ' .($curlError ?: $response));
            }

            curl_multi_remove_handle($multiHandle,$ch);

            curl_close($ch);
        }
    } 
    
    finally {
        curl_multi_close($multiHandle);
    }
}

function deletePdf(string $storagePath): void
{
    global $supabaseUrl, $supabaseKey, $bucket;

    if (trim($storagePath) === '') {
        return;
    }

    $storagePath = normalizeStoragePath($storagePath);

    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode('/', $storagePath)
        )
    );

    $url =
        rtrim($supabaseUrl, '/') .
        '/storage/v1/object/' .
        rawurlencode($bucket) .
        '/' .
        $encodedPath;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' .
                $supabaseKey,
            'apikey: ' .
                $supabaseKey
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60
    ]);

    curl_exec($ch);
    curl_close($ch);
}


function normalizeArticleData(array $articles): array {

    $normalized = [];

    foreach ($articles as $article) {
        $normalized[] = [
            'journalID' =>
                isset($article['journalID']) &&
                $article['journalID'] !== null
                    ? (int) $article['journalID']
                    : null,

            'title' =>
                trim(
                    (string) (
                        $article['title'] ?? ''
                    )
                ),

            'authors' =>
                is_array(
                    $article['authors'] ?? null
                )
                    ? $article['authors']
                    : [],

            'existingPdf' =>
                trim(
                    (string) (
                        $article['existingPdf'] ?? ''
                    )
                )
        ];
    }

    return $normalized;
}


function getIssueByPublicationId(PDO $pdo,int $publicationID): ?array {

    $statement = $pdo->prepare(
        'SELECT *
         FROM "PublicationIssue"
         WHERE "publicationID" = :publicationID
         LIMIT 1'
    );

    $statement->execute([
        ':publicationID' => $publicationID
    ]);

    $issue = $statement->fetch();

    return $issue ?: null;
}


function ensureOneDraft(PDO $pdo): void
{
    $draft = $pdo->query(
        'SELECT "publicationID"
         FROM "PublicationIssue"
         WHERE "is_draft" = TRUE
         LIMIT 1'
    )->fetchColumn();

    if ($draft !== false) {
        throw new Exception(
            'A draft issue already exists.'
        );
    }
}

function saveArticleAuthors(PDO $pdo, int $journalID,array $authors): void {

    if (empty($authors)) {
        throw new Exception('An article must have at least one author.');
    }

    $pdo->prepare(
        'DELETE FROM "ArticleAuthor"
         WHERE "journalID" = :journalID'
    )->execute([
        ':journalID' => $journalID
    ]);

    foreach ($authors as $author) {
        $firstName = trim(
            (string) (
                $author['firstName'] ?? ''
            )
        );

        $lastName = trim((string) ($author['lastName'] ?? ''));

        if ($firstName === '' ||$lastName === '') {
            throw new Exception('Author first name and last name are required.');
        }

        $findAuthor = $pdo->prepare(
            'SELECT "authorID"
             FROM "Author"
             WHERE "firstName" = :firstName
               AND "lastName" = :lastName
             LIMIT 1'
        );

        $findAuthor->execute([
            ':firstName' => $firstName,
            ':lastName' => $lastName
        ]);

        $authorID = $findAuthor->fetchColumn();

        if ($authorID === false) {
            $createAuthor = $pdo->prepare(
                'INSERT INTO "Author"
                    ("firstName", "lastName")
                 VALUES
                    (:firstName, :lastName)
                 RETURNING "authorID"'
            );

            $createAuthor->execute([
                ':firstName' => $firstName,
                ':lastName' => $lastName
            ]);

            $authorID = $createAuthor->fetchColumn();

            if ($authorID === false) {
                throw new Exception(
                    'Unable to create author.'
                );
            }
        }

        $insertLink = $pdo->prepare(
            'INSERT INTO "ArticleAuthor"
                ("journalID", "authorID")
             VALUES
                (:journalID, :authorID)'
        );

        $insertLink->execute([
            ':journalID' => $journalID,
            ':authorID' => (int) $authorID
        ]);
    }
}


function getIssuePayload(PDO $pdo,int $publicationID): array {
    
    $issue = getIssueByPublicationId($pdo,$publicationID);

    if (!$issue) {
        throw new Exception('Issue not found.');
    }

    $statement = $pdo->prepare(
        'SELECT
            ja."journalID",
            ja."title",
            ja."journalPDF",
            ja."publicationID",
            a."firstName",
            a."lastName"
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

    $statement->execute([
        ':publicationID' => $publicationID
    ]);

    $articles = [];

    foreach ($statement->fetchAll() as $row) {
        $journalID = (int) $row['journalID'];

        if (!isset($articles[$journalID])) {
            $pdfPath = trim(
                (string) (
                    $row['journalPDF'] ?? ''
                )
            );

            $articles[$journalID] = [
                'journalID' => $journalID,
                'title' => $row['title'],

                /*
                 * Keep the storage path.
                 * No Supabase request is made here.
                 */
                'journalPDF' =>
                    $pdfPath !== ''
                        ? $pdfPath
                        : null,

                /*
                 * Empty during initial loading.
                 * edit_journal.js will request a signed URL
                 * only when the administrator opens the PDF.
                 */
                'pdfUrl' => '',

                'authors' => []
            ];
        }

        if (!empty($row['firstName']) || !empty($row['lastName'])) {
            $articles[$journalID]['authors'][] = [
                'firstName' =>
                    $row['firstName'] ?? '',
                'lastName' =>
                    $row['lastName'] ?? ''
            ];
        }
    }

    $publicationPdfPath = trim((string) ($issue['publicationPDF'] ?? ''));

    return [
        'publicationID' =>
            (int) $issue['publicationID'],

        'year' =>
            (int) $issue['year'],

        'volume' =>
            (int) $issue['volume'],

        'number' =>
            (int) $issue['number'],

        /*
         * Keep the storage path.
         */
        'publicationPDF' =>
            $publicationPdfPath !== ''
                ? $publicationPdfPath
                : null,

        /*
         * No signed URL is generated here.
         */
        'publicationPdfUrl' => '',

        'is_current' =>
            (bool) $issue['is_current'],

        'is_draft' =>
            (bool) $issue['is_draft'],

        'articles' =>
            array_values($articles)
    ];
}


function getArticlePayload(PDO $pdo,int $journalID): array {
    $statement = $pdo->prepare(
        'SELECT
            ja."journalID",
            ja."title",
            ja."journalPDF",
            ja."publicationID",
            pi."year",
            pi."volume",
            pi."number",
            pi."publicationPDF"
         FROM "JournalArticle" ja
         INNER JOIN "PublicationIssue" pi
            ON ja."publicationID" =
               pi."publicationID"
         WHERE ja."journalID" = :journalID
         LIMIT 1'
    );

    $statement->execute([
        ':journalID' => $journalID
    ]);

    $article = $statement->fetch();

    if (!$article) {
        throw new Exception(
            'Journal article not found.'
        );
    }

    $authorStatement = $pdo->prepare(
        'SELECT
            a."firstName",
            a."lastName"
         FROM "ArticleAuthor" aa
         INNER JOIN "Author" a
            ON a."authorID" = aa."authorID"
         WHERE aa."journalID" = :journalID
         ORDER BY
            a."lastName",
            a."firstName"'
    );

    $authorStatement->execute([
        ':journalID' => $journalID
    ]);

    $authors = [];

    foreach ($authorStatement->fetchAll() as $author) {
        $authors[] = [
            'firstName' =>
                $author['firstName'] ?? '',
            'lastName' =>
                $author['lastName'] ?? ''
        ];
    }

    $pdfUrl = '';

    if (!empty($article['journalPDF'])) {
        $pdfUrl = createPublicPdfUrl((string) $article['journalPDF']);
    }

    $citationAuthors = [];

    foreach ($authors as $author) {
        $firstName = trim(
            (string) (
                $author['firstName'] ?? ''
            )
        );

        $lastName = trim((string) ($author['lastName'] ?? ''));

        if ($firstName === '' &&$lastName === '') {
            continue;
        }

        $initials = '';

        if ($firstName !== '') {
            $parts = preg_split(
                '/\s+/',
                $firstName
            );

            foreach ($parts as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $initials .=
                        strtoupper(
                            mb_substr(
                                $part,
                                0,
                                1
                            )
                        ) .
                        '. ';
                }
            }

            $initials = trim($initials);
        }

        if ($lastName !== '' &&$initials !== '') {
            $citationAuthors[] =
                $lastName .
                ', ' .
                $initials;
        } 
        
        elseif ($lastName !== '') {
            $citationAuthors[] = $lastName;
        } 
        
        else {
            $citationAuthors[] = $initials;
        }
    }

    $authorText = '';
    $authorCount = count($citationAuthors);

    if ($authorCount === 1) {
        $authorText =
            $citationAuthors[0] . ' ';
    } 
    
    elseif ($authorCount === 2) {
        $authorText =
            $citationAuthors[0] .
            ', & ' .
            $citationAuthors[1] .
            ' ';
    } 
    
    elseif ($authorCount > 2) {
        $lastAuthor =
            array_pop($citationAuthors);

        $authorText =
            implode(
                ', ',
                $citationAuthors
            ) .
            ', & ' .
            $lastAuthor .
            ' ';
    }

    $year = !empty($article['year'])
        ? (string) $article['year']
        : 'n.d.';

    $title = trim(
        (string) (
            $article['title'] ?? ''
        )
    );

    if ($title === '') {
        $title = 'Untitled Article';
    }

    $volume = !empty($article['volume'])
        ? (string) $article['volume']
        : '';

    $number = !empty($article['number'])
        ? (string) $article['number']
        : '';

    $journalInformation = 'Convergence';

    if ($volume !== '') {
        $journalInformation .=
            ', ' . $volume;

        if ($number !== '') {
            $journalInformation .=
                '(' . $number . ')';
        }
    }

    $citation =
        $authorText .
        '(' .
        $year .
        '). ' .
        $title .
        '. ' .
        $journalInformation .
        '.';

    return [
        'article' => [
            'journalID' =>
                (int) $article['journalID'],

            'title' =>
                (string) $article['title'],

            'publicationID' =>
                (int) $article['publicationID']
        ],

        'publication' => [
            'year' =>
                (int) (
                    $article['year'] ?? 0
                ),

            'volume' =>
                (int) (
                    $article['volume'] ?? 0
                ),

            'number' =>
                (int) (
                    $article['number'] ?? 0
                ),

            'publicationPDF' =>
                $article['publicationPDF'] ?? null
        ],

        'authors' => $authors,
        'pdfUrl' => $pdfUrl,
        'citation' => $citation
    ];
}


/*
|--------------------------------------------------------------------------
| ISSUE LIST
|--------------------------------------------------------------------------
*/

function loadIssueList(PDO $pdo): array
{
    $statement = $pdo->query(
        'SELECT *
         FROM "PublicationIssue"
         ORDER BY
            "year" DESC,
            "volume" DESC,
            "number" DESC,
            "publicationID" DESC'
    );

    $issues = [];

    foreach ($statement->fetchAll() as $row) {
        $issues[(int) $row['publicationID']] = [
            'publicationID' => (int) $row['publicationID'],
            'year' =>(int) $row['year'],
            'volume' =>(int) $row['volume'],
            'number' =>(int) $row['number'],
            'publicationPDF' =>$row['publicationPDF'] ?? null,
            'is_current' =>(bool) $row['is_current'],
            'is_draft' =>(bool) $row['is_draft'],
            'articles' => []
        ];
    }

    if (empty($issues)) {
        return [
            'current' => [],
            'draft' => [],
            'archive' => []
        ];
    }

    $publicationIDs = array_keys($issues);

    $placeholders = implode(
        ',',
        array_fill(
            0,
            count($publicationIDs),
            '?'
        )
    );

    $statement = $pdo->prepare(
        'SELECT
            ja."journalID",
            ja."title",
            ja."journalPDF",
            ja."publicationID",
            a."firstName",
            a."lastName"
         FROM "JournalArticle" ja
         LEFT JOIN "ArticleAuthor" aa
            ON aa."journalID" = ja."journalID"
         LEFT JOIN "Author" a
            ON a."authorID" = aa."authorID"
         WHERE ja."publicationID" IN (' .
            $placeholders .
            ')
         ORDER BY
            ja."publicationID",
            ja."journalID",
            a."authorID"'
    );

    $statement->execute($publicationIDs);

    foreach ($statement->fetchAll() as $row) {
        $publicationID =
            (int) $row['publicationID'];

        $journalID =
            (int) $row['journalID'];

        if (!isset($issues[$publicationID]['articles'][$journalID])) {
            $issues[$publicationID]['articles'][$journalID] = [
                'journalID' => $journalID,
                'title' => $row['title'],
                'journalPDF' =>
                    $row['journalPDF'] ?? null,
                'authors' => []
            ];
        }

        if (!empty($row['firstName']) ||!empty($row['lastName'])) {
            $issues[$publicationID]['articles'][$journalID]['authors'][] = [
                'firstName' =>
                    $row['firstName'] ?? '',
                'lastName' =>
                    $row['lastName'] ?? ''
            ];
        }
    }

    foreach ($issues as $publicationID => &$issue) {

        $issue['articles'] =array_values($issue['articles']);

        if (empty($issue['articles'])) {
            unset($issues[$publicationID]);
        }
    }

    unset($issue);

    $current = [];
    $draft = [];
    $archive = [];

    foreach ($issues as $issue) {
        if ($issue['is_current'] && !$issue['is_draft']) {
            $current[] = $issue;
        } 
        
        elseif (!$issue['is_current'] &&$issue['is_draft']) {
            $draft[] = $issue;
        } 
        
        else {
            $archive[] = $issue;
        }
    }

    return [
        'current' => $current,
        'draft' => $draft,
        'archive' => $archive
    ];
}


/*
|--------------------------------------------------------------------------
| ADD / SAVE ISSUE
|--------------------------------------------------------------------------
*/

function createIssueRecord(PDO $pdo,int $year,int $volume,int $number,string $publicationPDF,bool $isCurrent,bool $isDraft): int {
    $statement = $pdo->prepare(
        'INSERT INTO "PublicationIssue"
            (
                "year",
                "volume",
                "number",
                "publicationPDF",
                "is_current",
                "is_draft"
            )
         VALUES
            (
                :year,
                :volume,
                :number,
                :publicationPDF,
                :is_current,
                :is_draft
            )
         RETURNING "publicationID"'
    );

    $statement->execute([
        ':year' => $year,
        ':volume' => $volume,
        ':number' => $number,
        ':publicationPDF' => $publicationPDF,
        ':is_current' =>
            $isCurrent ? 'true' : 'false',
        ':is_draft' =>
            $isDraft ? 'true' : 'false'
    ]);

    $publicationID =
        $statement->fetchColumn();

    if ($publicationID === false) {
        throw new Exception(
            'Unable to create the publication issue.'
        );
    }

    return (int) $publicationID;
}


function saveIssue(PDO $pdo,int $year,int $volume,int $number,array $articleList,array $publicationFile,bool $isDraft,?int $publicationID = null): int {
    $articleList =
        normalizeArticleData($articleList);

    if (empty($articleList)) {
        throw new Exception('At least one article is required.');
    }

    if (!isset($publicationFile['tmp_name']) ||!is_uploaded_file($publicationFile['tmp_name'])) {
        throw new Exception('Publication issue PDF is required.');
    }

    validatePdf($publicationFile,'Publication issue PDF');

    if ($publicationID === null &&$isDraft) {
        ensureOneDraft($pdo);
    }

    $pdo->beginTransaction();

    try {
        if ($publicationID === null) {
            $publicationID = createIssueRecord($pdo,$year,$volume,$number,'',false,$isDraft);
        } 
        
        else {

            $existingIssue = getIssueByPublicationId($pdo,$publicationID);

            if (!$existingIssue) {
                throw new Exception('Issue not found.');
            }
        }

        $publicationPath =
            $year .
            '/publication_' .
            $publicationID .
            '/' .
            createStorageFilename(
                $publicationFile['name'],
                'publication_issue'
            );

        uploadPdfsConcurrently([
            [
                'localFile' =>
                    $publicationFile['tmp_name'],

                'storagePath' =>
                    $publicationPath,

                'description' =>
                    'Publication issue PDF'
            ]
        ]);

        $pdo->prepare(
            'UPDATE "PublicationIssue"
             SET
                "year" = :year,
                "volume" = :volume,
                "number" = :number,
                "publicationPDF" = :publicationPDF
             WHERE "publicationID" = :publicationID'
        )->execute([
            ':year' => $year,
            ':volume' => $volume,
            ':number' => $number,
            ':publicationPDF' => $publicationPath,
            ':publicationID' => $publicationID
        ]);

        foreach ($articleList as $index => $article) {
            $title = trim(
                (string) (
                    $article['title'] ?? ''
                )
            );

            if ($title === '') {
                throw new Exception(
                    'Title for Article ' .
                    ($index + 1) .
                    ' is required.'
                );
            }

            $authors =$article['authors'] ?? [];

            if (!is_array($authors) ||empty($authors)) {
                throw new Exception(
                    'Article ' .
                    ($index + 1) .
                    ' must have at least one author.'
                );
            }

            $pdfInput =
                $_FILES['pdf_' . $index]
                ?? null;

            if (!is_array($pdfInput) ||empty($pdfInput['tmp_name'])) {
                throw new Exception('PDF for Article ' .($index + 1) .' is required.');
            }

            validatePdf($pdfInput,'PDF for Article ' .($index + 1));

            $pdfPath =
                $year .
                '/publication_' .
                $publicationID .
                '/' .
                createStorageFilename(
                    $pdfInput['name'],
                    'article'
                );

            uploadPdfsConcurrently([
                [
                    'localFile' =>
                        $pdfInput['tmp_name'],

                    'storagePath' =>
                        $pdfPath,

                    'description' =>
                        'Article ' .
                        ($index + 1) .
                        ' PDF'
                ]
            ]);

            $insert = $pdo->prepare(
                'INSERT INTO "JournalArticle"
                    (
                        "title",
                        "journalPDF",
                        "publicationID"
                    )
                 VALUES
                    (
                        :title,
                        :journalPDF,
                        :publicationID
                    )
                 RETURNING "journalID"'
            );

            $insert->execute([
                ':title' => $title,
                ':journalPDF' => $pdfPath,
                ':publicationID' =>
                    $publicationID
            ]);

            $journalID =
                $insert->fetchColumn();

            if ($journalID === false) {
                throw new Exception(
                    'Unable to create journal article.'
                );
            }

            saveArticleAuthors(
                $pdo,
                (int) $journalID,
                $authors
            );
        }

        $pdo->commit();

        return (int) $publicationID;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/*UPDATE HELPERS*/
function getExistingArticles(PDO $pdo,int $publicationID): array {

    $statement = $pdo->prepare(
        'SELECT
            "journalID",
            "title",
            "journalPDF"
         FROM "JournalArticle"
         WHERE "publicationID" = :publicationID
         ORDER BY "journalID" ASC'
    );

    $statement->execute([
        ':publicationID' => $publicationID
    ]);

    $articles = [];

    foreach ($statement->fetchAll() as $article) {
        $articles[
            (int) $article['journalID']
        ] = $article;
    }

    return $articles;
}


function uploadReplacementArticlePdf(int $year,int $publicationID,int $articleNumber,?array $pdfInput): ?string {

    if (!is_array($pdfInput) ||empty($pdfInput['tmp_name'])) {
        return null;
    }

    validatePdf($pdfInput,'PDF for Article ' . $articleNumber);

    $path =
        $year .
        '/publication_' .
        $publicationID .
        '/' .
        createStorageFilename(
            $pdfInput['name'],
            'article'
        );

    uploadPdfsConcurrently([
        [
            'localFile' =>
                $pdfInput['tmp_name'],

            'storagePath' =>
                $path,

            'description' =>
                'Article ' .
                $articleNumber .
                ' PDF'
        ]
    ]);

    return $path;
}


function updateExistingArticle(PDO $pdo,array $article,array $existingArticle,int $year,int $volume,int $number,int $publicationID,string $publicationPdfPath,int $articleNumber): void {
    
    $journalID =(int) $existingArticle['journalID'];
    $title = trim((string) ($article['title'] ?? ''));

    if ($title === '') {
        throw new Exception(
            'Title for Article ' .
            $articleNumber .
            ' is required.'
        );
    }

    $authors =$article['authors'] ?? [];

    if (!is_array($authors) ||empty($authors)) {
        throw new Exception('Article ' .$articleNumber .' must have at least one author.');
    }

    $oldPdf =
        trim(
            (string) (
                $existingArticle['journalPDF']
                ?? ''
            )
        );

    $pdfPath = trim((string) ($article['existingPdf'] ?? ''));

    if ($pdfPath !== '') {
        $pdfPath =
            normalizeStoragePath($pdfPath);
    } 
    
    elseif ($oldPdf !== '') {
        $pdfPath = normalizeStoragePath($oldPdf);
    }

    $newPdf = uploadReplacementArticlePdf($year,$publicationID,$articleNumber,$_FILES['pdf_' . ($articleNumber - 1)]?? null);

    if ($newPdf !== null) {
        $pdfPath = $newPdf;
    }

    if ($pdfPath === '') {
        throw new Exception(
            'PDF for Article ' .
            $articleNumber .
            ' is required.'
        );
    }

    $journalArticle =new JournalArticle();

    $journalArticle->updateJournal($pdo,$journalID,$year,$volume,$number,$title,$pdfPath,$publicationPdfPath,$authors);

    if ($newPdf !== null &&$oldPdf !== '' &&normalizeStoragePath($oldPdf) !==$newPdf) {
        try {
            deletePdf($oldPdf);
        } 
        
        catch (Throwable $e) {
            error_log('Old article PDF cleanup error: ' .$e->getMessage());
        }
    }
}

function insertNewArticle(PDO $pdo,array $article,int $publicationID,int $year,int $articleNumber): void {

    $title = trim((string) ($article['title'] ?? ''));

    if ($title === '') {
        throw new Exception('Title for Article ' .$articleNumber .' is required.');
    }

    $authors = $article['authors'] ?? [];

    if (!is_array($authors) ||empty($authors)) {
        throw new Exception('Article ' .$articleNumber .' must have at least one author.');
    }

    $pdfPath = uploadReplacementArticlePdf($year,$publicationID,$articleNumber,$_FILES['pdf_' . ($articleNumber - 1)]?? null);

    if ($pdfPath === null) {
        throw new Exception('PDF for Article ' .$articleNumber .' is required.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO "JournalArticle"
            (
                "title",
                "journalPDF",
                "publicationID"
            )
         VALUES
            (
                :title,
                :journalPDF,
                :publicationID
            )
         RETURNING "journalID"'
    );

    $insert->execute([
        ':title' => $title,
        ':journalPDF' => $pdfPath,
        ':publicationID' => $publicationID
    ]);

    $journalID =
        $insert->fetchColumn();

    if ($journalID === false) {
        throw new Exception(
            'Unable to create Article ' .
            $articleNumber .
            '.'
        );
    }

    saveArticleAuthors(
        $pdo,
        (int) $journalID,
        $authors
    );
}


function publishExistingDraft(PDO $pdo,int $publicationID): void {

    $issue = getIssueByPublicationId($pdo,$publicationID);

    if (!$issue ||!(bool) $issue['is_draft']) {
        throw new Exception('Only a draft issue can be published.');
    }

    $pdo->beginTransaction();

    try {
        $current = $pdo->query(
            'SELECT "publicationID"
             FROM "PublicationIssue"
             WHERE "is_current" = TRUE
               AND "is_draft" = FALSE
             LIMIT 1'
        )->fetchColumn();

        if ($current !== false) {
            $pdo->prepare(
                'UPDATE "PublicationIssue"
                 SET
                    "is_current" = FALSE,
                    "is_draft" = FALSE
                 WHERE "publicationID" =
                       :publicationID'
            )->execute([
                ':publicationID' =>
                    (int) $current
            ]);
        }

        $pdo->prepare(
            'UPDATE "PublicationIssue"
             SET
                "is_current" = TRUE,
                "is_draft" = FALSE
             WHERE "publicationID" =
                   :publicationID'
        )->execute([
            ':publicationID' => $publicationID
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function deleteIssue(PDO $pdo,int $publicationID): void {

    $issue = getIssueByPublicationId($pdo,$publicationID);

    if (!$issue) {
        throw new Exception('Issue not found.');
    }

    $statement = $pdo->prepare(
        'SELECT
            "journalID",
            "journalPDF"
         FROM "JournalArticle"
         WHERE "publicationID" = :publicationID'
    );

    $statement->execute([
        ':publicationID' => $publicationID
    ]);

    $articles = $statement->fetchAll();

    $pdo->beginTransaction();

    try {
        foreach ($articles as $article) {
            $pdo->prepare(
                'DELETE FROM "ArticleAuthor"
                 WHERE "journalID" = :journalID'
            )->execute([
                ':journalID' =>
                    $article['journalID']
            ]);
        }

        $pdo->prepare(
            'DELETE FROM "JournalArticle"
             WHERE "publicationID" = :publicationID'
        )->execute([
            ':publicationID' => $publicationID
        ]);

        $pdo->prepare(
            'DELETE FROM "PublicationIssue"
             WHERE "publicationID" = :publicationID'
        )->execute([
            ':publicationID' => $publicationID
        ]);

        $pdo->commit();
    } 
    
    catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    foreach ($articles as $article) {
        if (!empty($article['journalPDF'])) {
            try {
                deletePdf(
                    (string) $article['journalPDF']
                );
            } catch (Throwable $e) {
                error_log(
                    'Journal PDF cleanup error: ' .
                    $e->getMessage()
                );
            }
        }
    }

    if (!empty($issue['publicationPDF'])) {
        try {
            deletePdf(
                (string) $issue['publicationPDF']
            );
        } catch (Throwable $e) {
            error_log(
                'Publication PDF cleanup error: ' .
                $e->getMessage()
            );
        }
    }
}


$database = new Database();
$pdo = $database->getConnection();

try {
    $action =
        $_GET['action'] ??
        $_POST['action'] ??
        '';

    switch ($action) {

        case 'list':
            sendResponse(true,'Issues retrieved successfully.',loadIssueList($pdo));
            break;

        case 'add':
            $year = filter_input(INPUT_POST,'year',FILTER_VALIDATE_INT);
            $volume = filter_input(INPUT_POST,'volume',FILTER_VALIDATE_INT);
            $number = filter_input(INPUT_POST,'number',FILTER_VALIDATE_INT);

            $mode = strtolower(
                trim((string) ($_POST['mode'] ?? 'draft')
                )
            );

            if ($year === false ||$year === null ||$year <= 0 ||$volume === false ||$volume === null ||$volume <= 0 ||$number === false ||$number === null ||$number <= 0) {
                sendResponse(false,'Invalid publication issue information.',[],400);
            }

            if (!isset($_FILES['publicationPDF'])) {
                sendResponse(false,'Publication issue PDF is required.',[],400);
            }

            $articleList = json_decode($_POST['articles'] ?? '[]',true);

            if (!is_array($articleList) || empty($articleList)) {
                sendResponse(
                    false,
                    'At least one article is required.',
                    [],
                    400
                );
            }

            $isDraft = $mode !== 'publish';

            $publicationID = saveIssue($pdo,(int) $year,(int) $volume,(int) $number,$articleList,$_FILES['publicationPDF'],$isDraft);

            if ($mode === 'publish') {
                publishExistingDraft($pdo,$publicationID
);
            }

            sendResponse(
                true,
                'Publication issue saved successfully.',
                [
                    'publicationID' =>
                        $publicationID
                ]
            );
            break;


    
        case 'update':

            $publicationID = filter_input(INPUT_POST,'publicationID',FILTER_VALIDATE_INT);
            $year = filter_input(INPUT_POST,'year',FILTER_VALIDATE_INT);
            $volume = filter_input(INPUT_POST,'volume',FILTER_VALIDATE_INT);

            $number = filter_input(INPUT_POST,'number',FILTER_VALIDATE_INT);

            $mode = strtolower(
                trim(
                    (string) (
                        $_POST['mode'] ?? 'draft'
                    )
                )
            );

            if ($publicationID === false ||$publicationID === null ||$publicationID <= 0) {
                sendResponse(false,'Invalid publication ID.',[],400);
            }

            if ($year === false ||$year === null ||$year <= 0 ||$volume === false ||$volume === null ||$volume <= 0 ||$number === false ||$number === null ||$number <= 0) {
                sendResponse(false,'Invalid publication issue information.',[],400);
            }

            if ($mode !== 'draft' &&$mode !== 'current') {
                $mode = 'draft';
            }

            $articleList = json_decode($_POST['articles'] ?? '[]',true);

            if (!is_array($articleList) ||empty($articleList)) {
                sendResponse(false,'At least one article is required.',[],400);
            }

            $articleList =
                normalizeArticleData(
                    $articleList
                );

            $existingIssue =
                getIssueByPublicationId(
                    $pdo,
                    (int) $publicationID
                );

            if (!$existingIssue) {
                sendResponse(
                    false,
                    'Publication issue not found.',
                    [],
                    404
                );
            }

            $existingArticles =
                getExistingArticles(
                    $pdo,
                    (int) $publicationID
                );

            /*
             * Keep the existing publication PDF
             * unless a replacement was uploaded.
             */
            $publicationPdfPath = trim(
                (string) (
                    $existingIssue['publicationPDF']
                    ?? ''
                )
            );

            if ($publicationPdfPath !== '') {
                $publicationPdfPath =
                    normalizeStoragePath(
                        $publicationPdfPath
                    );
            }

            $publicationFile =
                $_FILES['publicationPDF']
                ?? null;

            if (
                is_array($publicationFile) &&
                !empty($publicationFile['tmp_name'])
            ) {
                validatePdf(
                    $publicationFile,
                    'Publication issue PDF'
                );

                $newPublicationPath =
                    $year .
                    '/publication_' .
                    $publicationID .
                    '/' .
                    createStorageFilename(
                        $publicationFile['name'],
                        'publication_issue'
                    );

                uploadPdfsConcurrently([
                    [
                        'localFile' =>
                            $publicationFile['tmp_name'],

                        'storagePath' =>
                            $newPublicationPath,

                        'description' =>
                            'Publication issue PDF'
                    ]
                ]);

                $publicationPdfPath =
                    $newPublicationPath;
            }

            /*
             * Update each existing article through
             * JournalArticle::updateJournal().
             */
            foreach ($articleList as $index => $article) {

                $journalID =
                    isset($article['journalID'])
                        ? (int) $article['journalID']
                        : 0;

                if ($journalID > 0) {
                    if (
                        !isset(
                            $existingArticles[$journalID]
                        )
                    ) {
                        throw new Exception(
                            'Article ' .
                            ($index + 1) .
                            ' was not found in this publication issue.'
                        );
                    }

                    updateExistingArticle(
                        $pdo,
                        $article,
                        $existingArticles[$journalID],
                        (int) $year,
                        (int) $volume,
                        (int) $number,
                        (int) $publicationID,
                        $publicationPdfPath,
                        $index + 1
                    );
                } else {
                    insertNewArticle(
                        $pdo,
                        $article,
                        (int) $publicationID,
                        (int) $year,
                        $index + 1
                    );
                }
            }

            /*
             * updateJournal() updates PublicationIssue
             * for existing articles.
             *
             * If every submitted article is new,
             * update the issue directly.
             */
            $hasExistingArticle = false;

            foreach ($articleList as $article) {
                if (
                    isset($article['journalID']) &&
                    (int) $article['journalID'] > 0
                ) {
                    $hasExistingArticle = true;
                    break;
                }
            }

            if (!$hasExistingArticle) {
                $pdo->prepare(
                    'UPDATE "PublicationIssue"
                     SET
                        "year" = :year,
                        "volume" = :volume,
                        "number" = :number,
                        "publicationPDF" =
                            :publicationPDF
                     WHERE "publicationID" =
                           :publicationID'
                )->execute([
                    ':year' => $year,
                    ':volume' => $volume,
                    ':number' => $number,
                    ':publicationPDF' =>
                        $publicationPdfPath,
                    ':publicationID' =>
                        $publicationID
                ]);
            }

            /*
             * Delete the old publication PDF only
             * after the database update succeeds.
             */
            $oldPublicationPdf =
                trim(
                    (string) (
                        $existingIssue['publicationPDF']
                        ?? ''
                    )
                );

            if (
                is_array($publicationFile) &&
                !empty($publicationFile['tmp_name']) &&
                $oldPublicationPdf !== '' &&
                normalizeStoragePath(
                    $oldPublicationPdf
                ) !== $publicationPdfPath
            ) {
                try {
                    deletePdf(
                        $oldPublicationPdf
                    );
                } catch (Throwable $e) {
                    error_log(
                        'Old publication PDF cleanup error: ' .
                        $e->getMessage()
                    );
                }
            }

            sendResponse(
                true,
                $mode === 'current'
                    ? 'Journal updated successfully.'
                    : 'Draft updated successfully.',
                [
                    'publicationID' =>
                        $publicationID
                ]
            );
            break;


        /*
        |------------------------------------------------------------------
        | PUBLISH
        |------------------------------------------------------------------
        */

        case 'publish':
            $publicationID = filter_input(
                INPUT_POST,
                'publicationID',
                FILTER_VALIDATE_INT
            );

            if (
                $publicationID === false ||
                $publicationID === null ||
                $publicationID <= 0
            ) {
                sendResponse(
                    false,
                    'Invalid publication ID.',
                    [],
                    400
                );
            }

            publishExistingDraft(
                $pdo,
                (int) $publicationID
            );

            sendResponse(
                true,
                'Draft published successfully.',
                [
                    'publicationID' =>
                        $publicationID
                ]
            );
            break;


        /*
        |------------------------------------------------------------------
        | DELETE ISSUE
        |------------------------------------------------------------------
        */

        case 'delete':
            $publicationID = filter_input(
                INPUT_POST,
                'publicationID',
                FILTER_VALIDATE_INT
            );

            if (
                $publicationID === false ||
                $publicationID === null ||
                $publicationID <= 0
            ) {
                sendResponse(
                    false,
                    'Invalid publication ID.',
                    [],
                    400
                );
            }

            deleteIssue(
                $pdo,
                (int) $publicationID
            );

            sendResponse(
                true,
                'Issue deleted successfully.',
                []
            );
            break;


        case 'deleteArticle':
            $journalID = filter_input(INPUT_POST,'journalID',FILTER_VALIDATE_INT);

            if ($journalID === false ||$journalID === null ||$journalID <= 0) {
                sendResponse(false,'Invalid journal ID.',[],400);
            }

            $statement = $pdo->prepare(
                'SELECT "journalPDF"
                 FROM "JournalArticle"
                 WHERE "journalID" = :journalID
                 LIMIT 1'
            );

            $statement->execute([
                ':journalID' => $journalID
            ]);

            $pdfPath =
                $statement->fetchColumn();

            $journalArticle =
                new JournalArticle();

            $journalArticle->removeJournal(
                $pdo,
                (int) $journalID
            );

            if (!empty($pdfPath)) {
                try {
                    deletePdf(
                        (string) $pdfPath
                    );
                } catch (Throwable $e) {
                    error_log(
                        'Article PDF cleanup error: ' .
                        $e->getMessage()
                    );
                }
            }

            sendResponse(
                true,
                'Article removed successfully.',
                []
            );
            break;


        case 'view':
            $journalID = filter_input(INPUT_GET,'journalID',FILTER_VALIDATE_INT);

            $publicationID = filter_input(INPUT_GET,'publicationID',FILTER_VALIDATE_INT);

            if ($journalID !== false &&$journalID !== null &&$journalID > 0) {
                sendResponse(
                    true,
                    'Journal article retrieved successfully.',
                    getArticlePayload(
                        $pdo,
                        (int) $journalID
                    )
                );
            }

            if ($publicationID !== false &&$publicationID !== null &&$publicationID > 0) {
                sendResponse(true,'Issue retrieved successfully.',
                getIssuePayload($pdo,(int) $publicationID));
            }

            sendResponse(
                false,
                'Invalid journal ID or publication ID.',
                [],
                400
            );
            break;

        case 'pdfUrl':
            $type = strtolower(
                trim(
                    (string) (
                        $_GET['type'] ??
                        $_POST['type'] ??
                        ''
                    )
                )
            );

            $journalID = filter_input(
                INPUT_GET,
                'journalID',
                FILTER_VALIDATE_INT
            );

            if (
                $journalID === false ||
                $journalID === null
            ) {
                $journalID = filter_input(
                    INPUT_POST,
                    'journalID',
                    FILTER_VALIDATE_INT
                );
            }

            $publicationID = filter_input(
                INPUT_GET,
                'publicationID',
                FILTER_VALIDATE_INT
            );

            if (
                $publicationID === false ||
                $publicationID === null
            ) {
                $publicationID = filter_input(
                    INPUT_POST,
                    'publicationID',
                    FILTER_VALIDATE_INT
                );
            }

            $storagePath = '';

            /*
             * Article PDF
             */
            if ($type === 'article') {
                if (
                    $journalID === false ||
                    $journalID === null ||
                    $journalID <= 0
                ) {
                    sendResponse(
                        false,
                        'Invalid journal ID.',
                        [],
                        400
                    );
                }

                $statement = $pdo->prepare(
                    'SELECT "journalPDF"
                     FROM "JournalArticle"
                     WHERE "journalID" = :journalID
                     LIMIT 1'
                );

                $statement->execute([
                    ':journalID' => (int) $journalID
                ]);

                $storagePath =
                    trim(
                        (string) (
                            $statement->fetchColumn()
                            ?: ''
                        )
                    );

                if ($storagePath === '') {
                    sendResponse(
                        false,
                        'Article PDF not found.',
                        [],
                        404
                    );
                }
            }

            /*
             * Publication issue PDF
             */
            elseif ($type === 'publication') {
                if ($publicationID === false ||$publicationID === null ||$publicationID <= 0) {
                    sendResponse(false,'Invalid publication ID.',[],400);
                }

                $statement = $pdo->prepare(
                    'SELECT "publicationPDF"
                     FROM "PublicationIssue"
                     WHERE "publicationID" = :publicationID
                     LIMIT 1'
                );

                $statement->execute([
                    ':publicationID' =>
                        (int) $publicationID
                ]);

                $storagePath =
                    trim(
                        (string) (
                            $statement->fetchColumn()
                            ?: ''
                        )
                    );

                if ($storagePath === '') {
                    sendResponse(
                        false,
                        'Publication PDF not found.',
                        [],
                        404
                    );
                }
            }

            else {
                sendResponse(false,'Invalid PDF type.',[],400);
            }

            $signedUrl = createSignedPdfUrl($storagePath);

            sendResponse(true,'Signed PDF URL generated successfully.',
                [
                    'pdfUrl' => $signedUrl
                ]
            );

            break;


        /*INVALID ACTION*/
        default:
            sendResponse(false,'Invalid action.',[],400);
            break;
    }

} 

catch (Throwable $e) {
    error_log('Manage Journal API Error: ' .$e->getMessage());
    sendResponse( false,$e->getMessage(),[],500
    );
}