<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$res = Illuminate\Support\Facades\Http::post('http://192.168.0.186:8080/API/announcements/bill_master.php');
echo substr($res->body(), 0, 500);
