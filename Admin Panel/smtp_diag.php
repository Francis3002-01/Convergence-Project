<?php
/**
 * TEMPORARY diagnostic. DELETE immediately after use.
 * Prints no secrets: environment variables show only present/empty/missing.
 */

header('Content-Type: text/plain; charset=utf-8');

function line(string $s): void { echo $s . "\n"; }

/* 1. Environment variables: presence only, never values (except non-secret host/port) */
line("== 1. Environment variables ==");
foreach (['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'] as $k) {
    $g = getenv($k);
    $e = $_ENV[$k] ?? null;
    $state = ($g === false && $e === null) ? 'MISSING'
           : ((string)($g !== false ? $g : $e) === '' ? 'EMPTY' : 'present');
    $extra = '';
    if ($state === 'present' && in_array($k, ['MAIL_HOST', 'MAIL_PORT'], true)) {
        $extra = ' = ' . ($g !== false ? $g : $e);
    }
    line(sprintf("%-18s getenv:%-7s \$_ENV:%-7s -> %s%s",
        $k, $g === false ? 'no' : 'yes', $e === null ? 'no' : 'yes', $state, $extra));
}

/* 2. PHP / OpenSSL / CA certificates */
line("\n== 2. PHP, OpenSSL, CA certificates ==");
line("PHP version: " . PHP_VERSION);
line("openssl extension: " . (extension_loaded('openssl') ? 'loaded (' . OPENSSL_VERSION_TEXT . ')' : 'NOT LOADED'));
$caFile = ini_get('openssl.cafile') ?: '';
$paths  = openssl_get_cert_locations();
line("openssl.cafile ini: " . ($caFile !== '' ? $caFile : '(unset)'));
line("default cert file: " . ($paths['default_cert_file'] ?? '?') . ' exists=' . (file_exists($paths['default_cert_file'] ?? '') ? 'yes' : 'no'));
line("default cert dir:  " . ($paths['default_cert_dir'] ?? '?') . ' exists=' . (is_dir($paths['default_cert_dir'] ?? '') ? 'yes' : 'no'));
line("/etc/ssl/certs/ca-certificates.crt exists=" . (file_exists('/etc/ssl/certs/ca-certificates.crt') ? 'yes' : 'no'));

/* 3. DNS */
line("\n== 3. DNS for smtp.gmail.com ==");
$ips = gethostbynamel('smtp.gmail.com');
line($ips ? 'Resolved: ' . implode(', ', $ips) : 'DNS FAILED (no A records returned)');

/* 4. Raw TCP: SMTP ports vs HTTPS (443 as a control) */
line("\n== 4. Raw TCP connectivity ==");
$targets = [
    ['smtp.gmail.com', 465, 'Gmail SMTPS'],
    ['smtp.gmail.com', 587, 'Gmail STARTTLS'],
    ['smtp.gmail.com', 25,  'SMTP (port 25)'],
    ['www.google.com', 443, 'HTTPS control (should work)'],
];
foreach ($targets as [$host, $port, $label]) {
    $t0 = microtime(true);
    $s  = @fsockopen($host, $port, $errno, $errstr, 10);
    $dt = round(microtime(true) - $t0, 2);
    if ($s) {
        $banner = '';
        if ($port === 587 || $port === 25) {
            stream_set_timeout($s, 5);
            $banner = ' | banner: ' . trim((string)fgets($s, 200));
        }
        fclose($s);
        line("{$host}:{$port} ({$label}) CONNECTED in {$dt}s{$banner}");
    } else {
        line("{$host}:{$port} ({$label}) FAILED in {$dt}s  errno={$errno} err=" . $errstr);
    }
}

/* 5. TLS handshake on 465 (only meaningful if TCP connected) */
line("\n== 5. TLS handshake smtp.gmail.com:465 ==");
$ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
$s = @stream_socket_client('ssl://smtp.gmail.com:465', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
if ($s) {
    line("TLS OK. Server greeting: " . trim((string)fgets($s, 200)));
    fclose($s);
} else {
    line("TLS FAILED errno={$errno} err=" . $errstr);
}

line("\nDone. DELETE smtp_diag.php now.");