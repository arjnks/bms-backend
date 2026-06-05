<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\ExternalBillingService::class);
$bills = \App\Models\Bill::all();
$deleted = 0;

echo "Starting cleanup of empty bills...\n";
foreach ($bills as $bill) {
    $items = $service->getBillDetails($bill->invoice_no);
    if (empty($items)) {
        echo "Deleting empty bill: " . $bill->invoice_no . "\n";
        $bill->delete();
        $deleted++;
    }
}
echo "Finished. Deleted $deleted empty bills from the database.\n";

