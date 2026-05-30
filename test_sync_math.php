<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$billing = app(\App\Services\ExternalBillingService::class);
$data = $billing->getCustomers();

$emails = [];
$inserted = 0;
foreach($data as $idx => $c) {
    $email = strtolower(trim($c["EMAIL"] ?? ""));
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (!in_array($email, $emails)) {
            $emails[] = $email;
            $inserted++;
        }
    } else {
        $inserted++; // these get a unique placeholder email
    }
}
echo "Total expected after squash: $inserted\n";

