<?php
$url = "https://unknowing-relight-civic.ngrok-free.dev/API/announcements/bill_master.php";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["ngrok-skip-browser-warning: true"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Status: $status\nBody: " . substr($res, 0, 500) . "\n";

