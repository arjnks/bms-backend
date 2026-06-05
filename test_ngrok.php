<?php
$url = "https://billing.leopharma.tech/API/announcements/customer_details.php";

echo "Fetching Page 1...\n";
$start = microtime(true);
$ch = curl_init($url . "?page=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["ngrok-skip-browser-warning: true", "Connection: close"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$res1 = curl_exec($ch);
echo "Time: " . round(microtime(true) - $start, 2) . "s, HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ", Error: " . curl_error($ch) . "\n";
curl_close($ch);

echo "Fetching Page 2...\n";
$start = microtime(true);
$ch = curl_init($url . "?page=2");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["ngrok-skip-browser-warning: true", "Connection: close"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$res2 = curl_exec($ch);
echo "Time: " . round(microtime(true) - $start, 2) . "s, HTTP " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . ", Error: " . curl_error($ch) . "\n";
curl_close($ch);

