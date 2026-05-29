<?php
$ch = curl_init('http://192.168.0.186:8080/API/announcements/bill_master.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['cucode'=>'010311', 'from_date'=>'2020-01-01', 'to_date'=>'2030-01-01']);
echo substr(curl_exec($ch), 0, 500);
curl_close($ch);
