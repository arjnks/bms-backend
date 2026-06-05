<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$val = \App\Models\Bill::where("is_settled", false)->sum(\Illuminate\Support\Facades\DB::raw("grand_total - IFNULL(amount_received, 0)"));
echo "Total sum: " . $val . "\n";
$val2 = \App\Models\Bill::where("is_settled", false)->sum("grand_total");
echo "Total grand_total: " . $val2 . "\n";

