<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$api = app(\App\Services\ExternalBillingService::class);
$details = $api->getBillDetails(97576);
print_r($details);

