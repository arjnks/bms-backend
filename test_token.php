<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$customer = \App\Models\Customer::first();
$token = \Illuminate\Support\Str::random(64);
\Illuminate\Support\Facades\Cache::put("external_bill_token_".$token, [
    "billno" => "LPH-2627-107453",
    "customer_id" => $customer->id,
    "format" => "pdf",
], now()->addMinutes(15));
echo "Token: $token\n";

