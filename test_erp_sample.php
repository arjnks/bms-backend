<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$service = app(\App\Services\ExternalBillingService::class);
$bills = $service->getUnpaidBills();
echo json_encode(array_slice($bills, 0, 5), JSON_PRETTY_PRINT);

