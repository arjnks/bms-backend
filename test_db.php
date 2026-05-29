<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = Illuminate\Http\Request::capture();
$controller = new \App\Http\Controllers\Api\V1\Admin\ReportController();
try {
    $res = $controller->aging($request);
    echo $res->getContent();
} catch (\Exception $e) {
    echo $e->getMessage();
}

