<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = \App\Models\Bill::where("updated_at", ">=", "2026-06-04 00:00:00")
    ->where("is_settled", true)
    ->count();

echo "Bills settled today (UTC): $count\n";

$ghostCount = \App\Models\Bill::where("updated_at", ">=", \Carbon\Carbon::now()->subHours(2))
    ->where("is_settled", true)
    ->count();
echo "Bills settled in last 2 hours: $ghostCount\n";

