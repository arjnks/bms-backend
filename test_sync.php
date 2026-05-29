<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate the POST request to sync
$req = Illuminate\Http\Request::create('/api/v1/admin/sync/customers', 'POST');
$req->headers->set('Accept', 'application/json');
$user = App\Models\User::where('role', 'admin')->first();
$app->make('auth')->login($user);

$res = app()->handle($req);
$data = json_decode($res->getContent(), true);
echo "Status: " . $res->getStatusCode() . "\n";
print_r($data);
