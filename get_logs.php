<?php
$ch = curl_init("https://bms-backend-production-d0fe.up.railway.app/api/v1/debug-logs");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
file_put_contents("railway_logs.txt", $res);

