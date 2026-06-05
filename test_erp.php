<?php
require "vendor/autoload.php";
use Illuminate\Support\Facades\Http;
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$page = 1;
$found = false;
do {
    $response = Http::get("https://billing.leopharma.tech/API/announcements/bill_master_acc1.php?page=$page");
    $data = $response->json();
    if (empty($data["data"])) break;
    
    if ($page === 1) {
        echo "Keys in first bill: " . implode(", ", array_keys($data["data"][0])) . "\n";
    }
    
    foreach ($data["data"] as $bill) {
        $bn = $bill["BN"] ?? $bill["invoice_no"] ?? $bill["BILLNO"] ?? "";
        if (strpos($bn, "553624") !== false) {
            echo "FOUND IN bill_master_acc1!\n";
            echo json_encode($bill, JSON_PRETTY_PRINT) . "\n";
            $found = true;
            break 2;
        }
    }
    $page++;
} while($page <= 15);

if (!$found) echo "NOT FOUND in first 15 pages\n";

