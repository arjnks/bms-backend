<?php
require "vendor/autoload.php";
use Illuminate\Support\Facades\Http;
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$response = Http::get("https://billing.leopharma.tech/API/announcements/bill_master_acc1.php?page=1");
$data = $response->json();
$count = 0;
foreach ($data["data"] as $bill) {
    $bn = $bill["billno"];
    if (strpos($bn, "/2526/") !== false) {
        preg_match("/(\d+)\D*$/", $bn, $matches);
        $num = $matches[1];
        echo "Testing $bn (numeric $num)...\n";
        $det = Http::asMultipart()->post("https://billing.leopharma.tech/API/announcements/bill_details.php", ["billno" => $num])->json();
        if (isset($det["status"]) && $det["status"] === "empty") {
            echo " -> EMPTY\n";
        } else if (isset($det["data"])) {
            echo " -> SUCCESS: " . count($det["data"]) . " items\n";
        } else {
            echo " -> " . json_encode($det) . "\n";
        }
        $count++;
        if ($count >= 5) break;
    }
}
if ($count == 0) echo "No 2526 bills on page 1.\n";

