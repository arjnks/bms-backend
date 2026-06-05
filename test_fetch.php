<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = app(\App\Http\Controllers\Api\V1\Customer\BillController::class);
$service = app(\App\Services\ExternalBillingService::class);
$bill = \App\Models\Bill::first();

echo "Testing fetchErpLineItems for bill: " . $bill->invoice_no . "\n";

// Use reflection to call private method
$reflection = new ReflectionClass(get_class($controller));
$method = $reflection->getMethod("fetchErpLineItems");
$method->setAccessible(true);

$items = $method->invokeArgs($controller, [$bill, $service]);
echo "Items returned: " . count($items) . "\n";

