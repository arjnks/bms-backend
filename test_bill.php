<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$billno = $argv[1] ?? "100455";
echo "Testing bill details fetch for invoice_no: $billno\n";

$svc = app(\App\Services\ExternalBillingService::class);
$items = $svc->getBillDetails($billno);

if (empty($items)) {
    echo "FAILED: Returned empty. The ERP API does not have this bill.\n";
} else {
    echo "SUCCESS: Found " . count($items) . " line items.\n";
}

