<?php
$url = "https://billing.leopharma.tech/API/announcements/bill_master.php";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ["cucode" => "0110025", "from_date" => "2020-01-01", "to_date" => "2026-12-31"]);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["ngrok-skip-browser-warning: true"]);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$res = curl_exec($ch);
$data = json_decode($res, true);
echo "Status: " . ($data["status"] ?? "missing") . "\n";
echo "Count: " . (isset($data["data"]) ? count($data["data"]) : 0) . " bills\n";
if(isset($data["data"]) && count($data["data"]) > 0) {
    print_r(array_slice($data["data"], 0, 2));
}

