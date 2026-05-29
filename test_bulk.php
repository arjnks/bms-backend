<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = Illuminate\Http\Request::create('/api/v1/admin/remind/bulk', 'POST', ['customer_ids' => [2]]);
$req->headers->set('Accept', 'application/json');
// Mock authentication
$user = App\Models\User::where('role', 'admin')->first();
$app->make('auth')->login($user);

$res = app()->handle($req);
echo $res->getContent();
