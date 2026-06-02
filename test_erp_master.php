<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bills = app(\App\Services\ExternalBillingService::class)->getBills('001402', '2023-01-01', '2026-06-01');
print_r(array_slice($bills, 0, 1));
