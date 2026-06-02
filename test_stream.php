<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);
$bill = \App\Models\Bill::orderBy('id', 'desc')->first();
$url = \Illuminate\Support\Facades\URL::temporarySignedRoute('bills.download.stream', now()->addMinutes(30), ['id' => $bill->id]);
echo "URL:\n$url\n";
