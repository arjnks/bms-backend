<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://192.168.0.186:8080/API/announcements/bill_master.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, ['cucode' => '', 'from_date' => date('Y-m-d'), 'to_date' => date('Y-m-d')]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
$json = json_decode($res, true);
echo isset($json['data']) ? count($json['data']) : 'No data or error: ' . $res;
