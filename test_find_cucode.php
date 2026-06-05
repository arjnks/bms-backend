<?php
$url = "https://billing.leopharma.tech/API/announcements/bill_master_acc1.php";
for($i=1; $i<=3; $i++) {
    $ch = curl_init($url . "?page=" . $i);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["ngrok-skip-browser-warning: true"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $res = curl_exec($ch);
    if(strpos($res, "0110025") !== false) {
        echo "Found in page $i!\n";
        exit;
    }
}
echo "Not found in first 3 pages.\n";

