<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bill = app(\App\Services\ExternalBillingService::class)->getBillDetails(107453);
print_r($bill[0] ?? []);
