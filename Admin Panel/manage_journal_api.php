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


/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

function sendResponse(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): never {
    http_response_code($status);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| STORAGE PATH HELPERS
|--------------------------------------------------------------------------
*/

function normalizeStoragePath(string $storagePath): string
{
    global $bucket;

    $storagePath = trim($storagePath);

    if ($storagePath === '') {
        throw new Exception('The storage path is empty.');
    }

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {

        $parsedPath = parse_url(
            $storagePath,
            PHP_URL_PATH
        );

        if (!is_string($parsedPath)) {
            throw new Exception(
                'Unable to extract the storage path from the PDF URL.'
            );
        }

        $storagePath = $parsedPath;

        $objectMarker = '/storage/v1/object/';

        $markerPosition = strpos(
            $storagePath,
            $objectMarker
        );

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

    $storagePath = ltrim(
        $storagePath,
        '/'
    );

    $bucketName = trim(
        $bucket,
        '/'
    );

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

    $storagePath = preg_replace(
        '#/+#',
        '/',
        $storagePath
    );

    $storagePath = rtrim(
        $storagePath,
        '/'
    );

    if ($storagePath === '') {
        throw new Exception(
            'The storage path is empty.'
        );
    }

    return $storagePath;
}


function createSignedPdfUrl(
    string $storagePath,
    int $expiresIn = 3600
): string {

    global $supabaseUrl, $supabaseKey, $bucket;

    $storagePath =
        normalizeStoragePath(
            $storagePath
        );

    $bucketName =
        trim(
            $bucket,
            '/'
        );

    if ($bucketName === '') {
        throw new Exception(
            'Supabase storage bucket is not configured.'
        );
    }

    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode(
                '/',
                $storagePath
            )
        )
    );

    $url =
        rtrim(
            $supabaseUrl,
            '/'
        ) .
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

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        throw new Exception(
            'Unable to create signed PDF URL: ' .
                ($curlError ?: 'Unknown cURL error.')
        );
    }

    $result = json_decode(
        $response,
        true
    );

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

        $errorMessage = '';

        if (is_array($result)) {
            $errorMessage =
                $result['message'] ??
                $result['error'] ??
                '';
        }

        if ($errorMessage === '') {
            $errorMessage = $response;
        }

        throw new Exception(
            'Supabase Storage error (HTTP ' .
                $httpCode .
                '): ' .
                $errorMessage
        );
    }

    if (!is_array($result)) {
        throw new Exception(
            'Supabase returned an invalid signed URL response.'
        );
    }

    $signedUrl =
        trim(
            (string) (
                $result['signedURL'] ?? ''
            )
        );

    if ($signedUrl === '') {
        throw new Exception(
            'Supabase did not return a signed PDF URL.'
        );
    }

    if (
        str_starts_with(
            $signedUrl,
            '/'
        )
    ) {

        $signedUrl =
            rtrim(
                $supabaseUrl,
                '/'
            ) .
            '/storage/v1' .
            $signedUrl;
    }

    return $signedUrl;
}


function createPublicPdfUrl(
    string $storagePath
): string {

    global $supabaseUrl, $bucket;

    if (
        filter_var(
            $storagePath,
            FILTER_VALIDATE_URL
        )
    ) {
        return $storagePath;
    }

    $bucketName =
        trim(
            $bucket,
            '/'
        );

    if ($bucketName === '') {
        throw new Exception(
            'Supabase storage bucket is not configured.'
        );
    }

    $storagePath =
        normalizeStoragePath(
            $storagePath
        );

    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode(
                '/',
                $storagePath
            )
        )
    );

    return
        rtrim(
            $supabaseUrl,
            '/'
        ) .
        '/storage/v1/object/public/' .
        rawurlencode($bucketName) .
        '/' .
        $encodedPath;
}


/*
|--------------------------------------------------------------------------
| PDF VALIDATION
|--------------------------------------------------------------------------
*/

function validatePdf(
    array $file,
    string $description
): void {

    if (
        !isset($file['error']) ||
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        throw new Exception(
            $description . ' upload failed.'
        );
    }

    if (
        !isset($file['tmp_name']) ||
        !is_uploaded_file(
            $file['tmp_name']
        )
    ) {
        throw new Exception(
            $description . ' is invalid.'
        );
    }

    $mimeType =
        mime_content_type(
            $file['tmp_name']
        );

    if ($mimeType !== 'application/pdf') {
        throw new Exception(
            $description . ' must be a PDF.'
        );
    }

    $maxFileSize =
        40 * 1024 * 1024;

    if (
        $file['size'] >
        $maxFileSize
    ) {
        throw new Exception(
            $description .
                ' exceeds the 40 MB limit.'
        );
    }
}


function createStorageFilename(
    string $originalName,
    string $prefix
): string {

    $originalName =
        basename(
            $originalName
        );

    $safeFileName =
        preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            $originalName
        );

    return
        $prefix .
        '_' .
        uniqid(
            '',
            true
        ) .
        '_' .
        $safeFileName;
}


/*
|--------------------------------------------------------------------------
| SUPABASE STORAGE UPLOAD
|--------------------------------------------------------------------------
*/

function uploadPdfsConcurrently(
    array $uploads
): void {

    global $supabaseUrl, $supabaseKey, $bucket;

    if (empty($uploads)) {
        return;
    }

    $multiHandle =
        curl_multi_init();

    $handles = [];

    try {

        foreach (
            $uploads as $index => $upload
        ) {

            $localFile =
                $upload['localFile'];

            $storagePath =
                normalizeStoragePath(
                    $upload['storagePath']
                );

            $fileContents =
                file_get_contents(
                    $localFile
                );

            if ($fileContents === false) {
                throw new Exception(
                    'Unable to read PDF file.'
                );
            }

            $encodedPath = implode(
                '/',
                array_map(
                    'rawurlencode',
                    explode(
                        '/',
                        $storagePath
                    )
                )
            );

            $url =
                rtrim(
                    $supabaseUrl,
                    '/'
                ) .
                '/storage/v1/object/' .
                rawurlencode($bucket) .
                '/' .
                $encodedPath;

            $ch =
                curl_init($url);

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

            $handles[$index] =
                $ch;
        }

        $running = null;

        do {

            $status =
                curl_multi_exec(
                    $multiHandle,
                    $running
                );

            if ($running) {

                curl_multi_select(
                    $multiHandle,
                    1.0
                );
            }
        } while (
            $running &&
            $status === CURLM_OK
        );


        foreach (
            $handles as $index => $ch
        ) {

            $response =
                curl_multi_getcontent(
                    $ch
                );

            $httpCode =
                curl_getinfo(
                    $ch,
                    CURLINFO_HTTP_CODE
                );

            $curlError =
                curl_error($ch);

            if (
                $response === false ||
                $httpCode < 200 ||
                $httpCode >= 300
            ) {

                throw new Exception(
                    'PDF upload failed for ' .
                        $uploads[$index]['description'] .
                        ': ' .
                        ($curlError ?: $response)
                );
            }

            curl_multi_remove_handle(
                $multiHandle,
                $ch
            );

            curl_close($ch);
        }
    } finally {

        curl_multi_close(
            $multiHandle
        );
    }
}


function deletePdf(
    string $storagePath
): void {

    global $supabaseUrl, $supabaseKey, $bucket;

    if (
        trim($storagePath) === ''
    ) {
        return;
    }

    $storagePath =
        normalizeStoragePath(
            $storagePath
        );

    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode(
                '/',
                $storagePath
            )
        )
    );

    $url =
        rtrim(
            $supabaseUrl,
            '/'
        ) .
        '/storage/v1/object/' .
        rawurlencode($bucket) .
        '/' .
        $encodedPath;

    $ch =
        curl_init($url);

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


/*
|--------------------------------------------------------------------------
| ARTICLE DATA
|--------------------------------------------------------------------------
*/

function normalizeArticleData(
    array $articles
): array {

    $normalized = [];

    foreach (
        $articles as $article
    ) {

        $normalized[] = [

            'journalID' =>
            isset($article['journalID']) &&
                $article['journalID'] !== null &&
                $article['journalID'] !== ''
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


function getIssueByPublicationId(
    PDO $pdo,
    int $publicationID
): ?array {

    $statement =
        $pdo->prepare(
            'SELECT *
             FROM "PublicationIssue"
             WHERE "publicationID" = :publicationID
             LIMIT 1'
        );

    $statement->execute([
        ':publicationID' =>
        $publicationID
    ]);

    $issue =
        $statement->fetch();

    return $issue ?: null;
}


function ensureOneDraft(
    PDO $pdo
): void {

    $draft =
        $pdo->query(
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


/*
|--------------------------------------------------------------------------
| AUTHORS
|--------------------------------------------------------------------------
*/

function saveArticleAuthors(
    PDO $pdo,
    int $journalID,
    array $authors,
    bool $isDraft = false
): void {

    $pdo->prepare(
        'DELETE FROM "ArticleAuthor"
         WHERE "journalID" = :journalID'
    )->execute([
        ':journalID' =>
        $journalID
    ]);

    $validAuthors = [];

    foreach (
        $authors as $author
    ) {

        $firstName =
            trim(
                (string) (
                    $author['firstName'] ?? ''
                )
            );

        $lastName =
            trim(
                (string) (
                    $author['lastName'] ?? ''
                )
            );

        /*
         * Completely blank author rows
         * are allowed in drafts.
         */
        if (
            $firstName === '' &&
            $lastName === ''
        ) {
            continue;
        }

        /*
         * Drafts can temporarily contain
         * incomplete author information.
         */
        if (
            $isDraft &&
            (
                $firstName === '' ||
                $lastName === ''
            )
        ) {
            continue;
        }

        /*
         * Published articles require
         * complete author names.
         */
        if (
            $firstName === '' ||
            $lastName === ''
        ) {
            throw new Exception(
                'Author first name and last name are required.'
            );
        }

        $validAuthors[] = [
            'firstName' =>
            $firstName,

            'lastName' =>
            $lastName
        ];
    }

    /*
     * Drafts may have zero authors.
     */
    if (empty($validAuthors)) {

        if ($isDraft) {
            return;
        }

        throw new Exception(
            'An article must have at least one author.'
        );
    }

    foreach (
        $validAuthors as $author
    ) {

        $findAuthor =
            $pdo->prepare(
                'SELECT "authorID"
                 FROM "Author"
                 WHERE "firstName" = :firstName
                   AND "lastName" = :lastName
                 LIMIT 1'
            );

        $findAuthor->execute([
            ':firstName' =>
            $author['firstName'],

            ':lastName' =>
            $author['lastName']
        ]);

        $authorID =
            $findAuthor->fetchColumn();

        if ($authorID === false) {

            $createAuthor =
                $pdo->prepare(
                    'INSERT INTO "Author"
                        ("firstName", "lastName")
                     VALUES
                        (:firstName, :lastName)
                     RETURNING "authorID"'
                );

            $createAuthor->execute([
                ':firstName' =>
                $author['firstName'],

                ':lastName' =>
                $author['lastName']
            ]);

            $authorID =
                $createAuthor->fetchColumn();

            if ($authorID === false) {

                throw new Exception(
                    'Unable to create author.'
                );
            }
        }

        $insertLink =
            $pdo->prepare(
                'INSERT INTO "ArticleAuthor"
                    ("journalID", "authorID")
                 VALUES
                    (:journalID, :authorID)'
            );

        $insertLink->execute([
            ':journalID' =>
            $journalID,

            ':authorID' =>
            (int) $authorID
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| ISSUE PAYLOAD
|--------------------------------------------------------------------------
*/

function getIssuePayload(
    PDO $pdo,
    int $publicationID
): array {

    $issue =
        getIssueByPublicationId(
            $pdo,
            $publicationID
        );

    if (!$issue) {
        throw new Exception(
            'Issue not found.'
        );
    }

    $statement =
        $pdo->prepare(
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
        ':publicationID' =>
        $publicationID
    ]);

    $articles = [];

    foreach (
        $statement->fetchAll() as $row
    ) {

        $journalID =
            (int) $row['journalID'];

        if (
            !isset(
                $articles[$journalID]
            )
        ) {

            $pdfPath =
                trim(
                    (string) (
                        $row['journalPDF'] ?? ''
                    )
                );

            $articles[$journalID] = [

                'journalID' =>
                $journalID,

                'title' =>
                (string) (
                    $row['title'] ?? ''
                ),

                'journalPDF' =>
                $pdfPath !== ''
                    ? $pdfPath
                    : null,

                'authors' => []
            ];
        }

        if (
            !empty($row['firstName']) ||
            !empty($row['lastName'])
        ) {

            $articles[$journalID]['authors'][] = [

                'firstName' =>
                $row['firstName'] ?? '',

                'lastName' =>
                $row['lastName'] ?? ''
            ];
        }
    }

    $publicationPdfPath =
        trim(
            (string) (
                $issue['publicationPDF'] ?? ''
            )
        );

    return [

        'publicationID' =>
        (int) $issue['publicationID'],

        'year' =>
        (int) $issue['year'],

        'volume' =>
        (int) $issue['volume'],

        'number' =>
        (int) $issue['number'],

        'publicationPDF' =>
        $publicationPdfPath !== ''
            ? $publicationPdfPath
            : null,

        'is_current' =>
        (bool) $issue['is_current'],

        'is_draft' =>
        (bool) $issue['is_draft'],

        'articles' =>
        array_values($articles)
    ];
}


/*
|--------------------------------------------------------------------------
| ARTICLE PAYLOAD
|--------------------------------------------------------------------------
*/

function getArticlePayload(
    PDO $pdo,
    int $journalID
): array {

    $statement =
        $pdo->prepare(
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
        ':journalID' =>
        $journalID
    ]);

    $article =
        $statement->fetch();

    if (!$article) {

        throw new Exception(
            'Journal article not found.'
        );
    }

    $authorStatement =
        $pdo->prepare(
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
        ':journalID' =>
        $journalID
    ]);

    $authors = [];

    foreach (
        $authorStatement->fetchAll()
        as $author
    ) {

        $authors[] = [

            'firstName' =>
            $author['firstName'] ?? '',

            'lastName' =>
            $author['lastName'] ?? ''
        ];
    }

    $pdfUrl = '';

    if (
        !empty($article['journalPDF'])
    ) {

        $pdfUrl =
            createPublicPdfUrl(
                (string) $article['journalPDF']
            );
    }


    /*
     * Build APA author information.
     */
    $citationAuthors = [];

    foreach (
        $authors as $author
    ) {

        $firstName =
            trim(
                (string) (
                    $author['firstName'] ?? ''
                )
            );

        $lastName =
            trim(
                (string) (
                    $author['lastName'] ?? ''
                )
            );

        if (
            $firstName === '' &&
            $lastName === ''
        ) {
            continue;
        }

        $initials = '';

        if ($firstName !== '') {

            $parts =
                preg_split(
                    '/\s+/',
                    $firstName
                );

            foreach (
                $parts as $part
            ) {

                $part =
                    trim($part);

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

            $initials =
                trim($initials);
        }

        if (
            $lastName !== '' &&
            $initials !== ''
        ) {

            $citationAuthors[] =
                $lastName .
                ', ' .
                $initials;
        } elseif (
            $lastName !== ''
        ) {

            $citationAuthors[] =
                $lastName;
        } else {

            $citationAuthors[] =
                $initials;
        }
    }


    $authorText = '';

    $authorCount =
        count($citationAuthors);

    if ($authorCount === 1) {

        $authorText =
            $citationAuthors[0] .
            ' ';
    } elseif ($authorCount === 2) {

        $authorText =
            $citationAuthors[0] .
            ', & ' .
            $citationAuthors[1] .
            ' ';
    } elseif ($authorCount > 2) {

        $lastAuthor =
            array_pop(
                $citationAuthors
            );

        $authorText =
            implode(
                ', ',
                $citationAuthors
            ) .
            ', & ' .
            $lastAuthor .
            ' ';
    }


    $year =
        !empty($article['year'])
        ? (string) $article['year']
        : 'n.d.';

    $title =
        trim(
            (string) (
                $article['title'] ?? ''
            )
        );

    if ($title === '') {
        $title =
            'Untitled Article';
    }

    $volume =
        !empty($article['volume'])
        ? (string) $article['volume']
        : '';

    $number =
        !empty($article['number'])
        ? (string) $article['number']
        : '';

    $journalInformation =
        'Convergence';

    if ($volume !== '') {

        $journalInformation .=
            ', ' .
            $volume;

        if ($number !== '') {

            $journalInformation .=
                '(' .
                $number .
                ')';
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
            $article['publicationPDF'] ??
                null
        ],

        'authors' =>
        $authors,

        'pdfUrl' =>
        $pdfUrl,

        'citation' =>
        $citation
    ];
}


/*
|--------------------------------------------------------------------------
| ISSUE LIST
|--------------------------------------------------------------------------
*/

function loadIssueList(
    PDO $pdo
): array {

    $statement =
        $pdo->query(
            'SELECT *
             FROM "PublicationIssue"
             ORDER BY
                "year" DESC,
                "volume" DESC,
                "number" DESC,
                "publicationID" DESC'
        );

    $issues = [];

    foreach (
        $statement->fetchAll() as $row
    ) {

        $issues[(int) $row['publicationID']] = [

            'publicationID' =>
            (int) $row['publicationID'],

            'year' =>
            (int) $row['year'],

            'volume' =>
            (int) $row['volume'],

            'number' =>
            (int) $row['number'],

            'publicationPDF' =>
            $row['publicationPDF'] ??
                null,

            'is_current' =>
            (bool) $row['is_current'],

            'is_draft' =>
            (bool) $row['is_draft'],

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

    $publicationIDs =
        array_keys($issues);

    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($publicationIDs),
                '?'
            )
        );

    $statement =
        $pdo->prepare(
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

    $statement->execute(
        $publicationIDs
    );

    foreach (
        $statement->fetchAll() as $row
    ) {

        $publicationID =
            (int) $row['publicationID'];

        $journalID =
            (int) $row['journalID'];

        if (
            !isset(
                $issues[$publicationID]['articles'][$journalID]
            )
        ) {

            $issues[$publicationID]['articles'][$journalID] = [

                'journalID' =>
                $journalID,

                'title' =>
                $row['title'] ?? '',

                'journalPDF' =>
                $row['journalPDF'] ??
                    null,

                'authors' => []
            ];
        }

        if (
            !empty($row['firstName']) ||
            !empty($row['lastName'])
        ) {

            $issues[$publicationID]['articles'][$journalID]['authors'][] = [

                'firstName' =>
                $row['firstName'] ?? '',

                'lastName' =>
                $row['lastName'] ?? ''
            ];
        }
    }


    /*
     * Convert article associative arrays
     * into normal indexed arrays.
     *
     * Empty draft issues are still allowed
     * to appear here if they were initially
     * saved with only Year/Volume/Number.
     *
     * Once all articles are removed through
     * update/deleteArticle, the draft issue
     * itself is deleted.
     */
    foreach (
        $issues as $publicationID => &$issue
    ) {

        $issue['articles'] =
            array_values(
                $issue['articles']
            );
    }

    unset($issue);


    $current = [];
    $draft = [];
    $archive = [];

    foreach (
        $issues as $issue
    ) {

        if (
            $issue['is_current'] &&
            !$issue['is_draft']
        ) {

            $current[] =
                $issue;
        } elseif (
            !$issue['is_current'] &&
            $issue['is_draft']
        ) {

            $draft[] =
                $issue;
        } else {

            $archive[] =
                $issue;
        }
    }

    return [

        'current' =>
        $current,

        'draft' =>
        $draft,

        'archive' =>
        $archive
    ];
}


/*
|--------------------------------------------------------------------------
| CREATE ISSUE RECORD
|--------------------------------------------------------------------------
*/

function createIssueRecord(
    PDO $pdo,
    int $year,
    int $volume,
    int $number,
    string $publicationPDF,
    bool $isCurrent,
    bool $isDraft
): int {

    $statement =
        $pdo->prepare(
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

        ':year' =>
        $year,

        ':volume' =>
        $volume,

        ':number' =>
        $number,

        ':publicationPDF' =>
        $publicationPDF,

        ':is_current' =>
        $isCurrent
            ? 'true'
            : 'false',

        ':is_draft' =>
        $isDraft
            ? 'true'
            : 'false'
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


/*
|--------------------------------------------------------------------------
| SAVE ISSUE
|--------------------------------------------------------------------------
*/

function saveIssue(
    PDO $pdo,
    int $year,
    int $volume,
    int $number,
    array $articleList,
    array $publicationFile,
    bool $isDraft,
    ?int $publicationID = null
): int {

    $articleList =
        normalizeArticleData(
            $articleList
        );


    /*
     * Published/current issue:
     * publication PDF and articles are required.
     */
    if (!$isDraft) {

        if (empty($articleList)) {

            throw new Exception(
                'At least one article is required.'
            );
        }

        if (
            !isset(
                $publicationFile['tmp_name']
            ) ||
            !is_uploaded_file(
                $publicationFile['tmp_name']
            )
        ) {

            throw new Exception(
                'Publication issue PDF is required.'
            );
        }

        validatePdf(
            $publicationFile,
            'Publication issue PDF'
        );
    }


    /*
     * A new draft is only allowed if
     * there is no existing draft.
     */
    if (
        $publicationID === null &&
        $isDraft
    ) {

        ensureOneDraft(
            $pdo
        );
    }


    $pdo->beginTransaction();

    try {

        $existingIssue = null;

        /*
         * CREATE NEW ISSUE
         */
        if ($publicationID === null) {

            $publicationID =
                createIssueRecord(
                    $pdo,
                    $year,
                    $volume,
                    $number,
                    '',
                    false,
                    $isDraft
                );
        } else {

            $existingIssue =
                getIssueByPublicationId(
                    $pdo,
                    $publicationID
                );

            if (!$existingIssue) {

                throw new Exception(
                    'Issue not found.'
                );
            }
        }


        /*
         * Existing publication PDF.
         */
        $publicationPath = '';

        if (
            $existingIssue !== null
        ) {

            $publicationPath =
                trim(
                    (string) (
                        $existingIssue['publicationPDF']
                        ?? ''
                    )
                );

            if (
                $publicationPath !== ''
            ) {

                $publicationPath =
                    normalizeStoragePath(
                        $publicationPath
                    );
            }
        }


        /*
         * Publication PDF is optional
         * for drafts.
         */
        $oldPublicationPath =
            $publicationPath;


        if (
            isset(
                $publicationFile['tmp_name']
            ) &&
            is_uploaded_file(
                $publicationFile['tmp_name']
            )
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

            $publicationPath =
                $newPublicationPath;
        }


        /*
         * Update issue information.
         */
        $pdo->prepare(
            'UPDATE "PublicationIssue"
             SET
                "year" = :year,
                "volume" = :volume,
                "number" = :number,
                "publicationPDF" = :publicationPDF
             WHERE "publicationID" = :publicationID'
        )->execute([

            ':year' =>
            $year,

            ':volume' =>
            $volume,

            ':number' =>
            $number,

            ':publicationPDF' =>
            $publicationPath,

            ':publicationID' =>
            $publicationID
        ]);


        /*
         * SAVE ARTICLES
         */
        foreach (
            $articleList as $index => $article
        ) {

            $title =
                trim(
                    (string) (
                        $article['title'] ?? ''
                    )
                );

            $authors =
                $article['authors'] ??
                [];

            $pdfInput =
                $_FILES['pdf_' . $index] ?? null;


            /*
             * ------------------------------------------------------
             * DRAFT
             * ------------------------------------------------------
             */
            if ($isDraft) {

                $hasPdf =
                    is_array($pdfInput) &&
                    !empty($pdfInput['tmp_name']);


                /*
                 * Completely empty article slots
                 * are not stored.
                 */
                if (
                    $title === '' &&
                    empty($authors) &&
                    !$hasPdf
                ) {
                    continue;
                }


                $pdfPath = '';


                if ($hasPdf) {

                    validatePdf(
                        $pdfInput,
                        'PDF for Article ' .
                            ($index + 1)
                    );

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
                }


                $insert =
                    $pdo->prepare(
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

                    ':title' =>
                    $title,

                    ':journalPDF' =>
                    $pdfPath,

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
                    is_array($authors)
                        ? $authors
                        : [],
                    true
                );

                continue;
            }


            /*
             * ------------------------------------------------------
             * PUBLISH
             * ------------------------------------------------------
             */

            if ($title === '') {

                throw new Exception(
                    'Title for Article ' .
                        ($index + 1) .
                        ' is required.'
                );
            }

            if (
                !is_array($authors) ||
                empty($authors)
            ) {

                throw new Exception(
                    'Article ' .
                        ($index + 1) .
                        ' must have at least one author.'
                );
            }

            if (
                !is_array($pdfInput) ||
                empty($pdfInput['tmp_name'])
            ) {

                throw new Exception(
                    'PDF for Article ' .
                        ($index + 1) .
                        ' is required.'
                );
            }

            validatePdf(
                $pdfInput,
                'PDF for Article ' .
                    ($index + 1)
            );

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

            $insert =
                $pdo->prepare(
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

                ':title' =>
                $title,

                ':journalPDF' =>
                $pdfPath,

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
                $authors,
                false
            );
        }


        $pdo->commit();


        /*
         * Delete old publication PDF only
         * after the database transaction succeeds.
         */
        if (
            $oldPublicationPath !== '' &&
            $publicationPath !== '' &&
            normalizeStoragePath(
                $oldPublicationPath
            ) !== normalizeStoragePath(
                $publicationPath
            )
        ) {

            try {

                deletePdf(
                    $oldPublicationPath
                );
            } catch (Throwable $e) {

                error_log(
                    'Old publication PDF cleanup error: ' .
                        $e->getMessage()
                );
            }
        }


        return (int) $publicationID;
    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/*
|--------------------------------------------------------------------------
| UPDATE HELPERS
|--------------------------------------------------------------------------
*/

function getExistingArticles(
    PDO $pdo,
    int $publicationID
): array {

    $statement =
        $pdo->prepare(
            'SELECT
                "journalID",
                "title",
                "journalPDF"
             FROM "JournalArticle"
             WHERE "publicationID" = :publicationID
             ORDER BY "journalID" ASC'
        );

    $statement->execute([
        ':publicationID' =>
        $publicationID
    ]);

    $articles = [];

    foreach (
        $statement->fetchAll() as $article
    ) {

        $articles[(int) $article['journalID']] = $article;
    }

    return $articles;
}


function uploadReplacementArticlePdf(
    int $year,
    int $publicationID,
    int $articleNumber,
    ?array $pdfInput
): ?string {

    if (
        !is_array($pdfInput) ||
        empty($pdfInput['tmp_name'])
    ) {
        return null;
    }

    validatePdf(
        $pdfInput,
        'PDF for Article ' .
            $articleNumber
    );

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


/*
|--------------------------------------------------------------------------
| UPDATE EXISTING ARTICLE
|--------------------------------------------------------------------------
*/

function updateExistingArticle(
    PDO $pdo,
    array $article,
    array $existingArticle,
    int $year,
    int $publicationID,
    int $articleNumber,
    bool $isDraft
): void {



    $journalID = (int) $existingArticle['journalID'];

    $title = trim(
        (string) ($article['title'] ?? '')
    );

    $authors = $article['authors'] ?? [];

    /*
     * Published/current article validation.
     */
    if (!$isDraft) {

        if ($title === '') {
            throw new Exception(
                'Title for Article ' .
                    $articleNumber .
                    ' is required.'
            );
        }

        if (
            !is_array($authors) ||
            empty($authors)
        ) {
            throw new Exception(
                'Article ' .
                    $articleNumber .
                    ' must have at least one author.'
            );
        }
    }

    /*
     * Keep existing PDF unless a replacement
     * PDF was uploaded.
     */
    $oldPdf = trim(
        (string) (
            $existingArticle['journalPDF'] ?? ''
        )
    );

    $pdfPath = '';

    if ($oldPdf !== '') {
        $pdfPath = normalizeStoragePath($oldPdf);
    }

    /*
     * Check for replacement PDF.
     */

    $newPdf = uploadReplacementArticlePdf(
        $year,
        $publicationID,
        $articleNumber,
        $_FILES['pdf_' . ($articleNumber - 1)] ?? null
    );


    if ($newPdf !== null) {
        $pdfPath = $newPdf;
    }

    /*
     * Published/current article requires a PDF.
     */
    if (
        !$isDraft &&
        $pdfPath === ''
    ) {
        throw new Exception(
            'PDF for Article ' .
                $articleNumber .
                ' is required.'
        );
    }

    /*
     * Update the JournalArticle record ONLY.
     *
     * PublicationIssue is updated separately by
     * manage_journal_api.php.
     */
    $journalArticle = new JournalArticle();

    $journalArticle->updateJournal(
        $pdo,
        $journalID,
        $title,
        $pdfPath
    );

    /*
     * Save authors.
     */
    saveArticleAuthors(
        $pdo,
        $journalID,
        is_array($authors)
            ? $authors
            : [],
        $isDraft
    );

    /*
     * Delete the old PDF only after the database
     * update succeeds.
     */
    if (
        $newPdf !== null &&
        $oldPdf !== '' &&
        normalizeStoragePath($oldPdf) !== $newPdf
    ) {
        try {

            deletePdf($oldPdf);
        } catch (Throwable $e) {

            error_log(
                'Old article PDF cleanup error: ' .
                    $e->getMessage()
            );
        }
    }
}







/*
|--------------------------------------------------------------------------
| INSERT NEW ARTICLE
|--------------------------------------------------------------------------
*/

function insertNewArticle(
    PDO $pdo,
    array $article,
    int $publicationID,
    int $year,
    int $articleNumber,
    bool $isDraft
): void {

    $title =
        trim(
            (string) (
                $article['title'] ?? ''
            )
        );

    $authors =
        $article['authors'] ??
        [];


    /*
     * Draft:
     * all article information is optional.
     */
    if ($isDraft) {

        $pdfInput =
            $_FILES['pdf_' .
                ($articleNumber - 1)] ?? null;

        $hasPdf =
            is_array($pdfInput) &&
            !empty($pdfInput['tmp_name']);


        /*
         * If absolutely nothing exists,
         * do not create a database row.
         */
        if (
            $title === '' &&
            empty($authors) &&
            !$hasPdf
        ) {
            return;
        }


        $pdfPath = '';

        if ($hasPdf) {

            $pdfPath =
                uploadReplacementArticlePdf(
                    $year,
                    $publicationID,
                    $articleNumber,
                    $pdfInput
                ) ?? '';
        }


        $insert =
            $pdo->prepare(
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

            ':title' =>
            $title,

            ':journalPDF' =>
            $pdfPath,

            ':publicationID' =>
            $publicationID
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
            is_array($authors)
                ? $authors
                : [],
            true
        );

        return;
    }


    /*
     * Published/current:
     * everything is required.
     */
    if ($title === '') {

        throw new Exception(
            'Title for Article ' .
                $articleNumber .
                ' is required.'
        );
    }

    if (
        !is_array($authors) ||
        empty($authors)
    ) {

        throw new Exception(
            'Article ' .
                $articleNumber .
                ' must have at least one author.'
        );
    }


    $pdfPath =
        uploadReplacementArticlePdf(
            $year,
            $publicationID,
            $articleNumber,
            $_FILES['pdf_' .
                ($articleNumber - 1)] ?? null
        );


    if ($pdfPath === null) {

        throw new Exception(
            'PDF for Article ' .
                $articleNumber .
                ' is required.'
        );
    }


    $insert =
        $pdo->prepare(
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

        ':title' =>
        $title,

        ':journalPDF' =>
        $pdfPath,

        ':publicationID' =>
        $publicationID
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
        $authors,
        false
    );
}


/*
|--------------------------------------------------------------------------
| VALIDATE DRAFT BEFORE PUBLISHING
|--------------------------------------------------------------------------
*/

function validateDraftForPublishing(
    PDO $pdo,
    int $publicationID
): void {

    $issue =
        getIssueByPublicationId(
            $pdo,
            $publicationID
        );

    if (!$issue) {

        throw new Exception(
            'Draft issue not found.'
        );
    }

    if (
        !(bool) $issue['is_draft']
    ) {

        throw new Exception(
            'Only a draft issue can be published.'
        );
    }


    /*
     * YEAR
     */
    if (
        !isset($issue['year']) ||
        !is_numeric($issue['year']) ||
        (int) $issue['year'] <= 0
    ) {

        throw new Exception(
            'Year is required before publishing.'
        );
    }


    /*
     * VOLUME
     */
    if (
        !isset($issue['volume']) ||
        !is_numeric($issue['volume']) ||
        (int) $issue['volume'] <= 0
    ) {

        throw new Exception(
            'Volume is required before publishing.'
        );
    }


    /*
     * NUMBER
     */
    if (
        !isset($issue['number']) ||
        !is_numeric($issue['number']) ||
        (int) $issue['number'] <= 0
    ) {

        throw new Exception(
            'Number is required before publishing.'
        );
    }


    /*
     * PUBLICATION PDF
     */
    $publicationPDF =
        trim(
            (string) (
                $issue['publicationPDF']
                ?? ''
            )
        );

    if ($publicationPDF === '') {

        throw new Exception(
            'Publication issue PDF is required before publishing.'
        );
    }


    /*
     * ARTICLES
     */
    $statement =
        $pdo->prepare(
            'SELECT
                "journalID",
                "title",
                "journalPDF"
             FROM "JournalArticle"
             WHERE "publicationID" = :publicationID
             ORDER BY "journalID" ASC'
        );

    $statement->execute([
        ':publicationID' =>
        $publicationID
    ]);

    $articles =
        $statement->fetchAll();

    if (empty($articles)) {

        throw new Exception(
            'At least one article is required before publishing.'
        );
    }


    foreach (
        $articles as $index => $article
    ) {

        $articleNumber =
            $index + 1;


        /*
         * TITLE
         */
        $title =
            trim(
                (string) (
                    $article['title'] ?? ''
                )
            );

        if ($title === '') {

            throw new Exception(
                'Title for Article ' .
                    $articleNumber .
                    ' is required before publishing.'
            );
        }


        /*
         * ARTICLE PDF
         */
        $articlePDF =
            trim(
                (string) (
                    $article['journalPDF'] ?? ''
                )
            );

        if ($articlePDF === '') {

            throw new Exception(
                'PDF for Article ' .
                    $articleNumber .
                    ' is required before publishing.'
            );
        }


        /*
         * AUTHORS
         */
        $authorStatement =
            $pdo->prepare(
                'SELECT
                    a."firstName",
                    a."lastName"
                 FROM "ArticleAuthor" aa
                 INNER JOIN "Author" a
                    ON a."authorID" =
                       aa."authorID"
                 WHERE aa."journalID" =
                       :journalID'
            );

        $authorStatement->execute([
            ':journalID' =>
            (int) $article['journalID']
        ]);

        $authors =
            $authorStatement->fetchAll();

        if (empty($authors)) {

            throw new Exception(
                'Article ' .
                    $articleNumber .
                    ' must have at least one author before publishing.'
            );
        }

        $hasCompleteAuthor =
            false;

        foreach (
            $authors as $author
        ) {

            $firstName =
                trim(
                    (string) (
                        $author['firstName'] ?? ''
                    )
                );

            $lastName =
                trim(
                    (string) (
                        $author['lastName'] ?? ''
                    )
                );

            if (
                $firstName !== '' &&
                $lastName !== ''
            ) {

                $hasCompleteAuthor =
                    true;

                break;
            }
        }

        if (!$hasCompleteAuthor) {

            throw new Exception(
                'Article ' .
                    $articleNumber .
                    ' must have at least one complete author before publishing.'
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| PUBLISH EXISTING DRAFT
|--------------------------------------------------------------------------
*/

function publishExistingDraft(
    PDO $pdo,
    int $publicationID
): void {

    $issue =
        getIssueByPublicationId(
            $pdo,
            $publicationID
        );

    if (
        !$issue ||
        !(bool) $issue['is_draft']
    ) {

        throw new Exception(
            'Only a draft issue can be published.'
        );
    }

    validateDraftForPublishing(
        $pdo,
        $publicationID
    );

    $pdo->beginTransaction();

    try {

        /*
         * Find existing current issue.
         */
        $current =
            $pdo->query(
                'SELECT "publicationID"
                 FROM "PublicationIssue"
                 WHERE "is_current" = TRUE
                   AND "is_draft" = FALSE
                 LIMIT 1'
            )->fetchColumn();


        /*
         * Move current issue to archive.
         */
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


        /*
         * Make draft current.
         */
        $pdo->prepare(
            'UPDATE "PublicationIssue"
             SET
                "is_current" = TRUE,
                "is_draft" = FALSE
             WHERE "publicationID" =
                   :publicationID'
        )->execute([

            ':publicationID' =>
            $publicationID
        ]);


        $pdo->commit();
    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }
}


/*
|--------------------------------------------------------------------------
| DELETE ISSUE
|--------------------------------------------------------------------------
*/

function deleteIssue(
    PDO $pdo,
    int $publicationID
): void {

    $issue =
        getIssueByPublicationId(
            $pdo,
            $publicationID
        );

    if (!$issue) {

        throw new Exception(
            'Issue not found.'
        );
    }


    $statement =
        $pdo->prepare(
            'SELECT
                "journalID",
                "journalPDF"
             FROM "JournalArticle"
             WHERE "publicationID" =
                   :publicationID'
        );

    $statement->execute([
        ':publicationID' =>
        $publicationID
    ]);

    $articles =
        $statement->fetchAll();


    $pdo->beginTransaction();

    try {

        foreach (
            $articles as $article
        ) {

            $pdo->prepare(
                'DELETE FROM "ArticleAuthor"
                 WHERE "journalID" =
                       :journalID'
            )->execute([

                ':journalID' =>
                $article['journalID']
            ]);
        }


        $pdo->prepare(
            'DELETE FROM "JournalArticle"
             WHERE "publicationID" =
                   :publicationID'
        )->execute([

            ':publicationID' =>
            $publicationID
        ]);


        $pdo->prepare(
            'DELETE FROM "PublicationIssue"
             WHERE "publicationID" =
                   :publicationID'
        )->execute([

            ':publicationID' =>
            $publicationID
        ]);


        $pdo->commit();
    } catch (Throwable $e) {

        if (
            $pdo->inTransaction()
        ) {
            $pdo->rollBack();
        }

        throw $e;
    }


    /*
     * Delete article PDFs from storage.
     */
    foreach (
        $articles as $article
    ) {

        if (
            !empty($article['journalPDF'])
        ) {

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


    /*
     * Delete publication PDF.
     */
    if (
        !empty($issue['publicationPDF'])
    ) {

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


/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

$database =
    new Database();

$pdo =
    $database->getConnection();


try {

    $action =
        $_GET['action'] ??
        $_POST['action'] ??
        '';


    switch ($action) {


        /*
        |--------------------------------------------------------------------------
        | LIST
        |--------------------------------------------------------------------------
        */

        case 'list':

            sendResponse(
                true,
                'Issues retrieved successfully.',
                loadIssueList($pdo)
            );

            break;


        /*
        |--------------------------------------------------------------------------
        | ADD
        |--------------------------------------------------------------------------
        */

        case 'add':

            $year =
                filter_input(
                    INPUT_POST,
                    'year',
                    FILTER_VALIDATE_INT
                );

            $volume =
                filter_input(
                    INPUT_POST,
                    'volume',
                    FILTER_VALIDATE_INT
                );

            $number =
                filter_input(
                    INPUT_POST,
                    'number',
                    FILTER_VALIDATE_INT
                );


            $mode =
                strtolower(
                    trim(
                        (string) (
                            $_POST['mode'] ??
                            'draft'
                        )
                    )
                );


            if (
                $mode !== 'draft' &&
                $mode !== 'publish'
            ) {

                $mode =
                    'draft';
            }


            /*
             * Year, Volume and Number
             * are always required.
             */
            if (
                $year === false ||
                $year === null ||
                $year <= 0 ||
                $volume === false ||
                $volume === null ||
                $volume <= 0 ||
                $number === false ||
                $number === null ||
                $number <= 0
            ) {

                sendResponse(
                    false,
                    'Year, volume, and number are required.',
                    [],
                    400
                );
            }


            $articleList =
                json_decode(
                    $_POST['articles'] ?? '[]',
                    true
                );

            if (
                !is_array(
                    $articleList
                )
            ) {

                $articleList = [];
            }


            $publicationFile =
                $_FILES['publicationPDF'] ?? [];


            /*
             * New issues are first created
             * as drafts.
             */
            $publicationID =
                saveIssue(
                    $pdo,
                    (int) $year,
                    (int) $volume,
                    (int) $number,
                    $articleList,
                    $publicationFile,
                    true
                );


            /*
             * If Publish was selected,
             * validate and publish the draft.
             */
            if (
                $mode === 'publish'
            ) {

                publishExistingDraft(
                    $pdo,
                    $publicationID
                );

                sendResponse(
                    true,
                    'Publication issue published successfully.',
                    [
                        'publicationID' =>
                        $publicationID
                    ]
                );

                break;
            }


            /*
             * Normal Save as Draft.
             */
            sendResponse(
                true,
                'Draft saved successfully.',
                [
                    'publicationID' =>
                    $publicationID
                ]
            );

            break;


        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */

        case 'update':

            $publicationID =
                filter_input(
                    INPUT_POST,
                    'publicationID',
                    FILTER_VALIDATE_INT
                );

            $year =
                filter_input(
                    INPUT_POST,
                    'year',
                    FILTER_VALIDATE_INT
                );

            $volume =
                filter_input(
                    INPUT_POST,
                    'volume',
                    FILTER_VALIDATE_INT
                );

            $number =
                filter_input(
                    INPUT_POST,
                    'number',
                    FILTER_VALIDATE_INT
                );


            $mode =
                strtolower(
                    trim(
                        (string) (
                            $_POST['mode'] ??
                            'draft'
                        )
                    )
                );


            /*
             * Validate publication ID.
             */
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


            /*
             * Year, Volume and Number
             * are always required.
             */
            if (
                $year === false ||
                $year === null ||
                $year <= 0 ||
                $volume === false ||
                $volume === null ||
                $volume <= 0 ||
                $number === false ||
                $number === null ||
                $number <= 0
            ) {

                sendResponse(
                    false,
                    'Year, volume, and number are required.',
                    [],
                    400
                );
            }


            if (
                $mode !== 'draft' &&
                $mode !== 'current'
            ) {

                $mode =
                    'draft';
            }


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


            $isDraft =
                (bool) $existingIssue['is_draft'];


            /*
             * A draft remains a draft when using
             * Continue Editing / Save Draft.
             */
            if ($isDraft) {
                $mode = 'draft';
            }


            $articleList =
                json_decode(
                    $_POST['articles'] ?? '[]',
                    true
                );

            if (
                !is_array(
                    $articleList
                )
            ) {

                $articleList = [];
            }


            $articleList =
                normalizeArticleData(
                    $articleList
                );


            /*
             * Current/published issues cannot
             * have zero submitted articles.
             */
            if (
                !$isDraft &&
                empty($articleList)
            ) {

                sendResponse(
                    false,
                    'At least one article is required.',
                    [],
                    400
                );
            }


            /*
             * Load existing articles.
             */
            $existingArticles =
                getExistingArticles(
                    $pdo,
                    (int) $publicationID
                );


            /*
             * IMPORTANT:
             * Preserve the original editorial
             * note PDF path before making changes.
             *
             * This is needed in case the entire
             * draft is deleted because zero articles
             * remain.
             */
            $oldPublicationPdf =
                trim(
                    (string) (
                        $existingIssue['publicationPDF']
                        ?? ''
                    )
                );


            if (
                $oldPublicationPdf !== ''
            ) {

                $oldPublicationPdf =
                    normalizeStoragePath(
                        $oldPublicationPdf
                    );
            }


            /*
             * Current editorial PDF path.
             */
            $publicationPdfPath =
                $oldPublicationPdf;


            /*
             * Track PDFs that need to be
             * removed after the DB transaction.
             */
            $pdfsToDelete = [];


            /*
             * Tracks whether the entire draft
             * issue is deleted.
             */
            $deleteDraftIssue =
                false;


            /*
             * Track whether a replacement
             * editorial PDF was uploaded.
             */
            $newPublicationPdfUploaded =
                false;


            $publicationFile =
                $_FILES['publicationPDF'] ?? null;


            /*
             * Upload replacement editorial PDF
             * if one was selected.
             */
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

                $newPublicationPdfUploaded =
                    true;
            }


            /*
             * Published/current issue requires
             * an editorial PDF.
             */
            if (
                !$isDraft &&
                $publicationPdfPath === ''
            ) {

                sendResponse(
                    false,
                    'Publication issue PDF is required.',
                    [],
                    400
                );
            }


            $pdo->beginTransaction();

            try {

                /*
                 * Update PublicationIssue.
                 */
                $pdo->prepare(
                    'UPDATE "PublicationIssue"
                     SET
                        "year" = :year,
                        "volume" = :volume,
                        "number" = :number,
                        "publicationPDF" = :publicationPDF
                     WHERE "publicationID" =
                           :publicationID'
                )->execute([

                    ':year' =>
                    (int) $year,

                    ':volume' =>
                    (int) $volume,

                    ':number' =>
                    (int) $number,

                    ':publicationPDF' =>
                    $publicationPdfPath,

                    ':publicationID' =>
                    (int) $publicationID
                ]);


                /*
                 * Track submitted existing articles.
                 */
                $submittedExistingIDs = [];


                /*
                 * Process submitted articles.
                 */
                foreach (
                    $articleList as $index => $article
                ) {

                    $journalID =
                        isset(
                            $article['journalID']
                        ) &&
                        $article['journalID'] !== null
                        ? (int) $article['journalID']
                        : 0;


                    /*
                     * EXISTING ARTICLE
                     */
                    if (
                        $journalID > 0
                    ) {

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


                        $submittedExistingIDs[] =
                            $journalID;


                        updateExistingArticle(
                            $pdo,
                            $article,
                            $existingArticles[$journalID],
                            (int) $year,
                            (int) $volume,
                            (int) $number,
                            (int) $publicationID,
                            $publicationPdfPath,
                            $index + 1,
                            $isDraft
                        );

                        continue;
                    }


                    /*
                     * NEW ARTICLE
                     */
                    insertNewArticle(
                        $pdo,
                        $article,
                        (int) $publicationID,
                        (int) $year,
                        $index + 1,
                        $isDraft
                    );
                }


                /*
                 * DELETE EXISTING ARTICLES that were
                 * removed from the submitted list.
                 */
                foreach (
                    $existingArticles
                    as $existingJournalID =>
                    $existingArticle
                ) {

                    if (
                        !in_array(
                            (int) $existingJournalID,
                            $submittedExistingIDs,
                            true
                        )
                    ) {

                        /*
                         * Delete author links first.
                         */
                        $pdo->prepare(
                            'DELETE FROM "ArticleAuthor"
                             WHERE "journalID" =
                                   :journalID'
                        )->execute([

                            ':journalID' =>
                            (int) $existingJournalID
                        ]);


                        /*
                         * Delete article.
                         */
                        $pdo->prepare(
                            'DELETE FROM "JournalArticle"
                             WHERE "journalID" =
                                   :journalID'
                        )->execute([

                            ':journalID' =>
                            (int) $existingJournalID
                        ]);


                        /*
                         * Store old article PDF
                         * for cleanup after commit.
                         */
                        if (
                            !empty($existingArticle['journalPDF'])
                        ) {

                            $pdfsToDelete[] =
                                (string) (
                                    $existingArticle['journalPDF']
                                );
                        }
                    }
                }


                /*
                 * Count the articles that remain
                 * after the submitted list has been
                 * processed.
                 */
                $remainingStatement =
                    $pdo->prepare(
                        'SELECT COUNT(*)
                         FROM "JournalArticle"
                         WHERE "publicationID" =
                               :publicationID'
                    );

                $remainingStatement->execute([
                    ':publicationID' =>
                    (int) $publicationID
                ]);

                $remainingArticles =
                    (int) $remainingStatement->fetchColumn();


                /*
                 * IMPORTANT:
                 *
                 * If this is a draft and there are
                 * ZERO articles remaining, delete
                 * the entire draft issue.
                 *
                 * This removes:
                 * - Year
                 * - Volume
                 * - Number
                 * - publicationPDF database reference
                 * - PublicationIssue row
                 */
                if (
                    $isDraft &&
                    $remainingArticles === 0
                ) {

                    $deleteIssueStatement =
                        $pdo->prepare(
                            'DELETE FROM "PublicationIssue"
                             WHERE "publicationID" =
                                   :publicationID
                               AND "is_draft" = TRUE'
                        );

                    $deleteIssueStatement->execute([
                        ':publicationID' =>
                        (int) $publicationID
                    ]);

                    $deleteDraftIssue =
                        true;
                }


                /*
                 * Commit database changes.
                 */
                $pdo->commit();
            } catch (Throwable $e) {

                if (
                    $pdo->inTransaction()
                ) {

                    $pdo->rollBack();
                }

                throw $e;
            }


            /*
             * ------------------------------------------------------
             * STORAGE CLEANUP
             * ------------------------------------------------------
             */


            /*
             * Delete old article PDFs.
             */
            if (
                !empty($pdfsToDelete) &&
                is_array($pdfsToDelete)
            ) {

                foreach (
                    $pdfsToDelete as $oldArticlePdf
                ) {

                    try {

                        deletePdf(
                            $oldArticlePdf
                        );
                    } catch (Throwable $e) {

                        error_log(
                            'Removed article PDF cleanup error: ' .
                                $e->getMessage()
                        );
                    }
                }
            }


            /*
             * If the entire draft was deleted,
             * remove its editorial note PDF.
             *
             * If a replacement editorial PDF was
             * uploaded during this request, the
             * replacement must also be deleted because
             * the entire draft no longer exists.
             */
            if (
                $deleteDraftIssue
            ) {

                /*
                 * Delete the original editorial PDF.
                 */
                if (
                    $oldPublicationPdf !== ''
                ) {

                    try {

                        deletePdf(
                            $oldPublicationPdf
                        );
                    } catch (Throwable $e) {

                        error_log(
                            'Deleted draft editorial PDF cleanup error: ' .
                                $e->getMessage()
                        );
                    }
                }


                /*
                 * If a replacement PDF was uploaded,
                 * it is now orphaned because the draft
                 * issue was deleted. Remove it too.
                 */
                if (
                    $newPublicationPdfUploaded &&
                    $publicationPdfPath !== '' &&
                    (
                        $oldPublicationPdf === '' ||
                        normalizeStoragePath(
                            $publicationPdfPath
                        ) !== normalizeStoragePath(
                            $oldPublicationPdf
                        )
                    )
                ) {

                    try {

                        deletePdf(
                            $publicationPdfPath
                        );
                    } catch (Throwable $e) {

                        error_log(
                            'New draft editorial PDF cleanup error: ' .
                                $e->getMessage()
                        );
                    }
                }
            }


            /*
             * Normal case:
             *
             * If the draft still exists and a new
             * editorial PDF replaced the old one,
             * remove only the old PDF.
             */ elseif (
                $newPublicationPdfUploaded &&
                $oldPublicationPdf !== '' &&
                $publicationPdfPath !== '' &&
                normalizeStoragePath(
                    $oldPublicationPdf
                ) !== normalizeStoragePath(
                    $publicationPdfPath
                )
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


            /*
             * If the draft was completely deleted,
             * tell the frontend that the issue no
             * longer exists.
             */
            if (
                $deleteDraftIssue
            ) {

                sendResponse(
                    true,
                    'All articles were removed. The empty draft issue and editorial note were removed.',
                    [
                        'publicationID' =>
                        (int) $publicationID,

                        'remainingArticles' =>
                        0,

                        'draftIssueDeleted' =>
                        true,

                        'editorialNoteRemoved' =>
                        true
                    ]
                );
            }


            /*
             * Normal successful update.
             */
            sendResponse(
                true,
                $isDraft
                    ? 'Draft updated successfully.'
                    : 'Journal updated successfully.',
                [
                    'publicationID' =>
                    (int) $publicationID,

                    'remainingArticles' =>
                    $remainingArticles,

                    'draftIssueDeleted' =>
                    false,

                    'editorialNoteRemoved' =>
                    false
                ]
            );

            break;


        /*
        |--------------------------------------------------------------------------
        | PUBLISH
        |--------------------------------------------------------------------------
        */

        case 'publish':

            $publicationID =
                filter_input(
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


            /*
             * Complete validation occurs
             * inside publishExistingDraft().
             */
            publishExistingDraft(
                $pdo,
                (int) $publicationID
            );


            sendResponse(
                true,
                'Draft published successfully.',
                [
                    'publicationID' =>
                    (int) $publicationID
                ]
            );

            break;


        /*
        |--------------------------------------------------------------------------
        | DELETE ISSUE
        |--------------------------------------------------------------------------
        */

        case 'delete':

            $publicationID =
                filter_input(
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


        /*
        |--------------------------------------------------------------------------
        | DELETE ARTICLE
        |--------------------------------------------------------------------------
        */
        case 'deleteArticle':

            $journalID = filter_input(
                INPUT_POST,
                'journalID',
                FILTER_VALIDATE_INT
            );

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

            /*
     * Get the article and its publication issue.
     *
     * We need to know whether the article belongs to:
     * - a draft issue
     * - the current issue
     * - an archived issue
     */
            $statement = $pdo->prepare(
                'SELECT
            ja."journalID",
            ja."journalPDF",
            ja."publicationID",
            pi."publicationPDF",
            pi."is_draft",
            pi."is_current"
         FROM "JournalArticle" ja
         INNER JOIN "PublicationIssue" pi
            ON ja."publicationID" = pi."publicationID"
         WHERE ja."journalID" = :journalID
         LIMIT 1'
            );

            $statement->execute([
                ':journalID' => $journalID
            ]);

            $article = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$article) {
                sendResponse(
                    false,
                    'Article not found.',
                    [],
                    404
                );
            }

            $publicationID = (int) $article['publicationID'];

            $isDraft = (bool) $article['is_draft'];
            $isCurrent = (bool) $article['is_current'];

            /*
     * Archived articles cannot be removed.
     *
     * Only Draft and Current Issue articles can be removed.
     */
            if (!$isDraft && !$isCurrent) {
                sendResponse(
                    false,
                    'Archived articles cannot be removed.',
                    [],
                    400
                );
            }

            /*
     * Save the article PDF path before deleting
     * the database row.
     */
            $articlePdfPath = trim(
                (string) (
                    $article['journalPDF'] ?? ''
                )
            );

            /*
     * Save the editorial note PDF path before
     * potentially deleting the entire issue.
     */
            $publicationPdfPath = trim(
                (string) (
                    $article['publicationPDF'] ?? ''
                )
            );

            $remainingArticles = 0;

            $issueDeleted = false;

            $previousIssueActivated = false;

            /*
     * ------------------------------------------------------
     * DATABASE TRANSACTION
     * ------------------------------------------------------
     */
            $pdo->beginTransaction();

            try {

                /*
         * Remove author relationships first.
         */
                $pdo->prepare(
                    'DELETE FROM "ArticleAuthor"
             WHERE "journalID" = :journalID'
                )->execute([
                    ':journalID' => $journalID
                ]);

                /*
         * Remove the article itself.
         */
                $pdo->prepare(
                    'DELETE FROM "JournalArticle"
             WHERE "journalID" = :journalID'
                )->execute([
                    ':journalID' => $journalID
                ]);

                /*
         * Count how many articles remain in this issue.
         */
                $remainingStatement = $pdo->prepare(
                    'SELECT COUNT(*)
             FROM "JournalArticle"
             WHERE "publicationID" = :publicationID'
                );

                $remainingStatement->execute([
                    ':publicationID' => $publicationID
                ]);

                $remainingArticles = (int)
                $remainingStatement->fetchColumn();

                /*
         * --------------------------------------------------
         * IF ARTICLES STILL REMAIN
         * --------------------------------------------------
         *
         * The issue stays as it is.
         */
                if ($remainingArticles > 0) {

                    $pdo->commit();
                } else {

                    /*
             * --------------------------------------------------
             * LAST ARTICLE WAS REMOVED
             * --------------------------------------------------
             */

                    if ($isDraft) {

                        /*
                 * ----------------------------------------------
                 * DRAFT ISSUE
                 * ----------------------------------------------
                 *
                 * If the draft has no articles left,
                 * delete the entire draft issue.
                 */
                        $deleteIssueStatement = $pdo->prepare(
                            'DELETE FROM "PublicationIssue"
                     WHERE "publicationID" = :publicationID
                       AND "is_draft" = TRUE'
                        );

                        $deleteIssueStatement->execute([
                            ':publicationID' => $publicationID
                        ]);

                        $issueDeleted = true;
                    } elseif ($isCurrent) {

                        /*
                 * ----------------------------------------------
                 * CURRENT ISSUE
                 * ----------------------------------------------
                 *
                 * If the current issue has no articles left,
                 * find the most recent archived issue.
                 *
                 * Archived issues have:
                 *
                 * is_current = FALSE
                 * is_draft   = FALSE
                 */
                        $previousIssueStatement = $pdo->query(
                            'SELECT
                        "publicationID"
                     FROM "PublicationIssue"
                     WHERE "is_current" = FALSE
                       AND "is_draft" = FALSE
                     ORDER BY
                        "year" DESC,
                        "volume" DESC,
                        "number" DESC,
                        "publicationID" DESC
                     LIMIT 1'
                        );

                        $previousIssue =
                            $previousIssueStatement->fetch(
                                PDO::FETCH_ASSOC
                            );

                        /*
                 * Delete the now-empty current issue.
                 */
                        $deleteCurrentIssueStatement = $pdo->prepare(
                            'DELETE FROM "PublicationIssue"
                     WHERE "publicationID" = :publicationID
                       AND "is_current" = TRUE
                       AND "is_draft" = FALSE'
                        );

                        $deleteCurrentIssueStatement->execute([
                            ':publicationID' => $publicationID
                        ]);

                        $issueDeleted = true;

                        /*
                 * If an archived issue exists,
                 * make it the new current issue.
                 */
                        if ($previousIssue) {

                            $previousPublicationID =
                                (int) $previousIssue['publicationID'];

                            $activatePreviousStatement = $pdo->prepare(
                                'UPDATE "PublicationIssue"
                         SET "is_current" = TRUE
                         WHERE "publicationID" = :publicationID
                           AND "is_draft" = FALSE'
                            );

                            $activatePreviousStatement->execute([
                                ':publicationID' =>
                                $previousPublicationID
                            ]);

                            $previousIssueActivated = true;
                        }
                    }

                    /*
             * Commit all database changes.
             */
                    $pdo->commit();
                }
            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $e;
            }

            /*
     * ------------------------------------------------------
     * SUPABASE STORAGE CLEANUP
     * ------------------------------------------------------
     */

            /*
     * Delete the removed article's PDF.
     */
            if ($articlePdfPath !== '') {

                try {

                    deletePdf($articlePdfPath);
                } catch (Throwable $e) {

                    error_log(
                        'Article PDF cleanup error: ' .
                            $e->getMessage()
                    );
                }
            }

            /*
     * If the entire issue was deleted,
     * also delete its editorial note PDF.
     */
            if (
                $issueDeleted &&
                $publicationPdfPath !== ''
            ) {

                try {

                    deletePdf($publicationPdfPath);
                } catch (Throwable $e) {

                    error_log(
                        'Editorial note PDF cleanup error: ' .
                            $e->getMessage()
                    );
                }
            }

            /*
     * ------------------------------------------------------
     * RESPONSE
     * ------------------------------------------------------
     */

            if ($isDraft) {

                if ($issueDeleted) {

                    sendResponse(
                        true,
                        'Article removed successfully. The empty draft issue and editorial note were removed.',
                        [
                            'publicationID' =>
                            $publicationID,

                            'remainingArticles' =>
                            $remainingArticles,

                            'issueDeleted' =>
                            true,

                            'previousIssueActivated' =>
                            false,

                            'editorialNoteRemoved' => ($publicationPdfPath !== '')
                        ]
                    );
                }

                sendResponse(
                    true,
                    'Article removed successfully.',
                    [
                        'publicationID' =>
                        $publicationID,

                        'remainingArticles' =>
                        $remainingArticles,

                        'issueDeleted' =>
                        false,

                        'previousIssueActivated' =>
                        false,

                        'editorialNoteRemoved' =>
                        false
                    ]
                );
            } elseif ($isCurrent) {

                if (
                    $issueDeleted &&
                    $previousIssueActivated
                ) {

                    sendResponse(
                        true,
                        'Article removed successfully. The empty current issue was removed and the previous issue is now current.',
                        [
                            'publicationID' =>
                            $publicationID,

                            'remainingArticles' =>
                            $remainingArticles,

                            'issueDeleted' =>
                            true,

                            'previousIssueActivated' =>
                            true,

                            'editorialNoteRemoved' => ($publicationPdfPath !== '')
                        ]
                    );
                }

                /*
         * This happens if the current issue had no
         * previous archived issue to activate.
         */
                if ($issueDeleted) {

                    sendResponse(
                        true,
                        'Article removed successfully. The empty current issue was removed.',
                        [
                            'publicationID' =>
                            $publicationID,

                            'remainingArticles' =>
                            $remainingArticles,

                            'issueDeleted' =>
                            true,

                            'previousIssueActivated' =>
                            false,

                            'editorialNoteRemoved' => ($publicationPdfPath !== '')
                        ]
                    );
                }

                sendResponse(
                    true,
                    'Article removed successfully.',
                    [
                        'publicationID' =>
                        $publicationID,

                        'remainingArticles' =>
                        $remainingArticles,

                        'issueDeleted' =>
                        false,

                        'previousIssueActivated' =>
                        false,

                        'editorialNoteRemoved' =>
                        false
                    ]
                );
            }

            break;


        case 'view':

            $journalID =
                filter_input(
                    INPUT_GET,
                    'journalID',
                    FILTER_VALIDATE_INT
                );


            $publicationID =
                filter_input(
                    INPUT_GET,
                    'publicationID',
                    FILTER_VALIDATE_INT
                );


            /*
             * Article view.
             */
            if (
                $journalID !== false &&
                $journalID !== null &&
                $journalID > 0
            ) {

                sendResponse(
                    true,
                    'Journal article retrieved successfully.',
                    getArticlePayload(
                        $pdo,
                        (int) $journalID
                    )
                );
            }


            /*
             * Publication issue view.
             */
            if (
                $publicationID !== false &&
                $publicationID !== null &&
                $publicationID > 0
            ) {

                sendResponse(
                    true,
                    'Issue retrieved successfully.',
                    getIssuePayload(
                        $pdo,
                        (int) $publicationID
                    )
                );
            }


            sendResponse(
                false,
                'Invalid journal ID or publication ID.',
                [],
                400
            );

            break;


        /*
        |--------------------------------------------------------------------------
        | PDF URL
        |--------------------------------------------------------------------------
        */

        case 'pdfUrl':

            $type =
                strtolower(
                    trim(
                        (string) (
                            $_GET['type'] ??
                            $_POST['type'] ??
                            ''
                        )
                    )
                );


            $storagePath =
                trim(
                    (string) (
                        $_GET['storagePath'] ??
                        $_POST['storagePath'] ??
                        ''
                    )
                );


            if (
                $type !== 'article' &&
                $type !== 'publication'
            ) {

                sendResponse(
                    false,
                    'Invalid PDF type.',
                    [],
                    400
                );
            }


            if (
                $storagePath === ''
            ) {

                sendResponse(
                    false,
                    'Invalid storage path.',
                    [],
                    400
                );
            }


            $signedUrl =
                createSignedPdfUrl(
                    $storagePath
                );


            sendResponse(
                true,
                'Signed PDF URL generated successfully.',
                [
                    'pdfUrl' =>
                    $signedUrl
                ]
            );

            break;


        /*
        |--------------------------------------------------------------------------
        | DEFAULT
        |--------------------------------------------------------------------------
        */

        default:

            sendResponse(
                false,
                'Invalid action.',
                [],
                400
            );

            break;
    }
} catch (Throwable $e) {

    error_log(
        'Manage Journal API Error: ' .
            $e->getMessage()
    );

    sendResponse(
        false,
        $e->getMessage(),
        [],
        500
    );
}
