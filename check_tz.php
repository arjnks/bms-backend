<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$recent = \App\Models\Bill::orderBy("updated_at", "desc")->limit(10)->get(["id", "updated_at", "is_settled", "amount_received", "grand_total"]);
print_r($recent->toArray());

