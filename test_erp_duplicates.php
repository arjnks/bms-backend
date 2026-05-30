<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$billing = app(\App\Services\ExternalBillingService::class);
$data = $billing->getCustomers();
$codes = array_filter(array_column($data, "code"));
$emails = array_filter(array_column($data, "EMAIL"));

echo "Total elements: " . count($data) . "\n";
echo "Unique Codes: " . count(array_unique($codes)) . " (from " . count($codes) . ")\n";
echo "Unique Emails: " . count(array_unique($emails)) . " (from " . count($emails) . ")\n";

