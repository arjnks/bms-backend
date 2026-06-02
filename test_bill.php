<?php
$bill = \App\Models\Bill::where("invoice_no", "LPH/2627/107453")->first();
if ($bill && $bill->customer) {
    echo "Found! Cucode: " . $bill->customer->external_cucode . "\n";
} else {
    echo "Bill not found in local DB.\n";
}

