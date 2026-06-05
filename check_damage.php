<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$recentUnpaid = \App\Models\Bill::where("updated_at", ">=", \Carbon\Carbon::now()->subHours(1))
    ->where("is_settled", false)
    ->where("amount_received", 0)
    ->count();

echo "Recently updated to unpaid: $recentUnpaid\n";

$totalDue = \App\Models\Bill::where("is_settled", false)->sum(\Illuminate\Support\Facades\DB::raw("grand_total - amount_received"));
echo "Total Due currently: " . $totalDue . "\n";

