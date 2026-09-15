<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use GeoIp2\Database\Reader;

header('Content-Type: application/json; charset=utf-8');


/*
|--------------------------------------------------------------------------
| Load environment variables
|--------------------------------------------------------------------------
*/

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$supabaseUrl = $_ENV['SUPABASE_URL'] ?? '';
$supabaseKey = $_ENV['SUPABASE_SECRET_KEY'] ?? '';

if ($supabaseUrl === '' || $supabaseKey === '') {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Supabase environment variables are missing.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| JSON response helper
|--------------------------------------------------------------------------
*/

function sendJsonResponse(
    bool $success,
    string $message,
    array $data = [],
    int $statusCode = 200
): void {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Check if IP is local/private
|--------------------------------------------------------------------------
*/

function isLocalOrPrivateIp(string $ip): bool
{
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }

    return filter_var(
        $ip,
        FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE |
        FILTER_FLAG_NO_RES_RANGE
    ) === false;
}


/*
|--------------------------------------------------------------------------
| Get visitor IP
|--------------------------------------------------------------------------
*/

function getClientIp(): string
{
    /*
     * Optional testing:
     *
     * track_download.php?test_ip=8.8.8.8
     */
    if (
        isset($_GET['test_ip']) &&
        filter_var(
            $_GET['test_ip'],
            FILTER_VALIDATE_IP
        )
    ) {
        return $_GET['test_ip'];
    }


    /*
     * Cloudflare IP
     */
    if (
        isset($_SERVER['HTTP_CF_CONNECTING_IP']) &&
        filter_var(
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            FILTER_VALIDATE_IP
        )
    ) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }


    /*
     * X-Forwarded-For
     */
    elseif (
        isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    ) {
        $forwardedIps = explode(
            ',',
            $_SERVER['HTTP_X_FORWARDED_FOR']
        );

        $ip = trim($forwardedIps[0]);

        if (
            !filter_var(
                $ip,
                FILTER_VALIDATE_IP
            )
        ) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
    }


    /*
     * Client IP
     */
    elseif (
        isset($_SERVER['HTTP_CLIENT_IP']) &&
        filter_var(
            $_SERVER['HTTP_CLIENT_IP'],
            FILTER_VALIDATE_IP
        )
    ) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }


    /*
     * Normal server IP
     */
    else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }


    /*
     * Local/private IPs cannot be geolocated.
     *
     * Use 8.8.8.8 during local testing.
     */
    if ($ip === '' ||isLocalOrPrivateIp($ip)) {
        return '8.8.8.8';
    }

    return $ip;
}


/*
|--------------------------------------------------------------------------
| Normalize continent name
|--------------------------------------------------------------------------
*/

function normalizeContinentName(string $name): string
{
    $name = trim($name);

    /*
     * MaxMind can return "Australia".
     * The database uses "Oceania".
     */
    if ($name === 'Australia') {
        return 'Oceania';
    }

    return $name;
}


/*
|--------------------------------------------------------------------------
| Supabase REST API request
|--------------------------------------------------------------------------
*/

function supabaseRequest(
    string $method,
    string $endpoint,
    array $payload = []
): array {
    global $supabaseUrl, $supabaseKey;

    $url =
        rtrim($supabaseUrl, '/') .
        '/rest/v1/' .
        $endpoint;

    $headers = [
        'apikey: ' . $supabaseKey,
        'Authorization: Bearer ' . $supabaseKey,
        'Accept: application/json',
        'Content-Type: application/json'
    ];


    /*
     * Return inserted/updated records.
     */
    if ($method !== 'GET') {
        $headers[] = 'Prefer: return=representation';
    }


    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15
    ]);


    /*
     * Add JSON payload for write requests.
     */
    if (
        in_array(
            $method,
            ['POST', 'PATCH', 'PUT'],
            true
        )
    ) {
        $jsonPayload = json_encode($payload);

        if ($jsonPayload === false) {
            curl_close($ch);

            throw new RuntimeException(
                'Unable to encode request data as JSON.'
            );
        }

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            $jsonPayload
        );
    }


    /*
     * Execute request.
     */
    $response = curl_exec($ch);


    /*
     * cURL error.
     */
    if ($response === false) {
        $error = curl_error($ch);

        curl_close($ch);

        throw new RuntimeException(
            'cURL error: ' . $error
        );
    }


    /*
     * Get HTTP status.
     */
    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);


    /*
     * Supabase returned an error.
     */
    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        throw new RuntimeException(
            'Supabase HTTP ' .
            $httpCode .
            ': ' .
            $response
        );
    }


    /*
     * Empty successful response.
     */
    if (trim($response) === '') {
        return [];
    }


    /*
     * Decode Supabase JSON.
     */
    $decoded = json_decode(
        $response,
        true
    );

    if (!is_array($decoded)) {
        throw new RuntimeException(
            'Invalid Supabase response: ' .
            $response
        );
    }

    return $decoded;
}


/*
|--------------------------------------------------------------------------
| Get continent ID from MaxMind
|--------------------------------------------------------------------------
*/

function getContinentIdFromGeoIp(
    string $ip,
    array $continentMap
): ?int {
    $databasePath =
        __DIR__ .
        '/geoip/GeoLite2-Country.mmdb';


    /*
     * MaxMind database missing.
     */
    if (!file_exists($databasePath)) {
        return null;
    }


    try {

        $reader = new Reader(
            $databasePath
        );

        $record = $reader->country(
            $ip
        );

        $continentName =
            $record->continent->name ?? null;

        $reader->close();


        if (!$continentName) {
            return null;
        }


        $continentName =
            normalizeContinentName(
                $continentName
            );


        return $continentMap[
            $continentName
        ] ?? null;

    } catch (Throwable $e) {

        return null;
    }
}


/*
|--------------------------------------------------------------------------
| Main download tracking process
|--------------------------------------------------------------------------
*/

try {

    /*
     * Read JSON body.
     */
    $rawInput = file_get_contents(
        'php://input'
    );

    $jsonPayload = [];


    if (
        $rawInput !== false &&
        trim($rawInput) !== ''
    ) {
        $decoded = json_decode(
            $rawInput,
            true
        );

        if (is_array($decoded)) {
            $jsonPayload = $decoded;
        }
    }


    /*
     * Get journal ID.
     *
     * Supports JSON, POST, and GET.
     */
    $journalID =
        $jsonPayload['journalID']
        ?? $_POST['journalID']
        ?? $_GET['journalID']
        ?? null;


    $journalID = filter_var(
        $journalID,
        FILTER_VALIDATE_INT
    );


    /*
     * Validate journal ID.
     */
    if (
        $journalID === false ||
        $journalID <= 0
    ) {
        sendJsonResponse(
            false,
            'Invalid or missing journal ID.',
            [
                'receivedJournalID' => $journalID
            ],
            400
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Get continents from Supabase
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Do not put double quotes around the table name.
    |
    */

    $continents = supabaseRequest(
        'GET',
        'Continent?select=continentID,continentName'
    );


    /*
     * Build continent name => ID map.
     */
    $continentMap = [];


    foreach ($continents as $continent) {

        if (
            !isset($continent['continentID']) ||
            !isset($continent['continentName'])
        ) {
            continue;
        }


        $name = normalizeContinentName(
            $continent['continentName']
        );


        $continentMap[$name] =
            (int) $continent['continentID'];
    }


    /*
    |--------------------------------------------------------------------------
    | Determine visitor IP
    |--------------------------------------------------------------------------
    */

    $visitorIp = getClientIp();


    /*
    |--------------------------------------------------------------------------
    | Determine continent
    |--------------------------------------------------------------------------
    */

    $continentID =
        getContinentIdFromGeoIp(
            $visitorIp,
            $continentMap
        );


    /*
    |--------------------------------------------------------------------------
    | Prepare download record
    |--------------------------------------------------------------------------
    */

    $downloadRecord = [
        'journalID' => $journalID,
        'continentID' => $continentID
    ];


    /*
    |--------------------------------------------------------------------------
    | Insert download record
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    | Do not put double quotes around the table name.
    |
    */

    $insertedDownload = supabaseRequest(
        'POST',
        'Download',
        $downloadRecord
    );


    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    sendJsonResponse(
        true,
        'Download recorded successfully.',
        [
            'journalID' => $journalID,
            'continentID' => $continentID,
            'ip' => $visitorIp,
            'download' => $insertedDownload
        ],
        200
    );


} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | Error
    |--------------------------------------------------------------------------
    */

    sendJsonResponse(
        false,
        'Unable to record download.',
        [
            'error' => $e->getMessage(),
            'journalID' => $journalID ?? null
        ],
        500
    );
}