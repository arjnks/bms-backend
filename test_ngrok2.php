<?php
$url = "https://billing.leopharma.tech/API/announcements/customer_details.php";
$ch = curl_init($url . "?page=2");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["ngrok-skip-browser-warning: true", "Connection: close"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$res2 = curl_exec($ch);
$data = json_decode($res2, true);
echo "Status: " . ($data["status"] ?? "missing") . "\n";
echo "Total Pages: " . ($data["total_pages"] ?? "missing") . "\n";
echo "Data count: " . (isset($data["data"]) ? count($data["data"]) : "missing") . "\n";

