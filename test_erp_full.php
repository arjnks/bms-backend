<?php
require "vendor/autoload.php";
use Illuminate\Support\Facades\Http;
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$page = 1;
$found = false;
while (true) {
    $response = Http::get("https://billing.leopharma.tech/API/announcements/bill_master_acc1.php?page=$page");
    $data = $response->json();
    if (empty($data["data"])) break;
    
    foreach ($data["data"] as $bill) {
        $bn = $bill["BN"] ?? $bill["invoice_no"] ?? $bill["billno"] ?? $bill["BILLNO"] ?? "";
        if (strpos(strtolower($bn), "553624") !== false) {
            echo "FOUND ON PAGE $page!\n";
            echo json_encode($bill, JSON_PRETTY_PRINT) . "\n";
            $found = true;
            break 2;
        }
    }
    $page++;
}

if (!$found) echo "NOT FOUND on any page (checked up to page " . ($page - 1) . ")\n";

