<?php
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['cucode' => '', 'from_date' => date('Y-m-d'), 'to_date' => date('Y-m-d')])
    ]
];
$ctx = stream_context_create($opts);
$res = file_get_contents('http://192.168.0.186:8080/API/announcements/bill_master.php', false, $ctx);
if ($res) {
    $json = json_decode($res, true);
    if (isset($json['data'])) {
        echo "COUNT FOR TODAY: " . count($json['data']) . "\n";
    } else {
        echo "NO DATA\n";
    }
} else {
    echo "FAILED\n";
}
