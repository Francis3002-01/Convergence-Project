<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/Classes/JournalArticle.php';

use Dotenv\Dotenv;

try {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $journalID = filter_input(INPUT_GET,'journalID',FILTER_VALIDATE_INT);

    if (!$journalID || $journalID <= 0) {
        http_response_code(400);
        exit('Invalid article ID.');
    }

    $database = new Database();
    $pdo = $database->getConnection();
    $journalArticle = new JournalArticle();
    $pdfPath = $journalArticle->downloadPDF($pdo,$journalID);

    if ($pdfPath === null || trim($pdfPath) === '') {
        http_response_code(404);
        exit('PDF not found.');
    }

    $supabaseUrl = trim((string) ($_ENV['SUPABASE_URL'] ?? ''));
    $bucketName = trim((string) ($_ENV['SUPABASE_BUCKET'] ?? ''));

    if ($supabaseUrl === '' || $bucketName === '') {
        http_response_code(500);
        exit('Supabase configuration is missing.');
    }

    /*
     * If database contains a complete URL,
     * use it directly.
     */
    if (filter_var($pdfPath, FILTER_VALIDATE_URL)) {
        $pdfUrl = $pdfPath;
    } 
    
    else {
        $pdfPath = trim($pdfPath);
        $pdfPath = ltrim($pdfPath, '/');

        /*
         * Remove bucket name if it is already
         * included in the stored path.
         */
        $bucketPrefix = trim($bucketName, '/') . '/';

        if (str_starts_with($pdfPath, $bucketPrefix)) {
            $pdfPath = substr($pdfPath,strlen($bucketPrefix));
        }

        /*
         * Encode each path segment.
         */
        $encodedPath = implode(
            '/',
            array_map('rawurlencode',explode('/', $pdfPath))
        );

        $pdfUrl =
            rtrim($supabaseUrl, '/') .
            '/storage/v1/object/public/' .
            rawurlencode(trim($bucketName, '/')) .
            '/' .
            $encodedPath;
    }

    /*
     * Retrieve PDF from Supabase.
     */
    $ch = curl_init($pdfUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $pdfContent = curl_exec($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $curlError = curl_error($ch);

    curl_close($ch);

    if ($pdfContent === false ||$httpCode < 200 ||$httpCode >= 300) {
        error_log('PDF download failed. HTTP ' .$httpCode .'. cURL error: ' .$curlError);
        http_response_code(404);
        exit('Unable to retrieve PDF.');
    }

    /*
     * Get article title for filename.
     */
    $stmt = $pdo->prepare('SELECT "title" FROM "JournalArticle" WHERE "journalID" = :journalID LIMIT 1');

    $stmt->execute([
        ':journalID' => $journalID
    ]);

    $title = $stmt->fetchColumn();
    $filename = trim((string) ($title ?: 'journal_article'));

    /*
     * Make filename safe.
     */
    $filename = preg_replace(
        '/[^\p{L}\p{N}\-_ ]+/u',
        '',
        $filename
    );

    $filename = preg_replace(
        '/\s+/',
        ' ',
        $filename
    );

    $filename = trim($filename);

    if ($filename === '') {
        $filename = 'journal_article';
    }

    $filename .= '.pdf';

    /*
     * Force browser to download the PDF.
     */
    header('Content-Type: application/pdf');

    header(
        'Content-Disposition: attachment; filename="' .
        str_replace('"', '', $filename) .
        '"'
    );

    header(
        'Content-Length: ' .
        strlen($pdfContent)
    );

    header(
        'Cache-Control: private, max-age=0, must-revalidate'
    );

    header('Pragma: public');

    echo $pdfContent;
    exit;

} catch (Throwable $e) {

    error_log(
        'download_article.php error: ' .
        $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to download the PDF.');
}