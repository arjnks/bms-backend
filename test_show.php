<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$bill = \App\Models\Bill::first();
if (!$bill) die("No bills.\n");

$req = Illuminate\Http\Request::create("/api/v1/customer/bills/{$bill->id}", "GET");
$req->setUserResolver(function() use ($bill) { return $bill->customer->user; });

$controller = app(\App\Http\Controllers\Api\V1\Customer\BillController::class);
try {
    $res = $controller->show($req, $bill->id);
    echo $res->getContent();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}

