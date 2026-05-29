<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = Illuminate\Http\Request::create('/api/v1/admin/customers', 'GET');
$res = app(App\Http\Controllers\Api\V1\Admin\CustomerController::class)->index($req);

echo "Type: " . get_class($res) . "\n";
echo substr(json_encode($res), 0, 500);
