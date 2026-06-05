<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$bill = \App\Models\Bill::orderBy("id", "desc")->first();
if (!$bill) die("No bill found\n");
echo "Testing download for fresh bill ID: {$bill->id} Invoice: {$bill->invoice_no}\n";

$svc = app(\App\Services\ExternalBillingService::class);
$r2Path = $svc->getCachedFilePath("pdf", $bill->invoice_no);
if (\Illuminate\Support\Facades\Storage::disk("r2")->exists($r2Path)) {
    echo "File exists in R2, deleting it to force live generation...\n";
    \Illuminate\Support\Facades\Storage::disk("r2")->delete($r2Path);
}

$request = \Illuminate\Http\Request::create("/api/v1/customer/bills/{$bill->id}/stream/pdf", "GET");
$controller = app(\App\Http\Controllers\Api\V1\Customer\BillController::class);
$response = $controller->stream($request, $bill->id, $svc, "pdf");

echo "Response status: " . $response->getStatusCode() . "\n";
if ($response->getStatusCode() !== 302 && $response->getStatusCode() !== 200) {
    echo "Content: " . $response->getContent() . "\n";
} else if ($response->getStatusCode() === 302) {
    echo "Redirect URL: " . $response->headers->get("Location") . "\n";
}

