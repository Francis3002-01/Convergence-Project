<?php
//Test to see if maxmind geolite works for continent

// Expected output:
// IP Address: 8.8.8.8
// Country: United States
// Country Code: US
// Continent: North America
// Continent Code: NA

require_once __DIR__ . '/vendor/autoload.php';

use GeoIp2\Database\Reader;

try {
    // Load the GeoLite2 database
    $reader = new Reader(__DIR__ . '/geoip/GeoLite2-Country.mmdb');

    // Test IP address
    $ip = '8.8.8.8';

    // Look up the IP
    $record = $reader->country($ip);

    echo "IP Address: " . $ip . "<br>";
    echo "Country: " . $record->country->name . "<br>";
    echo "Country Code: " . $record->country->isoCode . "<br>";
    echo "Continent: " . $record->continent->name . "<br>";
    echo "Continent Code: " . $record->continent->code . "<br>";

    $reader->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}