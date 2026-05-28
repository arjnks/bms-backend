<?php
define('LARAVEL_START', microtime(true));
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Call sync directly without HTTP
$controller = app(App\Http\Controllers\Api\V1\Admin\SyncController::class);
$request = Illuminate\Http\Request::create('/api/v1/admin/sync/customers', 'POST');

echo "Starting sync...\n";
$start = microtime(true);
$response = $controller->syncCustomers($request);
$elapsed = round(microtime(true) - $start, 2);

echo "Done in {$elapsed}s\n";
echo $response->getContent() . "\n";

$count = App\Models\User::where('role','customer')->count();
echo "Customers in DB now: $count\n";
