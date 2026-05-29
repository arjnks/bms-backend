<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $service = app(App\Services\ExternalBillingService::class);
    $customers = $service->getCustomers();
    echo 'ERP is online! Found customers: ' . count($customers) . "\n";
} catch (\Exception $e) {
    echo 'ERP Error: ' . $e->getMessage() . "\n";
}
