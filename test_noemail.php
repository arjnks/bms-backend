<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Call the syncCustomers logic inline
$billing = app(App\Services\ExternalBillingService::class);
$erpCustomers = $billing->getCustomers();

echo "ERP total: " . count($erpCustomers) . "\n";

$noEmail = 0;
foreach ($erpCustomers as $c) {
    $email = strtolower(trim($c['EMAIL'] ?? ''));
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $noEmail++;
    }
}
echo "Without valid email: $noEmail\n";
echo "With valid email: " . (count($erpCustomers) - $noEmail) . "\n";
