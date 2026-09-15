<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;


/*
|--------------------------------------------------------------------------
| Load .env
|--------------------------------------------------------------------------
*/

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();


/*
|--------------------------------------------------------------------------
| Supabase configuration
|--------------------------------------------------------------------------
*/

$supabaseUrl = trim(
    $_ENV['SUPABASE_URL'] ?? ''
);

$supabaseKey = trim(
    $_ENV['SUPABASE_SECRET_KEY'] ?? ''
);

if ($supabaseUrl === '') {
    sendResponse(
        false,
        'SUPABASE_URL is not configured.',
        [],
        500
    );
}

if ($supabaseKey === '') {
    sendResponse(
        false,
        'SUPABASE_SECRET_KEY is not configured.',
        [],
        500
    );
}


/*
|--------------------------------------------------------------------------
| JSON response
|--------------------------------------------------------------------------
*/

function sendResponse(
    bool $success,
    string $message = '',
    array $data = [],
    int $status = 200
): never {

    http_response_code($status);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode([
        'success' => $success,
        'message' => $message,
        ...$data
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Supabase REST request
|--------------------------------------------------------------------------
*/

function supabaseRequest(
    string $endpoint
): array {

    global $supabaseUrl;
    global $supabaseKey;

    $url =
        rtrim($supabaseUrl, '/') .
        '/rest/v1/' .
        $endpoint;


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


    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );


    $curlError = curl_error($ch);


    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | cURL error
    |--------------------------------------------------------------------------
    */

    if ($response === false) {

        throw new Exception(
            $curlError !== ''
                ? $curlError
                : 'Supabase request failed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Decode JSON
    |--------------------------------------------------------------------------
    */

    $data = json_decode(
        $response,
        true
    );


    if (!is_array($data)) {

        throw new Exception(
            'Supabase returned an invalid response.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Supabase HTTP error
    |--------------------------------------------------------------------------
    */

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {

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


/*
|--------------------------------------------------------------------------
| Get dashboard statistics
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Get all continents
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Do not put double quotes around the table name.
    |
    */

    $continents = supabaseRequest(
        'Continent?select=continentID,continentName&order=continentID.asc'
    );


    /*
    |--------------------------------------------------------------------------
    | Create continent lookup
    |--------------------------------------------------------------------------
    */

    $continentCounts = [];


    foreach ($continents as $continent) {

        $continentID = (int) (
            $continent['continentID'] ?? 0
        );

        $continentName = trim(
            (string) (
                $continent['continentName'] ?? ''
            )
        );


        if (
            $continentID <= 0 ||
            $continentName === ''
        ) {
            continue;
        }


        $continentCounts[$continentID] = [
            'continentID' => $continentID,

            'continentName' => $continentName,

            'downloads' => 0
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Get all downloads
    |--------------------------------------------------------------------------
    |
    | Download now contains:
    |
    | downloadID
    | continentID
    | journalID
    |
    */

    $downloads = supabaseRequest(
        'Download?select=downloadID,continentID,journalID'
    );


    /*
    |--------------------------------------------------------------------------
    | Count downloads by continent
    |--------------------------------------------------------------------------
    */

    foreach ($downloads as $download) {

        if (
            !isset($download['continentID']) ||
            $download['continentID'] === null ||
            $download['continentID'] === ''
        ) {
            continue;
        }


        $continentID = (int) (
            $download['continentID']
        );


        if (
            isset(
                $continentCounts[$continentID]
            )
        ) {

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

    $totalDownloads = count(
        $downloads
    );


    /*
    |--------------------------------------------------------------------------
    | Re-index continent array
    |--------------------------------------------------------------------------
    */

    $continentData = array_values(
        $continentCounts
    );


    /*
    |--------------------------------------------------------------------------
    | Calculate identified downloads
    |--------------------------------------------------------------------------
    */

    $identifiedDownloads = 0;


    foreach ($continentData as $continent) {

        $identifiedDownloads +=
            $continent['downloads'];
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate percentages
    |--------------------------------------------------------------------------
    */

    foreach (
        $continentData
        as &$continent
    ) {

        if (
            $identifiedDownloads > 0
        ) {

            $continent['percentage'] =
                round(
                    (
                        $continent['downloads']
                        /
                        $identifiedDownloads
                    ) * 100,
                    1
                );

        } else {

            $continent['percentage'] = 0;
        }
    }


    unset($continent);


    /*
    |--------------------------------------------------------------------------
    | Send dashboard data
    |--------------------------------------------------------------------------
    */

    sendResponse(
        true,
        'Dashboard statistics loaded successfully.',
        [
            'totalDownloads' =>
                $totalDownloads,

            'identifiedDownloads' =>
                $identifiedDownloads,

            'continents' =>
                $continentData
        ]
    );


} catch (Throwable $e) {

    sendResponse(
        false,
        $e->getMessage(),
        [],
        500
    );
}