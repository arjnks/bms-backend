<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$api = app(\App\Services\ExternalBillingService::class);
$details = $api->getBills("", date("Y-m-d"), date("Y-m-d"));
print_r(count($details));

