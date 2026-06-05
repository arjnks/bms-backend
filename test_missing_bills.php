<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$billing = app(\App\Services\ExternalBillingService::class);
$unpaidBills = collect($billing->getUnpaidBills());
$erpInvoiceNos = $unpaidBills->pluck("billno")->map(fn($v) => (string)$v)->filter()->toArray();

$missingBills = \App\Models\Bill::where("is_settled", false)
    ->whereNotIn("invoice_no", $erpInvoiceNos)
    ->get();

$totalExtra = $missingBills->sum(function($b) { return $b->grand_total - $b->amount_received; });
echo "Missing bills count: " . $missingBills->count() . "\n";
echo "Total extra amount (missing from ERP but unsettled in DB): " . $totalExtra . "\n";

