<?php
$ch = curl_init("https://bms-backend-production-d0fe.up.railway.app/api/v1/customer/bills/24295");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "Status: $code\n";
echo "Response: $res\n";

