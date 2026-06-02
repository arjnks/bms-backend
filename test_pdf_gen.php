<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\ExternalBillingService::class);
$items = $service->getBillDetails("96609");
if (empty($items)) {
    echo "Items is empty!\n";
} else {
    echo "Items found: " . count($items) . "\n";
    $path = $service->generatePdf($items, "LPH/2627/96609", "2026-05-25", "Test Customer");
    echo "PDF Generated at: $path\n";
}

