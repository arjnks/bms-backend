<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$response = Illuminate\Support\Facades\Http::get("http://192.168.0.186:8080/API/announcements/bill_master_acc1.php?page=1");
$data = $response->json();
print_r(array_slice($data["data"] ?? [], 0, 3));

