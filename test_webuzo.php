<?php
// Quick debug script - run this on your Paymenter server:
// php test_webuzo.php

$host = 'https://web.veltrixdev.tech:2005';
$apiuser = 'root';
$apikey = 'b3ISmaHQvwdtScf4vBQiNL0vGnxRfbTf';

$url = $host . '/index.php?act=users&api=json&apiuser=' . urlencode($apiuser) . '&apikey=' . urlencode($apikey) . '&skip_callback=1';

echo "Testing URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Softaculous');
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpcode\n";
if ($error) {
    echo "cURL Error: $error\n";
} else {
    echo "Response (first 500 chars):\n";
    echo substr($response, 0, 500) . "\n";

    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "\nJSON decoded OK - keys: " . implode(', ', array_keys($decoded)) . "\n";
    } else {
        echo "\nJSON decode FAILED\n";
    }
}
