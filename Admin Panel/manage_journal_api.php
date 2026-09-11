<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/JournalArticle.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$supabaseUrl = trim($_ENV['SUPABASE_URL'] ?? '');
$supabaseKey = trim($_ENV['SUPABASE_SECRET_KEY'] ?? '');
$bucket = trim($_ENV['SUPABASE_BUCKET'] ?? '');

if ($supabaseUrl === '') 
    throw new Exception('SUPABASE_URL is not configured.');

if ($supabaseKey === '') 
    throw new Exception('SUPABASE_SECRET_KEY is not configured.');

if ($bucket === '') 
    throw new Exception('SUPABASE_BUCKET is not configured.');

//JSON response
function sendResponse(bool $success, string $message = '', array $data = [], int $status = 200): never{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Normalize storage path
function normalizeStoragePath(string $storagePath): string{

    global $bucket;
    $storagePath = trim($storagePath);

    if ($storagePath === '') 
        throw new Exception('The storage path is empty.');

    if (filter_var($storagePath, FILTER_VALIDATE_URL)) {
        $parsedPath = parse_url($storagePath, PHP_URL_PATH);

        if (!is_string($parsedPath)) {
            throw new Exception('Unable to extract the storage path from the PDF URL.');
        }

        $storagePath = $parsedPath;
        $objectMarker = '/storage/v1/object/';
        $markerPosition = strpos($storagePath, $objectMarker);

        if ($markerPosition !== false) {
            $storagePath = substr($storagePath,$markerPosition + strlen($objectMarker));
            $storagePath = preg_replace('#^(public|sign)/#','',$storagePath);
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

//Create public PDF URL
function createPublicPdfUrl(string $storagePath): string{
    global $supabaseUrl;
    global $bucket;

    $bucketName = trim($bucket, '/');

    if ($bucketName === '') {
        throw new Exception('Supabase storage bucket is not configured.');
    }

    $storagePath = normalizeStoragePath($storagePath);
    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode('/', $storagePath)
        )
    );

    return rtrim($supabaseUrl, '/') .
        '/storage/v1/object/public/' .
        rawurlencode($bucketName) .
        '/' .
        $encodedPath;
}

//Upload one PDF
function uploadPdf(string $localFile, string $storagePath): void{
    global $supabaseUrl;
    global $supabaseKey;
    global $bucket;

    $fileContents = file_get_contents($localFile);

    if ($fileContents === false) {
        throw new Exception('Unable to read PDF file.');
    }

    $storagePath = normalizeStoragePath($storagePath);
    $encodedPath = implode(
        '/',
        array_map(
            'rawurlencode',
            explode('/', $storagePath)
        )
    );

    $url = rtrim($supabaseUrl, '/') .
        '/storage/v1/object/' .
        rawurlencode($bucket) .
        '/' .
        $encodedPath;

    $ch = curl_init($url);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fileContents,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $supabaseKey,
                'apikey: ' . $supabaseKey,
                'Content-Type: application/pdf',
                'Content-Length: ' . strlen($fileContents)
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_TCP_KEEPALIVE => 1
        ]
    );

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        throw new Exception(
            'PDF upload failed: ' .
                ($curlError ?: $response)
        );
    }
}

//Upload multiple PDFs concurrently
function uploadPdfsConcurrently(array $uploads): void
{
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
            $localFile = $upload['localFile'];
            $storagePath = normalizeStoragePath(
                $upload['storagePath']
            );

            $fileContents = file_get_contents($localFile);

            if ($fileContents === false) {
                throw new Exception(
                    'Unable to read PDF file.'
                );
            }

            $encodedPath = implode(
                '/',
                array_map(
                    'rawurlencode',
                    explode('/', $storagePath)
                )
            );

            $url = rtrim($supabaseUrl, '/') .
                '/storage/v1/object/' .
                rawurlencode($bucket) .
                '/' .
                $encodedPath;

            $ch = curl_init($url);

            curl_setopt_array(
                $ch,
                [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $fileContents,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $supabaseKey,
                        'apikey: ' . $supabaseKey,
                        'Content-Type: application/pdf',
                        'Content-Length: ' . strlen($fileContents)
                    ],
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 300,
                    CURLOPT_TCP_KEEPALIVE => 1
                ]
            );

            curl_multi_add_handle(
                $multiHandle,
                $ch
            );

            $handles[$index] = $ch;
        }

        $running = null;

        do {
            $status = curl_multi_exec(
                $multiHandle,
                $running
            );

            if ($running) {
                curl_multi_select(
                    $multiHandle,
                    1.0
                );
            }
        }while ($running && $status === CURLM_OK);

        foreach ($handles as $index => $ch) {
            $response = curl_multi_getcontent($ch);

            $httpCode = curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            $curlError = curl_error($ch);

            if ($response === false || $httpCode < 200 || $httpCode >= 300) {
                throw new Exception(
                    'PDF upload failed for ' .
                        $uploads[$index]['description'] .
                        ': ' .
                        ($curlError ?: $response)
                );
            }
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }
    } 
    
    finally {
        curl_multi_close($multiHandle);
    }
}

//Delete PDF
function deletePdf(string $storagePath): void
{
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
            explode('/', $storagePath)
        )
    );

    $url = rtrim($supabaseUrl, '/') .
        '/storage/v1/object/' .
        rawurlencode($bucket) .
        '/' .
        $encodedPath;

    $ch = curl_init($url);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $supabaseKey,
                'apikey: ' . $supabaseKey
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60
        ]
    );

    curl_exec($ch);
    curl_close($ch);
}

//Validate uploaded PDF
function validatePdf(array $file, string $description): void
{

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception($description . ' upload failed.');
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new Exception($description . ' is invalid.');
    }

    $mimeType = mime_content_type($file['tmp_name']
    );

    if ($mimeType !== 'application/pdf') {
        throw new Exception($description . ' must be a PDF.');
    }

    $maxFileSize = 40 * 1024 * 1024;

    if ($file['size'] > $maxFileSize) {
        throw new Exception($description . ' exceeds the 40 MB limit.');
    }
}

//Create safe storage filename
function createStorageFilename(string $originalName, string $prefix): string{

    $originalName = basename($originalName);
    $safeFileName = preg_replace(
        '/[^A-Za-z0-9._-]/',
        '_',
        $originalName
    );

    return $prefix .
        '_' .
        uniqid('', true) .
        '_' .
        $safeFileName;
}

// Create database connection
$database = new Database();
$pdo = $database->getConnection();

// Create journal object
$journalModel = new JournalArticle();

try {

    $action =
        $_GET['action'] ??
        $_POST['action'] ??
        '';

    switch ($action) {

        case 'add':
            $year = filter_input(INPUT_POST,'year',FILTER_VALIDATE_INT);
            $volume = filter_input(INPUT_POST,'volume',FILTER_VALIDATE_INT);
            $number = filter_input(INPUT_POST,'number',FILTER_VALIDATE_INT);

            if ($year === false || $year === null ||$volume === false || $volume === null ||$number === false || $number === null) {
                sendResponse(
                    false,
                    'Invalid publication issue information.',
                    [],
                    400
                );
            }

            if (!isset($_FILES['publicationPDF'])) {
                sendResponse(
                    false,
                    'Publication issue PDF is required.',
                    [],
                    400
                );
            }

            $articlesJson = $_POST['articles'] ?? '';

            $articles = json_decode(
                $articlesJson,
                true
            );

            if (!is_array($articles) || count($articles) === 0) {
                sendResponse(
                    false,
                    'At least one article is required.',
                    [],
                    400
                );
            }

            $publicationID =
                $journalModel->addJournal(
                    $pdo,
                    (int) $year,
                    (int) $volume,
                    (int) $number,
                    $_FILES['publicationPDF'],
                    $articles
                );

            sendResponse(
                true,
                'Publication issue and articles saved successfully.',
                [
                    'publicationID' => $publicationID
                ]
            );


        case 'update':

            $journalID = filter_input(
                INPUT_POST,
                'journalID',
                FILTER_VALIDATE_INT
            );

            $year = filter_input(
                INPUT_POST,
                'year',
                FILTER_VALIDATE_INT
            );

            $volume = filter_input(
                INPUT_POST,
                'volume',
                FILTER_VALIDATE_INT
            );

            $number = filter_input(
                INPUT_POST,
                'number',
                FILTER_VALIDATE_INT
            );

            $title = trim(
                $_POST['title'] ?? ''
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

            if (
                $year === false || $year === null ||
                $volume === false || $volume === null ||
                $number === false || $number === null
            ) {
                sendResponse(
                    false,
                    'Invalid publication issue information.',
                    [],
                    400
                );
            }

            if ($title === '') {
                sendResponse(
                    false,
                    'Article title is required.',
                    [],
                    400
                );
            }

            $authors = null;

            if (isset($_POST['authors'])) {

                $authors = json_decode(
                    $_POST['authors'],
                    true
                );

                if (!is_array($authors)) {
                    sendResponse(
                        false,
                        'Invalid authors data.',
                        [],
                        400
                    );
                }
            }

            $journalPdf =
                $_FILES['journalPDF'] ?? null;

            $publicationPdf =
                $_FILES['publicationPDF'] ?? null;

            if (
                $journalPdf !== null &&
                (
                    !isset($journalPdf['error']) ||
                    $journalPdf['error'] === UPLOAD_ERR_NO_FILE
                )
            ) {
                $journalPdf = null;
            }

            if (
                $publicationPdf !== null &&
                (
                    !isset($publicationPdf['error']) ||
                    $publicationPdf['error'] === UPLOAD_ERR_NO_FILE
                )
            ) {
                $publicationPdf = null;
            }

            $journalModel->updateJournal(
                $pdo,
                (int) $journalID,
                (int) $year,
                (int) $volume,
                (int) $number,
                $title,
                $journalPdf,
                $publicationPdf,
                $authors
            );

            sendResponse(
                true,
                'Journal updated successfully.'
            );


        case 'list':

            $journals =
                $journalModel->listJournals(
                    $pdo
                );

            sendResponse(
                true,
                'Journals retrieved successfully.',
                [
                    'journals' => $journals
                ]
            );


        case 'view':

            $journalID = filter_input(
                INPUT_GET,
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

            $result =
                $journalModel->viewJournal(
                    $pdo,
                    (int) $journalID
                );

            if ($result === null) {
                sendResponse(
                    false,
                    'Journal article not found.',
                    [],
                    404
                );
            }

            $citation =
                $journalModel->generateCitation(
                    $result['article'],
                    $result['publication'],
                    $result['authors']
                );

            $pdfUrl = null;

            $journalPDF =
                trim(
                    $result['article']['journalPDF'] ??
                    ''
                );

            if ($journalPDF !== '') {

                $pdfUrl =
                    createPublicPdfUrl(
                        $journalPDF
                    );
            }

            sendResponse(
                true,
                'Journal retrieved successfully.',
                [
                    'article' =>
                        $result['article'],

                    'publication' =>
                        $result['publication'],

                    'authors' =>
                        $result['authors'],

                    'citation' =>
                        $citation,

                    'pdfUrl' =>
                        $pdfUrl
                ]
            );


        case 'delete':

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

            $journalModel->removeJournal(
                $pdo,
                (int) $journalID
            );

            sendResponse(
                true,
                'Journal deleted successfully.'
            );


        default:

            sendResponse(
                false,
                'Invalid action.',
                [],
                400
            );
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