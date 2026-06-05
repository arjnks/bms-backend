<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request = Illuminate\Support\Facades\Http::buildRequest("GET", "http://example.com", ["page" => 1]);
echo "Method: " . $request->method() . "\n";
echo "URL: " . (string)$request->url() . "\n";

