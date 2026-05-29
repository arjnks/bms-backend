<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$res = Illuminate\Support\Facades\Http::asMultipart()->post('http://192.168.0.186:8080/API/announcements/bill_master.php', [
    'from_date' => '2024-01-01',
    'to_date' => '2026-12-31'
]);
echo substr($res->body(), 0, 500) . "\n";
