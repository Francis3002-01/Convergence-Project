<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Dotenv\Dotenv;

/*Load .env*/
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

/*Supabase configuration*/
$supabaseUrl = trim($_ENV['SUPABASE_URL'] ?? '');
$supabaseKey = trim($_ENV['SUPABASE_SECRET_KEY'] ?? '');

if ($supabaseUrl === '') {
    sendResponse(false,'SUPABASE_URL is not configured',[],500);
}

if ($supabaseKey === '') {
    sendResponse(false,'SUPABASE_SECRET_KEY is not configured',[],500);
}

/*JSON response*/
function sendResponse(bool $success,string $message = '',array $data = [],int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        ...$data
    ]);
    exit;
}

/*Supabase REST request*/
function supabaseRequest(string $endpoint): array {
    global $supabaseUrl;
    global $supabaseKey;

    $url = rtrim($supabaseUrl, '/') .'/rest/v1/' .$endpoint;
    $ch = curl_init($url);

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'apikey: ' . $supabaseKey,
                'Authorization: Bearer ' . $supabaseKey,
                'Accept: application/json'
            ],

            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60
        ]
    );


    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    /*cURL error*/
    if ($response === false) {
        throw new Exception($curlError !== ''? $curlError: 'Supabase request failed.');
    }

    /*Decode JSON*/
    $data = json_decode($response,true);
    if (!is_array($data)) {
        throw new Exception('Supabase returned an invalid response.');
    }

    /*Supabase HTTP error*/
    if ($httpCode < 200 ||$httpCode >= 300) {

        $message =
            $data['message']
            ?? $data['error']
            ?? 'Supabase request failed.';

        throw new Exception(
            'Supabase HTTP ' .
            $httpCode .
            ': ' .
            $message
        );
    }


    return $data;
}

/*Get dashboard statistics*/
try {

    $journalID = filter_input(INPUT_GET,'journalID',FILTER_VALIDATE_INT);

    if ($journalID !== null && ($journalID === false || $journalID <= 0)) {
        sendResponse(false, 'Invalid journal ID.', [], 400);
    }

    /*
    |--------------------------------------------------------------------------
    | Get all continents
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Do not put double quotes around the table name.
    |
    */

    $continents = supabaseRequest('Continent?select=continentID,continentName&order=continentID.asc');
    
    /*Create continent lookup*/
    $continentCounts = [];
    foreach ($continents as $continent) {

        $continentID = (int) ($continent['continentID'] ?? 0);
        $continentName = trim((string) ($continent['continentName'] ?? ''));

        if ($continentID <= 0 ||$continentName === '') {
            continue;
        }

        $continentCounts[$continentID] = [
            'continentID' => $continentID,
            'continentName' => $continentName,
            'downloads' => 0
        ];
    }


    /*Get all downloads*/
    $downloadEndpoint =
        'Download?select=downloadID,continentID,journalID';

    if ($journalID !== null) {
        $downloadEndpoint .= '&journalID=eq.' . $journalID;
    }

    $downloads = supabaseRequest($downloadEndpoint);

    /*Get issues and their articles for the selector*/
    $issues = supabaseRequest('PublicationIssue?select=publicationID,year,volume,number&order=year.desc,volume.desc,number.desc');
    $articles = supabaseRequest('JournalArticle?select=journalID,title,publicationID&order=journalID.asc');

    $issueOptions = [];

    foreach ($issues as $issue) {
        $publicationID = (int) ($issue['publicationID'] ?? 0);

        if ($publicationID <= 0) {
            continue;
        }

        $issueOptions[$publicationID] = [
            'publicationID' => $publicationID,
            'year' => (int) ($issue['year'] ?? 0),
            'volume' => (int) ($issue['volume'] ?? 0),
            'number' => (int) ($issue['number'] ?? 0),
            'articles' => []
        ];
    }

    foreach ($articles as $article) {
        $publicationID = (int) ($article['publicationID'] ?? 0);
        $articleID = (int) ($article['journalID'] ?? 0);

        if ($publicationID <= 0 ||$articleID <= 0 ||!isset($issueOptions[$publicationID])) {
            continue;
        }

        $issueOptions[$publicationID]['articles'][] = [
            'journalID' => $articleID,
            'title' => trim((string) ($article['title'] ?? 'Untitled Article'))
        ];
    }


    /*Count downloads by continent*/
    foreach ($downloads as $download) {

        if (!isset($download['continentID']) ||$download['continentID'] === null ||$download['continentID'] === '') {
            continue;
        }

        $continentID = (int) ($download['continentID']);

        if (isset($continentCounts[$continentID])) {
            $continentCounts[
                $continentID
            ]['downloads']++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Total downloads
    |--------------------------------------------------------------------------
    |
    | Every row in Download represents one
    | successful download.
    |
    */
    $totalDownloads = count($downloads);

    /*Re-index continent array*/
    $continentData = array_values($continentCounts);

    /*Calculate identified downloads*/
    $identifiedDownloads = 0;
    
    foreach ($continentData as $continent) {
        $identifiedDownloads += $continent['downloads'];
    }

    /*Calculate percentages*/
    foreach ($continentData as &$continent) {

        if ($identifiedDownloads > 0) {
            $continent['percentage'] =
                round(
                    (
                        $continent['downloads']
                        /
                        $identifiedDownloads
                    ) * 100,
                    1
                );

        } 
        
        else {
            $continent['percentage'] = 0;
        }
    }

    unset($continent);

    /*Send dashboard data*/
    sendResponse(true,
        'Dashboard statistics loaded successfully.',
        [
            'totalDownloads' =>
                $totalDownloads,

            'identifiedDownloads' =>
                $identifiedDownloads,

            'issues' =>
                array_values($issueOptions),

            'continents' =>
                $continentData
        ]
    );


} 

catch (Throwable $e) {
    sendResponse(false,$e->getMessage(),[],500);
}