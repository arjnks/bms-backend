<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$billingService = app()->make(App\Services\ExternalBillingService::class);
$bill = \App\Models\Bill::find(276); // ID of bill 96609
$items = $billingService->getBillDetails((string) $bill->invoice_no);
try {
    $path = $billingService->generateExcel($bill, $items);
    echo "Generated at: " . $path . "\n";
} catch (\Exception $e) {
    echo "File generation failed. Error: " . $e->getMessage() . "\n";
}
