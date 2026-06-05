<?php
$url = "https://billing.leopharma.tech/API/announcements/bill_master_acc1.php";
$found = false;
for($i=1; $i<=5; $i++) {
    $ch = curl_init($url . "?page=" . $i);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["ngrok-skip-browser-warning: true", "Connection: close"]);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $res = curl_exec($ch);
    $data = json_decode($res, true);
    if(isset($data["data"])) {
        foreach($data["data"] as $bill) {
            if($bill["cucode"] === "0110025") {
                echo "Found bill: " . $bill["billno"] . ", net: " . $bill["netamount"] . ", rcvd: " . $bill["amtreceived"] . ", settled: " . $bill["settled"] . "\n";
                $found = true;
            }
        }
    }
}
if(!$found) echo "Customer 0110025 has absolutely NO bills in the ERP unpaid bills API.\n";

