<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\ExternalBillingService::class);
$bill = \App\Models\Bill::where("invoice_no", "like", "%100053%")->with("customer.user")->first();
if (!$bill) die("Bill not found in local DB\n");

$items = $service->getBillDetails($bill->invoice_no);
if (empty($items)) die("Items empty\n");

$customerName = $bill->customer->user->name ?? "Customer";
$billNoStr = $items[0]["BILLNO"] ?? (string) $bill->invoice_no;
$billDate = "2026-05-26";

$pdfPath = $service->generatePdf($items, $billNoStr, $billDate, $customerName);
echo "PDF generated successfully at: " . $pdfPath . "\n";

