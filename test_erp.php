<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $service = app(App\Services\ExternalBillingService::class);
    $bills = $service->getBills('010311', '2026-05-01', '2026-05-29');
    echo 'ERP is online! Found bills: ' . count($bills) . "\n";
} catch (\Exception $e) {
    echo 'ERP Error: ' . $e->getMessage() . "\n";
}
