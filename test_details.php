<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://192.168.0.186:8080/API/announcements/bill_details.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, ["billno" => "96609"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "Result for 96609: " . substr($res, 0, 200) . "\n";

$ch2 = curl_init();
curl_setopt($ch2, CURLOPT_URL, "http://192.168.0.186:8080/API/announcements/bill_details.php");
curl_setopt($ch2, CURLOPT_POST, 1);
curl_setopt($ch2, CURLOPT_POSTFIELDS, ["billno" => "LPH/2627/96609"]);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$res2 = curl_exec($ch2);
curl_close($ch2);
echo "Result for LPH/2627/96609: " . substr($res2, 0, 200) . "\n";

