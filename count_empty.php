<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\ExternalBillingService::class);
$bills = \App\Models\Bill::orderBy("id", "desc")->limit(100)->get();

$emptyCount = 0;
$totalCount = 0;
foreach ($bills as $bill) {
    $totalCount++;
    $items = $service->getBillDetails($bill->invoice_no);
    if (empty($items)) {
        $emptyCount++;
        echo "Empty: " . $bill->invoice_no . "\n";
    }
}
echo "\nTotal checked: $totalCount\n";
echo "Empty bills: $emptyCount\n";

