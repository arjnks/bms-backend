<?php
$ch = curl_init("https://bms-backend-production-d0fe.up.railway.app/api/v1/debug/24295");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
echo curl_exec($ch);

