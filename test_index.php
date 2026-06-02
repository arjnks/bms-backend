<?php
$cust = \App\Models\Customer::whereNotNull("external_cucode")->first();
echo "Testing for cucode: " . $cust->external_cucode . "\n";
$billing = app(\App\Services\ExternalBillingService::class);
$bills = $billing->getBills($cust->external_cucode, "2024-01-01", "2026-12-31");
echo "Count: " . count($bills) . "\n";
if (count($bills) > 0) {
    echo json_encode($bills[0], JSON_PRETTY_PRINT);
}

