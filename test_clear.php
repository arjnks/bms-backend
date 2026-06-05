<?php
require __DIR__."/vendor/autoload.php";
$app = require_once __DIR__."/bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Storage::disk("r2")->delete(Illuminate\Support\Facades\Storage::disk("r2")->allFiles("bills"));

