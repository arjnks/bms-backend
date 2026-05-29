<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$c = app(App\Services\ExternalBillingService::class)->getCustomers();
$keys = [];
foreach($c as $x) {
    foreach(array_keys($x) as $k) {
        $keys[$k] = 1;
    }
}
echo "Available fields across all customers:\n";
print_r(array_keys($keys));
