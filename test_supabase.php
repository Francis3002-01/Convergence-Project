<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$supabaseUrl = $_ENV['SUPABASE_URL'];
$supabaseKey = $_ENV['SUPABASE_SECRET_KEY'];
$bucket = $_ENV['SUPABASE_BUCKET'];

echo "<h2>Supabase Connection Test</h2>";

echo "<p><strong>SUPABASE_URL:</strong> " . htmlspecialchars($supabaseUrl) . "</p>";
echo "<p><strong>SUPABASE_BUCKET:</strong> " . htmlspecialchars($bucket) . "</p>";

$url = $supabaseUrl . "/storage/v1/bucket/" . urlencode($bucket);

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $supabaseKey,
    "apikey: " . $supabaseKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "<p style='color:red;'>cURL Error: " . curl_error($ch) . "</p>";
} else {
    echo "<p><strong>HTTP Status:</strong> " . $httpCode . "</p>";

    if ($httpCode === 200) {
        echo "<p style='color:green;'><strong>SUCCESS:</strong> Supabase URL, Secret Key, and Bucket are working.</p>";
    } else {
        echo "<p style='color:red;'><strong>FAILED:</strong> Something is wrong.</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

curl_close($ch);
