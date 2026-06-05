<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\ExternalBillingService::class);

// Test known working bill 100053
$items1 = $service->getBillDetails("100053");
echo "100053 items count: " . count($items1) . "\n";

// Test the famously broken bill 553624
$items2 = $service->getBillDetails("553624");
echo "553624 items count: " . count($items2) . "\n";


