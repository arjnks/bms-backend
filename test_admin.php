<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$req = \Illuminate\Http\Request::create("/api/admin/customers/1593/external-bills/LPH-2627-107453/download", "GET");
$admin = \App\Models\User::where("role", "admin")->first();
$req->setUserResolver(function () use ($admin) {
    return $admin;
});
$res = app()->handle($req);
echo $res->getStatusCode()."\n";
echo $res->getContent();

