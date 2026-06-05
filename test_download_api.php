<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$bill = \App\Models\Bill::first();
if (!$bill) die("No bill found\n");
echo "Testing download for bill ID: {$bill->id} Invoice: {$bill->invoice_no}\n";

$request = \Illuminate\Http\Request::create("/api/v1/customer/bills/{$bill->id}/stream/pdf", "GET");
$controller = app(\App\Http\Controllers\Api\V1\Customer\BillController::class);
$response = $controller->stream($request, $bill->id, app(\App\Services\ExternalBillingService::class), "pdf");

echo "Response status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() !== 302 && $response->getStatusCode() !== 200) {
    echo "Content: " . $response->getContent() . "\n";
}

