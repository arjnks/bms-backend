<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = \App\Models\Bill::where("is_settled", true)
    ->where("payment_status", "paid")
    ->whereNull("payment_verified_at")
    ->whereNull("utr_number")
    ->whereColumn("amount_received", "grand_total")
    ->count();

echo "Bills matching signature: $count\n";

$recentUpdates = \App\Models\Bill::selectRaw("DATE(updated_at) as dt, COUNT(*) as cnt")
    ->groupBy("dt")
    ->orderBy("dt", "desc")
    ->limit(5)
    ->get();

echo "Recent updates:\n";
print_r($recentUpdates->toArray());

