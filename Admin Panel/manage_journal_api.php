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
    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            [
                'data' => $data
            ]
        )
    );
    exit;
}

function normalizeStoragePath(string $storagePath): string {
    global $bucket;
    $storagePath = trim($storagePath);

    if ($storagePath === '') {
        throw new Exception('The storage path is empty.');
    }

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {

        $parsedPath = parse_url($storagePath,PHP_URL_PATH);
        if (!is_string($parsedPath)) {
            throw new Exception('Unable to extract the storage path from the PDF URL.');
        }

        $storagePath = $parsedPath;
        $objectMarker = '/storage/v1/object/';
        $markerPosition = strpos($storagePath,$objectMarker);

        if ($markerPosition !== false) {
            $storagePath = substr($storagePath,$markerPosition + strlen($objectMarker));
            $storagePath = preg_replace('#^(public|sign)/#','',$storagePath);
        }
    }

    $storagePath = ltrim($storagePath,'/');
    $bucketName = trim($bucket,'/');
    $bucketPrefix = $bucketName . '/';

    if (str_starts_with($storagePath,$bucketPrefix)) {
        $storagePath = substr($storagePath,strlen($bucketPrefix));
    }

    $storagePath = preg_replace('#/+#','/',$storagePath
    );

    $storagePath = rtrim($storagePath,'/'
    );

    if ($storagePath === '') {
        throw new Exception('The storage path is empty.');
    }

    return $storagePath;
}

function createSignedPdfUrl(string $storagePath,int $expiresIn = 3600): string {global $supabaseUrl;global $supabaseKey; global $bucket;

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        return $storagePath;
    }

    $bucketName = trim($bucket,'/');

    if ($bucketName === '') {
        throw new Exception('Supabase storage bucket is not configured.');
    }

    $storagePath = normalizeStoragePath($storagePath);

    error_log('SIGNED PDF STORAGE PATH: ' . $storagePath);
    error_log('BUCKET: ' . $bucketName);


    $encodedPath = implode('/',array_map('rawurlencode',explode('/', $storagePath)));

    $url = rtrim($supabaseUrl,'/')
        . '/storage/v1/object/sign/'
        . rawurlencode($bucketName)
        . '/'
        . $encodedPath;

    $ch = curl_init($url);
    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'expiresIn' => $expiresIn
        ]),

        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer '
            . $supabaseKey,

            'apikey: '
            . $supabaseKey,

            'Content-Type: application/json',

            'Accept: application/json'
        ],

        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);

    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new Exception('Unable to create signed PDF URL: '. ($curlError ?: 'Unknown cURL error.'));
    }

    if ($httpCode < 200|| $httpCode >= 300) {

        throw new Exception(
            'Unable to create signed PDF URL. '
            . 'Supabase returned HTTP '
            . $httpCode
            . ': '
            . $response
        );
    }

    $result = json_decode($response,true);

    if (!is_array($result)) {
        throw new Exception('Supabase returned an invalid signed URL response.');
    }

    $signedUrl = trim((string) ($result['signedURL']?? ''));

    if ($signedUrl === '') {
        throw new Exception('Supabase did not return a signed PDF URL.');
    }

    if (str_starts_with($signedUrl,'/')) {

        $signedUrl =rtrim($supabaseUrl,'/'). $signedUrl;
    }

    return $signedUrl;
}

function createPublicPdfUrl(string $storagePath): string {

    global $supabaseUrl;
    global $bucket;

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        return $storagePath;
    }

    $bucketName = trim($bucket,'/');

    if ($bucketName === '') {
        throw new Exception('Supabase storage bucket is not configured');
    }

    $storagePath = normalizeStoragePath(
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

    return rtrim($supabaseUrl,'/')
        . '/storage/v1/object/public/'
        . rawurlencode($bucketName)
        . '/'
        . $encodedPath;
}


function validatePdf(array $file,string $description): void {

    if (!isset($file['error'])|| $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(
            $description . ' upload failed.'
        );
    }

    if (!isset($file['tmp_name'])|| !is_uploaded_file($file['tmp_name'])) {
        throw new Exception($description . ' is invalid.');
    }

    $mimeType = mime_content_type($file['tmp_name']);

    if ($mimeType !== 'application/pdf') {
        throw new Exception($description . ' must be a PDF.');
    }

    $maxFileSize =40 * 1024 * 1024;

    if ($file['size'] > $maxFileSize) {
        throw new Exception($description. ' exceeds the 40 MB limit.');
    }
}


function createStorageFilename(string $originalName,string $prefix): string {

    $originalName =basename($originalName);
    $safeFileName = preg_replace(
        '/[^A-Za-z0-9.\_-]/',
        '_',
        $originalName
    );

    return $prefix
        . '_'
        . uniqid('', true)
        . '_'
        . $safeFileName;
}


function uploadPdfsConcurrently(array $uploads): void {
    global $supabaseUrl;
    global $supabaseKey;
    global $bucket;

    if (empty($uploads)) {
        return;
    }

    $multiHandle = curl_multi_init();
    $handles = [];

    try {
        foreach ($uploads as $index => $upload) {

            $localFile =$upload['localFile'];
            $storagePath = normalizeStoragePath($upload['storagePath']);
            $fileContents =file_get_contents($localFile);

            if ($fileContents === false) {
                throw new Exception('Unable to read PDF file.');
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

            $url = rtrim($supabaseUrl,'/')
            
                . '/storage/v1/object/'
                . rawurlencode($bucket)
                . '/'
                . $encodedPath;

            $ch = curl_init($url);

            curl_setopt_array($ch, [

                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS =>$fileContents,
                CURLOPT_HTTPHEADER => [

                    'Authorization: Bearer '
                    . $supabaseKey,

                    'apikey: '
                    . $supabaseKey,

                    'Content-Type: application/pdf',

                    'Content-Length: '
                    . strlen($fileContents)
                ],

                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_TCP_KEEPALIVE => 1
            ]);

            curl_multi_add_handle($multiHandle,$ch);
            $handles[$index] = $ch;
        }

        $running = null;

        do {
            $status = curl_multi_exec( $multiHandle,$running);
            if ($running) {
                curl_multi_select($multiHandle,1.0);
            }

        } 
        
        while ($running && $status === CURLM_OK);

        foreach ($handles as $index => $ch) {

            $response =curl_multi_getcontent($ch);
            $httpCode =curl_getinfo($ch,CURLINFO_HTTP_CODE);
            $curlError =curl_error($ch);

            if ($response === false|| $httpCode < 200|| $httpCode >= 300) {

                throw new Exception(
                    'PDF upload failed for '
                    . $uploads[$index]['description']
                    . ': '
                    . (
                        $curlError
                        ?: $response
                    )
                );
            }

            curl_multi_remove_handle(
                $multiHandle,
                $ch
            );

            curl_close($ch);
        }

    } 
    
    finally {
        curl_multi_close($multiHandle);
    }
}

function deletePdf(string $storagePath): void {
    global $supabaseUrl;
    global $supabaseKey;
    global $bucket;

    if (trim($storagePath) === '') {
        return;
    }

    $storagePath = normalizeStoragePath($storagePath);

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

    $url = rtrim($supabaseUrl,'/')
        . '/storage/v1/object/'
        . rawurlencode($bucket)
        . '/'
        . $encodedPath;

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => [

            'Authorization: Bearer '
            . $supabaseKey,

            'apikey: '
            . $supabaseKey
        ],

        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60
    ]);

    curl_exec($ch);
    curl_close($ch);
}


function normalizeArticleData(array $articles): array {
    $normalized = [];
    foreach ($articles as $index => $article) {

        $normalized[] = [

            'journalID' =>
                isset($article['journalID'])
                && $article['journalID'] !== null
                    ? (int) $article['journalID']
                    : null,

            'title' =>
                trim(
                    (string) (
                        $article['title']
                        ?? ''
                    )
                ),

            'authors' =>
                is_array(
                    $article['authors']
                    ?? null
                )
                    ? $article['authors']
                    : [],

            'existingPdf' =>
                trim(
                    (string) (
                        $article['existingPdf']
                        ?? ''
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
        ':publicationID' =>
            $publicationID
    ]);

    $issue =$statement->fetch();
    return $issue ?: null;
}

function getIssuePayload(PDO $pdo,int $publicationID): array {

    $issue =getIssueByPublicationId($pdo,$publicationID);

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
         ORDER BY ja."journalID", a."authorID"'
    );

    $statement->execute([':publicationID' =>$publicationID
    ]);
    $rows =$statement->fetchAll();
    $articlesById = [];

    foreach ($rows as $row) {

        $journalID =(int) $row['journalID'];

        if (!isset($articlesById[$journalID])) {
            $journalPdfPath =
                trim(
                    (string) (
                        $row['journalPDF']
                        ?? ''
                    )
                );

            $articlesById[$journalID] = [

                'journalID' => $journalID,
                'title' => $row['title'],
                'journalPDF' => $journalPdfPath !== ''
                        ? $journalPdfPath
                        : null,
                'pdfUrl' => $journalPdfPath !== ''
                        ? createSignedPdfUrl($journalPdfPath)
                        : '',
                'authors' => []
            ];
        }

        if (!empty($row['firstName'])|| !empty($row['lastName'])) {

            $articlesById[$journalID]['authors'][] = [

                'firstName' =>
                    $row['firstName']
                    ?? '',
                'lastName' =>
                    $row['lastName']
                    ?? ''
            ];
        }
    }

    $publicationPdfPath = trim((string) ($issue['publicationPDF']?? ''));

    return [

        'publicationID' =>(int) $issue['publicationID'],
        'year' =>(int) $issue['year'],
        'volume' =>(int) $issue['volume'],
        'number' =>(int) $issue['number'],

        'publicationPDF' =>
            $publicationPdfPath !== ''
                ? $publicationPdfPath
                : null,

        'publicationPdfUrl' =>
            $publicationPdfPath !== ''
                ? createSignedPdfUrl($publicationPdfPath)
                : '',

        'is_current' =>
            (bool) $issue['is_current'],

        'is_draft' =>
            (bool) $issue['is_draft'],

        'articles' =>
            array_values(
                $articlesById
            )
    ];
}


/* =========================================
   GET ARTICLE PAYLOAD
   UPDATED:
   - Includes publicationPDF
   - Creates PUBLIC PDF URL
   - Generates APA citation
========================================= */

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
        ':journalID' =>
            $journalID
    ]);

    $article = $statement->fetch();

    if (!$article) {
        throw new Exception('Journal article not found.');
    }

    /*GET AUTHORS*/
    $authorStatement = $pdo->prepare(
        'SELECT
            a."firstName",
            a."lastName"
         FROM "ArticleAuthor" aa
         INNER JOIN "Author" a
            ON a."authorID" = aa."authorID"
         WHERE aa."journalID" = :journalID
         ORDER BY a."lastName", a."firstName"'
    );

    $authorStatement->execute([':journalID' =>$journalID]);
    $authors = $authorStatement->fetchAll();
    $authorList = [];

    foreach ($authors as $author) {

        $authorList[] = [

            'firstName' =>
                $author['firstName']
                ?? '',

            'lastName' =>
                $author['lastName']
                ?? ''
        ];
    }

    /*CREATE PUBLIC ARTICLE PDF URL*/
    $pdfUrl = '';
    if (!empty($article['journalPDF'])) {
        $pdfUrl = createPublicPdfUrl((string) $article['journalPDF']);
    }

    /*GENERATE APA CITATION*/
    $citationAuthors = [];

    foreach ($authorList as $author) {

        $firstName =trim((string) ($author['firstName']?? ''));
        $lastName =trim((string) ($author['lastName']?? ''));

        if ($firstName === ''&& $lastName === '') {
            continue;
        }

        $initials = '';

        if ($firstName !== '') {

            $firstNameParts =
                preg_split(
                    '/\s+/',
                    $firstName
                );

            foreach ($firstNameParts as $part) {

                $part = trim($part);

                if ($part !== '') {

                    $initials .=
                        strtoupper(
                            mb_substr(
                                $part,
                                0,
                                1
                            )
                        )
                        . '. ';
                }
            }

            $initials =
                trim($initials);
        }

        if ($lastName !== ''&& $initials !== '') {
            $citationAuthors[] =$lastName. ', '. $initials;
        } 
        
        elseif ($lastName !== '') {
            $citationAuthors[] =$lastName;
        } 
    
        else {
            $citationAuthors[] = $initials;
        }
    }


    /*FORMAT AUTHORS */
    $authorText = '';
    $authorCount = count($citationAuthors);

    if ($authorCount === 1) {
        $authorText =$citationAuthors[0]. ' ';

    } 
    
    elseif ($authorCount === 2) {
        $authorText =
            $citationAuthors[0]
            . ', & '
            . $citationAuthors[1]
            . ' ';
    } 
    
    elseif ($authorCount > 2) {
        $lastAuthor =array_pop($citationAuthors);
        $authorText =
            implode(
                ', ',
                $citationAuthors
            )
            . ', & '
            . $lastAuthor
            . ' ';
    }

    /*YEAR */
    $year =
        !empty($article['year'])
            ? (string) $article['year']
            : 'n.d.';


    /*ARTICLE TITLE */
    $title = trim((string) ($article['title']?? ''));

    if ($title === '') {
        $title = 'Untitled Article';
    }


    /*VOLUME AND NUMBER*/
    $volume =
        !empty($article['volume'])
            ? (string) $article['volume']
            : '';

    $number =
        !empty($article['number'])
            ? (string) $article['number']
            : '';


    /*JOURNAL INFORMATION*/

    $journalInformation =
        'Convergence';

    if ($volume !== '') {

        $journalInformation .=
            ', '
            . $volume;

        if ($number !== '') {
            $journalInformation .=
                '('
                . $number
                . ')';
        }
    }

    /*FINAL CITATION*/
    $citation =
        $authorText
        . '('
        . $year
        . '). '
        . $title
        . '. '
        . $journalInformation
        . '.';


    /*RETURN ARTICLE DATA*/
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
                    $article['year']
                    ?? 0
                ),

            'volume' =>
                (int) (
                    $article['volume']
                    ?? 0
                ),

            'number' =>
                (int) (
                    $article['number']
                    ?? 0
                ),

            'publicationPDF' =>
                $article['publicationPDF']
                ?? null
        ],

        'authors' =>
            $authorList,

        'pdfUrl' =>
            $pdfUrl,

        'citation' =>
            $citation
    ];
}


/*LOAD ISSUE LIST*/
function loadIssueList(PDO $pdo): array {
    $statement = $pdo->query(
        'SELECT *
         FROM "PublicationIssue"
         ORDER BY
            "year" DESC,
            "volume" DESC,
            "number" DESC,
            "publicationID" DESC'
    );

    $rows =$statement->fetchAll();
    $issues = [];
    foreach ($rows as $row) {

        $issues[
            (int) $row['publicationID']
        ] = [

            'publicationID' =>
                (int) $row['publicationID'],

            'year' =>
                (int) $row['year'],

            'volume' =>
                (int) $row['volume'],

            'number' =>
                (int) $row['number'],

            'publicationPDF' =>
                $row['publicationPDF']
                ?? null,

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

    $publicationIds =array_keys($issues);
    $placeholders =
        implode(
            ',',
            array_fill(
                0,
                count($publicationIds),
                '?'
            )
        );

    $articleStatement = $pdo->prepare(
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
         WHERE ja."publicationID" IN ('
            . $placeholders .
            ')
         ORDER BY
            ja."publicationID",
            ja."journalID",
            a."authorID"'
    );

    $articleStatement->execute($publicationIds);
    $articleRows =$articleStatement->fetchAll();

    foreach ($articleRows as $row) {

        $publicationID =(int) $row['publicationID'];
        $journalID =(int) $row['journalID'];

        if (!isset($issues[$publicationID]['articles'][$journalID])) {

            $issues[$publicationID]
                ['articles'][$journalID] = [

                    'journalID' =>
                        $journalID,

                    'title' =>
                        $row['title'],

                    'journalPDF' =>
                        $row['journalPDF']
                        ?? null,

                    'authors' => []
                ];
        }

        if (!empty($row['firstName'])|| !empty($row['lastName'])) {

            $issues[$publicationID]
                ['articles'][$journalID]
                ['authors'][] = [

                    'firstName' =>
                        $row['firstName']
                        ?? '',

                    'lastName' =>
                        $row['lastName']
                        ?? ''
                ];
        }
    }

    foreach ($issues as $publicationID => $issue) {
        $issues[$publicationID]['articles'] =
            array_values(
                $issue['articles']
            );

        if (empty($issues[$publicationID]['articles'])) {
            $pdo->prepare(
                'DELETE FROM "PublicationIssue"
                 WHERE "publicationID" = :publicationID'
            )->execute([
                ':publicationID' => $publicationID
            ]);

            unset($issues[$publicationID]);
        }
    }


    /*SEPARATE CURRENT / DRAFT / ARCHIVE */
    $current = [];
    $draft = [];
    $archive = [];

    foreach ($issues as $issue) {

        if ($issue['is_current']&& !$issue['is_draft']) {
            $current[] =
                $issue;

        } 
        
        elseif (!$issue['is_current']&& $issue['is_draft']) {
            $draft[] =$issue;

        } 
        
        else {
            $archive[] =$issue;
        }
    }

    return [

        'current' =>
            $current,

        'draft' =>
            $draft,

        'archive' =>
            $archive
    ];}


/*SAVE ARTICLE AUTHORS*/

function saveArticleAuthors(PDO $pdo,int $journalID,array $authors): void {

    $pdo->prepare(
        'DELETE FROM "ArticleAuthor"
         WHERE "journalID" = :journalID'
    )->execute([
        ':journalID' =>
            $journalID
    ]);

    foreach ($authors as $author) {

        $firstName =
            trim(
                (string) (
                    $author['firstName']
                    ?? ''
                )
            );

        $lastName =
            trim(
                (string) (
                    $author['lastName']
                    ?? ''
                )
            );

        if ($firstName === ''|| $lastName === '') {
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

            ':firstName' =>
                $firstName,

            ':lastName' =>
                $lastName
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
                ':firstName' =>
                    $firstName,
                ':lastName' =>
                    $lastName
            ]);

            $authorID =$createAuthor->fetchColumn();

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

            ':journalID' =>
                $journalID,

            ':authorID' =>
                (int) $authorID
        ]);
    }
}


/*ENSURE ONLY ONE DRAFT*/
function ensureOneDraft(PDO $pdo): void {
    $draft =
        $pdo->query(
            'SELECT "publicationID"
             FROM "PublicationIssue"
             WHERE "is_draft" = TRUE
             LIMIT 1'
        )->fetchColumn();

    if ($draft !== false) {
        throw new Exception('A draft issue already exists.');
    }
}

function createIssueRecord(PDO $pdo,int $year,int $volume,int $number,string $publicationPDF,bool $isCurrent,bool $isDraft): int {

    $sql =
        'INSERT INTO "PublicationIssue"
            ("year",
             "volume",
             "number",
             "publicationPDF",
             "is_current",
             "is_draft")
         VALUES
            (:year,
             :volume,
             :number,
             :publicationPDF,
             :is_current,
             :is_draft)
         RETURNING "publicationID"';

    $statement = $pdo->prepare($sql);

    $statement->execute([

        ':year' => $year,
        ':volume' => $volume,
        ':number' =>$number,
        ':publicationPDF' =>$publicationPDF,
    
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
        throw new Exception('Unable to create the publication issue.');
    }

    return (int) $publicationID;
}

/*SAVE ISSUE*/
function saveIssue(PDO $pdo,int $year,int $volume,int $number,array $articleList,array $publicationFile,bool $isDraft,?int $publicationID = null): int {

    $articleList =normalizeArticleData($articleList);

    if (empty($articleList)) {
        throw new Exception(
            'At least one article is required.'
        );
    }

    $publicationPDF = '';

    if (isset($publicationFile['tmp_name'])&& is_uploaded_file($publicationFile['tmp_name'])) {

        validatePdf($publicationFile,
            'Publication issue PDF'
        );

        $publicationFilename =
            createStorageFilename(
                $publicationFile['name'],
                'publication_issue'
            );

        $publicationPath =
            $year
            . '/publication_'
            . ($publicationID ?? 0)
            . '/'
            . $publicationFilename;

        $publicationPDF =
            $publicationPath;
    }

    $pdo->beginTransaction();

    try {
        if ($publicationID !== null) {

            $existingIssue =
                getIssueByPublicationId(
                    $pdo,
                    $publicationID
                );

            if (!$existingIssue) {
                throw new Exception('Issue not found.');
            }

            $statement = $pdo->prepare(
                'UPDATE "PublicationIssue"
                 SET
                    "year" = :year,
                    "volume" = :volume,
                    "number" = :number,
                    "publicationPDF" = :publicationPDF
                 WHERE
                    "publicationID" = :publicationID'
            );

            $statement->execute([

                ':year' =>
                    $year,

                ':volume' =>
                    $volume,

                ':number' =>
                    $number,

                ':publicationPDF' =>
                    $publicationPDF !== ''
                        ? $publicationPDF
                        : (
                            $existingIssue[
                                'publicationPDF'
                            ] ?? ''
                        ),

                ':publicationID' =>
                    $publicationID
            ]);

        } 
        
        else {

            if ($isDraft) {
                ensureOneDraft($pdo);
            }

            $publicationID =
                createIssueRecord(
                    $pdo,
                    $year,
                    $volume,
                    $number,
                    $publicationPDF,
                    !$isDraft,
                    $isDraft
                );
        }

        /*UPLOAD PUBLICATION PDF*/
        if ($publicationPDF !== '') {

            $uploads = [[
                'localFile' =>$publicationFile['tmp_name'],
                'storagePath' =>$publicationPDF,
                'description' =>'Publication issue PDF'
            ]];

            uploadPdfsConcurrently($uploads);
        }


        /*SAVE ARTICLES */
        foreach ($articleList as $index => $article) {

            $title =
                trim(
                    (string) (
                        $article['title']
                        ?? ''
                    )
                );

            if ($title === '') {
                throw new Exception(
                    'Title for Article '
                    . ($index + 1)
                    . ' is required.'
                );
            }

            $pdfInput =
                $_FILES[
                    'pdf_' . $index
                ] ?? null;

            $pdfPath =
                trim(
                    (string) (
                        $article['existingPdf']
                        ?? ''
                    )
                );


            if (is_array($pdfInput)&& !empty($pdfInput['tmp_name'])) {

                validatePdf(
                    $pdfInput,
                    'PDF for Article '
                    . ($index + 1)
                );

                $pdfPath =
                    $year
                    . '/publication_'
                    . $publicationID
                    . '/'
                    . createStorageFilename(
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
                            'Article '
                            . ($index + 1)
                            . ' PDF'
                    ]
                ]);

            } 
            
            elseif ($pdfPath === '') {
                throw new Exception('PDF for Article '. ($index + 1). ' is required.');
            }

            $journalID = $article['journalID']?? null;
            if ($journalID !== null&& $journalID > 0) {

                $existingJournal =
                    $pdo->prepare(
                        'SELECT "journalPDF"
                         FROM "JournalArticle"
                         WHERE "journalID" = :journalID
                         LIMIT 1'
                    );

                $existingJournal->execute([
                    ':journalID' =>
                        $journalID
                ]);

                $oldPDF =
                    $existingJournal->fetchColumn();


                $updateJournal =
                    $pdo->prepare(
                        'UPDATE "JournalArticle"
                         SET
                            "title" = :title,
                            "journalPDF" = :journalPDF
                         WHERE
                            "journalID" = :journalID'
                    );

                $updateJournal->execute([

                    ':title' =>$title,
                    ':journalPDF' =>$pdfPath,
                    ':journalID' =>$journalID
                ]);

                if ($pdfPath !== ''&& $oldPDF&& $oldPDF !== $pdfPath) {

                    try {
                        deletePdf($oldPDF);

                    } 
                    
                    catch (Throwable $e) {
                        error_log('Old article PDF cleanup error: '. $e->getMessage());
                    }
                }


                saveArticleAuthors($pdo,$journalID,$article['authors']?? []);

            } 
            
            else {
                $insertJournal =
                    $pdo->prepare(
                        'INSERT INTO "JournalArticle"
                            ("title",
                             "journalPDF",
                             "publicationID")
                         VALUES
                            (:title,
                             :journalPDF,
                             :publicationID)
                         RETURNING "journalID"'
                    );

                $insertJournal->execute([
                    ':title' =>$title,
                    ':journalPDF' =>$pdfPath,
                    ':publicationID' =>$publicationID
                ]);

                $journalID =$insertJournal->fetchColumn();

                if ($journalID === false) {
                    throw new Exception('Unable to create journal article.');
                }

                saveArticleAuthors($pdo,(int) $journalID,$article['authors']?? []);
            }
        }

        $pdo->commit();
        return (int) $publicationID;

    } 
    
    catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

/*PUBLISH EXISTING DRAFT */
function publishExistingDraft(PDO $pdo,int $publicationID): void {
    $issue = getIssueByPublicationId($pdo,$publicationID);

    if (!$issue|| !(bool) $issue['is_draft']) {
        throw new Exception(
            'Only a draft issue can be published.'
        );
    }

    $pdo->beginTransaction();

    try {

        $current =
            $pdo->query(
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
                 WHERE
                    "publicationID" = :publicationID'
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
             WHERE
                "publicationID" = :publicationID'
        )->execute([
            ':publicationID' =>
                $publicationID
        ]);

        $pdo->commit();

    } 
    
    catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/*DELETE ISSUE*/
function deleteIssue(PDO $pdo,int $publicationID): void {

    $issue =getIssueByPublicationId($pdo,$publicationID);

    if (!$issue) {
        throw new Exception('Issue not found.');
    }

    $statement =
        $pdo->prepare(
            'SELECT
                "journalID",
                "journalPDF"
             FROM "JournalArticle"
             WHERE "publicationID" = :publicationID'
        );

    $statement->execute([
        ':publicationID' =>
            $publicationID
    ]);

    $articleRows =
        $statement->fetchAll();


    foreach ($articleRows as $article) {
        $pdo->prepare(
            'DELETE FROM "ArticleAuthor"
             WHERE "journalID" = :journalID'
        )->execute([
            ':journalID' =>
                $article['journalID']
        ]);


        if (!empty($article['journalPDF'])) {
            try {
                deletePdf($article['journalPDF']);
            } 
            
            catch (Throwable $e) {
                error_log('Journal PDF cleanup error: '. $e->getMessage());
            }
        }
    }

    $pdo->prepare(
        'DELETE FROM "JournalArticle"
         WHERE "publicationID" = :publicationID'
    )->execute([
        ':publicationID' =>
            $publicationID
    ]);


    if (!empty($issue['publicationPDF'])) {
        try {
            deletePdf($issue['publicationPDF']);
        } 
        
        catch (Throwable $e) {
            error_log('Publication PDF cleanup error: '. $e->getMessage());
        }
    }

    $pdo->prepare(
        'DELETE FROM "PublicationIssue"
         WHERE "publicationID" = :publicationID'
    )->execute([
        ':publicationID' =>
            $publicationID
    ]);
}


$database = new Database();
$pdo = $database->getConnection();

try {

    $action =
        $_GET['action']
        ?? $_POST['action']
        ?? '';


    switch ($action) {

        case 'list':
            sendResponse(true,'Issues retrieved successfully.',loadIssueList($pdo));
            break;

        /*ADD*/
        case 'add':
            $year = filter_input(INPUT_POST,'year',FILTER_VALIDATE_INT);
            $volume = filter_input(INPUT_POST,'volume',FILTER_VALIDATE_INT);
            $number = filter_input( INPUT_POST, 'number',FILTER_VALIDATE_INT);
            $mode =
                strtolower(
                    trim(
                        (string) (
                            $_POST['mode']
                            ?? 'draft'
                        )
                    )
                );


            if ($year === false|| $year === null|| $volume === false|| $volume === null|| $number === false|| $number === null) {
                sendResponse(false,'Invalid publication issue information.',[],400);
            }

            if (!isset($_FILES['publicationPDF'])) {
                sendResponse(false,'Publication issue PDF is required.',[],400);
            }

            $articleList =
                json_decode(
                    $_POST['articles']
                    ?? '[]',
                    true
                );

            if (!is_array($articleList)|| empty($articleList)) {
                sendResponse(false,'At least one article is required.',[],400);
            }

            $isDraft = $mode === 'draft';

            $publicationID =
                saveIssue(
                    $pdo,
                    (int) $year,
                    (int) $volume,
                    (int) $number,
                    $articleList,
                    $_FILES['publicationPDF'],
                    $isDraft,
                    null
                );


            if ($mode === 'publish') {
                $issue =getIssueByPublicationId($pdo,$publicationID);

                if ($issue&& !$issue['is_current']&& !$issue['is_draft']) {
                    // No-op.
                }


                $current =
                    $pdo->query(
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
                         WHERE
                            "publicationID" = :publicationID'
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
                     WHERE
                        "publicationID" = :publicationID'
                )->execute([
                    ':publicationID' =>
                        $publicationID
                ]);
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
            $volume =filter_input(INPUT_POST,'volume',FILTER_VALIDATE_INT);
            $number =filter_input(INPUT_POST,'number',FILTER_VALIDATE_INT);
            $mode =strtolower(trim((string) ($_POST['mode']?? 'draft')));

            if ($publicationID === false|| $publicationID === null|| $publicationID <= 0) {
                sendResponse(false,'Invalid publication ID.',[],400);
            }

            if ($year === false|| $year === null|| $volume === false|| $volume === null|| $number === false|| $number === null) {
                sendResponse(false,'Invalid publication issue information.',[],400);
            }

            $articleList =json_decode($_POST['articles']?? '[]',true);

            if (!is_array($articleList) || empty($articleList)) {
                sendResponse(false,'At least one article is required.',[],400);
            }

            $publicationFile =$_FILES['publicationPDF']?? null;

            $existingIssue =getIssueByPublicationId($pdo,$publicationID);


            if (!$existingIssue) {
                sendResponse(false,'Draft issue not found.',[],404);
            }

            if ($mode === 'draft') {

                /*UPDATE PUBLICATION PDF*/
                if ($publicationFile&& !empty($publicationFile['tmp_name'])) {
                    validatePdf($publicationFile,'Publication issue PDF');

                    $fileName = createStorageFilename( $publicationFile['name'],'publication_issue');
                    $path =
                        $year
                        . '/publication_'
                        . $publicationID
                        . '/'
                        . $fileName;


                    uploadPdfsConcurrently([

                        [
                            'localFile' =>
                                $publicationFile['tmp_name'],

                            'storagePath' =>
                                $path,

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
                         WHERE
                            "publicationID" = :publicationID'
                    )->execute([

                        ':year' =>$year,
                        ':volume' =>$volume,
                        ':number' =>$number,
                        ':publicationPDF' =>$path,
                        ':publicationID' =>$publicationID
                    ]);

                } 
                
                else {
                    $pdo->prepare(
                        'UPDATE "PublicationIssue"
                         SET
                            "year" = :year,
                            "volume" = :volume,
                            "number" = :number
                         WHERE
                            "publicationID" = :publicationID'
                    )->execute([

                        ':year' =>$year,
                        ':volume' =>$volume,
                        ':number' =>$number,
                        ':publicationID' =>$publicationID
                    ]);
                }

                /*UPDATE ARTICLES*/
                $articleList = normalizeArticleData($articleList);
                foreach ($articleList as $index => $article) {
                    $title = trim((string) ($article['title']?? ''));

                    if ($title === '') {
                        throw new Exception('Title for Article '. ($index + 1). ' is required.');
                    }

                    $pdfInput =
                        $_FILES[
                            'pdf_' . $index
                        ] ?? null;


                    $pdfPath =
                        trim(
                            (string) (
                                $article['existingPdf']
                                ?? ''
                            )
                        );

                    if ($pdfPath !== '') {
                        $pdfPath = normalizeStoragePath($pdfPath);
                    }


                    if (is_array($pdfInput)&& !empty($pdfInput['tmp_name'])) {
                        validatePdf($pdfInput,'PDF for Article '. ($index + 1));

                        $pdfPath =
                            $year
                            . '/publication_'
                            . $publicationID
                            . '/'
                            . createStorageFilename(
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
                                    'Article '
                                    . ($index + 1)
                                    . ' PDF'
                            ]
                        ]);
                    }


                    /*Keep your existing behavior for drafts*/
                    if ($pdfPath === '') {

                        $pdfPath =
                            $existingIssue[
                                'publicationPDF'
                            ] ?? '';
                    }

                    if ($pdfPath !== '') {
                        $pdfPath = normalizeStoragePath($pdfPath);
                    }


                    $journalID =
                        $article['journalID']
                        ?? null;


                    if ($journalID !== null&& $journalID > 0) {

                        $update =
                            $pdo->prepare(
                                'UPDATE "JournalArticle"
                                 SET
                                    "title" = :title,
                                    "journalPDF" = :journalPDF
                                 WHERE
                                    "journalID" = :journalID'
                            );


                        $update->execute([

                            ':title' => $title,
                            ':journalPDF' => $pdfPath,
                            ':journalID' => $journalID
                        ]);

                        saveArticleAuthors($pdo,(int) $journalID,$article['authors']?? []);
                    } 
                    
                    else {
                        $insert =
                            $pdo->prepare(
                                'INSERT INTO "JournalArticle"
                                    ("title",
                                     "journalPDF",
                                     "publicationID")
                                 VALUES
                                    (:title,
                                     :journalPDF,
                                     :publicationID)
                                 RETURNING "journalID"'
                            );


                        $insert->execute([
                            ':title' => $title,
                            ':journalPDF' => $pdfPath,
                            ':publicationID' => $publicationID
                        ]);

                        $newID = $insert->fetchColumn();

                        if ($newID !== false) {
                            saveArticleAuthors($pdo,(int) $newID,$article['authors']?? []);
                        }
                    }
                }
            }


            sendResponse(
                true,
                'Draft updated successfully.',
                [
                    'publicationID' =>
                        $publicationID
                ]
            );

            break;


        case 'publish':

            $publicationID = filter_input(INPUT_POST,'publicationID',FILTER_VALIDATE_INT);

            if ($publicationID === false|| $publicationID === null|| $publicationID <= 0) {
                sendResponse(false,'Invalid publication ID.',[],400);
            }

            publishExistingDraft($pdo,(int) $publicationID);
            sendResponse(true,'Draft published successfully.',
                [
                    'publicationID' =>
                        $publicationID
                ]
            );

            break;


        case 'delete':
            $publicationID =filter_input(INPUT_POST,'publicationID',FILTER_VALIDATE_INT);

            if ($publicationID === false|| $publicationID === null|| $publicationID <= 0) {
                sendResponse(false,'Invalid publication ID.',[],400);
            }

            deleteIssue($pdo,(int) $publicationID);
            sendResponse(true,'Issue deleted successfully.',[]);
            break;


        case 'deleteArticle':
            $journalID =filter_input(INPUT_POST,'journalID',FILTER_VALIDATE_INT);
            if ($journalID === false|| $journalID === null|| $journalID <= 0) {
                sendResponse(false,'Invalid journal ID.',[],400);
            }

            $pdfStatement = $pdo->prepare(
                'SELECT "journalPDF" FROM "JournalArticle" WHERE "journalID" = :journalID LIMIT 1'
            );
            $pdfStatement->execute([
                ':journalID' => $journalID
            ]);


            $pdfPath =$pdfStatement->fetchColumn();
            $journalArticle =new JournalArticle();
            $journalArticle->removeJournal($pdo,(int) $journalID);

            if (!empty($pdfPath)) {
                try {
                    deletePdf((string) $pdfPath);
                } 
                
                catch (Throwable $e) {
                    error_log('Article PDF cleanup error: '. $e->getMessage());
                }
            }

            sendResponse(true,'Article removed successfully.',[]);
            break;

        case 'view':
            $journalID = filter_input(INPUT_GET,'journalID',FILTER_VALIDATE_INT);
            $publicationID =filter_input(INPUT_GET,'publicationID',FILTER_VALIDATE_INT);

            if ($journalID !== false&& $journalID !== null&& $journalID > 0) {
                sendResponse(true,'Journal article retrieved successfully.',getArticlePayload($pdo,(int) $journalID));
            }

            if ($publicationID !== false&& $publicationID !== null&& $publicationID > 0) {
                sendResponse(true,'Issue retrieved successfully.',getIssuePayload($pdo,(int) $publicationID));
            }

            sendResponse(false,'Invalid journal ID or publication ID.',[],400);
            break;

        default:
            sendResponse(false,'Invalid action.',[],400);
            break;
    }
} 

catch (Throwable $e) {
    error_log('Manage Journal API Error: '. $e->getMessage());
    sendResponse(false,$e->getMessage(),[],500);
}